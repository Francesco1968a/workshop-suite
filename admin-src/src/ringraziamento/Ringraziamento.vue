<script setup>
import { ref, onMounted } from 'vue';
import { t } from '../shared/i18n.js';

const loading = ref(true);
const autosend = ref(true);
const oggi = ref([]);
const prossimi = ref([]);
const nextCron = ref(null);
const busy = ref(new Set());
const msg = ref('');

function apiUrl(path) {
  return window.WSMA_CONFIG.restUrl + path;
}

function headers(extra = {}) {
  return { 'X-WP-Nonce': window.WSMA_CONFIG.nonce, ...extra };
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
  flash(t('rg_impostazioni_salvate', 'Settings saved.'));
}

async function inviaUna(item) {
  busy.value.add(item.isc_id);
  try {
    const res = await fetch(apiUrl('ringraziamento/iscrizione/' + item.isc_id + '/invia'), { method: 'POST', headers: headers() });
    const data = await res.json();
    if (!res.ok) {
      flash(data.message || t('msg_err_invio', 'Send failed.'));
      return;
    }
    flash(t('rg_mail_inviata_a', 'Email sent to') + ' ' + item.nome + '.');
    load();
  } finally {
    busy.value.delete(item.isc_id);
  }
}

async function saltaUna(item) {
  if (!window.confirm(t('rg_confirm_salta', 'Skip this registration?'))) return;
  busy.value.add(item.isc_id);
  try {
    await fetch(apiUrl('ringraziamento/iscrizione/' + item.isc_id + '/salta'), { method: 'POST', headers: headers() });
    flash(item.nome + ' ' + t('rg_saltato', 'skipped.'));
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
    flash(t('rg_inviate_n_mail', 'Sent') + ' ' + data.inviate + ' ' + t('rg_mail_ringraziamento', 'thank-you emails.'));
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
    <h2>{{ t('rg_h2', 'Email #4 · Thank You T+2') }}</h2>
    <div v-if="msg" class="notice">{{ msg }}</div>
    <div class="info-box">
      {{ t('rg_info_pre', 'Every morning at 10:00 the system sends') }} <strong style="color: #7ddc8e">{{ t('rg_mail4', 'Email #4') }}</strong>
      {{ t('rg_info_post', 'to Confirmed participants of events that ended exactly 2 days ago. Friendly tone: thank-you + shared Drive folder for photos.') }}
    </div>

    <div class="card">
      <h3>{{ t('rg_impostazioni', 'Settings') }}</h3>
      <label>{{ t('ev_lbl_stato', 'Status') }}</label>
      <div style="display: flex; gap: 8px">
        <button :class="{ accent: autosend }" @click="setAutosend(true)">{{ t('rg_attivo', 'ACTIVE — sends automatically') }}</button>
        <button @click="setAutosend(false)">{{ t('rg_sospeso', 'PAUSED') }}</button>
      </div>
      <div class="hint" style="margin-top: 14px">
        {{ t('rg_prossimo_invio', 'Next automatic send:') }} <strong style="color: #fff">{{ nextCron || t('rg_non_pianificato', '(not scheduled)') }}</strong>
      </div>
    </div>

    <div class="card">
      <h3>{{ t('rg_da_inviare_oggi', 'To send today (events that ended 2 days ago)') }}</h3>
      <template v-if="oggi.length">
        <p style="color: rgba(255, 255, 255, 0.7); font-size: 13px">
          {{ t('rg_sono', 'There are') }} <strong style="color: #7ddc8e">{{ oggi.length }}</strong> {{ t('rg_iscrizioni_appena_concluso', 'registrations from events that just ended:') }}
        </p>
        <div v-for="item in oggi" :key="item.isc_id" class="item">
          <div class="info">
            <strong>{{ item.nome }}</strong> · {{ item.evento }}
            <div class="meta">{{ item.email }}</div>
          </div>
          <button class="accent btn-sm" :disabled="busy.has(item.isc_id)" @click="inviaUna(item)">🙏 {{ t('rg_invia_ora', 'Send now') }}</button>
          <button class="btn-sm btn-ghost" :disabled="busy.has(item.isc_id)" @click="saltaUna(item)">⏭ {{ t('rg_salta', 'Skip') }}</button>
        </div>
        <div class="row-actions">
          <button :disabled="busy.has('all')" @click="inviaTutte">⚡ {{ t('rg_invia_tutte', 'Send all now') }}</button>
        </div>
      </template>
      <div v-else class="empty">{{ t('rg_nessun_evento_2gg', 'No events ended 2 days ago with confirmed participants.') }}</div>
    </div>

    <div v-if="prossimi.length" class="card">
      <h3>{{ t('rg_in_arrivo', 'Coming up in the next few days') }}</h3>
      <div v-for="g in prossimi" :key="g.giorni" class="prossimi-group">
        <div class="prossimi-head">
          {{ t('ed_tra', 'in') }} <span class="countdown">{{ g.giorni }} {{ t('rg_giorni', 'days') }}</span> → {{ g.iscrizioni.length }} {{ t('rg_iscrizione_i', 'registration(s)') }}
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

/* Light variant — this panel's base style above is dark by default (it
   never had a wp-admin-backend equivalent to match), so this is the one
   panel needing a .ws-theme-light override instead of .ws-theme-dark.
   See WS_Shortcode_Base::render(). */
.ws-theme-light .wvring {
  color: #1d2327;
}

.ws-theme-light .wvring h2 {
  color: #1d2327;
  border-bottom-color: #dcdcde;
}

.ws-theme-light .wvring h3 {
  color: #646970;
}

.ws-theme-light .wvring .card {
  border-color: #c3c4c7;
  background: #fff;
}

.ws-theme-light .wvring label {
  color: #646970;
}

.ws-theme-light .wvring button {
  color: #2c3338;
  border-color: #8c8f94;
}

.ws-theme-light .wvring button:hover:not(:disabled) {
  color: #fff;
  background: #2271b1;
  border-color: #2271b1;
}

.ws-theme-light .wvring button.accent {
  color: #008a20;
  border-color: #008a20;
}

.ws-theme-light .wvring button.accent:hover:not(:disabled) {
  color: #fff;
  background: #008a20;
}

.ws-theme-light .wvring .btn-ghost {
  color: #646970;
  border-color: #c3c4c7;
}

.ws-theme-light .wvring .notice {
  color: #008a20;
  background: rgba(0, 138, 32, 0.06);
  border-left-color: #008a20;
}

.ws-theme-light .wvring .hint {
  color: #646970;
}

.ws-theme-light .wvring .item {
  border-bottom-color: #f0f0f1;
}

.ws-theme-light .wvring .item strong {
  color: #1d2327;
}

.ws-theme-light .wvring .meta {
  color: #646970;
}

.ws-theme-light .wvring .empty {
  color: #646970;
}

.ws-theme-light .wvring .info-box {
  color: #2c3338;
  background: #f6f7f7;
  border-left-color: #2271b1;
}

.ws-theme-light .wvring .row-actions {
  border-top-color: #f0f0f1;
}

.ws-theme-light .wvring .countdown {
  color: #b32d2e;
  background: #fcf0f1;
}

.ws-theme-light .wvring .prossimi-head {
  color: #646970;
}

.ws-theme-light .wvring .prossimi-row {
  color: #2c3338;
}
</style>
