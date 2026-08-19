<?php

if (!defined('ABSPATH')) exit;

/** Vue 3 + Element Plus rewrite of the legacy `workshop_archivio` shortcode. */
final class WS_Shortcode_Archivio extends WS_Shortcode_Base {

    protected function handle(): string  { return 'ws-archivio'; }
    protected function js_file(): string  { return 'assets/dist/archivio.js'; }
    protected function css_file(): string { return 'assets/dist/archivio.css'; }
    protected function app_id(): string   { return 'ws-archivio-app'; }

    public function register(): void {
        $this->add_shortcodes('workshop_archivio');
        $this->add_module_type_filter();
    }
}
