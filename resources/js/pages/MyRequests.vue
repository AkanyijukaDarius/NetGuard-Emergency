<template>
  <f7-page
    class="bg-gray-50 dark:bg-gray-900"
    ptr
    @ptr:refresh="onRefresh"
  >
    <div class="bg-[#1a5d3b] sticky top-0 z-50 text-white pt-10 pb-4 px-4 shadow-lg">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <f7-link back>
            <f7-icon f7="chevron_left" size="24" color="white" />
          </f7-link>
          <span class="text-2xl font-bold">My Requests</span>
        </div>
        <f7-nav-right>
          <f7-link @click="handleRefresh" :loading="emergencyStore.loading">
            <f7-icon f7="arrow_clockwise" color="white" size="24"></f7-icon>
          </f7-link>
        </f7-nav-right>
      </div>
    </div>

    <f7-block class="p-0">
      <div v-if="emergencyStore.loading && emergencyStore.myRequests.length === 0"
           class="flex flex-col items-center justify-center py-20">
        <f7-preloader color="green" size="44"></f7-preloader>
        <p class="mt-5 text-gray-500 dark:text-gray-400 text-sm">Loading your emergencies...</p>
      </div>

      <div v-else-if="emergencyStore.myRequests.length === 0"
           class="flex flex-col items-center justify-center py-20 px-5 text-center">
        <div class="w-32 h-32 bg-linear-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-full flex items-center justify-center mb-6 animate-bounce-slow">
          <f7-icon f7="checkmark_shield_fill" size="80" color="green"></f7-icon>
        </div>
        <h3 class="text-2xl font-semibold text-green-800 dark:text-green-400 mb-3">You're All Safe</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed mb-6">
          No active emergency requests at the moment.<br>
          Stay safe!
        </p>
        <f7-button fill color="green" round @click="goToTriggerEmergency" class="min-w-50 py-3">
          <f7-icon f7="phone_arrow_right" size="20" class="mr-2"></f7-icon>
          Report an Emergency
        </f7-button>
      </div>

      <div v-else class="p-4">
        <div class="mb-5">
          <div class="flex gap-2 overflow-x-auto pb-2 no-scrollbar">
            <button
              v-for="tab in statusTabs"
              :key="tab.value"
              @click="selectedStatus = tab.value"
              class="px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-all duration-200"
              :class="selectedStatus === tab.value
                ? 'bg-green-500 text-white shadow-lg shadow-green-500/30'
                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'"
            >
              <div class="flex items-center gap-2">
                <f7-icon :icon="tab.icon" size="14"></f7-icon>
                <span>{{ tab.label }}</span>
                <span v-if="getStatusCount(tab.value) > 0"
                      class="ml-1 text-xs"
                      :class="selectedStatus === tab.value ? 'text-white' : 'text-gray-500'">
                  ({{ getStatusCount(tab.value) }})
                </span>
              </div>
            </button>
          </div>
        </div>

        <div class="space-y-4">
          <div
            v-for="req in filteredRequests"
            :key="req.id"
            class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer active:scale-[0.98] overflow-hidden"
            :class="{
              'border-l-4 border-yellow-500': req.status === 'pending',
              'border-l-4 border-blue-500': req.status === 'dispatched',
              'border-l-4 border-purple-500': req.status === 'in_progress',
              'border-l-4 border-green-500': req.status === 'resolved',
              'border-l-4 border-red-500 opacity-75': req.status === 'cancelled'
            }"
            @click="viewEmergencyDetails(req.id)"
          >
            <div class="p-4 pb-0">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                  <f7-icon f7="exclamationmark_triangle_fill" size="18" :class="getStatusColor(req.status)"></f7-icon>
                  <span class="font-semibold text-gray-900 dark:text-white">Emergency #{{ req.id }}</span>
                </div>
                <div class="flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
                  <f7-icon f7="clock_fill" size="12"></f7-icon>
                  <span>{{ formatDate(req.created_at) }}</span>
                </div>
              </div>
            </div>

            <div class="mx-4 mb-4 p-3 rounded-xl"
                 :class="{
                   'bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800': req.status === 'pending',
                   'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800': req.status === 'dispatched',
                   'bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800': req.status === 'in_progress',
                   'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800': req.status === 'resolved',
                   'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800': req.status === 'cancelled'
                 }">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                     :class="{
                       'bg-yellow-200 dark:bg-yellow-800': req.status === 'pending',
                       'bg-blue-200 dark:bg-blue-800': req.status === 'dispatched',
                       'bg-purple-200 dark:bg-purple-800': req.status === 'in_progress',
                       'bg-green-200 dark:bg-green-800': req.status === 'resolved',
                       'bg-red-200 dark:bg-red-800': req.status === 'cancelled'
                     }">
                  <f7-icon :icon="getStatusIcon(req.status)" size="20" :class="getStatusColor(req.status)"></f7-icon>
                </div>
                <div class="flex-1">
                  <div class="font-semibold text-gray-900 dark:text-white">
                    {{ getStatusText(req.status) }}
                  </div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ getStatusDescription(req.status) }}
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-lg font-bold" :class="getStatusColor(req.status)">
                    {{ getProgressValue(req.status) }}
                  </div>
                  <div class="text-xs text-gray-400">Complete</div>
                </div>
              </div>
            </div>

            <div class="px-4 pb-3 space-y-2">
              <div v-if="req.incident?.type" class="text-sm">
                <span class="font-medium text-gray-600 dark:text-gray-400">Incident Type:</span>
                <span class="text-gray-800 dark:text-gray-300 ml-2">{{ req.incident.type }}</span>
              </div>
              <div v-else-if="req.symptoms" class="text-sm">
                <span class="font-medium text-gray-600 dark:text-gray-400">Symptoms:</span>
                <span class="text-gray-800 dark:text-gray-300 ml-2">{{ truncateText(req.symptoms, 100) }}</span>
              </div>

              <div v-if="req.responder" class="flex items-center gap-3 mt-3 p-2 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                <div class="w-8 h-8 bg-linear-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center text-white">
                  <f7-icon f7="person_fill" size="14"></f7-icon>
                </div>
                <div class="flex-1 flex flex-wrap items-center gap-2 text-sm">
                  <span class="font-medium text-gray-600 dark:text-gray-400">Responder:</span>
                  <span class="text-gray-900 dark:text-white">{{ req.responder.name }}</span>
                </div>
              </div>
            </div>

            <div v-if="getProgressValue(req.status) && req.status !== 'cancelled'" class="px-4 pb-3">
              <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500 relative overflow-hidden"
                     :class="{
                       'bg-linear-to-r from-yellow-500 to-yellow-400': req.status === 'pending',
                       'bg-linear-to-r from-blue-500 to-blue-400': req.status === 'dispatched',
                       'bg-linear-to-r from-purple-500 to-purple-400': req.status === 'in_progress',
                       'bg-linear-to-r from-green-500 to-green-400': req.status === 'resolved'
                     }"
                     :style="{ width: getProgressValue(req.status) }">
                </div>
              </div>
            </div>

            <div v-if="req.status === 'pending'" class="px-4 pb-4 pt-2 border-t border-gray-100 dark:border-gray-700 flex justify-end">
              <f7-button small outline color="red" @click.stop="cancelEmergency(req)">
                <f7-icon f7="xmark_circle" size="16" class="mr-1"></f7-icon>
                Cancel Request
              </f7-button>
            </div>
          </div>
        </div>

        <div v-if="filteredRequests.length === 0" class="text-center py-10">
          <f7-icon f7="folder" size="48" class="text-gray-300 dark:text-gray-600 mb-3"></f7-icon>
          <p class="text-gray-500 dark:text-gray-400">No {{ getStatusText(selectedStatus) }} emergencies</p>
        </div>
      </div>
    </f7-block>
  </f7-page>
