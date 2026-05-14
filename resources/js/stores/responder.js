import { defineStore } from 'pinia';
import axios from 'axios';
import { useUserStore } from './user';

export const useResponderStore = defineStore('responder', {
  state: () => ({
    liveResponders: [],
    isProbing: false,
    lastUpdated: null
  }),

  actions: {
    async fetchLiveResponders() {
      const userStore = useUserStore();
      
      if (this.isProbing) return;

      this.isProbing = true;

      try {
        const response = await axios.get('/api/responders/live', {
          headers: {
            Authorization: `Bearer ${userStore.token}`
          }
        });

        if (response.data.success) {
          this.liveResponders = response.data.data.map(responder => ({
            ...responder,
            phone: responder.phone || null, 
            distance: responder.distance !== null ? parseFloat(responder.distance) : null
          }));
          
          this.lastUpdated = new Date();
        }
      } catch (error) {
        console.error("CAMARA Network Probe failed:", error);
      } finally {
        this.isProbing = false;
      }
    }
  }
});