<?php

if (!defined('ABSPATH')) exit;

/**
 * Ported from legacy snippet 18 "Workshop CRM 9 - Data in Hero".
 * Shortcode [wsma_workshop_prossimo] — shows the period of the next upcoming
 * evento, optionally scoped to the categoria_evento matching the current
 * page's URL. Verbatim port of the legacy shortcode callback, with
 * `wv_format_periodo()` → `WSMA_Data::format_periodo()` and
 * `wv_find_categoria_by_url()` → `WSMA_Data::find_categoria_by_url()`.
 */
final class WSMA_Shortcode_Prossimo implements WSMA_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('wsma_workshop_prossimo', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_style']);
    }

    /** Enqueued early — render() itself runs too late (during content rendering) to print styles reliably. */
    public function maybe_enqueue_style(): void {
        if (is_admin()) return;
        global $post;
        if (!$post || !has_shortcode((string) $post->post_content, 'wsma_workshop_prossimo')) return;

        WSMA_Data::enqueue_inline_style(
            '.wv-prox-hero{display:block;text-align:center;font-family:"Antony",serif;'
            . 'font-size:clamp(28px,4vw,28px);letter-spacing:.04em;font-weight:400;'
            . 'color:inherit;line-height:1.2;margin:10px 0;}'
        );
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

        $oggi = current_time('Y-m-d');

        // === Determina la categoria target ===
        $term = null;
        if (!empty($atts['slug'])) {
            $term = get_term_by('slug', $atts['slug'], 'wsma_categoria_evento');
        } elseif ($atts['auto'] === 'si') {
            global $post;
            if ($post) {
                $term = WSMA_Data::find_categoria_by_url(get_permalink($post->ID));
            }
        }

        // === Query: con filtro categoria se trovata, altrimenti globale ===
        $query_args = [
            'post_type'      => 'wsma_evento',
            'posts_per_page' => 1,
            'meta_key'       => 'data_evento',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']],
            'no_found_rows'  => true,
        ];
        if ($term) {
            $query_args['tax_query'] = [[
                'taxonomy' => 'wsma_categoria_evento',
                'field'    => 'term_id',
                'terms'    => $term->term_id,
            ]];
        }

        $q = new WP_Query($query_args);
        if (!$q->have_posts()) return esc_html($atts['fallback']);

        $q->the_post();
        $id = get_the_ID();
        $d1 = WSMA_Data::get_field('data_evento', $id);
        $d2 = WSMA_Data::get_field('data_fine', $id);
        if (!$d2) $d2 = $d1;
        $t1 = strtotime($d1); $t2 = strtotime($d2);

        if ($atts['formato'] === 'short') {
            $y = wp_date('y', $t2);
            if ($d1 === $d2) {
                $s = date_i18n('j M', $t1) . "'" . $y;
            } elseif (wp_date('n', $t1) === wp_date('n', $t2) && wp_date('Y', $t1) === wp_date('Y', $t2)) {
                $s = wp_date('j', $t1) . '-' . wp_date('j', $t2) . ' ' . date_i18n('M', $t2) . "'" . $y;
            } else {
                $s = date_i18n('j M', $t1) . ' – ' . date_i18n('j M', $t2) . "'" . $y;
            }
        } elseif ($atts['formato'] === 'long-upper') {
            $y = wp_date('y', $t2);
            if ($d1 === $d2) {
                $s = mb_strtoupper(date_i18n('j F', $t1)) . "'" . $y;
            } elseif (wp_date('n', $t1) === wp_date('n', $t2) && wp_date('Y', $t1) === wp_date('Y', $t2)) {
                $month = mb_strtoupper(date_i18n('F', $t2));
                $s = wp_date('j', $t1) . '-' . wp_date('j', $t2) . ' ' . $month . "'" . $y;
            } else {
                $s = mb_strtoupper(date_i18n('j F', $t1) . ' – ' . date_i18n('j F', $t2)) . "'" . $y;
            }
        } else {
            $s = WSMA_Data::format_periodo($id);
        }

        if ($atts['con_categoria'] === 'si') {
            $terms = get_the_terms($id, 'wsma_categoria_evento');
            if ($terms) $s = esc_html($terms[0]->name) . ' · ' . esc_html($s);
        } else {
            $s = esc_html($s);
        }
        wp_reset_postdata();

        if ($atts['stile'] === 'hero') {
            $s = '<span class="wv-prox-hero">' . $s . '</span>';
        }

        return $s;
    }
}
