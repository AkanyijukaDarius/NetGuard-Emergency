<template>
  <f7-page name="home">
     <!-- Top Green Header -->
    <div class="bg-[#1a5d3b] sticky top-0 z-50 text-white pt-4 pb-4 px-4 shadow-lg">
      <div class="flex justify-between items-center">
        <div class="flex items-center space-x-3">
          <f7-icon f7="waveform_path_ecg" size="40" color="white" />
          <span class="text-2xl font-bold">NetGuard Emergency</span>
        </div>
        <f7-link class="relative" v-if="userStore.role === 'responder'" @click="userStore.resetNotificationCount">
        <f7-icon f7="bell_fill" size="24" color="white" />
        <span
            v-if="userStore.unreadCount > 0"
            class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full"
        >
            {{ userStore.unreadCount }}
        </span>
        </f7-link>
      </div>

      <div class="pb-1  mt-2">
        <span class="text-md opacity-80">Welcome,</span>
        <span class="font-bold text-lg"> {{ userStore.givenName }}</span>
      </div>
    </div>

    <!-- Main Content Area -->
    <f7-block class="px-0 pt-0 -mt-6 rounded-t-3xl bg-white min-h-screen">
      <Victim v-if="userStore.role === 'user'" />
      <Responder v-else-if="userStore.role === 'responder'" />
    </f7-block>
  </f7-page>
</template>

<script setup>
import { onMounted } from 'vue';
import { useUserStore } from '@/stores/user';
import { f7 } from 'framework7-vue';
import Victim from '@/components/Victim.vue';
import Responder from '@/components/Responder.vue';

const userStore = useUserStore();

onMounted(async () => {
  f7.preloader.hide();

  if (!userStore.token) {
    userStore.hydrateFromStorage();
  }

  if (userStore.isAuthenticated) {
    userStore.startPolling();
  }
});
</script>
