<script setup>
import { ref, reactive, onMounted, watch } from 'vue';

const props = defineProps({
  sub: { type: String, default: 'categorie' },
});
const emit = defineEmits(['message']);

function apiUrl(path) {
  return window.WS_CONFIG.restUrl + path;
}

function headers(extra = {}) {
  return { 'X-WP-Nonce': window.WS_CONFIG.nonce, ...extra };
}

function navigate(params) {
  const url = new URL(window.location.href);
  url.searchParams.delete('edit_cat');
  for (const [k, v] of Object.entries(params)) {
    if (v) url.searchParams.set(k, v);
  }
  window.history.pushState({}, '', url);
  syncEditCat();
  loadCategorie();
}

// ───────────────────────── Categorie ─────────────────────────

const loadingCat = ref(true);
const categorie = ref([]);
const tipiMap = ref({});
const pagine = ref([]);
const placeholders = ref({});
const defaultOggettoConferma = ref('');
const defaultMailConferma = ref('');
const defaultOggettoT15 = ref('');
const defaultMailT15 = ref('');
const editingCategoria = ref(null);
const editCat = ref(0);
const savingCategoria = ref(false);

function emptyCatForm() {
  return { nome: '', url: '', foto: '', tipo: '', oggetto_conferma: '', mail_conferma: '', oggetto_t15: '', mail_t15: '', prezzo: 0, acconto: 0, citta: '', nazione: '', indirizzo: '' };
}
const catForm = reactive(emptyCatForm());

function syncEditCat() {
  const url = new URL(window.location.href);
  editCat.value = parseInt(url.searchParams.get('edit_cat') || '0', 10);
}

async function loadCategorie() {
  loadingCat.value = true;
  const url = new URL(apiUrl('admin/categorie-tab'));
  if (editCat.value) url.searchParams.set('edit_cat', editCat.value);
  const data = await (await fetch(url, { headers: headers() })).json();
  categorie.value = data.categorie;
  tipiMap.value = data.tipi;
  pagine.value = data.pagine || [];
  placeholders.value = data.placeholders;
  defaultOggettoConferma.value = data.default_oggetto_conferma;
  defaultMailConferma.value = data.default_mail_conferma;
  defaultOggettoT15.value = data.default_oggetto_t15;
  defaultMailT15.value = data.default_mail_t15;
  editingCategoria.value = data.editing_categoria;

  Object.assign(catForm, editingCategoria.value ? { ...editingCategoria.value } : emptyCatForm());
  loadingCat.value = false;
}

function openCatMedia() {
  if (window.wp && window.wp.media) {
    const frame = window.wp.media({ title: 'Seleziona foto categoria', button: { text: 'Usa questa foto' }, multiple: false });
    frame.on('select', () => {
      const att = frame.state().get('selection').first().toJSON();
      if (att && att.url) catForm.foto = att.url;
    });
    frame.open();
  }
}

function onCatFile(e) {
  const file = e.target.files && e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = (ev) => {
    catForm.foto = ev.target.result;
  };
  reader.readAsDataURL(file);
}

async function submitCategoria() {
  savingCategoria.value = true;
  try {
    const editing = !!editingCategoria.value;
    const res = await fetch(apiUrl(editing ? 'admin/categorie/' + editCat.value : 'admin/categorie'), {
      method: editing ? 'PUT' : 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify(catForm),
    });
    const data = await res.json();
    if (!res.ok) {
      emit('message', data.message || 'Errore.');
      return;
    }
    emit('message', data.msg);
    navigate({ vista: 'categorie' });
  } finally {
    savingCategoria.value = false;
  }
}

async function creaPaginaCategoria() {
  const defT = catForm.nome ? 'Workshop ' + catForm.nome : 'Nuovo Workshop';
  const titolo = window.prompt('Titolo della nuova pagina WordPress da creare:', defT);
  if (!titolo) return;
  if (!editCat.value) {
    window.alert('Salva prima la categoria, poi potrai generare automaticamente la pagina con lo shortcode collegato.');
    return;
  }
  savingCategoria.value = true;
  try {
    const res = await fetch(apiUrl('admin/crea-pagina-categoria'), {
      method: 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ title: titolo, categoria_id: editCat.value, add_shortcode: true }),
    });
    const data = await res.json();
    if (!res.ok) {
      emit('message', data.message || 'Errore.');
      return;
    }
    pagine.value.unshift({ id: data.id, title: data.title, url: data.url });
    catForm.url = data.url;
    emit('message', 'Pagina creata e collegata: ' + data.title);
  } catch {
    emit('message', 'Errore creazione pagina.');
  } finally {
    savingCategoria.value = false;
  }
}

