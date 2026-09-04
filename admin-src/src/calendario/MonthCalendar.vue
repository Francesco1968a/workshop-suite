<script setup>
import { ref, computed } from 'vue';
import { t } from '../shared/i18n.js';

const props = defineProps({
  eventi: { type: Array, default: () => [] },
});

const today = new Date();
const viewYear = ref(today.getFullYear());
const viewMonth = ref(today.getMonth()); // 0-based

const MONTH_NAMES = [
  t('mcal_gennaio', 'January'), t('mcal_febbraio', 'February'), t('mcal_marzo', 'March'),
  t('mcal_aprile', 'April'), t('mcal_maggio', 'May'), t('mcal_giugno', 'June'),
  t('mcal_luglio', 'July'), t('mcal_agosto', 'August'), t('mcal_settembre', 'September'),
  t('mcal_ottobre', 'October'), t('mcal_novembre', 'November'), t('mcal_dicembre', 'December'),
];
const DAY_NAMES = [
  t('mcal_lun', 'Mon'), t('mcal_mar', 'Tue'), t('mcal_mer', 'Wed'), t('mcal_gio', 'Thu'),
  t('mcal_ven', 'Fri'), t('mcal_sab', 'Sat'), t('mcal_dom', 'Sun'),
];

function toISO(y, m, d) {
  return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

function prevMonth() {
  if (viewMonth.value === 0) {
    viewMonth.value = 11;
    viewYear.value -= 1;
  } else {
    viewMonth.value -= 1;
  }
}

function nextMonth() {
  if (viewMonth.value === 11) {
    viewMonth.value = 0;
    viewYear.value += 1;
  } else {
    viewMonth.value += 1;
  }
}

function goToday() {
  viewYear.value = today.getFullYear();
  viewMonth.value = today.getMonth();
}

const monthLabel = computed(() => `${MONTH_NAMES[viewMonth.value]} ${viewYear.value}`);

// Weeks grid: array of weeks, each an array of 7 day-cells (or null for
// padding before day 1 / after the last day).
const weeks = computed(() => {
  const y = viewYear.value;
  const m = viewMonth.value;
  const firstDay = new Date(y, m, 1);
  const daysInMonth = new Date(y, m + 1, 0).getDate();
  // JS getDay(): 0=Sun..6=Sat — convert to Mon-first index (0=Mon..6=Sun).
  const leadingBlanks = (firstDay.getDay() + 6) % 7;

  const cells = [];
  for (let i = 0; i < leadingBlanks; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) cells.push(d);
  while (cells.length % 7 !== 0) cells.push(null);

  const out = [];
  for (let i = 0; i < cells.length; i += 7) out.push(cells.slice(i, i + 7));
  return out;
});

function eventsForDay(day) {
  if (!day) return [];
  const iso = toISO(viewYear.value, viewMonth.value, day);
  return props.eventi.filter((e) => e.data_inizio <= iso && e.data_fine >= iso);
}

function isToday(day) {
  if (!day) return false;
  return (
    viewYear.value === today.getFullYear() &&
    viewMonth.value === today.getMonth() &&
    day === today.getDate()
  );
}

function eventUrl(id) {
  return `${window.location.pathname}?page=workshop-suite-dashboard&evento_id=${id}`;
}
</script>

<template>
  <div class="wvcal-month">
    <div class="wvcal-month-nav">
      <button type="button" class="btn" @click="prevMonth">‹</button>
      <div class="wvcal-month-label">{{ monthLabel }}</div>
      <button type="button" class="btn" @click="nextMonth">›</button>
      <button type="button" class="btn" @click="goToday">{{ t('mcal_oggi', 'Today') }}</button>
    </div>

    <div class="wvcal-grid wvcal-grid-head">
      <div v-for="d in DAY_NAMES" :key="d" class="wvcal-day-name">{{ d }}</div>
    </div>

    <div v-for="(week, wi) in weeks" :key="wi" class="wvcal-grid">
      <div
        v-for="(day, di) in week"
        :key="di"
        class="wvcal-cell"
        :class="{ 'wvcal-cell--empty': !day, 'wvcal-cell--today': isToday(day) }"
      >
        <template v-if="day">
          <div class="wvcal-daynum">{{ day }}</div>
          <a
            v-for="ev in eventsForDay(day)"
            :key="ev.id"
            :href="eventUrl(ev.id)"
            class="wvcal-chip"
            :class="{ 'wvcal-chip--soldout': ev.sold_out }"
            :title="ev.label"
          >
            {{ ev.categoria_nome || ev.label }}
          </a>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wvcal-month {
  background: #fff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
  padding: 16px;
}

.wvcal-month-nav {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.wvcal-month-label {
  font-size: 15px;
  font-weight: 600;
  color: #1d2327;
  flex: 1;
  text-transform: capitalize;
}

.btn {
  background: #f6f7f7;
  border: 1px solid #8c8f94;
  color: #2c3338;
  padding: 4px 12px;
  font-size: 13px;
  border-radius: 4px;
  cursor: pointer;
}

.btn:hover {
  background: #f0f0f1;
}

.wvcal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.wvcal-grid-head {
  margin-bottom: 4px;
}

.wvcal-day-name {
  text-align: center;
  font-size: 11px;
  font-weight: 600;
  color: #646970;
  text-transform: uppercase;
  padding: 4px 0;
}

.wvcal-cell {
  min-height: 76px;
  border: 1px solid #e0e0e1;
  border-radius: 3px;
  padding: 4px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.wvcal-cell--empty {
  border-color: transparent;
  background: transparent;
}

.wvcal-cell--today {
  border-color: #2271b1;
  background: #f0f6fc;
}

.wvcal-daynum {
  font-size: 11px;
  color: #646970;
  font-weight: 600;
}

.wvcal-chip {
  display: block;
  font-size: 10px;
  line-height: 1.3;
  background: #2271b1;
  color: #fff;
  padding: 2px 5px;
  border-radius: 2px;
  text-decoration: none;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wvcal-chip:hover {
  background: #135e96;
}

.wvcal-chip--soldout {
  background: #c9302c;
}

@media (max-width: 900px) {
  .wvcal-cell {
    min-height: 50px;
  }
  .wvcal-chip {
    font-size: 9px;
  }
}

/* Frontend dark theme — see WS_Shortcode_Base::render(). */
.ws-theme-dark .wvcal-month {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow: none;
}

.ws-theme-dark .wvcal-month-label {
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

.ws-theme-dark .wvcal-day-name {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvcal-cell {
  border-color: rgba(255, 255, 255, 0.12);
}

.ws-theme-dark .wvcal-cell--empty {
  border-color: transparent;
}

.ws-theme-dark .wvcal-cell--today {
  border-color: #ff6608;
  background: rgba(255, 102, 8, 0.08);
}

.ws-theme-dark .wvcal-daynum {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvcal-chip {
  background: #ff6608;
}

.ws-theme-dark .wvcal-chip:hover {
  background: #e05a00;
}

.ws-theme-dark .wvcal-chip--soldout {
  background: #c9302c;
}
</style>
