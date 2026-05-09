<template>
  <f7-page name="dashboard" class="bg-slate-50" @page:init="onInit" @page:beforeremove="onLeave">

    <div class="p-4 md:flex md:gap-6">

      <!-- LEFT COLUMN -->
      <div class="mb-4 md:w-1/3">
        <div class="grid grid-cols-1 gap-4">

          <div class="p-5 bg-white border-l-4 border-red-600 rounded-2xl shadow-sm">
            <h3 class="text-xs font-bold tracking-wider text-gray-500 uppercase">Active Alerts</h3>
            <p class="text-5xl font-black text-gray-900 mt-1">
              {{ emergencyStore.activeAlerts.length }}
            </p>
          </div>

          <div class="p-5 bg-white border-l-4 border-green-600 rounded-2xl shadow-sm">
            <h3 class="text-xs font-bold tracking-wider text-gray-500 uppercase">Your Current Position</h3>

            <!-- GPS states -->
            <div v-if="gpsState === 'requesting'" class="flex items-center gap-2 mt-1">
              <f7-icon f7="location" size="16" class="text-gray-400 animate-pulse" />
              <p class="text-base font-bold text-gray-400">Requesting GPS...</p>
            </div>
            <div v-else-if="gpsState === 'denied'" class="flex items-center gap-2 mt-1">
              <f7-icon f7="location_slash_fill" size="16" class="text-red-400" />
              <p class="text-base font-bold text-red-400">Location access denied</p>
            </div>
            <div v-else-if="gpsState === 'locating'" class="flex items-center gap-2 mt-1">
              <f7-icon f7="location_fill" size="16" class="text-blue-400 animate-pulse" />
              <p class="text-base font-bold text-blue-400">Identifying area...</p>
            </div>
            <div v-else>
              <p class="text-lg font-bold text-gray-800 leading-tight">{{ currentAreaName }}</p>
            </div>

            <div class="mt-2 flex items-center gap-2">
              <span
                class="w-2 h-2 rounded-full"
                :class="{
                  'bg-gray-300':              gpsState === 'requesting',
                  'bg-red-400':               gpsState === 'denied',
                  'bg-blue-400 animate-ping': gpsState === 'locating',
                  'bg-green-500 animate-ping': gpsState === 'ready',
                }"
              ></span>
              <p class="text-xs font-mono text-gray-400">
                <span v-if="gpsState === 'ready'">
                  {{ responderLocation.lat.toFixed(5) }}, {{ responderLocation.lng.toFixed(5) }}
                </span>
                <span v-else class="italic">{{ gpsStateLabel }}</span>
              </p>
            </div>
          </div>

          <div class="p-5 bg-white border-l-4 border-blue-600 rounded-2xl shadow-sm">
            <h3 class="text-xs font-bold tracking-wider text-gray-500 uppercase">Resolved Today</h3>
            <p class="text-5xl font-black text-gray-900 mt-1">{{ resolvedToday }}</p>
          </div>

        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div class="md:w-2/3">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-2xl font-bold text-slate-700">Live Incident Feed</h2>
          <f7-button small @click="refreshAlerts" color="blue" outline :disabled="emergencyStore.loading">
            <f7-icon f7="arrow_clockwise" size="14" class="mr-1" />
            {{ emergencyStore.loading ? 'Loading...' : 'Refresh' }}
          </f7-button>
        </div>

        <!-- Loading skeleton -->
        <div v-if="emergencyStore.loading" class="space-y-4">
          <div v-for="i in 3" :key="i"
            class="bg-white border border-slate-200 rounded-2xl p-5 animate-pulse"
          >
            <div class="h-4 bg-slate-100 rounded w-3/4 mb-3"></div>
            <div class="h-3 bg-slate-100 rounded w-1/2 mb-2"></div>
            <div class="h-3 bg-slate-100 rounded w-1/4"></div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="emergencyStore.activeAlerts.length === 0"
          class="bg-white border border-slate-200 rounded-2xl p-12 text-center"
        >
          <f7-icon f7="checkmark_shield_fill" size="48" class="text-green-400 mb-3" />
          <p class="text-slate-500 font-bold">No active incidents</p>
          <p class="text-slate-400 text-sm mt-1">All clear in your area</p>
        </div>

        <!-- Alert cards -->
        <div v-else>
          <div
            v-for="alert in emergencyStore.activeAlerts"
            :key="alert.id"
            @click="openDetails(alert)"
            class="mb-4 bg-white border border-slate-200 rounded-2xl hover:shadow-lg transition-all cursor-pointer overflow-hidden"
          >
            <div class="flex items-stretch">
              <div
                :class="[
                  'w-2 shrink-0',
                  alert.incident?.severity === 'critical' ? 'bg-red-500 animate-pulse' :
                  alert.incident?.severity === 'high'     ? 'bg-orange-500' :
                  alert.incident?.severity === 'medium'   ? 'bg-yellow-500' :
                                                            'bg-green-500'
                ]"
              ></div>

              <div class="flex-1 p-5">
                <!-- Title + ID -->
                <div class="flex items-center justify-between">
                  <h3 class="text-lg font-bold text-slate-900 leading-tight">
                    {{ alert.incident?.type || getTriageData(alert).condition }}
                  </h3>
                  <span class="px-2 py-1 text-[10px] font-black bg-slate-100 text-slate-500 rounded uppercase">
                    #{{ alert.id }}
                  </span>
                </div>

                <!-- Badges -->
                <div class="mt-2 flex gap-2 flex-wrap">
                  <span
                    class="px-2 py-0.5 text-[10px] font-black rounded uppercase"
                    :class="{
                      'bg-red-100 text-red-700':       alert.incident?.severity === 'critical',
                      'bg-orange-100 text-orange-700':  alert.incident?.severity === 'high',
                      'bg-yellow-100 text-yellow-700':  alert.incident?.severity === 'medium',
                      'bg-green-100 text-green-700':    alert.incident?.severity === 'low',
                      'bg-slate-100 text-slate-500':    !alert.incident?.severity,
                    }"
                  >
                    {{ alert.incident?.severity ?? 'unknown' }}
                  </span>
                  <span class="px-2 py-0.5 text-[10px] font-black rounded uppercase bg-blue-100 text-blue-700">
                    {{ getTriageData(alert).responder }}
                  </span>
                  <span
                        v-if="alert.reachability_status === 'sms'"
                        class="px-2 py-0.5 text-[10px] font-black rounded uppercase bg-purple-100 text-purple-700 flex items-center gap-1"
                    >
                        <f7-icon f7="chat_bubble_text_fill" size="10" />
                        SMS Protocol
                  </span>
                  <span v-if="alert.sms_fallback"
                    class="px-2 py-0.5 text-[10px] font-black rounded uppercase bg-yellow-100 text-yellow-700"
                  >
                    SMS fallback
                  </span>
                </div>

                <!-- Bottom row: distance + time + action -->
                <div class="mt-4 flex justify-between items-end border-t border-slate-50 pt-3">
                  <div class="space-y-1">
                    <div class="text-xs font-medium text-slate-500 flex items-center gap-1.5">
                      <f7-icon f7="placemark_fill" size="12" class="text-red-500" />
                      <span class="text-slate-900 font-bold text-sm">
                        {{ calculateDistance(alert) }} away
                      </span>
                    </div>
                    <div class="text-xs text-slate-400 flex items-center gap-1.5">
                      <f7-icon f7="clock" size="12" class="text-slate-400" />
                      <span>{{ formatTime(alert.created_at) }}</span>
                      <span class="text-slate-300">·</span>
                      <span class="font-medium text-slate-500">{{ timeAgo(alert.created_at) }}</span>
                    </div>
                  </div>
                  <span class="text-red-600 font-bold text-sm">View Details</span>
                </div>

              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ─── DETAIL POPUP ───────────────────────────────── -->
    <f7-popup :opened="popupOpened" @popup:closed="onPopupClose">
      <div v-if="selectedAlert" class="flex flex-col h-full bg-white">

        <div class="p-6 bg-[#1a5d3b] text-white flex justify-between items-start">
          <div>
            <h2 class="text-2xl font-black uppercase tracking-tight">Incident Details</h2>
            <p class="text-xs font-bold mt-1 opacity-80 uppercase">
              Ref: {{ selectedAlert.incident?.incident_code }}
            </p>
            <p class="text-xs opacity-60 mt-0.5">
              {{ formatTime(selectedAlert.created_at) }} · {{ timeAgo(selectedAlert.created_at) }}
            </p>
          </div>
          <f7-link popup-close>
            <f7-icon f7="xmark_circle_fill" size="32" color="white" />
          </f7-link>
        </div>

        <div class="flex-1 p-6 overflow-y-auto space-y-6">

          <!-- Quick stats -->
          <div class="grid grid-cols-3 gap-3">
            <div class="bg-slate-50 rounded-2xl p-3 text-center">
              <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Distance</p>
              <p class="text-sm font-black text-slate-800 font-mono mt-1">
                {{ calculateDistance(selectedAlert) }}
              </p>
            </div>
            <div class="bg-slate-50 rounded-2xl p-3 text-center">
              <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Severity</p>
              <p class="text-sm font-black mt-1 capitalize"
                :class="{
                  'text-red-600':    selectedAlert.incident?.severity === 'critical',
                  'text-orange-600': selectedAlert.incident?.severity === 'high',
                  'text-yellow-600': selectedAlert.incident?.severity === 'medium',
                  'text-green-600':  selectedAlert.incident?.severity === 'low',
                }"
              >
                {{ selectedAlert.incident?.severity ?? '—' }}
              </p>
            </div>
            <div class="bg-slate-50 rounded-2xl p-3 text-center">
              <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Sent</p>
              <p class="text-xs font-black text-slate-800 mt-1 leading-tight">
                {{ timeAgo(selectedAlert.created_at) }}
              </p>
            </div>
          </div>

        <div class="p-4 bg-slate-50 border border-slate-100 rounded-3xl">
        <div class="flex justify-between items-start mb-3">
            <h4 class="uppercase text-slate-500 text-[10px] font-black tracking-[0.2em] flex items-center gap-2">
            <f7-icon f7="location_fill" size="12" class="text-red-500" />
            Victim Location
            </h4>
            <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase"
            :class="selectedAlert.reachability_status === 'sms' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700'">
            {{ selectedAlert.connectivity_type }} Signal
            </span>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
            <p class="text-[9px] text-slate-400 uppercase font-bold">Latitude</p>
            <p class="text-sm font-bold font-mono text-slate-800 mt-0.5">
                {{ getVictimCoords(selectedAlert).lat?.toFixed(6) ?? '—' }}
            </p>
            </div>
            <div>
            <p class="text-[9px] text-slate-400 uppercase font-bold">Longitude</p>
            <p class="text-sm font-bold font-mono text-slate-800 mt-0.5">
                {{ getVictimCoords(selectedAlert).lng?.toFixed(6) ?? '—' }}
            </p>
            </div>
        </div>

        <div class="mt-3 pt-3 border-t border-slate-100 flex justify-between items-center">
            <div>
            <p class="text-[9px] text-slate-400 uppercase font-bold">Location Source</p>
            <p class="text-xs font-bold text-blue-600 mt-0.5">
                {{ selectedAlert.network_location ? 'CAMARA Cell Tower' : 'Device GPS' }}
            </p>
            </div>
            <div class="text-right">
            <p class="text-[9px] text-slate-400 uppercase font-bold">Accuracy</p>
            <p class="text-xs font-bold text-slate-600 mt-0.5">
            ±{{ selectedAlert.accuracy_radius || '15' }}m
                    </p>
            </div>
        </div>
        </div>

          <!-- AI Triage -->
          <div class="p-5 bg-blue-50 border border-blue-100 rounded-3xl">
            <h4 class="uppercase text-blue-800 text-[10px] font-black tracking-[0.2em] mb-4 flex items-center gap-2">
              <f7-icon f7="cpu" size="16" /> AI Triage Report
            </h4>
            <div class="space-y-4">

              <div class="flex justify-between items-center border-b border-blue-100 pb-3">
                <span class="text-sm font-bold text-slate-500 uppercase">Priority</span>
                <span
                  class="px-4 py-1 rounded-lg text-xs font-black uppercase"
                  :class="{
                    'bg-red-100 text-red-700':       selectedAlert.incident?.severity === 'critical',
                    'bg-orange-100 text-orange-700':  selectedAlert.incident?.severity === 'high',
                    'bg-yellow-100 text-yellow-700':  selectedAlert.incident?.severity === 'medium',
                    'bg-green-100 text-green-700':    selectedAlert.incident?.severity === 'low',
                    'bg-slate-100 text-slate-500':    !selectedAlert.incident?.severity,
                  }"
                >
                  {{ selectedAlert.incident?.severity ?? 'Urgent' }}
                </span>
              </div>

              <div>
                <span class="text-[10px] uppercase font-bold text-slate-400">Likely Condition</span>
                <p class="font-bold text-slate-800 text-lg">
                  {{ getTriageData(selectedAlert).condition }}
                </p>
              </div>

              <div class="flex justify-between items-center border-b border-blue-100 pb-3">
                <span class="text-sm font-bold text-slate-500 uppercase">Communication</span>
                <span
                    class="px-3 py-1 rounded-lg text-[10px] font-black uppercase"
                    :class="selectedAlert.reachability_status === 'sms' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                >
                    {{ selectedAlert.reachability_status === 'sms' ? 'SMS Required' : 'Data Active' }}
                </span>
            </div>

              <div>
                <span class="text-[10px] uppercase font-bold text-slate-400">Recommended Responder</span>
                <p class="font-bold text-blue-700 text-sm uppercase mt-0.5">
                  {{ getTriageData(selectedAlert).responder }}
                </p>
              </div>

              <div v-if="getTriageData(selectedAlert).tips.length > 0">
                <span class="text-[10px] uppercase font-black text-blue-600 block mb-2">
                  First Aid Instructions
                </span>
                <ul class="space-y-2">
                  <li
                    v-for="(tip, i) in getTriageData(selectedAlert).tips"
                    :key="i"
                    class="text-sm font-medium text-slate-700 bg-white p-3 rounded-xl border border-blue-50 shadow-sm flex gap-2"
                  >
                    <f7-icon f7="info_circle_fill" size="14" class="text-blue-500 mt-0.5 shrink-0" />
                    {{ tip }}
                  </li>
                </ul>
              </div>

            </div>
          </div>

          <!-- Symptoms -->
          <div v-if="selectedAlert.symptoms"
            class="p-4 bg-slate-50 border border-slate-100 rounded-3xl"
          >
            <h4 class="uppercase text-slate-500 text-[10px] font-black tracking-[0.2em] mb-2">
              Symptoms Reported
            </h4>
            <p class="text-slate-700 text-sm leading-relaxed">{{ selectedAlert.symptoms }}</p>
          </div>

        </div>

        <!-- Dispatch button -->
        <div class="p-6 border-t border-slate-100">
          <button
            @click="dispatchToLocation"
            :disabled="dispatching"
            :class="[
              'w-full py-5 rounded-3xl font-black text-lg uppercase active:scale-95 transition-transform',
              dispatching
                ? 'bg-slate-200 text-slate-400 cursor-not-allowed'
                : 'bg-red-600 text-white shadow-xl'
            ]"
          >
            {{ dispatching ? 'Notifying Victim...' : 'Confirm Dispatch' }}
          </button>
        </div>

      </div>
    </f7-popup>

  </f7-page>
