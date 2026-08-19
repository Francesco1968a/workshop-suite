<?php

if (!defined('ABSPATH')) exit;

/** Vue 3 + Element Plus rewrite of the legacy `workshop_admin` shortcode. */
final class WS_Shortcode_Admin extends WS_Shortcode_Base {

    public function should_load(): bool {
        return !is_admin() || wp_doing_ajax();
    }

    protected function handle(): string  { return 'ws-admin'; }
    protected function js_file(): string  { return 'assets/dist/admin.js'; }
    protected function css_file(): string { return 'assets/dist/admin.css'; }
    protected function app_id(): string   { return 'ws-admin-app'; }

    public function register(): void {
        $this->add_shortcodes('workshop_admin', 'workshop_app', 'fv_workshop');
        $this->add_module_type_filter();
    }
}
