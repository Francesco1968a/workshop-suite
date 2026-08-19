<script setup>
import { ref, reactive, nextTick, onMounted } from 'vue';

const pid = ref(0);
const loaded = ref(false);
const data = ref(null);
const editing = ref(false);
const form = reactive({ nome: '', cognome: '', email: '', telefono: '', citta: '' });
const noteInterne = ref('');
const savingAnagrafica = ref(false);
const savingNote = ref(false);
const toast = ref('');
const toastType = ref('success');

function apiUrl(path) {
  return window.WS_CONFIG.restUrl + path;
}

async function apiGet(path) {
  const res = await fetch(apiUrl(path), { headers: { 'X-WP-Nonce': window.WS_CONFIG.nonce } });
  if (!res.ok) throw new Error('request failed');
  return res.json();
}

async function apiPost(path, body) {
  const res = await fetch(apiUrl(path), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.WS_CONFIG.nonce },
    body: JSON.stringify(body),
  });
  if (!res.ok) throw new Error('request failed');
  return res.json();
}

function showToast(text, type = 'success') {
  toast.value = text;
  toastType.value = type;
  setTimeout(() => {
    if (toast.value === text) toast.value = '';
  }, 3000);
}

async function load() {
  data.value = await apiGet('partecipante/' + pid.value);
  form.nome = data.value.nome;
  form.cognome = data.value.cognome;
  form.email = data.value.email;
  form.telefono = data.value.telefono;
  form.citta = data.value.citta;
  noteInterne.value = data.value.note_interne;
  loaded.value = true;
}

function startEdit() {
  editing.value = true;
}

function cancelEdit() {
  editing.value = false;
  form.nome = data.value.nome;
  form.cognome = data.value.cognome;
  form.email = data.value.email;
  form.telefono = data.value.telefono;
  form.citta = data.value.citta;
}

async function saveAnagrafica() {
  savingAnagrafica.value = true;
  try {
    data.value = await apiPost('partecipante/' + pid.value + '/anagrafica', { ...form });
    editing.value = false;
    showToast('Anagrafica aggiornata');
  } catch {
    showToast('Errore salvataggio anagrafica', 'error');
  } finally {
    savingAnagrafica.value = false;
  }
}

async function saveNote() {
  savingNote.value = true;
  try {
    const result = await apiPost('partecipante/' + pid.value + '/note', { note_interne: noteInterne.value });
    noteInterne.value = result.note_interne;
    showToast('Note salvate');
  } catch {
    showToast('Errore salvataggio note', 'error');
  } finally {
    savingNote.value = false;
  }
}

onMounted(async () => {
  const url = new URL(window.location.href);
  pid.value = parseInt(url.searchParams.get('pid') || '0', 10);
  if (!pid.value) return;
  await load();
  await nextTick();
  const el = document.getElementById('wvpx-scheda');
  if (el) setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150);
});
</script>