</template>
<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { f7 } from 'framework7-vue'
import { useUserStore }      from '../stores/user'
import { useEmergencyStore } from '../stores/emergency'
import axios from 'axios'

const userStore      = useUserStore()
const emergencyStore = useEmergencyStore()

// ─── State ──────────────────────────────────────────────
const popupOpened       = ref(false)
const selectedAlert     = ref(null)
const dispatching       = ref(false)
const resolvedToday     = ref(0)
const currentAreaName   = ref('Waiting for GPS...')
const responderLocation = ref({ lat: 0, lng: 0 })

// gpsState: 'requesting' | 'locating' | 'ready' | 'denied'
const gpsState = ref('requesting')

const gpsStateLabel = computed(() => ({
  requesting: 'Waiting for permission...',
  locating:   'Resolving area name...',
  ready:      'GPS Active',
  denied:     'Enable location in settings',
}[gpsState.value] ?? ''))

let watchId = null


const formatTime = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleTimeString('en-UG', {
    hour: '2-digit', minute: '2-digit', hour12: true,
  })
}

const timeAgo = (dateStr) => {
  if (!dateStr) return ''

  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000)
  if (diff < 60) {
    return `${diff}s ago`
  }
  if (diff < 3600) {
    return `${Math.floor(diff / 60)}m ago`
  }
  if (diff < 86400) {
    return `${Math.floor(diff / 3600)}h ago`
  }
  const days = Math.floor(diff / 86400)
  return days === 1 ? '1 day ago' : `${days} days ago`
}

