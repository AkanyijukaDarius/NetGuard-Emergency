<template>
  <f7-page no-navbar no-toolbar class="onboarding-page">
    <div class="page-content">
      <div class="flex flex-col justify-center min-h-screen p-6 bg-linear-to-b from-gray-50 to-white">

        <div class="text-center mb-10">
          <div class="mx-auto w-20 h-20 bg-[#1a5d3b] rounded-3xl flex items-center justify-center mb-6">
            <f7-icon f7="waveform_path_ecg" size="48" color="white" />
          </div>
          <h1 class="text-3xl font-bold text-[#1a5d3b] tracking-tight">
            Welcome to NetGuard Emergency
          </h1>
          <h1 class="text-xl font-bold text-[#1a5d3b] tracking-tight mt-2">
            Complete Your Profile
          </h1>
          <p class="text-gray-600 mt-2 text-[17px]">
            Help us respond faster in case of emergency
          </p>

          <div class="mt-8 flex justify-center gap-2">
            <div class="h-1.5 w-10 bg-[#1a5d3b] rounded-full"></div>
            <div class="h-1.5 w-10 bg-gray-200 rounded-full"></div>
          </div>
        </div>

        <!-- Form Card -->
        <div class="max-w-md mx-auto w-full">
          <f7-block strong inset class="rounded-3xl shadow-sm border border-gray-100 overflow-hidden">

            <f7-list no-hairlines-md class="my-0">
                <f7-list-item title="Register As" smart-select :smart-select-params="{openIn: 'popover', closeOnSelect: true}">
                    <select v-model="form.role" name="role">
                    <option value="user" selected>Normal User</option>
                    <option value="responder">Responder</option>
                    </select>
                </f7-list-item>

              <f7-list-input
                label="First Name"
                type="text"
                placeholder="Family_Name"
                :value="form.family_name"
                @input="form.family_name = $event.target.value"
                clear-button
              />

              <f7-list-input
                label="Last Name"
                type="text"
                placeholder="Given_Name"
                :value="form.given_name"
                @input="form.given_name = $event.target.value"
                clear-button
              />

              <f7-list-input
                label="Phone Number"
                type="tel"
                placeholder="+256 700 000 000"
                :value="form.phone"
                @input="form.phone = $event.target.value"
                clear-button
              />

              <f7-list-input
                v-if="form.role === 'responder'"
                label="Responder Authorization Code"
                type="text"
                placeholder="Enter Code"
                :value="form.responder_code"
                @input="form.responder_code = $event.target.value"
                info="Required for medical or security personnel"
                class="bg-emerald-50"
                />

              <f7-list-input
                label="National ID / Passport Number"
                type="text"
                placeholder="CM1234567890"
                :value="form.id_document"
                @input="form.id_document = $event.target.value"
                clear-button
              />
            </f7-list>

            <div class="px-4 pb-6 pt-2">
              <p class="text-xs text-gray-500 text-center flex items-center justify-center gap-1">
                <f7-icon f7="shield_fill" size="14" color="green" />
                Your information is encrypted and secure
              </p>
            </div>
          </f7-block>

          <f7-button
            large
            fill
            round
            color="green"
            class="mt-8 font-semibold text-base shadow-md"
            @click="saveUser"
            :disabled="isSubmitting"
          >
            {{ isSubmitting ? 'Saving...' : 'Finish Setup & Continue' }}
          </f7-button>

          <p class="text-center text-xs text-gray-500 mt-6">
            This helps emergency responders reach you quickly
          </p>
          <div class="text-center mt-4">
            <span class="text-sm text-gray-400">Already have an account? </span>
            <f7-link
              href="/login/"
              class="text-[#1a5d3b] font-bold text-sm"
            >
              Sign In
            </f7-link>
          </div>
        </div>
      </div>
    </div>
  </f7-page>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useUserStore } from '@/stores/user';
import { f7 } from 'framework7-vue';

const userStore = useUserStore();
const isSubmitting = ref(false);

const form = reactive({
  given_name: '',
  family_name: '',
  phone: '',
  id_document: '',
  role: 'user',
  responder_code: ''
});

const saveUser = async () => {
  if (!form.given_name.trim() || !form.phone.trim() || !form.family_name.trim()) {
    f7.dialog.alert('Please provide your full name and phone number.');
    return;
  }

  if (form.role === 'responder' && !form.responder_code.trim()) {
    f7.dialog.alert('Responders must provide an authorization code.');
    return;
  }

  isSubmitting.value = true;
  f7.preloader.show();

  try {
    const success = await userStore.registerUser({
      phone: form.phone,
      given_name: form.given_name,
      family_name: form.family_name,
      id_document: form.id_document,
      role: form.role,
      responder_code: form.responder_code
    });

    if (success) {
      f7.views.main.router.navigate('/', {
        reloadCurrent: true,
        animate: true
      });
    }
  } catch (err) {
    const errorMsg = err.response?.data?.message || 'Registration failed.';
    f7.dialog.alert(errorMsg, 'Registration Failed');
  } finally {
    isSubmitting.value = false;
    f7.preloader.hide();
  }
};
</script>

<style scoped>
.onboarding-page {
  background: #f8fafc;
}

f7-list-input {
  --f7-list-item-padding-horizontal: 20px;
}

/* Optional: Make inputs look more modern */
:deep(.item-inner) {
  padding-top: 12px;
  padding-bottom: 12px;
}
</style>
