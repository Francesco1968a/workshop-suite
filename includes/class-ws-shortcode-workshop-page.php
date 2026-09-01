<?php

if (!defined('ABSPATH')) exit;

/**
 * [wsma_workshop_page slug="..."] — the new predefined, structured landing
 * page for a workshop categoria: hero image, intro, programma, requisiti,
 * note importanti, then the existing [eventi_categoria] grid and
 * [wsma_form_iscrizione] booking form composed in via do_shortcode() rather
 * than reimplemented — the old shortcodes stay first-class citizens, this
 * is just a curated default arrangement of them plus the new descriptive
 * fields. Deliberately NOT used for [wsma_aula_virtuale] pages, which stay
 * purely functional per the "Aula virtuale = utility, not marketing" split.
 *
 * [eventi_categoria] gets theme="light" explicitly: its own default
 * ("dark") renders light/white text assuming the page around it already
 * has a dark background (true of the old hand-built YOOtheme pages) —
 * our auto-created WP page has a plain white background, so without this
 * override its text is nearly invisible.
 */
final class WSMA_Shortcode_Workshop_Page implements WSMA_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('wsma_workshop_page', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_style']);
    }

    /** Fully static CSS — enqueued early since render() itself runs too late (during content rendering) to print styles reliably. */
    public function maybe_enqueue_style(): void {
        if (is_admin()) return;
        global $post;
        if (!$post || !has_shortcode((string) $post->post_content, 'wsma_workshop_page')) return;

        WSMA_Data::enqueue_inline_style(
            '.ws-wp-wrap { --ws-card-bg: #ffffff; --ws-card-border: #e2e8f0; --ws-text-heading: #1d2327; --ws-text-body: #3c434a; --ws-text-muted: #646970; --ws-surface-alt: #f6f7f7; --ws-accent: #ff6608; }'
            . '.ws-theme-dark .ws-wp-wrap { --ws-card-bg: transparent; --ws-card-border: rgba(255,255,255,.15); --ws-text-heading: #ffffff; --ws-text-body: rgba(255,255,255,.82); --ws-text-muted: rgba(255,255,255,.55); --ws-surface-alt: rgba(255,255,255,.05); }'
            . '.ws-wp-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1100px; margin: 0 auto; color: var(--ws-text-body); }'
            . '.ws-wp-msg { color: var(--ws-text-muted); }'
            . '.ws-wp-hero { position: relative; width: 100%; aspect-ratio: 21/9; background-size: cover; background-position: center; border-radius: 12px; overflow: hidden; margin-bottom: -70px; box-shadow: 0 20px 40px -16px rgba(0,0,0,.35); }'
            . '.ws-wp-hero::after { content: \'\'; position: absolute; inset: 0; background: linear-gradient(0deg, rgba(0,0,0,.85) 0%, rgba(0,0,0,.15) 55%, rgba(0,0,0,0) 100%); }'
            . '.ws-wp-hero-inner { position: relative; z-index: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; padding: 100px 40px 30px; }'
            . '.ws-wp-kicker { display: inline-block; align-self: flex-start; background: rgba(255,102,8,.92); color: #fff; font-size: 11.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; padding: 5px 14px; border-radius: 20px; margin-bottom: 14px; }'
            . '.ws-wp-hero-title { font-size: clamp(28px, 4.4vw, 46px); font-weight: 800; color: #fff; margin: 0; text-shadow: 0 2px 14px rgba(0,0,0,.45); letter-spacing: -.01em; }'
            . '.ws-wp-body { position: relative; z-index: 2; background: var(--ws-card-bg); border: 1px solid var(--ws-card-border); border-radius: 12px; padding: 56px 48px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }'
            . '.ws-theme-dark .ws-wp-body { background: #0f1115; box-shadow: none; }'
            . '.ws-wp-no-hero .ws-wp-body { margin-top: 0; }'
            . '.ws-wp-intro { font-size: 18px; line-height: 1.85; color: var(--ws-text-body); white-space: pre-line; }'
            . '.ws-wp-intro::first-letter { font-size: 2.6em; font-weight: 800; color: var(--ws-accent); float: left; line-height: .8; padding-right: 8px; padding-top: 4px; }'
            . '.ws-wp-divider { height: 1px; background: linear-gradient(90deg, var(--ws-card-border), transparent 70%); margin: 52px 0; }'
            . '.ws-wp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; }'
            . '@media (max-width: 720px) { .ws-wp-grid { grid-template-columns: 1fr; } }'
            . '.ws-wp-card { background: var(--ws-surface-alt); border: 1px solid var(--ws-card-border); border-radius: 10px; padding: 28px 26px; transition: transform .15s ease, box-shadow .15s ease; }'
            . '.ws-wp-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -12px rgba(0,0,0,.15); }'
            . '.ws-wp-card-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: rgba(255,102,8,.14); font-size: 18px; margin-bottom: 14px; }'
            . '.ws-wp-card h2 { font-size: 13.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--ws-accent); margin: 0 0 14px; }'
            . '.ws-wp-card .ws-wp-text { font-size: 15px; line-height: 1.75; color: var(--ws-text-body); white-space: pre-line; }'
            . '.ws-wp-note { margin-top: 44px; border: 1px solid rgba(255,102,8,.4); background: rgba(255,102,8,.08); border-radius: 10px; padding: 26px 28px; }'
            . '.ws-wp-note-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: rgba(255,102,8,.18); font-size: 18px; margin-bottom: 14px; }'
            . '.ws-wp-note h2 { font-size: 13.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--ws-accent); margin: 0 0 12px; }'
            . '.ws-wp-note .ws-wp-text { font-size: 15px; line-height: 1.75; color: var(--ws-text-body); white-space: pre-line; }'
            . '.ws-wp-booking { margin-top: 60px; padding-top: 48px; border-top: 1px solid var(--ws-card-border); }'
            . '.ws-wp-booking-title { font-size: 24px; font-weight: 800; color: var(--ws-text-heading); margin: 0 0 30px; text-align: center; }'
        );
    }

    public function render($atts): string {
        $atts = shortcode_atts(['slug' => ''], $atts);
        $slug = sanitize_title($atts['slug']);

        $theme = WSMA_Settings::get('default_theme_mode', 'dark');
        $theme = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';

        $term = $slug ? get_term_by('slug', $slug, 'wsma_categoria_evento') : null;
        if (!$term || is_wp_error($term)) {
            return '<div class="ws-theme-wrapper ws-theme-' . esc_attr($theme) . '"><p class="ws-wp-msg">Categoria workshop non trovata.</p></div>';
        }

        $tid = $term->term_id;
        $ctx = 'wsma_categoria_evento_' . $tid;
        $hero = (string) WSMA_Data::get_field('foto_categoria', $ctx);
        $intro = (string) WSMA_Data::get_field('intro', $ctx);
        $program = (string) WSMA_Data::get_field('program', $ctx);
        $requirements = (string) WSMA_Data::get_field('requirements', $ctx);
        $note = (string) WSMA_Data::get_field('important_notes', $ctx);

        ob_start();
        ?>
        <div class="ws-theme-wrapper ws-theme-<?php echo esc_attr($theme); ?>">
            <div class="ws-wp-wrap<?php echo esc_attr($hero ? '' : ' ws-wp-no-hero'); ?>">
                <?php if ($hero): ?>
                    <div class="ws-wp-hero" style="background-image:url('<?php echo esc_url($hero); ?>')">
                        <div class="ws-wp-hero-inner">
                            <span class="ws-wp-kicker">Workshop</span>
                            <h1 class="ws-wp-hero-title"><?php echo esc_html($term->name); ?></h1>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="ws-wp-body">
                    <?php if (!$hero): ?>
                        <h1 class="ws-wp-hero-title" style="color: var(--ws-text-heading); text-shadow: none; margin-bottom: 20px;"><?php echo esc_html($term->name); ?></h1>
                    <?php endif; ?>

                    <?php if ($intro): ?>
                        <div class="ws-wp-intro"><?php echo esc_html($intro); ?></div>
                    <?php endif; ?>

                    <?php if ($intro && ($program || $requirements || $note)): ?><div class="ws-wp-divider"></div><?php endif; ?>

                    <?php if ($program || $requirements): ?>
                        <div class="ws-wp-grid">
                            <?php if ($program): ?>
                                <div class="ws-wp-card">
                                    <span class="ws-wp-card-icon">📸</span>
                                    <h2>Programma</h2>
                                    <div class="ws-wp-text"><?php echo esc_html($program); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($requirements): ?>
                                <div class="ws-wp-card">
                                    <span class="ws-wp-card-icon">✅</span>
                                    <h2>Requisiti</h2>
                                    <div class="ws-wp-text"><?php echo esc_html($requirements); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($note): ?>
                        <div class="ws-wp-note">
                            <span class="ws-wp-note-icon">⚠️</span>
                            <h2>Note importanti</h2>
                            <div class="ws-wp-text"><?php echo esc_html($note); ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="ws-wp-booking">
                        <?php echo do_shortcode('[eventi_categoria slug="' . esc_attr($term->slug) . '" theme="light"]'); ?>
                        <h3 class="ws-wp-booking-title">Prenota il tuo posto</h3>
                        <?php echo do_shortcode('[wsma_form_iscrizione categoria="' . esc_attr($term->slug) . '"]'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