const getTriageData = (alert) => {
  const triage = alert?.ai_triage ?? alert?.incident?.ai_triage
  return {
    condition: triage?.likely_condition ?? triage?.condition ?? 'Medical Emergency',
    responder: triage?.recommended_responder ?? 'VHT',
    tips: Array.isArray(triage?.first_aid_tips) ? triage.first_aid_tips : [],
  }
}

const getVictimCoords = (alert) => {
  if (!alert) return { lat: 0, lng: 0 };

  const lat = alert.victim_lat ||
              alert.latitude ||
              alert.network_location?.latitude ||
              alert.network_location?.area?.center?.latitude ||
              alert.incident?.latitude;

  const lng = alert.victim_lng ||
              alert.longitude ||
              alert.network_location?.longitude ||
              alert.network_location?.area?.center?.longitude ||
              alert.incident?.longitude;

  return {
    lat: lat ? parseFloat(lat) : 0,
    lng: lng ? parseFloat(lng) : 0
  };
}


const calculateDistance = (alert) => {
  //  live phone GPS first. Fallback to API coords if GPS isn't ready.
  let rLat = responderLocation.value.lat;
  let rLng = responderLocation.value.lng;

  if (rLat === 0 && alert.responder_api_lat) {
    rLat = parseFloat(alert.responder_api_lat);
    rLng = parseFloat(alert.responder_api_lng);
  }

  //  VICTIM COORDINATES
  const victim = getVictimCoords(alert);
  const vLat = victim.lat;
  const vLng = victim.lng;

  if (rLat === 0) return 'Locating You...';
  if (vLat === 0) return 'Locating Victim...';

  const R = 6371;

  const toRad = (value) => (value * Math.PI) / 180;

  const dLat = toRad(vLat - rLat);
  const dLon = toRad(vLng - rLng);

  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(toRad(rLat)) * Math.cos(toRad(vLat)) * Math.sin(dLon / 2) * Math.sin(dLon / 2);

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  const distance = R * c; // Distance in km

  if (distance < 1) {
    return (distance * 1000).toFixed(0) + ' m';
  }

  return distance.toFixed(2) + ' km';
};

