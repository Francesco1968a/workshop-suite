<?php

if (!defined('ABSPATH')) exit;

/** Mounts the Vue Ringraziamento panel. REST endpoints live in WSMA_Ringraziamento. */
final class WSMA_Shortcode_Ringraziamento implements WSMA_Module {

    public function should_load(): bool {
        return !is_admin() || wp_doing_ajax();
    }

    public function register(): void {
        add_shortcode('workshop_ringraziamento_panel', [$this, 'render']);
        add_filter('script_loader_tag', [$this, 'add_module_type'], 10, 2);
    }

    public function add_module_type(string $tag, string $handle): string {
        if ($handle !== 'ws-ringraziamento') return $tag;
        if (strpos($tag, 'type=') !== false) return $tag;
        return str_replace(' src=', ' type="module" src=', $tag);
    }

    public function render(): string {
        if (!current_user_can('manage_options')) {
            return '<p>Accesso riservato.</p>';
        }

        $asset_js  = WSMA_PATH . 'assets/dist/ringraziamento.js';
        $asset_css = WSMA_PATH . 'assets/dist/ringraziamento.css';

        wp_enqueue_script(
            'ws-ringraziamento',
            WSMA_URL . 'assets/dist/ringraziamento.js',
            [],
            file_exists($asset_js) ? (string) filemtime($asset_js) : WSMA_VERSION,
            true
        );
        if (file_exists($asset_css)) {
            wp_enqueue_style(
                'ws-ringraziamento',
                WSMA_URL . 'assets/dist/ringraziamento.css',
                [],
                (string) filemtime($asset_css)
            );
        }

        $ringraziamento_config = [
            'restUrl' => esc_url_raw(rest_url('workshop-suite/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ];
        wp_localize_script('ws-ringraziamento', 'WSMA_CONFIG', $ringraziamento_config);

        $theme = WSMA_Settings::get('default_theme_mode', 'dark');
        $theme = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';

        return '<div class="ws-theme-wrapper ws-theme-' . esc_attr($theme) . '"><div id="ws-ringraziamento-app"></div></div>';
    }
}
