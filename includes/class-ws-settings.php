<?php

if (!defined('ABSPATH')) exit;

/**
 * Central settings helper for FV Workshop Admin.
 * Handles options storage and default values for site-wide configuration.
 */
final class WS_Settings {

    public const OPTION_KEY = 'workshop_suite_settings';
    public const LEGACY_OPTION_KEY = 'fvw_settings';

    /** @return array<string, mixed> */
    public static function get_all(): array {
        $defaults = [
            'site_brand_name'  => get_option('blogname') ?: 'Workshop Suite',
            'sender_name'      => get_option('blogname') ?: 'Staff',
            'sender_email'     => get_option('admin_email') ?: '',
            'currency_symbol'  => '€',
            'enable_t15_reminders' => 1,
            'default_theme_mode'   => 'dark', // 'dark' (default), 'light'
            'custom_css'           => '',
            'event_types'          => ['Workshop', 'Viaggio Fotografico', 'Masterclass'],
            'default_notice'       => 'Iscrizioni aperte',
            'intake_rate_limit_enabled'  => 1,
            'intake_rate_limit_requests' => 5,
            'intake_rate_limit_window'   => 60,
            'intake_honeypot_enabled'    => 1,
            // Profilo Proponente / Trainer Bio
            'proponente_nome'        => '',
            'proponente_ruolo'       => '',
            'proponente_bio'         => '',
            'proponente_foto'        => '',
            'proponente_lingue'      => ['Italiano'],
            'proponente_sito'        => '',
            'proponente_email'       => '',
            'proponente_telefono'    => '',
            'proponente_citta'       => '',
            'proponente_instagram'   => '',
            'proponente_facebook'    => '',
            'proponente_youtube'     => '',
            'proponente_linkedin'    => '',
            'proponente_tiktok'      => '',
            'proponente_x'           => '',
            // Moduli & Add-ons (Tutor LMS Pro Modular Architecture)
            'active_modules'         => [
                'multi_docente'    => 1,
                'stripe_payments'  => 0,
                'woocommerce'      => 0,
                'webhooks'         => 0,
                'global_hub_pro'   => 1,
                'poster_studio'    => 1,
                'pdf_receipts'     => 0,
                'whatsapp_widget'  => 1,
            ],
        ];

        $saved = get_option(self::OPTION_KEY);
        $legacy = get_option(self::LEGACY_OPTION_KEY);
        
        $merged = $defaults;
        if (is_array($legacy)) {
            $merged = array_merge($merged, $legacy);
        }
        if (is_array($saved)) {
            // Se saved ha active_modules vuoto o non impostato, preserva quelli merged
            if (isset($saved['active_modules']) && is_array($saved['active_modules'])) {
                $merged_modules = array_merge($merged['active_modules'] ?? [], $saved['active_modules']);
                $merged = array_merge($merged, $saved);
                $merged['active_modules'] = $merged_modules;
            } else {
                $merged = array_merge($merged, $saved);
            }
        }

        return $merged;
    }

    public static function get(string $key, $default = null) {
        $all = self::get_all();
        return $all[$key] ?? $default;
    }

    public static function is_module_active(string $module_id, bool $default = false): bool {
        $modules = (array) self::get('active_modules', []);
        return isset($modules[$module_id]) ? !empty($modules[$module_id]) : $default;
    }

    public static function update_all(array $new_settings): bool {
        $clean = [];
        $clean['site_brand_name']      = sanitize_text_field($new_settings['site_brand_name'] ?? '');
        $clean['sender_name']          = sanitize_text_field($new_settings['sender_name'] ?? '');
        $clean['sender_email']         = sanitize_email($new_settings['sender_email'] ?? '');
        $clean['currency_symbol']      = sanitize_text_field($new_settings['currency_symbol'] ?? '€');
        $clean['enable_t15_reminders'] = !empty($new_settings['enable_t15_reminders']) ? 1 : 0;
        $clean['default_theme_mode']   = in_array($new_settings['default_theme_mode'] ?? '', ['dark', 'light'], true) ? $new_settings['default_theme_mode'] : 'dark';
        $clean['custom_css']           = wp_strip_all_tags($new_settings['custom_css'] ?? '');
        
        if (isset($new_settings['event_types']) && is_array($new_settings['event_types'])) {
            $clean['event_types'] = array_values(array_filter(array_map('sanitize_text_field', $new_settings['event_types'])));
        } else {
            $clean['event_types'] = ['Workshop', 'Viaggio Fotografico', 'Masterclass'];
        }

        $clean['default_notice']             = sanitize_text_field($new_settings['default_notice'] ?? '');
        $clean['intake_rate_limit_enabled']  = !empty($new_settings['intake_rate_limit_enabled']) ? 1 : 0;
        $clean['intake_rate_limit_requests'] = max(1, (int) ($new_settings['intake_rate_limit_requests'] ?? 5));
        $clean['intake_rate_limit_window']   = max(10, (int) ($new_settings['intake_rate_limit_window'] ?? 60));
        $clean['intake_honeypot_enabled']    = !empty($new_settings['intake_honeypot_enabled']) ? 1 : 0;

        // Sanitization Profilo Proponente
        $clean['proponente_nome']      = sanitize_text_field($new_settings['proponente_nome'] ?? '');
        $clean['proponente_ruolo']     = sanitize_text_field($new_settings['proponente_ruolo'] ?? '');
        $clean['proponente_bio']       = sanitize_textarea_field($new_settings['proponente_bio'] ?? '');
        $clean['proponente_foto']      = esc_url_raw($new_settings['proponente_foto'] ?? '');
        
        if (isset($new_settings['proponente_lingue']) && is_array($new_settings['proponente_lingue'])) {
            $clean['proponente_lingue'] = array_values(array_filter(array_map('sanitize_text_field', $new_settings['proponente_lingue'])));
        } else {
            $clean['proponente_lingue'] = ['Italiano'];
        }

        $clean['proponente_sito']      = esc_url_raw($new_settings['proponente_sito'] ?? '');
        $clean['proponente_email']     = sanitize_email($new_settings['proponente_email'] ?? '');
        $clean['proponente_telefono']  = sanitize_text_field($new_settings['proponente_telefono'] ?? '');
        $clean['proponente_citta']     = sanitize_text_field($new_settings['proponente_citta'] ?? '');
        $clean['proponente_instagram'] = sanitize_text_field($new_settings['proponente_instagram'] ?? '');
        $clean['proponente_facebook']  = esc_url_raw($new_settings['proponente_facebook'] ?? '');
        $clean['proponente_youtube']   = esc_url_raw($new_settings['proponente_youtube'] ?? '');
        $clean['proponente_linkedin']  = esc_url_raw($new_settings['proponente_linkedin'] ?? '');
        $clean['proponente_tiktok']    = sanitize_text_field($new_settings['proponente_tiktok'] ?? '');
        $clean['proponente_x']         = sanitize_text_field($new_settings['proponente_x'] ?? '');

        // Sanitization Moduli & Add-ons
        if (isset($new_settings['active_modules']) && is_array($new_settings['active_modules'])) {
            $clean['active_modules'] = [];
            foreach ($new_settings['active_modules'] as $mod_key => $mod_val) {
                $clean['active_modules'][sanitize_key($mod_key)] = !empty($mod_val) ? 1 : 0;
            }
        } else {
            $clean['active_modules'] = self::get('active_modules', []);
        }

        update_option(self::LEGACY_OPTION_KEY, $clean);
        return update_option(self::OPTION_KEY, $clean);
    }
}
