<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import Ring from './Ring.vue';
import { t } from '../shared/i18n.js';

const emit = defineEmits(['open-evento']);

const loading = ref(true);
const data = ref(null);
const mostraTutti = ref(false);

const haTodo = computed(() => {
  if (!data.value) return false;
  const todo = data.value.todo;
  return todo.ai_coda + todo.inbox_check + todo.pagamenti_count > 0;
});

async function load() {
  loading.value = true;
  const url = new URL(window.WSMA_CONFIG.restUrl + 'riepilogo/cockpit');
  if (mostraTutti.value) url.searchParams.set('tutti', '1');
  const res = await fetch(url, { headers: { 'X-WP-Nonce': window.WSMA_CONFIG.nonce } });
  data.value = await res.json();
  loading.value = false;
}

watch(mostraTutti, load);

onMounted(() => {
  const url = new URL(window.location.href);
  mostraTutti.value = url.searchParams.get('wvr') === 'tutti';
  load();
});
</script>

<template>
  <div v-if="data">
    <h2>{{ t('cp_h2', 'Overview') }}</h2>
    <div class="wvr-cockpit">
      <aside class="wvr-sidebar">
        <div v-if="haTodo" class="wvr-section wvr-todo">
          <h3>⚠ {{ t('cp_da_fare', 'To do') }}</h3>
          <ul>
            <li v-if="data.todo.ai_coda > 0">
              <span class="ico">🤖</span>
              <span class="txt"><strong>{{ data.todo.ai_coda }}</strong> {{ t('cp_mail_ai_coda', 'AI emails queued') }}</span>
              <a :href="data.urls.ai">{{ t('cp_vai', 'go') }}</a>
            </li>
            <li v-if="data.todo.inbox_check > 0" class="info">
              <span class="ico">📬</span>
              <span class="txt"><strong>{{ data.todo.inbox_check }}</strong> {{ t('cp_mail_ai_inviate', 'AI emails sent (48h) — check inbox for replies') }}</span>
            </li>
            <li v-if="data.todo.pagamenti_count > 0">
              <span class="ico">💰</span>
              <span class="txt">
                <strong>{{ data.todo.pagamenti_count }}</strong>
                {{ data.todo.pagamenti_count > 1 ? t('cp_pagamenti_plural', 'payments to collect (event ≤30 days)') : t('cp_pagamenti_singular', 'payment to collect (event ≤30 days)') }}
              </span>
            </li>
          </ul>
        </div>

        <div class="wvr-section">
          <h3>{{ t('cp_stato_attuale', 'Current status') }}</h3>
          <div class="wvr-rings">
            <div class="wvr-ring-wrap">
              <Ring :pct="data.rings.pct_posti">
                <div class="v">{{ data.rings.pct_posti }}%</div>
                <div class="s">{{ data.rings.tot_confermati }}/{{ data.rings.tot_posti }}</div>
              </Ring>
              <div class="wvr-ring-label">{{ t('cp_posti', 'Seats') }}</div>
            </div>
            <a class="wvr-ring-wrap" :href="data.urls.checkpoint">
              <Ring :pct="data.rings.tot_richieste ? Math.min(100, data.rings.tot_richieste * 10) : 0">
                <div class="v">{{ data.rings.tot_richieste }}</div>
                <div class="s">{{ t('cp_attesa', 'pending') }}</div>
              </Ring>
              <div class="wvr-ring-label">{{ t('cp_richieste', 'Requests') }}</div>
            </a>
            <a class="wvr-ring-wrap" :href="data.urls.contatti">
              <Ring :pct="100">
                <div class="v">{{ data.rings.tot_partecipanti }}</div>
                <div class="s">{{ t('cp_tot', 'total') }}</div>
              </Ring>
              <div class="wvr-ring-label">{{ t('cp_anagrafica', 'Contacts') }}</div>
            </a>
          </div>
        </div>

        <div class="wvr-section">
          <h3>{{ t('cp_ultimi_30gg', 'Last 30 days') }}</h3>
          <div style="display: flex; justify-content: center; margin-bottom: 14px">
            <div class="wvr-ring-wrap" style="max-width: 140px">
              <Ring :pct="data.stats30.conv30" :size="120">
                <div class="v">{{ data.stats30.conv30 }}%</div>
                <div class="s">{{ t('cp_conv_abbr', 'conv.') }}</div>
              </Ring>
              <div class="wvr-ring-label">{{ t('cp_tasso_conversione', 'Conversion rate') }}</div>
            </div>
          </div>
          <div class="wvr-stat-list">
            <a class="wvr-stat-row" :href="data.urls.contatti">
              <span class="lbl">{{ t('cp_nuove_richieste', 'New requests') }}</span>
              <span class="val">{{ data.stats30.n_nuove }}</span>
            </a>
            <div class="wvr-stat-row">
              <span class="lbl">{{ t('cp_confermate', 'Confirmed') }}</span>
              <span class="val">{{ data.stats30.n_conf30 }}</span>
            </div>
            <a class="wvr-stat-row" :href="data.urls.ai">
              <span class="lbl">{{ t('cp_mail_ai_inviate_lbl', 'AI emails sent') }}</span>
              <span class="val">{{ data.stats30.mail_ai_30 }}</span>
            </a>
          </div>
        </div>

        <div v-if="data.pro_stats" class="wvr-section">
          <h3>📊 {{ t('cp_statistiche', 'Statistics') }} <span style="font-size: 10px; font-weight: 800; letter-spacing: 0.5px; background: #7c3aed; color: #fff; padding: 2px 7px; border-radius: 4px; vertical-align: middle;">PRO</span></h3>
          <div class="wvr-stat-list">
            <div class="wvr-stat-row">
              <span class="lbl">{{ t('cp_incassato_30gg', 'Revenue (30 days)') }}</span>
              <span class="val">{{ data.pro_stats.revenue30_label }}</span>
            </div>
            <div class="wvr-stat-row">
              <span class="lbl">{{ t('cp_incassato_totale', 'Revenue (total)') }}</span>
              <span class="val">{{ data.pro_stats.revenue_total_label }}</span>
            </div>
          </div>
          <div v-if="data.pro_stats.revenue_by_category?.length" class="wvr-stat-list" style="margin-top: 10px">
            <div v-for="c in data.pro_stats.revenue_by_category" :key="c.nome" class="wvr-stat-row">
              <span class="lbl">{{ c.nome }}</span>
              <span class="val">{{ c.label }}</span>
            </div>
          </div>
          <div v-if="data.pro_stats.marketing" class="wvr-stat-list" style="margin-top: 10px">
            <div class="wvr-stat-row">
              <span class="lbl">🎁 {{ t('cp_coupon_emessi_usati', 'Coupons issued / used') }}</span>
              <span class="val">{{ data.pro_stats.marketing.coupon_emessi }} / {{ data.pro_stats.marketing.coupon_usati }}</span>
            </div>
            <div class="wvr-stat-row">
              <span class="lbl">{{ t('cp_sconto_totale', 'Total discount granted') }}</span>
              <span class="val">{{ data.pro_stats.marketing.sconto_totale_label }}</span>
            </div>
          </div>
        </div>
      </aside>

      <main class="wvr-main">
        <div class="wvr-filtro">
          <a :class="{ on: !mostraTutti }" @click="mostraTutti = false">{{ t('cp_solo_in_programma', 'Upcoming only') }}</a>
          <a :class="{ on: mostraTutti }" @click="mostraTutti = true">{{ t('cp_tutti_anche_conclusi', 'All (including past)') }}</a>
        </div>

        <div v-if="data.eventi.length" class="wvr-grid">
          <a v-for="e in data.eventi" :key="e.id" class="wvr-card" @click="emit('open-evento', e.id)">
            <div class="wvr-card-foto" :style="e.foto ? { backgroundImage: `url('${e.foto}')` } : {}">
              <div class="wvr-card-overlay">
                <span class="wvr-card-cat">{{ e.cat_name }}</span>
                <span class="wvr-card-date">{{ e.periodo }}</span>
              </div>
              <span v-if="e.concluso" class="wvr-card-concluso">{{ t('cp_concluso', 'ended') }}</span>
            </div>
            <div class="wvr-card-body">
              <template v-if="e.sold_out">
                <span class="wvr-soldout">Sold Out</span>
                <template v-if="e.n_richieste > 0"> · <span class="wvr-richiesta-inline">{{ e.n_richieste }} {{ t('cp_rich_abbr', 'Req.') }}</span></template>
              </template>
              <template v-else>
                {{ t('cp_posti', 'Seats') }} <strong>{{ e.occupati }}/{{ e.totali }}</strong>
                <template v-if="e.n_richieste > 0"> · <span class="wvr-richiesta-inline">{{ e.n_richieste }} {{ t('cp_rich_abbr', 'Req.') }}</span></template>
              </template>
            </div>
          </a>
        </div>
        <div v-else class="wvr-empty">{{ t('cp_nessun_evento', 'No events.') }}</div>
      </main>
    </div>
  </div>
</template>
