<?php

if (!defined('ABSPATH')) exit;

/**
 * Frontend-only Categorie/Tipologia shortcode. Mounts the same admin.js/
 * admin.css bundle as [workshop_admin] (Eventi/Partecipanti), but defaults
 * to the Categorie/Tipologia tabs instead — mirroring the wp-admin backend
 * split between the "Events & Registrations" and "Categories & Types"
 * submenu pages, now that those two tab groups live in separate Vue
 * components (EventiTab.vue vs CategorieTipiTab.vue).
 */
final class WS_Shortcode_Categorie extends WS_Shortcode_Base {

    public function should_load(): bool {
        return !is_admin() || wp_doing_ajax();
    }

    protected function handle(): string  { return 'ws-admin'; }
    protected function js_file(): string  { return 'assets/dist/admin.js'; }
    protected function css_file(): string { return 'assets/dist/admin.css'; }
    protected function app_id(): string   { return 'ws-admin-app'; }

    protected function extra_config(): array {
        return ['panelMode' => 'categorie'];
    }

    public function register(): void {
        $this->add_shortcodes('workshop_categorie');
        $this->add_module_type_filter();
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_theme_css']);
    }

    /** Same dark-theme stylesheet as WS_Shortcode_Admin — see that class. */
    public function maybe_enqueue_theme_css(): void {
        $css_path = WS_PATH . 'assets/dist/admin-theme.css';
        if (!file_exists($css_path)) return;
        wp_enqueue_style('ws-admin-theme', WS_URL . 'assets/dist/admin-theme.css', [], (string) filemtime($css_path));
    }
}
