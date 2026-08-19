<?php

if (!defined('ABSPATH')) exit;

/**
 * Vue 3 + Element Plus rewrite of the legacy `workshop_calendar_panel` shortcode.
 * The legacy "Calendari esterni" snippet also served the live
 * `/wv-calendar/[token].ics` feed — that logic is ported to WS_Ics_Feed
 * so the whole legacy snippet can be deactivated once both are verified.
 */
final class WS_Shortcode_Calendario extends WS_Shortcode_Base {

    protected function handle(): string  { return 'ws-calendario'; }
    protected function js_file(): string  { return 'assets/dist/calendario.js'; }
    protected function css_file(): string { return 'assets/dist/calendario.css'; }
    protected function app_id(): string   { return 'fvw-calendario-app'; }

    protected function access_denied_html(): string {
        return '<p>Accesso riservato.</p>';
    }

    public function register(): void {
        $this->add_shortcodes('workshop_calendar_panel');
        $this->add_module_type_filter();
    }
}
