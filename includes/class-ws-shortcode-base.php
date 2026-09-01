<?php

if (!defined('ABSPATH')) exit;

/**
 * Base class for all FVW Vue 3 shortcodes.
 *
 * Centralises the repeated enqueue + script_loader_tag pattern that was
 * copy-pasted verbatim across 8 shortcode classes. Each concrete shortcode
 * only needs to declare its handle, asset paths, shortcode tag(s), and
 * override extra_config() if it needs additional WSMA_CONFIG keys.
 *
 * The single add_filter('script_loader_tag', ...) is registered here once
 * per handle — not once per class instance — so it fires only once per
 * request no matter how many shortcodes are active on the same page.
 */
abstract class WSMA_Shortcode_Base implements WSMA_Module {

    // -----------------------------------------------------------------------
    // Subclasses MUST implement these
    // -----------------------------------------------------------------------

    /** JS asset handle — must match the key in script_loader_tag. */
    abstract protected function handle(): string;

    /** Relative path from WSMA_PATH, e.g. 'assets/dist/admin.js' */
    abstract protected function js_file(): string;

    /** Relative path from WSMA_PATH, e.g. 'assets/dist/admin.css' — empty string to skip. */
    abstract protected function css_file(): string;

    /** The HTML id of the Vue mount point, e.g. 'ws-admin-app' */
    abstract protected function app_id(): string;

    /**
     * Additional WSMA_CONFIG keys beyond restUrl and nonce.
     * Override in subclass when needed (e.g. defaultTheme, brandName).
     *
     * @return array<string, mixed>
     */
    protected function extra_config(): array {
        return [];
    }

    // -----------------------------------------------------------------------
    // WSMA_Module default implementations
    // -----------------------------------------------------------------------

    public function should_load(): bool {
        return true;
    }

    // -----------------------------------------------------------------------
    // Access control — override in subclass for different behaviour
    // -----------------------------------------------------------------------

    /**
     * Returns the HTML to show when the current user lacks access.
     * Return empty string '' to render nothing at all.
     */
    protected function access_denied_html(): string {
        return '<p>Accesso riservato. <a href="' . esc_url(wp_login_url(get_permalink())) . '">Accedi</a>.</p>';
    }

    // -----------------------------------------------------------------------
    // Shortcode registration helpers — call from subclass register()
    // -----------------------------------------------------------------------

    /**
     * Register one or more shortcode tags all pointing to render().
     *
     * @param string ...$tags shortcode tag names
     */
    protected function add_shortcodes(string ...$tags): void {
        foreach ($tags as $tag) {
            add_shortcode($tag, [$this, 'render']);
        }
    }

    /**
     * Register the script_loader_tag filter exactly once per handle.
     * Multiple calls with the same handle are idempotent.
     */
    protected function add_module_type_filter(): void {
        $handle = $this->handle();
        // Guard: only add the filter once even if multiple instances exist.
        static $registered = [];
        if (isset($registered[$handle])) return;
        $registered[$handle] = true;

        add_filter('script_loader_tag', function (string $tag, string $h) use ($handle): string {
            if ($h !== $handle) return $tag;
            if (strpos($tag, 'type=') !== false) return $tag;
            return str_replace(' src=', ' type="module" src=', $tag);
        }, 10, 2);
    }

    // -----------------------------------------------------------------------
    // Shortcode renderer — final, delegates to subclass hooks
    // -----------------------------------------------------------------------

    final public function render($atts = []): string {
        if (!current_user_can('manage_options')) {
            return $this->access_denied_html();
        }

        $handle   = $this->handle();
        $js_path  = WSMA_PATH . $this->js_file();
        $css_path = $this->css_file() ? WSMA_PATH . $this->css_file() : '';

        wp_enqueue_script(
            $handle,
            WSMA_URL . $this->js_file(),
            [],
            file_exists($js_path) ? (string) filemtime($js_path) : WSMA_VERSION,
            true
        );

        if ($css_path && file_exists($css_path)) {
            wp_enqueue_style(
                $handle,
                WSMA_URL . $this->css_file(),
                [],
                (string) filemtime($css_path)
            );
        }

        $config = array_merge([
            'restUrl' => esc_url_raw(rest_url('workshop-suite/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ], $this->extra_config());

        wp_localize_script($handle, 'WSMA_CONFIG', $config);
        WSMA_I18n::localize($handle);

        // Frontend-only theming: the wp-admin dashboard pages never go
        // through this method (they use render_panel_wrapper() in
        // WSMA_Admin_Settings_Page instead), so this wrapper class only ever
        // affects the shortcode-embedded frontend pages, never wp-admin.
        $theme = WSMA_Settings::get('default_theme_mode', 'dark');
        $theme = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';

        return '<div class="ws-theme-wrapper ws-theme-' . esc_attr($theme) . '"><div id="' . esc_attr($this->app_id()) . '"></div></div>';
    }
}
