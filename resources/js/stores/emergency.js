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

    playNotificationSound(file) {
        console.log(`%c[Audio] Attempting to play: ${file}`, "color: blue; font-style: italic;")
      const audio = new Audio(file);

      audio.play()
        .then(() => console.log(`%c[AUDIO-SUCCESS]  Playing: ${file}`, "color: green;"))
        .catch(e => {
          console.warn(`[NetGuard Audio] BLOCKED: ${file}. User interaction required.`, e);
          f7.toast.create({
            text: "Audio alert blocked. Please tap the screen to enable sounds.",
            closeTimeout: 3000,
            position: 'center'
          }).open();
        });
    },

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


        this.listenForDispatch(alertData.id);

        return alertData
      } catch (error) {
        this.error = 'Trigger failed'
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchMyRequests() {
      const config = this.prepareHeaders()
      if (!config) return
      if (this.myRequests.length === 0) this.loading = true
      try {
        const response = await axios.get('/api/my-emergencies', config)
        const data = response.data.data || response.data
        this.myRequests = Array.isArray(data) ? data : [data]

        this.myRequests.forEach(req => {
          if (req.status === 'pending' || req.status === 'dispatched') {
            this.listenForDispatch(req.id);
          }
        });
      } catch (error) {
        if (error.response?.status === 401) useUserStore().logout()
      } finally {
        this.loading = false
      }
    },

    async fetchActiveAlerts() {
      const config = this.prepareHeaders()
      if (this.activeAlerts.length === 0) this.loading = true
      try {
        const response = await axios.get('/api/emergencies/active', config)
        this.activeAlerts = response.data.data ?? []
      } catch (error) {
        this.error = 'Failed to load alerts'
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
          const alertIndex = this.activeAlerts.findIndex(a => a.id === alertId)
          if (alertIndex !== -1) {
            const alert = this.activeAlerts[alertIndex]
            alert.status = 'dispatched'
            this.resolvedAlerts.unshift(alert)
            this.activeAlerts.splice(alertIndex, 1)
          }

          if (response.data.needs_sms && response.data.victim_phone) {
            const msg = encodeURIComponent(`NETGUARD: Responder ${response.data.responder} is coming. Stay calm.`);
            window.open(`sms:${response.data.victim_phone}?body=${msg}`, '_system');
          }

          return { success: true, responder: response.data.responder }
        }
      } catch (error) {
        return { success: false, message: error.response?.data?.message || 'Dispatch failed' }
      } finally {
        this.loading = false
      }
    },

    async fetchResolvedAlerts() {
      const config = this.prepareHeaders();
      if (!config) return;

      if (this.resolvedAlerts.length === 0) this.loading = true;

      try {

        const response = await axios.get('/api/emergencies/resolved', config);

        this.resolvedAlerts = response.data.data ?? response.data;
      } catch (error) {
        console.error("Error fetching dispatched alerts:", error);
        this.error = 'Failed to load dispatched alerts';
      } finally {
        this.loading = false;
      }
    },
