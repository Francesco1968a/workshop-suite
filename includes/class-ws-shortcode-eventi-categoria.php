<?php

if (!defined('ABSPATH')) exit;

/**
 * Ported from legacy snippet 12 "Workshop CRM 3 Eventi Categoria".
 * Shortcode [wsma_eventi_categoria slug="..." max_width="1100"] — grid of
 * upcoming eventi for a given categoria_evento. Verbatim port with
 * `wv_format_periodo()` / `wv_stato_posti()` → `WSMA_Data::` equivalents.
 * Excludes eventi flagged `nascondi_dal_frontend` (same ACF meta key
 * already read/written by WSMA_Rest_Admin).
 */
final class WSMA_Shortcode_Eventi_Categoria implements WSMA_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('wsma_eventi_categoria', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_style']);
    }

    /**
     * A single static stylesheet, enqueued early (before wp_head's print
     * point — render() itself runs too late for that, during content
     * rendering) — per-instance values (max-width, light/dark palette)
     * are no longer interpolated into the CSS text itself; they're passed
     * as CSS custom properties on each instance's own wrapper element
     * instead (see render()), so one shared, static stylesheet covers
     * every shortcode instance on the page regardless of its attributes.
     */
    public function maybe_enqueue_style(): void {
        if (is_admin()) return;
        global $post;
        if (!$post || !has_shortcode((string) $post->post_content, 'wsma_eventi_categoria')) return;

        WSMA_Data::enqueue_inline_style(
            '.wv-empty-msg{text-align:center;color:#888;}'
            . '.wv-eventi-wrap { max-width:var(--wv-max-w,1100px); margin:30px auto; padding:0 16px; box-sizing:border-box; }'
            . '.wv-grid { display:flex; flex-wrap:wrap; gap:32px; justify-content:center; }'
            . '.wv-grid .wv-evcard { flex:0 1 calc(25% - 24px); min-width:200px; max-width:240px;'
            . 'border:1px solid var(--wv-card-border); background:var(--wv-card-bg);'
            . 'display:flex; flex-direction:column; padding:14px; box-sizing:border-box;'
            . 'cursor:pointer; transition:border-color .2s, transform .2s, background .2s; }'
            . '.wv-grid .wv-evcard:hover { border-color:var(--wv-card-border-hov); background:var(--wv-card-bg-hov); transform:translateY(-2px); }'
            . '.wv-grid .wv-evfoto { width:100%; aspect-ratio:4/3; background-size:cover; background-position:center; background-color:var(--wv-foto-bg); }'
            . '.wv-grid .wv-evbody { padding:14px 4px 6px; text-align:center; }'
            . '.wv-grid .wv-evperiodo { font-size:15px; color:var(--wv-periodo-color); font-weight:600; margin-bottom:10px; line-height:1.3; }'
            . '.wv-grid .wv-evposti { font-size:13px; color:var(--wv-posti-color); line-height:1.5; }'
            . '.wv-grid .wv-evposti strong { color:var(--wv-posti-strong); font-weight:600; }'
            . '.wv-grid .wv-soldout { display:inline-block; margin-top:6px; font-size:10px; letter-spacing:.2em;'
            . 'text-transform:uppercase; color:#e74c3c; border:1px solid #e74c3c; padding:5px 12px; }'
            . '@media (max-width:1100px){ .wv-grid .wv-evcard { flex:0 1 calc(33.33% - 22px); } }'
            . '@media (max-width:800px){ .wv-grid .wv-evcard { flex:0 1 calc(50% - 16px); } }'
            . '@media (max-width:520px){ .wv-grid .wv-evcard { flex:0 1 100%; max-width:280px; } }'
        );
    }

    public function render($atts) {
        $atts = shortcode_atts([
            'slug'      => '',
            'max_width' => '1100',
            // 'dark' (default): white text, matches this site's original
            // design — every real page using this shortcode has a black
            // background. 'light': dark text, for pages with a light/white
            // background instead.
            'theme'     => 'dark',
        ], $atts);
        if (!$atts['slug']) return '<p>Specifica slug categoria.</p>';

        $is_dark = ($atts['theme'] === 'dark');

        $term = get_term_by('slug', $atts['slug'], 'wsma_categoria_evento');
        $foto = $term ? WSMA_Data::get_field('foto_categoria', 'wsma_categoria_evento_' . $term->term_id) : '';

        $oggi = current_time('Y-m-d');
        // phpcs:disable WordPress.DB.SlowDBQuery -- filters/sorts by postmeta
        // on purpose: WordPress has no native "event date" field to query
        // against instead. Deliberately not cached — this renders live seat
        // availability (WSMA_Data::stato_posti() below, incl. "Sold Out"),
        // and stale results here mean a customer either books a seat that's
        // already gone or is wrongly told an event is full. Cost is a
        // non-issue at this site's real scale (tens of eventi per categoria,
        // not thousands); revisit only if that scale changes materially.
        $q = new WP_Query([
            'post_type'      => 'wsma_evento',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'tax_query'      => [['taxonomy' => 'wsma_categoria_evento', 'field' => 'slug', 'terms' => $atts['slug']]],
            'meta_key'       => 'data_evento',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [
                'relation' => 'AND',
                // Solo eventi futuri
                ['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE'],
                // Escludi eventi nascosti dal frontend
                [
                    'relation' => 'OR',
                    ['key' => 'nascondi_dal_frontend', 'compare' => 'NOT EXISTS'],
                    ['key' => 'nascondi_dal_frontend', 'value' => '1', 'compare' => '!='],
                ],
            ],
        ]);
        // phpcs:enable WordPress.DB.SlowDBQuery

        if (!$q->have_posts()) return '<p class="wv-empty-msg">Nessuna data in programma.</p>';

        $max_w = (int) $atts['max_width'];
        if ($max_w < 400) $max_w = 1100;

        // Same layout for both themes; only colors differ.
        if ($is_dark) {
            $card_border      = 'rgba(255,255,255,.15)';
            $card_bg          = 'rgba(255,255,255,.02)';
            $card_border_hov  = 'rgba(255,255,255,.5)';
            $card_bg_hov      = 'rgba(255,255,255,.05)';
            $foto_bg          = '#1a1a1a';
            $periodo_color    = '#fff';
            $posti_color      = '#ddd';
            $posti_strong     = '#fff';
        } else {
            $card_border      = 'rgba(0,0,0,.12)';
            $card_bg          = 'rgba(0,0,0,.015)';
            $card_border_hov  = 'rgba(0,0,0,.35)';
            $card_bg_hov      = 'rgba(0,0,0,.035)';
            $foto_bg          = '#ececec';
            $periodo_color    = '#1a1a1a';
            $posti_color      = '#555';
            $posti_strong     = '#111';
        }

        $wrap_style = sprintf(
            '--wv-max-w:%dpx;--wv-card-border:%s;--wv-card-bg:%s;--wv-card-border-hov:%s;--wv-card-bg-hov:%s;--wv-foto-bg:%s;--wv-periodo-color:%s;--wv-posti-color:%s;--wv-posti-strong:%s;',
            $max_w, $card_border, $card_bg, $card_border_hov, $card_bg_hov, $foto_bg, $periodo_color, $posti_color, $posti_strong
        );

        ob_start(); ?>
        <div class="wv-eventi-wrap" style="<?php echo esc_attr($wrap_style); ?>">
          <div class="wv-grid">
            <?php while ($q->have_posts()): $q->the_post();
              $id = get_the_ID();
              $s = WSMA_Data::stato_posti($id); ?>
              <div class="wv-evcard" data-evento-id="<?php echo esc_attr($id); ?>" role="button" tabindex="0" aria-label="Prenota questa data">
                <?php if ($foto): ?>
                  <div class="wv-evfoto" style="background-image:url('<?php echo esc_url($foto); ?>');"></div>
                <?php else: ?>
                  <div class="wv-evfoto"></div>
                <?php endif; ?>
                <div class="wv-evbody">
                  <div class="wv-evperiodo"><?php echo esc_html(WSMA_Data::format_periodo($id)); ?></div>
                  <?php if ($s['sold_out']): ?>
                    <span class="wv-soldout">Sold Out</span>
                  <?php else: ?>
                    <div class="wv-evposti">Posti: Totali <strong><?php echo (int)$s['totali']; ?></strong> / Disponibili <strong><?php echo (int)$s['disponibili']; ?></strong></div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endwhile; wp_reset_postdata(); ?>
          </div>
        </div>
        <?php
        // Behavior is identical across every instance on the page (multiple
        // categories can appear on one page) — only queue it once.
        static $script_queued = false;
        if (!$script_queued) {
            WSMA_Data::enqueue_inline_script(<<<'JS'
            (function(){
              function preFillAndOpen(eid){
                if (!eid) return;
                window.WV_MODAL_PRESELECT = String(eid);
                var btn = document.querySelector('.fv-ecta-btn');
                if (btn) btn.click();
              }
              document.addEventListener('click', function(e){
                var card = e.target.closest('.wv-evcard[data-evento-id]');
                if (card) preFillAndOpen(card.dataset.eventoId);
              });
              document.addEventListener('keydown', function(e){
                if (e.key !== 'Enter' && e.key !== ' ') return;
                var card = e.target.closest('.wv-evcard[data-evento-id]');
                if (card) {
                  e.preventDefault();
                  preFillAndOpen(card.dataset.eventoId);
                }
              });
            })();
            JS
            );
            $script_queued = true;
        }
        return ob_get_clean();
    }
}
