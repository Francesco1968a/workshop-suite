<script setup>
import { ref, computed, watch, onMounted } from 'vue';

const loading = ref(true);
const contatti = ref([]);
const selectedId = ref(0);
const sending = ref(new Set());
const msg = ref('');
const replyBody = ref('');
const replySubject = ref('');
const savingBozza = ref(false);
const sendingReply = ref(false);

const selected = computed(() => contatti.value.find((c) => c.isc_id === selectedId.value) || null);

watch(selected, (c) => {
  replyBody.value = c ? c.reply_draft : '';
  replySubject.value = c ? `Re: ${c.evento_label}` : '';
});

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
  const res = await fetch(apiUrl('admin/messaggi-tab'), { headers: headers() });
  const data = await res.json();
  contatti.value = data.contatti;
  if (!contatti.value.find((c) => c.isc_id === selectedId.value)) {
    selectedId.value = contatti.value.length ? contatti.value[0].isc_id : 0;
  }
  loading.value = false;
}

async function conferma() {
  const c = selected.value;
  if (!c) return;
  sending.value.add(c.isc_id);
  try {
    const res = await fetch(apiUrl('riepilogo/iscrizione/' + c.isc_id + '/conferma'), {
      method: 'POST',
      headers: headers(),
    });
    const data = await res.json();
    if (!res.ok) {
      flash(data.message || 'Impossibile confermare.');
      return;
    }
    flash(c.nome + ' confermato.');
    load();
  } finally {
    sending.value.delete(c.isc_id);
  }
}

async function inviaT15() {
  const c = selected.value;
  if (!c) return;
  sending.value.add(c.isc_id);
  try {
    const res = await fetch(apiUrl('admin/messaggi/t15/' + c.isc_id), { method: 'POST', headers: headers() });
    const data = await res.json();
    flash(data.msg);
    if (data.sent) load();
  } finally {
    sending.value.delete(c.isc_id);
  }
}

async function salvaBozza() {
  const c = selected.value;
  if (!c) return;
  savingBozza.value = true;
  try {
    const res = await fetch(apiUrl('admin/messaggi/' + c.isc_id + '/bozza'), {
      method: 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ body: replyBody.value }),
    });
    const data = await res.json();
    c.reply_draft = data.reply_draft;
    flash(data.msg);
  } finally {
    savingBozza.value = false;
  }
}

async function inviaRisposta() {
  const c = selected.value;
  if (!c || !replyBody.value.trim()) return;
  sendingReply.value = true;
  try {
    const res = await fetch(apiUrl('admin/messaggi/' + c.isc_id + '/invia-risposta'), {
      method: 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ body: replyBody.value, subject: replySubject.value }),
    });
    const data = await res.json();
    if (!res.ok) {
      flash(data.message || 'Invio fallito.');
      return;
    }
    c.reply_draft = '';
    c.thread = data.thread;
    replyBody.value = '';
    flash(data.msg);
  } finally {
    sendingReply.value = false;
  }
}

