import { createApp } from 'vue';
import Partecipante from './Partecipante.vue';

const mountEl =
  document.getElementById('ws-partecipante-app') || document.getElementById('fvw-partecipante-app');

if (mountEl) {
  createApp(Partecipante).mount(mountEl);
}
