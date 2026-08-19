<?php

if (!defined('ABSPATH')) exit;

/**
 * License Manager for Workshop Suite.
 * Handles license key validation, status checking and updates integration.
 * Built to be compatible with Freemius / EDD (Easy Digital Downloads) Software Licensing.
 */
final class WS_License_Manager implements WS_Module {

    public const LICENSE_OPTION_KEY = 'ws_license_data';
    private const LEGACY_LICENSE_OPTION_KEY = 'fvw_license_data';

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

        $saved = get_option(self::LICENSE_OPTION_KEY, false);
        if ($saved === false) {
            // Migrate silently from the pre-rename option key so an already
            // activated Pro license doesn't appear inactive after the rename.
            $legacy = get_option(self::LEGACY_LICENSE_OPTION_KEY, []);
            if (!empty($legacy)) {
                update_option(self::LICENSE_OPTION_KEY, $legacy);
            }
            $saved = $legacy;
        }
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
        register_setting('ws_license_group', self::LICENSE_OPTION_KEY, [
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
        // Real remote verification logic (EDD / Freemius / Custom Endpoint)
        $api_url = apply_filters('ws_license_api_url', 'https://api.francescoverolino.com/v1/license/verify');

        $response = wp_remote_post($api_url, [
            'timeout' => 15,
            'body'    => [
                'license' => $key,
                'item_name' => 'Workshop Suite',
                'url'       => home_url(),
            ],
        ]);

        if (is_wp_error($response)) {
            return ['status' => 'invalid', 'expires' => '', 'type' => 'free'];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        // The remote endpoint's `license` field is documented as either
        // 'valid' or 'active' depending on provider (EDD Software Licensing
        // uses 'valid'; Freemius-style APIs tend to use 'active') — normalize
        // both to 'active' here so is_pro_active() has a single value to
        // check, instead of silently treating a real paid license as
        // inactive just because the provider phrased it differently.
        if (!empty($body['success']) && !empty($body['license'])) {
            $remote_status = $body['license'];
            $status = in_array($remote_status, ['valid', 'active'], true) ? 'active' : 'invalid';
            return [
                'status'  => $status,
                'expires' => $body['expires'] ?? '',
                'type'    => $body['price_name'] ?? 'single',
            ];
        }

        return ['status' => 'invalid', 'expires' => '', 'type' => 'free'];
    }
}
