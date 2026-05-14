<template>
  <f7-page name="dashboard" class="bg-slate-50" @page:init="onInit" @page:beforeremove="onLeave">

    <div class="p-4 md:flex md:gap-6">

      <div class="mb-4 md:w-1/3">
        <div class="grid grid-cols-1 gap-4">

          <div class="p-5 bg-white border-l-4 border-red-600 rounded-2xl shadow-sm">
            <h3 class="text-xs font-bold tracking-wider text-gray-500 uppercase">Active Alerts</h3>
            <p class="text-5xl font-black text-gray-900 mt-1">
              {{ activeFeed.length }}
            </p>
          </div>

        <div class="p-5 bg-white border-l-4 border-green-600 rounded-2xl shadow-sm">
            <h3 class="text-xs font-bold tracking-wider text-gray-500 uppercase">Your Current Position</h3>
            <div v-show="gpsState === 'ready'" class="mt-3 mb-3 w-full rounded-xl overflow-hidden border border-slate-100 shadow-inner" style="min-height: 112px;">
              <div id="responder-mini-map" style="width: 100%; height: 112px;"></div>
            </div>
            <div class="mt-2 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full" :class="gpsClass"></span>
              <p class="text-xs font-mono text-gray-400">
                <span v-if="gpsState === 'ready'">{{ responderLocation.lat.toFixed(5) }}, {{ responderLocation.lng.toFixed(5) }}</span>
                <span v-else class="italic">{{ gpsStateLabel }}</span>
              </p>
            </div>
            <div v-show="currentAreaName" class="mt-1 text-sm font-bold text-gray-700">
              {{ currentAreaName }}
            </div>
          </div>

          <div class="p-5 bg-white border-l-4 border-blue-600 rounded-2xl shadow-sm">
            <h3 class="text-xs font-bold tracking-wider text-gray-500 uppercase">Resolved Today</h3>
            <p class="text-5xl font-black text-gray-900 mt-1">{{ resolvedToday }}</p>
          </div>

        </div>
      </div>

      <div class="md:w-2/3">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-2xl font-bold text-slate-700">Live Incident Feed</h2>
          <f7-button small @click="refreshAlerts" color="blue" outline :disabled="emergencyStore.loading">
            <f7-icon f7="arrow_clockwise" size="14" class="mr-1" />
            {{ emergencyStore.loading ? 'Loading...' : 'Refresh' }}
          </f7-button>
        </div>

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
        <div v-else-if="activeFeed.length === 0"
          class="bg-white border border-slate-200 rounded-2xl p-12 text-center"
        >
          <f7-icon f7="checkmark_shield_fill" size="48" class="text-green-400 mb-3" />
          <p class="text-slate-500 font-bold">No active incidents</p>
          <p class="text-slate-400 text-sm mt-1">All clear in your area</p>
        </div>

        <!-- Alert cards -->
        <div v-else>
          <div
            v-for="alert in activeFeed"
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
                <div class="flex items-center justify-between">
                  <h3 class="text-lg font-bold text-slate-900 leading-tight">
                    {{ alert.incident?.type || getTriageData(alert).condition }}
                  </h3>
                  <span class="px-2 py-1 text-[10px] font-black bg-slate-100 text-slate-500 rounded uppercase">
                    #{{ alert.id }}
                  </span>
                </div>

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

    <!-- popop -->
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
        <div class="p-6 overflow-y-auto space-y-6">
            <h4 class="uppercase text-slate-500 text-[10px] font-black tracking-[0.2em] mb-3 flex items-center gap-2">
            <f7-icon f7="globe" size="12" class="text-green-500" />
            Victim location on a map
            </h4>

            <div class="relative w-full h-64 rounded-3xl overflow-hidden shadow-inner border border-slate-200">
                <div id="victim-map" class="w-full h-full"></div>
                <div class="absolute bottom-2 left-2 z-400 bg-white/80 backdrop-blur-sm px-2 py-1 rounded text-[9px] font-bold text-slate-600 uppercase">
                {{ selectedAlert?.network_location ? 'Tower Triangulation' : 'GPS High Accuracy' }}
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
import { ref, computed, onUnmounted, nextTick, watch } from 'vue' // Added watch
import { f7 } from 'framework7-vue'
import { useUserStore }      from '../stores/user'
import { useEmergencyStore } from '../stores/emergency'
import axios from 'axios'
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
});

const userStore      = useUserStore()
const emergencyStore = useEmergencyStore()

