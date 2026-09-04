<script setup>
import { ref, reactive, onMounted } from 'vue';
import { t } from '../shared/i18n.js';

const props = defineProps({ filterModalita: { type: String, default: '' } });
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

const eventoForm = reactive({ categoria: 0, data_evento: '', data_fine: '', ora_inizio: '', ora_fine: '', posti_totali: 5, modalita: 'fisico', piattaforma_virtuale: 'jitsi', link_virtuale: '', wc_product_id: 0 });
const wcExtra = ref({ active: false, products: [], product_id: 0 });
const iscrizioneForm = reactive({ evento: 0, stato: 'richiesta', stato_pagamento: 'in_attesa', num_persone: 1, anticipo: 0, saldo: 0, note: '' });

function apiUrl(path) {
  return window.WSMA_CONFIG.restUrl + path;
}

function headers(extra = {}) {
  return { 'X-WP-Nonce': window.WSMA_CONFIG.nonce, ...extra };
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
  if (props.filterModalita) url.searchParams.set('modalita', props.filterModalita);
  const data = await (await fetch(url, { headers: headers() })).json();
  categorie.value = data.categorie;
  eventi.value = data.eventi;
  editingEvento.value = data.editing_evento;
  editingIscrizione.value = data.editing_iscrizione;
  wcExtra.value = data.wc_extra || { active: false, products: [], product_id: 0 };

  if (editingEvento.value) {
    Object.assign(eventoForm, {
      categoria: editingEvento.value.categoria_id,
      data_evento: editingEvento.value.data_evento,
      data_fine: editingEvento.value.data_fine,
      ora_inizio: editingEvento.value.ora_inizio,
      ora_fine: editingEvento.value.ora_fine,
      posti_totali: editingEvento.value.posti_totali,
      modalita: editingEvento.value.modalita || 'fisico',
      piattaforma_virtuale: editingEvento.value.piattaforma_virtuale || 'jitsi',
      link_virtuale: editingEvento.value.link_virtuale || '',
      wc_product_id: (editingEvento.value.wc_extra || wcExtra.value).product_id || 0,
    });
  } else {
    Object.assign(eventoForm, { categoria: 0, data_evento: '', data_fine: '', ora_inizio: '', ora_fine: '', posti_totali: 5, modalita: props.filterModalita || 'fisico', piattaforma_virtuale: 'jitsi', link_virtuale: '', wc_product_id: 0 });
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
      emit('message', data.message || t('ev_err_generico', 'Error.'));
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
  if (!window.confirm(t('ev_confirm_elimina', 'Delete the event and all its registrations?'))) return;
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
      <h3>{{ editingEvento ? t('ev_h3_modifica', 'Edit event') : t('ev_h3_crea', 'Create event') }}</h3>
      <form @submit.prevent="submitEvento">
        <div class="wv-field">
          <label>{{ t('ev_lbl_categoria', 'Category') }}</label>
          <select v-model.number="eventoForm.categoria" required>
            <option :value="0">{{ t('ev_opt_scegli', '— choose —') }}</option>
            <option v-for="c in categorie" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div class="ws-field-row-2">
          <div class="wv-field"><label>{{ t('ev_lbl_data_inizio', 'Start date') }}</label><input type="date" v-model="eventoForm.data_evento" required /></div>
          <div class="wv-field"><label>{{ t('ev_lbl_data_fine', 'End date (leave empty for a 1-day event)') }}</label><input type="date" v-model="eventoForm.data_fine" /></div>
        </div>
        <div class="ws-field-row-2">
          <div class="wv-field"><label>{{ t('ev_lbl_ora_inizio', 'Start time') }}</label><input type="time" v-model="eventoForm.ora_inizio" /></div>
          <div class="wv-field"><label>{{ t('ev_lbl_ora_fine', 'End time') }}</label><input type="time" v-model="eventoForm.ora_fine" /></div>
        </div>
        <div class="wv-field">
          <label>{{ t('ev_lbl_posti', 'Total seats') }}</label>
          <input type="number" min="1" v-model.number="eventoForm.posti_totali" required />
        </div>
        <div class="wv-field">
          <label>{{ t('ev_lbl_modalita', 'Mode') }}</label>
          <select v-model="eventoForm.modalita">
            <option value="fisico">{{ t('ev_opt_in_presenza', 'In person') }}</option>
            <option value="virtuale">{{ t('ev_opt_aula_virtuale', 'Virtual classroom') }}</option>
          </select>
        </div>
        <template v-if="eventoForm.modalita === 'virtuale'">
          <div class="wv-field">
            <label>{{ t('ev_lbl_piattaforma', 'Platform') }}</label>
            <select v-model="eventoForm.piattaforma_virtuale">
              <option value="jitsi">{{ t('ev_opt_jitsi', 'Jitsi Meet (built into the page)') }}</option>
              <option value="zoom">Zoom</option>
              <option value="meet">Google Meet</option>
              <option value="altro">{{ t('ev_opt_altro_link', 'Other (external link)') }}</option>
            </select>
          </div>
          <div class="wv-field">
            <label>{{ t('ev_lbl_link_virtuale', 'Virtual classroom link') }}</label>
            <input type="url" v-model="eventoForm.link_virtuale" placeholder="https://zoom.us/j/..." />
            <div v-if="eventoForm.piattaforma_virtuale === 'jitsi'" style="color: #999; font-size: 13px; margin-top: 4px;">
              {{ t('ev_hint_jitsi_auto', 'Leave empty: a Jitsi room will be generated automatically when created.') }}
            </div>
          </div>
        </template>
        <div class="wv-field" v-if="wcExtra.active">
          <label>{{ t('ev_lbl_wc_product', 'Linked WooCommerce product') }}</label>
          <select v-model.number="eventoForm.wc_product_id">
            <option :value="0">{{ t('ev_opt_wc_nessuno', '— none (no direct purchase) —') }}</option>
            <option v-for="p in wcExtra.products" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <div class="hint">{{ t('ev_hint_wc_product', 'If set, purchasing this product in the shop automatically confirms registration for this event.') }}</div>
          <div class="hint" v-if="eventoForm.wc_product_id" style="color:#ff6608;">{{ t('ev_hint_wc_stripe', "The automatic Stripe payment link (if active) won't be sent for this event: the purchase happens through this WooCommerce product instead.") }}</div>
        </div>
        <div class="wv-form-actions">
          <button type="submit" :disabled="savingEvento">{{ editingEvento ? t('ev_btn_salva_modifiche', 'Save changes') : t('ev_h3_crea', 'Create event') }}</button>
          <a v-if="editingEvento" class="wv-btn wv-btn-ghost" @click="navigate({ vista: 'eventi' })">{{ t('ev_btn_annulla', 'Cancel') }}</a>
        </div>
      </form>
    </template>

    <h3>{{ editIsc ? t('ev_h3_modifica_iscrizione', 'Edit registration') : (props.filterModalita === 'virtuale' ? t('ev_h3_aule_in_programma', 'Upcoming virtual classrooms') : t('ev_h3_eventi_in_programma', 'Upcoming events')) }}</h3>
    <div class="wv-eventi-grid">
      <div v-for="e in eventi" :key="e.id" class="wv-card wv-evcard-grid">
        <div class="wv-evcard-foto" :style="e.categoria_foto ? { backgroundImage: `url('${e.categoria_foto}')` } : {}"></div>
        <div class="wv-evento-head">
          <div>
            <h4>{{ e.label }} <span v-if="e.nascosto" class="wv-badge-hidden">{{ t('ev_badge_non_pubblicato', 'Unpublished') }}</span> <span v-if="e.modalita === 'virtuale'" style="color:#ff6608;border:1px solid #ff6608;border-radius:3px;font-size:11px;padding:1px 6px;font-weight:700;">💻 {{ t('ev_opt_aula_virtuale', 'Virtual classroom') }}</span></h4>
            <span style="color: #999; font-size: 13px">
              <strong v-if="e.sold_out" style="color: #ff6b6b">SOLD OUT</strong>
              <template v-else>{{ t('ev_posti_confermati', 'Confirmed seats:') }} <strong style="color: #fff">{{ e.occupati }}/{{ e.totali }}</strong></template>
              <template v-if="e.n_richieste">
                · <span style="color: #ff6608">{{ e.n_richieste }} {{ e.n_richieste > 1 ? t('ev_richieste_plural', 'Requests') : t('ev_richiesta_singular', 'Request') }}</span>
              </template>
            </span>
          </div>
          <div v-if="!editIsc" class="wv-actions">
            <a class="wv-btn wv-btn-sm wv-btn-ghost" @click="navigate({ vista: 'eventi', edit_ev: e.id })">{{ t('ev_btn_modifica', 'Edit') }}</a>
            <button
              class="wv-btn-sm"
              :class="e.nascosto ? 'wv-btn-pub' : 'wv-btn-hide'"
              :disabled="toggling === e.id"
              @click="toggleFrontend(e)"
            >
              {{ e.nascosto ? '● ' + t('ev_btn_pubblica', 'Publish') : '○ ' + t('ev_btn_non_pubblicare', "Don't publish") }}
            </button>
            <button class="wv-btn-sm wv-btn-del" @click="eliminaEvento(e)">{{ t('ev_btn_elimina_evento', 'Delete event') }}</button>
          </div>
        </div>

        <form
          v-if="editIsc && editingIscrizione"
          @submit.prevent="submitIscrizione"
          style="padding: 12px 0; border-bottom: 1px dashed rgba(255, 255, 255, 0.1)"
        >
          <strong>{{ editingIscrizione.nome }}</strong>
          <div class="wv-field" style="margin-top: 12px">
            <label>{{ t('ev_lbl_sposta_evento', 'Move to event') }}</label>
            <select v-model.number="iscrizioneForm.evento">
              <option v-for="ev in editingIscrizione.eventi_stessa_categoria" :key="ev.id" :value="ev.id">
                {{ ev.label }} · {{ ev.sold_out ? 'SOLD OUT' : ev.disponibili + ' ' + t('ev_liberi', 'available') }}
              </option>
            </select>
            <div class="hint">{{ t('ev_hint_sposta', 'Only change this if you want to move to a different date.') }}</div>
          </div>
          <div class="wv-field">
            <label>{{ t('ev_lbl_stato', 'Status') }}</label>
            <select v-model="iscrizioneForm.stato">
              <option value="richiesta">{{ t('ev_richiesta_singular', 'Request') }}</option>
              <option value="confermato">{{ t('ev_opt_confermato', 'Confirmed') }}</option>
            </select>
          </div>
          <div class="wv-field">
            <label>{{ t('ev_lbl_stato_pagamento', 'Payment status') }}</label>
            <select v-model="iscrizioneForm.stato_pagamento">
              <option value="in_attesa">{{ t('ev_opt_in_attesa', 'Pending') }}</option>
              <option value="acconto_pagato">{{ t('ev_opt_acconto_pagato', 'Deposit paid') }}</option>
              <option value="saldato">{{ t('ev_opt_saldato', 'Paid in full') }}</option>
            </select>
          </div>
          <div class="wv-field">
            <label>{{ t('ev_lbl_num_persone', 'Number of people (group)') }}</label>
            <input type="number" min="1" v-model.number="iscrizioneForm.num_persone" />
            <div class="hint">{{ t('ev_hint_num_persone', 'How many seats this booking takes up.') }}</div>
          </div>
          <div class="wv-field"><label>{{ t('ev_lbl_anticipo', 'Deposit (€)') }}</label><input type="number" step="0.01" v-model.number="iscrizioneForm.anticipo" /></div>
          <div class="wv-field"><label>{{ t('ev_lbl_saldo', 'Balance (€)') }}</label><input type="number" step="0.01" v-model.number="iscrizioneForm.saldo" /></div>
          <div class="wv-field"><label>{{ t('ev_lbl_note', 'Notes') }}</label><textarea v-model="iscrizioneForm.note" rows="2"></textarea></div>
          <div class="wv-form-actions">
            <button type="submit" :disabled="savingIscrizione">{{ t('ev_btn_salva', 'Save') }}</button>
            <a class="wv-btn wv-btn-ghost" @click="navigate({ vista: 'eventi' })">{{ t('ev_btn_annulla', 'Cancel') }}</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