async function eliminaCategoria(c) {
  if (!window.confirm('Eliminare la categoria?')) return;
  const data = await (await fetch(apiUrl('admin/categorie/' + c.id), { method: 'DELETE', headers: headers() })).json();
  emit('message', data.msg);
  if (data.deleted) loadCategorie();
}

// ───────────────────────── Tipi ─────────────────────────

const loadingTipi = ref(true);
const tipi = ref([]);
const savingTipi = ref(false);

async function loadTipi() {
  loadingTipi.value = true;
  const data = await (await fetch(apiUrl('admin/tipi-tab'), { headers: headers() })).json();
  tipi.value = data.tipi || [];
  loadingTipi.value = false;
}

function aggiungiTipo() {
  tipi.value.push('');
}

function rimuoviTipo(i) {
  tipi.value.splice(i, 1);
}

async function submitTipi() {
  savingTipi.value = true;
  try {
    const res = await fetch(apiUrl('admin/tipi'), {
      method: 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ tipi: tipi.value }),
    });
    const data = await res.json();
    if (!res.ok) {
      emit('message', data.message || 'Errore.');
      return;
    }
    emit('message', data.msg || 'Tipi salvati.');
    tipi.value = data.tipi || [];
  } finally {
    savingTipi.value = false;
  }
}

watch(
  () => props.sub,
  (sub) => {
    if (sub === 'tipologia' && !tipi.value.length && !loadingTipi.value) loadTipi();
  }
);

onMounted(() => {
  syncEditCat();
  loadCategorie();
  loadTipi();
});
</script>

