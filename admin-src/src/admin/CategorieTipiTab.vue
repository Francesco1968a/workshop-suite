<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { t } from '../shared/i18n.js';

const props = defineProps({
  sub: { type: String, default: 'categorie' },
});
const emit = defineEmits(['message']);

function apiUrl(path) {
  return window.WSMA_CONFIG.restUrl + path;
}

function headers(extra = {}) {
  return { 'X-WP-Nonce': window.WSMA_CONFIG.nonce, ...extra };
}

// ───────────────────────── AI draft (optional — inert unless the PRO
// AI Assistant module + copy_categoria_corso flag + a connector are all
// configured; the REST call itself returns a clear message otherwise) ─────────────────────────
const aiNotes = ref('');
const aiLoading = ref(false);
const aiError = ref('');

async function generateAiDraft() {
  if (!catForm.nome || !aiNotes.value) {
    aiError.value = t('cat_err_nome_e_punto_chiave', 'Enter the category name and at least one key point.');
    return;
  }
  aiLoading.value = true;
  aiError.value = '';
  try {
    const res = await fetch(apiUrl('ai/generate-copy'), {
      method: 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ name: catForm.nome, notes: aiNotes.value }),
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
      aiError.value = data.message || t('cat_err_generazione', 'Error during generation.');
      return;
    }
    catForm.intro = data.intro || catForm.intro;
    catForm.program = data.program || catForm.program;
    catForm.requirements = data.requirements || catForm.requirements;
    catForm.important_notes = data.important_notes || catForm.important_notes;
  } catch (e) {
    aiError.value = t('cat_err_rete', 'Network error.');
  } finally {
    aiLoading.value = false;
  }
}

const translateLanguage = ref('Inglese');
const translateLoading = ref(false);
const translateError = ref('');

