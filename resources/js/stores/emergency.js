import { defineStore } from 'pinia';
import axios from 'axios';
import { f7 } from 'framework7-vue';
import { useUserStore } from './user';

export const useEmergencyStore = defineStore('emergency', {
  state: () => ({
    currentAlert: null,
    aiTriage: null,
    status: 'idle',
    loading: false,
    error: null,
    sessionToken: null,
    qodActive: false,
    activeAlerts: [],         // For Responders: list of incoming alerts
    myRequests: [],           // For Victims: list of sent requests
    activeListeners: [],      // Track IDs of active Echo channels to prevent duplicates
  }),

  getters: {
    isEmergencyActive: (state) => state.status !== 'idle' && state.currentAlert !== null,

    severityColor: (state) => {
      if (!state.aiTriage) return 'gray';
      const colors = { critical: 'red', high: 'orange', medium: 'blue' };
      return colors[state.aiTriage.severity] || 'green';
    }
  },

  actions: {
    /**
     * Fetches requests sent by the victim and sets up tracking.
     */
    async fetchMyRequests() {
      const userStore = useUserStore();
      if (!userStore.token) return;

      this.loading = true;
      this.error = null;

      try {
        const response = await axios.get('/api/my-emergencies', {
          headers: {
            'Authorization': `Bearer ${userStore.token}`,
            'Accept': 'application/json'
          }
        });

        this.myRequests = response.data.data || response.data;

        this.setupCancellationListeners();

      } catch (error) {
        this.error = error.response?.data?.message || 'Failed to sync emergencies';
        if (error.response?.status === 401) userStore.logout();
      } finally {
        this.loading = false;
      }
    },


    setupCancellationListeners() {
      if (!window.Echo) return;

      this.myRequests.forEach(emergency => {
        const isActive = !['resolved', 'cancelled'].includes(emergency.status);
        if (isActive && !this.activeListeners.includes(emergency.id)) {
          this.listenForCancellation(emergency.id);
        }
      });
    },

    listenForCancellation(alertId) {
      if (!window.Echo || this.activeListeners.includes(alertId)) return;

      try {
        window.Echo.channel(`emergency.${alertId}`)
          .listen('.emergency.cancelled', (data) => {
            this.myRequests = this.myRequests.filter(req => req.id !== alertId);

            if (this.currentAlert?.id === alertId) {
              this.reset();
              f7.dialog.alert('Your emergency request was cancelled.', 'Alert');
            }

            this.cleanupListener(alertId);
          })
          .listen('.emergency.status.updated', (data) => {
             // Handle status transitions (e.g., 'on_way')
             if (this.currentAlert?.id === alertId) {
               this.status = data.status;
             }
          });

        this.activeListeners.push(alertId);
      } catch (error) {
        console.error(`Echo subscription failed for ${alertId}:`, error);
      }
    },

    /**
     * Trigger a new emergency and immediately start listening for responders.
     */
    async triggerEmergency(payload) {
      const userStore = useUserStore();
      this.loading = true;
      this.error = null;

      try {
        const response = await axios.post('/api/emergency/trigger', payload, {
          headers: {
            'Authorization': `Bearer ${userStore.token}`,
            'Accept': 'application/json'
          }
        });

        const alertData = response.data.alert || response.data;

        this.currentAlert = alertData;
        this.aiTriage = alertData.ai_triage;
        this.sessionToken = alertData.session_token;
        this.qodActive = alertData.qod_active || false;
        this.status = 'pending';

        if (alertData.id || alertData.alert_id) {
          const id = alertData.id || alertData.alert_id;
          this.listenForCancellation(id);
        }

        return alertData;
      } catch (error) {
        this.error = error.response?.data?.message || 'Trigger failed';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    initializeListener() {
      const userStore = useUserStore();

      if (userStore.isResponder) {
        this.initializeResponderListener();
      }

      if (userStore.isAuthenticated) {
        this.fetchMyRequests();
      }

      console.log("Emergency listeners initialized.");
    },


    initializeResponderListener() {
      if (!window.Echo) return;

      window.Echo.channel('emergency-channel')
        .listen('.emergency.triggered', (data) => {
          this.activeAlerts.unshift(data.alert);
          this.notifyResponder(data);
        });
    },

    notifyResponder(data) {
      f7.notification.create({
        icon: '<i class="f7-icons text-red-600">exclamationmark_triangle_fill</i>',
        title: 'NEW EMERGENCY',
        subtitle: `${data.alert?.ai_triage?.severity?.toUpperCase() || 'URGENT'} Priority`,
        text: `Condition: ${data.alert?.ai_triage?.likely_condition || 'Incoming Incident'}`,
        closeButton: true,
        closeTimeout: 5000,
      }).open();

      const audio = new Audio('/assets/siren.mp3');
      audio.play().catch(() => console.warn("Audio interaction required"));
    },


    cleanupListener(alertId) {
      if (window.Echo) {
        window.Echo.leaveChannel(`emergency.${alertId}`);
        this.activeListeners = this.activeListeners.filter(id => id !== alertId);
      }
    },

   
    reset() {
      this.activeListeners.forEach(id => this.cleanupListener(id));

      this.currentAlert = null;
      this.aiTriage = null;
      this.status = 'idle';
      this.sessionToken = null;
      this.qodActive = false;
      this.loading = false;
    }
  }
});
