<script setup>
import { ref, onMounted } from 'vue';
import { showToast } from '../shared/toast.js';
import MonthCalendar from './MonthCalendar.vue';
import { t } from '../shared/i18n.js';

const url = ref('');
const webcal = ref('');
const eventi = ref([]);
const urlInput = ref(null);
const revoking = ref(false);

async function load() {
  const res = await fetch(window.WSMA_CONFIG.restUrl + 'calendario', {
    headers: { 'X-WP-Nonce': window.WSMA_CONFIG.nonce },
  });
  const data = await res.json();
  url.value = data.url;
  webcal.value = data.webcal;
  eventi.value = data.eventi || [];
}

async function revokeToken() {
  if (!window.confirm(t('cal_confirm_revoca', 'The current link will stop working: every already-connected calendar (Apple/Google/Zoho...) will need to be reconnected with the new URL. Continue?'))) return;
  revoking.value = true;
  try {
    const res = await fetch(window.WSMA_CONFIG.restUrl + 'calendario/revoca-token', {
      method: 'POST',
      headers: { 'X-WP-Nonce': window.WSMA_CONFIG.nonce },
    });
    const data = await res.json();
    url.value = data.url;
    webcal.value = data.webcal;
    showToast(t('cal_toast_token_rigenerato', 'Token regenerated — the old link no longer works'));
  } finally {
    revoking.value = false;
  }
}

async function copyUrl() {
  urlInput.value?.select();
  try {
    await navigator.clipboard.writeText(url.value);
  } catch {
    document.execCommand('copy');
  }
  showToast(t('cal_toast_url_copiato', 'URL copied to clipboard'));
}

onMounted(load);
</script>

