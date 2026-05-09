import { defineStore } from 'pinia'
import axios from 'axios'
import { f7 } from 'framework7-vue'
import { useUserStore } from './user'

export const useEmergencyStore = defineStore('emergency', {
  state: () => ({
    currentAlert:    null,
    aiTriage:        null,
    status:          'idle',
    loading:         false,
    error:           null,
    activeAlerts:    [],
    resolvedAlerts:  [],
    myRequests:      [],
    activeListeners: [],
  }),

  actions: {

    prepareHeaders() {
      const userStore = useUserStore()
      if (!userStore.token) return null
      return {
        headers: {
          Authorization: `Bearer ${userStore.token}`,
          Accept:        'application/json',
        }
      }
    },

    // ─── Victim: fetch my own alerts ───────────────────────
    async fetchMyRequests() {
      const config = this.prepareHeaders()
      if (!config) return

      if (this.myRequests.length === 0) {
            this.loading = true
        }
      try {
        const response = await axios.get('/api/my-emergencies', config)
        const data = response.data.data || response.data
        this.myRequests = Array.isArray(data) ? data : [data]
        this.setupCancellationListeners()
      } catch (error) {
        if (error.response?.status === 401) useUserStore().logout()
      } finally {
        this.loading = false
      }
    },

    // ─── Responder: fetch active alerts ───────────────────
    async fetchActiveAlerts() {
      const config = this.prepareHeaders()
        if (this.activeAlerts.length === 0) {
            this.loading = true
        } try {
        const response = await axios.get('/api/emergencies/active', config)
        this.activeAlerts = response.data.data ?? []
      } catch (error) {
        console.error('Fetch active alerts error:', error)
        this.error = 'Failed to load alerts'
      } finally {
        this.loading = false
      }
    },

    // ─── Responder: fetch resolved/dispatched alerts ───────
    async fetchResolvedAlerts() {
      const config = this.prepareHeaders()
     if (this.resolvedAlerts.length === 0) {
        this.loading = true
    }
      try {
        const response = await axios.get('/api/emergencies/resolved', config)
        this.resolvedAlerts = response.data.data ?? []
      } catch (error) {
        console.error('Fetch resolved alerts error:', error)
        this.error = 'Failed to load resolved alerts'
      } finally {
        this.loading = false
      }
    },

    // ─── Victim: trigger emergency ─────────────────────────
    async triggerEmergency(payload) {
      const config = this.prepareHeaders()
      if (!config) return

      this.loading = true
      try {
        const response = await axios.post('/api/emergency/trigger', payload, config)
        const alertData = response.data.alert || response.data

        this.currentAlert = alertData
        this.aiTriage     = alertData.ai_triage
        this.status       = 'pending'

        const id = alertData.id || alertData.alert_id
        if (id) this.listenForDispatch(id)

        return alertData
      } catch (error) {
        this.error = 'Trigger failed'
        throw error
      } finally {
        this.loading = false
      }
    },

async dispatchToAlert(alertId) {
    const config = this.prepareHeaders()
    if (!config) return { success: false }

    this.loading = true

    try {
        const response = await axios.post(`/api/emergencies/${alertId}/dispatch`, {}, config)

        if (response.data.success) {
            const {
                responder,
                victim_phone,
                needs_sms,
                incident_type,
                alert_id,
            } = response.data

            // 1. Update the local state arrays
            const alertIndex = this.activeAlerts.findIndex(a => a.id === alertId)
            if (alertIndex !== -1) {
                const alert = this.activeAlerts[alertIndex]

                // Update alert object
                alert.status         = 'dispatched'
                alert.dispatched_at  = new Date().toISOString()
                alert.responder_name = responder ?? null

                // Move from active to resolved/dispatched list
                this.resolvedAlerts.unshift(alert)
                this.activeAlerts.splice(alertIndex, 1)
            }

            // 2. Handle SMS Fallback for victims with no data connection
            if (needs_sms && victim_phone) {
                const message = encodeURIComponent(
                    `NETGUARD EMERGENCY\n` +
                    `Your ${incident_type} alert (#${alert_id}) has been received.\n` +
                    `Responder ${responder} is on the way to you.\n` +
                    `Stay calm and remain at your location.\n` +
                    `Help is coming.`
                )
                // Using _system to ensure it opens the native SMS app on mobile
                window.open(`sms:${victim_phone}?body=${message}`, '_system')
            }

            this.loading = false
            return {
                success: true,
                message: 'Dispatch successful',
                responder,
                needs_sms: needs_sms ?? false,
                victim_phone,
                alert_type: incident_type,
                alert_id,
            }
        }

        this.loading = false
        return { success: false, message: response.data.message || 'Dispatch failed' }

    } catch (error) {
        this.loading = false
        console.error('Dispatch error:', error)

        const errorMsg = error.response?.data?.message || 'Server error during dispatch'
        this.error = errorMsg

        return {
            success: false,
            message: errorMsg
        }
    }
},

    // ─── Victim: cancel alert ─────────────────────────────
async cancelEmergencyById(alertId) {
  const config = this.prepareHeaders()
  if (!config) return false

  try {
    const response = await axios.post(`/api/emergencies/${alertId}/cancel`, {}, config)

    if (response.data.success) {
      const index = this.myRequests.findIndex(r => r.id === alertId)
      if (index !== -1) {
        this.myRequests[index].status = 'cancelled'
        this.myRequests[index].cancelled_at = new Date().toISOString()
      }

      if (this.currentAlert?.id === alertId) {
        this.currentAlert = null
        this.status = 'idle'
      }

      this.cleanupListener(alertId)
      return true
    }
    return false
  } catch (error) {
    console.error('Cancel error:', error)
    return false
  }
},

    // ─── Listeners init ────────────────────────────────────
    initializeListener() {
      const userStore = useUserStore()

      if (userStore.isResponder) {
        this.initializeResponderListener()
        this.fetchActiveAlerts()
      }

      if (userStore.isAuthenticated) {
        this.fetchMyRequests()
      }
    },

    destroyListener() {
      if (!window.Echo) return
      window.Echo.leaveChannel('emergency-channel')
      window.Echo.leaveChannel('responder.alerts')
      this.activeListeners.forEach(id => this.cleanupListener(id))
    },

    // ─── Responder: listen for new incoming alerts ─────────
    initializeResponderListener() {
      if (!window.Echo) return

      window.Echo.channel('emergency-channel')
        .listen('.emergency.triggered', (data) => {
          // Avoid duplicates
          const exists = this.activeAlerts.find(a => a.id === data.alert?.id)
          if (!exists) this.activeAlerts.unshift(data.alert)

          const userStore = useUserStore()
          userStore.triggerSiren?.()

          f7.notification.create({
            icon:         '<i class="f7-icons text-red-600">exclamationmark_triangle_fill</i>',
            title:        'NEW EMERGENCY',
            subtitle:     `${data.alert?.ai_triage?.severity?.toUpperCase() ?? 'URGENT'} Priority`,
            text:         `Incident: ${data.alert?.incident?.type ?? 'Incoming Request'}`,
            closeButton:  true,
            closeTimeout: 5000,
          }).open()
        })

      // Listen for cancellations from victims
      window.Echo.channel('responder.alerts')
        .listen('.emergency.cancelled', (data) => {
          this.activeAlerts = this.activeAlerts.filter(a => a.id !== data.alert_id)

          f7.notification.create({
            title:        'Alert Cancelled',
            text:         `Incident #${data.alert_id} was cancelled by the victim.`,
            closeTimeout: 3000,
          }).open()
        })
    },

    // ─── Victim: listen for responder coming ──────────────
    listenForDispatch(alertId) {
      if (!window.Echo || this.activeListeners.includes(alertId)) return

      window.Echo.private(`emergency.${alertId}`)
        .listen('.responder.coming', (data) => {
          if (this.currentAlert) {
            this.currentAlert.responder_name = data.responderName
            this.currentAlert.status         = 'dispatched'
          }
          this.status = 'dispatched'

          f7.notification.create({
            icon:         '<i class="f7-icons text-green-600">checkmark_shield_fill</i>',
            title:        'Help is on the way!',
            subtitle:     `Responder: ${data.responderName}`,
            text:         'A responder has been dispatched to your location.',
            closeTimeout: 6000,
          }).open()
        })

      this.activeListeners.push(alertId)
    },

    // ─── Victim: cancellation listeners ───────────────────
    setupCancellationListeners() {
      if (!window.Echo) return
      this.myRequests.forEach(emergency => {
        const isActive = !['resolved', 'cancelled'].includes(emergency.status)
        if (isActive && !this.activeListeners.includes(emergency.id)) {
          this.listenForCancellation(emergency.id)
        }
      })
    },

    listenForCancellation(alertId) {
      if (!window.Echo || this.activeListeners.includes(alertId)) return

      window.Echo.channel(`emergency.${alertId}`)
        .listen('.emergency.cancelled', () => {
          this.myRequests = this.myRequests.filter(r => r.id !== alertId)
          if (this.currentAlert?.id === alertId) this.reset()
          this.cleanupListener(alertId)
        })

      this.activeListeners.push(alertId)
    },

    cleanupListener(alertId) {
      if (!window.Echo) return
      window.Echo.leaveChannel(`emergency.${alertId}`)
      this.activeListeners = this.activeListeners.filter(id => id !== alertId)
    },

    reset() {
      this.activeListeners.forEach(id => this.cleanupListener(id))
      this.currentAlert   = null
      this.activeAlerts   = []
      this.myRequests     = []
      this.resolvedAlerts = []
      this.status         = 'idle'
      this.error          = null
    },
  },
})
