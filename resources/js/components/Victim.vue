<template>
  <f7-block class="px-0 pt-0 -mt-6 rounded-t-3xl bg-white min-h-screen">
    <!-- 1. Map & Main Trigger Section -->
    <div class="bg-white rounded-3xl shadow-xl overflow-hidden mx-4 mt-4 border border-gray-100">
      <div class="h-72 relative flex items-center justify-center bg-slate-50">
        <div id="map" class="absolute inset-0 z-0"></div>

        <div class="relative z-10 text-center">
          <f7-button
            v-if="!emergencyStore.isEmergencyActive"
            large
            fill
            round
            color="red"
            class="emergency-btn shadow-2xl rounded-full! bg-red-600!"
            :loading="emergencyStore.loading"
            @click="handleTrigger('General Emergency')"
          >
            <span class="text-xl font-black tracking-wider">TRIGGER<br>EMERGENCY</span>
          </f7-button>
          <p class="text-[10px] text-gray-500 mt-3 font-bold uppercase tracking-widest">
            Instant Dispatch
          </p>
        </div>
      </div>
    </div>

    <!-- 2. Quick-Select Grid -->
    <f7-block-title class="font-bold text-gray-700 mt-6">Select Incident for AI Triage</f7-block-title>
    <f7-block class="mt-2">
      <div class="grid grid-cols-2 gap-4">
        <button
          v-for="type in emergencyTypes"
          :key="type.title"
          @click="handleTrigger(type.title)"
          class="bg-white p-5 rounded-3xl shadow-sm border border-gray-100 flex flex-col items-center text-center space-y-2 active:scale-95 transition-all"
        >
          <div :class="type.bgColor" class="p-4 rounded-2xl">
            <f7-icon :f7="type.icon" :class="type.iconColor" size="28" />
          </div>
          <div>
            <p class="font-bold text-gray-800 text-xs">{{ type.title }}</p>
            <p class="text-[9px] text-gray-400 font-medium">{{ type.desc }}</p>
          </div>
        </button>
      </div>
    </f7-block>

    <!-- 3. Network Intelligence Status -->
    <f7-block-title class="font-bold text-gray-700">Network Intelligence Status</f7-block-title>
    <div class="grid grid-cols-2 gap-4 px-4 pb-12">
    <div class="bg-slate-50 p-5 rounded-2xl border border-gray-100 flex items-center space-x-3">
    <f7-icon f7="antenna_radiowaves_left_right" size="22"
            :color="emergencyStore.qodActive ? 'red' : 'teal'" />
    <div>
        <p class="text-[10px] text-gray-500">Signal Priority</p>
        <p class="font-bold text-xs" :class="emergencyStore.qodActive ? 'text-red-600' : 'text-[#1a5d3b]'">
        {{ emergencyStore.qodActive ? 'QoD Boost Active' : 'Standard Priority' }}
        </p>
    </div>
    </div>

      <div class="bg-slate-50 p-5 rounded-2xl border border-gray-100 flex items-center space-x-3">
        <f7-icon f7="location_fill" size="22" color="teal" />
        <div>
          <p class="text-[10px] text-gray-500">Location Sync</p>
          <p class="font-bold text-xs text-[#1a5d3b]">High Precision</p>
        </div>
      </div>
    </div>
  </f7-block>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useEmergencyStore } from '@/stores/emergency';
import { useUserStore } from '@/stores/user';
import { f7 } from 'framework7-vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const emergencyStore = useEmergencyStore();
const userStore = useUserStore();
const mapInstance = ref(null);

const emergencyTypes = [
  { title: 'Road Accident', icon: 'car_fill', desc: 'Boda/Vehicle', bgColor: 'bg-red-50', iconColor: 'text-red-500' },
  { title: 'Maternal', icon: 'person_2_fill', desc: 'Labor/Urgent', bgColor: 'bg-purple-50', iconColor: 'text-purple-500' },
  { title: 'Medical', icon: 'heart_fill', desc: 'Health Crisis', bgColor: 'bg-blue-50', iconColor: 'text-green-500' },
  { title: 'Security', icon: 'shield_fill', desc: 'Safety/Theft', bgColor: 'bg-orange-50', iconColor: 'text-orange-500' },
  { title: 'Poisoning', desc: 'Chemical / Snake', icon: 'ant_fill', iconColor: 'text-green-400', bgColor: 'bg-green-500/10' },
  { title: 'Crisis', desc: 'Mental Health', icon: 'person_crop_circle_badge_exclamationmark', iconColor: 'text-yellow-400', bgColor: 'bg-yellow-500/10' }
];

const handleTrigger = (symptomType) => {
  f7.dialog.confirm(
    `Confirm ${symptomType} dispatch for ${userStore.givenName}?`,
    'Verify Identity',
    async () => {
      f7.dialog.preloader('Verifying Identity...');

      try {
        const response = await emergencyStore.triggerEmergency({
          phoneNumber: userStore.phoneNumber,
        idDocument: userStore.id_document || userStore.idDocument || "",
          givenName: userStore.givenName,
          familyName: userStore.familyName,
          symptomType: symptomType,

        });

        f7.dialog.close();

        // 2. Success message - navigation or UI updates handled by store state
        if (response && (response.id || response.alert_id)) {
          f7.dialog.alert('🚨 Alert sent! Help is being dispatched.', 'Success');
        }
      } catch (error) {
        f7.dialog.close();
        console.error('Trigger Error:', error);

        f7.dialog.confirm(
          'Internet connection issue. Send emergency via SMS instead?',
          'Connection Notice',
          () => {
            const smsBody = `EMERGENCY: ${symptomType}. User: ${userStore.fullName}`;
            window.location.href = `sms:${userStore.phoneNumber}?body=${encodeURIComponent(smsBody)}`;
          }
        );
      }
    }
  );
};


onMounted(async () => {
  await nextTick();

  const mapContainer = document.getElementById('map');

  if (!mapContainer || mapInstance.value) return;

  try {
    mapInstance.value = L.map('map', {
      zoomControl: false,
      attributionControl: false
    }).setView([0.3476, 32.5825], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
    }).addTo(mapInstance.value);

    setTimeout(() => {
      mapInstance.value?.invalidateSize();
    }, 200);

  } catch (err) {
    console.error("Leaflet Init Error:", err);
  }
});

onBeforeUnmount(() => {
  if (mapInstance.value) {
    mapInstance.value.off();
    mapInstance.value.remove();
    mapInstance.value = null;
  }
});



</script>

<style scoped>
.emergency-btn {
  width: 170px;
  height: 170px;
  font-size: 16px;
  font-weight: 900;
  border: 10px solid white;
  box-shadow: 0 15px 40px rgba(211, 47, 47, 0.4);
  transition: all 0.3s ease;
  z-index: 50;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  line-height: 1.2;
}

.emergency-btn:active {
  transform: scale(0.9);
  box-shadow: 0 5px 15px rgba(211, 47, 47, 0.4);
}

#map {
  width: 100%;
  height: 100%;
}
</style>
