import { createApp } from 'vue';
import Messaggi from './Messaggi.vue';

const mountEl =
  document.getElementById('ws-messaggi-app') || document.getElementById('fvw-messaggi-app');

if (mountEl) {
  createApp(Messaggi).mount(mountEl);
}
