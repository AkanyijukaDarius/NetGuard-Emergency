import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import './echo';

import Framework7 from 'framework7/lite-bundle';
import Framework7Vue, { registerComponents } from 'framework7-vue/bundle';


import 'framework7/css/bundle';
import 'framework7-icons/css/framework7-icons.css'; 
import '../css/app.css';

Framework7.use(Framework7Vue);

import App from './App.vue';

const app = createApp(App);
const pinia = createPinia();

registerComponents(app);

app.use(pinia);
app.mount('#app');
