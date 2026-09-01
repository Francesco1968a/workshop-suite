<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import Ring from './Ring.vue';

const emit = defineEmits(['open-evento']);

const loading = ref(true);
const data = ref(null);
const mostraTutti = ref(false);

const haTodo = computed(() => {
  if (!data.value) return false;
  const t = data.value.todo;
  return t.ai_coda + t.inbox_check + t.pagamenti_count > 0;
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
    <h2>Riepilogo</h2>
    <div class="wvr-cockpit">
      <aside class="wvr-sidebar">
        <div v-if="haTodo" class="wvr-section wvr-todo">
          <h3>⚠ Da fare</h3>
          <ul>
            <li v-if="data.todo.ai_coda > 0">
              <span class="ico">🤖</span>
              <span class="txt"><strong>{{ data.todo.ai_coda }}</strong> mail AI in coda</span>
              <a :href="data.urls.ai">vai</a>
            </li>
            <li v-if="data.todo.inbox_check > 0" class="info">
              <span class="ico">📬</span>
              <span class="txt"><strong>{{ data.todo.inbox_check }}</strong> mail AI inviate (48h) — controlla risposte in inbox</span>
            </li>
            <li v-if="data.todo.pagamenti_count > 0">
              <span class="ico">💰</span>
              <span class="txt">
                <strong>{{ data.todo.pagamenti_count }}</strong>
                pagament{{ data.todo.pagamenti_count > 1 ? 'i' : 'o' }} da incassare (evento ≤30gg)
              </span>
            </li>
          </ul>
        </div>

        <div class="wvr-section">
          <h3>Stato attuale</h3>
          <div class="wvr-rings">
            <div class="wvr-ring-wrap">
              <Ring :pct="data.rings.pct_posti">
                <div class="v">{{ data.rings.pct_posti }}%</div>
                <div class="s">{{ data.rings.tot_confermati }}/{{ data.rings.tot_posti }}</div>
              </Ring>
              <div class="wvr-ring-label">Posti</div>
            </div>
            <a class="wvr-ring-wrap" :href="data.urls.checkpoint">
              <Ring :pct="data.rings.tot_richieste ? Math.min(100, data.rings.tot_richieste * 10) : 0">
                <div class="v">{{ data.rings.tot_richieste }}</div>
                <div class="s">attesa</div>
              </Ring>
              <div class="wvr-ring-label">Richieste</div>
            </a>
            <a class="wvr-ring-wrap" :href="data.urls.contatti">
              <Ring :pct="100">
                <div class="v">{{ data.rings.tot_partecipanti }}</div>
                <div class="s">tot</div>
              </Ring>
              <div class="wvr-ring-label">Anagrafica</div>
            </a>
          </div>
        </div>

        <div class="wvr-section">
          <h3>Ultimi 30 giorni</h3>
          <div style="display: flex; justify-content: center; margin-bottom: 14px">
            <div class="wvr-ring-wrap" style="max-width: 140px">
              <Ring :pct="data.stats30.conv30" :size="120">
                <div class="v">{{ data.stats30.conv30 }}%</div>
                <div class="s">conv.</div>
              </Ring>
              <div class="wvr-ring-label">Tasso conversione</div>
            </div>
          </div>
          <div class="wvr-stat-list">
            <a class="wvr-stat-row" :href="data.urls.contatti">
              <span class="lbl">Nuove richieste</span>
              <span class="val">{{ data.stats30.n_nuove }}</span>
            </a>
            <div class="wvr-stat-row">
              <span class="lbl">Confermate</span>
              <span class="val">{{ data.stats30.n_conf30 }}</span>
            </div>
            <a class="wvr-stat-row" :href="data.urls.ai">
              <span class="lbl">Mail AI inviate</span>
              <span class="val">{{ data.stats30.mail_ai_30 }}</span>
            </a>
          </div>
        </div>

        <div v-if="data.pro_stats" class="wvr-section">
          <h3>📊 Statistiche <span style="font-size: 10px; font-weight: 800; letter-spacing: 0.5px; background: #7c3aed; color: #fff; padding: 2px 7px; border-radius: 4px; vertical-align: middle;">PRO</span></h3>
          <div class="wvr-stat-list">
            <div class="wvr-stat-row">
              <span class="lbl">Incassato (30gg)</span>
              <span class="val">{{ data.pro_stats.revenue30_label }}</span>
            </div>
            <div class="wvr-stat-row">
              <span class="lbl">Incassato (totale)</span>
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
              <span class="lbl">🎁 Coupon emessi / usati</span>
              <span class="val">{{ data.pro_stats.marketing.coupon_emessi }} / {{ data.pro_stats.marketing.coupon_usati }}</span>
            </div>
            <div class="wvr-stat-row">
              <span class="lbl">Sconto totale concesso</span>
              <span class="val">{{ data.pro_stats.marketing.sconto_totale_label }}</span>
            </div>
          </div>
        </div>
      </aside>

      <main class="wvr-main">
        <div class="wvr-filtro">
          <a :class="{ on: !mostraTutti }" @click="mostraTutti = false">Solo in programma</a>
          <a :class="{ on: mostraTutti }" @click="mostraTutti = true">Tutti (anche conclusi)</a>
        </div>

        <div v-if="data.eventi.length" class="wvr-grid">
          <a v-for="e in data.eventi" :key="e.id" class="wvr-card" @click="emit('open-evento', e.id)">
            <div class="wvr-card-foto" :style="e.foto ? { backgroundImage: `url('${e.foto}')` } : {}">
              <div class="wvr-card-overlay">
                <span class="wvr-card-cat">{{ e.cat_name }}</span>
                <span class="wvr-card-date">{{ e.periodo }}</span>
              </div>
              <span v-if="e.concluso" class="wvr-card-concluso">concluso</span>
            </div>
            <div class="wvr-card-body">
              <template v-if="e.sold_out">
                <span class="wvr-soldout">Sold Out</span>
                <template v-if="e.n_richieste > 0"> · <span class="wvr-richiesta-inline">{{ e.n_richieste }} Rich.</span></template>
              </template>
              <template v-else>
                Posti <strong>{{ e.occupati }}/{{ e.totali }}</strong>
                <template v-if="e.n_richieste > 0"> · <span class="wvr-richiesta-inline">{{ e.n_richieste }} Rich.</span></template>
              </template>
            </div>
          </a>
        </div>
        <div v-else class="wvr-empty">Nessun evento.</div>
      </main>
    </div>
  </div>
</template>
