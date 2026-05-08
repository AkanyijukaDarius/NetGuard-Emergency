<template>
  <f7-page name="resolved" class="bg-slate-50">

    <f7-navbar title="Dispatched Alerts" back-link="Back">
      <template #right>
        <f7-link @click="refresh" icon-f7="arrow_clockwise" />
      </template>
    </f7-navbar>

    <div class="p-4">

      <!-- Summary banner -->
      <div class="p-5 bg-white border-l-4 border-green-600 rounded-2xl shadow-sm mb-6 flex items-center justify-between">
        <div>
          <h3 class="text-xs font-bold tracking-wider text-gray-500 uppercase">Total Dispatched</h3>
          <p class="text-5xl font-black text-gray-900 mt-1">
            {{ emergencyStore.resolvedAlerts.length }}
          </p>
        </div>
        <f7-icon f7="checkmark_shield_fill" size="48" class="text-green-400" />
      </div>

      <!-- Loading -->
      <div v-if="emergencyStore.loading" class="space-y-4">
        <div v-for="i in 3" :key="i"
          class="bg-white border border-slate-200 rounded-2xl p-5 animate-pulse"
        >
          <div class="h-4 bg-slate-100 rounded w-3/4 mb-3"></div>
          <div class="h-3 bg-slate-100 rounded w-1/2"></div>
        </div>
      </div>

      <!-- Empty -->
      <div v-else-if="emergencyStore.resolvedAlerts.length === 0"
        class="bg-white border border-slate-200 rounded-2xl p-12 text-center"
      >
        <f7-icon f7="tray" size="48" class="text-slate-300 mb-3" />
        <p class="text-slate-500 font-bold">No dispatched alerts yet</p>
        <p class="text-slate-400 text-sm mt-1">Alerts you respond to will appear here</p>
      </div>

      <!-- Resolved cards -->
      <div v-else class="space-y-3">
        <div
          v-for="alert in emergencyStore.resolvedAlerts"
          :key="alert.id"
          class="bg-white border border-slate-200 rounded-2xl overflow-hidden"
        >
          <div class="flex items-stretch">
            <!-- Green strip — dispatched -->
            <div class="w-2 shrink-0 bg-green-500"></div>

            <div class="flex-1 p-5">
              <!-- Title + ID -->
              <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900 leading-tight">
                  {{ alert.incident?.type || getTriageData(alert).condition }}
                </h3>
                <span class="px-2 py-1 text-[10px] font-black bg-green-50 text-green-600 rounded uppercase">
                  Dispatched
                </span>
              </div>

              <!-- Ref -->
              <p class="text-[10px] text-slate-400 font-mono mt-1">
                Ref: {{ alert.incident?.incident_code ?? `#${alert.id}` }}
              </p>

              <!-- Responder name if available -->
              <p v-if="alert.responder_name"
                class="text-xs text-blue-600 font-bold mt-1"
              >
                Responder: {{ alert.responder_name }}
              </p>

              <!-- Severity badge -->
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

              <!-- Time info -->
              <div class="mt-3 pt-3 border-t border-slate-50 flex justify-between items-center">
                <div class="space-y-0.5">
                  <div class="text-xs text-slate-400 flex items-center gap-1.5">
                    <f7-icon f7="clock" size="11" class="text-slate-400" />
                    <span>Sent: {{ formatTime(alert.created_at) }}</span>
                    <span class="text-slate-300">·</span>
                    <span>{{ timeAgo(alert.created_at) }}</span>
                  </div>
                  <div v-if="alert.dispatched_at"
                    class="text-xs text-green-600 flex items-center gap-1.5 font-medium"
                  >
                    <f7-icon f7="checkmark_circle_fill" size="11" class="text-green-500" />
                    <span>Dispatched: {{ formatTime(alert.dispatched_at) }}</span>
                  </div>
                </div>
                <div class="text-right">
                  <p class="text-xs text-slate-400">Response time</p>
                  <p class="text-sm font-black text-green-600">
                    {{ responseTime(alert.created_at, alert.dispatched_at) }}
                  </p>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

    </div>
  </f7-page>
</template>

<script setup>
import { onMounted } from 'vue'
import { f7 } from 'framework7-vue'
import { useEmergencyStore } from '../stores/emergency'

const emergencyStore = useEmergencyStore()

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

const timeAgo = (dateStr) => {
  if (!dateStr) return ''
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000)
  if (diff < 60)    return `${diff}s ago`
  if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

// Calculate how fast the responder was dispatched
const responseTime = (sentAt, dispatchedAt) => {
  if (!sentAt || !dispatchedAt) return '—'
  const diff = Math.floor((new Date(dispatchedAt) - new Date(sentAt)) / 1000)
  if (diff < 60)   return `${diff}s`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ${diff % 60}s`
  return `${Math.floor(diff / 3600)}h`
}

const refresh = async () => {
  f7.preloader.show()
  await emergencyStore.fetchResolvedAlerts()
  f7.preloader.hide()
}

onMounted(async () => {
  // If store already has resolved from dispatch, don't re-fetch
  if (emergencyStore.resolvedAlerts.length === 0) {
    await emergencyStore.fetchResolvedAlerts()
  }
})
</script>
