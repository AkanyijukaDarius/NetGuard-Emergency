<template>
  <f7-page class="bg-slate-50">
    <div class="p-4 md:flex md:gap-6">
      <!-- Summary Stats -->
      <div class="mb-4 md:w-1/3">
        <div class="grid grid-cols-1 gap-4">
          <div class="p-5 bg-white border-l-4 border-red-600 rounded-2xl shadow-sm">
            <h3 class="text-xs font-bold tracking-wider text-gray-500 uppercase">Active Alerts</h3>
            <p class="text-5xl font-black text-gray-900 mt-1">{{ alerts.length }}</p>
          </div>
          <div class="p-5 bg-white border-l-4 border-green-600 rounded-2xl shadow-sm">
            <h3 class="text-xs font-bold tracking-wider text-gray-500 uppercase">Your Location</h3>
            <p class="text-xl font-semibold text-gray-700">Makerere University Campus</p>
            <p class="text-sm text-emerald-600 mt-1">Ready to Respond</p>
          </div>
        </div>
      </div>

      <!-- Emergency Feed -->
      <div class="md:w-2/3">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-2xl font-bold text-slate-700">Live Incident Feed</h2>
          <f7-button v-if="alerts.length > 0" small @click="refreshAlerts" color="blue" outline>
            <f7-icon f7="arrow_clockwise" class="mr-1" size="14" /> Refresh
          </f7-button>
        </div>

        <!-- Empty State -->
        <div v-if="alerts.length === 0" class="text-center py-16 bg-white rounded-3xl border border-dashed border-slate-300">
          <f7-icon f7="bell_slash" size="48" class="text-slate-300" />
          <p class="text-slate-500 mt-4 font-medium">No active emergencies at the moment</p>
        </div>

        <!-- Alerts List -->
        <div v-for="alert in alerts" :key="alert.id"
             @click="openDetails(alert)"
             class="mb-4 bg-white border border-slate-200 rounded-2xl hover:shadow-lg transition-all cursor-pointer active:scale-[0.985] overflow-hidden"
             :class="['mb-4 ...', isOld(alert.created_at) ? 'opacity-60' : 'opacity-100']">
          <div class="flex items-stretch">
            <div :class="[
              'w-2 shrink-0',
              (alert.incident?.ai_triage?.severity === 'critical' || alert.incident?.ai_triage?.severity === 'high')
                ? 'bg-red-500 animate-pulse'
                : 'bg-orange-500'
            ]"></div>

            <div class="flex-1 p-5">
              <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900 leading-tight">
                  {{ alert.incident?.type || 'Emergency Trigger' }}
                </h3>
                <span class="px-2 py-1 text-[10px] font-black bg-slate-100 text-slate-500 rounded uppercase">
                  {{ alert.incident?.incident_code || `#${alert.id}` }}
                </span>
              </div>

              <div class="flex items-center mt-2 text-sm text-slate-600">
                <f7-icon f7="person_crop_circle_fill" size="18" class="mr-1.5 text-slate-400" />
                <span class="font-medium">{{ getReporterName(alert) }}</span>
                <!-- Verified Badge via KYC Result -->
                <f7-icon v-if="alert.incident?.kyc_result?.idDocumentMatch === 'true'"
                         f7="checkmark_seal_fill" size="14" color="green" class="ml-2" />
              </div>

              <div class="mt-4 flex justify-between items-end border-t border-slate-50 pt-3">
                <div class="text-xs font-medium text-slate-500 flex items-center gap-2">
                  <span class="text-slate-900 font-bold">{{ calculateDistance(alert) }} km</span>
                  <span>•</span>
                  <span class="text-emerald-600 capitalize tracking-tighter font-bold">
                    {{ timeAgo(alert.created_at) }}
                  </span>
                </div>
                <span class="text-red-600 font-bold text-sm flex items-center">
                  Respond <f7-icon f7="chevron_right" size="12" class="ml-1" />
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== RICH INCIDENT DETAILS POPUP ==================== -->
    <f7-popup :opened="popupOpened" @popup:closed="popupOpened = false" class="incident-popup">
      <div v-if="selectedAlert" class="flex flex-col h-full bg-white">
        <!-- Header -->
        <div class="p-6 bg-[#1a5d3b] text-shadow-white flex justify-between items-start">
          <div>
            <h2 class="text-2xl text-white font-black uppercase tracking-tight leading-none">Incident Response</h2>
            <p class="text-white text-xs font-bold mt-2 uppercase tracking-widest">
              Ref: {{ selectedAlert.incident?.incident_code || 'GUEST-TRIGGER' }}
            </p>
          </div>
          <f7-link popup-close>
            <f7-icon f7="xmark_circle_fill" size="32" color="white" />
          </f7-link>
        </div>

        <div class="flex-1 p-6 overflow-y-auto space-y-6">
           <!-- AI Triage Summary -->
          <div class="p-5 bg-blue-50 border border-blue-100 rounded-3xl">
            <h4 class="uppercase text-blue-800 text-[10px] font-black tracking-[0.2em] mb-4 flex items-center gap-2">
              <f7-icon f7="cpu" size="16" /> Intelligence Report
            </h4>

            <div class="space-y-5">
              <div class="flex justify-between items-center">
                <span class="text-sm font-bold text-slate-500 uppercase tracking-tight">Severity</span>
                <span :class="getSeverityClass(selectedAlert.incident?.ai_triage?.severity)"
                      class="px-4 py-1 rounded-lg text-xs font-black uppercase shadow-sm">
                  {{ selectedAlert.incident?.ai_triage?.severity || 'Evaluation Pending' }}
                </span>
              </div>

              <div>
                <span class="text-[10px] uppercase font-black text-slate-400">Diagnosis</span>
                <p class="font-bold text-slate-800 text-lg">
                  {{ selectedAlert.incident?.ai_triage?.likely_condition || 'Awaiting Sensor Analysis' }}
                </p>
              </div>

              <div v-if="selectedAlert.incident?.ai_triage?.reasoning">
                <span class="text-[10px] uppercase font-black text-slate-400">Situational Context</span>
                <p class="text-slate-600 text-sm mt-1 leading-relaxed italic">
                  "{{ selectedAlert.incident.ai_triage.reasoning }}"
                </p>
              </div>

              <!-- First Aid Tips -->
              <div v-if="selectedAlert.incident?.ai_triage?.first_aid_tips?.length" class="bg-white/60 p-4 rounded-2xl">
                <span class="text-[10px] uppercase font-black text-emerald-700">Immediate Action Protocol</span>
                <ul class="mt-2 space-y-2">
                  <li v-for="(tip, i) in selectedAlert.incident.ai_triage.first_aid_tips"
                      :key="i" class="flex items-start gap-2 text-sm text-slate-700">
                    <f7-icon f7="checkmark_alt" size="14" class="text-emerald-500 mt-0.5" />
                    {{ tip }}
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Network Location Accuracy -->
          <div class="p-5 bg-slate-50 rounded-3xl border border-slate-100">
             <h4 class="uppercase text-slate-400 text-[10px] font-black tracking-widest mb-3">Telecom Location (CAMARA)</h4>
             <div class="grid grid-cols-2 gap-4">
                <div>
                  <span class="text-[10px] font-bold text-slate-400 block">Latitude</span>
                  <p class="font-mono text-sm font-bold text-slate-700">
                    {{ selectedAlert.latitude || selectedAlert.network_location?.area?.center?.latitude || 'Unknown' }}
                  </p>
                </div>
                <div>
                  <span class="text-[10px] font-bold text-slate-400 block">Longitude</span>
                  <p class="font-mono text-sm font-bold text-slate-700">
                    {{ selectedAlert.longitude || selectedAlert.network_location?.area?.center?.longitude || 'Unknown' }}
                  </p>
                </div>
             </div>
             <p v-if="selectedAlert.network_location?.area?.radius" class="mt-3 text-[10px] text-slate-400 italic">
               * Location precision within {{ selectedAlert.network_location.area.radius }}m radius
             </p>
          </div>

          <!-- Dispatch Button -->
          <button @click="dispatchToLocation"
            class="w-full py-5 bg-red-600 hover:bg-red-700 active:scale-[0.98] text-white rounded-3xl font-black text-lg shadow-xl shadow-red-200 transition-all uppercase tracking-widest">
            Confirm Dispatch ({{ calculateDistance(selectedAlert) }} km)
          </button>
        </div>
      </div>
    </f7-popup>
  </f7-page>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useUserStore } from '../stores/user';
