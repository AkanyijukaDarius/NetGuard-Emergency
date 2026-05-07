<template>
  <f7-page class="bg-gray-50">
    <f7-navbar title="Not Found" back-link="Back" />
    <f7-block class="text-center">
      <h1 class="text-4xl font-black text-[#1a5d3b]">404</h1>
      <p class="text-gray-500">Sorry, this page doesn't exist.</p>
      <f7-link href="/" class="mt-4 inline-block font-bold">Return Home</f7-link>
    </f7-block>
  </f7-page>
</template>

<script setup>
import { f7Page, f7Navbar, f7Block, f7Link } from 'framework7-vue';
</script>
