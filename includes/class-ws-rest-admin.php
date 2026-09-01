<?php

if (!defined('ABSPATH')) exit;

/**
 * REST endpoints backing the Dashboard (workshop_admin) panel: the 3-tab
 * CRUD hub for partecipanti/eventi/categorie. Ports every legacy POST
 * action 1:1. Note: the legacy `invia_conferma` action defined in this
 * shortcode's handler had no corresponding UI button anywhere in its own
 * template (dead/unreachable code) — not ported here since nothing in the
 * new UI can trigger it either; the real "Conferma" button lives in
 * workshop_riepilogo and is served by WSMA_Rest_Riepilogo::conferma_iscrizione().
 */
final class WSMA_Rest_Admin implements WSMA_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        $perm = fn() => current_user_can('manage_options') || current_user_can('ws_access_panel');
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
        register_rest_route($ns, '/admin/sync-hub-now', ['methods' => 'POST', 'callback' => [$this, 'sync_hub_now'], 'permission_callback' => $perm]);
    }

    public function sync_hub_now(WP_REST_Request $request): WP_REST_Response {
        if (!class_exists('WSMA_Hub_Sync')) {
            require_once WSMA_PATH . 'includes/class-ws-hub-sync.php';
        }
        $results = WSMA_Hub_Sync::sync_all_published_events();
        return new WP_REST_Response([
            'success' => true,
            'synced'  => $results['synced'] ?? 0,
            'failed'  => $results['failed'] ?? 0,
            /* translators: %d: number of workshops synced */
            'msg'     => sprintf(__('Sincronizzazione completata: %d workshop inviati all\'Hub con successo!', 'wsmaker'), $results['synced'] ?? 0),
        ], 200);
    }

    // ───────────────────────── shared read helpers ─────────────────────────

    private function evento_option(int $id): array {
        $s = WSMA_Data::stato_posti($id);
        $prezzo = 0.0;
        $acconto = 0.0;
        $terms = get_the_terms($id, 'wsma_categoria_evento');
        if ($terms && !is_wp_error($terms)) {
            $prezzo = (float) WSMA_Data::get_field('prezzo', 'wsma_categoria_evento_' . $terms[0]->term_id);
            $acconto = (float) WSMA_Data::get_field('acconto', 'wsma_categoria_evento_' . $terms[0]->term_id);
        }
        return [
            'id' => $id,
            'label' => WSMA_Data::evento_label($id),
            'disponibili' => $s['disponibili'],
            'sold_out' => $s['sold_out'],
            'prezzo' => $prezzo,
            'acconto' => $acconto,
        ];
    }

    private function oggi(): string {
        return current_time('Y-m-d');
    }

    /**
     * Only fills in link_virtuale when it's empty and the chosen platform
     * is Jitsi — never overwrites a link the organizer already set or
     * pasted (Zoom/Meet/altro stay fully manual, as they always were).
     */
    private function maybe_generate_jitsi_room(string $piattaforma, string $link_virtuale, int $post_id): string {
        if ($link_virtuale || $piattaforma !== 'jitsi') return $link_virtuale;
        $slug = sanitize_title(wp_parse_url(home_url(), PHP_URL_HOST) ?: 'workshop');
        $room = $slug . '-' . $post_id . '-' . substr(md5(wp_generate_password(12, false)), 0, 8);
        return 'https://meet.jit.si/' . $room;
    }

    /**
     * Creates the [ws_aula_virtuale] page once per evento (only for
     * Jitsi — Zoom/Meet/altro keep using the raw external link directly,
     * no page needed). {luogo} then points here instead of the bare
     * meet.jit.si URL, so the emailed link opens our branding-free
     * embedded room instead of jitsi's own site.
     */
    private function maybe_create_aula_page(int $evento_id, string $piattaforma, string $link_virtuale): void {
        if ($piattaforma !== 'jitsi' || !$link_virtuale) return;

        $existing_page_id = (int) get_post_meta($evento_id, '_ws_aula_page_id', true);
        if ($existing_page_id && get_post_status($existing_page_id) && get_post_status($existing_page_id) !== 'trash') {
            return;
        }

        $page_id = wp_insert_post([
            'post_type' => 'page',
            'post_title' => WSMA_Data::evento_label($evento_id) ?: __('Aula virtuale', 'wsmaker'),
            'post_content' => '[ws_aula_virtuale evento_id="' . $evento_id . '"]',
            'post_status' => 'publish',
        ], true);
        if (is_wp_error($page_id)) return;

        update_post_meta($evento_id, '_ws_aula_page_id', $page_id);
        update_post_meta($page_id, '_ws_aula_page_for', $evento_id);
    }

    // ───────────────────────── TAB: Partecipanti ─────────────────────────

    public function get_partecipanti_tab(): WP_REST_Response {
        $oggi = $this->oggi();

        $in_programma = [];
        $args1 = apply_filters('wsma_scope_query_args', ['post_type' => 'wsma_evento', 'posts_per_page' => -1, 'no_found_rows' => true, 'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'ASC',
            'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']]], 'wsma_evento');
        $eq = new WP_Query($args1);
        foreach ($eq->posts as $ev) $in_programma[] = $this->evento_option($ev->ID);

        $conclusi = [];
        $args2 = apply_filters('wsma_scope_query_args', ['post_type' => 'wsma_evento', 'posts_per_page' => -1, 'no_found_rows' => true, 'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'DESC',
            'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '<', 'type' => 'DATE']]], 'wsma_evento');
        $eq2 = new WP_Query($args2);
        foreach ($eq2->posts as $ev) {
            $conclusi[] = ['id' => $ev->ID, 'label' => WSMA_Data::evento_label($ev->ID)];
        }

        $partecipanti = [];
        $args3 = apply_filters('wsma_scope_query_args', ['post_type' => 'wsma_partecipante', 'posts_per_page' => -1, 'no_found_rows' => true, 'orderby' => 'title', 'order' => 'ASC'], 'wsma_partecipante');
        $pq = new WP_Query($args3);
        if (!empty($pq->posts)) {
            update_postmeta_cache(wp_list_pluck($pq->posts, 'ID'));
            foreach ($pq->posts as $p) {
                $partecipanti[] = [
                    'id' => $p->ID,
                    'nome' => WSMA_Data::get_field('nome', $p->ID) ?: '',
                    'cognome' => WSMA_Data::get_field('cognome', $p->ID) ?: '',
                    'email' => WSMA_Data::get_field('email', $p->ID) ?: '',
                    'telefono' => WSMA_Data::get_field('telefono', $p->ID) ?: '',
                    'citta' => WSMA_Data::get_field('citta', $p->ID) ?: '',
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
        if (!apply_filters('wsma_scope_can_access_evento', true, $evento_id)) {
            return new WP_Error('forbidden', 'Non hai accesso a questo evento.', ['status' => 403]);
        }

        $aggiorna_anagrafica = !empty($request->get_param('aggiorna_anagrafica'));

        $pid = $existing ?: WSMA_Data::find_partecipante_by_email($email);
        $is_new = false;
        if (!$pid) {
            $is_new = true;
            $pid = wp_insert_post(['post_type' => 'wsma_partecipante', 'post_status' => 'publish', 'post_title' => trim($nome . ' ' . $cognome)]);
        }

        // Always update metadata if it's a new participant, or if explicitly requested, or populate only missing empty fields
        if ($is_new || $aggiorna_anagrafica) {
            WSMA_Data::update_field('nome', $nome, $pid);
            WSMA_Data::update_field('cognome', $cognome, $pid);
            if ($tel) WSMA_Data::update_field('telefono', $tel, $pid);
            if ($email) WSMA_Data::update_field('email', $email, $pid);
            if ($citta) WSMA_Data::update_field('citta', $citta, $pid);
            wp_update_post(['ID' => $pid, 'post_title' => trim($nome . ' ' . $cognome)]);
        } else {
            // Fill in missing fields only without overwriting existing non-empty values
            if (!WSMA_Data::get_field('nome', $pid) && $nome) WSMA_Data::update_field('nome', $nome, $pid);
            if (!WSMA_Data::get_field('cognome', $pid) && $cognome) WSMA_Data::update_field('cognome', $cognome, $pid);
            if (!WSMA_Data::get_field('telefono', $pid) && $tel) WSMA_Data::update_field('telefono', $tel, $pid);
            if (!WSMA_Data::get_field('email', $pid) && $email) WSMA_Data::update_field('email', $email, $pid);
            if (!WSMA_Data::get_field('citta', $pid) && $citta) WSMA_Data::update_field('citta', $citta, $pid);
        }

        if (WSMA_Data::find_iscrizione($pid, $evento_id)) {
            return new WP_REST_Response(['msg' => 'Questa persona è già iscritta a questo evento.']);
        }

        $isc = wp_insert_post(['post_type' => 'wsma_iscrizione', 'post_status' => 'publish',
            'post_title' => $nome . ' ' . $cognome . ' → ' . WSMA_Data::evento_label($evento_id)]);
        WSMA_Data::update_field('partecipante', $pid, $isc);
        WSMA_Data::update_field('evento', $evento_id, $isc);
        WSMA_Data::update_field('stato', $stato_iniziale, $isc);
        WSMA_Data::update_field('anticipo', $anticipo, $isc);
        WSMA_Data::update_field('saldo', $saldo, $isc);
        WSMA_Data::update_field('note', $note, $isc);
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

        if ($edit_ev && !apply_filters('wsma_scope_can_access_evento', true, $edit_ev)) {
            return new WP_REST_Response(['error' => 'Non hai accesso a questo evento.'], 403);
        }
        if ($edit_isc && !apply_filters('wsma_scope_can_access_iscrizione', true, $edit_isc)) {
            return new WP_REST_Response(['error' => 'Non hai accesso a questa iscrizione.'], 403);
        }

        $categorie = [];
        foreach (get_terms(['taxonomy' => 'wsma_categoria_evento', 'hide_empty' => false]) as $t) {
            $categorie[] = ['id' => $t->term_id, 'name' => $t->name];
        }

        $editing_evento = null;
        if ($edit_ev) {
            $terms = get_the_terms($edit_ev, 'wsma_categoria_evento');
            $editing_evento = [
                'id' => $edit_ev,
                'categoria_id' => $terms ? $terms[0]->term_id : 0,
                'data_evento' => WSMA_Data::get_field('data_evento', $edit_ev) ?: '',
                'data_fine' => WSMA_Data::get_field('data_fine', $edit_ev) ?: '',
                'ora_inizio' => WSMA_Data::get_field('ora_inizio', $edit_ev) ?: '',
                'ora_fine' => WSMA_Data::get_field('ora_fine', $edit_ev) ?: '',
                'posti_totali' => (int) WSMA_Data::get_field('posti_totali', $edit_ev) ?: 5,
                'modalita' => WSMA_Data::get_field('modalita', $edit_ev) ?: 'fisico',
                'piattaforma_virtuale' => WSMA_Data::get_field('piattaforma_virtuale', $edit_ev) ?: 'jitsi',
                'link_virtuale' => WSMA_Data::get_field('link_virtuale', $edit_ev) ?: '',
                'enable_hub_sync' => (bool) WSMA_Data::get_field('enable_hub_sync', $edit_ev),
                'indirizzo_geocoding' => (string) WSMA_Data::get_field('indirizzo_geocoding', $edit_ev) ?: '',
                'event_latitude' => (string) WSMA_Data::get_field('event_latitude', $edit_ev) ?: '',
                'event_longitude' => (string) WSMA_Data::get_field('event_longitude', $edit_ev) ?: '',
                'hub_status' => (string) WSMA_Data::get_field('hub_status', $edit_ev) ?: 'non_sincronizzato',
                // Injection point for PRO's WooCommerce connector (evento
                // lives in core, the connector doesn't) — mirrors the
                // ws_scope_* filter convention used above for Academy.
                'wc_extra' => apply_filters('wsma_evento_admin_wc_fields', ['active' => false, 'products' => [], 'product_id' => 0], $edit_ev),
            ];
        }

        $editing_iscrizione = null;
        if ($edit_isc) {
            $p = WSMA_Data::get_field('partecipante', $edit_isc);
            $curr_eid = (int) WSMA_Data::get_field('evento', $edit_isc);
            $curr_terms = get_the_terms($curr_eid, 'wsma_categoria_evento');
            $curr_cat = ($curr_terms && !is_wp_error($curr_terms)) ? $curr_terms[0]->term_id : 0;

            $ev_args = ['post_type' => 'wsma_evento', 'posts_per_page' => -1, 'no_found_rows' => true, 'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'ASC',
                'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']]];
            if ($curr_cat) $ev_args['tax_query'] = [['taxonomy' => 'wsma_categoria_evento', 'field' => 'term_id', 'terms' => $curr_cat]];
            $ev_args = apply_filters('wsma_scope_query_args', $ev_args, 'wsma_evento');
            $ev_tutti = new WP_Query($ev_args);
            $eventi_stessa_categoria = [];
            foreach ($ev_tutti->posts as $ev) $eventi_stessa_categoria[] = $this->evento_option($ev->ID);

            $editing_iscrizione = [
                'id' => $edit_isc,
                'nome' => $p ? trim(WSMA_Data::get_field('nome', $p) . ' ' . WSMA_Data::get_field('cognome', $p)) : get_the_title($edit_isc),
                'stato' => WSMA_Data::get_field('stato', $edit_isc) === 'confermato' ? 'confermato' : 'richiesta',
                'stato_pagamento' => in_array((string) WSMA_Data::get_field('stato_pagamento', $edit_isc), ['in_attesa', 'acconto_pagato', 'saldato'], true)
                    ? WSMA_Data::get_field('stato_pagamento', $edit_isc) : 'in_attesa',
                'curr_evento_id' => $curr_eid,
                'num_persone' => max(1, (int) get_post_meta($edit_isc, 'num_persone', true) ?: 1),
                'anticipo' => (float) WSMA_Data::get_field('anticipo', $edit_isc),
                'saldo' => (float) WSMA_Data::get_field('saldo', $edit_isc),
                'note' => (string) WSMA_Data::get_field('note', $edit_isc),
                'eventi_stessa_categoria' => $eventi_stessa_categoria,
            ];
        }

        // Filters the evento list to a single Modalità — used by the
        // "Aula Virtuale" admin entry point, which reuses this exact tab
        // pre-filtered instead of duplicating a separate panel.
        $modalita_filter = sanitize_text_field((string) $request->get_param('modalita'));

        if ($edit_isc) {
            $isc_eid = (int) WSMA_Data::get_field('evento', $edit_isc);
            $eq = new WP_Query(['post_type' => 'wsma_evento', 'posts_per_page' => 1, 'no_found_rows' => true, 'post__in' => [$isc_eid]]);
        } else {
            $meta_query = [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']];
            if (in_array($modalita_filter, ['fisico', 'virtuale'], true)) {
                $meta_query[] = ['key' => 'modalita', 'value' => $modalita_filter];
            }
            $main_args = apply_filters('wsma_scope_query_args', ['post_type' => 'wsma_evento', 'posts_per_page' => -1, 'no_found_rows' => true, 'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'ASC',
                'meta_query' => $meta_query], 'wsma_evento');
            $eq = new WP_Query($main_args);
        }
        $eventi = [];
        foreach ($eq->posts as $ev) {
            $id = $ev->ID;
            $s = WSMA_Data::stato_posti($id);
            $terms = get_the_terms($id, 'wsma_categoria_evento');
            $term = ($terms && !is_wp_error($terms)) ? $terms[0] : null;
            $cat_id = $term ? $term->term_id : 0;
            $cat_name = $term ? $term->name : '';
            $cat_tipo = $cat_id ? (string) (WSMA_Data::get_field('tipo_categoria', 'wsma_categoria_evento_' . $cat_id) ?: get_term_meta($cat_id, 'tipo_categoria', true)) : '';
            $cat_foto = $cat_id ? (string) (WSMA_Data::get_field('foto_categoria', 'wsma_categoria_evento_' . $cat_id) ?: get_term_meta($cat_id, 'foto_categoria', true)) : '';
            $d_inizio = (string) WSMA_Data::get_field('data_evento', $id);
            $d_fine = (string) WSMA_Data::get_field('data_fine', $id);

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
                'label' => WSMA_Data::evento_label($id),
                'categoria_nome' => $cat_name,
                'categoria_tipo' => $cat_tipo,
                'categoria_foto' => $cat_foto,
                'data_formattata' => $date_str,
                'occupati' => $s['occupati'], 'totali' => $s['totali'], 'sold_out' => $s['sold_out'],
                'n_richieste' => WSMA_Data::count_richieste($id),
                'nascosto' => (bool) get_post_meta($id, 'nascondi_dal_frontend', true),
                'enable_hub_sync' => (bool) WSMA_Data::get_field('enable_hub_sync', $id),
                'hub_status' => (string) WSMA_Data::get_field('hub_status', $id) ?: 'non_sincronizzato',
                'modalita' => (string) WSMA_Data::get_field('modalita', $id) ?: 'fisico',
                'link_virtuale' => (string) WSMA_Data::get_field('link_virtuale', $id) ?: '',
            ];
        }

        return new WP_REST_Response([
            'categorie' => $categorie,
            'editing_evento' => $editing_evento,
            'editing_iscrizione' => $editing_iscrizione,
            'eventi' => $eventi,
            'eventi_options' => $eventi,
            // Present at top level too (not just inside editing_evento) so
            // the "crea evento" form — no editing_evento yet — can still
            // show the WooCommerce product selector.
            'wc_extra' => apply_filters('wsma_evento_admin_wc_fields', ['active' => false, 'products' => [], 'product_id' => 0], $edit_ev),
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
        $modalita = sanitize_text_field((string) $request->get_param('modalita')) === 'virtuale' ? 'virtuale' : 'fisico';
        $piattaforma_virtuale = sanitize_text_field((string) $request->get_param('piattaforma_virtuale'));
        if (!in_array($piattaforma_virtuale, ['jitsi', 'zoom', 'meet', 'altro'], true)) $piattaforma_virtuale = 'jitsi';
        $link_virtuale = esc_url_raw((string) $request->get_param('link_virtuale'));

        $term = get_term($cat, 'wsma_categoria_evento');
        if (!$cat || !$data || !$term || is_wp_error($term)) {
            return new WP_Error('invalid', 'Categoria e data sono obbligatorie.', ['status' => 400]);
        }
        if (!$data_fine || $data_fine < $data) $data_fine = $data;

        $id = wp_insert_post(['post_type' => 'wsma_evento', 'post_status' => 'publish',
            'post_title' => $term->name . ' – ' . date_i18n('d/m/Y', strtotime($data))]);
        if (!$id) return new WP_Error('failed', 'Creazione evento fallita.', ['status' => 500]);

        if ($modalita === 'virtuale') $link_virtuale = $this->maybe_generate_jitsi_room($piattaforma_virtuale, $link_virtuale, $id);

        WSMA_Data::update_field('data_evento', $data, $id);
        WSMA_Data::update_field('data_fine', $data_fine, $id);
        WSMA_Data::update_field('ora_inizio', $oi, $id);
        WSMA_Data::update_field('ora_fine', $of, $id);
        WSMA_Data::update_field('posti_totali', $posti, $id);
        WSMA_Data::update_field('enable_hub_sync', $enable_hub_sync, $id);
        WSMA_Data::update_field('modalita', $modalita, $id);
        WSMA_Data::update_field('piattaforma_virtuale', $piattaforma_virtuale, $id);
        WSMA_Data::update_field('link_virtuale', $link_virtuale, $id);
        WSMA_Data::update_field('indirizzo_geocoding', $indirizzo_geocoding, $id);
        WSMA_Data::update_field('event_latitude', $lat, $id);
        WSMA_Data::update_field('event_longitude', $lng, $id);
        WSMA_Data::update_field('hub_status', 'non_sincronizzato', $id);
        wp_set_object_terms($id, $cat, 'wsma_categoria_evento');
        if ($modalita === 'virtuale') $this->maybe_create_aula_page($id, $piattaforma_virtuale, $link_virtuale);
        do_action('wsma_evento_admin_save_wc_field', $id, $request);

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
        $modalita = sanitize_text_field((string) $request->get_param('modalita')) === 'virtuale' ? 'virtuale' : 'fisico';
        $piattaforma_virtuale = sanitize_text_field((string) $request->get_param('piattaforma_virtuale'));
        if (!in_array($piattaforma_virtuale, ['jitsi', 'zoom', 'meet', 'altro'], true)) $piattaforma_virtuale = 'jitsi';
        $link_virtuale = esc_url_raw((string) $request->get_param('link_virtuale'));

        if (!$eid || !$data) return new WP_Error('invalid', 'Data obbligatoria.', ['status' => 400]);
        if (!apply_filters('wsma_scope_can_access_evento', true, $eid)) {
            return new WP_Error('forbidden', 'Non hai accesso a questo evento.', ['status' => 403]);
        }
        if (!$data_fine || $data_fine < $data) $data_fine = $data;

        if ($modalita === 'virtuale') $link_virtuale = $this->maybe_generate_jitsi_room($piattaforma_virtuale, $link_virtuale, $eid);

        WSMA_Data::update_field('data_evento', $data, $eid);
        WSMA_Data::update_field('data_fine', $data_fine, $eid);
        WSMA_Data::update_field('ora_inizio', $oi, $eid);
        WSMA_Data::update_field('ora_fine', $of, $eid);
        WSMA_Data::update_field('posti_totali', $posti, $eid);
        WSMA_Data::update_field('enable_hub_sync', $enable_hub_sync, $eid);
        WSMA_Data::update_field('modalita', $modalita, $eid);
        WSMA_Data::update_field('piattaforma_virtuale', $piattaforma_virtuale, $eid);
        WSMA_Data::update_field('link_virtuale', $link_virtuale, $eid);
        WSMA_Data::update_field('indirizzo_geocoding', $indirizzo_geocoding, $eid);
        WSMA_Data::update_field('event_latitude', $lat, $eid);
        WSMA_Data::update_field('event_longitude', $lng, $eid);
        if ($cat) wp_set_object_terms($eid, $cat, 'wsma_categoria_evento');
        $term = $cat ? get_term($cat, 'wsma_categoria_evento') : null;
        $tcat = ($term && !is_wp_error($term)) ? $term->name : get_the_title($eid);
        wp_update_post(['ID' => $eid, 'post_title' => $tcat . ' – ' . date_i18n('d/m/Y', strtotime($data))]);
        if ($modalita === 'virtuale') $this->maybe_create_aula_page($eid, $piattaforma_virtuale, $link_virtuale);
        do_action('wsma_evento_admin_save_wc_field', $eid, $request);

        return new WP_REST_Response(['msg' => 'Evento aggiornato.']);
    }

    public function elimina_evento(WP_REST_Request $request): WP_REST_Response {
        $eid = (int) $request['id'];
        if (!apply_filters('wsma_scope_can_access_evento', true, $eid)) {
            return new WP_REST_Response(['msg' => 'Non hai accesso a questo evento.'], 403);
        }
        foreach (WSMA_Data::iscrizioni_evento($eid) as $isc) wp_delete_post($isc, true);
        wp_delete_post($eid, true);
        return new WP_REST_Response(['msg' => 'Evento e relative iscrizioni eliminati.']);
    }

    public function toggle_frontend(WP_REST_Request $request) {
        $eid = (int) $request['id'];
        if (get_post_type($eid) !== 'wsma_evento') {
            return new WP_Error('not_found', 'Evento non trovato', ['status' => 404]);
        }
        if (!apply_filters('wsma_scope_can_access_evento', true, $eid)) {
            return new WP_Error('forbidden', 'Non hai accesso a questo evento.', ['status' => 403]);
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
        if (get_post_type($isc) !== 'wsma_iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }
        if (!apply_filters('wsma_scope_can_access_iscrizione', true, $isc)) {
            return new WP_Error('forbidden', 'Non hai accesso a questa iscrizione.', ['status' => 403]);
        }

        $st_new = ((string) $request->get_param('stato') === 'confermato') ? 'confermato' : 'richiesta';
        $st_old = WSMA_Data::get_field('stato', $isc);
        $new_eid = (int) $request->get_param('evento');
        $curr_eid = (int) WSMA_Data::get_field('evento', $isc);
        $msg = '';

        if ($new_eid && $new_eid !== $curr_eid) {
            $s_new_ev = WSMA_Data::stato_posti($new_eid);
            if ($st_new === 'confermato' && $s_new_ev['sold_out']) {
                $msg = 'Impossibile spostare come Confermato: il nuovo evento è SOLD OUT.';
                $st_new = 'richiesta';
            }
            WSMA_Data::update_field('evento', $new_eid, $isc);
            $p = WSMA_Data::get_field('partecipante', $isc);
            $nome_p = $p ? (WSMA_Data::get_field('nome', $p) . ' ' . WSMA_Data::get_field('cognome', $p)) : get_the_title($isc);
            wp_update_post(['ID' => $isc, 'post_title' => $nome_p . ' → ' . WSMA_Data::evento_label($new_eid)]);
            if (!$msg) $msg = 'Iscrizione spostata su: ' . WSMA_Data::evento_label($new_eid) . '.';
        }

        if ($st_new === 'confermato' && $st_old !== 'confermato' && !$msg) {
            $check_eid = $new_eid ?: $curr_eid;
            $s_check = WSMA_Data::stato_posti($check_eid);
            if ($s_check['sold_out']) {
                $msg = 'Impossibile passare a Confermato: l\'evento è già SOLD OUT.';
                $st_new = 'richiesta';
            }
        }

        WSMA_Data::update_field('stato', $st_new, $isc);
        $sp_new = (string) $request->get_param('stato_pagamento');
        if (in_array($sp_new, ['in_attesa', 'acconto_pagato', 'saldato'], true)) {
            WSMA_Data::update_field('stato_pagamento', $sp_new, $isc);
        }
        WSMA_Data::update_field('anticipo', (float) $request->get_param('anticipo'), $isc);
        WSMA_Data::update_field('saldo', (float) $request->get_param('saldo'), $isc);
        WSMA_Data::update_field('note', sanitize_textarea_field((string) $request->get_param('note')), $isc);
        update_post_meta($isc, 'num_persone', max(1, (int) $request->get_param('num_persone')));

        if (!$msg) $msg = 'Iscrizione aggiornata.';
        return new WP_REST_Response(['msg' => $msg]);
    }

    // ───────────────────────── TAB: Categorie ─────────────────────────

    /**
     * `tipo_categoria` is a plain term-meta field via WSMA_Data::update_field()/WSMA_Data::get_field(),
     * same ad-hoc pattern as url_pagina/foto_categoria/contesto_ai — none of
     * these have a formal ACF field group registered (verified: no
     * `acf-field-group` post for categoria_evento), so no UI field definition
     * to add either, just read/write the meta key directly.
     */
    private function get_tipi_map(): array {
        $saved = WSMA_Settings::get('event_types', ['Workshop', 'Viaggio Fotografico', 'Masterclass']);
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
        '{luogo}' => 'Link Aula virtuale (se online) o indirizzo (se in presenza)',
        '{anticipo}' => 'Anticipo pagato (es. € 200,00)', '{saldo}' => 'Saldo pagato',
    ];

    public function get_categorie_tab(WP_REST_Request $request): WP_REST_Response {
        $edit_cat = (int) $request->get_param('edit_cat');
        if ($edit_cat && !apply_filters('wsma_scope_can_access_categoria', true, $edit_cat)) {
            return new WP_REST_Response(['error' => 'Non hai accesso a questa categoria.'], 403);
        }

        $editing_categoria = null;
        if ($edit_cat) {
            $t = get_term($edit_cat, 'wsma_categoria_evento');
            if ($t && !is_wp_error($t)) {
                $editing_categoria = [
                    'id' => $edit_cat,
                    'nome' => $t->name,
                    'slug' => $t->slug,
                    'url' => WSMA_Data::get_field('url_pagina', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'foto' => WSMA_Data::get_field('foto_categoria', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'contesto_ai' => WSMA_Data::get_field('contesto_ai', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'tipo' => $this->sanitize_tipo((string) WSMA_Data::get_field('tipo_categoria', 'wsma_categoria_evento_' . $edit_cat)),
                    'oggetto_conferma' => WSMA_Data::get_field('oggetto_conferma', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'mail_conferma' => WSMA_Data::get_field('mail_conferma', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'oggetto_t15' => WSMA_Data::get_field('oggetto_t15', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'mail_t15' => WSMA_Data::get_field('mail_t15', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'prezzo' => (float) WSMA_Data::get_field('prezzo', 'wsma_categoria_evento_' . $edit_cat),
                    'acconto' => (float) WSMA_Data::get_field('acconto', 'wsma_categoria_evento_' . $edit_cat),
                    'citta' => WSMA_Data::get_field('citta', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'nazione' => WSMA_Data::get_field('nazione', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'indirizzo' => WSMA_Data::get_field('indirizzo', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'intro' => WSMA_Data::get_field('intro', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'program' => WSMA_Data::get_field('program', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'requirements' => WSMA_Data::get_field('requirements', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'important_notes' => WSMA_Data::get_field('important_notes', 'wsma_categoria_evento_' . $edit_cat) ?: '',
                    'fb_share_enabled' => (bool) WSMA_Data::get_field('fb_share_enabled', 'wsma_categoria_evento_' . $edit_cat),
                ];
            }
        }

        $categorie = [];
        $tutte_le_categorie = apply_filters('wsma_scope_filter_terms', get_terms(['taxonomy' => 'wsma_categoria_evento', 'hide_empty' => false]), 'categoria');
        foreach ($tutte_le_categorie as $t) {
            $prossimo = null;
            $pq = new WP_Query([
                'post_type' => 'wsma_evento', 'posts_per_page' => 1, 'no_found_rows' => true,
                'tax_query' => [['taxonomy' => 'wsma_categoria_evento', 'field' => 'term_id', 'terms' => $t->term_id]],
                'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'ASC',
                'meta_query' => [['key' => 'data_fine', 'value' => $this->oggi(), 'compare' => '>=', 'type' => 'DATE']],
            ]);
            if ($pq->have_posts()) {
                $prossimo = WSMA_Data::format_periodo($pq->posts[0]->ID);
            }

            $categorie[] = [
                'id' => $t->term_id,
                'nome' => $t->name,
                'slug' => $t->slug,
                'url' => WSMA_Data::get_field('url_pagina', 'wsma_categoria_evento_' . $t->term_id) ?: '',
                'foto' => WSMA_Data::get_field('foto_categoria', 'wsma_categoria_evento_' . $t->term_id) ?: '',
                'tipo' => $this->sanitize_tipo((string) WSMA_Data::get_field('tipo_categoria', 'wsma_categoria_evento_' . $t->term_id)),
                'count' => (int) $t->count,
                'prezzo' => (float) WSMA_Data::get_field('prezzo', 'wsma_categoria_evento_' . $t->term_id),
                'acconto' => (float) WSMA_Data::get_field('acconto', 'wsma_categoria_evento_' . $t->term_id),
                'citta' => WSMA_Data::get_field('citta', 'wsma_categoria_evento_' . $t->term_id) ?: '',
                'prossimo_evento' => $prossimo,
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
            'default_oggetto_t15' => WSMA_T15_Reminder::default_oggetto(),
            'default_mail_t15' => WSMA_T15_Reminder::default_mail(),
        ]);
    }

    public function get_tipi_tab(): WP_REST_Response {
        $types = WSMA_Settings::get('event_types', ['Workshop', 'Viaggio Fotografico', 'Masterclass']);
        return new WP_REST_Response([
            'tipi' => $types,
        ]);
    }

    public function salva_tipi(WP_REST_Request $request): WP_REST_Response {
        $tipi = $request->get_param('tipi');
        if (!is_array($tipi)) $tipi = [];
        $clean = array_values(array_filter(array_map('sanitize_text_field', $tipi)));

        $all = WSMA_Settings::get_all();
        $all['event_types'] = $clean;
        WSMA_Settings::update_all($all);

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
        $term = $tid ? get_term($tid, 'wsma_categoria_evento') : null;
        if (!$tid || !$term || is_wp_error($term)) {
            return new WP_Error('invalid', 'Categoria non valida.', ['status' => 400]);
        }
        if (!apply_filters('wsma_scope_can_access_categoria', true, $tid)) {
            return new WP_Error('forbidden', 'Non hai accesso a questa categoria.', ['status' => 403]);
        }

        $add_shortcode = $request->get_param('add_shortcode');
        $add_shortcode = $add_shortcode === null ? true : (bool) $add_shortcode;

        $page_id = wp_insert_post([
            'post_type'    => 'page',
            'post_title'   => $title,
            'post_content' => '',
            'post_status'  => 'publish',
        ], true);

        if (is_wp_error($page_id)) {
            return new WP_Error('failed', $page_id->get_error_message(), ['status' => 500]);
        }

        if ($add_shortcode) {
            // The "+ Crea Pagina" button builds a brand-new blank page, so it
            // gets the new all-in-one shortcode (hero + intro/programma/
            // requisiti/note + eventi + form) as the predefined default.
            // ensure_categoria_shortcodes() (old eventi_categoria + ws_form_iscrizione
            // combo) is untouched and still runs for organizers linking an
            // existing, manually-built page instead — see crea_categoria/modifica_categoria.
            wp_update_post(['ID' => $page_id, 'post_content' => '[ws_workshop_page slug="' . $term->slug . '"]']);
        }

        return new WP_REST_Response([
            'id'    => $page_id,
            'title' => $title,
            'url'   => get_permalink($page_id),
        ]);
    }

    /**
     * Ensures a categoria's linked page carries both shortcodes
     * ([eventi_categoria] and the registration form), without duplicating
     * either one if already present — preserves any existing layout/text.
     */
    private function ensure_categoria_shortcodes(int $page_id, string $slug): void {
        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') return;

        $content = (string) $page->post_content;

        // Page builders like YOOtheme Pro store the entire visual layout as
        // a JSON blob inside an HTML comment (`<!-- {"name":...} -->`) that
        // MUST be the absolute last thing in post_content for their editor
        // to recognize the page. Appending shortcodes after it broke the
        // builder entirely (page showed as "empty"/incompatible). Since we
        // can't safely insert content *inside* that comment, the only safe
        // move is to not touch a page that already has one — the user adds
        // the shortcodes manually inside the builder in that case.
        if (strpos($content, '<!-- {"') !== false || strpos($content, '<!-- {') !== false) {
            return;
        }

        $additions = [];

        if (strpos($content, '[eventi_categoria') === false) {
            $additions[] = '[eventi_categoria slug="' . $slug . '"]';
        }

        $has_form = strpos($content, '[ws_form_iscrizione') !== false
            || strpos($content, '[fv_form_iscrizione') !== false
            || strpos($content, '[fvw_form_iscrizione') !== false;
        if (!$has_form) {
            $additions[] = '[ws_form_iscrizione categoria="' . $slug . '"]';
        }

        if (empty($additions)) return;

        $new_content = trim($content) !== ''
            ? rtrim($content) . "\n\n" . implode("\n\n", $additions)
            : implode("\n\n", $additions);

        wp_update_post(['ID' => $page_id, 'post_content' => $new_content]);
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
        $prezzo = (float) $request->get_param('prezzo');
        $acconto = (float) $request->get_param('acconto');
        $citta = sanitize_text_field((string) $request->get_param('citta'));
        $nazione = sanitize_text_field((string) $request->get_param('nazione'));
        $indirizzo = sanitize_text_field((string) $request->get_param('indirizzo'));
        $intro = sanitize_textarea_field((string) $request->get_param('intro'));
        $program = sanitize_textarea_field((string) $request->get_param('program'));
        $requirements = sanitize_textarea_field((string) $request->get_param('requirements'));
        $important_notes = sanitize_textarea_field((string) $request->get_param('important_notes'));
        $fb_share_enabled = !empty($request->get_param('fb_share_enabled')) ? 1 : 0;

        $t = wp_insert_term($nome, 'wsma_categoria_evento');
        if (is_wp_error($t)) return new WP_Error('failed', $t->get_error_message(), ['status' => 500]);
        $tid = $t['term_id'];
        // A scoped docente's own new category must be owned by them from
        // the start — otherwise it would be immediately invisible to its
        // own creator once the scoping filter (owned categories only) applies.
        do_action('wsma_scope_assign_new_owner', 'categoria', $tid);

        if ($url) {
            WSMA_Data::update_field('url_pagina', $url, 'wsma_categoria_evento_' . $tid);
            update_term_meta($tid, 'url_pagina', $url);
            $linked_page_id = url_to_postid($url);
            if ($linked_page_id) {
                // wp_insert_term()'s return array has no 'slug' key, only
                // term_id/term_taxonomy_id — fetch the real term for it.
                $fresh_term = get_term($tid, 'wsma_categoria_evento');
                if ($fresh_term && !is_wp_error($fresh_term)) {
                    $this->ensure_categoria_shortcodes($linked_page_id, $fresh_term->slug);
                }
            }
        }
        if ($foto) {
            WSMA_Data::update_field('foto_categoria', $foto, 'wsma_categoria_evento_' . $tid);
            update_term_meta($tid, 'foto_categoria', $foto);
        }
        if ($contesto_ai) WSMA_Data::update_field('contesto_ai', $contesto_ai, 'wsma_categoria_evento_' . $tid);
        if ($tipo) {
            WSMA_Data::update_field('tipo_categoria', $tipo, 'wsma_categoria_evento_' . $tid);
            update_term_meta($tid, 'tipo_categoria', $tipo);
        }
        if ($oggetto_conferma) WSMA_Data::update_field('oggetto_conferma', $oggetto_conferma, 'wsma_categoria_evento_' . $tid);
        if ($mail_conferma) WSMA_Data::update_field('mail_conferma', $mail_conferma, 'wsma_categoria_evento_' . $tid);
        if ($oggetto_t15) WSMA_Data::update_field('oggetto_t15', $oggetto_t15, 'wsma_categoria_evento_' . $tid);
        if ($mail_t15) WSMA_Data::update_field('mail_t15', $mail_t15, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('prezzo', $prezzo, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('acconto', $acconto, 'wsma_categoria_evento_' . $tid);
        if ($citta) WSMA_Data::update_field('citta', $citta, 'wsma_categoria_evento_' . $tid);
        if ($nazione) WSMA_Data::update_field('nazione', $nazione, 'wsma_categoria_evento_' . $tid);
        if ($indirizzo) WSMA_Data::update_field('indirizzo', $indirizzo, 'wsma_categoria_evento_' . $tid);
        if ($intro) WSMA_Data::update_field('intro', $intro, 'wsma_categoria_evento_' . $tid);
        if ($program) WSMA_Data::update_field('program', $program, 'wsma_categoria_evento_' . $tid);
        if ($requirements) WSMA_Data::update_field('requirements', $requirements, 'wsma_categoria_evento_' . $tid);
        if ($important_notes) WSMA_Data::update_field('important_notes', $important_notes, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('fb_share_enabled', $fb_share_enabled, 'wsma_categoria_evento_' . $tid);

        return new WP_REST_Response(['msg' => 'Categoria creata.', 'id' => $tid]);
    }

    public function modifica_categoria(WP_REST_Request $request) {
        $tid = (int) $request['id'];
        $nome = sanitize_text_field((string) $request->get_param('nome'));
        if (!$tid || !$nome) return new WP_Error('invalid', 'Nome obbligatorio.', ['status' => 400]);
        if (!apply_filters('wsma_scope_can_access_categoria', true, $tid)) {
            return new WP_Error('forbidden', 'Non hai accesso a questa categoria.', ['status' => 403]);
        }

        $url = esc_url_raw((string) $request->get_param('url'));
        $foto_raw = (string) $request->get_param('foto');
        $foto = strpos($foto_raw, 'data:image/') === 0 ? $this->save_base64_image($foto_raw, 'categoria-' . $tid) : esc_url_raw($foto_raw);
        $contesto_ai = sanitize_textarea_field((string) $request->get_param('contesto_ai'));
        $tipo = $this->sanitize_tipo((string) $request->get_param('tipo'));
        $oggetto_conferma = sanitize_text_field((string) $request->get_param('oggetto_conferma'));
        $mail_conferma = sanitize_textarea_field((string) $request->get_param('mail_conferma'));
        $oggetto_t15 = sanitize_text_field((string) $request->get_param('oggetto_t15'));
        $mail_t15 = sanitize_textarea_field((string) $request->get_param('mail_t15'));
        $prezzo = (float) $request->get_param('prezzo');
        $acconto = (float) $request->get_param('acconto');
        $citta = sanitize_text_field((string) $request->get_param('citta'));
        $nazione = sanitize_text_field((string) $request->get_param('nazione'));
        $indirizzo = sanitize_text_field((string) $request->get_param('indirizzo'));
        $intro = sanitize_textarea_field((string) $request->get_param('intro'));
        $program = sanitize_textarea_field((string) $request->get_param('program'));
        $requirements = sanitize_textarea_field((string) $request->get_param('requirements'));
        $important_notes = sanitize_textarea_field((string) $request->get_param('important_notes'));
        $fb_share_enabled = !empty($request->get_param('fb_share_enabled')) ? 1 : 0;

        wp_update_term($tid, 'wsma_categoria_evento', ['name' => $nome]);
        WSMA_Data::update_field('url_pagina', $url, 'wsma_categoria_evento_' . $tid);
        update_term_meta($tid, 'url_pagina', $url);
        if ($url) {
            $linked_page_id = url_to_postid($url);
            if ($linked_page_id) {
                $fresh_term = get_term($tid, 'wsma_categoria_evento');
                if ($fresh_term && !is_wp_error($fresh_term)) {
                    $this->ensure_categoria_shortcodes($linked_page_id, $fresh_term->slug);
                }
            }
        }
        WSMA_Data::update_field('foto_categoria', $foto, 'wsma_categoria_evento_' . $tid);
        update_term_meta($tid, 'foto_categoria', $foto);
        WSMA_Data::update_field('contesto_ai', $contesto_ai, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('tipo_categoria', $tipo, 'wsma_categoria_evento_' . $tid);
        update_term_meta($tid, 'tipo_categoria', $tipo);
        WSMA_Data::update_field('oggetto_conferma', $oggetto_conferma, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('mail_conferma', $mail_conferma, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('oggetto_t15', $oggetto_t15, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('mail_t15', $mail_t15, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('prezzo', $prezzo, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('acconto', $acconto, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('citta', $citta, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('nazione', $nazione, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('indirizzo', $indirizzo, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('intro', $intro, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('program', $program, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('requirements', $requirements, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('important_notes', $important_notes, 'wsma_categoria_evento_' . $tid);
        WSMA_Data::update_field('fb_share_enabled', $fb_share_enabled, 'wsma_categoria_evento_' . $tid);

        return new WP_REST_Response(['msg' => 'Categoria aggiornata.']);
    }

    public function elimina_categoria(WP_REST_Request $request): WP_REST_Response {
        $tid = (int) $request['id'];
        if (!apply_filters('wsma_scope_can_access_categoria', true, $tid)) {
            return new WP_REST_Response(['msg' => 'Non hai accesso a questa categoria.', 'deleted' => false], 403);
        }
        $term = get_term($tid, 'wsma_categoria_evento');
        $n = ($term && !is_wp_error($term)) ? (int) $term->count : 0;
        if ($n > 0) {
            return new WP_REST_Response(['msg' => 'Categoria NON eliminata: ci sono ' . $n . ' eventi collegati.', 'deleted' => false]);
        }
        wp_delete_term($tid, 'wsma_categoria_evento');
        return new WP_REST_Response(['msg' => 'Categoria eliminata.', 'deleted' => true]);
    }

    /** Reads the option, migrating silently from the legacy key(s) on first access. */
    private function get_modelli_locandine_option(): array {
        $modelli = get_option('wsma_modelli_locandine', false);
        if ($modelli === false) {
            $legacy = get_option('ws_modelli_locandine', []);
            if (empty($legacy)) {
                $legacy = get_option('fvw_modelli_locandine', []);
            }
            if (!empty($legacy)) {
                update_option('wsma_modelli_locandine', $legacy);
            }
            $modelli = $legacy;
        }
        return is_array($modelli) ? $modelli : [];
    }

    public function get_modelli_locandine(WP_REST_Request $request): WP_REST_Response {
        $modelli = $this->get_modelli_locandine_option();
        return new WP_REST_Response(['modelli' => is_array($modelli) ? array_values($modelli) : []]);
    }

    public function salva_modello_locandina(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();
        $id = !empty($params['id']) ? sanitize_key($params['id']) : 'mod_' . uniqid();
        $nome = !empty($params['nome']) ? sanitize_text_field($params['nome']) : 'Modello ' . current_time('d/m/Y H:i');

        $modelli = $this->get_modelli_locandine_option();
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
            'updated_at' => current_time('Y-m-d H:i:s'),
        ];

        $modelli[$id] = $item;
        update_option('wsma_modelli_locandine', $modelli);

        return new WP_REST_Response([
            'msg' => 'Modello salvato con successo!',
            'modelli' => array_values($modelli),
        ]);
    }

    public function elimina_modello_locandina(WP_REST_Request $request): WP_REST_Response {
        $id = sanitize_key((string)$request['id']);
        $modelli = $this->get_modelli_locandine_option();
        if (is_array($modelli) && isset($modelli[$id])) {
            unset($modelli[$id]);
            update_option('wsma_modelli_locandine', $modelli);
        }
        return new WP_REST_Response([
            'msg' => 'Modello eliminato.',
            'modelli' => is_array($modelli) ? array_values($modelli) : [],
        ]);
    }
}
