<template>
  <f7-page>
    <!-- Green Header -->
    <div class="bg-[#1a5d3b] text-white sticky top-0 z-50 pt-4 pb-2 px-4 shadow-lg">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <f7-link back>
            <f7-icon f7="chevron_left" size="24" color="white" />
          </f7-link>
          <f7-icon f7="person_2_fill" size="28" color="white" />
          <span class="text-2xl font-bold">Responders</span>
        </div>
        <!-- Link now uses manualRefresh and local spinning state -->
        <f7-link @click="manualRefresh">
          <f7-icon
            f7="arrow_clockwise"
            size="22"
            color="white"
            :class="{'animate-spin': isManualRefreshing}"
          />
        </f7-link>
      </div>
      <!-- Live Indicator -->
      <p class="text-sm opacity-75 mt-1 pl-1 flex items-center">
        <span class="relative flex h-2 w-2 mr-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
        </span>
        Live Network (Auto-syncing)
      </p>
    </div>

    <f7-block class="pb-10!">
      <!-- Search -->
      <f7-searchbar
        placeholder="Search live responders..."
        v-model="searchQuery"
        :clear-button="true"
      />

      <div v-if="responderStore.isProbing && responderStore.liveResponders.length === 0" class="text-center py-6">
        <f7-preloader color="green"></f7-preloader>
        <p class="text-xs text-gray-500 mt-2">Connecting to CAMARA network...</p>
      </div>

      <!-- Stats -->
      <div v-else class="grid grid-cols-2 gap-4 my-6">
        <div class="bg-white rounded-2xl p-5 shadow-sm text-center border border-gray-100">
          <div class="text-3xl font-bold text-[#1a5d3b]">
            {{ responderStore.liveResponders?.length ?? 0 }}
          </div>
          <div class="text-xs text-gray-500 mt-1">Total Found</div>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm text-center border border-gray-100">
          <div class="text-3xl font-bold text-green-600">{{ onlineCount }}</div>
          <div class="text-xs text-gray-500 mt-1">Online Now</div>
        </div>
      </div>

      <!-- Responders List -->
      <f7-block-title class="mb-3">Nearby Responders (Live)</f7-block-title>

      <f7-list media-list v-if="responderStore.liveResponders.length > 0">
        <f7-list-item
          v-for="responder in filteredResponders"
          :key="responder.id"
          :title="responder.name"
          :subtitle="responder.role || 'Community Health Worker'"
          link="#"
          @click="viewResponder(responder)"
        >
          <template #text>
            <div class="flex items-center overflow-hidden h-5">
              <transition name="slide-up" mode="out-in">
                <span
                  :key="responder.distance"
                  class="font-bold text-blue-700"
                >
                  {{ (typeof responder.distance === 'number') ? `${responder.distance} km away` : 'Locating...' }}
                </span>
              </transition>
              <span class="mx-1">•</span>
              <span>Mode: {{ responder.connectivity || 'Cellular' }}</span>
            </div>
          </template>

          <f7-icon
            slot="media"
            f7="person_crop_circle"
            :color="responder.status === 'Online' ? 'green' : (responder.status === 'SMS Only' ? 'orange' : 'gray')"
            size="40"
          />
          <div slot="after" class="text-right">
            <div :class="getStatusClass(responder.status)">
              {{ responder.status || 'Offline' }}
            </div>
          </div>
        </f7-list-item>
      </f7-list>

      <div v-if="!responderStore.isProbing && filteredResponders.length === 0" class="text-center py-12">
        <f7-icon f7="person_crop_circle_badge_exclam" size="60" class="text-gray-300 mx-auto mb-4" />
        <h3 class="text-gray-500 font-medium">No responders found</h3>
        <p class="text-sm text-gray-400 mt-2">Nearby VHTs will appear here based on live network reachability.</p>
      </div>
    </f7-block>
  </f7-page>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { f7 } from 'framework7-vue'
import { useResponderStore } from '../stores/responder'

const responderStore = useResponderStore()
const searchQuery = ref('')
const isManualRefreshing = ref(false)
let pollingTimer = null


const startPolling = () => {
  pollingTimer = setInterval(async () => {
    if (!responderStore.isProbing) {
      await responderStore.fetchLiveResponders();
    }
  }, 20000);
}


const manualRefresh = async () => {
  if (isManualRefreshing.value) return;

  isManualRefreshing.value = true;

  f7.toast.create({
    text: 'Updating responders...',
    closeTimeout: 800,
    position: 'top',
  }).open();

  await responderStore.fetchLiveResponders();

  isManualRefreshing.value = false;
};

const filteredResponders = computed(() => {
  const list = responderStore.liveResponders ?? [];
  if (!searchQuery.value) return list;

  const q = searchQuery.value.toLowerCase();
  return list.filter(r =>
    (r.name?.toLowerCase().includes(q)) ||
    (r.role?.toLowerCase().includes(q))
  );
});

const onlineCount = computed(() => {
  return (responderStore.liveResponders ?? []).filter(r => r.status === 'Online').length;
});

const getStatusClass = (status) => {
  const base = "text-xs px-3 py-1 rounded-full ";
  if (status === 'Online') return base + "bg-green-100 text-green-700";
  if (status === 'SMS Only') return base + "bg-orange-100 text-orange-700";
  return base + "bg-gray-100 text-gray-500";
};

const viewResponder = (responder) => {
  const phoneNumber = responder.phone;

  f7.dialog.create({
    title: responder.name,
    content: `
      <div class="list no-hairlines-md" style="margin: 0;">
        <ul>
          <li class="item-content" style="padding-left: 0;">
            <div class="item-inner">
              <div class="item-title text-gray-500">Status</div>
              <div class="item-after font-bold">${responder.status}</div>
            </div>
          </li>
            <li class="item-content" style="padding-left: 0;">
            <div class="item-inner">
              <div class="item-title text-gray-500">Connectivity</div>
              <div class="item-after font-bold">${responder.connectivity}</div>
            </div>
          </li>
          <li class="item-content" style="padding-left: 0;">
            <div class="item-inner">
              <div class="item-title text-gray-500">Distance</div>
              <div class="item-after font-bold">${responder.distance ?? '—'} km</div>
            </div>
          </li>
          <li class="item-content" style="padding-left: 0;">
            <div class="item-inner">
              <div class="item-title text-gray-500">Phone</div>
              <div class="item-after font-mono">${phoneNumber || 'Not available'}</div>
            </div>
          </li>
        </ul>
      </div>
    `,
    buttons: [
      {
        text: 'Call Responder',
        bold: true,
        color: 'green',
        onClick: () => {
          if (phoneNumber) {
            window.location.href = `tel:${phoneNumber}`;
          } else {
            f7.toast.create({ text: 'No phone number available', closeTimeout: 2000 }).open();
          }
        }
      },
      {
        text: 'Close',
        close: true
      }
    ]
  }).open();
};

onMounted(() => {
  responderStore.fetchLiveResponders();
  startPolling();
});

onUnmounted(() => {
  if (pollingTimer) clearInterval(pollingTimer);
});
</script>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.4s ease-out;
}

.slide-up-enter-from {
  transform: translateY(10px);
  opacity: 0;
}

.slide-up-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}
</style>
