import { createApp } from 'vue';
import Calendario from './Calendario.vue';

const mountEl =
  document.getElementById('ws-calendario-app') || document.getElementById('fvw-calendario-app');

if (mountEl) {
  createApp(Calendario).mount(mountEl);
}
