<?php

if (!defined('ABSPATH')) exit;

/**
 * REST endpoints backing the Dashboard (workshop_admin) panel: the 3-tab
 * CRUD hub for partecipanti/eventi/categorie. Ports every legacy POST
 * action 1:1. Note: the legacy `invia_conferma` action defined in this
 * shortcode's handler had no corresponding UI button anywhere in its own
 * template (dead/unreachable code) — not ported here since nothing in the
 * new UI can trigger it either; the real "Conferma" button lives in
 * workshop_riepilogo and is served by WS_Rest_Riepilogo::conferma_iscrizione().
 */
final class WS_Rest_Admin implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        $perm = fn() => current_user_can('manage_options');
        $ns = 'workshop-suite/v1';

        register_rest_route($ns, '/admin/partecipanti-tab', ['methods' => 'GET', 'callback' => [$this, 'get_partecipanti_tab'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/partecipanti', ['methods' => 'POST', 'callback' => [$this, 'aggiungi_partecipante'], 'permission_callback' => $perm]);

        register_rest_route($ns, '/admin/eventi-tab', ['methods' => 'GET', 'callback' => [$this, 'get_eventi_tab'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/eventi', ['methods' => 'POST', 'callback' => [$this, 'crea_evento'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/eventi/(?P<id>\d+)', ['methods' => 'PUT', 'callback' => [$this, 'modifica_evento'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/eventi/(?P<id>\d+)', ['methods' => 'DELETE', 'callback' => [$this, 'elimina_evento'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/eventi/(?P<id>\d+)/toggle-frontend', ['methods' => 'POST', 'callback' => [$this, 'toggle_frontend'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/iscrizioni/(?P<id>\d+)', ['methods' => 'PUT', 'callback' => [$this, 'modifica_iscrizione'], 'permission_callback' => $perm]);

        register_rest_route($ns, '/admin/categorie-tab', ['methods' => 'GET', 'callback' => [$this, 'get_categorie_tab'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/categorie', ['methods' => 'POST', 'callback' => [$this, 'crea_categoria'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/categorie/(?P<id>\d+)', ['methods' => 'PUT', 'callback' => [$this, 'modifica_categoria'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/categorie/(?P<id>\d+)', ['methods' => 'DELETE', 'callback' => [$this, 'elimina_categoria'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/crea-pagina-categoria', ['methods' => 'POST', 'callback' => [$this, 'crea_pagina_categoria'], 'permission_callback' => $perm]);

        register_rest_route($ns, '/admin/tipi-tab', ['methods' => 'GET', 'callback' => [$this, 'get_tipi_tab'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/tipi', ['methods' => 'POST', 'callback' => [$this, 'salva_tipi'], 'permission_callback' => $perm]);

        register_rest_route($ns, '/admin/locandine-modelli', ['methods' => 'GET', 'callback' => [$this, 'get_modelli_locandine'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/locandine-modelli', ['methods' => 'POST', 'callback' => [$this, 'salva_modello_locandina'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/locandine-modelli/(?P<id>[a-zA-Z0-9_\-]+)', ['methods' => 'DELETE', 'callback' => [$this, 'elimina_modello_locandina'], 'permission_callback' => $perm]);
    }

    // ───────────────────────── shared read helpers ─────────────────────────

    private function evento_option(int $id): array {
        $s = WS_Data::stato_posti($id);
        return [
            'id' => $id,
            'label' => WS_Data::evento_label($id),
            'disponibili' => $s['disponibili'],
            'sold_out' => $s['sold_out'],
        ];
    }

    private function oggi(): string {
        return date('Y-m-d');
    }

    // ───────────────────────── TAB: Partecipanti ─────────────────────────

    public function get_partecipanti_tab(): WP_REST_Response {
        $oggi = $this->oggi();

        $in_programma = [];
        $eq = new WP_Query(['post_type' => 'evento', 'posts_per_page' => -1, 'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'ASC',
            'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']]]);
        foreach ($eq->posts as $ev) $in_programma[] = $this->evento_option($ev->ID);

        $conclusi = [];
        $eq2 = new WP_Query(['post_type' => 'evento', 'posts_per_page' => -1, 'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'DESC',
            'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '<', 'type' => 'DATE']]]);
        foreach ($eq2->posts as $ev) {
            $conclusi[] = ['id' => $ev->ID, 'label' => WS_Data::evento_label($ev->ID)];
        }

        $partecipanti = [];
        $pq = new WP_Query(['post_type' => 'partecipante', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        if (!empty($pq->posts)) {
            update_postmeta_cache(wp_list_pluck($pq->posts, 'ID'));
            foreach ($pq->posts as $p) {
                $partecipanti[] = [
                    'id' => $p->ID,
                    'nome' => WS_Data::get_field('nome', $p->ID) ?: '',
                    'cognome' => WS_Data::get_field('cognome', $p->ID) ?: '',
                    'email' => WS_Data::get_field('email', $p->ID) ?: '',
                    'telefono' => WS_Data::get_field('telefono', $p->ID) ?: '',
                    'citta' => WS_Data::get_field('citta', $p->ID) ?: '',
                ];
            }
        }

        return new WP_REST_Response([
            'eventi_in_programma' => $in_programma,
            'eventi_conclusi' => $conclusi,
            'partecipanti' => $partecipanti,
        ]);
    }

    public function aggiungi_partecipante(WP_REST_Request $request) {
        $evento_id = (int) $request->get_param('evento');
        $email = sanitize_email((string) $request->get_param('email'));
        $nome = sanitize_text_field((string) $request->get_param('nome'));
        $cognome = sanitize_text_field((string) $request->get_param('cognome'));
        $tel = sanitize_text_field((string) $request->get_param('telefono'));
        $citta = sanitize_text_field((string) $request->get_param('citta'));
        $anticipo = (float) $request->get_param('anticipo');
        $saldo = (float) $request->get_param('saldo');
        $note = sanitize_textarea_field((string) $request->get_param('note'));
        $stato_iniziale = ((string) $request->get_param('stato_iniziale') === 'confermato') ? 'confermato' : 'richiesta';
        $num_persone = max(1, (int) $request->get_param('num_persone'));
        $existing = (int) $request->get_param('existing');

        if (!$evento_id || !$email || !$nome) {
            return new WP_Error('invalid', 'Evento, email e nome sono obbligatori.', ['status' => 400]);
        }

        $aggiorna_anagrafica = !empty($request->get_param('aggiorna_anagrafica'));

        $pid = $existing ?: WS_Data::find_partecipante_by_email($email);
        $is_new = false;
        if (!$pid) {
            $is_new = true;
            $pid = wp_insert_post(['post_type' => 'partecipante', 'post_status' => 'publish', 'post_title' => trim($nome . ' ' . $cognome)]);
        }

        // Always update metadata if it's a new participant, or if explicitly requested, or populate only missing empty fields
        if ($is_new || $aggiorna_anagrafica) {
            WS_Data::update_field('nome', $nome, $pid);
            WS_Data::update_field('cognome', $cognome, $pid);
            if ($tel) WS_Data::update_field('telefono', $tel, $pid);
            if ($email) WS_Data::update_field('email', $email, $pid);
            if ($citta) WS_Data::update_field('citta', $citta, $pid);
            wp_update_post(['ID' => $pid, 'post_title' => trim($nome . ' ' . $cognome)]);
        } else {
            // Fill in missing fields only without overwriting existing non-empty values
            if (!WS_Data::get_field('nome', $pid) && $nome) WS_Data::update_field('nome', $nome, $pid);
            if (!WS_Data::get_field('cognome', $pid) && $cognome) WS_Data::update_field('cognome', $cognome, $pid);
            if (!WS_Data::get_field('telefono', $pid) && $tel) WS_Data::update_field('telefono', $tel, $pid);
            if (!WS_Data::get_field('email', $pid) && $email) WS_Data::update_field('email', $email, $pid);
            if (!WS_Data::get_field('citta', $pid) && $citta) WS_Data::update_field('citta', $citta, $pid);
        }

        if (WS_Data::find_iscrizione($pid, $evento_id)) {
            return new WP_REST_Response(['msg' => 'Questa persona è già iscritta a questo evento.']);
        }

        $isc = wp_insert_post(['post_type' => 'iscrizione', 'post_status' => 'publish',
            'post_title' => $nome . ' ' . $cognome . ' → ' . WS_Data::evento_label($evento_id)]);
        WS_Data::update_field('partecipante', $pid, $isc);
        WS_Data::update_field('evento', $evento_id, $isc);
        WS_Data::update_field('stato', $stato_iniziale, $isc);
        WS_Data::update_field('anticipo', $anticipo, $isc);
        WS_Data::update_field('saldo', $saldo, $isc);
        WS_Data::update_field('note', $note, $isc);
        update_post_meta($isc, 'num_persone', $num_persone);

        $msg = $stato_iniziale === 'confermato'
            ? 'Iscrizione creata come Confermata (nessuna mail inviata).'
            : 'Iscrizione creata in stato "Richiesta".';
        return new WP_REST_Response(['msg' => $msg]);
    }

    // ───────────────────────── TAB: Eventi ─────────────────────────

    public function get_eventi_tab(WP_REST_Request $request): WP_REST_Response {
        $oggi = $this->oggi();
        $edit_ev = (int) $request->get_param('edit_ev');
        $edit_isc = (int) $request->get_param('edit_isc');

        $categorie = [];
        foreach (get_terms(['taxonomy' => 'categoria_evento', 'hide_empty' => false]) as $t) {
            $categorie[] = ['id' => $t->term_id, 'name' => $t->name];
        }

        $editing_evento = null;
        if ($edit_ev) {
            $terms = get_the_terms($edit_ev, 'categoria_evento');
            $editing_evento = [
                'id' => $edit_ev,
                'categoria_id' => $terms ? $terms[0]->term_id : 0,
                'data_evento' => WS_Data::get_field('data_evento', $edit_ev) ?: '',
                'data_fine' => WS_Data::get_field('data_fine', $edit_ev) ?: '',
                'ora_inizio' => WS_Data::get_field('ora_inizio', $edit_ev) ?: '',
                'ora_fine' => WS_Data::get_field('ora_fine', $edit_ev) ?: '',
                'posti_totali' => (int) WS_Data::get_field('posti_totali', $edit_ev) ?: 5,
                'enable_hub_sync' => (bool) WS_Data::get_field('enable_hub_sync', $edit_ev),
                'indirizzo_geocoding' => (string) WS_Data::get_field('indirizzo_geocoding', $edit_ev) ?: '',
                'event_latitude' => (string) WS_Data::get_field('event_latitude', $edit_ev) ?: '',
                'event_longitude' => (string) WS_Data::get_field('event_longitude', $edit_ev) ?: '',
                'hub_status' => (string) WS_Data::get_field('hub_status', $edit_ev) ?: 'non_sincronizzato',
            ];
        }

        $editing_iscrizione = null;
        if ($edit_isc) {
            $p = WS_Data::get_field('partecipante', $edit_isc);
            $curr_eid = (int) WS_Data::get_field('evento', $edit_isc);
            $curr_terms = get_the_terms($curr_eid, 'categoria_evento');
            $curr_cat = ($curr_terms && !is_wp_error($curr_terms)) ? $curr_terms[0]->term_id : 0;

            $ev_args = ['post_type' => 'evento', 'posts_per_page' => -1, 'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'ASC',
                'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']]];
            if ($curr_cat) $ev_args['tax_query'] = [['taxonomy' => 'categoria_evento', 'field' => 'term_id', 'terms' => $curr_cat]];
            $ev_tutti = new WP_Query($ev_args);
            $eventi_stessa_categoria = [];
            foreach ($ev_tutti->posts as $ev) $eventi_stessa_categoria[] = $this->evento_option($ev->ID);

            $editing_iscrizione = [
                'id' => $edit_isc,
                'nome' => $p ? trim(WS_Data::get_field('nome', $p) . ' ' . WS_Data::get_field('cognome', $p)) : get_the_title($edit_isc),
                'stato' => WS_Data::get_field('stato', $edit_isc) === 'confermato' ? 'confermato' : 'richiesta',
                'curr_evento_id' => $curr_eid,
                'num_persone' => max(1, (int) get_post_meta($edit_isc, 'num_persone', true) ?: 1),
                'anticipo' => (float) WS_Data::get_field('anticipo', $edit_isc),
                'saldo' => (float) WS_Data::get_field('saldo', $edit_isc),
                'note' => (string) WS_Data::get_field('note', $edit_isc),
                'eventi_stessa_categoria' => $eventi_stessa_categoria,
            ];
        }

        if ($edit_isc) {
            $isc_eid = (int) WS_Data::get_field('evento', $edit_isc);
            $eq = new WP_Query(['post_type' => 'evento', 'posts_per_page' => 1, 'post__in' => [$isc_eid]]);
        } else {
            $eq = new WP_Query(['post_type' => 'evento', 'posts_per_page' => -1, 'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'ASC',
                'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']]]);
        }
        $eventi = [];
        foreach ($eq->posts as $ev) {
            $id = $ev->ID;
            $s = WS_Data::stato_posti($id);
            $terms = get_the_terms($id, 'categoria_evento');
            $term = ($terms && !is_wp_error($terms)) ? $terms[0] : null;
            $cat_id = $term ? $term->term_id : 0;
            $cat_name = $term ? $term->name : '';
            $cat_tipo = $cat_id ? (string) (WS_Data::get_field('tipo_categoria', 'categoria_evento_' . $cat_id) ?: get_term_meta($cat_id, 'tipo_categoria', true)) : '';
            $cat_foto = $cat_id ? (string) (WS_Data::get_field('foto_categoria', 'categoria_evento_' . $cat_id) ?: get_term_meta($cat_id, 'foto_categoria', true)) : '';
            $d_inizio = (string) WS_Data::get_field('data_evento', $id);
            $d_fine = (string) WS_Data::get_field('data_fine', $id);

            $date_str = '';
            if ($d_inizio) {
                $t1 = strtotime($d_inizio);
                $t2 = $d_fine ? strtotime($d_fine) : $t1;
                if ($t1 === $t2 || !$d_fine) {
                    $date_str = strtoupper(date_i18n('d M\'y', $t1));
                } else {
                    $date_str = strtoupper(date_i18n('d', $t1) . ' - ' . date_i18n('d M\'y', $t2));
                }
            }

            $eventi[] = [
                'id' => $id,
                'label' => WS_Data::evento_label($id),
                'categoria_nome' => $cat_name,
                'categoria_tipo' => $cat_tipo,
                'categoria_foto' => $cat_foto,
                'data_formattata' => $date_str,
                'occupati' => $s['occupati'], 'totali' => $s['totali'], 'sold_out' => $s['sold_out'],
                'n_richieste' => WS_Data::count_richieste($id),
                'nascosto' => (bool) get_post_meta($id, 'nascondi_dal_frontend', true),
                'enable_hub_sync' => (bool) WS_Data::get_field('enable_hub_sync', $id),
                'hub_status' => (string) WS_Data::get_field('hub_status', $id) ?: 'non_sincronizzato',
            ];
        }

        return new WP_REST_Response([
            'categorie' => $categorie,
            'editing_evento' => $editing_evento,
            'editing_iscrizione' => $editing_iscrizione,
            'eventi' => $eventi,
            'eventi_options' => $eventi,
        ]);
    }

    public function crea_evento(WP_REST_Request $request) {
        $cat = (int) $request->get_param('categoria');
        $data = sanitize_text_field((string) $request->get_param('data_evento'));
        $data_fine = sanitize_text_field((string) $request->get_param('data_fine'));
        $oi = sanitize_text_field((string) $request->get_param('ora_inizio'));
        $of = sanitize_text_field((string) $request->get_param('ora_fine'));
        $posti = (int) ($request->get_param('posti_totali') ?: 5);
        $enable_hub_sync = !empty($request->get_param('enable_hub_sync')) ? 1 : 0;
        $indirizzo_geocoding = sanitize_text_field((string) $request->get_param('indirizzo_geocoding'));
        $lat = sanitize_text_field((string) $request->get_param('event_latitude'));
        $lng = sanitize_text_field((string) $request->get_param('event_longitude'));

        $term = get_term($cat, 'categoria_evento');
        if (!$cat || !$data || !$term || is_wp_error($term)) {
            return new WP_Error('invalid', 'Categoria e data sono obbligatorie.', ['status' => 400]);
        }
        if (!$data_fine || $data_fine < $data) $data_fine = $data;

        $id = wp_insert_post(['post_type' => 'evento', 'post_status' => 'publish',
            'post_title' => $term->name . ' – ' . date_i18n('d/m/Y', strtotime($data))]);
        if (!$id) return new WP_Error('failed', 'Creazione evento fallita.', ['status' => 500]);

        WS_Data::update_field('data_evento', $data, $id);
        WS_Data::update_field('data_fine', $data_fine, $id);
        WS_Data::update_field('ora_inizio', $oi, $id);
        WS_Data::update_field('ora_fine', $of, $id);
        WS_Data::update_field('posti_totali', $posti, $id);
        WS_Data::update_field('enable_hub_sync', $enable_hub_sync, $id);
        WS_Data::update_field('indirizzo_geocoding', $indirizzo_geocoding, $id);
        WS_Data::update_field('event_latitude', $lat, $id);
        WS_Data::update_field('event_longitude', $lng, $id);
        WS_Data::update_field('hub_status', 'non_sincronizzato', $id);
        wp_set_object_terms($id, $cat, 'categoria_evento');

        return new WP_REST_Response(['msg' => 'Evento creato.', 'id' => $id]);
    }

    public function modifica_evento(WP_REST_Request $request) {
        $eid = (int) $request['id'];
        $cat = (int) $request->get_param('categoria');
        $data = sanitize_text_field((string) $request->get_param('data_evento'));
        $data_fine = sanitize_text_field((string) $request->get_param('data_fine'));
        $oi = sanitize_text_field((string) $request->get_param('ora_inizio'));
        $of = sanitize_text_field((string) $request->get_param('ora_fine'));
        $posti = (int) ($request->get_param('posti_totali') ?: 5);
        $enable_hub_sync = !empty($request->get_param('enable_hub_sync')) ? 1 : 0;
        $indirizzo_geocoding = sanitize_text_field((string) $request->get_param('indirizzo_geocoding'));
        $lat = sanitize_text_field((string) $request->get_param('event_latitude'));
        $lng = sanitize_text_field((string) $request->get_param('event_longitude'));

        if (!$eid || !$data) return new WP_Error('invalid', 'Data obbligatoria.', ['status' => 400]);
        if (!$data_fine || $data_fine < $data) $data_fine = $data;

        WS_Data::update_field('data_evento', $data, $eid);
        WS_Data::update_field('data_fine', $data_fine, $eid);
        WS_Data::update_field('ora_inizio', $oi, $eid);
        WS_Data::update_field('ora_fine', $of, $eid);
        WS_Data::update_field('posti_totali', $posti, $eid);
        WS_Data::update_field('enable_hub_sync', $enable_hub_sync, $eid);
        WS_Data::update_field('indirizzo_geocoding', $indirizzo_geocoding, $eid);
        WS_Data::update_field('event_latitude', $lat, $eid);
        WS_Data::update_field('event_longitude', $lng, $eid);
        if ($cat) wp_set_object_terms($eid, $cat, 'categoria_evento');
        $term = $cat ? get_term($cat, 'categoria_evento') : null;
        $tcat = ($term && !is_wp_error($term)) ? $term->name : get_the_title($eid);
        wp_update_post(['ID' => $eid, 'post_title' => $tcat . ' – ' . date_i18n('d/m/Y', strtotime($data))]);

        return new WP_REST_Response(['msg' => 'Evento aggiornato.']);
    }

    public function elimina_evento(WP_REST_Request $request): WP_REST_Response {
        $eid = (int) $request['id'];
        foreach (WS_Data::iscrizioni_evento($eid) as $isc) wp_delete_post($isc, true);
        wp_delete_post($eid, true);
        return new WP_REST_Response(['msg' => 'Evento e relative iscrizioni eliminati.']);
    }

    public function toggle_frontend(WP_REST_Request $request) {
        $eid = (int) $request['id'];
        if (get_post_type($eid) !== 'evento') {
            return new WP_Error('not_found', 'Evento non trovato', ['status' => 404]);
        }
        $nascosto = (bool) get_post_meta($eid, 'nascondi_dal_frontend', true);
        update_post_meta($eid, 'nascondi_dal_frontend', $nascosto ? '' : '1');
        return new WP_REST_Response([
            'msg' => $nascosto ? 'Evento ora visibile nel frontend.' : 'Evento nascosto dal frontend.',
            'nascosto' => !$nascosto,
        ]);
    }

    public function modifica_iscrizione(WP_REST_Request $request) {
        $isc = (int) $request['id'];
        if (get_post_type($isc) !== 'iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }

        $st_new = ((string) $request->get_param('stato') === 'confermato') ? 'confermato' : 'richiesta';
        $st_old = WS_Data::get_field('stato', $isc);
        $new_eid = (int) $request->get_param('evento');
        $curr_eid = (int) WS_Data::get_field('evento', $isc);
        $msg = '';

        if ($new_eid && $new_eid !== $curr_eid) {
            $s_new_ev = WS_Data::stato_posti($new_eid);
            if ($st_new === 'confermato' && $s_new_ev['sold_out']) {
                $msg = 'Impossibile spostare come Confermato: il nuovo evento è SOLD OUT.';
                $st_new = 'richiesta';
            }
            WS_Data::update_field('evento', $new_eid, $isc);
            $p = WS_Data::get_field('partecipante', $isc);
            $nome_p = $p ? (WS_Data::get_field('nome', $p) . ' ' . WS_Data::get_field('cognome', $p)) : get_the_title($isc);
            wp_update_post(['ID' => $isc, 'post_title' => $nome_p . ' → ' . WS_Data::evento_label($new_eid)]);
            if (!$msg) $msg = 'Iscrizione spostata su: ' . WS_Data::evento_label($new_eid) . '.';
        }

        if ($st_new === 'confermato' && $st_old !== 'confermato' && !$msg) {
            $check_eid = $new_eid ?: $curr_eid;
            $s_check = WS_Data::stato_posti($check_eid);
            if ($s_check['sold_out']) {
                $msg = 'Impossibile passare a Confermato: l\'evento è già SOLD OUT.';
                $st_new = 'richiesta';
            }
        }

        WS_Data::update_field('stato', $st_new, $isc);
        WS_Data::update_field('anticipo', (float) $request->get_param('anticipo'), $isc);
        WS_Data::update_field('saldo', (float) $request->get_param('saldo'), $isc);
        WS_Data::update_field('note', sanitize_textarea_field((string) $request->get_param('note')), $isc);
        update_post_meta($isc, 'num_persone', max(1, (int) $request->get_param('num_persone')));

        if (!$msg) $msg = 'Iscrizione aggiornata.';
        return new WP_REST_Response(['msg' => $msg]);
    }

    // ───────────────────────── TAB: Categorie ─────────────────────────

    /**
     * `tipo_categoria` is a plain term-meta field via WS_Data::update_field()/WS_Data::get_field(),
     * same ad-hoc pattern as url_pagina/foto_categoria/contesto_ai — none of
     * these have a formal ACF field group registered (verified: no
     * `acf-field-group` post for categoria_evento), so no UI field definition
     * to add either, just read/write the meta key directly.
     */
    private function get_tipi_map(): array {
        $saved = WS_Settings::get('event_types', ['Workshop', 'Viaggio Fotografico', 'Masterclass']);
        $map = [];
        foreach ($saved as $t) {
            $slug = sanitize_title($t);
            if ($slug) $map[$slug] = $t;
        }
        return $map;
    }

    private function sanitize_tipo(string $tipo): string {
        $map = $this->get_tipi_map();
        return isset($map[$tipo]) ? $tipo : '';
    }

    /**
     * `oggetto_conferma`/`mail_conferma` are the same term-meta keys the
     * legacy "Workshop Mail Center" panel (excluded from this migration)
     * already reads/writes on categoria_evento terms, and that
     * `wv_invia_conferma()` (still in the active "Workshop CRM 5" snippet)
     * reads when sending. Editing them here instead of in Mail Center is
     * purely a UI move — the sending mechanism (wp_mail + .ics attachment)
     * is unchanged, still no AI involved.
     */
    private const CONFERMA_PLACEHOLDERS = [
        '{nome}' => 'Nome del partecipante', '{cognome}' => 'Cognome', '{nome_completo}' => 'Nome + Cognome',
        '{email}' => 'Email del partecipante', '{telefono}' => 'Telefono', '{citta}' => 'Città di provenienza',
        '{categoria_nome}' => 'Nome categoria (es. NapoliVelata)', '{categoria_url}' => 'URL pagina presentazione categoria',
        '{periodo}' => 'Periodo evento (es. 9 – 12 Lug 2026)', '{data_inizio}' => 'Data inizio per esteso',
        '{data_fine}' => 'Data fine per esteso', '{ora_inizio}' => 'Ora inizio (HH:MM)', '{ora_fine}' => 'Ora fine (HH:MM)',
        '{posti_totali}' => 'Numero posti totali', '{posti_disponibili}' => 'Posti ancora disponibili',
        '{anticipo}' => 'Anticipo pagato (es. € 200,00)', '{saldo}' => 'Saldo pagato',
    ];

    public function get_categorie_tab(WP_REST_Request $request): WP_REST_Response {
        $edit_cat = (int) $request->get_param('edit_cat');

        $editing_categoria = null;
        if ($edit_cat) {
            $t = get_term($edit_cat, 'categoria_evento');
            if ($t && !is_wp_error($t)) {
                $editing_categoria = [
                    'id' => $edit_cat,
                    'nome' => $t->name,
                    'url' => WS_Data::get_field('url_pagina', 'categoria_evento_' . $edit_cat) ?: '',
                    'foto' => WS_Data::get_field('foto_categoria', 'categoria_evento_' . $edit_cat) ?: '',
                    'contesto_ai' => WS_Data::get_field('contesto_ai', 'categoria_evento_' . $edit_cat) ?: '',
                    'tipo' => $this->sanitize_tipo((string) WS_Data::get_field('tipo_categoria', 'categoria_evento_' . $edit_cat)),
                    'oggetto_conferma' => WS_Data::get_field('oggetto_conferma', 'categoria_evento_' . $edit_cat) ?: '',
                    'mail_conferma' => WS_Data::get_field('mail_conferma', 'categoria_evento_' . $edit_cat) ?: '',
                    'oggetto_t15' => WS_Data::get_field('oggetto_t15', 'categoria_evento_' . $edit_cat) ?: '',
                    'mail_t15' => WS_Data::get_field('mail_t15', 'categoria_evento_' . $edit_cat) ?: '',
                ];
            }
        }

        $categorie = [];
        foreach (get_terms(['taxonomy' => 'categoria_evento', 'hide_empty' => false]) as $t) {
            $categorie[] = [
                'id' => $t->term_id,
                'nome' => $t->name,
                'slug' => $t->slug,
                'url' => WS_Data::get_field('url_pagina', 'categoria_evento_' . $t->term_id) ?: '',
                'tipo' => $this->sanitize_tipo((string) WS_Data::get_field('tipo_categoria', 'categoria_evento_' . $t->term_id)),
                'count' => (int) $t->count,
            ];
        }

        $wp_pages = [];
        $raw_pages = get_pages(['post_status' => 'publish', 'sort_column' => 'post_title', 'sort_order' => 'ASC']);
        foreach ($raw_pages as $p) {
            $wp_pages[] = [
                'id'    => (int) $p->ID,
                'title' => (string) $p->post_title,
                'url'   => (string) get_permalink($p->ID),
            ];
        }

        return new WP_REST_Response([
            'editing_categoria' => $editing_categoria,
            'categorie' => $categorie,
            'tipi' => $this->get_tipi_map(),
            'pagine' => $wp_pages,
            'placeholders' => self::CONFERMA_PLACEHOLDERS,
            'default_oggetto_conferma' => '✓ Confermato · {categoria_nome} · {periodo}',
            'default_mail_conferma' => "Ciao {nome},\n\nsei ufficialmente confermato per {categoria_nome} del {periodo}.\n\nIn allegato il file calendario (.ics) per aggiungere l'evento al tuo Google o Apple Calendar.\n\nPer qualsiasi cosa, rispondimi a questa mail o scrivimi su WhatsApp.\n\nA presto,\nFrancesco",
            'default_oggetto_t15' => WS_T15_Reminder::default_oggetto(),
            'default_mail_t15' => WS_T15_Reminder::default_mail(),
        ]);
    }

    public function get_tipi_tab(): WP_REST_Response {
        $types = WS_Settings::get('event_types', ['Workshop', 'Viaggio Fotografico', 'Masterclass']);
        return new WP_REST_Response([
            'tipi' => $types,
        ]);
    }

    public function salva_tipi(WP_REST_Request $request): WP_REST_Response {
        $tipi = $request->get_param('tipi');
        if (!is_array($tipi)) $tipi = [];
        $clean = array_values(array_filter(array_map('sanitize_text_field', $tipi)));

        $all = WS_Settings::get_all();
        $all['event_types'] = $clean;
        WS_Settings::update_all($all);

        return new WP_REST_Response(['msg' => 'Tipi di evento salvati.', 'tipi' => $clean]);
    }

    private function save_base64_image(string $base64_data, string $filename_prefix = 'cat-img'): string {
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64_data, $type)) {
            return '';
        }
        $data = substr($base64_data, strpos($base64_data, ',') + 1);
        $type = strtolower($type[1]);
        if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp'])) {
            return '';
        }
        $data = base64_decode($data);
        if ($data === false) return '';

        $upload = wp_upload_bits($filename_prefix . '-' . time() . '.' . $type, null, $data);
        if (!empty($upload['error'])) {
            return '';
        }

        $wp_filetype = wp_check_filetype($upload['file'], null);
        $attachment = [
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_file_name(basename($upload['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ];
        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        if (!is_wp_error($attach_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
            wp_update_attachment_metadata($attach_id, $attach_data);
        }

        return (string) $upload['url'];
    }

    /**
     * Creates a real WP page for a categoria_evento on the spot, so the
     * admin doesn't have to leave the Categorie panel to set up its booking
     * page. Optionally pre-fills the page with the [eventi_categoria]
     * shortcode already tied to that category's slug, so the page is
     * immediately functional rather than just created-but-empty.
     */
    public function crea_pagina_categoria(WP_REST_Request $request) {
        $title = sanitize_text_field((string) $request->get_param('title'));
        if (!$title) return new WP_Error('invalid', 'Titolo obbligatorio.', ['status' => 400]);

        $tid = (int) $request->get_param('categoria_id');
        $term = $tid ? get_term($tid, 'categoria_evento') : null;
        if (!$tid || !$term || is_wp_error($term)) {
            return new WP_Error('invalid', 'Categoria non valida.', ['status' => 400]);
        }

        $add_shortcode = $request->get_param('add_shortcode');
        $add_shortcode = $add_shortcode === null ? true : (bool) $add_shortcode;
        $content = $add_shortcode ? '[eventi_categoria slug="' . $term->slug . '"]' : '';

        $page_id = wp_insert_post([
            'post_type'    => 'page',
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
        ], true);

        if (is_wp_error($page_id)) {
            return new WP_Error('failed', $page_id->get_error_message(), ['status' => 500]);
        }

        return new WP_REST_Response([
            'id'    => $page_id,
            'title' => $title,
            'url'   => get_permalink($page_id),
        ]);
    }

    public function crea_categoria(WP_REST_Request $request) {
        $nome = sanitize_text_field((string) $request->get_param('nome'));
        if (!$nome) return new WP_Error('invalid', 'Nome obbligatorio.', ['status' => 400]);

        $url = esc_url_raw((string) $request->get_param('url'));
        $foto_raw = (string) $request->get_param('foto');
        $foto = strpos($foto_raw, 'data:image/') === 0 ? $this->save_base64_image($foto_raw, 'categoria') : esc_url_raw($foto_raw);
        $contesto_ai = sanitize_textarea_field((string) $request->get_param('contesto_ai'));
        $tipo = $this->sanitize_tipo((string) $request->get_param('tipo'));
        $oggetto_conferma = sanitize_text_field((string) $request->get_param('oggetto_conferma'));
        $mail_conferma = sanitize_textarea_field((string) $request->get_param('mail_conferma'));
        $oggetto_t15 = sanitize_text_field((string) $request->get_param('oggetto_t15'));
        $mail_t15 = sanitize_textarea_field((string) $request->get_param('mail_t15'));

        $t = wp_insert_term($nome, 'categoria_evento');
        if (is_wp_error($t)) return new WP_Error('failed', $t->get_error_message(), ['status' => 500]);
        $tid = $t['term_id'];

        if ($url) {
            WS_Data::update_field('url_pagina', $url, 'categoria_evento_' . $tid);
            update_term_meta($tid, 'url_pagina', $url);
        }
        if ($foto) {
            WS_Data::update_field('foto_categoria', $foto, 'categoria_evento_' . $tid);
            update_term_meta($tid, 'foto_categoria', $foto);
        }
        if ($contesto_ai) WS_Data::update_field('contesto_ai', $contesto_ai, 'categoria_evento_' . $tid);
        if ($tipo) {
            WS_Data::update_field('tipo_categoria', $tipo, 'categoria_evento_' . $tid);
            update_term_meta($tid, 'tipo_categoria', $tipo);
        }
        if ($oggetto_conferma) WS_Data::update_field('oggetto_conferma', $oggetto_conferma, 'categoria_evento_' . $tid);
        if ($mail_conferma) WS_Data::update_field('mail_conferma', $mail_conferma, 'categoria_evento_' . $tid);
        if ($oggetto_t15) WS_Data::update_field('oggetto_t15', $oggetto_t15, 'categoria_evento_' . $tid);
        if ($mail_t15) WS_Data::update_field('mail_t15', $mail_t15, 'categoria_evento_' . $tid);

        return new WP_REST_Response(['msg' => 'Categoria creata.', 'id' => $tid]);
    }

    public function modifica_categoria(WP_REST_Request $request) {
        $tid = (int) $request['id'];
        $nome = sanitize_text_field((string) $request->get_param('nome'));
        if (!$tid || !$nome) return new WP_Error('invalid', 'Nome obbligatorio.', ['status' => 400]);

        $url = esc_url_raw((string) $request->get_param('url'));
        $foto_raw = (string) $request->get_param('foto');
        $foto = strpos($foto_raw, 'data:image/') === 0 ? $this->save_base64_image($foto_raw, 'categoria-' . $tid) : esc_url_raw($foto_raw);
        $contesto_ai = sanitize_textarea_field((string) $request->get_param('contesto_ai'));
        $tipo = $this->sanitize_tipo((string) $request->get_param('tipo'));
        $oggetto_conferma = sanitize_text_field((string) $request->get_param('oggetto_conferma'));
        $mail_conferma = sanitize_textarea_field((string) $request->get_param('mail_conferma'));
        $oggetto_t15 = sanitize_text_field((string) $request->get_param('oggetto_t15'));
        $mail_t15 = sanitize_textarea_field((string) $request->get_param('mail_t15'));

        wp_update_term($tid, 'categoria_evento', ['name' => $nome]);
        WS_Data::update_field('url_pagina', $url, 'categoria_evento_' . $tid);
        update_term_meta($tid, 'url_pagina', $url);
        WS_Data::update_field('foto_categoria', $foto, 'categoria_evento_' . $tid);
        update_term_meta($tid, 'foto_categoria', $foto);
        WS_Data::update_field('contesto_ai', $contesto_ai, 'categoria_evento_' . $tid);
        WS_Data::update_field('tipo_categoria', $tipo, 'categoria_evento_' . $tid);
        update_term_meta($tid, 'tipo_categoria', $tipo);
        WS_Data::update_field('oggetto_conferma', $oggetto_conferma, 'categoria_evento_' . $tid);
        WS_Data::update_field('mail_conferma', $mail_conferma, 'categoria_evento_' . $tid);
        WS_Data::update_field('oggetto_t15', $oggetto_t15, 'categoria_evento_' . $tid);
        WS_Data::update_field('mail_t15', $mail_t15, 'categoria_evento_' . $tid);

        return new WP_REST_Response(['msg' => 'Categoria aggiornata.']);
    }

    public function elimina_categoria(WP_REST_Request $request): WP_REST_Response {
        $tid = (int) $request['id'];
        $term = get_term($tid, 'categoria_evento');
        $n = ($term && !is_wp_error($term)) ? (int) $term->count : 0;
        if ($n > 0) {
            return new WP_REST_Response(['msg' => 'Categoria NON eliminata: ci sono ' . $n . ' eventi collegati.', 'deleted' => false]);
        }
        wp_delete_term($tid, 'categoria_evento');
        return new WP_REST_Response(['msg' => 'Categoria eliminata.', 'deleted' => true]);
    }

    public function get_modelli_locandine(WP_REST_Request $request): WP_REST_Response {
        $modelli = get_option('fvw_modelli_locandine', []);
        return new WP_REST_Response(['modelli' => is_array($modelli) ? array_values($modelli) : []]);
    }

    public function salva_modello_locandina(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();
        $id = !empty($params['id']) ? sanitize_key($params['id']) : 'mod_' . uniqid();
        $nome = !empty($params['nome']) ? sanitize_text_field($params['nome']) : 'Modello ' . date('d/m/Y H:i');

        $modelli = get_option('fvw_modelli_locandine', []);
        if (!is_array($modelli)) $modelli = [];

        $img_raw = !empty($params['imageUrl']) ? $params['imageUrl'] : (!empty($params['imageUrl1']) ? $params['imageUrl1'] : '');
        $img_sanitized = '';
        if (strpos($img_raw, 'data:image/') === 0) {
            $img_sanitized = $img_raw;
        } else if (!empty($img_raw)) {
            $img_sanitized = esc_url_raw($img_raw);
        }

        $item = [
            'id' => $id,
            'nome' => $nome,
            'format' => !empty($params['format']) ? sanitize_text_field($params['format']) : 'sq',
            'brand' => !empty($params['brand']) ? sanitize_text_field($params['brand']) : 'FRANCESCOVEROLINO',
            'brandFont' => !empty($params['brandFont']) ? sanitize_text_field($params['brandFont']) : '',
            'brandFontSize' => isset($params['brandFontSize']) ? (int)$params['brandFontSize'] : 28,
            'brandColor' => !empty($params['brandColor']) ? sanitize_text_field($params['brandColor']) : '#ffffff',
            'brandX' => isset($params['brandX']) ? (int)$params['brandX'] : 60,
            'brandY' => isset($params['brandY']) ? (int)$params['brandY'] : 75,

            'title' => !empty($params['title']) ? sanitize_text_field($params['title']) : '',
            'titleFont' => !empty($params['titleFont']) ? sanitize_text_field($params['titleFont']) : '',
            'titleFontSize' => isset($params['titleFontSize']) ? (int)$params['titleFontSize'] : null,
            'titleColor' => !empty($params['titleColor']) ? sanitize_text_field($params['titleColor']) : '#E11D48',
            'titleX' => isset($params['titleX']) ? (int)$params['titleX'] : null,
            'titleY' => isset($params['titleY']) ? (int)$params['titleY'] : null,

            'subtitle' => !empty($params['subtitle']) ? sanitize_text_field($params['subtitle']) : '',
            'subtitleFont' => !empty($params['subtitleFont']) ? sanitize_text_field($params['subtitleFont']) : '',
            'subtitleFontSize' => isset($params['subtitleFontSize']) ? (int)$params['subtitleFontSize'] : null,
            'subtitleColor' => !empty($params['subtitleColor']) ? sanitize_text_field($params['subtitleColor']) : '#ffffff',
            'subtitleX' => isset($params['subtitleX']) ? (int)$params['subtitleX'] : null,
            'subtitleY' => isset($params['subtitleY']) ? (int)$params['subtitleY'] : null,

            'dates' => !empty($params['dates']) ? sanitize_text_field($params['dates']) : '',
            'datesFont' => !empty($params['datesFont']) ? sanitize_text_field($params['datesFont']) : '',
            'datesFontSize' => isset($params['datesFontSize']) ? (int)$params['datesFontSize'] : null,
            'datesColor' => !empty($params['datesColor']) ? sanitize_text_field($params['datesColor']) : '#ffffff',
            'datesX' => isset($params['datesX']) ? (int)$params['datesX'] : null,
            'datesY' => isset($params['datesY']) ? (int)$params['datesY'] : null,

            'description' => !empty($params['description']) ? sanitize_textarea_field($params['description']) : '',
            'descFont' => !empty($params['descFont']) ? sanitize_text_field($params['descFont']) : '',
            'descFontSize' => isset($params['descFontSize']) ? (int)$params['descFontSize'] : null,
            'descColor' => !empty($params['descColor']) ? sanitize_text_field($params['descColor']) : 'rgba(255,255,255,0.85)',
            'descX' => isset($params['descX']) ? (int)$params['descX'] : null,
            'descY' => isset($params['descY']) ? (int)$params['descY'] : null,

            'imageUrl' => $img_sanitized,
            'imgScale' => isset($params['imgScale']) ? (float)$params['imgScale'] : 1.0,
            'imgOffsetX' => isset($params['imgOffsetX']) ? (int)$params['imgOffsetX'] : 0,
            'imgOffsetY' => isset($params['imgOffsetY']) ? (int)$params['imgOffsetY'] : 0,
            'darkOverlay' => isset($params['darkOverlay']) ? (float)$params['darkOverlay'] : 0.5,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $modelli[$id] = $item;
        update_option('fvw_modelli_locandine', $modelli);

        return new WP_REST_Response([
            'msg' => 'Modello salvato con successo!',
            'modelli' => array_values($modelli),
        ]);
    }

    public function elimina_modello_locandina(WP_REST_Request $request): WP_REST_Response {
        $id = sanitize_key((string)$request['id']);
        $modelli = get_option('fvw_modelli_locandine', []);
        if (is_array($modelli) && isset($modelli[$id])) {
            unset($modelli[$id]);
            update_option('fvw_modelli_locandine', $modelli);
        }
        return new WP_REST_Response([
            'msg' => 'Modello eliminato.',
            'modelli' => is_array($modelli) ? array_values($modelli) : [],
        ]);
    }
}
