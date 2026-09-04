<script setup>
import { ref, watch, onMounted } from 'vue';
import Ring from './Ring.vue';
import { t } from '../shared/i18n.js';

const props = defineProps({
  eventoId: { type: Number, required: true },
});
const emit = defineEmits(['back', 'message', 'open-evento']);

const data = ref(null);
const loading = ref(true);
const confermando = ref(new Set());
const eliminando = ref(new Set());
const aggiornandoPagamento = ref(new Set());
const dialogAperto = ref(false);
const noteIscId = ref(0);
const noteText = ref('');
const salvandoNote = ref(false);

function apiUrl(path) {
  return window.WSMA_CONFIG.restUrl + path;
}

function headers(extra = {}) {
  return { 'X-WP-Nonce': window.WSMA_CONFIG.nonce, ...extra };
}

async function load() {
  loading.value = true;
  const res = await fetch(apiUrl('riepilogo/evento/' + props.eventoId), { headers: headers() });
  data.value = await res.json();
  loading.value = false;
}

async function conferma(isc) {
  confermando.value.add(isc.id);
  try {
    const res = await fetch(apiUrl('riepilogo/iscrizione/' + isc.id + '/conferma'), { method: 'POST', headers: headers() });
    if (!res.ok) {
      const err = await res.json();
      emit('message', err.message || t('ed_err_invio_mail', 'Could not send the email (missing address?).'));
      return;
    }
    isc.stato = 'confermato';
    emit('message', t('ed_conferma_inviata', 'Confirmation sent. Status → Confirmed.'));
  } finally {
    confermando.value.delete(isc.id);
  }
}

async function aggiornaStatoPagamento(isc, stato) {
  aggiornandoPagamento.value.add(isc.id);
  try {
    const res = await fetch(apiUrl('riepilogo/iscrizione/' + isc.id + '/stato-pagamento'), {
      method: 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ stato }),
    });
    if (!res.ok) {
      const err = await res.json();
      emit('message', err.message || t('ed_err_stato_pagamento', 'Could not update the payment status.'));
      return;
    }
    isc.stato_pagamento = stato;
    emit('message', t('ed_stato_pagamento_ok', 'Payment status updated.'));
  } finally {
    aggiornandoPagamento.value.delete(isc.id);
  }
}

function apriNote(isc) {
  noteIscId.value = isc.id;
  noteText.value = isc.note || '';
  dialogAperto.value = true;
}

async function salvaNote() {
  salvandoNote.value = true;
  try {
    const res = await fetch(apiUrl('riepilogo/iscrizione/' + noteIscId.value + '/nota'), {
      method: 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ note: noteText.value }),
    });
    const result = await res.json();
    const isc = data.value.iscrizioni.find((i) => i.id === noteIscId.value);
    if (isc) isc.note = result.note;
    dialogAperto.value = false;
    emit('message', t('ed_nota_aggiornata', 'Note updated.'));
  } finally {
    salvandoNote.value = false;
  }
}

async function elimina(isc) {
  if (!window.confirm(t('ed_confirm_elimina', 'Remove this participant from the event?'))) return;
  eliminando.value.add(isc.id);
  try {
    await fetch(apiUrl('riepilogo/iscrizione/' + isc.id), { method: 'DELETE', headers: headers() });
    data.value.iscrizioni = data.value.iscrizioni.filter((i) => i.id !== isc.id);
    emit('message', t('ed_partecipante_eliminato', 'Participant removed from the event.'));
  } finally {
    eliminando.value.delete(isc.id);
  }
}

const fbCopiato = ref(false);

async function preparaEventoFacebook() {
  try {
    await navigator.clipboard.writeText(data.value.fb_share_text || '');
  } catch (e) {
    // Clipboard API can fail (permessi negati, contesto non sicuro): l'apertura
    // della pagina Facebook resta comunque utile anche senza copia riuscita.
  }
  fbCopiato.value = true;
  window.open('https://www.facebook.com/events/create/', '_blank', 'noopener');
  setTimeout(() => { fbCopiato.value = false; }, 3000);
}