<template>
  <div v-if="pid && loaded" id="wvpx-scheda" class="wvpx">
    <div v-if="toast" class="wvpx-toast" :class="'wvpx-toast--' + toastType">{{ toast }}</div>

    <div class="head">
      <template v-if="editing">
        <div class="head-row" style="margin-bottom: 8px">
          <h2>Modifica anagrafica</h2>
        </div>
        <div class="wvpx-grid2">
          <div><label>Nome</label><input type="text" v-model="form.nome" /></div>
          <div><label>Cognome</label><input type="text" v-model="form.cognome" /></div>
          <div><label>Email</label><input type="text" v-model="form.email" /></div>
          <div><label>Telefono</label><input type="text" v-model="form.telefono" /></div>
          <div style="grid-column: 1 / -1"><label>Città</label><input type="text" v-model="form.citta" /></div>
        </div>
        <div class="wvpx-actions">
          <button class="wvpx-btn wvpx-btn--accent" :disabled="savingAnagrafica" @click="saveAnagrafica">💾 Salva</button>
          <button class="wvpx-btn wvpx-btn--ghost" @click="cancelEdit">Annulla</button>
        </div>
      </template>
      <template v-else>
        <div class="head-row">
          <h2>{{ (data.nome + ' ' + data.cognome).trim() }}</h2>
          <button class="wvpx-btn wvpx-btn--ghost" @click="startEdit">✏ Modifica anagrafica</button>
        </div>
        <div class="meta">
          <a v-if="data.email" :href="'mailto:' + data.email">{{ data.email }}</a>
          <template v-if="data.telefono">
            ·
            <a v-if="data.wa_link" :href="data.wa_link" target="_blank">{{ data.telefono }} (WhatsApp)</a>
            <template v-else>{{ data.telefono }}</template>
          </template>
          <template v-if="data.citta"> · {{ data.citta }}</template>
          <br v-if="data.stats.prima_iscr_fmt" />
          <template v-if="data.stats.prima_iscr_fmt">
            Cliente dal <strong>{{ data.stats.prima_iscr_fmt }}</strong>
            <template v-if="data.stats.giorni_ultimo !== null"> · Ultimo contatto <strong>{{ data.stats.giorni_ultimo }} giorni fa</strong></template>
          </template>
        </div>
      </template>
    </div>

    <template v-if="!editing">
      <div class="wvpx-stats">
        <div class="wvpx-stat"><div class="n">{{ data.stats.totali }}</div><div class="l">Iscrizioni</div></div>
        <div class="wvpx-stat wvpx-stat--accent"><div class="n">{{ data.stats.confermate }}</div><div class="l">Confermate</div></div>
        <div class="wvpx-stat"><div class="n">{{ data.stats.richieste }}</div><div class="l">Richieste</div></div>
        <div class="wvpx-stat"><div class="n">{{ data.stats.abbandonate }}</div><div class="l">Abbandonate</div></div>
        <div class="wvpx-stat"><div class="n">{{ data.stats.conv_pct }}%</div><div class="l">Conversione</div></div>
        <div class="wvpx-stat"><div class="n">{{ data.stats.mail_inviate }}</div><div class="l">Mail inviate</div></div>
      </div>

      <div class="wvpx-card">
        <h3>Iscrizioni</h3>
        <ul v-if="data.iscrizioni.length" class="wvpx-isc-list">
          <li v-for="(isc, i) in data.iscrizioni" :key="i" class="wvpx-isc-row">
            <a v-if="isc.evento_url" class="wvpx-isc-evento" :href="isc.evento_url">{{ isc.evento }}</a>
            <span v-else class="wvpx-isc-evento">{{ isc.evento }}</span>
            <span class="wvpx-badge" :class="'wvpx-badge--' + isc.stato">{{ isc.stato === 'confermato' ? 'Confermato' : 'Richiesta' }}</span>
            <span class="wvpx-badge" :class="'wvpx-badge--pag-' + isc.stato_pagamento">
              {{ isc.stato_pagamento === 'saldato' ? 'Saldato' : isc.stato_pagamento === 'acconto_pagato' ? 'Acconto pagato' : 'In attesa' }}
            </span>
            <span class="wvpx-isc-data">{{ isc.data_fmt }}</span>
          </li>
        </ul>
        <div v-else class="wvpx-empty">Nessuna iscrizione.</div>
      </div>

      <div class="wvpx-card">
        <h3>Note interne</h3>
        <textarea v-model="noteInterne" rows="5" placeholder="Appunti veloci su questo contatto…"></textarea>
        <div class="wvpx-actions">
          <button class="wvpx-btn" :disabled="savingNote" @click="saveNote">💾 Salva note</button>
        </div>
      </div>

      <div class="wvpx-card">
        <h3>Timeline</h3>
        <div v-if="!data.timeline.length" class="wvpx-empty">Nessun evento registrato.</div>
        <ul v-else class="wvpx-tl">
          <li v-for="(e, i) in data.timeline" :key="i">
            <span class="icon">{{ e.icon }}</span>
            <div class="titolo">{{ e.titolo }}</div>
            <div class="sub"><strong>{{ e.data_fmt }}</strong> · {{ e.rel_fmt }} · {{ e.evento }}</div>
            <div v-if="e.extra" class="extra">{{ e.extra }}</div>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>

<style scoped>
.wvpx {
  color: #2c3338;
  max-width: 900px;
  margin: 0 0 24px;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.wvpx-toast {
  color: #1d2327;
  background: #fff;
  border-left: 4px solid #008a20;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);
  margin: 0 0 16px;
  padding: 10px 16px;
  font-size: 13px;
}

.wvpx-toast--error {
  border-left-color: #d63638;
}

.head {
  background: #fff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
  padding: 20px;
  margin-bottom: 16px;
}

.head-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 10px;
}

.head-row h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #1d2327;
}

.meta {
  color: #50575e;
  font-size: 13px;
  line-height: 1.6;
}

.meta a {
  color: #2271b1;
  text-decoration: none;
}

.meta a:hover {
  text-decoration: underline;
}

.wvpx-grid2 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
  margin-bottom: 16px;
}

.wvpx-grid2 label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #1d2327;
  margin-bottom: 4px;
}

.wvpx-grid2 input {
  width: 100%;
  box-sizing: border-box;
  background: #f6f7f7;
  border: 1px solid #8c8f94;
  color: #2c3338;
  padding: 6px 10px;
  font-size: 13px;
  border-radius: 4px;
}

.wvpx-actions {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}

.wvpx-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f6f7f7;
  border: 1px solid #8c8f94;
  color: #2c3338;
  padding: 6px 14px;
  font-size: 13px;
  border-radius: 4px;
  cursor: pointer;
}

.wvpx-btn:hover {
  background: #f0f0f1;
}

.wvpx-btn--accent {
  background: #2271b1;
  border-color: #2271b1;
  color: #fff;
}

.wvpx-btn--accent:hover {
  background: #135e96;
}

.wvpx-btn--ghost {
  background: transparent;
}

.wvpx-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wvpx-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 16px;
}

