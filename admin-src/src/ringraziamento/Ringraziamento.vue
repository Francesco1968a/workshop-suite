<script setup>
import { ref, onMounted } from 'vue';

const loading = ref(true);
const autosend = ref(true);
const oggi = ref([]);
const prossimi = ref([]);
const nextCron = ref(null);
const busy = ref(new Set());
const msg = ref('');

function apiUrl(path) {
  return window.WS_CONFIG.restUrl + path;
}

function headers(extra = {}) {
  return { 'X-WP-Nonce': window.WS_CONFIG.nonce, ...extra };
}

function flash(text) {
  msg.value = text;
  setTimeout(() => {
    if (msg.value === text) msg.value = '';
  }, 4000);
}

async function load() {
  loading.value = true;
  const res = await fetch(apiUrl('ringraziamento/pannello'), { headers: headers() });
  const data = await res.json();
  autosend.value = data.autosend;
  oggi.value = data.oggi;
  prossimi.value = data.prossimi;
  nextCron.value = data.next_cron;
  loading.value = false;
}

async function saveImpostazioni() {
  await fetch(apiUrl('ringraziamento/impostazioni'), {
    method: 'POST',
    headers: headers({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ autosend: autosend.value }),
  });
  flash('Impostazioni salvate.');
}

async function inviaUna(item) {
  busy.value.add(item.isc_id);
  try {
    const res = await fetch(apiUrl('ringraziamento/iscrizione/' + item.isc_id + '/invia'), { method: 'POST', headers: headers() });
    const data = await res.json();
    if (!res.ok) {
      flash(data.message || 'Invio fallito.');
      return;
    }
    flash('Mail inviata a ' + item.nome + '.');
    load();
  } finally {
    busy.value.delete(item.isc_id);
  }
}

async function saltaUna(item) {
  if (!window.confirm('Saltare questa iscrizione?')) return;
  busy.value.add(item.isc_id);
  try {
    await fetch(apiUrl('ringraziamento/iscrizione/' + item.isc_id + '/salta'), { method: 'POST', headers: headers() });
    flash(item.nome + ' saltato.');
    load();
  } finally {
    busy.value.delete(item.isc_id);
  }
}

async function inviaTutte() {
  busy.value.add('all');
  try {
    const res = await fetch(apiUrl('ringraziamento/invia-tutte'), { method: 'POST', headers: headers() });
    const data = await res.json();
    flash('Inviate ' + data.inviate + ' mail ringraziamento.');
    load();
  } finally {
    busy.value.delete('all');
  }
}

function setAutosend(val) {
  autosend.value = val;
  saveImpostazioni();
}

onMounted(load);
</script>

<template>
  <div v-if="!loading" class="wvring">
    <h2>Mail #4 · Ringraziamento T+2</h2>
    <div v-if="msg" class="notice">{{ msg }}</div>
    <div class="info-box">
      Ogni mattina alle 10:00 il sistema invia <strong style="color: #7ddc8e">Mail #4</strong>
      ai Confermati di eventi conclusi esattamente 2 giorni fa. Tono amichevole: ringraziamento + Drive condiviso per le foto.
    </div>

    <div class="card">
      <h3>Impostazioni</h3>
      <label>Stato</label>
      <div style="display: flex; gap: 8px">
        <button :class="{ accent: autosend }" @click="setAutosend(true)">ATTIVO — invia automaticamente</button>
        <button @click="setAutosend(false)">SOSPESO</button>
      </div>
      <div class="hint" style="margin-top: 14px">
        Prossimo invio automatico: <strong style="color: #fff">{{ nextCron || '(non pianificato)' }}</strong>
      </div>
    </div>

    <div class="card">
      <h3>Da inviare oggi (eventi conclusi 2gg fa)</h3>
      <template v-if="oggi.length">
        <p style="color: rgba(255, 255, 255, 0.7); font-size: 13px">
          Sono <strong style="color: #7ddc8e">{{ oggi.length }}</strong> iscrizioni di eventi appena conclusi:
        </p>
        <div v-for="item in oggi" :key="item.isc_id" class="item">
          <div class="info">
            <strong>{{ item.nome }}</strong> · {{ item.evento }}
            <div class="meta">{{ item.email }}</div>
          </div>
          <button class="accent btn-sm" :disabled="busy.has(item.isc_id)" @click="inviaUna(item)">🙏 Invia ora</button>
          <button class="btn-sm btn-ghost" :disabled="busy.has(item.isc_id)" @click="saltaUna(item)">⏭ Salta</button>
        </div>
        <div class="row-actions">
          <button :disabled="busy.has('all')" @click="inviaTutte">⚡ Invia tutte adesso</button>
        </div>
      </template>
      <div v-else class="empty">Nessun evento concluso 2 giorni fa con partecipanti confermati.</div>
    </div>

    <div v-if="prossimi.length" class="card">
      <h3>In arrivo nei prossimi giorni</h3>
      <div v-for="g in prossimi" :key="g.giorni" class="prossimi-group">
        <div class="prossimi-head">
          Tra <span class="countdown">{{ g.giorni }} giorni</span> → {{ g.iscrizioni.length }} iscrizione/i
        </div>
        <div v-for="item in g.iscrizioni" :key="item.isc_id" class="prossimi-row">
          • {{ item.nome }} · {{ item.evento }}
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wvring {
  color: #fff;
  max-width: 1040px;
  margin: 0 auto;
  font-family: -apple-system, system-ui, sans-serif;
}

