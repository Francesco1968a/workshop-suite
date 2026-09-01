<?php

if (!defined('ABSPATH')) exit;

/** Vue 3 + Element Plus rewrite of the legacy `workshop_admin` shortcode. */
final class WSMA_Shortcode_Admin extends WSMA_Shortcode_Base {

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
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_theme_css']);
    }

    /**
     * admin.css is also loaded directly by two PHP-only wp-admin pages
     * (Settings, Onboarding Wizard) that have nothing to do with this
     * shortcode's frontend dark theme — so the .ws-theme-dark overrides
     * live in their own small file instead of inside admin.css.
     *
     * Registered unconditionally on wp_enqueue_scripts rather than gated
     * on shortcode presence: should_load() already restricts this whole
     * class to frontend requests, the shortcode itself only enqueues its
     * own script/style at render() time (during content processing, which
     * runs after this hook), so there's no reliable way to detect "is the
     * shortcode actually on this page" this early — and the file is a few
     * KB, harmless to register on pages that don't end up using it.
     */
    public function maybe_enqueue_theme_css(): void {
        $css_path = WSMA_PATH . 'assets/dist/admin-theme.css';
        if (!file_exists($css_path)) return;
        wp_enqueue_style('ws-admin-theme', WSMA_URL . 'assets/dist/admin-theme.css', [], (string) filemtime($css_path));
    }
}