const updateAreaName = async (lat, lng) => {
  currentAreaName.value = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
  gpsState.value = 'locating';

  try {
    const res = await axios.get('https://nominatim.openstreetmap.org/reverse', {
      params: { format: 'jsonv2', lat, lon: lng, zoom: 16, 'accept-language': 'en' },
      headers: { 'User-Agent': 'NetGuardEmergency/1.0 (dariusakanyijuka3@gmail.com)' }
    });

    if (res.data?.address) {
      const a = res.data.address;
      currentAreaName.value = a.suburb || a.neighbourhood || a.village || a.town || 'Kampala Area';
    }
    gpsState.value = 'ready';
  } catch (err) {
    gpsState.value = 'ready';
  }
}

const startGps = () => {
  if (!navigator.geolocation) {
    gpsState.value = 'denied';
    return;
  }

  const options = { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 };

  const success = (pos) => {
    const { latitude, longitude } = pos.coords;
    responderLocation.value = { lat: latitude, lng: longitude };
    if (gpsState.value !== 'ready') updateAreaName(latitude, longitude);
    gpsState.value = 'ready';
  };

  const error = (err) => {
    console.warn(`GPS Error (${err.code}): ${err.message}`);
    if (err.code === 1) gpsState.value = 'denied';
  };

  watchId = navigator.geolocation.watchPosition(success, error, options);
  navigator.geolocation.getCurrentPosition(success, error, options);
}


