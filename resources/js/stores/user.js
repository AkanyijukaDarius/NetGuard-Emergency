import { defineStore } from 'pinia';
import axios from 'axios';
import { f7 } from 'framework7-vue';

export const useUserStore = defineStore('user', {
  state: () => ({
    token: localStorage.getItem('token') || '',
    role: localStorage.getItem('role') || 'user',
    phoneNumber: localStorage.getItem('phone') || '',
    givenName: localStorage.getItem('given_name') || '',
    familyName: localStorage.getItem('family_name') || '',
    idDocument: localStorage.getItem('id_document') || '',
    isKycVerified: localStorage.getItem('kyc_status') === 'true',
    activeAlerts: [],
    unreadCount: 0,
    isListening: false,
    pollingTimer: null,
    sirenInstance: null,
    lastChecked: null,
  }),

  getters: {
    fullName: (state) =>
      `${state.givenName} ${state.familyName}`.trim() || 'NetGuard User',
    isAuthenticated: (state) => !!state.token,
    isResponder: (state) => state.role === 'responder',
    isRegistered: (state) => !!state.phoneNumber,
  },

  actions: {
    hydrateFromStorage() {
      this.token = localStorage.getItem('token') || '';
      this.role = localStorage.getItem('role') || 'user';
      this.phoneNumber = localStorage.getItem('phone') || '';
      this.givenName = localStorage.getItem('given_name') || '';
      this.familyName = localStorage.getItem('family_name') || '';
      this.isKycVerified = localStorage.getItem('kyc_status') === 'true';

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
      this.isKycVerified = details.is_kyc_verified ?? this.isKycVerified;

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

    // --- Authentication Actions ---
    async registerUser(details) {
      try {
        const response = await axios.post('/api/register', {
          phone: details.phone, // No formatting applied
          given_name: details.given_name,
          family_name: details.family_name,
          id_document: details.id_document,
          role: details.role || 'user',
          responder_code: details.responder_code || null,
        });

        if (response.data.success) {
          this.setUserDetails(response.data.user, response.data.token);
          return true;
        }
        return false;
      } catch (error) {
        console.error('Registration error:', error);
        throw error;
      }
    },

    async loginUser(phoneNumber) {
      try {
        const response = await axios.post('/api/login', { phone: phoneNumber.trim() });

        if (response.data.success) {
          this.setUserDetails(response.data.user, response.data.token);
          return true;
        }
        return false;
      } catch (error) {
        console.error('Login error:', error);
        throw error;
      }
    },

    // --- Real-Time Polling ---
    async fetchActiveAlerts() {
      if (!this.token) return;

      try {
        const response = await axios.get('/api/emergencies/active', {
          params: { since: this.lastChecked },
        });

        if (response.data?.data) {
          const newAlerts = response.data.data;
          if (newAlerts.length > 0) {
            this.activeAlerts = [...newAlerts, ...this.activeAlerts].filter(
              (alert, index, self) => index === self.findIndex((a) => a.id === alert.id)
            );
          }
        }
        this.lastChecked = response.data?.timestamp || new Date().toISOString();
      } catch (error) {
        if (error.response?.status === 401) {
          this.logout();
        }
      }
    },

    startPolling() {
      if (this.pollingTimer) return;

      const poll = async () => {
        if (!this.token) return;
        await this.fetchActiveAlerts();
        this.pollingTimer = setTimeout(poll, 5000);
      };

      poll();
    },

    stopPolling() {
      if (this.pollingTimer) {
        clearTimeout(this.pollingTimer);
        this.pollingTimer = null;
      }
    },

    // --- Emergency Logic ---
    triggerSiren() {
      if (!this.sirenInstance) {
        this.sirenInstance = new Audio('/assets/siren.mp3');
      }
      this.sirenInstance.loop = true;
      this.sirenInstance.play().catch((err) => console.warn('Autoplay blocked', err));
      setTimeout(() => this.stopSiren(), 10000);
    },

    stopSiren() {
      if (this.sirenInstance) {
        this.sirenInstance.pause();
        this.sirenInstance.currentTime = 0;
      }
    },

    logout() {
      this.stopPolling();
      this.stopSiren();

      this.token = '';
      this.activeAlerts = [];
      this.lastChecked = null;
      this.isListening = false;
      localStorage.removeItem('token');
      delete axios.defaults.headers.common['Authorization'];

      f7.views.main.router.navigate('/login/', {
        reloadCurrent: true,
        ignoreCache: true
      });
    },
  },
});
