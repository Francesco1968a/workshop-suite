<?php

if (!defined('ABSPATH')) exit;

/**
 * Ported from legacy snippet 20 "Integrazione Fluent Forms → CRM Workshop"
 * (originally "SNIPPET 11" internally, unrelated numbering). This is the
 * live production data-entry path: it injects the events-of-this-category
 * JS data + the visible date-select UI on category pages, and creates
 * partecipante/iscrizione posts from Fluent Forms submissions.
 *
 * `wv_find_categoria_by_url()` was DEFINED here in the legacy snippet — now
 * ported to `WSMA_Data::find_categoria_by_url()` (also used by
 * FVW_Shortcode_Prossimo). All other `wv_*` calls replaced with the
 * `WSMA_Data::` equivalents already ported this session.
 *
 * Mail #1 (Risposta) on submission is sent via WSMA_Mail_Templates
 * (ported from legacy snippet 19), which also owns the category-level
 * template fields and per-iscrizione tracking fields.
 */
final class WSMA_Fluent_Forms_Intake implements WSMA_Module {

    public const FORM_ID = 3;

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('wp_head', [$this, 'inject_events_instant'], 5);
        add_action('wp_footer', [$this, 'inject_visible_select_script']);
        add_action('fluentform/submission_inserted', [$this, 'handle_submission'], 20, 3);
    }

    /** @return array{id:int,label:string}[] */
    public static function eventi_per_categoria_array(int $term_id): array {
        $oggi = current_time('Y-m-d');
        $eq = new WP_Query([
            'post_type' => 'wsma_evento',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'tax_query' => [['taxonomy' => 'wsma_categoria_evento', 'field' => 'term_id', 'terms' => $term_id]],
            'meta_key' => 'data_evento',
            'orderby' => 'meta_value', 'order' => 'ASC',
            'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']],
        ]);
        $out = [];
        while ($eq->have_posts()) {
            $eq->the_post();
            $id = get_the_ID();
            $s = WSMA_Data::stato_posti($id);
            $label = WSMA_Data::format_periodo($id);
            if ($s['sold_out']) {
                $label .= ' — Sold Out';
            } else {
                $label .= ' (' . $s['disponibili'] . ' posti liberi)';
            }
            $out[] = ['id' => $id, 'label' => $label];
        }
        wp_reset_postdata();
        return $out;
    }

    /**
     * Pre-inietta gli eventi della pagina corrente come variabili JS
     * (server-side) + nome categoria + periodo "pulito" per ogni evento
     * (usato dal modal header).
     */
    public function inject_events_instant(): void {
        if (is_admin()) return;
        global $post;
        if (!$post) return;

        $url = get_permalink($post->ID);
        $term = WSMA_Data::find_categoria_by_url($url);
        if (!$term) return;

        $eventi = self::eventi_per_categoria_array($term->term_id);

        // Arricchisci ogni evento con il "periodo pulito" (senza info posti/sold out)
        foreach ($eventi as &$ev) {
            $ev['periodo'] = WSMA_Data::format_periodo($ev['id']);
        }
        unset($ev);

        WSMA_Data::enqueue_inline_script(
            'window.WV_EVENTS = ' . wp_json_encode($eventi, JSON_HEX_TAG | JSON_HEX_AMP) . ';'
            . 'window.WV_CATEGORY = ' . wp_json_encode($term->name, JSON_HEX_TAG | JSON_HEX_AMP) . ';'
        );
    }

    /** JS nel footer: crea select visibile prima del hidden field e copia il valore. */
    public function inject_visible_select_script(): void {
        if (is_admin()) return;

        WSMA_Data::enqueue_inline_script(<<<'JS'
        (function(){
          function init(){
            if (typeof window.WV_EVENTS === 'undefined') return; // non è una pagina categoria
            var hidden = document.querySelector('input[type="hidden"][name="evento_scelto"]');
            if (!hidden) return;
            if (document.getElementById('wv-evento-visible')) return;

            var events = window.WV_EVENTS || [];

            // Wrapper che mimica la struttura standard Fluent
            var wrapper = document.createElement('div');
            wrapper.className = 'ff-el-group';
            wrapper.id = 'wv-evento-group';
            wrapper.innerHTML =
              '<div class="ff-el-input--label ff-el-is-required ff-el-lbl--top">' +
                '<label>Data di interesse <span style="color:#888;">*</span></label>' +
              '</div>' +
              '<div class="ff-el-input--content">' +
                '<select id="wv-evento-visible" class="ff-el-form-control" required></select>' +
              '</div>';

            var sel = wrapper.querySelector('#wv-evento-visible');
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = events.length ? '— Seleziona una data —' : '— Nessuna data disponibile —';
            sel.appendChild(placeholder);

            events.forEach(function(ev){
              var o = document.createElement('option');
              o.value = ev.id;
              o.textContent = ev.label;
              sel.appendChild(o);
            });

            // Inserisci wrapper subito prima del .ff-el-group del hidden field
            var hiddenGroup = hidden.closest('.ff-el-group') || hidden.parentNode;
            if (hiddenGroup && hiddenGroup.parentNode) {
              hiddenGroup.parentNode.insertBefore(wrapper, hiddenGroup);
            }

            // Copia il valore selezionato nel hidden field
            sel.addEventListener('change', function(){
              hidden.value = sel.value;
            });

            // Validazione client-side al submit
            var form = hidden.closest('form');
            if (form) {
              form.addEventListener('submit', function(e){
                if (!sel.value) {
                  e.preventDefault();
                  e.stopPropagation();
                  sel.focus();
                  sel.style.borderBottomColor = '#ff5a1f';
                  setTimeout(function(){ sel.style.borderBottomColor = ''; }, 2500);
                }
              }, true);
            }
          }
          if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
          } else {
            init();
          }
        })();
        JS
        );
    }

    /** Hook submission form Fluent → crea iscrizione + manda Mail #1. */
    public function handle_submission($entryId, $formData, $form): void {
        $form_id = is_object($form) ? (int)($form->id ?? 0) : (int)$form;
        if ($form_id !== self::FORM_ID) return;

        $evento_id = (int) ($formData['evento_scelto'] ?? 0);
        if (!$evento_id || get_post_type($evento_id) !== 'wsma_evento') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated behind WP_DEBUG above; not run in production.
                error_log("[WV CRM] Submission #{$entryId}: evento_scelto non valido → skip.");
            }
            return;
        }
        if (WSMA_Data::evento_concluso($evento_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated behind WP_DEBUG above; not run in production.
                error_log("[WV CRM] Submission #{$entryId}: evento {$evento_id} concluso → skip.");
            }
            return;
        }

        $terms = get_the_terms($evento_id, 'wsma_categoria_evento');
        $term = $terms ? $terms[0] : null;
        if (!$term) return;

        $name_field = $formData['name_0'] ?? [];
        $first_name = '';
        $last_name  = '';
        if (is_array($name_field)) {
            $first_name = sanitize_text_field($name_field['first_name'] ?? '');
            $last_name  = sanitize_text_field($name_field['last_name'] ?? '');
        } else {
            $parts = explode(' ', sanitize_text_field((string) $name_field), 2);
            $first_name = $parts[0] ?? '';
            $last_name  = $parts[1] ?? '';
        }
        $email       = sanitize_email($formData['email_1'] ?? '');
        $phone       = sanitize_text_field($formData['number_7'] ?? '');
        $message     = sanitize_textarea_field($formData['textarea_2'] ?? '');
        $citta       = sanitize_text_field($formData['citta_provenienza'] ?? '');
        $num_persone = max(1, (int) ($formData['numero_persone'] ?? 1)); // campo FF opzionale

        if (!$email || !$first_name) return;

        $titolo = trim($first_name . ' ' . $last_name);

        $pid = WSMA_Data::find_partecipante_by_email($email);
        if (!$pid) {
            $pid = wp_insert_post(['post_type'=>'wsma_partecipante','post_status'=>'publish','post_title'=>$titolo]);
            if (!$pid || is_wp_error($pid)) return;
        }
        WSMA_Data::update_field('nome', $first_name, $pid);
        WSMA_Data::update_field('cognome', $last_name, $pid);
        WSMA_Data::update_field('telefono', $phone, $pid);
        WSMA_Data::update_field('email', $email, $pid);
        if ($citta) WSMA_Data::update_field('citta', $citta, $pid);
        wp_update_post(['ID' => $pid, 'post_title' => $titolo]);

        $existing_isc = WSMA_Data::find_iscrizione($pid, $evento_id);
        if ($existing_isc) {
            $old_msg = (string) WSMA_Data::get_field('messaggio_originale', $existing_isc);
            $stamp   = current_time('d/m/Y H:i');
            $new_msg = $old_msg ? ($old_msg . "\n\n— [{$stamp}] Nuovo messaggio dal form —\n" . $message) : $message;
            WSMA_Data::update_field('messaggio_originale', $new_msg, $existing_isc);
            WSMA_Data::update_field('note', $new_msg, $existing_isc);
            return;
        }

        $isc = wp_insert_post([
            'post_type'   => 'wsma_iscrizione',
            'post_status' => 'publish',
            'post_title'  => $titolo . ' → ' . WSMA_Data::evento_label($evento_id),
        ]);
        if (!$isc || is_wp_error($isc)) return;

        WSMA_Data::update_field('partecipante', $pid, $isc);
        WSMA_Data::update_field('evento', $evento_id, $isc);
        WSMA_Data::update_field('stato', 'richiesta', $isc);
        WSMA_Data::update_field('anticipo', 0, $isc);
        WSMA_Data::update_field('saldo', 0, $isc);
        WSMA_Data::update_field('messaggio_originale', $message, $isc);
        WSMA_Data::update_field('note', $message, $isc);
        update_post_meta($isc, 'num_persone', $num_persone);

        WSMA_Mail_Templates::send_template_email($isc, 'mail_risposta');
    }
}