const popupOpened       = ref(false)
const selectedAlert     = ref(null)
const dispatching       = ref(false)
const resolvedToday     = ref(0)
const currentAreaName   = ref('Waiting for GPS...')
const responderLocation = ref({ lat: 0, lng: 0 })
const map = ref(null);
const marker = ref(null);
const miniMap = ref(null);
const miniMarker = ref(null);

// GPS State logic
const gpsState = ref('requesting')
let watchId = null

// WATCHER: This is the critical fix for the mini-map
watch(gpsState, (newState) => {
  if (newState === 'ready') {
    nextTick(() => {
      updateResponderMiniMap(responderLocation.value.lat, responderLocation.value.lng);
    });
  }
});

const initMap = (lat, lng) => {
  nextTick(() => {
    if (map.value) map.value.remove();
    
    map.value = L.map('victim-map', {
      zoomControl: false,
      attributionControl: false
    }).setView([lat, lng], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map.value);

    const pulsingIcon = L.divIcon({
      className: 'pulse-marker',
      iconSize: [20, 20],
      iconAnchor: [10, 10],
      html: ''
    });

    marker.value = L.marker([lat, lng], { icon: pulsingIcon }).addTo(map.value);

    setTimeout(() => {
      if (map.value) map.value.invalidateSize();
    }, 400);
  });
}

const updateResponderMiniMap = (lat, lng) => {
  nextTick(() => {
    const container = document.getElementById('responder-mini-map');
    if (!container) return;

    if (!miniMap.value) {
      miniMap.value = L.map('responder-mini-map', {
        zoomControl: false,
        attributionControl: false,
        dragging: false,
        touchZoom: false,
        scrollWheelZoom: false
      }).setView([lat, lng], 15);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(miniMap.value);

      const respIcon = L.divIcon({
        className: 'pulse-marker responder-pulse',
        iconSize: [20, 20],
        iconAnchor: [10, 10]
      });

      miniMarker.value = L.marker([lat, lng], { icon: respIcon }).addTo(miniMap.value);
    } else {
      miniMap.value.setView([lat, lng]);
      miniMarker.value.setLatLng([lat, lng]);
    }

    // Force size re-calculation after the UI transition
    setTimeout(() => {
      if (miniMap.value) miniMap.value.invalidateSize();
    }, 300);
  });
};

const startGps = () => {
  if (!navigator.geolocation) {
    gpsState.value = 'denied';
    return;
  }

  const options = { enableHighAccuracy: false, timeout: 15000, maximumAge: 5000 };

  navigator.geolocation.getCurrentPosition(
    (pos) => { handleGpsSuccess(pos); watchId = navigator.geolocation.watchPosition(handleGpsSuccess, handleGpsError, options); },
    handleGpsError,
    options
  );
};

const handleGpsSuccess = (pos) => {
  const { latitude, longitude } = pos.coords;
  responderLocation.value = { lat: latitude, lng: longitude };
  gpsState.value = 'ready';
  updateResponderMiniMap(latitude, longitude);
  if (currentAreaName.value === 'Waiting for GPS...') updateAreaName(latitude, longitude);
};

const handleGpsError = (err) => { 
  console.error("GPS Error Code:", err.code);
  f7.toast.create({ text: `GPS Error: ${err.message}`, closeTimeout: 3000 }).open(); 
  gpsState.value = 'denied'; 
};

const updateAreaName = async (lat, lng) => {
  try {
    const res = await axios.get('https://nominatim.openstreetmap.org/reverse', {
      params: { format: 'jsonv2', lat, lon: lng, zoom: 16 }
    });
    currentAreaName.value = res.data?.address?.suburb || res.data?.address?.town || 'Kampala Area';
  } catch (e) { currentAreaName.value = "Location Locked"; }
};

const onInit = async () => {
  startGps();
  if (emergencyStore.activeAlerts.length === 0) emergencyStore.initializeListener();
}

const onLeave = () => {
  if (watchId) navigator.geolocation.clearWatch(watchId);
  emergencyStore.destroyListener();
}

const openDetails = (alert) => {
  selectedAlert.value = alert;
  popupOpened.value = true;
  const coords = getVictimCoords(alert);
  if (coords.lat !== 0) initMap(coords.lat, coords.lng);
}

const onPopupClose = () => {
  popupOpened.value = false;
  if (map.value) { map.value.remove(); map.value = null; }
  selectedAlert.value = null;
}

// Helpers
const activeFeed = computed(() => emergencyStore.activeAlerts.filter(a => a.status === 'pending' || a.status === 'triggered'));
const gpsStateLabel = computed(() => ({ requesting: 'Waiting...', locating: 'Locating...', ready: 'GPS Active', denied: 'GPS Blocked' }[gpsState.value]));
const gpsClass = computed(() => ({ 'bg-gray-300': gpsState.value === 'requesting', 'bg-red-400': gpsState.value === 'denied', 'bg-blue-400 animate-ping': gpsState.value === 'locating', 'bg-green-500 animate-ping': gpsState.value === 'ready' }));