.wvring h2 {
  color: #fff;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  margin: 0 0 20px;
  padding-bottom: 12px;
  font-size: 16px;
  font-weight: 300;
}

.wvring h3 {
  color: #888;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  margin: 0 0 14px;
  font-size: 11px;
  font-weight: 400;
}

.wvring .card {
  border: 1px solid rgba(255, 255, 255, 0.1);
  margin-bottom: 20px;
  padding: 20px;
}

.wvring label {
  color: #888;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  margin-bottom: 8px;
  font-size: 11px;
  display: block;
}

.wvring button {
  color: #fff;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  cursor: pointer;
  background: none;
  border: 1px solid #fff;
  padding: 11px 24px;
  font-family: inherit;
  font-size: 11px;
  transition: all 0.3s;
}

.wvring button:hover:not(:disabled) {
  color: #000;
  background: #fff;
}

.wvring button:disabled {
  opacity: 0.4;
  cursor: default;
}

.wvring button.accent {
  color: #7ddc8e;
  border-color: #7ddc8e;
}

.wvring button.accent:hover:not(:disabled) {
  color: #000;
  background: #7ddc8e;
}

.wvring .btn-sm {
  letter-spacing: 0.15em;
  padding: 5px 12px;
  font-size: 10px;
}

.wvring .btn-ghost {
  color: rgba(255, 255, 255, 0.6);
  border-color: rgba(255, 255, 255, 0.3);
}

.wvring .btn-ghost:hover:not(:disabled) {
  color: #fff;
  background: none;
  border-color: #fff;
}

.wvring .notice {
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #7ddc8e;
  background: rgba(125, 220, 142, 0.08);
  border-left: 2px solid #7ddc8e;
  margin: 16px 0;
  padding: 12px 16px;
  font-size: 11px;
}

.wvring .hint {
  color: rgba(255, 255, 255, 0.4);
  letter-spacing: 0.05em;
  margin-top: 10px;
  font-size: 11px;
  line-height: 1.6;
}

.wvring .item {
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 12px 0;
  display: flex;
}

.wvring .item:last-child {
  border-bottom: none;
}

.wvring .item .info {
  flex: 1;
  min-width: 220px;
}

.wvring .item strong {
  color: #fff;
  font-size: 14px;
}

.wvring .meta {
  color: rgba(255, 255, 255, 0.5);
  margin-top: 3px;
  font-size: 12px;
}

.wvring .empty {
  color: rgba(255, 255, 255, 0.5);
  text-align: center;
  padding: 30px 20px;
  font-size: 13px;
}

.wvring .info-box {
  color: rgba(255, 255, 255, 0.75);
  background: rgba(125, 220, 142, 0.05);
  border-left: 2px solid rgba(125, 220, 142, 0.4);
  margin-bottom: 18px;
  padding: 14px 16px;
  font-size: 12px;
  line-height: 1.6;
}

.wvring .row-actions {
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-top: 18px;
  padding-top: 18px;
  display: flex;
}

.wvring .countdown {
  color: #7ddc8e;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  background: rgba(125, 220, 142, 0.15);
  padding: 3px 10px;
  font-size: 10px;
  display: inline-block;
}

.wvring .prossimi-group {
  margin-bottom: 16px;
}

.wvring .prossimi-head {
  color: rgba(255, 255, 255, 0.6);
  letter-spacing: 0.1em;
  text-transform: uppercase;
  margin-bottom: 8px;
  font-size: 12px;
}

.wvring .prossimi-row {
  color: rgba(255, 255, 255, 0.7);
  padding: 6px 0;
  font-size: 13px;
}
</style>
