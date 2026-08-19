<script setup>
import { ref, watch, onMounted } from 'vue';
import Ring from './Ring.vue';

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
  return window.WS_CONFIG.restUrl + path;
}

function headers(extra = {}) {
  return { 'X-WP-Nonce': window.WS_CONFIG.nonce, ...extra };
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
      emit('message', err.message || 'Impossibile inviare la mail (email mancante?).');
      return;
    }
    isc.stato = 'confermato';
    emit('message', 'Conferma inviata. Stato → Confermato.');
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
      emit('message', err.message || 'Impossibile aggiornare lo stato pagamento.');
      return;
    }
    isc.stato_pagamento = stato;
    emit('message', 'Stato pagamento aggiornato.');
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
    emit('message', 'Nota aggiornata.');
  } finally {
    salvandoNote.value = false;
  }
}

async function elimina(isc) {
  if (!window.confirm("Eliminare questo partecipante dall'evento?")) return;
  eliminando.value.add(isc.id);
  try {
    await fetch(apiUrl('riepilogo/iscrizione/' + isc.id), { method: 'DELETE', headers: headers() });
    data.value.iscrizioni = data.value.iscrizioni.filter((i) => i.id !== isc.id);
    emit('message', "Partecipante eliminato dall'evento.");
  } finally {
    eliminando.value.delete(isc.id);
  }
}

function euro(n) {
  return '€ ' + Number(n).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

watch(() => props.eventoId, load);
onMounted(load);
</script>

<template>
  <div v-if="data">
    <a class="wvr-back" @click="emit('back')">← Torna al riepilogo</a>
    <h2>{{ data.label }}</h2>

    <div class="wvr-evento-grid">
      <div class="wvr-card-hero">
        <div class="wvr-card-foto" :style="data.foto ? { backgroundImage: `url('${data.foto}')` } : {}">
          <div class="wvr-card-overlay">
            <span class="wvr-card-cat">{{ data.cat_name }}</span>
            <span class="wvr-card-date">{{ data.periodo }}</span>
          </div>
          <span v-if="data.concluso" class="wvr-card-concluso">concluso</span>
        </div>
      </div>

      <div class="wvr-evento-ring-col">
        <Ring :pct="data.ring.pct" :size="100">
          <div class="v">{{ data.ring.pct }}%</div>
          <div class="s">{{ data.ring.occupati }}/{{ data.ring.totali }}</div>
        </Ring>
        <div class="lbl">Prenotazione</div>
        <div class="big">{{ data.ring.occupati }} <span>su {{ data.ring.totali }}</span></div>
        <div class="sub">
          {{ data.n_iscritti }} iscritti
          <template v-if="data.giorni_a_evento !== null && data.giorni_a_evento > 0"> · tra {{ data.giorni_a_evento }} gg</template>
        </div>
      </div>

      <div class="wvr-evento-todo-col">
        <div v-if="data.todo.richieste + data.todo.pagamenti_mancanti + data.todo.welcome_da_inviare > 0" class="wvr-section wvr-todo">
          <h3>⚠ Da fare</h3>
          <ul>
            <li v-if="data.todo.richieste > 0">
              <span class="ico">🔀</span>
              <span class="txt"><strong>{{ data.todo.richieste }}</strong> richiest{{ data.todo.richieste > 1 ? 'e' : 'a' }}</span>
              <a :href="data.urls.checkpoint">vai</a>
            </li>
            <li v-if="data.todo.pagamenti_mancanti > 0">
              <span class="ico">💰</span>
              <span class="txt"><strong>{{ data.todo.pagamenti_mancanti }}</strong> pagament{{ data.todo.pagamenti_mancanti > 1 ? 'i' : 'o' }}</span>
            </li>
            <li v-if="data.todo.welcome_da_inviare > 0">
              <span class="ico">👋</span>
              <span class="txt"><strong>{{ data.todo.welcome_da_inviare }}</strong> mail a presto</span>
              <a :href="data.urls.apresto">vai</a>
            </li>
          </ul>
        </div>
        <div v-else class="empty-todo">Nessun task aperto</div>

        <div class="wvr-evento-actions">
          <a v-if="data.cat_url" class="wvr-link" :href="data.cat_url" target="_blank" rel="noopener">Vai a pagina</a>
          <a class="wvr-link" :href="data.edit_ev_link">Modifica</a>
        </div>
      </div>

      <aside class="wvr-evento-right">
        <h3>Altri eventi · {{ data.cat_name }}</h3>
        <div v-if="data.altri_eventi.length" class="wvr-altri-grid">
          <a v-for="e in data.altri_eventi" :key="e.id" class="wvr-altri-chip" @click="emit('open-evento', e.id)">
            <span class="d">{{ e.periodo }}</span>
            <span class="st">{{ e.stato }}</span>
          </a>
        </div>
        <div v-else class="wvr-empty" style="padding: 14px 0; font-size: 11px">Nessun altro evento.</div>
      </aside>
    </div>

    <h3>Partecipanti</h3>
    <template v-if="data.iscrizioni.length">
      <div class="wvr-table-wrap">
        <table class="wvr-table">
          <thead>
            <tr>
              <th>Nome</th><th>Email</th><th>Città</th><th>Telefono</th><th>Persone</th>
              <th>Stato</th><th>Pagamento</th><th>Anticipo</th><th>Saldo</th><th>Azioni</th>
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
                  {{ isc.stato === 'confermato' ? 'Confermato' : 'Richiesta' }}
                </span>
              </td>
              <td>
                <select
                  :value="isc.stato_pagamento"
                  :disabled="aggiornandoPagamento.has(isc.id)"
                  @change="aggiornaStatoPagamento(isc, $event.target.value)"
                >
                  <option value="in_attesa">In attesa</option>
                  <option value="acconto_pagato">Acconto pagato</option>
                  <option value="saldato">Saldato</option>
                </select>
              </td>
              <td>{{ euro(isc.anticipo) }}</td>
              <td>{{ euro(isc.saldo) }}</td>
              <td>
                <div class="wvr-actions-cell">
                  <button v-if="isc.stato !== 'confermato'" class="wvr-link" :disabled="confermando.has(isc.id)" @click="conferma(isc)">Conferma</button>
                  <button class="wvr-link" @click="apriNote(isc)">Note<template v-if="isc.note"> •</template></button>
                  <a class="wvr-link" :href="isc.edit_link">Modifica</a>
                  <button class="wvr-link wvr-link-del" :disabled="eliminando.has(isc.id)" @click="elimina(isc)">Elimina</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div style="text-align: center; margin-top: 24px">
        <a class="wvr-link" :href="data.aggiungi_link">+ Aggiungi partecipante a questo evento</a>
      </div>
    </template>
    <div v-else class="wvr-empty">Nessun iscritto a questo evento.</div>

    <div v-if="dialogAperto" class="wvr-dialog-mask" @click.self="dialogAperto = false">
      <div class="wvr-dialog">
        <h4>Note partecipante</h4>
        <textarea v-model="noteText" maxlength="200" placeholder="Massimo 200 caratteri…"></textarea>
        <div class="wvr-note-count">{{ noteText.length }}/200</div>
        <div class="wvr-dialog-actions">
          <button class="wvr-link" @click="dialogAperto = false">Annulla</button>
          <button class="wvr-link" :disabled="salvandoNote" @click="salvaNote">Salva</button>
        </div>
      </div>
    </div>
  </div>
</template>