//resolve an emergency after completion
    async completeMission(alertId) {
      const config = this.prepareHeaders();
      try {
        const response = await axios.post(`/api/emergencies/${alertId}/resolve`, {}, config);
        if (response.data.success) {
          this.activeAlerts = this.activeAlerts.filter(a => a.id !== alertId);
          this.currentAlert = null;

          f7.dialog.alert('Emergency Resolved!', 'Success');
          f7.view.main.router.back();
        }
      } catch (error) {
        f7.dialog.alert('Error closing the alert.');
      }
    },

    async cancelEmergencyById(alertId) {
      const config = this.prepareHeaders()
      if (!config) return

      this.loading = true
      try {
        const response = await axios.post(`/api/emergencies/${alertId}/cancel`, {}, config)

        if (response.data.success) {

          this.cleanupListener(`emergency.${alertId}`);

          if (this.currentAlert && this.currentAlert.id === alertId) {
            this.currentAlert.status = 'cancelled';
          }
          this.status = 'idle';

          f7.toast.create({
            text: 'Emergency cancelled successfully.',
            closeTimeout: 3000,
            cssClass: 'bg-orange-600'
          }).open();

          return true;
        }
      } catch (error) {
        console.error("Cancellation failed:", error);
        f7.alert(error.response?.data?.message || 'Could not cancel emergency.');
        return false;
      } finally {
        this.loading = false
      }
    },


   initializeListener() {
      const userStore = useUserStore()
      if (!userStore.isAuthenticated) {
    console.log('[NetGuard] Unauthenticated user. Listeners skipped.');
    return;
  }

      if (userStore.isResponder) {
        this.initializeResponderListener()
        this.fetchActiveAlerts()
      }

      if (userStore.isAuthenticated && !userStore.isResponder) {
        this.fetchMyRequests()
      }
    },

  initializeResponderListener() {
  if (!window.Echo) {
    console.error('[NetGuard] Echo is not initialized. Check your broadcast configuration.');
    return;
  }

  const userStore = useUserStore();

  if (!userStore.isResponder) {
    console.log('[NetGuard] Access Denied: User is not a responder. Listeners aborted.');
    return;
  }

  console.log('[NetGuard] Initializing Responder Listeners...');

  window.Echo.channel('emergency-channel')
    .listen('.EmergencyTriggered', (data) => {
      
      if (data.alert?.user_id === userStore.userId) return;

      const exists = this.activeAlerts.find(a => a.id === data.alert?.id);
      if (!exists) {
        this.activeAlerts.unshift(data.alert);
      }

      this.playNotificationSound('/assets/siren.mp3');

      f7.notification.create({
        icon: '<i class="f7-icons text-red-600">exclamationmark_triangle_fill</i>',
        title: 'NEW EMERGENCY ALERT',
        text: `Type: ${data.alert?.incident?.type || 'Medical Request'}`,
        subtitle: `Ref: #${data.alert?.id}`,
        closeButton: true,
        on: {
          close: () => {
            userStore.stopSiren?.();
          }
        }
      }).open();
    });

  window.Echo.channel('responder.alerts')
    .listen('.emergency.cancelled', (data) => {
      console.log(`[NetGuard] Incident #${data.alert_id} was cancelled by the user.`);

      this.activeAlerts = this.activeAlerts.filter(a => a.id !== data.alert_id);

      if (this.currentAlert && this.currentAlert.id === data.alert_id) {
        this.currentAlert = null;
        this.status = 'idle';

        f7.notification.create({
          icon: '<i class="f7-icons text-orange-600">xmark_octagon_fill</i>',
          title: 'Incident Cancelled',
          text: `The request #${data.alert_id} is no longer active.`,
          closeTimeout: 5000,
        }).open();

        f7.view.main.router.back();
      }
    });
},

    listenForDispatch(alertId) {
      const channelName = `emergency.${alertId}`;

      if (!window.Echo || this.activeListeners.includes(channelName)) return;


      window.Echo.private(channelName)
        .listen('.ResponderDispatched', (data) => {

          this.playNotificationSound('/assets/dispatched_chime.mp3');

          if (this.currentAlert && this.currentAlert.id === alertId) {
            this.currentAlert.status = 'dispatched';
            this.currentAlert.responder_name = data.responder?.given_name;
          }
          this.status = 'dispatched';

          f7.notification.create({
            icon: '<i class="f7-icons text-green-600">checkmark_shield_fill</i>',
            title: 'Help is on the way!',
            text: `Responder ${data.responder?.given_name} has been dispatched to your location.`,
            closeTimeout: 8000,
          }).open();
        });

      this.activeListeners.push(channelName);
    },

    cleanupListener(channelName) {
      if (!window.Echo) return
      window.Echo.leaveChannel(channelName)
      this.activeListeners = this.activeListeners.filter(id => id !== channelName)
    },

    reset() {
      this.activeListeners.forEach(id => this.cleanupListener(id))
      this.currentAlert   = null
      this.activeAlerts   = []
      this.myRequests     = []
      this.resolvedAlerts = []
      this.status         = 'idle'
    }
  }
})
