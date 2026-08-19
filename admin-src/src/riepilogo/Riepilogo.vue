<script setup>
import { ref, onMounted } from 'vue';
import Cockpit from './Cockpit.vue';
import EventoDetail from './EventoDetail.vue';

const eventoId = ref(0);
const msg = ref('');

function syncFromUrl() {
  const url = new URL(window.location.href);
  eventoId.value = parseInt(url.searchParams.get('evento_id') || '0', 10);
}

function openEvento(id) {
  const url = new URL(window.location.href);
  url.searchParams.set('evento_id', id);
  window.history.pushState({}, '', url);
  eventoId.value = id;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function back() {
  const url = new URL(window.location.href);
  url.searchParams.delete('evento_id');
  window.history.pushState({}, '', url);
  eventoId.value = 0;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function flash(text) {
  msg.value = text;
  setTimeout(() => {
    if (msg.value === text) msg.value = '';
  }, 4000);
}

onMounted(() => {
  syncFromUrl();
  window.addEventListener('popstate', syncFromUrl);
});
</script>

<template>
  <div class="wvr">
    <div v-if="msg" class="wvr-msg">{{ msg }}</div>
    <EventoDetail v-if="eventoId" :evento-id="eventoId" @back="back" @message="flash" />
    <Cockpit v-else @open-evento="openEvento" />
  </div>
</template>

<style>
.wvr {
  color: #2c3338;
  box-sizing: border-box;
  max-width: 100%;
  margin: 0;
  padding: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
}

.wvr a,
.wvr a:visited,
.wvr a:hover {
  text-decoration: none !important;
}

.wvr h2 {
  color: #1d2327;
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 1px solid #dcdcde;
  margin: 0 0 20px;
  padding-bottom: 8px;
}

.wvr h3 {
  color: #1d2327;
  font-size: 13px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin: 0 0 14px;
}

.wvr-msg {
  color: #1d2327;
  background: #fff;
  border-left: 4px solid #2271b1;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);
  margin: 0 0 18px;
  padding: 12px 16px;
  font-size: 13px;
}

.wvr-cockpit {
  display: grid;
  grid-template-columns: 340px 1fr;
  align-items: start;
  gap: 24px;
}

@media (max-width: 980px) {
  .wvr-cockpit {
    grid-template-columns: 1fr;
  }
}

.wvr-sidebar {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.wvr-section {
  background: #ffffff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
  padding: 18px;
}

.wvr-todo {
  background: #fff8e5;
  border-color: #f0c33c;
}

.wvr-todo h3 {
  color: #8c5b00;
  font-weight: 700;
}

.wvr-todo ul {
  margin: 0;
  padding: 0;
  list-style: none;
}

.wvr-todo li {
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0;
  font-size: 13px;
  color: #2c3338;
}

.wvr-todo li:last-child {
  border-bottom: none;
}

.wvr-todo li strong {
  color: #8c5b00;
}

.wvr-todo li .txt {
  flex: 1;
}

.wvr-todo li a {
  font-size: 12px;
  font-weight: 600;
  color: #2271b1 !important;
  text-transform: uppercase;
}

.wvr-rings {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  margin-top: 10px;
}

.wvr-ring-wrap {
  text-align: center;
  flex: 1;
  min-width: 0;
  background: #f6f7f7;
  border: 1px solid #dcdcde;
  border-radius: 4px;
  padding: 12px 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease-in-out;
}

a.wvr-ring-wrap {
  color: inherit;
  cursor: pointer;
}

a.wvr-ring-wrap:hover {
  background: #fff;
  border-color: #2271b1;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.wvr-ring {
  position: relative;
  width: auto !important;
  height: auto !important;
  margin: 0 auto;
}

.wvr-ring svg {
  display: none !important;
}

.wvr-ring-center {
  position: static !important;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  line-height: 1.2;
}

.wvr-ring-center .v {
  color: #1d2327 !important;
  font-size: 22px !important;
  font-weight: 700 !important;
}

.wvr-ring-center .s {
  color: #646970 !important;
  font-size: 11px !important;
  margin-top: 2px;
}

.wvr-ring-label {
  text-align: center;
  color: #50575e;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 6px;
  font-size: 11px;
}

.wvr-stat-list {
  display: flex;
  flex-direction: column;
}

.wvr-stat-row {
  border-bottom: 1px solid #f0f0f1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  font-size: 13px;
  color: #2c3338;
}

.wvr-stat-row:last-child {
  border-bottom: none;
}

.wvr-stat-row .lbl {
  color: #646970;
  font-weight: 500;
  font-size: 13px;
}

.wvr-stat-row .val {
  color: #1d2327;
  font-size: 14px;
  font-weight: 600;
}

a.wvr-stat-row:hover .lbl {
  color: #2271b1;
}

a.wvr-stat-row:hover .val {
  color: #2271b1;
}

.wvr-main {
  min-width: 0;
}

.wvr-filtro {
  margin: 0 0 16px;
  font-size: 13px;
  display: flex;
  gap: 8px;
}

.wvr-filtro a {
  color: #50575e;
  cursor: pointer;
  padding: 6px 12px;
  background: #f6f7f7;
  border: 1px solid #dcdcde;
  border-radius: 3px;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.15s ease;
}

.wvr-filtro a.on {
  background: #2271b1;
  border-color: #2271b1;
  color: #ffffff !important;
}

.wvr-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
}

.wvr-card {
  cursor: pointer;
  background: #ffffff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  transition: all 0.15s ease-in-out;
  display: block;
  color: inherit !important;
}

.wvr-card:hover {
  border-color: #2271b1;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-1px);
}

.wvr-evento-grid {
  display: grid;
  grid-template-columns: 360px 1fr 280px;
  gap: 20px;
  align-items: start;
  margin-bottom: 20px;
}

.wvr-card-hero {
  grid-column: 1;
  grid-row: 1 / span 2;
}

.wvr-evento-ring-col {
  grid-column: 2;
  grid-row: 1;
}

.wvr-evento-todo-col {
  grid-column: 2;
  grid-row: 2;
}

.wvr-evento-right {
  grid-column: 3;
  grid-row: 1 / span 2;
}

@media (max-width: 1100px) {
  .wvr-evento-grid {
    grid-template-columns: 360px 1fr;
  }
  .wvr-evento-right {
    grid-column: 1 / span 2;
    grid-row: 3;
  }
}

@media (max-width: 780px) {
  .wvr-evento-grid {
    grid-template-columns: 1fr;
  }
  .wvr-card-hero,
  .wvr-evento-ring-col,
  .wvr-evento-todo-col,
  .wvr-evento-right {
    grid-column: 1;
    grid-row: auto;
  }
}

.wvr-altri-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.wvr-altri-chip {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  background: #fff;
  border: 1px solid #c3c4c7;
  border-radius: 4px;
  padding: 8px 12px;
  text-decoration: none;
  font-size: 13px;
  color: #1d2327;
  transition: border-color 0.15s;
}

.wvr-altri-chip:hover {
  border-color: #2271b1;
}

.wvr-altri-chip .d {
  font-weight: 600;
}

.wvr-altri-chip .st {
  font-size: 11px;
  color: #646970;
  text-transform: uppercase;
  white-space: nowrap;
}

.wvr-card-foto {
  aspect-ratio: 2;
  background-color: #f0f0f1;
  background-position: center;
  background-size: cover;
  width: 100%;
  position: relative;
}

.wvr-card-foto:after {
  content: '';
  background: linear-gradient(rgba(0, 0, 0, 0) 30%, rgba(0, 0, 0, 0.4) 60%, rgba(0, 0, 0, 0.85) 100%);
  position: absolute;
  inset: 0;
}

.wvr-card-overlay {
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  position: absolute;
  bottom: 8px;
  left: 12px;
  right: 12px;
}

.wvr-card-cat {
  color: #ff9a6f;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 10px;
  font-weight: 700;
}

.wvr-card-date {
  color: #ffffff;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.2;
}

.wvr-card-concluso {
  z-index: 1;
  color: #ffffff;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  background: rgba(0, 0, 0, 0.65);
  border-radius: 3px;
  padding: 3px 6px;
  font-size: 10px;
  position: absolute;
  top: 8px;
  right: 8px;
}

.wvr-card-body {
  color: #646970;
  padding: 12px;
  font-size: 13px;
  background: #ffffff;
  border-top: 1px solid #f0f0f1;
}

.wvr-card-body strong {
  color: #1d2327;
}

.wvr-card-body .wvr-richiesta-inline {
  color: #d63638;
  font-weight: 600;
}

.wvr-soldout {
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #d63638;
  background: #fcf0f1;
  border: 1px solid #f8d7da;
  border-radius: 3px;
  padding: 2px 6px;
  font-size: 10px;
  font-weight: 600;
  display: inline-block;
}

.wvr-empty {
  text-align: center;
  color: #646970;
  padding: 40px 0;
  font-size: 14px;
  background: #fff;
  border: 1px dashed #c3c4c7;
  border-radius: 4px;
}

.wvr-back {
  color: #2271b1 !important;
  font-weight: 600;
  cursor: pointer;
  margin-bottom: 12px;
  display: inline-block;
  font-size: 13px;
}
.wvr-back:hover {
  color: #135e96 !important;
  text-decoration: underline !important;
}

.wvr-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  border: 1px solid #c3c4c7;
  margin-top: 12px;
}
.wvr-table th {
  background: #f6f7f7;
  color: #1d2327;
  font-weight: 600;
  text-align: left;
  padding: 10px;
  border-bottom: 1px solid #c3c4c7;
  font-size: 13px;
}
.wvr-table td {
  padding: 10px;
  border-bottom: 1px solid #f0f0f1;
  color: #2c3338;
  font-size: 13px;
}
.wvr-table tr:hover td {
  background-color: #f6f7f7;
}

.wvr-badge {
  text-transform: uppercase;
  font-size: 10px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 3px;
  display: inline-block;
}
.wvr-confermato {
  background: #edfaef;
  color: #008a20;
  border: 1px solid #c3e6cb;
}
.wvr-richiesta {
  background: #fff8e5;
  color: #8c5b00;
  border: 1px solid #ffeeba;
}

.wvr-link {
  color: #2271b1 !important;
  cursor: pointer;
  background: #f6f7f7;
  border: 1px solid #dcdcde;
  border-radius: 3px;
  padding: 4px 8px;
  font-size: 12px;
  font-weight: 500;
}
.wvr-link:hover {
  background: #f0f0f1;
  border-color: #2271b1;
}
.wvr-link-del {
  color: #d63638 !important;
  border-color: #f8d7da;
  background: #fcf0f1;
}
.wvr-link-del:hover {
  background: #d63638;
  color: #fff !important;
}

.wvr-evento-ring-col .lbl {
  text-align: center;
  color: #50575e;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 6px;
  font-size: 11px;
}

.wvr-evento-ring-col .big {
  text-align: center;
  color: #1d2327;
  font-size: 20px;
  font-weight: 700;
  margin-top: 10px;
}

.wvr-evento-ring-col .big span {
  color: #646970;
  font-size: 13px;
  font-weight: 400;
}

.wvr-evento-ring-col .sub {
  text-align: center;
  color: #646970;
  font-size: 12px;
  margin-top: 4px;
}

.empty-todo {
  color: #646970;
  text-align: center;
  padding: 18px 0;
  font-size: 13px;
  font-style: italic;
}

.wvr-evento-actions {
  display: flex;
  gap: 10px;
  margin-top: 14px;
}

.wvr-table-wrap {
  overflow-x: auto;
}

.wvr-actions-cell {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.wvr-dialog-mask {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100000;
}

.wvr-dialog {
  background: #fff;
  border-radius: 4px;
  padding: 20px;
  width: 360px;
  max-width: 90vw;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.wvr-dialog h4 {
  margin: 0 0 12px;
  color: #1d2327;
  font-size: 14px;
}

.wvr-dialog textarea {
  width: 100%;
  box-sizing: border-box;
  min-height: 80px;
  resize: vertical;
}

.wvr-note-count {
  text-align: right;
  color: #8c8f94;
  font-size: 11px;
  margin-top: 4px;
}

.wvr-dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 14px;
}

/* Frontend dark theme — see WS_Shortcode_Base::render(). */
.ws-theme-dark .wvr {
  color: #fff;
}

.ws-theme-dark .wvr h2,
.ws-theme-dark .wvr h3 {
  color: #fff;
  border-bottom-color: rgba(255, 255, 255, 0.12);
}

.ws-theme-dark .wvr-msg {
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
  border-left-color: #ff6608;
}

.ws-theme-dark .wvr-section {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.1);
  box-shadow: none;
}

.ws-theme-dark .wvr-todo {
  background: rgba(255, 102, 8, 0.04);
  border-color: rgba(255, 102, 8, 0.4);
}

.ws-theme-dark .wvr-todo h3 {
  color: #ff6608;
}

.ws-theme-dark .wvr-todo li strong {
  color: #ff6608;
}

.ws-theme-dark .wvr-todo li a {
  color: rgba(255, 255, 255, 0.8) !important;
}

.ws-theme-dark .wvr-ring-wrap {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.15);
  color: #fff !important;
}

