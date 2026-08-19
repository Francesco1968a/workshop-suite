<?php

if (!defined('ABSPATH')) exit;

/**
 * Ported from legacy snippet 17 "Programma Workshop totali".
 * Shortcode [workshop_programma colonne="2"] — global grid of every
 * upcoming evento across all categorie. Verbatim port with
 * `wv_format_periodo()` / `wv_stato_posti()` → `WS_Data::` equivalents.
 */
final class WS_Shortcode_Programma implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('workshop_programma', [$this, 'render']);
    }

    public function render($atts) {
        $atts = shortcode_atts([
            'colonne' => '2',   // numero di card per riga (1, 2, 3, 4)
        ], $atts);
        $cols = max(1, min(4, (int) $atts['colonne']));

        $oggi = date('Y-m-d');
        $q = new WP_Query([
            'post_type' => 'evento', 'posts_per_page' => -1,
            'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'ASC',
            'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']],
        ]);

        if (!$q->have_posts()) {
            return '<style>.wv-prog-empty-msg{text-align:center;color:#888;padding:30px 0;}</style><p class="wv-prog-empty-msg">Nessun evento in programma al momento.</p>';
        }

        $gap = 14;
        // Larghezze per ogni numero di colonne (con gap incluso)
        $widths = [
            1 => '100%',
            2 => 'calc(50% - ' . ($gap/2) . 'px)',
            3 => 'calc(33.333% - ' . round($gap*2/3, 2) . 'px)',
            4 => 'calc(25% - ' . round($gap*3/4, 2) . 'px)',
        ];
        $width = $widths[$cols];

        ob_start(); ?>
        <style>
          .wv-prog { max-width:1100px; margin:0 auto; }
          .wv-prog a, .wv-prog a:visited, .wv-prog a:hover { text-decoration:none !important; }
          .wv-prog-grid { display:flex; flex-wrap:wrap; gap:<?php echo $gap; ?>px; margin:24px 0; justify-content:center; }
          .wv-prog-card { flex:0 1 <?php echo $width; ?>; max-width:520px;
            display:flex; align-items:stretch;
            border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.02);
            color:#fff !important; transition:.25s; min-height:120px; }
          .wv-prog-card:hover { border-color:rgba(255,255,255,.45); }
          .wv-prog-foto { flex:0 0 140px; background-size:cover; background-position:center; background-color:#1a1a1a;
            filter:grayscale(.2); opacity:.92; transition:.3s; }
          .wv-prog-card:hover .wv-prog-foto { filter:none; opacity:1; }
          .wv-prog-body { flex:1; padding:14px 16px; display:flex; flex-direction:column; justify-content:center; gap:4px; }
          .wv-prog-cat { font-size:13px; text-transform:uppercase; letter-spacing:.18em; color:#FF6608; font-weight:600; line-height:1.2; }
          .wv-prog-periodo { font-size:16px; font-weight:600; color:#fff; line-height:1.3; }
          .wv-prog-posti { font-size:12px; color:#bbb; margin-top:2px; }
          .wv-prog-posti strong { color:#fff; font-weight:600; }
          .wv-prog-soldout { display:inline-block; margin-top:4px; font-size:10px; letter-spacing:.2em;
            text-transform:uppercase; color:#ff6b6b; border:1px solid #ff6b6b; padding:3px 9px; align-self:flex-start; }
          @media (max-width:980px){ .wv-prog-card { flex:0 1 calc(50% - <?php echo $gap/2; ?>px); } }
          @media (max-width:640px){ .wv-prog-card { flex:0 1 100%; } .wv-prog-foto { flex:0 0 110px; } }
        </style>
        <div class="wv-prog">
          <div class="wv-prog-grid">
            <?php while ($q->have_posts()): $q->the_post();
              $id = get_the_ID();
              $s = WS_Data::stato_posti($id);
              $terms = get_the_terms($id, 'categoria_evento');
              $cat_name = $terms ? $terms[0]->name : '';
              $foto = $terms ? WS_Data::get_field('foto_categoria', 'categoria_evento_' . $terms[0]->term_id) : '';
              $cat_url = $terms ? WS_Data::get_field('url_pagina', 'categoria_evento_' . $terms[0]->term_id) : '';

              $card_html  = '<div class="wv-prog-foto"' . ($foto ? ' style="background-image:url(\'' . esc_url($foto) . '\');"' : '') . '></div>';
              $card_html .= '<div class="wv-prog-body">';
              if ($cat_name) $card_html .= '<div class="wv-prog-cat">' . esc_html($cat_name) . '</div>';
              $card_html .= '<div class="wv-prog-periodo">' . esc_html(WS_Data::format_periodo($id)) . '</div>';
              if ($s['sold_out']) {
                  $card_html .= '<span class="wv-prog-soldout">Sold Out</span>';
              } else {
                  $card_html .= '<div class="wv-prog-posti">Posti: Totali <strong>' . (int)$s['totali'] . '</strong> / Disponibili <strong>' . (int)$s['disponibili'] . '</strong></div>';
              }
              $card_html .= '</div>';

              if ($cat_url) {
                  echo '<a class="wv-prog-card" href="' . esc_url($cat_url) . '">' . $card_html . '</a>';
              } else {
                  echo '<div class="wv-prog-card">' . $card_html . '</div>';
              }
            endwhile; wp_reset_postdata(); ?>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
