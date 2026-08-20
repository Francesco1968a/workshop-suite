<?php

if (!defined('ABSPATH')) exit;

/**
 * Registers the core data model: CPTs (`evento`, `partecipante`,
 * `iscrizione`), taxonomy (`categoria_evento`), and their base ACF field
 * groups. Ported 1:1 (verbatim, same field keys) from the legacy
 * "Workshop CRM 1" snippet — this is the single most foundational piece
 * of the whole system (every other panel/query depends on these post
 * types existing), so it's ported and verified working *before*
 * deactivating the legacy snippet, not after.
 */
final class WS_Post_Types implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('init', [$this, 'register_post_types']);
        add_action('acf/init', [$this, 'register_fields']);
    }

    public function register_post_types(): void {
        register_post_type('evento', [
            'labels' => [
                'name'          => __('Eventi', 'workshop-suite'),
                'singular_name' => __('Evento', 'workshop-suite'),
            ],
            'public' => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon' => 'dashicons-calendar-alt', 'supports' => ['title'], 'has_archive' => false,
        ]);
        register_post_type('partecipante', [
            'labels' => [
                'name'          => __('Partecipanti', 'workshop-suite'),
                'singular_name' => __('Partecipante', 'workshop-suite'),
            ],
            'public' => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon' => 'dashicons-groups', 'supports' => ['title'], 'has_archive' => false,
        ]);
        register_post_type('iscrizione', [
            'labels' => [
                'name'          => __('Iscrizioni', 'workshop-suite'),
                'singular_name' => __('Iscrizione', 'workshop-suite'),
            ],
            'public' => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon' => 'dashicons-tickets-alt', 'supports' => ['title'], 'has_archive' => false,
        ]);
        register_taxonomy('categoria_evento', ['evento'], [
            'labels' => [
                'name'          => __('Categorie Workshop', 'workshop-suite'),
                'singular_name' => __('Categoria', 'workshop-suite'),
            ],
            'hierarchical' => true, 'show_ui' => true, 'show_admin_column' => true,
        ]);
    }

    public function register_fields(): void {
        if (!function_exists('acf_add_local_field_group')) return;

        // EVENTO
        acf_add_local_field_group([
            'key' => 'group_evento', 'title' => 'Dati Evento',
            'fields' => [
                ['key' => 'field_ev_data', 'label' => 'Data inizio', 'name' => 'data_evento', 'type' => 'date_picker', 'display_format' => 'd/m/Y', 'return_format' => 'Y-m-d', 'required' => 1],
                ['key' => 'field_ev_data_fine', 'label' => 'Data fine', 'name' => 'data_fine', 'type' => 'date_picker', 'display_format' => 'd/m/Y', 'return_format' => 'Y-m-d'],
                ['key' => 'field_ev_ora_inizio', 'label' => 'Ora inizio', 'name' => 'ora_inizio', 'type' => 'time_picker', 'display_format' => 'H:i', 'return_format' => 'H:i'],
                ['key' => 'field_ev_ora_fine', 'label' => 'Ora fine', 'name' => 'ora_fine', 'type' => 'time_picker', 'display_format' => 'H:i', 'return_format' => 'H:i'],
                ['key' => 'field_ev_posti', 'label' => 'Posti totali', 'name' => 'posti_totali', 'type' => 'number', 'min' => 1, 'default_value' => 5, 'required' => 1],
                ['key' => 'field_ev_enable_hub_sync', 'label' => 'Pubblica su Hub / Mappa Globale', 'name' => 'enable_hub_sync', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'Abilita la trasmissione automatica dell\'evento al server centralizzato.'],
                ['key' => 'field_ev_modalita', 'label' => 'Modalità', 'name' => 'modalita', 'type' => 'select', 'choices' => ['fisico' => 'In presenza', 'virtuale' => 'Aula virtuale'], 'default_value' => 'fisico'],
                ['key' => 'field_ev_piattaforma_virtuale', 'label' => 'Piattaforma', 'name' => 'piattaforma_virtuale', 'type' => 'select', 'choices' => ['jitsi' => 'Jitsi Meet (integrato nella pagina)', 'zoom' => 'Zoom', 'meet' => 'Google Meet', 'altro' => 'Altro (link esterno)'], 'default_value' => 'jitsi', 'conditional_logic' => [[['field' => 'field_ev_modalita', 'operator' => '==', 'value' => 'virtuale']]]],
                ['key' => 'field_ev_link_virtuale', 'label' => 'Link Aula virtuale', 'name' => 'link_virtuale', 'type' => 'url', 'placeholder' => 'https://zoom.us/j/... (lascia vuoto per generare automaticamente una stanza Jitsi)', 'conditional_logic' => [[['field' => 'field_ev_modalita', 'operator' => '==', 'value' => 'virtuale']]]],
                ['key' => 'field_ev_indirizzo_geocoding', 'label' => 'Indirizzo / Luogo Evento', 'name' => 'indirizzo_geocoding', 'type' => 'text', 'placeholder' => 'Es. Passo Giau, Cortina d\'Ampezzo (BL)', 'instructions' => 'Indirizzo o luogo per la mappa dell\'Hub.', 'conditional_logic' => [[['field' => 'field_ev_modalita', 'operator' => '==', 'value' => 'fisico']]]],
                ['key' => 'field_ev_latitude', 'label' => 'Latitudine (Geocoding)', 'name' => 'event_latitude', 'type' => 'text', 'placeholder' => '46.4825'],
                ['key' => 'field_ev_longitude', 'label' => 'Longitudine (Geocoding)', 'name' => 'event_longitude', 'type' => 'text', 'placeholder' => '12.0538'],
                ['key' => 'field_ev_hub_status', 'label' => 'Stato Sincronizzazione Hub', 'name' => 'hub_status', 'type' => 'select', 'choices' => ['non_sincronizzato' => 'Non sincronizzato', 'sincronizzato' => 'Sincronizzato', 'errore' => 'Errore invio'], 'default_value' => 'non_sincronizzato', 'readonly' => 1],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'evento']]],
        ]);

        // PARTECIPANTE (anagrafica pura)
        acf_add_local_field_group([
            'key' => 'group_partecipante', 'title' => 'Dati Partecipante',
            'fields' => [
                ['key' => 'field_p_nome', 'label' => 'Nome', 'name' => 'nome', 'type' => 'text', 'required' => 1],
                ['key' => 'field_p_cognome', 'label' => 'Cognome', 'name' => 'cognome', 'type' => 'text', 'required' => 1],
                ['key' => 'field_p_tel', 'label' => 'Telefono', 'name' => 'telefono', 'type' => 'text'],
                ['key' => 'field_p_email', 'label' => 'Email', 'name' => 'email', 'type' => 'email', 'required' => 1],
                ['key' => 'field_p_citta', 'label' => 'Città di provenienza', 'name' => 'citta', 'type' => 'text'],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'partecipante']]],
        ]);

        // ISCRIZIONE (persona <-> evento)
        acf_add_local_field_group([
            'key' => 'group_iscrizione', 'title' => 'Dati Iscrizione',
            'fields' => [
                ['key' => 'field_i_part', 'label' => 'Partecipante', 'name' => 'partecipante', 'type' => 'post_object', 'post_type' => ['partecipante'], 'return_format' => 'id', 'required' => 1],
                ['key' => 'field_i_tipo', 'label' => 'Tipo iscrizione', 'name' => 'tipo_iscrizione', 'type' => 'select', 'choices' => ['workshop' => 'Workshop', 'corso' => 'Corso Online'], 'default_value' => 'workshop'],
                ['key' => 'field_i_evento', 'label' => 'Evento', 'name' => 'evento', 'type' => 'post_object', 'post_type' => ['evento'], 'return_format' => 'id', 'required' => 0, 'conditional_logic' => [[['field' => 'field_i_tipo', 'operator' => '==', 'value' => 'workshop']]]],
                ['key' => 'field_i_corso', 'label' => 'Corso', 'name' => 'corso', 'type' => 'post_object', 'post_type' => ['ws_course'], 'return_format' => 'id', 'required' => 0, 'conditional_logic' => [[['field' => 'field_i_tipo', 'operator' => '==', 'value' => 'corso']]]],
                ['key' => 'field_i_stato', 'label' => 'Stato', 'name' => 'stato', 'type' => 'select', 'choices' => ['richiesta' => 'Richiesta', 'confermato' => 'Confermato'], 'default_value' => 'richiesta'],
                ['key' => 'field_i_anticipo', 'label' => 'Anticipo pagato (€)', 'name' => 'anticipo', 'type' => 'number', 'step' => '0.01', 'default_value' => 0],
                ['key' => 'field_i_saldo', 'label' => 'Saldo pagato (€)', 'name' => 'saldo', 'type' => 'number', 'step' => '0.01', 'default_value' => 0],
                ['key' => 'field_i_note', 'label' => 'Note', 'name' => 'note', 'type' => 'textarea', 'rows' => 3],
                ['key' => 'field_i_num_persone', 'label' => 'Numero persone (gruppo)', 'name' => 'num_persone', 'type' => 'number', 'min' => 1, 'default_value' => 1, 'instructions' => 'Se il partecipante prenota per un gruppo, inserisci il totale delle persone (incluso lui).'],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'iscrizione']]],
        ]);

        // CATEGORIA
        acf_add_local_field_group([
            'key' => 'group_categoria', 'title' => 'Dati Categoria',
            'fields' => [
                ['key' => 'field_cat_tipo', 'label' => 'Tipo Evento', 'name' => 'tipo_evento', 'type' => 'text', 'default_value' => 'Workshop'],
                ['key' => 'field_cat_url', 'label' => 'URL pagina presentazione', 'name' => 'url_pagina', 'type' => 'url'],
                ['key' => 'field_cat_foto', 'label' => 'Foto categoria (URL immagine)', 'name' => 'foto_categoria', 'type' => 'url'],
            ],
            'location' => [[['param' => 'taxonomy', 'operator' => '==', 'value' => 'categoria_evento']]],
        ]);
    }
}
