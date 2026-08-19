<script setup>
import { ref, reactive, onMounted } from 'vue';

const emit = defineEmits(['message']);

const loading = ref(true);
const categorie = ref([]);
const eventi = ref([]);
const editingEvento = ref(null);
const editingIscrizione = ref(null);
const editEv = ref(0);
const editIsc = ref(0);
const savingEvento = ref(false);
const savingIscrizione = ref(false);
const toggling = ref(0);

const eventoForm = reactive({ categoria: 0, data_evento: '', data_fine: '', ora_inizio: '', ora_fine: '', posti_totali: 5 });
const iscrizioneForm = reactive({ evento: 0, stato: 'richiesta', stato_pagamento: 'in_attesa', num_persone: 1, anticipo: 0, saldo: 0, note: '' });

function apiUrl(path) {
  return window.WS_CONFIG.restUrl + path;
}

function headers(extra = {}) {
  return { 'X-WP-Nonce': window.WS_CONFIG.nonce, ...extra };
}

function baseUrl() {
  const url = new URL(window.location.href);
  url.searchParams.delete('edit_ev');
  url.searchParams.delete('edit_isc');
  return url;
}

function navigate(params) {
  const url = baseUrl();
  for (const [k, v] of Object.entries(params)) {
    if (v) url.searchParams.set(k, v);
  }
  window.history.pushState({}, '', url);
  syncFromUrl();
  load();
}

function syncFromUrl() {
  const url = new URL(window.location.href);
  editEv.value = parseInt(url.searchParams.get('edit_ev') || '0', 10);
  editIsc.value = parseInt(url.searchParams.get('edit_isc') || '0', 10);
}

async function load() {
  loading.value = true;
  const url = new URL(apiUrl('admin/eventi-tab'));
  if (editEv.value) url.searchParams.set('edit_ev', editEv.value);
  if (editIsc.value) url.searchParams.set('edit_isc', editIsc.value);
  const data = await (await fetch(url, { headers: headers() })).json();
  categorie.value = data.categorie;
  eventi.value = data.eventi;
  editingEvento.value = data.editing_evento;
  editingIscrizione.value = data.editing_iscrizione;

  if (editingEvento.value) {
    Object.assign(eventoForm, {
      categoria: editingEvento.value.categoria_id,
      data_evento: editingEvento.value.data_evento,
      data_fine: editingEvento.value.data_fine,
      ora_inizio: editingEvento.value.ora_inizio,
      ora_fine: editingEvento.value.ora_fine,
      posti_totali: editingEvento.value.posti_totali,
    });
  } else {
    Object.assign(eventoForm, { categoria: 0, data_evento: '', data_fine: '', ora_inizio: '', ora_fine: '', posti_totali: 5 });
  }

  if (editingIscrizione.value) {
    Object.assign(iscrizioneForm, {
      evento: editingIscrizione.value.curr_evento_id,
      stato: editingIscrizione.value.stato,
      stato_pagamento: editingIscrizione.value.stato_pagamento,
      num_persone: editingIscrizione.value.num_persone,
      anticipo: editingIscrizione.value.anticipo,
      saldo: editingIscrizione.value.saldo,
      note: editingIscrizione.value.note,
    });
  }

  loading.value = false;
}

async function submitEvento() {
  savingEvento.value = true;
  try {
    const editing = !!editingEvento.value;
    const res = await fetch(apiUrl(editing ? 'admin/eventi/' + editEv.value : 'admin/eventi'), {
      method: editing ? 'PUT' : 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(eventoForm),
    });
    const data = await res.json();
    if (!res.ok) {
      emit('message', data.message || 'Errore.');
      return;
    }
    emit('message', data.msg);
    navigate({ vista: 'eventi' });
  } finally {
    savingEvento.value = false;
  }
}

async function toggleFrontend(ev) {
  toggling.value = ev.id;
  try {
    const data = await (await fetch(apiUrl('admin/eventi/' + ev.id + '/toggle-frontend'), { method: 'POST', headers: headers() })).json();
    ev.nascosto = data.nascosto;
    emit('message', data.msg);
  } finally {
    toggling.value = 0;
  }
}

async function eliminaEvento(ev) {
  if (!window.confirm("Eliminare l'evento e tutte le iscrizioni?")) return;
  const data = await (await fetch(apiUrl('admin/eventi/' + ev.id), { method: 'DELETE', headers: headers() })).json();
  emit('message', data.msg);
  load();
}

