<?php

if (!defined('ABSPATH')) exit;

/**
 * Woorkshoop Global Hub Client Sync Module.
 * Automatically syndicates workshops to the global hub directory.
 */
final class WS_Hub_Sync implements WS_Module {

    const DEFAULT_HUB_API_URL = 'https://workshopsuite.pro/wp-json/woorkshoop-hub/v1/sync';

    public function should_load(): bool {
        return WS_Settings::is_module_active('global_hub_pro', true);
    }

    public function register(): void {
        add_action('save_post_wv_eventi', [__CLASS__, 'on_event_save'], 20, 2);
        add_action('wp_trash_post', [__CLASS__, 'on_event_trash']);
    }

    public static function on_event_save(int $post_id, WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if ($post->post_status !== 'publish') return;

        // Check if sync is disabled for this specific event
        $disabled = get_post_meta($post_id, '_ws_hub_sync_disabled', true);
        if ($disabled) return;

        self::sync_single_event($post_id);
    }

    public static function on_event_trash(int $post_id): void {
        if (get_post_type($post_id) !== 'wv_eventi') return;
        self::delete_from_hub($post_id);
    }

    public static function sync_single_event(int $post_id): array {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return ['success' => false, 'message' => 'Post not published'];
        }

        $meta = get_post_meta($post_id);
        $settings = WS_Settings::get_all();

        // Get hero image URL
        $image_url = '';
        $thumb_id = get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            $image_url = wp_get_attachment_image_url($thumb_id, 'large') ?: '';
        }

        // Get category / type terms
        $terms = get_the_terms($post_id, 'categoria_evento');
        $cat_name = ($terms && !is_wp_error($terms)) ? $terms[0]->name : 'Workshop';

        // Resolve real public booking URL (prevent 404 on private CPTs)
        $booking_url = '';
        if ($terms && !is_wp_error($terms)) {
            $cat_url = WS_Data::get_field('url_pagina', 'categoria_evento_' . $terms[0]->term_id);
            if ($cat_url) {
                $booking_url = esc_url_raw($cat_url);
            }
        }
        if (empty($booking_url)) {
            $booking_page = WS_Data::find_page_url_containing('ws_form_iscrizione');
            if (!$booking_page) {
                // 'fvw_iscrizione' was never a real substring of either
                // registered shortcode tag (fv_form_iscrizione /
                // fvw_form_iscrizione), so this search never matched
                // anything — try the actual live tag instead.
                $booking_page = WS_Data::find_page_url_containing('fv_form_iscrizione');
            }
            $booking_url = $booking_page ?: home_url();
        }

        // Prepare JSON payload
        $payload = [
            'uuid'                => 'ws_ev_' . md5(home_url() . '_' . $post_id),
            'origin_site'         => home_url(),
            'booking_url'         => $booking_url,
            'title'               => $post->post_title,
            'description'         => $post->post_content,
            'excerpt'             => $post->post_excerpt ?: wp_trim_words($post->post_content, 30),
            'category'            => $cat_name,
            'city'                => $meta['citta'][0] ?? $meta['_wv_citta'][0] ?? 'Napoli, Italy',
            'country'             => $meta['nazione'][0] ?? 'Italy',
            'location_address'    => $meta['indirizzo'][0] ?? '',
            'start_date'          => $meta['data_inizio'][0] ?? $meta['_wv_data_inizio'][0] ?? '',
            'end_date'            => $meta['data_fine'][0] ?? $meta['_wv_data_fine'][0] ?? '',
            'start_time'          => $meta['ora_inizio'][0] ?? '',
            'price'               => $meta['prezzo'][0] ?? $meta['_wv_prezzo'][0] ?? '',
            'deposit'             => $meta['acconto'][0] ?? $meta['_wv_acconto'][0] ?? '',
            'currency'            => $settings['currency_symbol'] ?? '€',
            'image_url'           => $image_url,
            'available_seats'     => intval($meta['posti_disponibili'][0] ?? 10),
            'total_seats'         => intval($meta['posti_totali'][0] ?? 10),
            'is_pro'              => 1,
            // Trainer bio
            'organizer_name'      => $settings['proponente_nome'] ?: ($settings['site_brand_name'] ?: get_bloginfo('name')),
            'organizer_role'      => $settings['proponente_ruolo'] ?: 'Lead Instructor',
            'organizer_bio'       => $settings['proponente_bio'] ?: '',
            'organizer_avatar'    => $settings['proponente_foto'] ?: '',
            'organizer_website'   => $settings['proponente_sito'] ?: home_url(),
            'organizer_languages' => (array) ($settings['proponente_lingue'] ?? ['Italiano']),
        ];

        // Send via REST API to Hub
        $hub_api_url = apply_filters('ws_hub_api_endpoint', self::DEFAULT_HUB_API_URL);

        $response = wp_remote_post($hub_api_url, [
            'timeout'     => 12,
            'headers'     => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'Host'         => 'workshopsuite.pro',
            ],
            'body'        => wp_json_encode($payload),
            'sslverify'   => false,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300 && !empty($body['success'])) {
            update_post_meta($post_id, '_ws_hub_syndicated_url', esc_url_raw($body['hub_url'] ?? ''));
            update_post_meta($post_id, '_ws_hub_syndicated_at', current_time('mysql'));
            return ['success' => true, 'hub_url' => $body['hub_url'] ?? ''];
        }

        return ['success' => false, 'message' => $body['message'] ?? 'Hub sync failed'];
    }

    public static function delete_from_hub(int $post_id): void {
        $uuid = 'ws_ev_' . md5(home_url() . '_' . $post_id);
        $hub_delete_url = str_replace('/sync', '/delete', self::DEFAULT_HUB_API_URL);

        wp_remote_request($hub_delete_url, [
            'method'    => 'DELETE',
            'timeout'   => 8,
            'headers'   => [
                'Content-Type' => 'application/json',
                'Host'         => 'workshopsuite.pro',
            ],
            'body'      => wp_json_encode([
                'uuid'        => $uuid,
                'origin_site' => home_url(),
            ]),
            'sslverify' => false,
        ]);

        delete_post_meta($post_id, '_ws_hub_syndicated_url');
        delete_post_meta($post_id, '_ws_hub_syndicated_at');
    }
}
