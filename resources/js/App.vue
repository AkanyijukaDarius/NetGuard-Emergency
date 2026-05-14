<template>
  <f7-app v-bind="f7params">

    <template v-if="!userStore.token">
      <f7-view
        main
        :url="!userStore.hasAccount ? '/onboarding/' : '/login/'"
        class="safe-areas"
      />
    </template>

    <template v-else>
      <f7-view main url="/" class="safe-areas" :browser-history="true">

        <f7-toolbar bottom tabbar labels class="custom-tabbar mb-1!">
          <f7-link
            href="/"
            tab-link
            :tab-link-active="currentPath === '/'"
            text="Home"
            icon-f7="house_fill"
          />
          <f7-link
            v-if="userStore.role === 'user'"
            href="/my-requests"
            tab-link
            :tab-link-active="currentPath === '/my-requests'"
            text="My Requests"
            icon-f7="list_bullet"
          />
          <f7-link
            v-if="userStore.role === 'user'"
            href="/responders"
            tab-link
            :tab-link-active="currentPath === '/responders'"
            text="Responders"
            icon-f7="person_fill"
          />
          <f7-link
            v-if="userStore.role === 'responder'"
            href="/resolved-emergencies"
            tab-link
            :tab-link-active="currentPath === '/resolved-emergencies'"
            text="Resolved Emergencies"
            icon-f7="checkmark_circle_fill"
          />
          <f7-link
            href="/settings"
            tab-link
            :tab-link-active="currentPath === '/settings'"
            text="Settings"
            icon-f7="gear_alt_fill"
          />
        </f7-toolbar>

      </f7-view>
    </template>

  </f7-app>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { f7, f7ready } from 'framework7-vue';
import routes from './routes';
import { useUserStore } from './stores/user';
import { useEmergencyStore } from './stores/emergency';

const currentPath = ref('/');
const userStore = useUserStore();
const emergencyStore = useEmergencyStore();

const f7params = {
  name: 'NetGuard Emergency',
  theme: 'auto',
  routes: routes,
  darkMode: false,
  colors: { primary: '#1a5d3b' },
  on: {
    routeChange(newRoute) {
      currentPath.value = newRoute.path;
    }
  }
};

watch(() => userStore.token, (newToken) => {
  if (newToken) {
    emergencyStore.initializeListener();
  } else {
    if (window.Echo) window.Echo.disconnect();
  }
});

onMounted(() => {
  f7ready(() => {
    const token = userStore.token;
    const hasAccount = userStore.hasAccount;

    setTimeout(() => {
      if (token) {
        emergencyStore.initializeListener();
        f7.views.main.router.navigate('/', { reloadCurrent: true });
      } else if (hasAccount) {
        f7.views.main.router.navigate('/login/', { reloadCurrent: true });
      } else {
        f7.views.main.router.navigate('/onboarding/', { reloadCurrent: true });
      }
    }, 100);
  });

});
</script>
<style>
/* 1. Toolbar Container Fix */
.custom-tabbar {
  height: 100px !important;
  background: #ffffff !important;
  border-top: 2px solid rgba(0, 0, 0, 0.08) !important;

}

.custom-tabbar .tab-link {
  height: 100% !important; 
  padding-top: 15px !important; 
  flex-direction: column;
  justify-content: center;
}

.custom-tabbar .tab-link-active {
  background-color: #e8f5e9 !important;
  margin-left: 6px !important;
  margin-right: 6px !important;
}

.custom-tabbar .tab-link i {
  color: #6b7280 !important;
  font-size: 22px !important;
}

.custom-tabbar .tab-link-active i,
.custom-tabbar .tab-link-active .tabbar-label {
  color: #1a5d3b !important; /* Brand green active */
}

.custom-tabbar .tabbar-label {
  font-size: 10px !important;
  font-weight: 700 !important;
  margin-top: 4px !important;
  color: #6b7280;
}
</style>
