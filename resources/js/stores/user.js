import { defineStore } from 'pinia';
import axios from 'axios';
import { f7 } from 'framework7-vue';
import { useEmergencyStore } from './emergency'; // Correctly linked

export const useUserStore = defineStore('user', {
  state: () => ({
    token: localStorage.getItem('token') || '',
    role: localStorage.getItem('role') || 'user',
    phoneNumber: localStorage.getItem('phone') || '',
    givenName: localStorage.getItem('given_name') || '',
    familyName: localStorage.getItem('family_name') || '',
    idDocument: localStorage.getItem('id_document') || '',
    isKycVerified: localStorage.getItem('kyc_status') === 'true',
    kycStatus: null,
    isListening: false,
    pollingTimer: null,
    sirenInstance: null,
  }),

  getters: {
    fullName: (state) => `${state.givenName} ${state.familyName}`.trim() || 'NetGuard User',
    isAuthenticated: (state) => !!state.token,
    isResponder: (state) => state.role === 'responder',
  },

  actions: {
    hydrateFromStorage() {
      this.token = localStorage.getItem('token') || '';
      if (this.token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
      }
    },

    setUserDetails(details, token = null) {
      this.phoneNumber = details.phone;
      this.givenName = details.given_name;
      this.familyName = details.family_name;
      this.idDocument = details.id_document;
      this.role = details.role || 'user';
      this.isKycVerified = !!details.is_kyc_verified;

      if (token) {
        this.token = token;
        localStorage.setItem('token', token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
      }

      localStorage.setItem('phone', this.phoneNumber);
      localStorage.setItem('given_name', this.givenName);
      localStorage.setItem('family_name', this.familyName);
      localStorage.setItem('role', this.role);
      localStorage.setItem('kyc_status', String(this.isKycVerified));
    },

    async loginUser(phoneNumber) {
  try {
    const trimmedPhone = phoneNumber.trim();

    // We send both keys to satisfy Laravel's validation
    const response = await axios.post('/api/login', {
      phone: trimmedPhone,
      password: trimmedPhone
    });

    if (response.data.success) {
      // Use the details from the server to populate the store
      this.setUserDetails(response.data.user, response.data.token);

      // If they are a responder, start looking for alerts immediately
      if (response.data.user.role === 'responder') {
        this.startPolling();
      }

      return true;
    }
    return false;
  } catch (error) {
    console.error('Login error:', error.response?.data || error.message);
    throw error;
  }
},

    startPolling() {
      if (this.pollingTimer || !this.isResponder) return;
      const emergencyStore = useEmergencyStore();

      const poll = async () => {
        if (!this.token) {
          this.stopPolling();
          return;
        }
        try {
          // Pointing to the new home of this action
          await emergencyStore.fetchActiveAlerts();
          this.pollingTimer = setTimeout(poll, 5000);
        } catch (error) {
          this.stopPolling();
        }
      };
      poll();
    },

    stopPolling() {
      if (this.pollingTimer) {
        clearTimeout(this.pollingTimer);
        this.pollingTimer = null;
      }
    },

    triggerSiren() {
      if (!this.sirenInstance) this.sirenInstance = new Audio('/assets/siren.mp3');
      this.sirenInstance.loop = true;
      this.sirenInstance.play().catch(() => {});
      setTimeout(() => this.stopSiren(), 10000);
    },

    stopSiren() {
      if (this.sirenInstance) {
        this.sirenInstance.pause();
        this.sirenInstance.currentTime = 0;
      }
    },

    logout() {
      const emergencyStore = useEmergencyStore();
      this.stopPolling();
      this.stopSiren();
      emergencyStore.reset(); // Clean up emergency state too

      this.token = '';
      localStorage.clear();
      delete axios.defaults.headers.common['Authorization'];

      f7.views.main.router.navigate('/login', {
        reloadCurrent: true,
        ignoreCache: true
      });
    },
  },
});