import { f7 } from 'framework7-vue';

const userStore = useUserStore();
const popupOpened = ref(false);
const selectedAlert = ref(null);

const responderLocation = ref({ lat: 0.3476, lng: 32.5825 });

const alerts = computed(() => userStore.activeAlerts);


const getReporterName = (alert) => {
  if (alert.user?.name) return alert.user.name;
  if (alert.givenName || alert.familyName) {
    return `${alert.givenName || ''} ${alert.familyName || ''}`.trim();
  }
  return 'Anonymous Victim';
};

const timeAgo = (dateString) => {
  if (!dateString) return '';

  const date = new Date(dateString);
  const now = new Date();
  const seconds = Math.floor((now - date) / 1000);

  if (seconds < 60) return 'Just now';

  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;

  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;

  const days = Math.floor(hours / 24);
  if (days < 7) return `${days}d ago`;

  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short'
  });
};

const isOld = (dateString) => {
  const hours = Math.floor((new Date() - new Date(dateString)) / 3600000);
  return hours >= 24;
};

const getSeverityClass = (severity) => {
  const s = severity?.toLowerCase();
  if (s === 'critical' || s === 'high') return 'bg-red-100 text-red-700 border border-red-200';
  if (s === 'medium') return 'bg-orange-100 text-orange-700 border border-orange-200';
  return 'bg-yellow-100 text-yellow-700 border border-yellow-200';
};

