<template>
  <f7-page name="resolved" class="bg-slate-50">

    <div class="bg-[#1a5d3b] sticky top-0 z-50 text-white pt-10 pb-4 px-4 shadow-lg">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <f7-link back>
            <f7-icon f7="chevron_left" size="24" color="white" />
          </f7-link>
          <span class="text-2xl font-bold">Mission Control</span>
        </div>
        <f7-nav-right>
          <f7-link @click="refresh" class="flex items-center gap-1">
            <f7-icon f7="arrow_clockwise" color="white" size="24"></f7-icon>
          </f7-link>
        </f7-nav-right>
      </div>
    </div>

    <div class="p-4">

      <div class="flex gap-2 mb-6">
        <button
          @click="activeTab = 'dispatched'"
          :class="[
            'px-4 py-2 rounded-full text-xs font-black transition-all flex items-center gap-2',
            activeTab === 'dispatched' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-slate-500 border border-slate-200'
          ]"
        >
          <f7-icon f7="map_fill" size="14"></f7-icon>
          Dispatched ({{ counts.dispatched }})
        </button>

        <button
          @click="activeTab = 'resolved'"
          :class="[
            'px-4 py-2 rounded-full text-xs font-black transition-all flex items-center gap-2',
            activeTab === 'resolved' ? 'bg-green-600 text-white shadow-md' : 'bg-white text-slate-500 border border-slate-200'
          ]"
        >
          <f7-icon f7="checkmark_seal_fill" size="14"></f7-icon>
          Resolved ({{ counts.resolved }})
        </button>
      </div>

      <div class="p-5 bg-white border-l-4 rounded-2xl shadow-sm mb-6 flex items-center justify-between"
           :class="activeTab === 'dispatched' ? 'border-blue-600' : 'border-green-600'">
        <div>
          <h3 class="text-xs font-bold tracking-wider text-gray-500 uppercase">
            {{ activeTab === 'dispatched' ? 'Active Missions' : 'Completed Cases' }}
          </h3>
          <p class="text-5xl font-black text-gray-900 mt-1">
            {{ filteredAlerts.length }}
          </p>
        </div>
        <f7-icon
          :f7="activeTab === 'dispatched' ? 'bolt_fill' : 'checkmark_shield_fill'"
          size="48"
          :class="activeTab === 'dispatched' ? 'text-blue-400' : 'text-green-400'"
        />
      </div>

      <div v-if="emergencyStore.loading" class="space-y-4">
        <div v-for="i in 3" :key="i" class="bg-white border border-slate-200 rounded-2xl p-5 animate-pulse">
          <div class="h-4 bg-slate-100 rounded w-3/4 mb-3"></div>
          <div class="h-3 bg-slate-100 rounded w-1/2"></div>
        </div>
      </div>

      <div v-else-if="filteredAlerts.length === 0"
        class="bg-white border border-slate-200 rounded-2xl p-12 text-center"
      >
        <f7-icon f7="tray" size="48" class="text-slate-300 mb-3" />
        <p class="text-slate-500 font-bold">No {{ activeTab }} alerts</p>
        <p class="text-slate-400 text-sm mt-1">Your activity will appear here</p>
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="alert in filteredAlerts"
          :key="alert.id"
          class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm"
        >
          <div class="flex items-stretch">
            <div class="w-2 shrink-0" :class="alert.status === 'dispatched' ? 'bg-blue-500' : 'bg-green-500'"></div>

            <div class="flex-1 p-5">
              <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900 leading-tight">
                  {{ alert.incident?.type || getTriageData(alert).condition }}
                </h3>
                <span
                  class="px-2 py-1 text-[10px] font-black rounded uppercase"
                  :class="alert.status === 'dispatched' ? 'bg-blue-50 text-blue-600' : 'bg-green-50 text-green-600'"
                >
                  {{ alert.status }}
                </span>
              </div>

              <p class="text-[10px] text-slate-400 font-mono mt-1">
                Ref: {{ alert.incident?.incident_code ?? `#${alert.id}` }}
              </p>

              <div class="mt-2 flex gap-2">
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
              </div>

              <div class="mt-3 pt-3 border-t border-slate-50 flex justify-between items-center">
                <div class="space-y-0.5">
                  <div class="text-[10px] text-slate-400 flex items-center gap-1.5">
                    <f7-icon f7="clock" size="11" />
                    <span>Sent: {{ formatTime(alert.created_at) }}</span>
                  </div>
                  <div v-if="alert.resolved_at" class="text-[10px] text-green-600 font-bold flex items-center gap-1.5">
                    <f7-icon f7="checkmark_circle_fill" size="11" />
                    <span>Resolved: {{ formatTime(alert.resolved_at) }}</span>
                  </div>
                </div>
                <div class="text-right">
                  <p class="text-[9px] text-slate-400 uppercase font-bold">Response</p>
                  <p class="text-sm font-black text-slate-900">
                    {{ responseTime(alert.created_at, alert.dispatched_at) }}
                  </p>
                </div>
              </div>

              <div v-if="alert.status === 'dispatched'" class="mt-4 pt-3 border-t border-slate-100">
                <f7-button
                  fill
                  color="green"
                  class="rounded-xl font-black shadow-sm"
                  @click="handleResolve(alert.id)"
                >
                  <f7-icon f7="checkmark_seal_fill" size="18" class="mr-2" />
                  Mark as Resolved
                </f7-button>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </f7-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { f7 } from 'framework7-vue'
import { useEmergencyStore } from '../stores/emergency'

const emergencyStore = useEmergencyStore()
const activeTab = ref('dispatched') // default view

const filteredAlerts = computed(() => {
  return emergencyStore.resolvedAlerts.filter(alert => alert.status === activeTab.value)
})

const counts = computed(() => ({
  dispatched: emergencyStore.resolvedAlerts.filter(a => a.status === 'dispatched').length,
  resolved:   emergencyStore.resolvedAlerts.filter(a => a.status === 'resolved').length
}))

const getTriageData = (alert) => {
  const triage = alert?.ai_triage ?? alert?.incident?.ai_triage
  return {
    condition: triage?.likely_condition ?? triage?.condition ?? 'Medical Emergency',
    responder: triage?.recommended_responder ?? 'VHT',
  }
}

const formatTime = (dateStr) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleTimeString('en-UG', {
    hour: '2-digit', minute: '2-digit', hour12: true,
  })
}

const responseTime = (sentAt, dispatchedAt) => {
  if (!sentAt || !dispatchedAt) return '—'
  const diff = Math.floor((new Date(dispatchedAt) - new Date(sentAt)) / 1000)
  if (diff < 60) return `${diff}s`
  return `${Math.floor(diff / 60)}m ${diff % 60}s`
}

const refresh = async () => {
  f7.preloader.show()
  await emergencyStore.fetchResolvedAlerts()
  f7.preloader.hide()
}

const handleResolve = (alertId) => {
  f7.dialog.confirm(
    'Is the victim assisted and the mission complete?',
    'Confirm Resolution',
    async () => {
      f7.preloader.show();
      try {
        await emergencyStore.completeMission(alertId);
        f7.toast.create({
          text: 'Case closed. Great job!',
          closeTimeout: 3000,
          cssClass: 'bg-green-600'
        }).open();

        activeTab.value = 'resolved';
      } catch (error) {
        f7.dialog.alert('Error updating status.');
      } finally {
        f7.preloader.hide();
      }
    }
  );
}

onMounted(async () => {
  await emergencyStore.fetchResolvedAlerts()
})
</script>
