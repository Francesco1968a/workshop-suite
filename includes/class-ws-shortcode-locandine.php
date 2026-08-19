<?php

if (!defined('ABSPATH')) exit;

/**
 * Shortcode & Admin Page module for Locandine Generator ([workshop_locandine]).
 * Passes extra FVW_CONFIG keys: defaultTheme and brandName.
 */
final class WS_Shortcode_Locandine extends WS_Shortcode_Base {

    public function should_load(): bool {
        return WS_Settings::is_module_active('poster_studio', true);
    }

    protected function handle(): string  { return 'ws-locandine'; }
    protected function js_file(): string  { return 'assets/dist/locandine.js'; }
    protected function css_file(): string { return 'assets/dist/locandine.css'; }
    protected function app_id(): string   { return 'fvw-locandine-app'; }

    protected function extra_config(): array {
        return [
            'defaultTheme' => WS_Settings::get('default_theme_mode', 'dark'),
            'brandName'    => WS_Settings::get('site_brand_name', 'FRANCESCOVEROLINO'),
        ];
    }

    public function register(): void {
        $this->add_shortcodes('workshop_locandine');
        $this->add_module_type_filter();
    }
}
