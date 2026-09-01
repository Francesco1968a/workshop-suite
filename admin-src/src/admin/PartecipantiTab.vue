<script setup>
import { ref, reactive, computed, onMounted } from 'vue';

const emit = defineEmits(['message']);

const loading = ref(true);
const eventiInProgramma = ref([]);
const eventiConclusi = ref([]);
const partecipanti = ref([]);
const preEvento = ref(0);
const saving = ref(false);

const form = reactive({
  evento: 0,
  existing: 0,
  nome: '',
  cognome: '',
  email: '',
  telefono: '',
  citta: '',
  num_persone: 1,
  stato_iniziale: 'richiesta',
  anticipo: 0,
  saldo: 0,
  note: '',
});

const preEventoLabel = computed(
  () => [...eventiInProgramma.value, ...eventiConclusi.value].find((e) => e.id === preEvento.value)?.label || ''
);

function apiUrl(path) {
  return window.WSMA_CONFIG.restUrl + path;
}

function headers(extra = {}) {
  return { 'X-WP-Nonce': window.WSMA_CONFIG.nonce, ...extra };
}

async function load() {
  loading.value = true;
  const res = await fetch(apiUrl('admin/partecipanti-tab'), { headers: headers() });
  const data = await res.json();
  eventiInProgramma.value = data.eventi_in_programma;
  eventiConclusi.value = data.eventi_conclusi;
  partecipanti.value = data.partecipanti;
  if (preEvento.value) {
    form.evento = preEvento.value;
    if (!form.anticipo) {
      const ev = eventiInProgramma.value.find((e) => e.id === preEvento.value);
      if (ev && ev.acconto) form.anticipo = ev.acconto;
    }
  }
  loading.value = false;
}

function onEventoChange(id) {
  form.evento = id;
  if (!form.anticipo) {
    const ev = [...eventiInProgramma.value, ...eventiConclusi.value].find((e) => e.id === id);
    if (ev && ev.acconto) form.anticipo = ev.acconto;
  }
}

function onExistingChange() {
  const p = partecipanti.value.find((p) => p.id === form.existing);
  if (p) {
    form.nome = p.nome;
    form.cognome = p.cognome;
    form.email = p.email;
    form.telefono = p.telefono;
    form.citta = p.citta;
  }
}

function resetForm() {
  form.existing = 0;
  form.nome = '';
  form.cognome = '';
  form.email = '';
  form.telefono = '';
  form.citta = '';
  form.num_persone = 1;
  form.stato_iniziale = 'richiesta';
  form.anticipo = 0;
  form.saldo = 0;
  form.note = '';
}

async function submit() {
  saving.value = true;
  try {
    const res = await fetch(apiUrl('admin/partecipanti'), {
      method: 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(form),
    });
    const data = await res.json();
    if (!res.ok) {
      emit('message', data.message || 'Errore.');
      return;
    }
    emit('message', data.msg);
    resetForm();
    load();
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  const url = new URL(window.location.href);
  preEvento.value = parseInt(url.searchParams.get('pre_evento') || '0', 10);
  load();
});
</script>

<template>
  <div v-if="!loading" class="wv-dash">
    <h3>Aggiungi partecipante a evento</h3>
    <div v-if="preEvento && preEventoLabel" class="hint">
      Evento pre-selezionato: <strong>{{ preEventoLabel }}</strong>
    </div>

    <form @submit.prevent="submit">
      <div class="wv-field">
        <label>Evento</label>
        <select :value="form.evento" required @change="onEventoChange(Number($event.target.value))">
          <option :value="0">— scegli —</option>
          <optgroup label="In programma">
            <option v-for="e in eventiInProgramma" :key="e.id" :value="e.id">
              {{ e.label }} ({{ e.disponibili }} liberi){{ e.sold_out ? ' · solo richieste' : '' }}
            </option>
          </optgroup>
          <optgroup label="Conclusi">
            <option v-for="e in eventiConclusi" :key="e.id" :value="e.id">{{ e.label }} · concluso</option>
          </optgroup>
        </select>
      </div>

      <div class="wv-field">
        <label>Partecipante esistente (opzionale)</label>
        <select v-model.number="form.existing" @change="onExistingChange">
          <option :value="0">— nuovo partecipante —</option>
          <option v-for="p in partecipanti" :key="p.id" :value="p.id">{{ p.nome }} {{ p.cognome }} · {{ p.email }}</option>
        </select>
      </div>

      <div class="wv-field"><label>Nome</label><input type="text" v-model="form.nome" required /></div>
      <div class="wv-field"><label>Cognome</label><input type="text" v-model="form.cognome" required /></div>
      <div class="wv-field">
        <label>Email (se già esistente, riusa la scheda)</label>
        <input type="email" v-model="form.email" required />
      </div>
      <div class="wv-field"><label>Telefono</label><input type="text" v-model="form.telefono" /></div>
      <div class="wv-field"><label>Città di provenienza</label><input type="text" v-model="form.citta" /></div>
      <div class="wv-field">
        <label>Numero persone (gruppo)</label>
        <input type="number" min="1" v-model.number="form.num_persone" />
        <div class="hint">Se prenota per un gruppo, inserisci il totale (incluso lui). Default: 1.</div>
      </div>
      <div class="wv-field">
        <label>Stato iniziale</label>
        <select v-model="form.stato_iniziale">
          <option value="richiesta">Richiesta (default — niente mail)</option>
          <option value="confermato">Confermato (caricamento retroattivo)</option>
        </select>
      </div>
      <div class="wv-field"><label>Anticipo (€)</label><input type="number" step="0.01" v-model.number="form.anticipo" /></div>
      <div class="wv-field"><label>Saldo (€)</label><input type="number" step="0.01" v-model.number="form.saldo" /></div>
      <div class="wv-field"><label>Note</label><textarea v-model="form.note" rows="3"></textarea></div>

      <div class="wv-form-actions">
        <button type="submit" :disabled="saving">Crea iscrizione</button>
      </div>
    </form>
  </div>
</template>