async function translateFields() {
  translateLoading.value = true;
  translateError.value = '';
  try {
    const res = await fetch(apiUrl('ai/translate-copy'), {
      method: 'POST',
      headers: headers({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({
        language: translateLanguage.value,
        intro: catForm.intro,
        program: catForm.program,
        requirements: catForm.requirements,
        important_notes: catForm.important_notes,
      }),
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
      translateError.value = data.message || t('cat_err_traduzione', 'Error during translation.');
      return;
    }
    catForm.intro = data.intro || catForm.intro;
    catForm.program = data.program || catForm.program;
    catForm.requirements = data.requirements || catForm.requirements;
    catForm.important_notes = data.important_notes || catForm.important_notes;
  } catch (e) {
    translateError.value = t('cat_err_rete', 'Network error.');
  } finally {
    translateLoading.value = false;
  }
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
  return { nome: '', url: '', foto: '', tipo: '', oggetto_conferma: '', mail_conferma: '', oggetto_t15: '', mail_t15: '', prezzo: 0, acconto: 0, citta: '', nazione: '', indirizzo: '', intro: '', program: '', requirements: '', important_notes: '', fb_share_enabled: false };
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
    const frame = window.wp.media({ title: t('cat_media_titolo', 'Select category photo'), button: { text: t('cat_media_usa_foto', 'Use this photo') }, multiple: false });
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

async function submitCategoria(keepOpen = false) {
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
      emit('message', data.message || t('cat_err_generico', 'Error.'));
      return;
    }
    emit('message', data.msg);
    // "Crea categoria" (not yet editing) always closes back to the list —
    // there's no existing edit_cat to stay on. Only an in-progress edit
    // can choose to stay open on the same categoria after saving.
    if (keepOpen && editing) {
      navigate({ vista: 'categorie', edit_cat: editCat.value });
    } else {
      navigate({ vista: 'categorie' });
    }
  } finally {
    savingCategoria.value = false;
  }
}

async function creaPaginaCategoria() {
  const defT = catForm.nome ? 'Workshop ' + catForm.nome : t('cat_nuovo_workshop', 'New Workshop');
  const titolo = window.prompt(t('cat_prompt_titolo_pagina', 'Title of the new WordPress page to create:'), defT);
  if (!titolo) return;
  if (!editCat.value) {
    window.alert(t('cat_alert_salva_prima', 'Save the category first, then you can automatically generate the page with the linked shortcode.'));
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
      emit('message', data.message || t('cat_err_generico', 'Error.'));
      return;
    }
    pagine.value.unshift({ id: data.id, title: data.title, url: data.url });
    catForm.url = data.url;
    emit('message', t('cat_msg_pagina_creata', 'Page created and linked: ') + data.title);
  } catch {
    emit('message', t('cat_err_creazione_pagina', 'Error creating the page.'));
  } finally {
    savingCategoria.value = false;
  }
}

async function eliminaCategoria(c) {
  if (!window.confirm(t('cat_confirm_elimina', 'Delete the category?'))) return;
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
      emit('message', data.message || t('cat_err_generico', 'Error.'));
      return;
    }
    emit('message', data.msg || t('cat_msg_tipi_salvati', 'Types saved.'));
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
      <h3>{{ editingCategoria ? t('cat_h3_modifica', 'Edit category') : t('cat_h3_crea', 'Create category') }}</h3>
      <form @submit.prevent="submitCategoria(true)">
        <div class="wv-field"><label>{{ t('cat_lbl_nome', 'Name (e.g. NapoliVelata)') }}</label><input type="text" v-model="catForm.nome" required /></div>

        <div class="wv-field">
          <label>{{ t('cat_lbl_tipo', 'Type') }}</label>
          <select v-model="catForm.tipo">
            <option value="">{{ t('cat_opt_non_specificato', '— not specified —') }}</option>
            <option v-for="(label, key) in tipiMap" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>

        <div class="wv-field"><label>{{ t('cat_lbl_citta', 'City') }} <code style="font-weight: normal; opacity: .6; font-size: 11px">[wsma_workshop_text field="city"]</code></label><input type="text" v-model="catForm.citta" :placeholder="t('cat_ph_napoli', 'Naples')" /></div>
        <div class="wv-field"><label>{{ t('cat_lbl_nazione', 'Country') }} <code style="font-weight: normal; opacity: .6; font-size: 11px">[wsma_workshop_text field="country"]</code></label><input type="text" v-model="catForm.nazione" :placeholder="t('cat_ph_italia', 'Italy')" /></div>
        <div class="wv-field"><label>{{ t('cat_lbl_indirizzo', 'Address (optional)') }} <code style="font-weight: normal; opacity: .6; font-size: 11px">[wsma_workshop_text field="address"]</code></label><input type="text" v-model="catForm.indirizzo" :placeholder="t('cat_ph_indirizzo', 'Street/Square...')" /></div>

        <div class="wv-field">
          <label>
            <input type="checkbox" v-model="catForm.fb_share_enabled" style="margin-right: 6px" />
            {{ t('cat_lbl_fb_share', 'Allow Facebook sharing for events in this category') }}
          </label>
        </div>

        <div class="wv-field">
          <label>{{ t('cat_lbl_pagina', 'Landing page (select a page or enter a URL)') }}</label>
          <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 6px">
            <select style="flex: 1; min-width: 220px" @change="$event.target.value && (catForm.url = $event.target.value)">
              <option value="">{{ t('cat_opt_scegli_pagina', '— Choose a page from your site —') }}</option>
              <option v-for="p in pagine" :key="p.id" :value="p.url">{{ p.title }} ({{ p.url }})</option>
            </select>
            <input type="url" v-model="catForm.url" placeholder="https://..." style="flex: 1; min-width: 220px" />
            <button type="button" class="wv-btn wv-btn-sm wv-btn-ghost" style="white-space: nowrap" @click="creaPaginaCategoria">+ {{ t('cat_btn_crea_pagina', 'Create Page') }}</button>
          </div>
          <div class="hint">{{ t('cat_hint_pagina', 'You can select an existing page, paste one in, or click [+ Create Page] to generate one right away with the category shortcode already inserted.') }}</div>
        </div>

        <div class="wv-field">
          <label>{{ t('cat_lbl_foto', 'Category Photo (URL / Media Library / local upload)') }} <code style="font-weight: normal; opacity: .6; font-size: 11px">[wsma_workshop_text field="photo"]</code></label>
          <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 8px">
            <input type="text" v-model="catForm.foto" :placeholder="t('cat_ph_foto_url', 'Image URL or upload...')" style="flex: 1" />
            <button type="button" class="wv-btn wv-btn-sm" @click="openCatMedia">{{ t('cat_btn_media', 'Media') }}</button>
            <label class="wv-btn wv-btn-sm" style="cursor: pointer; display: inline-flex; align-items: center">
              {{ t('cat_btn_carica', 'Upload') }}
              <input type="file" accept="image/*" style="display: none" @change="onCatFile" />
            </label>
          </div>
          <div v-if="catForm.foto" style="display: flex; align-items: center; gap: 12px; margin-top: 8px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 4px; border: 1px solid rgba(255,255,255,0.1)">
            <img :src="catForm.foto" style="height: 65px; width: 100px; object-fit: cover; border-radius: 4px" />
            <button type="button" class="wv-btn wv-btn-sm wv-btn-del" @click="catForm.foto = ''">✕ {{ t('cat_btn_rimuovi_foto', 'Remove photo') }}</button>
          </div>
          <div class="hint">{{ t('cat_hint_foto', 'Also used as the hero image at the top of the page generated by the [wsma_workshop_page] shortcode. A high-resolution square-ish image at least 1600–2000px wide is recommended for a good full-width result.') }}</div>
        </div>

        <div class="hint" style="margin: 0 0 4px">{{ t('cat_hint_campi_pagina', "These fields appear on the page generated with [wsma_workshop_page] — not on the Virtual Classroom, which stays purely functional. You can also paste any single block elsewhere using the shortcode shown in its label (add") }} <code>slug="{{ editingCategoria ? editingCategoria.slug : '...' }}"</code>).</div>

        <div style="background: rgba(255,102,8,0.06); border: 1px solid rgba(255,102,8,0.3); border-radius: 6px; padding: 14px 16px; margin: 4px 0 18px">
          <label style="display:block; font-weight:600; margin-bottom:6px;">✨ {{ t('cat_lbl_ai_draft', 'Generate a draft with AI (optional)') }}</label>
          <textarea v-model="aiNotes" rows="2" :placeholder="t('cat_ph_ai_notes', 'Key points, e.g.: 3 days, street photography, Naples, small group max 6 people...')" style="width:100%; margin-bottom:8px"></textarea>
          <button type="button" class="wv-btn wv-btn-sm" :disabled="aiLoading" @click="generateAiDraft">{{ aiLoading ? t('cat_btn_generazione_corso', 'Generating…') : '✨ ' + t('cat_btn_genera_bozza', 'Generate draft (Intro/Program/Requirements/Notes)') }}</button>
          <div v-if="aiError" style="color:#ff6b6b; font-size:13px; margin-top:6px">{{ aiError }}</div>
          <div class="hint" style="margin-top:6px">{{ t('cat_hint_ai_draft', 'Fills the fields below with a proposal — always review it before saving. Requires a configured AI connector and the AI Assistant feature enabled.') }}</div>
        </div>
        <div class="wv-field"><label>{{ t('cat_lbl_intro', 'Introduction') }} <code style="font-weight: normal; opacity: .6; font-size: 11px">[wsma_workshop_text field="intro"]</code></label><textarea v-model="catForm.intro" rows="3" :placeholder="t('cat_ph_intro', 'One or two introductory paragraphs about the workshop...')"></textarea></div>
        <div class="wv-field"><label>{{ t('cat_lbl_programma', 'Program') }} <code style="font-weight: normal; opacity: .6; font-size: 11px">[wsma_workshop_text field="program"]</code></label><textarea v-model="catForm.program" rows="6" :placeholder="t('cat_ph_programma', 'Day 1: ...\nDay 2: ...')"></textarea></div>
        <div class="wv-field"><label>{{ t('cat_lbl_requisiti', 'Requirements') }} <code style="font-weight: normal; opacity: .6; font-size: 11px">[wsma_workshop_text field="requirements"]</code></label><textarea v-model="catForm.requirements" rows="3" :placeholder="t('cat_ph_requisiti', 'E.g. DSLR or mirrorless camera, no experience required...')"></textarea></div>
        <div class="wv-field"><label>{{ t('cat_lbl_note', 'Important notes') }} <code style="font-weight: normal; opacity: .6; font-size: 11px">[wsma_workshop_text field="important_notes"]</code></label><textarea v-model="catForm.important_notes" rows="3" :placeholder="t('cat_ph_note', 'E.g. Meeting point, what to bring, weather conditions...')"></textarea></div>

        <div style="background: rgba(255,102,8,0.06); border: 1px solid rgba(255,102,8,0.3); border-radius: 6px; padding: 14px 16px; margin: 4px 0 18px">
          <label style="display:block; font-weight:600; margin-bottom:6px;">🌐 {{ t('cat_lbl_traduci_ai', 'Translate with AI (optional)') }}</label>
          <div style="display:flex; gap:8px; align-items:center;">
            <input type="text" v-model="translateLanguage" :placeholder="t('cat_ph_lingua', 'E.g. English, French...')" style="flex:1" />
            <button type="button" class="wv-btn wv-btn-sm" :disabled="translateLoading" @click="translateFields">{{ translateLoading ? t('cat_btn_traduzione_corso', 'Translating…') : t('cat_btn_traduci_campi', 'Translate the 4 fields above') }}</button>
          </div>
          <div v-if="translateError" style="color:#ff6b6b; font-size:13px; margin-top:6px">{{ translateError }}</div>
          <div class="hint" style="margin-top:6px">{{ t('cat_hint_traduci', 'Replaces Intro/Program/Requirements/Notes with the translation — save only after checking the result (or discard without saving).') }}</div>
        </div>

        <div class="wv-field">
          <label>{{ t('cat_lbl_oggetto_conferma', 'Confirmation email subject') }}</label>
          <input type="text" v-model="catForm.oggetto_conferma" :placeholder="defaultOggettoConferma" />
        </div>
        <div class="wv-field">
          <label>{{ t('cat_lbl_testo_conferma', 'Confirmation email body') }}</label>
          <textarea v-model="catForm.mail_conferma" rows="8" :placeholder="defaultMailConferma"></textarea>
          <div class="hint">
            {{ t('cat_hint_mail_conferma', 'If left empty, uses the default text above (shown as placeholder). Sent when you press "Confirm" in Overview, with an .ics attachment — no AI involved. Available placeholders:') }}
            <code v-for="(desc, ph) in placeholders" :key="ph" :title="desc">{{ ph }}</code>
          </div>
        </div>
        <div class="wv-field">
          <label>{{ t('cat_lbl_oggetto_t15', 'T-15 reminder subject') }}</label>
          <input type="text" v-model="catForm.oggetto_t15" :placeholder="defaultOggettoT15" />
        </div>
        <div class="wv-field">
          <label>{{ t('cat_lbl_testo_t15', 'T-15 reminder body') }}</label>
          <textarea v-model="catForm.mail_t15" rows="8" :placeholder="defaultMailT15"></textarea>
          <div class="hint">{{ t('cat_hint_t15', 'Sent automatically 15 days before the event to Confirmed participants — no AI involved. Same placeholders as above.') }}</div>
        </div>

        <div class="wv-field"><label>{{ t('cat_lbl_prezzo', 'Price (€)') }}</label><input type="number" step="0.01" v-model.number="catForm.prezzo" /></div>
        <div class="wv-field"><label>{{ t('cat_lbl_acconto', 'Deposit (€)') }}</label><input type="number" step="0.01" v-model.number="catForm.acconto" /></div>

        <div class="wv-form-actions">
          <button type="submit" :disabled="savingCategoria">{{ editingCategoria ? t('cat_btn_salva_modifiche', 'Save changes') : t('cat_h3_crea', 'Create category') }}</button>
          <button v-if="editingCategoria" type="button" class="wv-btn" :disabled="savingCategoria" @click="submitCategoria(false)">{{ t('cat_btn_salva_chiudi', 'Save and close') }}</button>
          <a v-if="editingCategoria" class="wv-btn wv-btn-ghost" @click="navigate({ vista: 'categorie' })">{{ t('cat_btn_annulla', 'Cancel') }}</a>
        </div>
      </form>

      <h3>{{ t('cat_h3_esistenti', 'Existing categories') }}</h3>
      <div class="wv-categorie-grid">
        <div v-for="c in categorie" :key="c.id" class="wv-row wv-catcard-grid">
          <div class="wv-catcard-foto" :style="c.foto ? { backgroundImage: `url('${c.foto}')` } : {}"></div>
          <span>
            <strong>{{ c.nome }}</strong>
            <template v-if="c.tipo"> · <span style="color: #ff6608">{{ tipiMap[c.tipo] || c.tipo }}</span></template>
            · slug: <code>{{ c.slug }}</code>
            <template v-if="c.url">
              ·
              <a :href="c.url" target="_blank" style="color: rgba(255,255,255,.7); text-decoration: none; border-bottom: 1px dotted rgba(255,255,255,.3)">{{ t('cat_link_pagina', 'page') }}</a>
            </template>
            <template v-if="c.count"> · {{ c.count }} {{ t('cat_word_eventi', 'events') }}</template>
            <div v-if="c.prossimo_evento" class="wv-catcard-prossimo">{{ t('cat_prossimo', 'Next:') }} {{ c.prossimo_evento }}</div>
            <div v-else class="wv-catcard-prossimo wv-catcard-prossimo--none">{{ t('cat_nessuna_data', 'No date scheduled') }}</div>
          </span>
          <span class="wv-actions">
            <a class="wv-btn wv-btn-sm wv-btn-ghost" @click="navigate({ vista: 'categorie', edit_cat: c.id })">{{ t('cat_btn_modifica', 'Edit') }}</a>
            <button class="wv-btn-sm wv-btn-del" @click="eliminaCategoria(c)">{{ t('cat_btn_elimina', 'Delete') }}</button>
          </span>
        </div>
      </div>
    </template>
  </div>

  <div v-else class="wv-dash">
    <template v-if="!loadingTipi">
      <h3>{{ t('cat_h3_gestisci_tipi', 'Manage Event Types') }}</h3>
      <p style="color: #888; margin-bottom: 20px; font-size: 13px">{{ t('cat_p_gestisci_tipi', 'Add, edit, or remove the event types available for categories.') }}</p>
      <form @submit.prevent="submitTipi">
        <div v-for="(tipoVal, i) in tipi" :key="i" class="wv-field" style="display: flex; gap: 10px; align-items: center">
          <input type="text" v-model="tipi[i]" :placeholder="t('cat_ph_es_workshop', 'e.g. Workshop')" required style="flex: 1" />
          <button type="button" class="wv-btn-sm wv-btn-del" @click="rimuoviTipo(i)">✕</button>
        </div>
        <div class="wv-form-actions" style="margin-top: 24px">
          <button type="button" class="wv-btn wv-btn-ghost" @click="aggiungiTipo">+ {{ t('cat_btn_aggiungi_tipo', 'Add Type') }}</button>
          <button type="submit" :disabled="savingTipi">{{ t('cat_btn_salva_tipi', 'Save Types') }}</button>
        </div>
      </form>
    </template>
  </div>
</template>