</template>

<script setup>
import { onMounted, inject, ref, computed,onUnmounted } from 'vue';
import { useEmergencyStore } from '../stores/emergency';
import { f7 } from 'framework7-vue';

const $f7 = inject('$f7');
const emergencyStore = useEmergencyStore();
const selectedStatus = ref('all');

// Status tabs for filtering
const statusTabs = [
  { value: 'all', label: 'All', icon: 'list_bullet' },
  { value: 'pending', label: 'Pending', icon: 'clock' },
  { value: 'dispatched', label: 'Dispatched', icon: 'person_fill_checkmark' },
  { value: 'in_progress', label: 'In Progress', icon: 'arrow_right_circle' },
  { value: 'resolved', label: 'Resolved', icon: 'checkmark_circle' },
  { value: 'cancelled', label: 'Cancelled', icon: 'xmark_circle' }
];

const onRefresh = async (done) => {
  try {
    await emergencyStore.fetchMyRequests();
  } finally {
    if (done) done();
  }
};

// Filtered requests based on selected status
const filteredRequests = computed(() => {
  if (selectedStatus.value === 'all') {
    return emergencyStore.myRequests;
  }
  return emergencyStore.myRequests.filter(req => req.status === selectedStatus.value);
});

/**
 * Get count of emergencies by status
 */
