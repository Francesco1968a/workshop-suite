<?php

if (!defined('ABSPATH')) exit;

/**
 * Ported from legacy snippet 17 "Programma Workshop totali".
 * Shortcode [workshop_programma colonne="2"] — global grid of every
 * upcoming evento across all categorie. Verbatim port with
 * `wv_format_periodo()` / `wv_stato_posti()` → `WSMA_Data::` equivalents.
 */
final class WSMA_Shortcode_Programma implements WSMA_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('workshop_programma', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_style']);
    }

    /**
     * Static, shared stylesheet enqueued early — per-instance sizing
     * (columns count → gap/card width) is passed via CSS custom
     * properties on each instance's own wrapper (see render()) instead of
     * being interpolated into the CSS text, so one early-enqueued
     * stylesheet covers every [workshop_programma] on the page regardless
     * of its `colonne` attribute.
     */
    public function maybe_enqueue_style(): void {
        if (is_admin()) return;
        global $post;
        if (!$post || !has_shortcode((string) $post->post_content, 'workshop_programma')) return;

        WSMA_Data::enqueue_inline_style(
            '.wv-prog-empty-msg{text-align:center;color:#888;padding:30px 0;}'
            . '.wv-prog { max-width:1100px; margin:0 auto; }'
            . '.wv-prog a, .wv-prog a:visited, .wv-prog a:hover { text-decoration:none !important; }'
            . '.wv-prog-grid { display:flex; flex-wrap:wrap; gap:var(--wv-prog-gap,14px); margin:24px 0; justify-content:center; }'
            . '.wv-prog-card { flex:0 1 var(--wv-prog-width,calc(50% - 7px)); max-width:520px;'
            . 'display:flex; align-items:stretch;'
            . 'border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.02);'
            . 'color:#fff !important; transition:.25s; min-height:120px; }'
            . '.wv-prog-card:hover { border-color:rgba(255,255,255,.45); }'
            . '.wv-prog-foto { flex:0 0 140px; background-size:cover; background-position:center; background-color:#1a1a1a;'
            . 'filter:grayscale(.2); opacity:.92; transition:.3s; }'
            . '.wv-prog-card:hover .wv-prog-foto { filter:none; opacity:1; }'
            . '.wv-prog-body { flex:1; padding:14px 16px; display:flex; flex-direction:column; justify-content:center; gap:4px; }'
            . '.wv-prog-cat { font-size:13px; text-transform:uppercase; letter-spacing:.18em; color:#FF6608; font-weight:600; line-height:1.2; }'
            . '.wv-prog-periodo { font-size:16px; font-weight:600; color:#fff; line-height:1.3; }'
            . '.wv-prog-posti { font-size:12px; color:#bbb; margin-top:2px; }'
            . '.wv-prog-posti strong { color:#fff; font-weight:600; }'
            . '.wv-prog-soldout { display:inline-block; margin-top:4px; font-size:10px; letter-spacing:.2em;'
            . 'text-transform:uppercase; color:#ff6b6b; border:1px solid #ff6b6b; padding:3px 9px; align-self:flex-start; }'
            . '@media (max-width:980px){ .wv-prog-card { flex:0 1 calc(50% - 7px); } }'
            . '@media (max-width:640px){ .wv-prog-card { flex:0 1 100%; } .wv-prog-foto { flex:0 0 110px; } }'
        );
    }

    public function render($atts) {
        $atts = shortcode_atts([
            'colonne' => '2',   // numero di card per riga (1, 2, 3, 4)
        ], $atts);
        $cols = max(1, min(4, (int) $atts['colonne']));

        $oggi = current_time('Y-m-d');
        $q = new WP_Query([
            'post_type' => 'wsma_evento', 'posts_per_page' => -1, 'no_found_rows' => true,
            'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'ASC',
            'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']],
        ]);

        if (!$q->have_posts()) {
            return '<p class="wv-prog-empty-msg">Nessun evento in programma al momento.</p>';
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
        $wrap_style = sprintf('--wv-prog-gap:%dpx;--wv-prog-width:%s;', $gap, $width);

        ob_start(); ?>
        <div class="wv-prog" style="<?php echo esc_attr($wrap_style); ?>">
          <div class="wv-prog-grid">
            <?php while ($q->have_posts()): $q->the_post();
              $id = get_the_ID();
              $s = WSMA_Data::stato_posti($id);
              $terms = get_the_terms($id, 'wsma_categoria_evento');
              $cat_name = $terms ? $terms[0]->name : '';
              $foto = $terms ? WSMA_Data::get_field('foto_categoria', 'wsma_categoria_evento_' . $terms[0]->term_id) : '';
              $cat_url = $terms ? WSMA_Data::get_field('url_pagina', 'wsma_categoria_evento_' . $terms[0]->term_id) : '';

              $card_html  = '<div class="wv-prog-foto"' . ($foto ? ' style="background-image:url(\'' . esc_url($foto) . '\');"' : '') . '></div>';
              $card_html .= '<div class="wv-prog-body">';
              if ($cat_name) $card_html .= '<div class="wv-prog-cat">' . esc_html($cat_name) . '</div>';
              $card_html .= '<div class="wv-prog-periodo">' . esc_html(WSMA_Data::format_periodo($id)) . '</div>';
              if ($s['sold_out']) {
                  $card_html .= '<span class="wv-prog-soldout">Sold Out</span>';
              } else {
                  $card_html .= '<div class="wv-prog-posti">Posti: Totali <strong>' . (int)$s['totali'] . '</strong> / Disponibili <strong>' . (int)$s['disponibili'] . '</strong></div>';
              }
              $card_html .= '</div>';

              if ($cat_url) {
                  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $card_html is assembled above from already-escaped/cast components (esc_html, esc_url, (int) casts) plus static markup.
                  echo '<a class="wv-prog-card" href="' . esc_url($cat_url) . '">' . $card_html . '</a>';
              } else {
                  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $card_html is assembled above from already-escaped/cast components (esc_html, esc_url, (int) casts) plus static markup.
                  echo '<div class="wv-prog-card">' . $card_html . '</div>';
              }
            endwhile; wp_reset_postdata(); ?>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
