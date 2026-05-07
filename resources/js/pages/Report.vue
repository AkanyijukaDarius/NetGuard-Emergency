<template>
  <f7-page name="report">
    <div class="bg-[#1a5d3b] text-white pt-4 pb-4 px-5 shadow-lg">
      <div class="flex items-center space-x-3">
        <f7-link back icon-f7="chevron_left" color="white" />
        <h1 class="text-2xl font-bold">Report Emergency</h1>
      </div>
      <p class="mt-2 text-sm opacity-80 pl-8">Select the type of incident for AI triage.</p>
    </div>

    <div class="px-4 mt-4 pb-20">
      <div class="grid grid-cols-2 gap-4">

        <button v-for="type in emergencyTypes" :key="type.title"
          @click="selectType(type)"
          class="bg-white p-6 rounded-4xl shadow-md border border-gray-50 flex flex-col items-center text-center space-y-3 active:scale-95 transition-transform"
        >
          <div :class="type.bgColor" class="p-4 rounded-2xl">
            <f7-icon :f7="type.icon" :class="type.iconColor" size="32" />
          </div>
          <div>
            <p class="font-bold text-gray-800 text-sm">{{ type.title }}</p>
            <p class="text-[10px] text-gray-400 font-medium">{{ type.desc }}</p>
          </div>
        </button>

      </div>

      <div class="mt-6 bg-vht/10 border border-vht/20 p-5 rounded-3xl flex items-start space-x-4">
        <f7-icon f7="info_circle_fill" class="text-vht" size="20" />
        <div>
          <p class="text-xs font-bold text-vht uppercase tracking-wider">First Aid Tip</p>
          <p class="text-sm text-gray-700 leading-tight mt-1">
            If reporting a road accident, ensure you are in a safe position away from traffic before continuing.
          </p>
        </div>
      </div>
    </div>
  </f7-page>
</template>

<script setup>
import { f7 } from 'framework7-vue';

const emergencyTypes = [
  { title: 'Road Accident', icon: 'car_fill', desc: 'Boda or Vehicle', bgColor: 'bg-red-50', iconColor: 'text-red-500' },
  { title: 'Maternal', icon: 'person_2_fill', desc: 'Pregnancy/Labor', bgColor: 'bg-purple-50', iconColor: 'text-purple-500' },
  { title: 'Medical', icon: 'heart_fill', desc: 'Illness/Attack', bgColor: 'bg-blue-50', iconColor: 'text-blue-500' },
  { title: 'Security', icon: 'shield_fill', desc: 'Theft/Violence', bgColor: 'bg-orange-50', iconColor: 'text-orange-500' },
];

const selectType = (type) => {
  f7.dialog.confirm(`Initialize ${type.title} triage via AI?`, 'Confirm Emergency', () => {
    f7.dialog.preloader('Activating Network Priority...');
    setTimeout(() => {
      f7.dialog.close();
      // Logic to move to the AI Chat or Call screen
    }, 1500);
  });
};
</script>
