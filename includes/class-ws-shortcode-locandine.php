<?php

if (!defined('ABSPATH')) exit;

/**
 * Shortcode & Admin Page module for Locandine Generator ([workshop_locandine]).
 * Passes extra FVW_CONFIG keys: defaultTheme and brandName.
 */
final class WSMA_Shortcode_Locandine extends WSMA_Shortcode_Base {

    public function should_load(): bool {
        return defined('WS_PRO_VERSION') && WSMA_Settings::is_module_active('poster_studio', false);
    }

    protected function handle(): string  { return 'ws-locandine'; }
    protected function js_file(): string  { return 'assets/dist/locandine.js'; }
    protected function css_file(): string { return 'assets/dist/locandine.css'; }
    protected function app_id(): string   { return 'ws-locandine-app'; }

    protected function extra_config(): array {
        return [
            'defaultTheme' => WSMA_Settings::get('default_theme_mode', 'dark'),
            'brandName'    => WSMA_Settings::get('site_brand_name', 'FRANCESCOVEROLINO'),
        ];
    }

    public function register(): void {
        $this->add_shortcodes('workshop_locandine');
        $this->add_module_type_filter();
    }
}
