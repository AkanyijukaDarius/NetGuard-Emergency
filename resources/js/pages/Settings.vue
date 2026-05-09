<template>
  <f7-page class="bg-gray-50 pb-10">
    <div class="bg-[#1a5d3b] sticky top-0 z-50 text-white pt-10 pb-4 px-4 shadow-lg">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <f7-link back>
            <f7-icon f7="chevron_left" size="24" color="white" />
          </f7-link>
          <span class="text-2xl font-bold">Settings</span>
        </div>
        <f7-icon f7="gear" size="26" color="white" />
      </div>
    </div>

    <f7-block class="pt-2 px-4 space-y-6 pb-20">
      <div class="bg-white rounded-3xl shadow-md p-6 border border-gray-100">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Account Information</p>

        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <div class="w-14 h-14 bg-[#1a5d3b] rounded-2xl flex items-center justify-center">
              <f7-icon f7="person_fill" size="32" color="white" />
            </div>
            <div>
              <p class="font-bold text-lg text-gray-800">{{ userStore.fullName }}</p>
              <div class="flex items-center gap-1.5">
                <span :class="userStore.isKycVerified ? 'text-green-600' : 'text-orange-500'" class="text-xs font-semibold">
                  {{ userStore.isKycVerified ? 'Verified' : 'Verification Required' }}
                </span>
                <f7-icon
                  :f7="userStore.isKycVerified ? 'checkmark_seal_fill' : 'exclamationmark_shield_fill'"
                  size="16"
                  :color="userStore.isKycVerified ? 'green' : 'orange'"
                />
              </div>
              <p class="text-xs text-gray-500 mt-0.5">{{ userStore.phoneNumber }}</p>
            </div>
          </div>
          <f7-link class="text-[#1a5d3b]">
            <f7-icon f7="chevron_right" size="20" />
          </f7-link>

        </div>
        <f7-button
          v-if="!userStore.isKycVerified"
          small
          outline
          color="green"
          class="mt-4 bg-green-50 border-green-200 text-green-700 hover:bg-green-100"
          @click="userStore.checkVerificationStatus"
        >
          Check Status
        </f7-button>
      </div>

      <div class="bg-white rounded-3xl shadow-md p-6 border border-gray-100">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Security</p>

        <f7-list no-hairlines-md class="m-0">
          <f7-list-item title="Biometric Login">
            <template #after>
              <f7-toggle
                color="green"
                :checked="userStore.biometricEnabled"
                @change="(e) => userStore.biometricEnabled = e.target.checked"
              />
            </template>
          </f7-list-item>

          <f7-list-item title="Two-Factor Authentication">
            <template #after>
              <span class="text-green-600 text-xs font-bold">Enabled</span>
            </template>
          </f7-list-item>
        </f7-list>
      </div>

      <!-- Network & API Information -->
      <div class="bg-white rounded-3xl shadow-md p-6 border border-gray-100">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Network & Integration</p>
        <div class="space-y-5">
          <div class="flex justify-between items-center">
            <span class="text-sm font-medium">QoD Profile</span>
            <span class="text-xs font-bold text-[#1a5d3b]">DOWNLINK_M_UPLINK_L</span> 
          </div>
          <div class="flex justify-between items-center border-t border-gray-100 pt-4">
            <span class="text-sm font-medium">Registration status</span>
            <span class="text-xs font-bold" :class="userStore.isRegistered ? 'text-green-600' : 'text-red-500'">
              {{ userStore.isRegistered ? 'Active' : 'Inactive' }}
            </span>
          </div>
        </div>
      </div>

      <!-- AI Agent with Persistent Toggle -->
      <div class="bg-linear-to-br from-white to-emerald-50 rounded-3xl shadow-md p-6 border border-emerald-100">
        <div class="flex justify-between items-start">
          <div>
            <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">AI Features</p>
            <h4 class="font-bold text-gray-800 mt-1">Agentic Triage Agent</h4>
            <p class="text-xs text-gray-600 mt-1">Smart symptom analysis & dispatch recommendation</p>
          </div>
          <!-- Calls store action to save preference -->
          <f7-toggle
            color="green"
            :checked="userStore.aiEnabled"
            @change="(e) => userStore.toggleAi(e.target.checked)"
          />
        </div>
      </div>

      <f7-button color="red" outline class="mt-8 bg-red-100 rounded-2xl" @click="handleLogout">
        Sign Out
      </f7-button>
    </f7-block>
  </f7-page>
</template>

<script setup>
import { f7 } from 'framework7-vue';
import { useUserStore } from '../stores/user';

const userStore = useUserStore();

const handleLogout = () => {
  f7.dialog.confirm(
    `Are you sure you want to sign out, ${userStore.givenName}?`,
    'Sign Out',
    () => {
      userStore.logout();
      f7.views.main.router.navigate('/login');
    }
  );
};

</script>
