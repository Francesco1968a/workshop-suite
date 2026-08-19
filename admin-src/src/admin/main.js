import { createApp } from 'vue';
import AdminDashboard from './AdminDashboard.vue';

const mountEl =
  document.getElementById('ws-admin-app') || document.getElementById('fvw-admin-app');

if (mountEl) {
  createApp(AdminDashboard).mount(mountEl);
}
