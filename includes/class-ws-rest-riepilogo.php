<?php

if (!defined('ABSPATH')) exit;

/**
 * REST endpoints backing the Riepilogo cockpit + per-event subview.
 * Ports `workshop_riepilogo` 1:1. Confirmation email sending was ported
 * from the legacy `wv_invia_conferma()` once the direct Zoho SMTP channel
 * (WS_Mail_Inbox::send_reply()) was verified working, so it too sends
 * from workshop@francescoverolino.com instead of the SES-forced
 * info@francescoverolino.it — see conferma_iscrizione().
 */
final class WS_Rest_Riepilogo implements WS_Module {

    private const META_THREAD = 'wv_thread';

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        $perm = fn() => current_user_can('manage_options');

        register_rest_route('workshop-suite/v1', '/riepilogo/cockpit', [
            'methods' => 'GET', 'callback' => [$this, 'get_cockpit'], 'permission_callback' => $perm,
        ]);
        register_rest_route('workshop-suite/v1', '/riepilogo/evento/(?P<id>\d+)', [
            'methods' => 'GET', 'callback' => [$this, 'get_evento'], 'permission_callback' => $perm,
        ]);
        register_rest_route('workshop-suite/v1', '/riepilogo/iscrizione/(?P<id>\d+)/conferma', [
            'methods' => 'POST', 'callback' => [$this, 'conferma_iscrizione'], 'permission_callback' => $perm,
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
            'checkpoint' => home_url('/admin-checkpoint/'),
            'contatti'   => home_url('/admin-contatti/'),
            'ai'         => home_url('/admin-ai/'),
            'apresto'    => home_url('/admin-apresto/'),
        ];
    }

    public function get_cockpit(WP_REST_Request $request): WP_REST_Response {
        $mostra_tutti = (bool) $request->get_param('tutti');
        $oggi = date('Y-m-d');

        $args = [
            'post_type'      => 'evento',
            'posts_per_page' => -1,
            'meta_key'       => 'data_evento',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ];
        if (!$mostra_tutti) {
            $args['meta_query'] = [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']];
        }
        $eq = new WP_Query($args);

        if (!empty($eq->posts)) {
            update_postmeta_cache(wp_list_pluck($eq->posts, 'ID'));
        }

        $tot_confermati = 0; $tot_richieste = 0; $tot_posti = 0;
        $eventi = [];
        while ($eq->have_posts()) {
            $eq->the_post();
            $id = get_the_ID();
            $s = WS_Data::stato_posti($id);
            $tot_confermati += $s['occupati'];
            $tot_richieste  += WS_Data::count_richieste($id);
            $tot_posti      += $s['totali'];

            $terms = get_the_terms($id, 'categoria_evento');
            $cat_name = $terms ? $terms[0]->name : '—';
            $foto = $terms ? WS_Data::get_field('foto_categoria', 'categoria_evento_' . $terms[0]->term_id) : '';
            $n_richieste = WS_Data::count_richieste($id);

            $eventi[] = [
                'id' => $id,
                'cat_name' => $cat_name,
                'foto' => $foto ?: '',
                'periodo' => WS_Data::format_periodo($id),
                'occupati' => $s['occupati'],
                'totali' => $s['totali'],
                'sold_out' => $s['sold_out'],
                'n_richieste' => $n_richieste,
                'concluso' => WS_Data::evento_concluso($id),
            ];
        }
        wp_reset_postdata();

        $tot_partecipanti = (int) (new WP_Query(['post_type' => 'partecipante', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]))->post_count;

        // wv_checkpoint_pending() was never actually defined anywhere in the
        // legacy codebase — the original also always evaluated this to 0 via
        // its function_exists() guard. Preserved as-is (behavior-preserving
        // port), not silently "fixed".
        $chk_pending = function_exists('wv_checkpoint_pending') ? count(wv_checkpoint_pending()) : 0;
        $ap_pending  = function_exists('wv_apresto_pending') ? count(wv_apresto_pending(10)) : 0;

        $delay_min = (int) get_option('wv_ai_delay_minutes', 60);
        $cutoff_gmt = gmdate('Y-m-d H:i:s', time() - ($delay_min * 60));
        $ai_coda = (int) (new WP_Query([
            'post_type' => 'iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'date_query' => [['column' => 'post_date_gmt', 'before' => $cutoff_gmt]],
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'mail_risposta_sent_at', 'compare' => 'NOT EXISTS'],
                ['key' => 'mail_risposta_sent_at', 'value' => '', 'compare' => '='],
            ],
        ]))->post_count;

        $two_days_ago = date('Y-m-d H:i:s', strtotime('-48 hours'));
        $inbox_check = (int) (new WP_Query([
            'post_type' => 'iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => [['key' => 'mail_risposta_sent_at', 'value' => $two_days_ago, 'compare' => '>=', 'type' => 'DATETIME']],
        ]))->post_count;