async function submitIscrizione() {
  savingIscrizione.value = true;
  try {
    const data = await (
      await fetch(apiUrl('admin/iscrizioni/' + editIsc.value), {
        method: 'PUT',
        headers: headers({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(iscrizioneForm),
      })
    ).json();
    emit('message', data.msg);
    navigate({ vista: 'eventi' });
  } finally {
    savingIscrizione.value = false;
  }
}

onMounted(() => {
  syncFromUrl();
  load();
});
</script>

<template>
  <div v-if="!loading">
    <template v-if="!editIsc">
      <h3>{{ editingEvento ? 'Modifica evento' : 'Crea evento' }}</h3>
      <form @submit.prevent="submitEvento">
        <div class="wv-field">
          <label>Categoria</label>
          <select v-model.number="eventoForm.categoria" required>
            <option :value="0">— scegli —</option>
            <option v-for="c in categorie" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="ws-field-row-2">
          <div class="wv-field"><label>Data inizio</label><input type="date" v-model="eventoForm.data_evento" required /></div>
          <div class="wv-field"><label>Data fine (vuoto se 1 giorno)</label><input type="date" v-model="eventoForm.data_fine" /></div>
        </div>
        <div class="ws-field-row-2">
          <div class="wv-field"><label>Ora inizio</label><input type="time" v-model="eventoForm.ora_inizio" /></div>
          <div class="wv-field"><label>Ora fine</label><input type="time" v-model="eventoForm.ora_fine" /></div>
        </div>
        <div class="wv-field">
          <label>Posti totali</label>
          <input type="number" min="1" v-model.number="eventoForm.posti_totali" required />
        </div>
        <div class="wv-form-actions">
          <button type="submit" :disabled="savingEvento">{{ editingEvento ? 'Salva modifiche' : 'Crea evento' }}</button>
          <a v-if="editingEvento" class="wv-btn wv-btn-ghost" @click="navigate({ vista: 'eventi' })">Annulla</a>
        </div>
      </form>
    </template>

    <h3>{{ editIsc ? 'Modifica iscrizione' : 'Eventi in programma' }}</h3>
    <div class="wv-eventi-grid">
      <div v-for="e in eventi" :key="e.id" class="wv-card wv-evcard-grid">
        <div class="wv-evcard-foto" :style="e.categoria_foto ? { backgroundImage: `url('${e.categoria_foto}')` } : {}"></div>
        <div class="wv-evento-head">
          <div>
            <h4>{{ e.label }} <span v-if="e.nascosto" class="wv-badge-hidden">Non pubblicato</span></h4>
            <span style="color: #999; font-size: 13px">
              <strong v-if="e.sold_out" style="color: #ff6b6b">SOLD OUT</strong>
              <template v-else>Posti confermati: <strong style="color: #fff">{{ e.occupati }}/{{ e.totali }}</strong></template>
              <template v-if="e.n_richieste">
                · <span style="color: #ff6608">{{ e.n_richieste }} Richiest{{ e.n_richieste > 1 ? 'e' : 'a' }}</span>
              </template>
            </span>
          </div>
          <div v-if="!editIsc" class="wv-actions">
            <a class="wv-btn wv-btn-sm wv-btn-ghost" @click="navigate({ vista: 'eventi', edit_ev: e.id })">Modifica</a>
            <button
              class="wv-btn-sm"
              :class="e.nascosto ? 'wv-btn-pub' : 'wv-btn-hide'"
              :disabled="toggling === e.id"
              @click="toggleFrontend(e)"
            >
              {{ e.nascosto ? '● Pubblica' : '○ Non pubblicare' }}
            </button>
            <button class="wv-btn-sm wv-btn-del" @click="eliminaEvento(e)">Elimina evento</button>
          </div>
        </div>

        <form
          v-if="editIsc && editingIscrizione"
          @submit.prevent="submitIscrizione"
          style="padding: 12px 0; border-bottom: 1px dashed rgba(255, 255, 255, 0.1)"
        >
          <strong>{{ editingIscrizione.nome }}</strong>
          <div class="wv-field" style="margin-top: 12px">
            <label>Sposta su evento</label>
            <select v-model.number="iscrizioneForm.evento">
              <option v-for="ev in editingIscrizione.eventi_stessa_categoria" :key="ev.id" :value="ev.id">
                {{ ev.label }} · {{ ev.sold_out ? 'SOLD OUT' : ev.disponibili + ' liberi' }}
              </option>
            </select>
            <div class="hint">Cambia solo se vuoi spostare su un'altra data.</div>
          </div>
          <div class="wv-field">
            <label>Stato</label>
            <select v-model="iscrizioneForm.stato">
              <option value="richiesta">Richiesta</option>
              <option value="confermato">Confermato</option>
            </select>
          </div>
          <div class="wv-field">
            <label>Stato pagamento</label>
            <select v-model="iscrizioneForm.stato_pagamento">
              <option value="in_attesa">In attesa</option>
              <option value="acconto_pagato">Acconto pagato</option>
              <option value="saldato">Saldato</option>
            </select>
          </div>
          <div class="wv-field">
            <label>Numero persone (gruppo)</label>
            <input type="number" min="1" v-model.number="iscrizioneForm.num_persone" />
            <div class="hint">Quanti posti occupa questa prenotazione.</div>
          </div>
          <div class="wv-field"><label>Anticipo (€)</label><input type="number" step="0.01" v-model.number="iscrizioneForm.anticipo" /></div>
          <div class="wv-field"><label>Saldo (€)</label><input type="number" step="0.01" v-model.number="iscrizioneForm.saldo" /></div>
          <div class="wv-field"><label>Note</label><textarea v-model="iscrizioneForm.note" rows="2"></textarea></div>
          <div class="wv-form-actions">
            <button type="submit" :disabled="savingIscrizione">Salva</button>
            <a class="wv-btn wv-btn-ghost" @click="navigate({ vista: 'eventi' })">Annulla</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
