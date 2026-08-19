<?php

if (!defined('ABSPATH')) exit;

/** REST endpoint backing the Calendario Workshop subscription panel. */
final class WS_Rest_Calendario implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('workshop-suite/v1', '/calendario', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_calendario'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function get_calendario(): WP_REST_Response {
        $url = WS_Data::calendar_url();
        $webcal = preg_replace('#^https?://#', 'webcal://', $url);

        $eq = new WP_Query([
            'post_type'      => 'evento',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'meta_key'       => 'data_evento',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ]);

        $eventi = [];
        if (!empty($eq->posts)) {
            update_postmeta_cache(wp_list_pluck($eq->posts, 'ID'));
        }
        foreach ($eq->posts as $ev) {
            $id = $ev->ID;
            $d1 = (string) WS_Data::get_field('data_evento', $id);
            if (!$d1) continue;
            $d2 = (string) WS_Data::get_field('data_fine', $id) ?: $d1;
            $terms = get_the_terms($id, 'categoria_evento');
            $s = WS_Data::stato_posti($id);
            $eventi[] = [
                'id'            => $id,
                'label'         => WS_Data::evento_label($id),
                'categoria_nome' => ($terms && !is_wp_error($terms)) ? $terms[0]->name : '',
                'data_inizio'   => $d1,
                'data_fine'     => $d2,
                'sold_out'      => $s['sold_out'],
            ];
        }

        return new WP_REST_Response([
            'url'    => $url,
            'webcal' => $webcal,
            'eventi' => $eventi,
        ]);
    }
}