function euro(n) {
  return '€ ' + Number(n).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

watch(() => props.eventoId, load);
onMounted(load);
</script>

<template>
  <div v-if="data">
    <a class="wvr-back" @click="emit('back')">← {{ t('ed_torna_riepilogo', 'Back to overview') }}</a>
    <h2>{{ data.label }}</h2>

    <div class="wvr-evento-grid">
      <div class="wvr-card-hero">
        <div class="wvr-card-foto" :style="data.foto ? { backgroundImage: `url('${data.foto}')` } : {}">
          <div class="wvr-card-overlay">
            <span class="wvr-card-cat">{{ data.cat_name }}</span>
            <span class="wvr-card-date">{{ data.periodo }}</span>
          </div>
          <span v-if="data.concluso" class="wvr-card-concluso">{{ t('cp_concluso', 'ended') }}</span>
        </div>
      </div>

      <div class="wvr-evento-ring-col">
        <Ring :pct="data.ring.pct" :size="100">
          <div class="v">{{ data.ring.pct }}%</div>
          <div class="s">{{ data.ring.occupati }}/{{ data.ring.totali }}</div>
        </Ring>
        <div class="lbl">{{ t('ed_prenotazione', 'Bookings') }}</div>
        <div class="big">{{ data.ring.occupati }} <span>{{ t('ed_su', 'of') }} {{ data.ring.totali }}</span></div>
        <div class="sub">
          {{ data.n_iscritti }} {{ t('ed_iscritti', 'registered') }}
          <template v-if="data.giorni_a_evento !== null && data.giorni_a_evento > 0"> · {{ t('ed_tra', 'in') }} {{ data.giorni_a_evento }} {{ t('ed_gg_abbr', 'days') }}</template>
        </div>
      </div>

      <div class="wvr-evento-todo-col">
        <div v-if="data.todo.richieste + data.todo.pagamenti_mancanti + data.todo.welcome_da_inviare > 0" class="wvr-section wvr-todo">
          <h3>⚠ {{ t('cp_da_fare', 'To do') }}</h3>
          <ul>
            <li v-if="data.todo.richieste > 0">
              <span class="ico">🔀</span>
              <span class="txt"><strong>{{ data.todo.richieste }}</strong> {{ data.todo.richieste > 1 ? t('ev_richieste_plural', 'Requests') : t('ev_richiesta_singular', 'Request') }}</span>
              <a :href="data.urls.checkpoint">{{ t('cp_vai', 'go') }}</a>
            </li>
            <li v-if="data.todo.pagamenti_mancanti > 0">
              <span class="ico">💰</span>
              <span class="txt"><strong>{{ data.todo.pagamenti_mancanti }}</strong> {{ data.todo.pagamenti_mancanti > 1 ? t('cp_pagamenti_plural_bare', 'payments') : t('cp_pagamenti_singular_bare', 'payment') }}</span>
            </li>
            <li v-if="data.todo.welcome_da_inviare > 0">
              <span class="ico">👋</span>
              <span class="txt"><strong>{{ data.todo.welcome_da_inviare }}</strong> {{ t('ed_mail_a_presto', 'welcome emails') }}</span>
              <a :href="data.urls.apresto">{{ t('cp_vai', 'go') }}</a>
            </li>
          </ul>
        </div>
        <div v-else class="empty-todo">{{ t('ed_nessun_task', 'No open tasks') }}</div>

        <div class="wvr-evento-actions">
          <a v-if="data.cat_url" class="wvr-link" :href="data.cat_url" target="_blank" rel="noopener">{{ t('ed_vai_a_pagina', 'Go to page') }}</a>
          <a class="wvr-link" :href="data.edit_ev_link">{{ t('ev_btn_modifica', 'Edit') }}</a>
          <button v-if="data.fb_share_enabled" class="wvr-link" @click="preparaEventoFacebook">
            {{ fbCopiato ? '✓ ' + t('ed_copiato_apro_fb', 'Copied, opening Facebook…') : '📘 ' + t('ed_prepara_fb', 'Prepare Facebook event') }}
          </button>
        </div>
      </div>

      <aside class="wvr-evento-right">
        <h3>{{ t('ed_altri_eventi', 'Other events') }} · {{ data.cat_name }}</h3>
        <div v-if="data.altri_eventi.length" class="wvr-altri-grid">
          <a v-for="e in data.altri_eventi" :key="e.id" class="wvr-altri-chip" @click="emit('open-evento', e.id)">
            <span class="d">{{ e.periodo }}</span>
            <span class="st">{{ e.stato }}</span>
          </a>
        </div>
        <div v-else class="wvr-empty" style="padding: 14px 0; font-size: 11px">{{ t('ed_nessun_altro_evento', 'No other events.') }}</div>
      </aside>
    </div>

    <h3>{{ t('pl_h2', 'Participants') }}</h3>
    <template v-if="data.iscrizioni.length">
      <div class="wvr-table-wrap">
        <table class="wvr-table">
          <thead>
            <tr>
              <th>{{ t('px_lbl_nome', 'First name') }}</th><th>Email</th><th>{{ t('px_lbl_citta', 'City') }}</th><th>{{ t('px_lbl_telefono', 'Phone') }}</th><th>{{ t('ed_persone', 'People') }}</th>
              <th>{{ t('ev_lbl_stato', 'Status') }}</th><th>{{ t('ev_lbl_stato_pagamento', 'Payment status') }}</th><th>{{ t('ev_lbl_anticipo', 'Deposit (€)') }}</th><th>{{ t('ev_lbl_saldo', 'Balance (€)') }}</th><th>{{ t('ed_azioni', 'Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="isc in data.iscrizioni" :key="isc.id">
              <td><strong style="color: #1d2327">{{ isc.nome }}</strong></td>
              <td>{{ isc.email }}</td>
              <td>{{ isc.citta }}</td>
              <td>{{ isc.telefono }}</td>
              <td>{{ isc.num_persone }}</td>
              <td>
                <span class="wvr-badge" :class="isc.stato === 'confermato' ? 'wvr-confermato' : 'wvr-richiesta'">
                  {{ isc.stato === 'confermato' ? t('ev_opt_confermato', 'Confirmed') : t('ev_richiesta_singular', 'Request') }}
                </span>
              </td>
              <td>
                <select
                  :value="isc.stato_pagamento"
                  :disabled="aggiornandoPagamento.has(isc.id)"
                  @change="aggiornaStatoPagamento(isc, $event.target.value)"
                >
                  <option value="in_attesa">{{ t('ev_opt_in_attesa', 'Pending') }}</option>
                  <option value="acconto_pagato">{{ t('ev_opt_acconto_pagato', 'Deposit paid') }}</option>
                  <option value="saldato">{{ t('ev_opt_saldato', 'Paid in full') }}</option>
                </select>
              </td>
              <td>{{ euro(isc.anticipo) }}</td>
              <td>{{ euro(isc.saldo) }}</td>
              <td>
                <div class="wvr-actions-cell">
                  <button v-if="isc.stato !== 'confermato'" class="wvr-link" :disabled="confermando.has(isc.id)" @click="conferma(isc)">{{ t('msg_btn_conferma', 'Confirm') }}</button>
                  <button class="wvr-link" @click="apriNote(isc)">{{ t('ed_note', 'Notes') }}<template v-if="isc.note"> •</template></button>
                  <a class="wvr-link" :href="isc.edit_link">{{ t('ev_btn_modifica', 'Edit') }}</a>
                  <button class="wvr-link wvr-link-del" :disabled="eliminando.has(isc.id)" @click="elimina(isc)">{{ t('ev_btn_elimina', 'Delete') }}</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div style="text-align: center; margin-top: 24px">
        <a class="wvr-link" :href="data.aggiungi_link">+ {{ t('ed_aggiungi_partecipante', 'Add participant to this event') }}</a>
      </div>
    </template>
    <div v-else class="wvr-empty">{{ t('ed_nessun_iscritto', 'No one registered for this event yet.') }}</div>

    <div v-if="dialogAperto" class="wvr-dialog-mask" @click.self="dialogAperto = false">
      <div class="wvr-dialog">
        <h4>{{ t('ed_note_partecipante', 'Participant notes') }}</h4>
        <textarea v-model="noteText" maxlength="200" :placeholder="t('ed_ph_note', 'Up to 200 characters…')"></textarea>
        <div class="wvr-note-count">{{ noteText.length }}/200</div>
        <div class="wvr-dialog-actions">
          <button class="wvr-link" @click="dialogAperto = false">{{ t('ev_btn_annulla', 'Cancel') }}</button>
          <button class="wvr-link" :disabled="salvandoNote" @click="salvaNote">{{ t('ev_btn_salva', 'Save') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>
