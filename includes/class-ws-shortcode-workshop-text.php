<?php

if (!defined('ABSPATH')) exit;

/**
 * [wsma_workshop_text slug="..." field="..."] — a single parameterized
 * shortcode for pulling one categoria field into a hand-built page,
 * instead of the full [wsma_workshop_page]. One field, one source of
 * truth (the categoria form) — editing it there updates every page that
 * embeds it, whether via [wsma_workshop_page] or placed individually here.
 * Deliberately one shortcode with a `field` attribute rather than a
 * separate tag per field, to keep the shortcode reference list from
 * growing for what's really one capability.
 *
 * `field` is a stable English token even where the underlying term-meta
 * key is still Italian (citta/nazione/indirizzo, kept as-is since they're
 * shared with WSMA_Hub_Sync's evento-level geo fields — renaming those has
 * a much bigger blast radius than the categoria-only text fields) — the
 * generalist-plugin API surface stays English regardless of storage.
 */
final class WSMA_Shortcode_Workshop_Text implements WSMA_Module {

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
        add_shortcode('wsma_workshop_text', [$this, 'render']);
    }

    public function render($atts): string {
        $atts = shortcode_atts(['slug' => '', 'categoria' => '', 'field' => 'intro'], $atts);
        $field = isset(self::FIELD_MAP[$atts['field']]) ? $atts['field'] : 'intro';
        $meta_key = self::FIELD_MAP[$field];

        // 'slug' and 'categoria' are accepted as aliases (matches
        // [wsma_form_iscrizione]'s own attribute), and when neither is given
        // the categoria is auto-detected from the current page — same
        // resolution logic already used by [wsma_prezzo]/[workshop_prossimo],
        // so this shortcode works standalone inside a categoria page
        // without having to repeat the slug by hand.
        $explicit_slug = sanitize_title($atts['slug'] ?: $atts['categoria']);
        $term = null;
        if ($explicit_slug) {
            $term = get_term_by('slug', $explicit_slug, 'wsma_categoria_evento');
        } else {
            global $post;
            if ($post) {
                $term = WSMA_Data::find_categoria_by_url(get_permalink($post->ID));
            }
        }
        if (!$term || is_wp_error($term)) return '';

        $value = (string) WSMA_Data::get_field($meta_key, 'categoria_evento_' . $term->term_id);
        if (!$value) return '';

        if ($field === 'photo') {
            return '<img src="' . esc_url($value) . '" alt="' . esc_attr($term->name) . '" style="max-width:100%;height:auto;display:block;" loading="lazy" />';
        }

        // wpautop turns blank-line-separated text into real <p> paragraphs
        // (and single line breaks into <br>) — sturdier than a CSS
        // white-space trick for genuinely multi-paragraph notes.
        return '<div class="ws-workshop-text" style="line-height: 1.7;">' . wpautop(esc_html($value)) . '</div>';
    }
}