/**
 * Calculate distance using Haversine formula with Nokia Location fallback
 */
const calculateDistance = (alert) => {
  if (!alert) return '0.00';

  const lat2 = alert.latitude || alert.network_location?.area?.center?.latitude;
  const lon2 = alert.longitude || alert.network_location?.area?.center?.longitude;

  if (!lat2 || !lon2) return '?.??';

  const lat1 = responderLocation.value.lat;
  const lon1 = responderLocation.value.lng;

  const R = 6371; // Earth radius in km
  const dLat = (lat2 - lat1) * (Math.PI / 180);
  const dLon = (lon2 - lon1) * (Math.PI / 180);

  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(lat1 * (Math.PI / 180)) *
    Math.cos(lat2 * (Math.PI / 180)) *
    Math.sin(dLon / 2) * Math.sin(dLon / 2);

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return (R * c).toFixed(2);
};

const openDetails = (alert) => {
  selectedAlert.value = alert;
  setTimeout(() => {
    popupOpened.value = true;
  }, 50);
};

const dispatchToLocation = () => {
  const alertId = selectedAlert.value?.id;

  f7.dialog.confirm(
    `Are you ready to respond to Case #${alertId}?`,
    'Confirm Dispatch',
    async () => {
      f7.preloader.show('Notifying Victim...');

      try {
        const success = await userStore.dispatchToAlert(alertId);

        if (success) {
          f7.preloader.hide();
          f7.dialog.alert(
            'Dispatch confirmed. The victim has been notified that you are en route.',
            'En Route'
          );
          popupOpened.value = false;
        } else {
          throw new Error("Dispatch failed at server");
        }
      } catch (error) {
        f7.preloader.hide();
        f7.dialog.alert('Could not complete dispatch. Please check your network connection.');
      }
    }
  );
};

const refreshAlerts = async () => {
  f7.preloader.show();
  await userStore.fetchActiveAlerts();
  f7.preloader.hide();
};

onMounted(async () => {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition((pos) => {
      responderLocation.value = { lat: pos.coords.latitude, lng: pos.coords.longitude };
    });
  }

  await userStore.fetchActiveAlerts();
  userStore.initRealTimeListener();
});
</script>