const getStatusCount = (status) => {
  if (status === 'all') {
    return emergencyStore.myRequests.length;
  }
  return emergencyStore.myRequests.filter(req => req.status === status).length;
};

/**
 * Format date with relative time
 */
const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  if (date.toDateString() === today.toDateString()) {
    return `Today at ${date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}`;
  } else if (date.toDateString() === yesterday.toDateString()) {
    return `Yesterday at ${date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}`;
  }
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

/**
 * Get status display text
 */
const getStatusText = (status) => {
  const statusMap = {
    pending: 'Pending Response',
    dispatched: 'Responder Dispatched',
    in_progress: 'Emergency In Progress',
    resolved: 'Resolved',
    cancelled: 'Cancelled'
  };
  return statusMap[status] || status;
};

/**
 * Get status description
 */
const getStatusDescription = (status) => {
  const descriptions = {
    pending: 'Waiting for responder to accept',
    dispatched: 'Responder is on their way',
    in_progress: 'Responder is providing assistance',
    resolved: 'Emergency has been resolved',
    cancelled: 'Emergency request was cancelled'
  };
  return descriptions[status] || 'Processing';
};

/**
 * Get estimated time based on status
 */
const getEstimatedTime = (status) => {
  const times = {
    pending: 'Estimated response: 5-10 minutes',
    dispatched: 'Estimated arrival: 3-5 minutes',
    in_progress: 'Estimated completion: 10-15 minutes'
  };
  return times[status] || 'Processing your request';
};

/**
 * Get status icon
 */
const getStatusIcon = (status) => {
  const icons = {
    pending: 'clock_fill',
    dispatched: 'person_fill_checkmark',
    in_progress: 'arrow_right_circle_fill',
    resolved: 'checkmark_circle_fill',
    cancelled: 'xmark_circle_fill'
  };
  return icons[status] || 'questionmark_circle_fill';
};

/**
 * Get status color classes
 */
const getStatusColor = (status) => {
  const colors = {
    pending: 'text-yellow-500',
    dispatched: 'text-blue-500',
    in_progress: 'text-purple-500',
    resolved: 'text-green-500',
    cancelled: 'text-red-500'
  };
  return colors[status] || 'text-gray-500';
};

/**
 * Get progress percentage
 */
const getProgressValue = (status) => {
  const progress = {
    pending: '25%',
    dispatched: '50%',
    in_progress: '75%',
    resolved: '100%'
  };
  return progress[status];
};

/**
 * Truncate text
 */
const truncateText = (text, length) => {
  if (!text) return '';
  if (text.length <= length) return text;
  return text.substring(0, length) + '...';
};

/**
 * Refresh handler
 */
const handleRefresh = async () => {
  await emergencyStore.fetchMyRequests();
};

/**
 * View emergency details
 */
const viewEmergencyDetails = (emergencyId) => {
  if ($f7?.views?.current?.router) {
    $f7.views.current.router.navigate(`/emergency/${emergencyId}`);
  }
};

/**
 * Cancel emergency
 */
const cancelEmergency = async (emergency) => {
  f7.dialog.confirm(
    'Are you sure you want to cancel this emergency request?',
    'Cancel Emergency',
    async () => {
      f7.preloader.show(); // Show loading since we are talking to the server
      try {
        const success = await emergencyStore.cancelEmergencyById(emergency.id);
        f7.preloader.hide();

        if (success) {
          f7.toast.create({
            text: 'Emergency request cancelled',
            closeTimeout: 2000,
            color: 'red'
          }).open();
        }
      } catch (error) {
        f7.preloader.hide();
        f7.dialog.alert('Could not cancel request. Please try again.');
      }
    }
  );
};

/**
 * Go to trigger emergency page
 */
const goToTriggerEmergency = () => {
  if ($f7?.views?.current?.router) {
    $f7.views.current.router.navigate('/emergency/trigger');
  }
};

let refreshInterval = null;

onMounted(async () => {
  await emergencyStore.fetchMyRequests();

  refreshInterval = setInterval(() => {
    emergencyStore.fetchMyRequests();
  }, 30000);
});

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval);
});
</script>

<style scoped>
/* Hide scrollbar for filter tabs */
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

/* Custom animations */
@keyframes bounce-slow {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

.animate-bounce-slow {
  animation: bounce-slow 2s infinite;
}

.active\:scale-\[0\.98\]:active {
  transform: scale(0.98);
}
</style>
