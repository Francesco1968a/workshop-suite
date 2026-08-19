<script setup>
import { ref, watch, onMounted } from 'vue';

const q = ref('');
const items = ref([]);
const count = ref(0);
const loading = ref(false);
let debounceTimer = null;

async function load() {
  loading.value = true;
  try {
    const url = new URL(window.WS_CONFIG.restUrl + 'partecipanti');
    if (q.value) url.searchParams.set('q', q.value);
    const res = await fetch(url, { headers: { 'X-WP-Nonce': window.WS_CONFIG.nonce } });
    const data = await res.json();
    items.value = data.items;
    count.value = data.count;
  } finally {
    loading.value = false;
  }
}

function schedaUrl(id) {
  const url = new URL(window.location.href);
  url.hash = '';
  url.searchParams.set('pid', id);
  return url.toString() + '#wvpx-scheda';
}

watch(q, () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(load, 300);
});

onMounted(load);
</script>

<template>
  <div class="wvpl">
    <h2>Partecipanti</h2>
    <input
      type="text"
      class="wvpl-search"
      v-model="q"
      placeholder="Cerca per nome, email, telefono, città…"
    />
    <div class="wvpl-count">
      {{ count }} contatti
      <span v-if="q">&nbsp;per "{{ q }}"</span>
    </div>
    <div :class="{ 'wvpl-loading': loading }">
      <div v-for="p in items" :key="p.id" class="wvpl-row">
        <a class="wvpl-nome" :href="schedaUrl(p.id)">{{ p.nome }}</a>
        <span class="wvpl-meta">
          {{ p.email }}
          <template v-if="p.citta"> · {{ p.citta }}</template>
        </span>
        <span class="wvpl-badge">{{ p.totali }} iscr.</span>
        <span v-if="p.confermate > 0" class="wvpl-badge wvpl-badge--ok">{{ p.confermate }} conf.</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wvpl {
  color: #2c3338;
  max-width: 100%;
  margin: 0;
  padding: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.wvpl h2 {
  color: #1d2327;
  border-bottom: 1px solid #dcdcde;
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin: 0 0 16px;
  padding-bottom: 8px;
}

.wvpl-search {
  display: block;
  margin: 0 0 16px;
  max-width: 320px;
  width: 100%;
  box-sizing: border-box;
  background: #fff;
  border: 1px solid #8c8f94;
  border-radius: 4px;
  box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.07);
  padding: 0 10px;
  height: 36px;
  color: #2c3338;
  font-size: 13px;
}

.wvpl-search:focus {
  outline: none;
  border-color: #2271b1;
  box-shadow: 0 0 0 1px #2271b1;
}

.wvpl-count {
  color: #646970;
  font-size: 13px;
  font-weight: 500;
  margin: 12px 0;
}

.wvpl-loading {
  opacity: 0.5;
}

.wvpl-row {
  background: #fff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
  padding: 12px 16px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 14px;
}

.wvpl-row:hover {
  border-color: #2271b1;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
}

.wvpl-nome {
  color: #2271b1;
  flex: 1;
  min-width: 200px;
  font-weight: 600;
  font-size: 14px;
  text-decoration: none;
}

.wvpl-nome:hover {
  color: #135e96;
  text-decoration: underline;
}

.wvpl-meta {
  color: #646970;
  font-size: 13px;
}

.wvpl-badge {
  color: #2c3338;
  background: #f6f7f7;
  border: 1px solid #dcdcde;
  border-radius: 3px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  padding: 2px 8px;
  font-size: 11px;
  font-weight: 600;
  display: inline-block;
}

.wvpl-badge--ok {
  color: #008a20;
  background: #edfaef;
  border-color: #c3e6cb;
}

/* Frontend dark theme — see WS_Shortcode_Base::render(). */
.ws-theme-dark .wvpl {
  color: #fff;
}

.ws-theme-dark .wvpl h2 {
  color: #fff;
  border-bottom-color: rgba(255, 255, 255, 0.12);
}

.ws-theme-dark .wvpl-search {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
  box-shadow: none;
}

.ws-theme-dark .wvpl-search:focus {
  border-color: #ff6608;
  box-shadow: 0 0 0 1px #ff6608;
}

.ws-theme-dark .wvpl-count {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvpl-row {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow: none;
}

.ws-theme-dark .wvpl-row:hover {
  border-color: #ff6608;
  box-shadow: none;
}

.ws-theme-dark .wvpl-nome {
  color: #ff9a6f;
}

.ws-theme-dark .wvpl-nome:hover {
  color: #ff6608;
}

.ws-theme-dark .wvpl-meta {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvpl-badge {
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.2);
}

.ws-theme-dark .wvpl-badge--ok {
  color: #7ddc8e;
  background: rgba(125, 220, 142, 0.1);
  border-color: #7ddc8e;
}
</style>
