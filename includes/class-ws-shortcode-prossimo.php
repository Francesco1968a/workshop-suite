<?php

if (!defined('ABSPATH')) exit;

/**
 * Ported from legacy snippet 18 "Workshop CRM 9 - Data in Hero".
 * Shortcode [workshop_prossimo] — shows the period of the next upcoming
 * evento, optionally scoped to the categoria_evento matching the current
 * page's URL. Verbatim port of the legacy shortcode callback, with
 * `wv_format_periodo()` → `WS_Data::format_periodo()` and
 * `wv_find_categoria_by_url()` → `WS_Data::find_categoria_by_url()`.
 */
final class WS_Shortcode_Prossimo implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('workshop_prossimo', [$this, 'render']);
    }

    public function render($atts) {
        $atts = shortcode_atts([
            'formato'       => 'lungo',   // lungo | short | long-upper
            'con_categoria' => 'no',
            'fallback'      => '',
            'stile'         => '',        // '' | hero
            'slug'          => '',        // forza una categoria specifica
            'auto'          => 'si',      // si | no — auto-rileva categoria dalla pagina corrente
        ], $atts);

        $oggi = date('Y-m-d');

        // === Determina la categoria target ===
        $term = null;
        if (!empty($atts['slug'])) {
            $term = get_term_by('slug', $atts['slug'], 'categoria_evento');
        } elseif ($atts['auto'] === 'si') {
            global $post;
            if ($post) {
                $term = WS_Data::find_categoria_by_url(get_permalink($post->ID));
            }
        }

        // === Query: con filtro categoria se trovata, altrimenti globale ===
        $query_args = [
            'post_type'      => 'evento',
            'posts_per_page' => 1,
            'meta_key'       => 'data_evento',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']],
            'no_found_rows'  => true,
        ];
        if ($term) {
            $query_args['tax_query'] = [[
                'taxonomy' => 'categoria_evento',
                'field'    => 'term_id',
                'terms'    => $term->term_id,
            ]];
        }

        $q = new WP_Query($query_args);
        if (!$q->have_posts()) return esc_html($atts['fallback']);

        $q->the_post();
        $id = get_the_ID();
        $d1 = WS_Data::get_field('data_evento', $id);
        $d2 = WS_Data::get_field('data_fine', $id);
        if (!$d2) $d2 = $d1;
        $t1 = strtotime($d1); $t2 = strtotime($d2);

        if ($atts['formato'] === 'short') {
            $y = date('y', $t2);
            if ($d1 === $d2) {
                $s = date_i18n('j M', $t1) . "'" . $y;
            } elseif (date('n', $t1) === date('n', $t2) && date('Y', $t1) === date('Y', $t2)) {
                $s = date('j', $t1) . '-' . date('j', $t2) . ' ' . date_i18n('M', $t2) . "'" . $y;
            } else {
                $s = date_i18n('j M', $t1) . ' – ' . date_i18n('j M', $t2) . "'" . $y;
            }
        } elseif ($atts['formato'] === 'long-upper') {
            $y = date('y', $t2);
            if ($d1 === $d2) {
                $s = mb_strtoupper(date_i18n('j F', $t1)) . "'" . $y;
            } elseif (date('n', $t1) === date('n', $t2) && date('Y', $t1) === date('Y', $t2)) {
                $month = mb_strtoupper(date_i18n('F', $t2));
                $s = date('j', $t1) . '-' . date('j', $t2) . ' ' . $month . "'" . $y;
            } else {
                $s = mb_strtoupper(date_i18n('j F', $t1) . ' – ' . date_i18n('j F', $t2)) . "'" . $y;
            }
        } else {
            $s = WS_Data::format_periodo($id);
        }

        if ($atts['con_categoria'] === 'si') {
            $terms = get_the_terms($id, 'categoria_evento');
            if ($terms) $s = $terms[0]->name . ' · ' . $s;
        }
        wp_reset_postdata();

        if ($atts['stile'] === 'hero') {
            static $css_done = false;
            $css = '';
            if (!$css_done) {
                $css = '<style>.wv-prox-hero{display:block;text-align:center;font-family:"Antony",serif;'
                     . 'font-size:clamp(28px,4vw,28px);letter-spacing:.04em;font-weight:400;'
                     . 'color:inherit;line-height:1.2;margin:10px 0;}</style>';
                $css_done = true;
            }
            $s = $css . '<span class="wv-prox-hero">' . esc_html($s) . '</span>';
        }

        return $s;
    }
}
