<?php

if (!defined('ABSPATH')) exit;

/** Mounts the Vue Ringraziamento panel. REST endpoints live in WS_Ringraziamento. */
final class WS_Shortcode_Ringraziamento implements WS_Module {

    public function should_load(): bool {
        return !is_admin() || wp_doing_ajax();
    }

    public function register(): void {
        add_shortcode('workshop_ringraziamento_panel', [$this, 'render']);
        add_filter('script_loader_tag', [$this, 'add_module_type'], 10, 2);
    }

    public function add_module_type(string $tag, string $handle): string {
        if ($handle !== 'fvw-ringraziamento') return $tag;
        if (strpos($tag, 'type=') !== false) return $tag;
        return str_replace(' src=', ' type="module" src=', $tag);
    }

    public function render(): string {
        if (!current_user_can('manage_options')) {
            return '<p>Accesso riservato.</p>';
        }

        $asset_js  = WS_PATH . 'assets/dist/ringraziamento.js';
        $asset_css = WS_PATH . 'assets/dist/ringraziamento.css';

        wp_enqueue_script(
            'fvw-ringraziamento',
            WS_URL . 'assets/dist/ringraziamento.js',
            [],
            file_exists($asset_js) ? (string) filemtime($asset_js) : WS_VERSION,
            true
        );
        if (file_exists($asset_css)) {
            wp_enqueue_style(
                'fvw-ringraziamento',
                WS_URL . 'assets/dist/ringraziamento.css',
                [],
                (string) filemtime($asset_css)
            );
        }

        $ringraziamento_config = [
            'restUrl' => esc_url_raw(rest_url('workshop-suite/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ];
        wp_localize_script('fvw-ringraziamento', 'WS_CONFIG', $ringraziamento_config);
        wp_localize_script('fvw-ringraziamento', 'FVW_CONFIG', $ringraziamento_config);

        return '<div id="fvw-ringraziamento-app"></div>';
    }
}
