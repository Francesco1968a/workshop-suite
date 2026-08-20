<?php

if (!defined('ABSPATH')) exit;

/**
 * [ws_workshop_page slug="..."] — the new predefined, structured landing
 * page for a workshop categoria: hero image, intro, programma, requisiti,
 * note importanti, then the existing [eventi_categoria] grid and
 * [ws_form_iscrizione] booking form composed in via do_shortcode() rather
 * than reimplemented — the old shortcodes stay first-class citizens, this
 * is just a curated default arrangement of them plus the new descriptive
 * fields. Deliberately NOT used for [ws_aula_virtuale] pages, which stay
 * purely functional per the "Aula virtuale = utility, not marketing" split.
 */
final class WS_Shortcode_Workshop_Page implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('ws_workshop_page', [$this, 'render']);
    }

    public function render($atts): string {
        $atts = shortcode_atts(['slug' => ''], $atts);
        $slug = sanitize_title($atts['slug']);

        $theme = WS_Settings::get('default_theme_mode', 'dark');
        $theme = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';

        $term = $slug ? get_term_by('slug', $slug, 'categoria_evento') : null;
        if (!$term || is_wp_error($term)) {
            return '<div class="ws-theme-wrapper ws-theme-' . esc_attr($theme) . '"><p class="ws-wp-msg">Categoria workshop non trovata.</p></div>';
        }

        $tid = $term->term_id;
        $ctx = 'categoria_evento_' . $tid;
        $hero = (string) WS_Data::get_field('foto_categoria', $ctx);
        $intro = (string) WS_Data::get_field('intro', $ctx);
        $programma = (string) WS_Data::get_field('programma_testo', $ctx);
        $requisiti = (string) WS_Data::get_field('requisiti', $ctx);
        $note = (string) WS_Data::get_field('note_importanti', $ctx);

        ob_start();
        ?>
        <style>
            .ws-wp-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1100px; margin: 0 auto; }
            .ws-wp-hero { width: 100%; aspect-ratio: 16/6; background-size: cover; background-position: center; border-radius: 6px; margin-bottom: 26px; }
            .ws-wp-title { font-size: clamp(26px, 4vw, 38px); font-weight: 700; margin: 0 0 20px; color: #1d2327; }
            .ws-theme-dark .ws-wp-title { color: #fff; }
            .ws-wp-msg { color: #646970; }
            .ws-theme-dark .ws-wp-msg { color: rgba(255,255,255,.6); }
            .ws-wp-section { margin-bottom: 24px; }
            .ws-wp-section h2 { font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #ff6608; margin: 0 0 10px; }
            .ws-wp-section .ws-wp-text { font-size: 15.5px; line-height: 1.7; color: #3c434a; white-space: pre-line; }
            .ws-theme-dark .ws-wp-section .ws-wp-text { color: rgba(255,255,255,.82); }
            .ws-wp-note { border: 1px solid rgba(255,102,8,.4); background: rgba(255,102,8,.08); border-radius: 6px; padding: 16px 18px; }
        </style>
        <div class="ws-theme-wrapper ws-theme-<?php echo esc_attr($theme); ?>">
            <div class="ws-wp-wrap">
                <?php if ($hero): ?>
                    <div class="ws-wp-hero" style="background-image:url('<?php echo esc_url($hero); ?>')"></div>
                <?php endif; ?>

                <h1 class="ws-wp-title"><?php echo esc_html($term->name); ?></h1>

                <?php if ($intro): ?>
                    <div class="ws-wp-section"><div class="ws-wp-text"><?php echo esc_html($intro); ?></div></div>
                <?php endif; ?>

                <?php if ($programma): ?>
                    <div class="ws-wp-section">
                        <h2>Programma</h2>
                        <div class="ws-wp-text"><?php echo esc_html($programma); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($requisiti): ?>
                    <div class="ws-wp-section">
                        <h2>Requisiti</h2>
                        <div class="ws-wp-text"><?php echo esc_html($requisiti); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($note): ?>
                    <div class="ws-wp-section ws-wp-note">
                        <h2>Note importanti</h2>
                        <div class="ws-wp-text"><?php echo esc_html($note); ?></div>
                    </div>
                <?php endif; ?>

                <?php echo do_shortcode('[eventi_categoria slug="' . esc_attr($term->slug) . '"]'); ?>
                <?php echo do_shortcode('[ws_form_iscrizione categoria="' . esc_attr($term->slug) . '"]'); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
