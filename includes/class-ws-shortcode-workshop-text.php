<?php

if (!defined('ABSPATH')) exit;

/**
 * [ws_workshop_text slug="..." field="intro|program|requirements|important_notes"]
 * — a single parameterized shortcode for pulling one categoria text block
 * into a hand-built page, instead of the full [ws_workshop_page]. One
 * field, one source of truth (the categoria form) — editing it there
 * updates every page that embeds it, whether via [ws_workshop_page] or
 * placed individually here. Deliberately one shortcode with a `field`
 * attribute rather than four separate tags, to keep the shortcode
 * reference list from growing for what's really one capability.
 */
final class WS_Shortcode_Workshop_Text implements WS_Module {

    private const FIELDS = ['intro', 'program', 'requirements', 'important_notes'];

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('ws_workshop_text', [$this, 'render']);
    }

    public function render($atts): string {
        $atts = shortcode_atts(['slug' => '', 'field' => 'intro'], $atts);
        $slug = sanitize_title($atts['slug']);
        $field = in_array($atts['field'], self::FIELDS, true) ? $atts['field'] : 'intro';

        $term = $slug ? get_term_by('slug', $slug, 'categoria_evento') : null;
        if (!$term || is_wp_error($term)) return '';

        $text = (string) WS_Data::get_field($field, 'categoria_evento_' . $term->term_id);
        if (!$text) return '';

        return '<div class="ws-workshop-text" style="white-space: pre-line; line-height: 1.7;">' . esc_html($text) . '</div>';
    }
}
