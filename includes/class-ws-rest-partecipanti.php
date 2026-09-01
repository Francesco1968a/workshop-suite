<?php

if (!defined('ABSPATH')) exit;

/**
 * REST endpoint backing the Partecipanti list panel.
 *
 * Ports the query from the legacy `workshop_partecipanti_lista` shortcode
 * 1:1 (same post type, same ACF fields, same search behaviour).
 */
final class WSMA_Rest_Partecipanti implements WSMA_Module {

    public function should_load(): bool {
        return true; // REST routes must register on every request, WP filters by URL internally.
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('workshop-suite/v1', '/partecipanti', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_partecipanti'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'q' => [
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function get_partecipanti(WP_REST_Request $request): WP_REST_Response {
        $q = (string) $request->get_param('q');

        $args = [
            'post_type'      => 'wsma_partecipante',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ];
        if ($q !== '') {
            $args['meta_query'] = [
                'relation' => 'OR',
                ['key' => 'nome',     'value' => $q, 'compare' => 'LIKE'],
                ['key' => 'cognome',  'value' => $q, 'compare' => 'LIKE'],
                ['key' => 'email',    'value' => $q, 'compare' => 'LIKE'],
                ['key' => 'telefono', 'value' => $q, 'compare' => 'LIKE'],
                ['key' => 'citta',    'value' => $q, 'compare' => 'LIKE'],
            ];
        }

        $partecipanti = get_posts($args);
        $p_ids = !empty($partecipanti) ? wp_list_pluck($partecipanti, 'ID') : [];

        // Batch pre-fetch all postmeta for all participants in 1 query
        if (!empty($p_ids)) {
            update_postmeta_cache($p_ids);
        }

        // Batch pre-fetch all iscrizioni for all participants in 1 single query
        $stats_map = [];
        if (!empty($p_ids)) {
            $all_iscrizioni = get_posts([
                'post_type'      => 'wsma_iscrizione',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
                'meta_query'     => [
                    [
                        'key'     => 'partecipante',
                        'value'   => $p_ids,
                        'compare' => 'IN',
                    ],
                ],
            ]);

            if (!empty($all_iscrizioni)) {
                $isc_ids = wp_list_pluck($all_iscrizioni, 'ID');
                update_postmeta_cache($isc_ids);

                foreach ($all_iscrizioni as $isc) {
                    $pid = (int) WSMA_Data::get_field('partecipante', $isc->ID);
                    if (!$pid) continue;
                    if (!isset($stats_map[$pid])) {
                        $stats_map[$pid] = ['totali' => 0, 'confermate' => 0];
                    }
                    $stats_map[$pid]['totali']++;
                    $stato = WSMA_Data::get_field('stato', $isc->ID);
                    if ($stato === 'confermato') {
                        $stats_map[$pid]['confermate']++;
                    }
                }
            }
        }

        $items = array_map(function ($p) use ($stats_map) {
            $pid = $p->ID;
            $tot = $stats_map[$pid]['totali'] ?? 0;
            $conf = $stats_map[$pid]['confermate'] ?? 0;

            return [
                'id'         => $pid,
                'nome'       => trim(WSMA_Data::get_field('nome', $pid) . ' ' . WSMA_Data::get_field('cognome', $pid)),
                'email'      => WSMA_Data::get_field('email', $pid) ?: '',
                'citta'      => WSMA_Data::get_field('citta', $pid) ?: '',
                'totali'     => (int) $tot,
                'confermate' => (int) $conf,
            ];
        }, $partecipanti);

        return new WP_REST_Response([
            'items' => $items,
            'count' => count($items),
            'q'     => $q,
        ]);
    }
}