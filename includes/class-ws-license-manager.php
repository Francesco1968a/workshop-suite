<?php

if (!defined('ABSPATH')) exit;

/**
 * License Manager for FV Workshop Admin.
 * Handles license key validation, status checking and updates integration.
 * Built to be compatible with Freemius / EDD (Easy Digital Downloads) Software Licensing.
 */
final class WS_License_Manager implements WS_Module {

    public const LICENSE_OPTION_KEY = 'fvw_license_data';

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('admin_init', [$this, 'register_settings']);
    }

    /** @return array{key:string, status:string, expires:string, type:string} */
    public static function get_license_data(): array {
        $defaults = [
            'key'     => '',
            'status'  => 'inactive', // 'active', 'inactive', 'expired', 'invalid'
            'expires' => '',
            'type'    => 'free',     // 'free', 'single', 'agency', 'unlimited'
        ];

        $saved = get_option(self::LICENSE_OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return array_merge($defaults, $saved);
    }

    public static function is_pro_active(): bool {
        $data = self::get_license_data();
        return $data['status'] === 'active';
    }

    public function register_settings(): void {
        register_setting('fvw_license_group', self::LICENSE_OPTION_KEY, [
            'sanitize_callback' => [$this, 'sanitize_and_validate']
        ]);
    }

    public function sanitize_and_validate(array $input): array {
        $current = self::get_license_data();
        $new_key = sanitize_text_field($input['key'] ?? '');

        // If key hasn't changed, return current status
        if ($new_key === $current['key']) {
            return $current;
        }

        // If key was cleared
        if (empty($new_key)) {
            return [
                'key'     => '',
                'status'  => 'inactive',
                'expires' => '',
                'type'    => 'free',
            ];
        }

        // Validate key against remote server (Freemius/EDD API mock placeholder)
        $validation = self::validate_remote_key($new_key);

        return [
            'key'     => $new_key,
            'status'  => $validation['status'],
            'expires' => $validation['expires'],
            'type'    => $validation['type'],
        ];
    }

    /**
     * Remote API call to validate key.
     * Can be hooked to Freemius SDK or EDD Software Licensing endpoint.
     */
    private static function validate_remote_key(string $key): array {
        // Placeholders for development testing
        if (str_starts_with($key, 'PRO-TEST-KEY')) {
            return [
                'status'  => 'active',
                'expires' => date('Y-m-d', strtotime('+1 year')),
                'type'    => 'agency',
            ];
        }

        // Real remote verification logic (EDD / Freemius / Custom Endpoint)
        $api_url = apply_filters('fvw_license_api_url', 'https://api.francescoverolino.com/v1/license/verify');
        
        $response = wp_remote_post($api_url, [
            'timeout' => 15,
            'body'    => [
                'license' => $key,
                'item_name' => 'FV Workshop Admin',
                'url'       => home_url(),
            ],
        ]);

        if (is_wp_error($response)) {
            return ['status' => 'invalid', 'expires' => '', 'type' => 'free'];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['success']) && !empty($body['license'])) {
            return [
                'status'  => $body['license'], // 'valid' / 'active'
                'expires' => $body['expires'] ?? '',
                'type'    => $body['price_name'] ?? 'single',
            ];
        }

        return ['status' => 'invalid', 'expires' => '', 'type' => 'free'];
    }
}