const onInit = async () => {
  startGps();

  if (emergencyStore.activeAlerts.length === 0) {
     emergencyStore.initializeListener();
  }
}

const onLeave = () => {
  if (watchId) navigator.geolocation.clearWatch(watchId)
  emergencyStore.destroyListener()
}

const openDetails = (alert) => { selectedAlert.value = alert; popupOpened.value = true; }
const onPopupClose = () => { popupOpened.value = false; selectedAlert.value = null; }
const refreshAlerts = async () => {
  f7.preloader.show();
  await emergencyStore.fetchActiveAlerts();
  f7.preloader.hide();
}

const dispatchToLocation = async () => {
  const alert = selectedAlert.value;
  if (!alert) return;

  const alertId = alert.id;
  const alertType = alert.incident?.type || getTriageData(alert).condition;
  const victimPhone = alert.phone;
  const isSmsMode = alert.reachability_status === 'sms';

  f7.dialog.confirm(
    `Confirm dispatch to Incident #${alertId}?\nThe victim will be notified immediately.`,
    'Responder Dispatch',
    async () => {
      dispatching.value = true;
      const result = await emergencyStore.dispatchToAlert(alertId);
      dispatching.value = false;

      if (result.success) {
        popupOpened.value = false;
        resolvedToday.value++;

       
        if (isSmsMode && victimPhone) {
          const message = `NetGuard: Responder ${userStore.user.given_name} is dispatched to your location (#${alertId}). Proceeding to you now.`;
          const smsUri = `sms:${victimPhone}?body=${encodeURIComponent(message)}`;

          window.location.href = smsUri;

          f7.toast.create({
            text: 'Opening SMS to notify victim...',
            closeTimeout: 2000,
            color: 'purple'
          }).open();
        }

        f7.dialog.create({
          title:   '✅ Dispatch Confirmed',
          text:    isSmsMode
                   ? `Dispatched to <strong>${alertType}</strong>. Please complete the SMS send in your messaging app.`
                   : `Dispatched to <strong>${alertType}</strong>. The victim has been notified via data.`,
          buttons: [
            {
              text:  'View Path/Map',
              bold:  true,
              color: 'green',
              onClick: () => {
                f7.views.main.router.navigate('/resolved-emergencies', {
                  animate: true,
                  transition: 'f7-flip',
                });
              }
            },
            {
              text:  'Close'
            }
          ]
        }).open();

      } else {

        f7.dialog.alert(
          result.message || 'Dispatch failed. Please try again.',
          'Dispatch Error'
        );
      }
    }
  );
};
</script>