<template>
  <div v-if="sub === 'categorie'" class="wv-dash">
    <template v-if="!loadingCat">
      <h3>{{ editingCategoria ? 'Modifica categoria' : 'Crea categoria' }}</h3>
      <form @submit.prevent="submitCategoria">
        <div class="wv-field"><label>Nome (es. NapoliVelata)</label><input type="text" v-model="catForm.nome" required /></div>

        <div class="wv-field">
          <label>Tipo</label>
          <select v-model="catForm.tipo">
            <option value="">— non specificato —</option>
            <option v-for="(label, key) in tipiMap" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>

        <div class="wv-field"><label>Città</label><input type="text" v-model="catForm.citta" placeholder="Napoli" /></div>
        <div class="wv-field"><label>Nazione</label><input type="text" v-model="catForm.nazione" placeholder="Italia" /></div>
        <div class="wv-field"><label>Indirizzo (opzionale)</label><input type="text" v-model="catForm.indirizzo" placeholder="Via/Piazza..." /></div>

        <div class="wv-field">
          <label>Pagina di presentazione (Seleziona pagina o inserisci URL)</label>
          <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 6px">
            <select style="flex: 1; min-width: 220px" @change="$event.target.value && (catForm.url = $event.target.value)">
              <option value="">— Scegli una pagina del sito —</option>
              <option v-for="p in pagine" :key="p.id" :value="p.url">{{ p.title }} ({{ p.url }})</option>
            </select>
            <input type="url" v-model="catForm.url" placeholder="https://..." style="flex: 1; min-width: 220px" />
            <button type="button" class="wv-btn wv-btn-sm wv-btn-ghost" style="white-space: nowrap" @click="creaPaginaCategoria">+ Crea Pagina</button>
          </div>
          <div class="hint">Puoi selezionare una pagina esistente, incollarla, oppure cliccare su [+ Crea Pagina] per crearla subito con lo shortcode della categoria già inserito.</div>
        </div>

        <div class="wv-field">
          <label>Foto Categoria (URL / Libreria Media / Upload locale)</label>
          <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 8px">
            <input type="text" v-model="catForm.foto" placeholder="URL immagine o carica..." style="flex: 1" />
            <button type="button" class="wv-btn wv-btn-sm" @click="openCatMedia">Media</button>
            <label class="wv-btn wv-btn-sm" style="cursor: pointer; display: inline-flex; align-items: center">
              Carica
              <input type="file" accept="image/*" style="display: none" @change="onCatFile" />
            </label>
          </div>
          <div v-if="catForm.foto" style="display: flex; align-items: center; gap: 12px; margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 4px; border: 1px solid rgba(255,255,255,0.1)">
            <img :src="catForm.foto" style="height: 65px; width: 100px; object-fit: cover; border-radius: 4px" />
            <button type="button" class="wv-btn wv-btn-sm wv-btn-del" @click="catForm.foto = ''">✕ Rimuovi foto</button>
          </div>
        </div>

        <div class="wv-field">
          <label>Oggetto mail di conferma</label>
          <input type="text" v-model="catForm.oggetto_conferma" :placeholder="defaultOggettoConferma" />
        </div>
        <div class="wv-field">
          <label>Testo mail di conferma</label>
          <textarea v-model="catForm.mail_conferma" rows="8" :placeholder="defaultMailConferma"></textarea>
          <div class="hint">
            Se lasci vuoto usa il testo di default qui sopra (mostrato come placeholder). Parte quando premi "Conferma" in Riepilogo, con allegato .ics — nessuna AI coinvolta. Segnaposto disponibili:
            <code v-for="(desc, ph) in placeholders" :key="ph" :title="desc">{{ ph }}</code>
          </div>
        </div>
        <div class="wv-field">
          <label>Oggetto promemoria T-15</label>
          <input type="text" v-model="catForm.oggetto_t15" :placeholder="defaultOggettoT15" />
        </div>
        <div class="wv-field">
          <label>Testo promemoria T-15</label>
          <textarea v-model="catForm.mail_t15" rows="8" :placeholder="defaultMailT15"></textarea>
          <div class="hint">Parte in automatico 15 giorni prima dell'evento ai Confermati — nessuna AI coinvolta. Stessi segnaposto di sopra.</div>
        </div>

        <div class="wv-field"><label>Prezzo (€)</label><input type="number" step="0.01" v-model.number="catForm.prezzo" /></div>
        <div class="wv-field"><label>Acconto (€)</label><input type="number" step="0.01" v-model.number="catForm.acconto" /></div>

        <div class="wv-form-actions">
          <button type="submit" :disabled="savingCategoria">{{ editingCategoria ? 'Salva modifiche' : 'Crea categoria' }}</button>
          <a v-if="editingCategoria" class="wv-btn wv-btn-ghost" @click="navigate({ vista: 'categorie' })">Annulla</a>
        </div>
      </form>

      <h3>Categorie esistenti</h3>
      <div class="wv-categorie-grid">
        <div v-for="c in categorie" :key="c.id" class="wv-row wv-catcard-grid">
          <div class="wv-catcard-foto" :style="c.foto ? { backgroundImage: `url('${c.foto}')` } : {}"></div>
          <span>
            <strong>{{ c.nome }}</strong>
            <template v-if="c.tipo"> · <span style="color: #ff6608">{{ tipiMap[c.tipo] || c.tipo }}</span></template>
            · slug: <code>{{ c.slug }}</code>
            <template v-if="c.url">
              ·
              <a :href="c.url" target="_blank" style="color: rgba(255,255,255,.7); text-decoration: none; border-bottom: 1px dotted rgba(255,255,255,.3)">pagina</a>
            </template>
            <template v-if="c.count"> · {{ c.count }} eventi</template>
            <div v-if="c.prossimo_evento" class="wv-catcard-prossimo">Prossimo: {{ c.prossimo_evento }}</div>
            <div v-else class="wv-catcard-prossimo wv-catcard-prossimo--none">Nessuna data in programma</div>
          </span>
          <span class="wv-actions">
            <a class="wv-btn wv-btn-sm wv-btn-ghost" @click="navigate({ vista: 'categorie', edit_cat: c.id })">Modifica</a>
            <button class="wv-btn-sm wv-btn-del" @click="eliminaCategoria(c)">Elimina</button>
          </span>
        </div>
      </div>
    </template>
  </div>

  <div v-else class="wv-dash">
    <template v-if="!loadingTipi">
      <h3>Gestisci Tipi di Evento</h3>
      <p style="color: #888; margin-bottom: 20px; font-size: 13px">Aggiungi, modifica o rimuovi i tipi di evento disponibili per le categorie.</p>
      <form @submit.prevent="submitTipi">
        <div v-for="(t, i) in tipi" :key="i" class="wv-field" style="display: flex; gap: 10px; align-items: center">
          <input type="text" v-model="tipi[i]" placeholder="Es. Workshop" required style="flex: 1" />
          <button type="button" class="wv-btn-sm wv-btn-del" @click="rimuoviTipo(i)">✕</button>
        </div>
        <div class="wv-form-actions" style="margin-top: 24px">
          <button type="button" class="wv-btn wv-btn-ghost" @click="aggiungiTipo">+ Aggiungi Tipo</button>
          <button type="submit" :disabled="savingTipi">Salva Tipi</button>
        </div>
      </form>
    </template>
  </div>
</template>