.wvpx-stat {
  flex: 1;
  min-width: 100px;
  background: #fff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
  padding: 14px;
  text-align: center;
}

.wvpx-stat .n {
  display: block;
  font-size: 22px;
  font-weight: 700;
  color: #1d2327;
  margin-bottom: 4px;
}

.wvpx-stat .l {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #646970;
}

.wvpx-stat--accent .n {
  color: #2271b1;
}

.wvpx-card {
  background: #fff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
  padding: 20px;
  margin-bottom: 16px;
}

.wvpx-card textarea {
  width: 100%;
  min-height: 80px;
  background: #f6f7f7;
  border: 1px solid #8c8f94;
  color: #2c3338;
  padding: 8px 12px;
  font-size: 13px;
  border-radius: 4px;
  box-sizing: border-box;
  font-family: inherit;
  margin-bottom: 10px;
}

.wvpx-isc-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.wvpx-isc-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid #f0f0f1;
}

.wvpx-isc-row:last-child {
  border-bottom: none;
}

.wvpx-isc-evento {
  flex: 1;
  min-width: 150px;
  font-size: 13px;
  font-weight: 600;
  color: #1d2327;
}

a.wvpx-isc-evento {
  color: #2271b1;
  text-decoration: none;
}

a.wvpx-isc-evento:hover {
  text-decoration: underline;
}

.wvpx-isc-data {
  font-size: 12px;
  color: #646970;
}

.wvpx-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 3px 9px;
  border-radius: 3px;
  background: #f6f7f7;
  color: #646970;
}

.wvpx-badge--confermato {
  background: #edfaef;
  color: #008a20;
}

.wvpx-badge--richiesta {
  background: #fdf6ec;
  color: #b88230;
}

.wvpx-badge--pag-saldato {
  background: #edfaef;
  color: #008a20;
}

.wvpx-badge--pag-acconto_pagato {
  background: #fdf6ec;
  color: #b88230;
}

.wvpx-badge--pag-in_attesa {
  background: #fef0f0;
  color: #c9302c;
}

.wvpx-empty {
  color: #646970;
  font-size: 13px;
  text-align: center;
  padding: 20px 0;
}

.wvpx-tl {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.wvpx-tl > li {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  padding-bottom: 14px;
  border-bottom: 1px solid #f0f0f1;
}

.wvpx-tl > li:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.wvpx-tl .icon {
  font-size: 16px;
  line-height: 1.4;
}

.wvpx-tl .titolo {
  font-size: 13px;
  color: #1d2327;
  font-weight: 600;
}

.wvpx-tl .sub {
  font-size: 12px;
  color: #646970;
  margin-top: 2px;
}

.wvpx-tl .extra {
  font-size: 12px;
  color: #50575e;
  margin-top: 4px;
  white-space: pre-wrap;
}

/* Frontend dark theme — see WS_Shortcode_Base::render(). */
.ws-theme-dark .wvpx {
  color: #fff;
}

.ws-theme-dark .wvpx-toast {
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
  border-left-color: #7ddc8e;
}

.ws-theme-dark .wvpx-toast--error {
  border-left-color: #ff6608;
}

.ws-theme-dark .head {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow: none;
}

.ws-theme-dark .head-row h2 {
  color: #fff;
}

.ws-theme-dark .meta {
  color: rgba(255, 255, 255, 0.6);
}

.ws-theme-dark .meta a {
  color: #ff9a6f;
}

.ws-theme-dark .wvpx-grid2 label {
  color: #fff;
}

.ws-theme-dark .wvpx-grid2 input {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
}

.ws-theme-dark .wvpx-btn {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
}

.ws-theme-dark .wvpx-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}

.ws-theme-dark .wvpx-btn--accent {
  background: #ff6608;
  border-color: #ff6608;
  color: #fff;
}

.ws-theme-dark .wvpx-btn--accent:hover {
  background: #e05a00;
}

.ws-theme-dark .wvpx-stat {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow: none;
}

.ws-theme-dark .wvpx-stat .n {
  color: #fff;
}

.ws-theme-dark .wvpx-stat .l {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvpx-stat--accent .n {
  color: #ff6608;
}

.ws-theme-dark .wvpx-card {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow: none;
}

.ws-theme-dark .wvpx-card textarea {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
}

.ws-theme-dark .wvpx-isc-row {
  border-bottom-color: rgba(255, 255, 255, 0.08);
}

.ws-theme-dark .wvpx-isc-evento {
  color: #fff;
}

.ws-theme-dark a.wvpx-isc-evento {
  color: #ff9a6f;
}

.ws-theme-dark .wvpx-isc-data {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvpx-empty {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvpx-tl > li {
  border-bottom-color: rgba(255, 255, 255, 0.08);
}

.ws-theme-dark .wvpx-tl .titolo {
  color: #fff;
}

.ws-theme-dark .wvpx-tl .sub {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvpx-tl .extra {
  color: rgba(255, 255, 255, 0.6);
}
</style>
