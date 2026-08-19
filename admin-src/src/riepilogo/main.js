import { createApp } from 'vue';
import Riepilogo from './Riepilogo.vue';

const mountEl =
  document.getElementById('ws-riepilogo-app') || document.getElementById('fvw-riepilogo-app');

if (mountEl) {
  createApp(Riepilogo).mount(mountEl);
}
