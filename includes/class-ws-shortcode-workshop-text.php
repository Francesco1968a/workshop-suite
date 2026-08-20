<?php

if (!defined('ABSPATH')) exit;

/**
 * [ws_workshop_text slug="..." field="..."] — a single parameterized
 * shortcode for pulling one categoria field into a hand-built page,
 * instead of the full [ws_workshop_page]. One field, one source of
 * truth (the categoria form) — editing it there updates every page that
 * embeds it, whether via [ws_workshop_page] or placed individually here.
 * Deliberately one shortcode with a `field` attribute rather than a
 * separate tag per field, to keep the shortcode reference list from
 * growing for what's really one capability.
 *
 * `field` is a stable English token even where the underlying term-meta
 * key is still Italian (citta/nazione/indirizzo, kept as-is since they're
 * shared with WS_Hub_Sync's evento-level geo fields — renaming those has
 * a much bigger blast radius than the categoria-only text fields) — the
 * generalist-plugin API surface stays English regardless of storage.
 */
final class WS_Shortcode_Workshop_Text implements WS_Module {

    private const FIELD_MAP = [
        'intro'           => 'intro',
        'program'         => 'program',
        'requirements'    => 'requirements',
        'important_notes' => 'important_notes',
        'city'            => 'citta',
        'country'         => 'nazione',
        'address'         => 'indirizzo',
        'photo'           => 'foto_categoria',
    ];

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('ws_workshop_text', [$this, 'render']);
    }

    public function render($atts): string {
        $atts = shortcode_atts(['slug' => '', 'field' => 'intro'], $atts);
        $slug = sanitize_title($atts['slug']);
        $field = isset(self::FIELD_MAP[$atts['field']]) ? $atts['field'] : 'intro';
        $meta_key = self::FIELD_MAP[$field];

        $term = $slug ? get_term_by('slug', $slug, 'categoria_evento') : null;
        if (!$term || is_wp_error($term)) return '';

        $value = (string) WS_Data::get_field($meta_key, 'categoria_evento_' . $term->term_id);
        if (!$value) return '';

        if ($field === 'photo') {
            return '<img src="' . esc_url($value) . '" alt="' . esc_attr($term->name) . '" style="max-width:100%;height:auto;display:block;" loading="lazy" />';
        }

        return '<div class="ws-workshop-text" style="white-space: pre-line; line-height: 1.7;">' . esc_html($value) . '</div>';
    }
}
