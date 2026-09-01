<?php

if (!defined('ABSPATH')) exit;

/**
 * Vue 3 + Element Plus rewrite of the legacy `workshop_partecipanti_lista`
 * shortcode. Verified visually against the original on a private test page
 * (2026-08-16) before taking over the production tag. The legacy Code
 * Snippets entry must be deactivated (never deleted) so only one of the two
 * registers `workshop_partecipanti_lista`.
 */
final class WSMA_Shortcode_Partecipanti_Lista extends WSMA_Shortcode_Base {

    protected function handle(): string  { return 'ws-partecipanti-lista'; }
    protected function js_file(): string  { return 'assets/dist/partecipanti-lista.js'; }
    protected function css_file(): string { return 'assets/dist/partecipanti-lista.css'; }
    protected function app_id(): string   { return 'ws-partecipanti-lista-app'; }

    protected function access_denied_html(): string {
        return '<p>Accesso riservato.</p>';
    }

    public function register(): void {
        $this->add_shortcodes('workshop_partecipanti_lista');
        $this->add_module_type_filter();
    }
}
