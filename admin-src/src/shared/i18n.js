/**
 * Minimal translation lookup for the Vue admin panels.
 *
 * PHP injects a flat key -> translated-string map for the current site
 * locale into window.WSMA_CONFIG.i18n (see enqueue_panel_assets() in
 * class-ws-admin-settings-page.php, and includes/i18n/*.php for the maps
 * themselves). English is the plugin's source language, so every call
 * site always passes its own English text as the fallback: with no
 * WSMA_CONFIG.i18n entry (English locale, or a key not yet translated)
 * the component still renders in English rather than showing a raw key.
 *
 * @param {string} key      Stable lookup key (not shown to users).
 * @param {string} fallback English text, used whenever no translation
 *                          for `key` exists in the current locale map.
 */
export function t(key, fallback) {
  const map = (typeof window !== 'undefined' && window.WSMA_CONFIG && window.WSMA_CONFIG.i18n) || {};
  const value = map[key];
  return typeof value === 'string' && value !== '' ? value : fallback;
}
