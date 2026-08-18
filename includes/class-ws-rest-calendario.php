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

        return new WP_REST_Response([
            'url'    => $url,
            'webcal' => $webcal,
        ]);
    }
}