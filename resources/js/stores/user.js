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
      localStorage.setItem('id_document', this.idDocument || '');
      localStorage.setItem('role', this.role);
      localStorage.setItem('kyc_status', String(this.isKycVerified));
    },

    async registerUser(userData) {
  try {
    const response = await axios.post('/api/register', {
      phone: userData.phone.trim(),
      given_name: userData.given_name.trim(),
      family_name: userData.family_name.trim(),
      id_document: userData.id_document.trim(),
      role: userData.role,
      responder_code: userData.responder_code || null,
      password: userData.phone.trim(),
    });

    if (response.data.success) {

        this.setUserDetails(response.data.user, response.data.token);


      if (response.data.user.role === 'responder') {
        this.startPolling();
      }

      return true;
    }

    return false;
  } catch (error) {
    const errorMsg = error.response?.data?.message || error.message;
    console.error('Registration logic failed:', errorMsg);
    throw error;
  }
},

    async loginUser(phoneNumber) {
  try {
    const trimmedPhone = phoneNumber.trim();

    const response = await axios.post('/api/login', {
      phone: trimmedPhone,
      password: trimmedPhone
    });

    if (response.data.success) {
      this.setUserDetails(response.data.user, response.data.token);

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
    //verify KYC status
    async checkVerificationStatus() {
        f7.dialog.preloader('Verifying with Network...');
        try {
            const response = await axios.get('/api/user/verify-kyc', {
                headers: {
                    Authorization: `Bearer ${this.token}`,
                    Accept: 'application/json',
                }
            });

            f7.dialog.close();

            this.kycStatus = response.data.status;
            this.isKycVerified = !!response.data.is_verified;

            localStorage.setItem('kyc_status', String(this.isKycVerified));

            if (this.isKycVerified) {
                f7.toast.create({
                    text: '✅ Identity Verified via CAMARA',
                    closeTimeout: 3000,
                    color: 'green'
                }).open();
            } else {
                f7.dialog.alert(`Current status: ${this.kycStatus}`, 'KYC Status');
            }

        } catch (error) {
            f7.dialog.close();

            console.error('Error checking KYC status:', error.response?.data || error.message);

            if (error.response && error.response.status === 422) {
                const serverMessage = error.response.data.message || 'Identity mismatch detected.';
                f7.dialog.alert(serverMessage, 'Verification Failed');
            } else {
                f7.dialog.alert(
                    'Unable to reach the NetGuard verification server. Please check your connection.',
                    'Connection Error'
                );
            }
        }
    },

    logout() {
      const emergencyStore = useEmergencyStore();
      this.stopPolling();
      this.stopSiren();
      emergencyStore.reset();

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
