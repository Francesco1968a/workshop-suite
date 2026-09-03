<?php

if (!defined('ABSPATH')) exit;

/**
 * REST endpoints backing the Riepilogo cockpit + per-event subview.
 * Ports `workshop_riepilogo` 1:1. Confirmation email sending was ported
 * from the legacy `wv_invia_conferma()` once the direct Zoho SMTP channel
 * (WSMA_Mail_Inbox::send_reply()) was verified working, so it too sends
 * from workshop@francescoverolino.com instead of the SES-forced
 * info@francescoverolino.it — see conferma_iscrizione().
 */
final class WSMA_Rest_Riepilogo implements WSMA_Module {

    private const META_THREAD = 'wv_thread';

    /** Valori ammessi per il campo stato_pagamento, indipendente da 'stato'. */
    private const STATI_PAGAMENTO = ['in_attesa', 'acconto_pagato', 'saldato'];

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        $perm = fn() => current_user_can('manage_options') || current_user_can('wsma_access_panel');

        register_rest_route('workshop-suite/v1', '/riepilogo/cockpit', [
            'methods' => 'GET', 'callback' => [$this, 'get_cockpit'], 'permission_callback' => $perm,
        ]);
        register_rest_route('workshop-suite/v1', '/riepilogo/evento/(?P<id>\d+)', [
            'methods' => 'GET', 'callback' => [$this, 'get_evento'], 'permission_callback' => $perm,
        ]);
        register_rest_route('workshop-suite/v1', '/riepilogo/iscrizione/(?P<id>\d+)/conferma', [
            'methods' => 'POST', 'callback' => [$this, 'conferma_iscrizione'], 'permission_callback' => $perm,
        ]);
        register_rest_route('workshop-suite/v1', '/riepilogo/iscrizione/(?P<id>\d+)/stato-pagamento', [
            'methods' => 'POST', 'callback' => [$this, 'aggiorna_stato_pagamento'], 'permission_callback' => $perm,
        ]);
        register_rest_route('workshop-suite/v1', '/riepilogo/iscrizione/(?P<id>\d+)/nota', [
            'methods' => 'POST', 'callback' => [$this, 'save_nota'], 'permission_callback' => $perm,
        ]);
        register_rest_route('workshop-suite/v1', '/riepilogo/iscrizione/(?P<id>\d+)', [
            'methods' => 'DELETE', 'callback' => [$this, 'delete_iscrizione'], 'permission_callback' => $perm,
        ]);
    }

    private function urls(): array {
        return [
            'contatti'   => home_url('/admin-contatti/'),
            'ai'         => home_url('/admin-ai/'),
        ];
    }

    public function get_cockpit(WP_REST_Request $request): WP_REST_Response {
        $mostra_tutti = (bool) $request->get_param('tutti');
        $oggi = current_time('Y-m-d');

        $args = [
            'post_type'      => 'wsma_evento',
            'posts_per_page' => -1,
            'meta_key'       => 'data_evento',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ];
        if (!$mostra_tutti) {
            $args['meta_query'] = [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']];
        }
        $args = apply_filters('wsma_scope_query_args', $args, 'wsma_evento');
        $eq = new WP_Query($args);

        if (!empty($eq->posts)) {
            update_postmeta_cache(wp_list_pluck($eq->posts, 'ID'));
        }

        $tot_confermati = 0; $tot_richieste = 0; $tot_posti = 0;
        $eventi = [];
        while ($eq->have_posts()) {
            $eq->the_post();
            $id = get_the_ID();
            $s = WSMA_Data::stato_posti($id);
            $tot_confermati += $s['occupati'];
            $tot_richieste  += WSMA_Data::count_richieste($id);
            $tot_posti      += $s['totali'];

            $terms = get_the_terms($id, 'wsma_categoria_evento');
            $cat_name = $terms ? $terms[0]->name : '—';
            $foto = $terms ? WSMA_Data::get_field('foto_categoria', 'wsma_categoria_evento_' . $terms[0]->term_id) : '';
            $n_richieste = WSMA_Data::count_richieste($id);

            $eventi[] = [
                'id' => $id,
                'cat_name' => $cat_name,
                'foto' => $foto ?: '',
                'periodo' => WSMA_Data::format_periodo($id),
                'occupati' => $s['occupati'],
                'totali' => $s['totali'],
                'sold_out' => $s['sold_out'],
                'n_richieste' => $n_richieste,
                'concluso' => WSMA_Data::evento_concluso($id),
            ];
        }
        wp_reset_postdata();

        $tot_partecipanti = (int) (new WP_Query(['post_type' => 'wsma_partecipante', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]))->post_count;

        $delay_min = (int) get_option('wsma_ai_delay_minutes', 60);
        $cutoff_gmt = gmdate('Y-m-d H:i:s', time() - ($delay_min * 60));
        $ai_coda = (int) (new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'date_query' => [['column' => 'post_date_gmt', 'before' => $cutoff_gmt]],
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'mail_risposta_sent_at', 'compare' => 'NOT EXISTS'],
                ['key' => 'mail_risposta_sent_at', 'value' => '', 'compare' => '='],
            ],
        ]))->post_count;

        $two_days_ago = wp_date('Y-m-d H:i:s', strtotime('-48 hours'));
        $inbox_check = (int) (new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => [['key' => 'mail_risposta_sent_at', 'value' => $two_days_ago, 'compare' => '>=', 'type' => 'DATETIME']],
        ]))->post_count;

        $in30 = wp_date('Y-m-d', strtotime('+30 days'));
        $pagamenti_count = 0;
        $confermati_ids = (new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => [['key' => 'stato', 'value' => 'confermato', 'compare' => '=']],
        ]))->posts;
        if (!empty($confermati_ids)) {
            update_postmeta_cache($confermati_ids);

            // Resolve each iscrizione's evento first, then batch-prime the
            // eventi meta cache too — otherwise get_field('data_evento', $eid_p)
            // below hits an uncached get_post_meta() per iscrizione (N+1).
            $eid_by_isc = [];
            foreach ($confermati_ids as $isc_p_id) {
                $eid_p = (int) WSMA_Data::get_field('evento', $isc_p_id);
                if ($eid_p) $eid_by_isc[$isc_p_id] = $eid_p;
            }
            $unique_eids = array_unique(array_values($eid_by_isc));
            if (!empty($unique_eids)) {
                update_postmeta_cache($unique_eids);
            }

            foreach ($eid_by_isc as $isc_p_id => $eid_p) {
                $date_p = WSMA_Data::get_field('data_evento', $eid_p);
                if (!$date_p || $date_p > $in30 || $date_p < $oggi) continue;
                $anticipo_p = (float) WSMA_Data::get_field('anticipo', $isc_p_id);
                if ($anticipo_p <= 0) $pagamenti_count++;
            }
        }

        $thirty_ago = wp_date('Y-m-d H:i:s', strtotime('-30 days'));
        $nuove_ids = (new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'date_query' => [['column' => 'post_date_gmt', 'after' => $thirty_ago]],
        ]))->posts;
        $n_nuove = count($nuove_ids);
        $n_conf30 = 0;
        foreach ($nuove_ids as $iid) {
            if (WSMA_Data::get_field('stato', $iid) === 'confermato') $n_conf30++;
        }
        $mail_ai_30 = (int) (new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => [['key' => 'mail_risposta_sent_at', 'value' => $thirty_ago, 'compare' => '>=', 'type' => 'DATETIME']],
        ]))->post_count;
        $conv30 = $n_nuove ? round($n_conf30 * 100 / $n_nuove) : 0;

        $pct_posti = $tot_posti ? round($tot_confermati * 100 / $tot_posti) : 0;

        $cockpit_data = [
            'urls' => $this->urls(),
            'todo' => [
                'ai_coda' => $ai_coda,
                'inbox_check' => $inbox_check,
                'pagamenti_count' => $pagamenti_count,
            ],
            'rings' => [
                'pct_posti' => $pct_posti, 'tot_confermati' => $tot_confermati, 'tot_posti' => $tot_posti,
                'tot_richieste' => $tot_richieste, 'tot_partecipanti' => $tot_partecipanti,
            ],
            'stats30' => [
                'n_nuove' => $n_nuove, 'n_conf30' => $n_conf30, 'mail_ai_30' => $mail_ai_30, 'conv30' => $conv30,
            ],
            'mostra_tutti' => $mostra_tutti,
            'eventi' => $eventi,
        ];

        // Lets PRO's analytics module (if active) append a 'pro_stats' key
        // to the same cockpit payload — the Vue view renders an extra
        // section only when that key is present, so this stays a no-op
        // extension point on free installs, not a separate dashboard.
        $cockpit_data = apply_filters('wsma_cockpit_data', $cockpit_data);

        return new WP_REST_Response($cockpit_data);
    }

    public function get_evento(WP_REST_Request $request) {
        $evento_id = (int) $request['id'];
        if (!apply_filters('wsma_scope_can_access_evento', true, $evento_id)) {
            return new WP_Error('forbidden', 'Non hai accesso a questo evento.', ['status' => 403]);
        }
        if (get_post_type($evento_id) !== 'wsma_evento') {
            return new WP_Error('not_found', 'Evento non trovato', ['status' => 404]);
        }

        $oggi = current_time('Y-m-d');
    $admin = WSMA_Data::find_page_url_containing('[workshop_admin]');
        $iscrizioni_ids = WSMA_Data::iscrizioni_evento($evento_id);
        $s = WSMA_Data::stato_posti($evento_id);
        $terms_ev = get_the_terms($evento_id, 'wsma_categoria_evento');
        $cat_id_ev = $terms_ev ? $terms_ev[0]->term_id : 0;
        $cat_name_ev = $terms_ev ? $terms_ev[0]->name : '—';
        $foto_ev = $cat_id_ev ? WSMA_Data::get_field('foto_categoria', 'wsma_categoria_evento_' . $cat_id_ev) : '';
        $cat_url_ev = $cat_id_ev ? WSMA_Data::get_field('url_pagina', 'wsma_categoria_evento_' . $cat_id_ev) : '';
        $fb_share_enabled = $cat_id_ev ? (bool) WSMA_Data::get_field('fb_share_enabled', 'wsma_categoria_evento_' . $cat_id_ev) : false;
        $fb_share_text = '';
        if ($fb_share_enabled) {
            $city = (string) WSMA_Data::get_field('citta', $evento_id) ?: ($cat_id_ev ? (string) WSMA_Data::get_field('citta', 'wsma_categoria_evento_' . $cat_id_ev) : '');
            $address = (string) WSMA_Data::get_field('indirizzo', $evento_id) ?: ($cat_id_ev ? (string) WSMA_Data::get_field('indirizzo', 'wsma_categoria_evento_' . $cat_id_ev) : '');
            $intro = $cat_id_ev ? (string) WSMA_Data::get_field('intro', 'wsma_categoria_evento_' . $cat_id_ev) : '';
            $luogo = trim($address ? ($address . ($city ? ', ' . $city : '')) : $city);
            $righe = [
                WSMA_Data::evento_label($evento_id),
                '',
                '📅 ' . WSMA_Data::format_periodo($evento_id),
            ];
            if ($luogo) $righe[] = '📍 ' . $luogo;
            if ($intro) { $righe[] = ''; $righe[] = $intro; }
            if ($cat_url_ev) { $righe[] = ''; $righe[] = 'Info e iscrizioni: ' . $cat_url_ev; }
            $fb_share_text = implode("\n", $righe);
        }
        $pct_prenotazione = $s['totali'] ? round($s['occupati'] * 100 / $s['totali']) : 0;

        $ev_richieste = 0; $ev_pag_manc = 0;
        foreach ($iscrizioni_ids as $isc_id) {
            $st_x = WSMA_Data::get_field('stato', $isc_id);
            if ($st_x === 'richiesta') $ev_richieste++;
            if ($st_x === 'confermato' && (float) WSMA_Data::get_field('anticipo', $isc_id) <= 0) $ev_pag_manc++;
        }
        $data_ev = WSMA_Data::get_field('data_evento', $evento_id);
        $giorni_a_evento = $data_ev ? (int) floor((strtotime($data_ev) - strtotime($oggi)) / 86400) : null;
        $ev_welcome_da = 0;
        if ($giorni_a_evento !== null && $giorni_a_evento >= 0 && $giorni_a_evento <= 10) {
            foreach ($iscrizioni_ids as $isc_id) {
                if (WSMA_Data::get_field('stato', $isc_id) === 'confermato' && !WSMA_Data::get_field('mail_welcome_sent_at', $isc_id)) {
                    $ev_welcome_da++;
                }
            }
        }

        $altri = [];
        if ($cat_id_ev) {
            $altri_q = new WP_Query([
                'post_type' => 'wsma_evento', 'posts_per_page' => -1, 'no_found_rows' => true,
                'tax_query' => [['taxonomy' => 'wsma_categoria_evento', 'field' => 'term_id', 'terms' => $cat_id_ev]],
                'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'ASC',
                'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']],
            ]);
            foreach ($altri_q->posts as $ev2) {
                if ($ev2->ID === $evento_id) continue;
                $s2 = WSMA_Data::stato_posti($ev2->ID);
                $altri[] = [
                    'id' => $ev2->ID,
                    'periodo' => WSMA_Data::format_periodo($ev2->ID),
                    'stato' => $s2['sold_out'] ? 'Sold Out' : $s2['occupati'] . '/' . $s2['totali'],
                ];
            }
        }

        $iscrizioni = [];
        if (!empty($iscrizioni_ids)) {
            update_postmeta_cache($iscrizioni_ids);
            $p_ids = array_filter(array_map(fn($id) => (int) WSMA_Data::get_field('partecipante', $id), $iscrizioni_ids));
            if (!empty($p_ids)) update_postmeta_cache($p_ids);

            foreach ($iscrizioni_ids as $isc) {
                $p = WSMA_Data::get_field('partecipante', $isc);
                if (!$p) continue;
                $stato_pagamento = (string) WSMA_Data::get_field('stato_pagamento', $isc);
                $iscrizioni[] = [
                    'id' => $isc,
                    'nome' => trim(WSMA_Data::get_field('nome', $p) . ' ' . WSMA_Data::get_field('cognome', $p)),
                    'email' => WSMA_Data::get_field('email', $p) ?: '',
                    'citta' => WSMA_Data::get_field('citta', $p) ?: '',
                    'telefono' => WSMA_Data::get_field('telefono', $p) ?: '',
                    'num_persone' => max(1, (int) get_post_meta($isc, 'num_persone', true) ?: 1),
                    'stato' => WSMA_Data::get_field('stato', $isc) === 'confermato' ? 'confermato' : 'richiesta',
                    'stato_pagamento' => in_array($stato_pagamento, self::STATI_PAGAMENTO, true) ? $stato_pagamento : 'in_attesa',
                    'anticipo' => (float) WSMA_Data::get_field('anticipo', $isc),
                    'saldo' => (float) WSMA_Data::get_field('saldo', $isc),
                    'note' => (string) WSMA_Data::get_field('note', $isc),
                    'edit_link' => $admin ? add_query_arg(['vista' => 'eventi', 'edit_isc' => $isc], $admin) : '',
                ];
            }
        }

        return new WP_REST_Response([
            'id' => $evento_id,
            'cat_name' => $cat_name_ev,
            'foto' => $foto_ev ?: '',
            'cat_url' => $cat_url_ev ?: '',
            'fb_share_enabled' => $fb_share_enabled,
            'fb_share_text' => $fb_share_text,
            'periodo' => WSMA_Data::format_periodo($evento_id),
            'label' => WSMA_Data::evento_label($evento_id),
            'concluso' => WSMA_Data::evento_concluso($evento_id),
            'ring' => ['pct' => $pct_prenotazione, 'occupati' => $s['occupati'], 'totali' => $s['totali']],
            'n_iscritti' => count($iscrizioni_ids),
            'giorni_a_evento' => $giorni_a_evento,
            'todo' => ['richieste' => $ev_richieste, 'pagamenti_mancanti' => $ev_pag_manc, 'welcome_da_inviare' => $ev_welcome_da],
            'urls' => $this->urls(),
            'edit_ev_link' => $admin ? add_query_arg(['vista' => 'eventi', 'edit_ev' => $evento_id], $admin) : '',
            'aggiungi_link' => $admin ? add_query_arg(['vista' => 'partecipanti', 'pre_evento' => $evento_id], $admin) : '',
            'altri_eventi' => $altri,
            'iscrizioni' => $iscrizioni,
        ]);
    }

    public function conferma_iscrizione(WP_REST_Request $request) {
        $isc_id = (int) $request['id'];
        if (get_post_type($isc_id) !== 'wsma_iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }
        if (!apply_filters('wsma_scope_can_access_iscrizione', true, $isc_id)) {
            return new WP_Error('forbidden', 'Non hai accesso a questa iscrizione.', ['status' => 403]);
        }

        $pid = (int) WSMA_Data::get_field('partecipante', $isc_id);
        $eid = (int) WSMA_Data::get_field('evento', $isc_id);
        $email = $pid ? WSMA_Data::get_field('email', $pid) : '';
        if (!$pid || !$eid || !$email) {
            return new WP_Error('send_failed', 'Impossibile inviare la mail (email mancante?)', ['status' => 500]);
        }

        $terms = get_the_terms($eid, 'wsma_categoria_evento');
        $cat_id = $terms ? $terms[0]->term_id : 0;
        $oggetto_tpl = $cat_id ? WSMA_Data::get_field('oggetto_conferma', 'wsma_categoria_evento_' . $cat_id) : '';
        $mail_tpl = $cat_id ? WSMA_Data::get_field('mail_conferma', 'wsma_categoria_evento_' . $cat_id) : '';
        if (!$oggetto_tpl) $oggetto_tpl = '✓ Confermato · {categoria_nome} · {periodo}';
        if (!$mail_tpl) $mail_tpl = "Ciao {nome},\n\nsei ufficialmente confermato per {categoria_nome} del {periodo}.\n\nIn allegato il file calendario (.ics) per aggiungere l'evento al tuo Google o Apple Calendar.\n\nPer qualsiasi cosa, rispondimi a questa mail o scrivimi su WhatsApp.\n\nA presto,\nFrancesco";

        $subject = WSMA_Data::render_template($oggetto_tpl, $isc_id);
        $body = WSMA_Data::render_template($mail_tpl, $isc_id);

        $attachments = [];
        $ics = WSMA_Data::genera_ics($eid);
        if ($ics) {
            $attachments[] = ['content' => $ics, 'filename' => 'evento-' . $eid . '.ics', 'type' => 'text/calendar'];
        }

        // Lets add-ons (e.g. the PRO voucher/PDF pass) attach to this same
        // confirmation email instead of sending a separate one.
        $attachments = apply_filters('wsma_confirmation_email_attachments', $attachments, $isc_id);

        $result = WSMA_Mail_Inbox::send_reply($email, $subject, $body, $attachments);
        if (!$result['ok']) {
            return new WP_Error('send_failed', $result['msg'], ['status' => 500]);
        }

        WSMA_Data::update_field('stato', 'confermato', $isc_id);
        WSMA_Data::update_field('mail_conferma_sent_at', current_time('mysql'), $isc_id);
        WSMA_Data::append_thread($isc_id, 'out', $subject, $body);

        return new WP_REST_Response(['stato' => 'confermato']);
    }

    /**
     * Aggiorna solo stato_pagamento — indipendente dal campo 'stato'
     * (uno traccia la conferma della prenotazione, l'altro il denaro).
     */
    public function aggiorna_stato_pagamento(WP_REST_Request $request) {
        $isc_id = (int) $request['id'];
        if (get_post_type($isc_id) !== 'wsma_iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }
        if (!apply_filters('wsma_scope_can_access_iscrizione', true, $isc_id)) {
            return new WP_Error('forbidden', 'Non hai accesso a questa iscrizione.', ['status' => 403]);
        }

        $stato = (string) $request->get_param('stato');
        if (!in_array($stato, self::STATI_PAGAMENTO, true)) {
            return new WP_Error('invalid', 'Stato pagamento non valido.', ['status' => 400]);
        }

        WSMA_Data::update_field('stato_pagamento', $stato, $isc_id);

        return new WP_REST_Response(['stato_pagamento' => $stato]);
    }

    public function save_nota(WP_REST_Request $request) {
        $isc_id = (int) $request['id'];
        if (get_post_type($isc_id) !== 'wsma_iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }
        if (!apply_filters('wsma_scope_can_access_iscrizione', true, $isc_id)) {
            return new WP_Error('forbidden', 'Non hai accesso a questa iscrizione.', ['status' => 403]);
        }
        $nota = sanitize_textarea_field((string) $request->get_param('note'));
        if (mb_strlen($nota) > 200) $nota = mb_substr($nota, 0, 200);
        WSMA_Data::update_field('note', $nota, $isc_id);
        return new WP_REST_Response(['note' => $nota]);
    }

    public function delete_iscrizione(WP_REST_Request $request) {
        $isc_id = (int) $request['id'];
        if (get_post_type($isc_id) !== 'wsma_iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }
        if (!apply_filters('wsma_scope_can_access_iscrizione', true, $isc_id)) {
            return new WP_Error('forbidden', 'Non hai accesso a questa iscrizione.', ['status' => 403]);
        }
        wp_delete_post($isc_id, true);
        return new WP_REST_Response(['deleted' => true]);
    }
}