// Optional — inert unless the PRO AI Assistant's mail_reply_draft flag
// + a connector are both configured; the endpoint returns a clear
// message otherwise. Never sends by itself: only fills replyBody, the
// organizer still reviews and clicks Invia.
const aiDraftLoading = ref(false);
async function generateAiReplyDraft() {
  const c = selected.value;
  if (!c) return;
  aiDraftLoading.value = true;
  try {
    const res = await fetch(apiUrl('ai/generate-mail-reply'), {
      method: 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ isc_id: c.isc_id }),
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
      flash(data.message || 'Errore nella generazione.');
      return;
    }
    replyBody.value = data.reply;
  } finally {
    aiDraftLoading.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="wvm wv-dash">
    <h2>Messaggi</h2>
    <div v-if="msg" class="msg">{{ msg }}</div>

    <div v-if="!loading" class="wvm-layout">
      <div class="wvm-list">
        <div v-if="!contatti.length" class="wvm-empty">Nessun contatto su eventi in programma.</div>
        <div
          v-for="c in contatti"
          :key="c.isc_id"
          class="wvm-card"
          :class="{ active: c.isc_id === selectedId }"
          @click="selectedId = c.isc_id"
        >
          <div class="wvm-card-top">
            <span class="wvm-dot" :class="c.tipo"></span>
            <span class="wvm-card-nome">{{ c.nome }}</span>
          </div>
          <div class="wvm-card-meta">
            {{ c.evento_label }}
            <template v-if="c.tipo === 'conferma'"> · da confermare</template>
            <template v-else-if="c.tipo === 't15'"> · promemoria T-15</template>
            <template v-else> · confermato</template>
          </div>
          <div v-if="c.messaggio_originale" class="wvm-card-date">{{ c.messaggio_originale_data }}</div>
        </div>
      </div>

      <div class="wvm-detail">
        <template v-if="selected">
          <div class="wvm-field-row"><span class="l">Nome</span><span class="v">{{ selected.nome }}</span></div>
          <div class="wvm-field-row"><span class="l">Email</span><span class="v">{{ selected.email || '—' }}</span></div>
          <div v-if="selected.telefono" class="wvm-field-row"><span class="l">Telefono</span><span class="v">{{ selected.telefono }}</span></div>
          <div v-if="selected.citta" class="wvm-field-row"><span class="l">Città</span><span class="v">{{ selected.citta }}</span></div>
          <div class="wvm-field-row"><span class="l">Evento</span><span class="v">{{ selected.evento_label }}</span></div>

          <div class="wvm-section-label">
            Primo messaggio ricevuto
            <span v-if="selected.messaggio_originale" class="wvm-date-highlight">{{ selected.messaggio_originale_data }}</span>
          </div>
          <div class="wvm-msg-box" :class="{ empty: !selected.messaggio_originale }">
            {{ selected.messaggio_originale || 'Nessun messaggio registrato dal form.' }}
          </div>

          <template v-if="selected.note">
            <div class="wvm-section-label">Note</div>
            <div class="wvm-msg-box">{{ selected.note }}</div>
          </template>

          <template v-if="selected.thread.length">
            <div class="wvm-section-label">Storico risposte</div>
            <div v-for="(t, i) in selected.thread" :key="i" class="wvm-msg-box" style="margin-bottom: 8px">
              <div style="color: #888; font-size: 11px; margin-bottom: 6px">
                {{ t.direction === 'out' ? '→ Inviata' : '← Ricevuta' }} · {{ t.date }} · {{ t.subject }}
              </div>
              {{ t.body }}
            </div>
          </template>

          <div class="wvm-section-label">Rispondi</div>
          <div class="wv-field">
            <label>Oggetto</label>
            <input type="text" v-model="replySubject" />
          </div>
          <div class="wv-field">
            <textarea v-model="replyBody" rows="8" placeholder="Scrivi la risposta…"></textarea>
          </div>
          <div v-if="selected.thread && selected.thread.length" style="margin: -6px 0 12px">
            <button type="button" class="wv-btn wv-btn-ghost wv-btn-sm" :disabled="aiDraftLoading" @click="generateAiReplyDraft">
              {{ aiDraftLoading ? '✨ Generazione…' : '✨ Bozza con AI' }}
            </button>
          </div>
          <div class="wvm-detail-actions">
            <button class="wv-btn wv-btn-ghost" :disabled="savingBozza" @click="salvaBozza">Bozza</button>
            <button class="wv-btn" :disabled="sendingReply || !selected.email || !replyBody.trim()" @click="inviaRisposta">Invia</button>
            <button
              v-if="selected.tipo === 'conferma'"
              class="wv-btn wv-btn-ghost"
              :disabled="sending.has(selected.isc_id) || !selected.email"
              @click="conferma"
            >
              Conferma
            </button>
            <button
              v-else-if="selected.tipo === 't15'"
              class="wv-btn wv-btn-ghost"
              :disabled="sending.has(selected.isc_id) || !selected.email"
              @click="inviaT15"
            >
              Invia promemoria T-15
            </button>
          </div>
        </template>
        <div v-else class="wvm-detail-empty">Seleziona un contatto dalla lista.</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wvm {
  color: #2c3338;
  max-width: 100%;
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
}

.wvm h2 {
  color: #1d2327;
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #dcdcde;
  margin: 0 0 16px;
  padding-bottom: 8px;
}

.wvm-layout {
  display: grid;
  grid-template-columns: 340px 1fr;
  gap: 20px;
  align-items: start;
}

@media (max-width: 900px) {
  .wvm-layout {
    grid-template-columns: 1fr;
  }
}

.wvm-list {
  background: #fff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
  max-height: 75vh;
  overflow-y: auto;
}

.wvm-empty {
  text-align: center;
  color: #646970;
  padding: 24px;
  font-size: 13px;
  font-style: italic;
}

.wvm-card {
  cursor: pointer;
  border-bottom: 1px solid #f0f0f1;
  border-left: 4px solid transparent;
  padding: 12px 16px;
  transition: background 0.1s ease-in-out;
}

.wvm-card:last-child {
  border-bottom: none;
}

.wvm-card:hover {
  background: #f6f7f7;
}

.wvm-card.active {
  background: #f0f6fc;
  border-left-color: #2271b1;
}

.wvm-card-top {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.wvm-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.wvm-dot.conferma {
  background: #d63638;
}

.wvm-dot.t15 {
  background: #2271b1;
}

.wvm-dot.ok {
  background: #008a20;
}

.wvm-card-nome {
  color: #1d2327;
  font-size: 14px;
  font-weight: 600;
}

.wvm-card-meta {
  color: #646970;
  margin-left: 16px;
  font-size: 12px;
}

.wvm-card-date {
  color: #8c8f94;
  margin-top: 2px;
  margin-left: 16px;
  font-size: 11px;
}

.wvm-detail {
  background: #fff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
  padding: 20px;
}

.wvm-detail-empty {
  color: #646970;
  text-align: center;
  padding: 60px 20px;
  font-size: 13px;
  font-style: italic;
}

.wvm-field-row {
  border-bottom: 1px solid #f0f0f1;
  display: grid;
  grid-template-columns: 120px 1fr;
  gap: 8px;
  padding: 8px 0;
  font-size: 13px;
}

.wvm-field-row .l {
  color: #646970;
  font-weight: 600;
  font-size: 12px;
}

.wvm-field-row .v {
  color: #1d2327;
}

.wvm-field-row .v a {
  color: #2271b1;
  text-decoration: none;
}

.wvm-field-row .v a:hover {
  text-decoration: underline;
}

.wvm-section-label {
  color: #1d2327;
  font-weight: 600;
  font-size: 13px;
  margin: 16px 0 8px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.wvm-msg-box {
  background: #f6f7f7;
  border: 1px solid #dcdcde;
  border-radius: 4px;
  color: #2c3338;
  white-space: pre-wrap;
  padding: 12px 14px;
  font-size: 13px;
  line-height: 1.5;
}

.wvm-msg-box.empty {
  color: #646970;
  font-style: italic;
}

.wvm-detail-actions {
  display: flex;
  gap: 10px;
  margin-top: 16px;
}

/* Reply form: .wv-field/.wv-btn are shared conventions used elsewhere in
   the suite, but this bundle doesn't load admin.css — define them locally. */
.wv-dash .wv-field {
  margin-bottom: 16px;
}

.wv-dash .wv-field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #1d2327;
  margin-bottom: 6px;
}

.wv-dash .wv-field input,
.wv-dash .wv-field textarea {
  width: 100%;
  box-sizing: border-box;
  background: #fff;
  border: 1px solid #8c8f94;
  color: #2c3338;
  padding: 6px 10px;
  font-size: 13px;
  border-radius: 4px;
  font-family: inherit;
}

.wv-dash .wv-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #2271b1;
  border: 1px solid #2271b1;
  color: #fff;
  padding: 6px 14px;
  font-size: 13px;
  border-radius: 4px;
  cursor: pointer;
}

.wv-dash .wv-btn:hover {
  background: #135e96;
}

.wv-dash .wv-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.wv-dash .wv-btn-ghost {
  background: #f6f7f7;
  border-color: #dcdcde;
  color: #2c3338;
}

.wv-dash .wv-btn-ghost:hover {
  background: #f0f0f1;
}

/* Frontend dark theme — see WS_Shortcode_Base::render(). */
.ws-theme-dark .wvm {
  color: #fff;
}

.ws-theme-dark .wvm h2 {
  color: #fff;
  border-bottom-color: rgba(255, 255, 255, 0.12);
}

.ws-theme-dark .msg {
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
  border-left-color: #ff6608;
}

.ws-theme-dark .wvm-list,
.ws-theme-dark .wvm-detail {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow: none;
}

.ws-theme-dark .wvm-empty {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvm-card {
  border-bottom-color: rgba(255, 255, 255, 0.08);
}

.ws-theme-dark .wvm-card:hover {
  background: rgba(255, 255, 255, 0.05);
}

.ws-theme-dark .wvm-card.active {
  background: rgba(255, 102, 8, 0.1);
  border-left-color: #ff6608;
}

.ws-theme-dark .wvm-card-nome {
  color: #fff;
}

.ws-theme-dark .wvm-card-meta,
.ws-theme-dark .wvm-card-date {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvm-field-row {
  border-bottom-color: rgba(255, 255, 255, 0.08);
}

.ws-theme-dark .wvm-field-row .l {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvm-field-row .v {
  color: #fff;
}

.ws-theme-dark .wvm-field-row .v a {
  color: #ff9a6f;
}

.ws-theme-dark .wvm-section-label {
  color: #fff;
}

.ws-theme-dark .wvm-msg-box {
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.12);
  color: #fff;
}

.ws-theme-dark .wvm-msg-box.empty {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wv-dash .wv-field input,
.ws-theme-dark .wv-dash .wv-field textarea {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
}

.ws-theme-dark .wv-dash .wv-field label {
  color: #fff;
}

.ws-theme-dark .wv-dash .wv-btn-ghost {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
}

.ws-theme-dark .wv-dash .wv-btn-ghost:hover {
  background: rgba(255, 255, 255, 0.1);
}
</style>
