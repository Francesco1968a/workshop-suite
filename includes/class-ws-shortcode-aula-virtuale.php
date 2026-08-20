<?php

if (!defined('ABSPATH')) exit;

/**
 * [ws_aula_virtuale evento_id="X"] — embeds the virtual room for a
 * Modalità=virtuale evento. Jitsi rooms are embedded in-page via Jitsi's
 * own external_api.js (no self-hosted infra needed, works against the
 * free public meet.jit.si server); any other platform (Zoom/Meet/altro)
 * just shows a "Join" button linking out, exactly like before this
 * feature existed — this shortcode never becomes a requirement, only an
 * enhancement for organizers who pick Jitsi.
 */
final class WS_Shortcode_Aula_Virtuale implements WS_Module {

    public function should_load(): bool {
        return !is_admin() || wp_doing_ajax();
    }

    public function register(): void {
        add_shortcode('ws_aula_virtuale', [$this, 'render']);
    }

    public function render(array $atts = []): string {
        $atts = shortcode_atts(['evento_id' => 0], $atts);
        $evento_id = (int) ($_GET['evento_id'] ?? $atts['evento_id']);

        $theme = WS_Settings::get('default_theme_mode', 'dark');
        $theme = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';

        if (!$evento_id || get_post_type($evento_id) !== 'evento') {
            return '<div class="ws-theme-wrapper ws-theme-' . esc_attr($theme) . '"><p class="ws-av-msg">Nessuna aula virtuale specificata.</p></div>';
        }

        $modalita = WS_Data::get_field('modalita', $evento_id) ?: 'fisico';
        if ($modalita !== 'virtuale') {
            return '<div class="ws-theme-wrapper ws-theme-' . esc_attr($theme) . '"><p class="ws-av-msg">Questo evento non è un\'aula virtuale.</p></div>';
        }

        $piattaforma = WS_Data::get_field('piattaforma_virtuale', $evento_id) ?: 'jitsi';
        $link = (string) WS_Data::get_field('link_virtuale', $evento_id);
        $titolo = WS_Data::evento_label($evento_id);

        ob_start();
        ?>
        <style>
            .ws-av-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1100px; margin: 0 auto; }
            .ws-av-wrap h2 { font-size: 20px; font-weight: 700; margin: 0 0 16px; color: #1d2327; }
            .ws-theme-dark .ws-av-wrap h2 { color: #fff; }
            .ws-av-msg { color: #646970; }
            .ws-theme-dark .ws-av-msg { color: rgba(255,255,255,.6); }
            .ws-av-frame { position: relative; width: 100%; padding-bottom: 56.25%; height: 0; background: #000; border-radius: 6px; overflow: hidden; }
            .ws-av-frame iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }
            .ws-av-external { text-align: center; padding: 60px 24px; border: 1px solid #e2e8f0; border-radius: 6px; }
            .ws-theme-dark .ws-av-external { border-color: rgba(255,255,255,.15); }
            .ws-av-btn { display: inline-block; background: #ff6608; color: #fff !important; font-weight: 700; font-size: 15px; padding: 13px 30px; border-radius: 4px; text-decoration: none !important; }
            .ws-av-btn:hover { background: #e05a00; }
        </style>
        <div class="ws-theme-wrapper ws-theme-<?php echo esc_attr($theme); ?>">
            <div class="ws-av-wrap">
                <h2><?php echo esc_html($titolo); ?></h2>
                <?php if ($piattaforma === 'jitsi' && $link && strpos($link, 'meet.jit.si') !== false): ?>
                    <?php $room = trim((string) wp_parse_url($link, PHP_URL_PATH), '/'); ?>
                    <div id="ws-av-jitsi-<?php echo (int) $evento_id; ?>" class="ws-av-frame"></div>
                    <script src="https://meet.jit.si/external_api.js"></script>
                    <script>
                    (function () {
                        if (typeof JitsiMeetExternalAPI === 'undefined') return;
                        new JitsiMeetExternalAPI('meet.jit.si', {
                            roomName: <?php echo wp_json_encode($room); ?>,
                            parentNode: document.getElementById('ws-av-jitsi-<?php echo (int) $evento_id; ?>'),
                            width: '100%',
                            height: '100%',
                            configOverwrite: { prejoinPageEnabled: true },
                        });
                    })();
                    </script>
                <?php elseif ($link): ?>
                    <div class="ws-av-external">
                        <p class="ws-av-msg">La videochiamata si svolge su <?php echo esc_html(ucfirst($piattaforma)); ?>.</p>
                        <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener" class="ws-av-btn">Vai alla videochiamata →</a>
                    </div>
                <?php else: ?>
                    <p class="ws-av-msg">Il link dell'aula virtuale non è ancora disponibile.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
