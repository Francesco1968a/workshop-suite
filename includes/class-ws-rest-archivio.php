<?php

if (!defined('ABSPATH')) exit;

/**
 * REST endpoint backing the Archivio Workshop panel. Ports the query from
 * the legacy `workshop_archivio` shortcode 1:1 (conclusi = data_fine < oggi,
 * optional year filter, same distinct-years query for the filter chips).
 */
final class WS_Rest_Archivio implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('workshop-suite/v1', '/archivio', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_archivio'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'anno' => [
                    'type'    => 'integer',
                    'default' => 0,
                ],
            ],
        ]);
    }

    public function get_archivio(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $anno_filtro = (int) $request->get_param('anno');
        $oggi = date('Y-m-d');

        $args = [
            'post_type' => 'evento', 'posts_per_page' => -1,
            'meta_key' => 'data_evento', 'orderby' => 'meta_value', 'order' => 'DESC',
            'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '<', 'type' => 'DATE']],
        ];
        if ($anno_filtro) {
            $args['meta_query'][] = [
                'key' => 'data_evento',
                'value' => [$anno_filtro . '-01-01', $anno_filtro . '-12-31'],
                'compare' => 'BETWEEN', 'type' => 'DATE',
            ];
        }
        $eq = new WP_Query($args);

        $anni = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT YEAR(pm.meta_value) AS y
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = 'data_evento' AND p.post_type = 'evento' AND p.post_status = 'publish'
              AND pm.meta_value <> '' AND pm.meta_value < %s
            ORDER BY y DESC
        ", $oggi));

        $admin = WS_Data::find_page_url_containing('[workshop_admin]');
        $riep  = WS_Data::find_page_url_containing('[workshop_riepilogo');

        $eventi = array_map(function ($post) use ($admin, $riep) {
            $id = $post->ID;
            $s = WS_Data::stato_posti($id);
            $terms = get_the_terms($id, 'categoria_evento');
            $cat_name = $terms ? $terms[0]->name : '—';
            $foto = $terms ? WS_Data::get_field('foto_categoria', 'categoria_evento_' . $terms[0]->term_id) : '';
            $cat_url = $terms ? WS_Data::get_field('url_pagina', 'categoria_evento_' . $terms[0]->term_id) : '';

            return [
                'id'          => $id,
                'cat_name'    => $cat_name,
                'foto'        => $foto ?: '',
                'periodo'     => WS_Data::format_periodo($id),
                'ora'         => WS_Data::get_field('ora_inizio', $id) ?: '',
                'occupati'    => $s['occupati'],
                'totali'      => $s['totali'],
                'link_partecipanti' => $riep ? add_query_arg('evento_id', $id, $riep) : '',
                'link_gestisci'     => $admin ? add_query_arg(['vista' => 'eventi', 'edit_ev' => $id], $admin) : '',
                'link_pagina'       => $cat_url ?: '',
            ];
        }, $eq->posts);

        return new WP_REST_Response([
            'eventi' => $eventi,
            'anni'   => array_map('intval', $anni),
            'anno'   => $anno_filtro,
        ]);
    }
}