.ws-theme-dark a.wvr-ring-wrap:hover {
  background: rgba(255, 255, 255, 0.05);
  border-color: #ff6608;
}

.ws-theme-dark .wvr-ring-center .v {
  color: #fff !important;
}

.ws-theme-dark .wvr-ring-center .s {
  color: rgba(255, 255, 255, 0.5) !important;
}

.ws-theme-dark .wvr-ring-label {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvr-stat-row {
  border-bottom-color: rgba(255, 255, 255, 0.06);
  color: #fff;
}

.ws-theme-dark .wvr-stat-row .lbl {
  color: rgba(255, 255, 255, 0.6);
}

.ws-theme-dark .wvr-stat-row .val {
  color: #fff;
}

.ws-theme-dark a.wvr-stat-row:hover .lbl,
.ws-theme-dark a.wvr-stat-row:hover .val {
  color: #ff6608;
}

.ws-theme-dark .wvr-filtro a {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
}

.ws-theme-dark .wvr-filtro a.on {
  background: #ff6608;
  border-color: #ff6608;
  color: #fff;
}

.ws-theme-dark .wvr-card {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.12);
  box-shadow: none;
  color: inherit !important;
}

.ws-theme-dark .wvr-card:hover {
  border-color: #ff6608;
  box-shadow: none;
}

