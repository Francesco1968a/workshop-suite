<script setup>
import { ref, onMounted } from 'vue';
import { showToast } from '../shared/toast.js';

const url = ref('');
const webcal = ref('');
const urlInput = ref(null);

async function load() {
  const res = await fetch(window.WS_CONFIG.restUrl + 'calendario', {
    headers: { 'X-WP-Nonce': window.WS_CONFIG.nonce },
  });
  const data = await res.json();
  url.value = data.url;
  webcal.value = data.webcal;
}

async function copyUrl() {
  urlInput.value?.select();
  try {
    await navigator.clipboard.writeText(url.value);
  } catch {
    document.execCommand('copy');
  }
  showToast('URL copiato negli appunti');
}

onMounted(load);
</script>

<template>
  <div class="wvcal">
    <h2>Calendario Workshop · Sottoscrizione</h2>

    <div class="card">
      <h3>URL del calendario</h3>
      <div class="url-box">
        <input
          ref="urlInput"
          type="text"
          class="url"
          :value="url"
          readonly
          @click="($event) => $event.target.select()"
        />
        <button type="button" class="btn accent" @click="copyUrl">📋 Copia</button>
        <a class="btn" :href="webcal">📅 Aggiungi</a>
      </div>
      <div class="hint">
        Il bottone <strong>Aggiungi</strong> apre il tuo calendario di sistema (su iPhone/Mac →
        Apple Calendar, su Windows → Outlook).<br />
        Per Google Calendar usa <strong>Copia</strong> + istruzioni sotto.
      </div>
    </div>

    <div class="card">
      <h3>📱 Apple Calendar (iPhone / Mac / iPad)</h3>
      <ol>
        <li>Clicca il bottone <strong>📅 Aggiungi</strong> qui sopra dal dispositivo Apple.</li>
        <li>Si apre Calendario → click <strong>Iscriviti</strong>.</li>
        <li>
          Conferma. Imposta <strong>Aggiornamento automatico: Ogni 15 minuti</strong> (o orario).
        </li>
      </ol>
      <div class="hint">
        In alternativa: Calendario → File → Nuova sottoscrizione calendario → incolla l'URL.
      </div>
    </div>

    <div class="card">
      <h3>📧 Google Calendar (desktop)</h3>
      <ol>
        <li>
          Apri
          <a href="https://calendar.google.com" target="_blank" class="link-accent"
            >calendar.google.com</a
          >
          sul computer.
        </li>
        <li>
          Sidebar sinistra → <strong>Altri calendari</strong> → click sul <strong>+</strong> →
          <strong>Da URL</strong>.
        </li>
        <li>Incolla l'URL → <strong>Aggiungi calendario</strong>.</li>
      </ol>
      <div class="hint">
        ⚠ <strong>Importante</strong>: Google rinfresca i calendari sottoscritti ogni 8–24 ore (non
        puoi cambiarlo). Per realtime su Google ci vuole l'integrazione API → un altro
        snippet.<br />
        Una volta aggiunto, lo vedi anche sull'app Google Calendar di telefono e tablet.
      </div>
    </div>
  </div>
</template>

<style scoped>
.wvcal {
  color: #2c3338;
  max-width: 720px;
  font-family:
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    Roboto,
    sans-serif;
}

.wvcal h2 {
  color: #1d2327;
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #dcdcde;
  margin: 0 0 16px;
  padding-bottom: 8px;
}

.card {
  background: #fff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
  padding: 20px;
  margin-bottom: 20px;
}

.card h3 {
  margin-top: 0;
  font-size: 13px;
  font-weight: 600;
  color: #1d2327;
  text-transform: uppercase;
}

.card ol {
  margin: 0 0 0 20px;
  padding: 0;
  font-size: 13px;
  color: #2c3338;
  line-height: 1.6;
}

.card li {
  margin-bottom: 6px;
}

.url-box {
  display: flex;
  gap: 8px;
  margin: 12px 0;
  flex-wrap: wrap;
}

.url-box .url {
  background: #f6f7f7;
  border: 1px solid #8c8f94;
  color: #2c3338;
  padding: 6px 12px;
  font-size: 13px;
  border-radius: 4px;
  flex: 1;
  min-width: 200px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f6f7f7;
  border: 1px solid #8c8f94;
  color: #2c3338;
  padding: 6px 14px;
  font-size: 13px;
  border-radius: 4px;
  text-decoration: none;
  cursor: pointer;
  white-space: nowrap;
}

.btn:hover {
  background: #f0f0f1;
}

.btn.accent {
  background: #2271b1;
  border-color: #2271b1;
  color: #fff;
}

.btn.accent:hover {
  background: #135e96;
}

.hint {
  color: #50575e;
  font-size: 13px;
  line-height: 1.6;
  margin-top: 14px;
}

.link-accent {
  color: #2271b1;
}
</style>