const formatTime = (d) => d ? new Date(d).toLocaleTimeString('en-UG', { hour: '2-digit', minute: '2-digit' }) : '—';
const timeAgo = (d) => {
    if (!d) return '';
    const diff = Math.floor((Date.now() - new Date(d)) / 1000);
    return diff < 60 ? `${diff}s ago` : diff < 3600 ? `${Math.floor(diff/60)}m ago` : `${Math.floor(diff/3600)}h ago`;
};

const getTriageData = (a) => {
  const t = a?.ai_triage ?? a?.incident?.ai_triage;
  return { condition: t?.likely_condition ?? 'Medical Emergency', responder: t?.recommended_responder ?? 'VHT', tips: Array.isArray(t?.first_aid_tips) ? t.first_aid_tips : [] };
}

const getVictimCoords = (a) => {
  const lat = a?.victim_lat || a?.latitude || a?.network_location?.latitude || a?.incident?.latitude;
  const lng = a?.victim_lng || a?.longitude || a?.network_location?.longitude || a?.incident?.longitude;
  return { lat: parseFloat(lat) || 0, lng: parseFloat(lng) || 0 };
}

const calculateDistance = (a) => {
  let rLat = responderLocation.value.lat || parseFloat(a.responder_api_lat) || 0;
  let rLng = responderLocation.value.lng || parseFloat(a.responder_api_lng) || 0;
  const v = getVictimCoords(a);
  if (rLat === 0 || v.lat === 0) return 'Locating...';
  const R = 6371;
  const dLat = (v.lat - rLat) * Math.PI / 180;
  const dLon = (v.lng - rLng) * Math.PI / 180;
  const res = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(rLat * Math.PI / 180) * Math.cos(v.lat * Math.PI / 180) * Math.sin(dLon/2) * Math.sin(dLon/2);
  const dist = R * 2 * Math.atan2(Math.sqrt(res), Math.sqrt(1-res));
  return dist < 1 ? (dist * 1000).toFixed(0) + ' m' : dist.toFixed(2) + ' km';
};

const refreshAlerts = async () => { f7.preloader.show(); await emergencyStore.fetchActiveAlerts(); f7.preloader.hide(); }

const dispatchToLocation = async () => {
  const alert = selectedAlert.value;
  f7.dialog.confirm(`Confirm dispatch to Incident #${alert.id}?`, 'NetGuard Dispatch', async () => {
    dispatching.value = true;
    const res = await emergencyStore.dispatchToAlert(alert.id);
    dispatching.value = false;
    if (res.success) {
      popupOpened.value = false; resolvedToday.value++;
      if (alert.reachability_status === 'sms') window.location.href = `sms:${alert.phone}?body=NetGuard: Responder ${userStore.user.given_name} is on the way.`;
      f7.dialog.alert('Dispatch Confirmed!');
    }
  });
};

onUnmounted(() => {
  if (map.value) map.value.remove();
  if (miniMap.value) miniMap.value.remove();
  if (watchId) navigator.geolocation.clearWatch(watchId);
});
</script>
<style>
#responder-mini-map, #victim-map {
  width: 100%;
  height: 100%;
  background: #f8fafc; 
  z-index: 1;
}

.leaflet-container {
  font-family: inherit;
  z-index: 1 !important;
}

/* Pulsing Marker Styles */
.pulse-marker {
  background: transparent !important;
  border: none !important;
}

.pulse-marker::before {
  content: '';
  display: block;
  width: 12px;
  height: 12px;
  background-color: #ef4444; /* Red for Victims */
  border: 2px solid white;
  border-radius: 50%;
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 2;
  box-shadow: 0 0 5px rgba(0,0,0,0.3);
}

.pulse-marker::after {
  content: '';
  display: block;
  width: 12px;
  height: 12px;
  background-color: #ef4444;
  border-radius: 50%;
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  animation: emergency-pulse 1.8s ease-out infinite;
  z-index: 1;
}

/* Blue pulse for Responder */
.responder-pulse::before {
  background-color: #3b82f6;
}

.responder-pulse::after {
  background-color: #3b82f6;
}

@keyframes emergency-pulse {
  0% { width: 12px; height: 12px; opacity: 1; }
  100% { width: 45px; height: 45px; opacity: 0; }
}

/* Custom leaflet control overrides */
.leaflet-control-attribution {
  display: none !important;
}
</style>