.ws-theme-dark .wvr-card-cat {
  color: #ff9a6f;
}

.ws-theme-dark .wvr-card-date {
  color: #fff;
}

.ws-theme-dark .wvr-card-body {
  color: rgba(255, 255, 255, 0.6);
  background: transparent;
  border-top-color: rgba(255, 255, 255, 0.08);
}

.ws-theme-dark .wvr-card-body strong {
  color: #fff;
}

.ws-theme-dark .wvr-empty {
  color: rgba(255, 255, 255, 0.5);
  background: transparent;
  border-color: rgba(255, 255, 255, 0.2);
}

.ws-theme-dark .wvr-back {
  color: rgba(255, 255, 255, 0.6) !important;
}

.ws-theme-dark .wvr-back:hover {
  color: #ff9a6f !important;
}

.ws-theme-dark .wvr-evento-ring-col .lbl,
.ws-theme-dark .wvr-evento-ring-col .sub {
  color: rgba(255, 255, 255, 0.6);
}

.ws-theme-dark .wvr-evento-ring-col .big {
  color: #fff;
}

.ws-theme-dark .empty-todo {
  color: rgba(255, 255, 255, 0.5);
}

.ws-theme-dark .wvr-altri-chip {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.3);
  color: #fff;
}

.ws-theme-dark .wvr-altri-chip:hover {
  border-color: #ff6608;
  background: rgba(255, 102, 8, 0.05);
}

