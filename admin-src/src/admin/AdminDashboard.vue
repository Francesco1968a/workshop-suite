<script setup>
import { ref, onMounted } from 'vue';
import PartecipantiTab from './PartecipantiTab.vue';
import EventiTab from './EventiTab.vue';
import CategorieTipiTab from './CategorieTipiTab.vue';
import { t } from '../shared/i18n.js';

// wp-admin backend: distinguished by the ?page= WP always appends for its
// own submenu URLs. Frontend shortcodes have no such URL naturally, so
// WS_Shortcode_Categorie passes an explicit panelMode config flag instead
// — see includes/class-ws-shortcode-categorie.php.
const page = new URL(window.location.href).searchParams.get('page');
const isCatPage = page === 'workshop-suite-categorie' || window.WSMA_CONFIG?.panelMode === 'categorie';
const isVirtualPage = window.WSMA_CONFIG?.panelMode === 'virtuale';
const tabs = isCatPage
  ? { categorie: t('admin_tab_categorie', 'Categories'), tipologia: t('admin_tab_tipologia', 'Types') }
  : isVirtualPage
    ? { categorie: t('admin_tab_categoria', 'Category'), eventi: t('admin_tab_eventi', 'Events'), partecipanti: t('admin_tab_partecipanti', 'Participants') }
    : { eventi: t('admin_tab_eventi', 'Events'), partecipanti: t('admin_tab_aggiungi_partecipante', 'Add Participant') };

const initVista = new URL(window.location.href).searchParams.get('vista') || Object.keys(tabs)[0];
const vista = ref(initVista);
const msg = ref('');

function syncFromUrl() {
  const v = new URL(window.location.href).searchParams.get('vista');
  vista.value = tabs[v] ? v : isCatPage ? 'categorie' : 'eventi';
}

function goTo(v) {
  const url = new URL(window.location.href);
  url.searchParams.set('vista', v);
  url.searchParams.delete('edit_cat');
  url.searchParams.delete('edit_ev');
  url.searchParams.delete('edit_isc');
  url.searchParams.delete('pre_evento');
  window.history.pushState({}, '', url);
  vista.value = v;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function flash(text) {
  msg.value = text;
  setTimeout(() => {
    if (msg.value === text) msg.value = '';
  }, 4000);
}

onMounted(() => {
  syncFromUrl();
  window.addEventListener('popstate', syncFromUrl);
});
</script>

<template>
  <div class="wv-dash">
    <h2>{{ isVirtualPage ? t('admin_h2_aula_virtuale', '💻 Virtual Classroom') : isCatPage ? t('admin_h2_categoria_tipologia', 'Category & Type') : 'Dashboard' }}</h2>
    <div v-if="msg" class="msg">{{ msg }}</div>
    <nav>
      <a v-for="(label, key) in tabs" :key="key" :class="{ active: vista === key }" @click="goTo(key)">{{ label }}</a>
    </nav>

    <PartecipantiTab v-if="vista === 'partecipanti'" @message="flash" />
    <EventiTab v-else-if="vista === 'eventi'" :filter-modalita="isVirtualPage ? 'virtuale' : ''" @message="flash" />
    <CategorieTipiTab v-else-if="vista === 'categorie'" sub="categorie" @message="flash" />
    <CategorieTipiTab v-else-if="vista === 'tipologia'" sub="tipologia" @message="flash" />
  </div>
</template>