<template>
  <div class="wvcal">
    <h2>{{ t('cal_h2', 'Workshop Calendar') }}</h2>

    <div class="wvcal-layout">
      <div class="wvcal-col-main">
        <MonthCalendar :eventi="eventi" />
      </div>

      <div class="wvcal-col-side">
        <div class="card">
          <h3>{{ t('cal_h3_url', 'Calendar URL') }}</h3>
          <div class="url-box">
            <input
              ref="urlInput"
              type="text"
              class="url"
              :value="url"
              readonly
              @click="($event) => $event.target.select()"
            />
            <button type="button" class="btn accent" @click="copyUrl">📋 {{ t('cal_btn_copia', 'Copy') }}</button>
            <a class="btn" :href="webcal">📅 {{ t('cal_btn_aggiungi', 'Add') }}</a>
          </div>
          <div class="hint">
            {{ t('cal_hint_aggiungi_pre', 'The') }} <strong>{{ t('cal_btn_aggiungi', 'Add') }}</strong> {{ t('cal_hint_aggiungi_post', 'button opens your system calendar app (on iPhone/Mac →') }}
            Apple Calendar, {{ t('cal_hint_on_windows', 'on Windows →') }} Outlook).<br />
            {{ t('cal_hint_google_zoho_pre', 'For Google Calendar/Zoho Calendar use') }} <strong>{{ t('cal_btn_copia', 'Copy') }}</strong> {{ t('cal_hint_google_zoho_post', '+ the instructions below.') }}
          </div>
          <div class="hint token-box">
            {{ t('cal_hint_token', "The URL contains a token tied to your account: anyone who knows it can see the name/phone/email of every event's registrants. If you think it's leaked, regenerate it — the old link stops working immediately.") }}
            <div style="margin-top: 8px">
              <button type="button" class="btn" :disabled="revoking" @click="revokeToken">
                🔄 {{ revoking ? t('cal_btn_rigenerazione_corso', 'Regenerating…') : t('cal_btn_rigenera_token', 'Regenerate token') }}
              </button>
            </div>
          </div>
        </div>

        <div class="card">
          <h3>📱 {{ t('cal_h3_apple', 'Apple Calendar (iPhone / Mac / iPad)') }}</h3>
          <ol>
            <li>{{ t('cal_apple_step1_pre', 'Click the') }} <strong>📅 {{ t('cal_btn_aggiungi', 'Add') }}</strong> {{ t('cal_apple_step1_post', 'button above from your Apple device.') }}</li>
            <li>{{ t('cal_apple_step2_pre', 'Calendar opens → click') }} <strong>{{ t('cal_apple_step2_iscriviti', 'Subscribe') }}</strong>.</li>
            <li>
              {{ t('cal_apple_step3_pre', 'Confirm. Set') }} <strong>{{ t('cal_apple_step3_setting', 'Auto-refresh: Every 15 minutes') }}</strong> {{ t('cal_apple_step3_post', '(or hourly).') }}
            </li>
          </ol>
          <div class="hint">
            {{ t('cal_apple_hint', 'Alternatively: Calendar → File → New Calendar Subscription → paste the URL.') }}
          </div>
        </div>

        <div class="card">
          <h3>📧 {{ t('cal_h3_google', 'Google Calendar (desktop)') }}</h3>
          <ol>
            <li>
              {{ t('cal_google_step1_pre', 'Open') }}
              <a href="https://calendar.google.com" target="_blank" class="link-accent"
                >calendar.google.com</a
              >
              {{ t('cal_google_step1_post', 'on your computer.') }}
            </li>
            <li>
              {{ t('cal_google_step2_pre', 'Left sidebar →') }} <strong>{{ t('cal_google_step2_other', 'Other calendars') }}</strong> {{ t('cal_google_step2_mid', '→ click the') }} <strong>+</strong> →
              <strong>{{ t('cal_google_step2_from_url', 'From URL') }}</strong>.
            </li>
            <li>{{ t('cal_google_step3_pre', 'Paste the URL →') }} <strong>{{ t('cal_google_step3_add', 'Add calendar') }}</strong>.</li>
          </ol>
          <div class="hint">
            ⚠ <strong>{{ t('cal_important', 'Important') }}</strong>: {{ t('cal_google_hint', "Google refreshes subscribed calendars every 8–24 hours (you can't change this). Real-time sync on Google requires an API integration — a separate project.") }}<br />
            {{ t('cal_google_hint2', 'Once added, you\'ll also see it on the Google Calendar app on phone and tablet.') }}
          </div>
        </div>

        <div class="card">
          <h3>📇 {{ t('cal_h3_zoho', 'Zoho Calendar') }}</h3>
          <ol>
            <li>
              {{ t('cal_zoho_step1', 'Open') }}
              <a href="https://calendar.zoho.com" target="_blank" class="link-accent"
                >calendar.zoho.com</a
              >.
            </li>
            <li>
              {{ t('cal_zoho_step2_pre', 'Click the') }} <strong>+</strong> {{ t('cal_zoho_step2_mid', 'icon next to "Other calendars" →') }} <strong>{{ t('cal_zoho_step2_add', 'Add by URL') }}</strong> {{ t('cal_zoho_step2_post', '(the exact label may vary).') }}
            </li>
            <li>{{ t('cal_zoho_step3', 'Paste the URL → confirm.') }}</li>
          </ol>
          <div class="hint">
            {{ t('cal_zoho_hint', "Here too, updates are periodic (not instant): Zoho rechecks the feed on its own schedule, which can't be changed from here.") }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wvcal {
  color: #2c3338;
  max-width: 1200px;
  font-family:
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    Roboto,
    sans-serif;
}

.wvcal-layout {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px;
  align-items: start;
}

@media (max-width: 900px) {
  .wvcal-layout {
    grid-template-columns: 1fr;
  }
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

.token-box {
  margin-top: 18px;
  padding-top: 14px;
  border-top: 1px solid #dcdcde;
}

.link-accent {
  color: #2271b1;
}

/* Frontend dark theme — see WS_Shortcode_Base::render(). */
.ws-theme-dark .wvcal {
  color: #fff;
}

.ws-theme-dark .wvcal h2 {
  color: #fff;
  border-bottom-color: rgba(255, 255, 255, 0.12);
}

.ws-theme-dark .card {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow: none;
}

.ws-theme-dark .card h3 {
  color: #fff;
}

.ws-theme-dark .card ol {
  color: #fff;
}

.ws-theme-dark .url-box .url {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
}

.ws-theme-dark .btn {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
}

.ws-theme-dark .btn:hover {
  background: rgba(255, 255, 255, 0.1);
}

.ws-theme-dark .btn.accent {
  background: #ff6608;
  border-color: #ff6608;
  color: #fff;
}

.ws-theme-dark .btn.accent:hover {
  background: #e05a00;
}

.ws-theme-dark .hint {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .token-box {
  border-top-color: rgba(255, 255, 255, 0.12);
}

.ws-theme-dark .link-accent {
  color: #ff9a6f;
}
</style>