        $in30 = date('Y-m-d', strtotime('+30 days'));
        $pagamenti_count = 0;
        $confermati_ids = (new WP_Query([
            'post_type' => 'iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => [['key' => 'stato', 'value' => 'confermato', 'compare' => '=']],
        ]))->posts;
        if (!empty($confermati_ids)) {
            update_postmeta_cache($confermati_ids);

            // Resolve each iscrizione's evento first, then batch-prime the
            // eventi meta cache too — otherwise get_field('data_evento', $eid_p)
            // below hits an uncached get_post_meta() per iscrizione (N+1).
            $eid_by_isc = [];
            foreach ($confermati_ids as $isc_p_id) {
                $eid_p = (int) WS_Data::get_field('evento', $isc_p_id);
                if ($eid_p) $eid_by_isc[$isc_p_id] = $eid_p;
            }
            $unique_eids = array_unique(array_values($eid_by_isc));
            if (!empty($unique_eids)) {
                update_postmeta_cache($unique_eids);
            }

            foreach ($eid_by_isc as $isc_p_id => $eid_p) {
                $date_p = WS_Data::get_field('data_evento', $eid_p);
                if (!$date_p || $date_p > $in30 || $date_p < $oggi) continue;
                $anticipo_p = (float) WS_Data::get_field('anticipo', $isc_p_id);
                if ($anticipo_p <= 0) $pagamenti_count++;
            }
        }

        $thirty_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        $nuove_ids = (new WP_Query([
            'post_type' => 'iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'date_query' => [['column' => 'post_date_gmt', 'after' => $thirty_ago]],
        ]))->posts;
        $n_nuove = count($nuove_ids);
        $n_conf30 = 0;
        foreach ($nuove_ids as $iid) {
            if (WS_Data::get_field('stato', $iid) === 'confermato') $n_conf30++;
        }
        $mail_ai_30 = (int) (new WP_Query([
            'post_type' => 'iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => [['key' => 'mail_risposta_sent_at', 'value' => $thirty_ago, 'compare' => '>=', 'type' => 'DATETIME']],
        ]))->post_count;
        $conv30 = $n_nuove ? round($n_conf30 * 100 / $n_nuove) : 0;

        $pct_posti = $tot_posti ? round($tot_confermati * 100 / $tot_posti) : 0;

        return new WP_REST_Response([
            'urls' => $this->urls(),
            'todo' => [
                'chk_pending' => $chk_pending,
                'ai_coda' => $ai_coda,
                'ap_pending' => $ap_pending,
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
        ]);
    }

