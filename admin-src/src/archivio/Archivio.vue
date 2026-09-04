<script setup>
import { ref, onMounted } from 'vue';
import { t } from '../shared/i18n.js';

const eventi = ref([]);
const anni = ref([]);
const anno = ref(0);
const loading = ref(false);

function apiUrl(path) {
  return window.WSMA_CONFIG.restUrl + path;
}

async function load() {
  loading.value = true;
  try {
    const url = new URL(apiUrl('archivio'));
    if (anno.value) url.searchParams.set('anno', anno.value);
    const res = await fetch(url, { headers: { 'X-WP-Nonce': window.WSMA_CONFIG.nonce } });
    const data = await res.json();
    eventi.value = data.eventi;
    anni.value = data.anni;
  } finally {
    loading.value = false;
  }
}

function filtraAnno(a) {
  anno.value = a;
  const url = new URL(window.location.href);
  if (a) {
    url.searchParams.set('anno', a);
  } else {
    url.searchParams.delete('anno');
  }
  window.history.replaceState({}, '', url);
  load();
}

onMounted(() => {
  const url = new URL(window.location.href);
  anno.value = parseInt(url.searchParams.get('anno') || '0', 10);
  load();
});
</script>

<template>
  <div class="wva">
    <h2>{{ t('arch_h2', 'Workshop Archive') }}</h2>
    <div class="wva-intro">{{ t('arch_intro', 'Past events · sorted by most recent') }}</div>

    <div v-if="anni.length" class="wva-anni">
      <a href="#" :class="{ on: !anno }" @click.prevent="filtraAnno(0)">{{ t('arch_tutti', 'All') }}</a>
      <a v-for="a in anni" :key="a" href="#" :class="{ on: a === anno }" @click.prevent="filtraAnno(a)">{{ a }}</a>
    </div>

    <div v-if="loading" class="wva-empty">{{ t('arch_caricamento', 'Loading…') }}</div>
    <template v-else>
      <div v-if="!eventi.length" class="wva-empty">{{ t('arch_nessun_evento', 'No events in the archive.') }}</div>
      <div v-else class="wva-grid">
        <div v-for="e in eventi" :key="e.id" class="wva-card">
          <div class="wva-card-foto" :style="e.foto ? { backgroundImage: `url('${e.foto}')` } : {}"></div>
          <div class="wva-card-body">
            <div class="wva-card-cat">{{ e.cat_name }}</div>
            <div class="wva-card-periodo">{{ e.periodo }}</div>
            <div v-if="e.ora" class="wva-card-meta">{{ t('arch_ore', 'at') }} {{ e.ora }}</div>
            <div class="wva-card-stats">
              {{ t('arch_partecipanti_finali', 'Final participants') }}
              <strong>{{ e.occupati }}/{{ e.totali }}</strong>
            </div>
            <div class="wva-card-actions">
              <a v-if="e.link_partecipanti" class="wva-link" :href="e.link_partecipanti">{{ t('arch_link_partecipanti', 'Participants') }}</a>
              <a v-if="e.link_gestisci" class="wva-link" :href="e.link_gestisci">{{ t('arch_link_apri', 'Open') }}</a>
              <a v-if="e.link_pagina" class="wva-link" :href="e.link_pagina" target="_blank" rel="noopener">{{ t('arch_link_pagina', 'Page') }}</a>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.wva {
  color: #2c3338;
  max-width: 100%;
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
}

.wva h2 {
  color: #1d2327;
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #dcdcde;
  margin: 0 0 16px;
  padding-bottom: 8px;
}

.wva-intro {
  color: #646970;
  font-size: 13px;
  margin: -10px 0 16px;
}

.wva-anni {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 16px;
}

.wva-anni a {
  padding: 4px 10px;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  color: #2c3338;
  text-decoration: none;
  font-size: 12px;
}

.wva-anni a.on {
  background: #2271b1;
  border-color: #2271b1;
  color: #fff;
}

.wva-empty {
  text-align: center;
  color: #646970;
  padding: 40px 0;
  font-size: 14px;
  background: #fff;
  border: 1px dashed #c3c4c7;
  border-radius: 4px;
}

.wva-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.wva-card {
  background: #ffffff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: all 0.15s ease-in-out;
}

.wva-card:hover {
  border-color: #2271b1;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.wva-card-foto {
  height: 120px;
  border-radius: 4px;
  background-color: #f0f0f1;
  background-size: cover;
  background-position: center;
}

.wva-card-cat {
  color: #1d2327;
  font-size: 14px;
  font-weight: 600;
}

.wva-card-periodo {
  color: #2c3338;
  font-size: 13px;
}

.wva-card-meta {
  color: #646970;
  font-size: 12px;
}

.wva-card-stats {
  display: flex;
  justify-content: space-between;
  padding-top: 8px;
  border-top: 1px solid #f0f0f1;
  font-size: 13px;
  color: #2c3338;
}

.wva-card-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  padding-top: 4px;
}

.wva-link {
  color: #2271b1;
  font-size: 12px;
  text-decoration: none;
}

.wva-link:hover {
  text-decoration: underline;
}

/* Frontend dark theme — applied only when embedded via shortcode on the
   public site with "Tema Frontend Predefinito" set to Dark (see
   WS_Shortcode_Base::render(), which wraps output in .ws-theme-dark). */
.ws-theme-dark .wva {
  color: #fff;
}

.ws-theme-dark .wva h2 {
  color: #fff;
  border-bottom-color: rgba(255, 255, 255, 0.12);
}

.ws-theme-dark .wva-intro {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wva-anni a {
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
  background: transparent;
}

.ws-theme-dark .wva-anni a.on {
  background: #ff6608;
  border-color: #ff6608;
  color: #fff;
}

.ws-theme-dark .wva-empty {
  color: rgba(255, 255, 255, 0.5);
  background: transparent;
  border-color: rgba(255, 255, 255, 0.2);
}

.ws-theme-dark .wva-card {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow: none;
}

.ws-theme-dark .wva-card:hover {
  border-color: #ff6608;
  box-shadow: none;
}

.ws-theme-dark .wva-card-cat {
  color: #ff6608;
}

.ws-theme-dark .wva-card-periodo {
  color: #fff;
}

.ws-theme-dark .wva-card-meta {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wva-card-stats {
  color: #fff;
  border-top-color: rgba(255, 255, 255, 0.1);
}

.ws-theme-dark .wva-link {
  color: #ff9a6f;
}
</style>
