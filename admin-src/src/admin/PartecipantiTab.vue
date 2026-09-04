<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { t } from '../shared/i18n.js';

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
      emit('message', data.message || t('pt_err_generico', 'Error.'));
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
    <h3>{{ t('pt_h3_aggiungi', 'Add participant to event') }}</h3>
    <div v-if="preEvento && preEventoLabel" class="hint">
      {{ t('pt_evento_preselezionato', 'Pre-selected event:') }} <strong>{{ preEventoLabel }}</strong>
    </div>

    <form @submit.prevent="submit">
      <div class="wv-field">
        <label>{{ t('pt_lbl_evento', 'Event') }}</label>
        <select :value="form.evento" required @change="onEventoChange(Number($event.target.value))">
          <option :value="0">{{ t('pt_opt_scegli', '— choose —') }}</option>
          <optgroup :label="t('pt_optgroup_in_programma', 'Upcoming')">
            <option v-for="e in eventiInProgramma" :key="e.id" :value="e.id">
              {{ e.label }} ({{ e.disponibili }} {{ t('pt_liberi', 'available') }}){{ e.sold_out ? ' · ' + t('pt_solo_richieste', 'requests only') : '' }}
            </option>
          </optgroup>
          <optgroup :label="t('pt_optgroup_conclusi', 'Past')">
            <option v-for="e in eventiConclusi" :key="e.id" :value="e.id">{{ e.label }} · {{ t('pt_concluso', 'ended') }}</option>
          </optgroup>
        </select>
      </div>

      <div class="wv-field">
        <label>{{ t('pt_lbl_esistente', 'Existing participant (optional)') }}</label>
        <select v-model.number="form.existing" @change="onExistingChange">
          <option :value="0">{{ t('pt_opt_nuovo', '— new participant —') }}</option>
          <option v-for="p in partecipanti" :key="p.id" :value="p.id">{{ p.nome }} {{ p.cognome }} · {{ p.email }}</option>
        </select>
      </div>

      <div class="wv-field"><label>{{ t('pt_lbl_nome', 'First name') }}</label><input type="text" v-model="form.nome" required /></div>
      <div class="wv-field"><label>{{ t('pt_lbl_cognome', 'Last name') }}</label><input type="text" v-model="form.cognome" required /></div>
      <div class="wv-field">
        <label>{{ t('pt_lbl_email', 'Email (if already existing, reuses that record)') }}</label>
        <input type="email" v-model="form.email" required />
      </div>
      <div class="wv-field"><label>{{ t('pt_lbl_telefono', 'Phone') }}</label><input type="text" v-model="form.telefono" /></div>
      <div class="wv-field"><label>{{ t('pt_lbl_citta', "City they're coming from") }}</label><input type="text" v-model="form.citta" /></div>
      <div class="wv-field">
        <label>{{ t('pt_lbl_num_persone', 'Number of people (group)') }}</label>
        <input type="number" min="1" v-model.number="form.num_persone" />
        <div class="hint">{{ t('pt_hint_num_persone', 'If booking for a group, enter the total (including them). Default: 1.') }}</div>
      </div>
      <div class="wv-field">
        <label>{{ t('pt_lbl_stato_iniziale', 'Initial status') }}</label>
        <select v-model="form.stato_iniziale">
          <option value="richiesta">{{ t('pt_opt_richiesta', 'Requested (default — no email sent)') }}</option>
          <option value="confermato">{{ t('pt_opt_confermato', 'Confirmed (backfilling past data)') }}</option>
        </select>
      </div>
      <div class="wv-field"><label>{{ t('pt_lbl_anticipo', 'Deposit (€)') }}</label><input type="number" step="0.01" v-model.number="form.anticipo" /></div>
      <div class="wv-field"><label>{{ t('pt_lbl_saldo', 'Balance (€)') }}</label><input type="number" step="0.01" v-model.number="form.saldo" /></div>
      <div class="wv-field"><label>{{ t('pt_lbl_note', 'Notes') }}</label><textarea v-model="form.note" rows="3"></textarea></div>

      <div class="wv-form-actions">
        <button type="submit" :disabled="saving">{{ t('pt_btn_crea', 'Create registration') }}</button>
      </div>
    </form>
  </div>
</template>
