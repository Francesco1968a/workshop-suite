import { createApp } from 'vue';
import PartecipantiLista from './PartecipantiLista.vue';

const mountEl =
  document.getElementById('ws-partecipanti-lista-app') || document.getElementById('fvw-partecipanti-lista-app');

if (mountEl) {
  createApp(PartecipantiLista).mount(mountEl);
}
