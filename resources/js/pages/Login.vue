<template>
  <f7-page no-toolbar no-navbar no-swipeback login-screen class="bg-gray-50">
    <div class="flex flex-col items-center justify-center min-h-screen px-6">
        <div class="mx-auto w-20 h-20 bg-[#1a5d3b] rounded-3xl flex items-center justify-center mb-6">
            <f7-icon f7="waveform_path_ecg" size="48" color="white" />
          </div>

      <f7-login-screen-title class="text-3xl font-extrabold text-gray-800 mb-2">
        Welcome Back
      </f7-login-screen-title>

      <p class="text-gray-500 mb-8 text-center">
        Enter your registered phone number to access NetGuard Emergency services.
      </p>

      <f7-list inset class="w-full m-0 space-y-4">
        <f7-list-input
          label="Phone Number"
          type="tel"
          placeholder="e.g., +256..."
          required
          validate
          clear-button
          :value="loginPhone"
          @input="loginPhone = $event.target.value"
          class="bg-white rounded-xl border border-gray-100 shadow-sm"
        >
          <template #media>
            <f7-icon f7="phone_fill" color="green" />
          </template>
        </f7-list-input>
      </f7-list>

      <f7-block class="w-full mt-8">
        <f7-button
          large
          fill
          round
          color="green"
          class="bg-[#1a5d3b] shadow-md font-bold uppercase tracking-wide"
          @click="handleLogin"
        >
          Secure Sign In
        </f7-button>
      </f7-block>
    </div>
  </f7-page>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { f7 } from 'framework7-vue';
import axios from 'axios';
import { useUserStore } from '../stores/user';

const userStore = useUserStore();
const loginPhone = ref('');

onMounted(() => {
  if (userStore.phoneNumber) {
    loginPhone.value = userStore.phoneNumber;
  }

  if (userStore.token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${userStore.token}`;
  }
});

const handleLogin = async () => {
  if (!loginPhone.value) {
    f7.dialog.alert('Please enter your phone number to proceed.');
    return;
  }

  f7.preloader.show();

  try {
    const success = await userStore.loginUser(loginPhone.value.trim());

    f7.preloader.hide();

    if (success) {
      userStore.startPolling();

      if (userStore.role === 'responder') {
        userStore.initRealTimeListener();
      }

      f7.views.main.router.navigate('/', {
        animate: true,
        transition: 'f7-flip',
        reloadCurrent: true,
      });
    } else {
      f7.dialog.alert('Login failed. Please check your credentials.');
    }
  } catch (error) {
    f7.preloader.hide();
    console.error('Login Error:', error.response?.data || error.message);

    if (error.response?.status === 404) {
      f7.dialog.confirm(
        'Account not found. Would you like to register as a new user?',
        'Access Denied',
        () => f7.views.main.router.navigate('/onboarding/')
      );
    } else {
      f7.dialog.alert('A server error occurred. Please try again later.');
    }
  }
};
</script>

<style scoped>
:deep(.item-label) {
  font-weight: 700;
  color: #374151;
  font-size: 12px;
  text-transform: uppercase;
  margin-bottom: 4px;
}
</style>