.ws-theme-dark .wvr-altri-chip .st {
  color: rgba(255, 255, 255, 0.6);
}

.ws-theme-dark .wvr-table {
  background: transparent;
  border-color: rgba(255, 255, 255, 0.15);
  color: #fff;
}

.ws-theme-dark .wvr-table th {
  background: transparent;
  color: rgba(255, 255, 255, 0.6);
  border-bottom-color: rgba(255, 255, 255, 0.15);
}

.ws-theme-dark .wvr-table td {
  border-bottom-color: rgba(255, 255, 255, 0.08);
  color: #fff;
}

.ws-theme-dark .wvr-table td strong {
  color: #fff !important;
}

.ws-theme-dark .wvr-table tr:hover td {
  background-color: rgba(255, 255, 255, 0.04);
}

.ws-theme-dark .wvr-badge.wvr-confermato {
  background: transparent;
  color: #7ddc8e;
  border-color: #7ddc8e;
}

.ws-theme-dark .wvr-badge.wvr-richiesta {
  background: transparent;
  color: #ff9a6f;
  border-color: #ff6608;
}

.ws-theme-dark .wvr-link {
  color: #fff !important;
  background: transparent;
  border-color: rgba(255, 255, 255, 0.4);
}

.ws-theme-dark .wvr-link:hover {
  background: #fff;
  border-color: #fff;
  color: #000 !important;
}

.ws-theme-dark .wvr-link-del {
  color: #ff6608 !important;
  border-color: rgba(255, 102, 8, 0.5);
  background: transparent;
}

.ws-theme-dark .wvr-link-del:hover {
  background: #ff6608;
  border-color: #ff6608;
  color: #fff !important;
}

.ws-theme-dark .wvr-dialog-mask {
  background: rgba(0, 0, 0, 0.75);
}

.ws-theme-dark .wvr-dialog {
  color: #fff;
  background: #111;
  border-color: rgba(255, 255, 255, 0.25);
}

.ws-theme-dark .wvr-dialog h4 {
  color: #fff;
}

.ws-theme-dark .wvr-dialog textarea {
  color: #fff;
  background: transparent;
  border-color: rgba(255, 255, 255, 0.3);
}

.ws-theme-dark .wvr-note-count {
  color: rgba(255, 255, 255, 0.5);
}
</style>
