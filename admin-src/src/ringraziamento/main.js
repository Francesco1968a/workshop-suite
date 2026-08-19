import { createApp } from 'vue';
import Ringraziamento from './Ringraziamento.vue';

const mountEl =
  document.getElementById('ws-ringraziamento-app') || document.getElementById('fvw-ringraziamento-app');

if (mountEl) {
  createApp(Ringraziamento).mount(mountEl);
}
