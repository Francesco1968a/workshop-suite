<?php

if (!defined('ABSPATH')) exit;

/**
 * [wsma_aula_virtuale evento_id="X"] — embeds the virtual room for a
 * Modalità=virtuale evento. Jitsi rooms are embedded in-page via Jitsi's
 * own external_api.js (no self-hosted infra needed, works against the
 * free public meet.jit.si server); any other platform (Zoom/Meet/altro)
 * just shows a "Join" button linking out, exactly like before this
 * feature existed — this shortcode never becomes a requirement, only an
 * enhancement for organizers who pick Jitsi.
 */
final class WSMA_Shortcode_Aula_Virtuale implements WSMA_Module {

    public function should_load(): bool {
        return !is_admin() || wp_doing_ajax();
    }

    public function register(): void {
        add_shortcode('wsma_aula_virtuale', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_style']);
    }

    /**
     * Styles must be queued before wp_head's print point, but a shortcode's
     * render() callback only runs later, during content rendering — so the
     * CSS is enqueued here instead, gated on the shortcode actually being
     * present on the current post (checked early, on wp_enqueue_scripts).
     */
    public function maybe_enqueue_style(): void {
        if (is_admin()) return;
        global $post;
        if (!$post || !has_shortcode((string) $post->post_content, 'wsma_aula_virtuale')) return;

        WSMA_Data::enqueue_inline_style(
            '.ws-av-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1100px; margin: 0 auto; }'
            . '.ws-av-wrap h2 { font-size: 20px; font-weight: 700; margin: 0 0 16px; color: #1d2327; }'
            . '.ws-theme-dark .ws-av-wrap h2 { color: #fff; }'
            . '.ws-av-msg { color: #646970; }'
            . '.ws-theme-dark .ws-av-msg { color: rgba(255,255,255,.6); }'
            . '.ws-av-frame { position: relative; width: 100%; padding-bottom: 56.25%; height: 0; background: #000; border-radius: 6px; overflow: hidden; }'
            . '.ws-av-frame iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }'
            . '.ws-av-external { text-align: center; padding: 60px 24px; border: 1px solid #e2e8f0; border-radius: 6px; }'
            . '.ws-theme-dark .ws-av-external { border-color: rgba(255,255,255,.15); }'
            . '.ws-av-btn { display: inline-block; background: #ff6608; color: #fff !important; font-weight: 700; font-size: 15px; padding: 13px 30px; border-radius: 4px; text-decoration: none !important; }'
            . '.ws-av-btn:hover { background: #e05a00; }'
        );
    }

    public function render(array $atts = []): string {
        $atts = shortcode_atts(['evento_id' => 0], $atts);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only GET param selecting which event's page to render, no form data is processed.
        $evento_id = isset($_GET['evento_id']) ? absint(wp_unslash($_GET['evento_id'])) : (int) $atts['evento_id'];

        $theme = WSMA_Settings::get('default_theme_mode', 'dark');
        $theme = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';

        if (!$evento_id || get_post_type($evento_id) !== 'wsma_evento') {
            return '<div class="ws-theme-wrapper ws-theme-' . esc_attr($theme) . '"><p class="ws-av-msg">' . esc_html__('No virtual classroom specified.', 'wsmaker') . '</p></div>';
        }

        $modalita = WSMA_Data::get_field('modalita', $evento_id) ?: 'fisico';
        if ($modalita !== 'virtuale') {
            return '<div class="ws-theme-wrapper ws-theme-' . esc_attr($theme) . '"><p class="ws-av-msg">' . esc_html__('This event is not a virtual classroom.', 'wsmaker') . '</p></div>';
        }

        $piattaforma = WSMA_Data::get_field('piattaforma_virtuale', $evento_id) ?: 'jitsi';
        $link = (string) WSMA_Data::get_field('link_virtuale', $evento_id);
        $titolo = WSMA_Data::evento_label($evento_id);

        ob_start();
        ?>
        <div class="ws-theme-wrapper ws-theme-<?php echo esc_attr($theme); ?>">
            <div class="ws-av-wrap">
                <h2><?php echo esc_html($titolo); ?></h2>
                <?php if ($piattaforma === 'jitsi' && $link && strpos($link, 'meet.jit.si') !== false): ?>
                    <?php
                    $room = trim((string) wp_parse_url($link, PHP_URL_PATH), '/');
                    if (!wp_script_is('ws-jitsi-external', 'registered')) {
                        wp_register_script('ws-jitsi-external', 'https://meet.jit.si/external_api.js', [], null, true);
                    }
                    wp_enqueue_script('ws-jitsi-external');
                    wp_add_inline_script('ws-jitsi-external',
                        '(function () {'
                        . 'if (typeof JitsiMeetExternalAPI === "undefined") return;'
                        . 'new JitsiMeetExternalAPI("meet.jit.si", {'
                        . 'roomName: ' . wp_json_encode($room) . ','
                        . 'parentNode: document.getElementById(' . wp_json_encode('ws-av-jitsi-' . $evento_id) . '),'
                        . 'width: "100%", height: "100%",'
                        . 'configOverwrite: { prejoinPageEnabled: true },'
                        . '});'
                        . '})();',
                        'after'
                    );
                    ?>
                    <div id="ws-av-jitsi-<?php echo (int) $evento_id; ?>" class="ws-av-frame"></div>
                <?php elseif ($link): ?>
                    <div class="ws-av-external">
                        <p class="ws-av-msg"><?php echo esc_html(sprintf(
                            /* translators: %s: video platform name (e.g. Zoom, Meet) */
                            __('The video call takes place on %s.', 'wsmaker'),
                            ucfirst($piattaforma)
                        )); ?></p>
                        <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener" class="ws-av-btn"><?php esc_html_e('Go to the video call →', 'wsmaker'); ?></a>
                    </div>
                <?php else: ?>
                    <p class="ws-av-msg"><?php esc_html_e('The virtual classroom link is not available yet.', 'wsmaker'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
