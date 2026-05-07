import { defineStore } from 'pinia';
import axios from 'axios';
import { useUserStore } from './user';

export const useResponderStore = defineStore('responder', {
  state: () => ({
    liveResponders: [],
    isProbing: false,
  }),

  actions: {
    async fetchLiveResponders() {
      const userStore = useUserStore();
      this.isProbing = true;

      try {
        const response = await axios.get('/api/responders/live', {
          headers: {
            Authorization: `Bearer ${userStore.token}`
          }
        });

        if (response.data.success) {
          this.liveResponders = response.data.data;
        }
      } catch (error) {
        console.error("Probe failed:", error);
      } finally {
        this.isProbing = false;
      }
    }
  }
});
