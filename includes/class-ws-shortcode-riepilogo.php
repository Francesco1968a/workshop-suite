<?php

if (!defined('ABSPATH')) exit;

/** Vue 3 + Element Plus rewrite of the legacy `workshop_riepilogo` shortcode. */
final class WS_Shortcode_Riepilogo extends WS_Shortcode_Base {

    protected function handle(): string  { return 'ws-riepilogo'; }
    protected function js_file(): string  { return 'assets/dist/riepilogo.js'; }
    protected function css_file(): string { return 'assets/dist/riepilogo.css'; }
    protected function app_id(): string   { return 'ws-riepilogo-app'; }

    public function register(): void {
        $this->add_shortcodes('workshop_riepilogo');
        $this->add_module_type_filter();
    }
}
