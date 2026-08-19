<?php

if (!defined('ABSPATH')) exit;

/**
 * Shortcode [ws_prezzo] / [ws_acconto] — stampano il prezzo di listino o
 * l'acconto richiesto di una categoria evento, in formato leggibile.
 * Stessa logica di risoluzione categoria (slug esplicito, altrimenti
 * auto-rilevata dalla pagina corrente) già usata da [ws_form_iscrizione]
 * e [workshop_prossimo].
 */
final class WS_Shortcode_Prezzo implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('ws_prezzo', [$this, 'render_prezzo']);
        add_shortcode('ws_acconto', [$this, 'render_acconto']);
    }

    public function render_prezzo($atts) {
        return $this->render_valore($atts, 'prezzo');
    }

    public function render_acconto($atts) {
        return $this->render_valore($atts, 'acconto');
    }

    private function render_valore($atts, string $campo): string {
        $atts = shortcode_atts([
            'slug'   => '',
            'prefix' => '€ ',
            'raw'    => '0',
        ], is_array($atts) ? $atts : []);

        $term = null;
        if (!empty($atts['slug'])) {
            $term = get_term_by('slug', $atts['slug'], 'categoria_evento');
            if (is_wp_error($term)) $term = null;
        } else {
            global $post;
            if ($post) {
                $term = WS_Data::find_categoria_by_url(get_permalink($post->ID));
            }
        }

        if (!$term) return '';

        $valore = (float) WS_Data::get_field($campo, 'categoria_evento_' . $term->term_id);
        if ($valore <= 0) return '';

        if ((string) $atts['raw'] === '1') {
            return (string) $valore;
        }

        return esc_html($atts['prefix'] . number_format_i18n($valore, 2));
    }
}
