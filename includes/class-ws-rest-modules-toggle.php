<?php

if (!defined('ABSPATH')) exit;

/**
 * REST controller for instant toggle of Modules & Add-ons.
 */
final class WS_Rest_Modules_Toggle {

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route('fvw/v1', '/modules/toggle', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle_toggle'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public static function handle_toggle(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params();
        $mod_key = sanitize_key($params['module'] ?? '');
        $active = !empty($params['active']);

        if (empty($mod_key)) {
            return new WP_REST_Response(['error' => 'Invalid module key'], 400);
        }

        $current = WS_Settings::get_all();
        if (!isset($current['active_modules']) || !is_array($current['active_modules'])) {
            $current['active_modules'] = [];
        }

        $current['active_modules'][$mod_key] = $active ? 1 : 0;
        WS_Settings::update_all($current);

        return new WP_REST_Response([
            'success' => true,
            'module'  => $mod_key,
            'active'  => $active,
            'message' => $active ? __('Modulo attivato con successo', 'workshop-suite') : __('Modulo disattivato', 'workshop-suite'),
        ], 200);
    }
}
