import { createApp } from 'vue';
import Archivio from './Archivio.vue';

const mountEl =
  document.getElementById('ws-archivio-app') || document.getElementById('fvw-archivio-app');

if (mountEl) {
  createApp(Archivio).mount(mountEl);
}
