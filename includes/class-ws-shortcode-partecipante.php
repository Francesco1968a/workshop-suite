<?php

if (!defined('ABSPATH')) exit;

/**
 * Vue 3 + Element Plus rewrite of the legacy `workshop_partecipante` shortcode.
 * Reads `pid` from the query string client-side, same as the legacy version.
 * Returns empty string when user lacks access (no login link shown intentionally).
 */
final class WSMA_Shortcode_Partecipante extends WSMA_Shortcode_Base {

    public function should_load(): bool {
        return !is_admin() || wp_doing_ajax();
    }

    protected function handle(): string  { return 'ws-partecipante'; }
    protected function js_file(): string  { return 'assets/dist/partecipante.js'; }
    protected function css_file(): string { return 'assets/dist/partecipante.css'; }
    protected function app_id(): string   { return 'ws-partecipante-app'; }

    protected function access_denied_html(): string {
        return '';
    }

    public function register(): void {
        $this->add_shortcodes('workshop_partecipante');
        $this->add_module_type_filter();
    }
}