    public function get_evento(WP_REST_Request $request) {
        $evento_id = (int) $request['id'];
        if (get_post_type($evento_id) !== 'evento') {
            return new WP_Error('not_found', 'Evento non trovato', ['status' => 404]);
        }

        $oggi = date('Y-m-d');
        $admin = WS_Data::find_page_url_containing('[workshop_admin]');
        $iscrizioni_ids = WS_Data::iscrizioni_evento($evento_id);
        $s = WS_Data::stato_posti($evento_id);
        $terms_ev = get_the_terms($evento_id, 'categoria_evento');
        $cat_id_ev = $terms_ev ? $terms_ev[0]->term_id : 0;
        $cat_name_ev = $terms_ev ? $terms_ev[0]->name : '—';
        $foto_ev = $cat_id_ev ? WS_Data::get_field('foto_categoria', 'categoria_evento_' . $cat_id_ev) : '';
        $cat_url_ev = $cat_id_ev ? WS_Data::get_field('url_pagina', 'categoria_evento_' . $cat_id_ev) : '';
        $pct_prenotazione = $s['totali'] ? round($s['occupati'] * 100 / $s['totali']) : 0;

        $ev_richieste = 0; $ev_pag_manc = 0;
        foreach ($iscrizioni_ids as $isc_id) {
            $st_x = WS_Data::get_field('stato', $isc_id);
            if ($st_x === 'richiesta') $ev_richieste++;
            if ($st_x === 'confermato' && (float) WS_Data::get_field('anticipo', $isc_id) <= 0) $ev_pag_manc++;
        }
        $data_ev = WS_Data::get_field('data_evento', $evento_id);
        $giorni_a_evento = $data_ev ? (int) floor((strtotime($data_ev) - strtotime($oggi)) / 86400) : null;
        $ev_welcome_da = 0;
        if ($giorni_a_evento !== null && $giorni_a_evento >= 0 && $giorni_a_evento <= 10) {
            foreach ($iscrizioni_ids as $isc_id) {
                if (WS_Data::get_field('stato', $isc_id) === 'confermato' && !WS_Data::get_field('mail_welcome_sent_at', $isc_id)) {
                    $ev_welcome_da++;
                }
            }
        }

        $altri = [];
        if ($cat_id_ev) {
            $altri_q = new WP_Query([
                'post_type' => 'evento', 'posts_per_page' => -1,
                'tax_query' => [['taxonomy' => 'categoria_evento', 'field' => 'term_id', 'terms' => $cat_id_ev]],
                'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'ASC',
                'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']],
            ]);
            foreach ($altri_q->posts as $ev2) {
                if ($ev2->ID === $evento_id) continue;
                $s2 = WS_Data::stato_posti($ev2->ID);
                $altri[] = [
                    'id' => $ev2->ID,
                    'periodo' => WS_Data::format_periodo($ev2->ID),
                    'stato' => $s2['sold_out'] ? 'Sold Out' : $s2['occupati'] . '/' . $s2['totali'],
                ];
            }
        }

        $iscrizioni = [];
        if (!empty($iscrizioni_ids)) {
            update_postmeta_cache($iscrizioni_ids);
            $p_ids = array_filter(array_map(fn($id) => (int) WS_Data::get_field('partecipante', $id), $iscrizioni_ids));
            if (!empty($p_ids)) update_postmeta_cache($p_ids);

            foreach ($iscrizioni_ids as $isc) {
                $p = WS_Data::get_field('partecipante', $isc);
                if (!$p) continue;
                $iscrizioni[] = [
                    'id' => $isc,
                    'nome' => trim(WS_Data::get_field('nome', $p) . ' ' . WS_Data::get_field('cognome', $p)),
                    'email' => WS_Data::get_field('email', $p) ?: '',
                    'citta' => WS_Data::get_field('citta', $p) ?: '',
                    'telefono' => WS_Data::get_field('telefono', $p) ?: '',
                    'stato' => WS_Data::get_field('stato', $isc) === 'confermato' ? 'confermato' : 'richiesta',
                    'anticipo' => (float) WS_Data::get_field('anticipo', $isc),
                    'saldo' => (float) WS_Data::get_field('saldo', $isc),
                    'note' => (string) WS_Data::get_field('note', $isc),
                    'edit_link' => $admin ? add_query_arg(['vista' => 'eventi', 'edit_isc' => $isc], $admin) : '',
                ];
            }
        }

        return new WP_REST_Response([
            'id' => $evento_id,
            'cat_name' => $cat_name_ev,
            'foto' => $foto_ev ?: '',
            'cat_url' => $cat_url_ev ?: '',
            'periodo' => WS_Data::format_periodo($evento_id),
            'label' => WS_Data::evento_label($evento_id),
            'concluso' => WS_Data::evento_concluso($evento_id),
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
        if (get_post_type($isc_id) !== 'iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }

        $pid = (int) WS_Data::get_field('partecipante', $isc_id);
        $eid = (int) WS_Data::get_field('evento', $isc_id);
        $email = $pid ? WS_Data::get_field('email', $pid) : '';
        if (!$pid || !$eid || !$email) {
            return new WP_Error('send_failed', 'Impossibile inviare la mail (email mancante?)', ['status' => 500]);
        }

        $terms = get_the_terms($eid, 'categoria_evento');
        $cat_id = $terms ? $terms[0]->term_id : 0;
        $oggetto_tpl = $cat_id ? WS_Data::get_field('oggetto_conferma', 'categoria_evento_' . $cat_id) : '';
        $mail_tpl = $cat_id ? WS_Data::get_field('mail_conferma', 'categoria_evento_' . $cat_id) : '';
        if (!$oggetto_tpl) $oggetto_tpl = '✓ Confermato · {categoria_nome} · {periodo}';
        if (!$mail_tpl) $mail_tpl = "Ciao {nome},\n\nsei ufficialmente confermato per {categoria_nome} del {periodo}.\n\nIn allegato il file calendario (.ics) per aggiungere l'evento al tuo Google o Apple Calendar.\n\nPer qualsiasi cosa, rispondimi a questa mail o scrivimi su WhatsApp.\n\nA presto,\nFrancesco";

        $subject = WS_Data::render_template($oggetto_tpl, $isc_id);
        $body = WS_Data::render_template($mail_tpl, $isc_id);

        $attachments = [];
        $ics = WS_Data::genera_ics($eid);
        if ($ics) {
            $attachments[] = ['content' => $ics, 'filename' => 'evento-' . $eid . '.ics', 'type' => 'text/calendar'];
        }

        $result = WS_Mail_Inbox::send_reply($email, $subject, $body, $attachments);
        if (!$result['ok']) {
            return new WP_Error('send_failed', $result['msg'], ['status' => 500]);
        }

        WS_Data::update_field('stato', 'confermato', $isc_id);
        WS_Data::update_field('mail_conferma_sent_at', current_time('mysql'), $isc_id);
        WS_Data::append_thread($isc_id, 'out', $subject, $body);

        return new WP_REST_Response(['stato' => 'confermato']);
    }

    public function save_nota(WP_REST_Request $request) {
        $isc_id = (int) $request['id'];
        if (get_post_type($isc_id) !== 'iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }
        $nota = sanitize_textarea_field((string) $request->get_param('note'));
        if (mb_strlen($nota) > 200) $nota = mb_substr($nota, 0, 200);
        WS_Data::update_field('note', $nota, $isc_id);
        return new WP_REST_Response(['note' => $nota]);
    }

    public function delete_iscrizione(WP_REST_Request $request) {
        $isc_id = (int) $request['id'];
        if (get_post_type($isc_id) !== 'iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }
        wp_delete_post($isc_id, true);
        return new WP_REST_Response(['deleted' => true]);
    }
}