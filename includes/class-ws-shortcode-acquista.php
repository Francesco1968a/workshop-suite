<?php

if (!defined('ABSPATH')) exit;

/**
 * [ws_acquista slug="..."] — single frontend "buy" entry point that core
 * owns but renders nothing on its own: it resolves the categoria (same
 * slug-or-auto-detect logic as [ws_form_iscrizione]/[ws_prezzo]), lists
 * upcoming eventi in it, and for each one asks `wsma_acquista_render`
 * (filter, priority-ordered) whether any PRO connector can sell it. This
 * keeps core unaware WooCommerce or Stripe exist — same convention as
 * wsma_evento_admin_wc_fields / wsma_confirmation_placeholders.
 *
 * Priority rule (mirrors the confirmation-email suppression logic):
 * WooCommerce (priority 5) is asked first — if that evento has a linked
 * product, its "Acquista" link wins. Stripe (priority 10) only runs if
 * WooCommerce didn't already claim the evento, so the two connectors can
 * both be active without ever offering two payment paths for one event.
 */
final class WSMA_Shortcode_Acquista implements WSMA_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('wsma_acquista', [$this, 'render']);
        add_shortcode('ws_acquista', [$this, 'render']); // legacy alias, existing page content
    }

    public function render($atts): string {
        $atts = shortcode_atts(['slug' => ''], is_array($atts) ? $atts : []);

        $term = null;
        if (!empty($atts['slug'])) {
            $term = get_term_by('slug', $atts['slug'], 'wsma_categoria_evento');
            if (is_wp_error($term)) $term = null;
        } else {
            global $post;
            if ($post) $term = WSMA_Data::find_categoria_by_url(get_permalink($post->ID));
        }
        if (!$term) return '';

        $eventi = class_exists('WSMA_Fluent_Forms_Intake') ? WSMA_Fluent_Forms_Intake::eventi_per_categoria_array($term->term_id) : [];

        $righe = [];
        foreach ($eventi as $ev) {
            $html = apply_filters('wsma_acquista_render', '', $ev['id']);
            if ($html) $righe[] = ['label' => $ev['label'], 'html' => $html];
        }
        if (!$righe) return '';

        // Same CSS custom properties (light/dark) as [ws_form_iscrizione] —
        // the "ws-form-iscrizione" stylesheet is always registered by that
        // module regardless of whether it's actually on the page, so it's
        // safe to enqueue it here too even standalone.
        wp_enqueue_style('ws-form-iscrizione');
        $theme = WSMA_Settings::get('default_theme_mode', 'dark');

        if (count($righe) === 1) {
            return '<div class="ws-theme-wrapper ws-theme-' . esc_attr($theme) . '">' . wp_kses_post($righe[0]['html']) . '</div>';
        }

        ob_start(); ?>
        <div class="ws-theme-wrapper ws-theme-<?php echo esc_attr($theme); ?>">
            <div class="ws-acquista-multi">
                <?php foreach ($righe as $r): ?>
                    <div style="margin-bottom:14px;">
                        <div style="font-size:13px;opacity:.7;margin-bottom:6px;"><?php echo esc_html($r['label']); ?></div>
                        <?php echo wp_kses_post($r['html']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
