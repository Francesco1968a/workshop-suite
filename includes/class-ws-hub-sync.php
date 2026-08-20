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
        add_action('init', [__CLASS__, 'migrate_legacy_synced_events']);
        add_action('save_post_evento', [__CLASS__, 'on_event_save'], 20, 2);
        add_action('save_post_wv_eventi', [__CLASS__, 'on_event_save'], 20, 2);
        add_action('wp_trash_post', [__CLASS__, 'on_event_trash']);
    }

    public static function on_event_save(int $post_id, WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if ($post->post_status !== 'publish') return;
        if (!self::should_sync($post_id)) return;

        self::sync_single_event($post_id);
    }

    /**
     * The real per-event gate is the "Pubblica su Hub" checkbox
     * (enable_hub_sync, shown on the Evento form) — this used to be
     * ignored entirely (the code checked a different, never-written meta
     * key, '_ws_hub_sync_disabled'), so every published event synced
     * regardless of the checkbox. Opt-in matches the field's own label
     * and default value (unchecked); events that were already syndicated
     * under the old always-on behavior are backfilled to enable_hub_sync=1
     * once (see migrate_legacy_enable_hub_sync()) so nothing already
     * listed on the Hub silently stops updating because of this fix.
     */
    public static function should_sync(int $post_id): bool {
        return (bool) get_post_meta($post_id, 'enable_hub_sync', true);
    }

    public static function on_event_trash(int $post_id): void {
        if (!in_array(get_post_type($post_id), ['evento', 'wv_eventi'], true)) return;
        self::delete_from_hub($post_id);
    }

    /**
     * One-time backfill (init hook): any event that already has a
     * _ws_hub_syndicated_url (proof it was synced under the old
     * always-on logic) but enable_hub_sync not set gets it turned on,
     * so should_sync()'s new opt-in gate doesn't retroactively orphan
     * listings already live on the Hub.
     */
    public static function migrate_legacy_synced_events(): void {
        if (get_option('ws_hub_legacy_backfill_done')) return;

        $ids = get_posts([
            'post_type' => ['evento', 'wv_eventi'], 'post_status' => 'publish',
            'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => [
                ['key' => '_ws_hub_syndicated_url', 'compare' => 'EXISTS'],
                ['key' => 'enable_hub_sync', 'compare' => 'NOT EXISTS'],
            ],
        ]);
        foreach ($ids as $id) {
            update_post_meta($id, 'enable_hub_sync', 1);
        }
        update_option('ws_hub_legacy_backfill_done', 1);
    }

    /**
     * Batch syndicates all currently published, opted-in events to the
     * Global Hub.
     */
    public static function sync_all_published_events(): array {
        $query = new WP_Query([
            'post_type'      => ['evento', 'wv_eventi'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [['key' => 'enable_hub_sync', 'value' => '1']],
        ]);

        $results = ['synced' => 0, 'failed' => 0, 'details' => []];
        foreach ($query->posts as $id) {
            $res = self::sync_single_event((int) $id);
            if (!empty($res['success'])) {
                $results['synced']++;
            } else {
                $results['failed']++;
            }
            $results['details'][$id] = $res;
        }

        return $results;
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
        $cat_term_id = ($terms && !is_wp_error($terms)) ? $terms[0]->term_id : 0;

        // Modalità: virtual eventi have no physical geo data at all — don't
        // fake a city/country fallback for them (the old 'Napoli, Italy'
        // default was written back when only physical workshops existed).
        $modalita = $meta['modalita'][0] ?? 'fisico';
        $is_virtual = $modalita === 'virtuale';

        // Geo fields: event meta overrides category defaults, which override hardcoded fallbacks
        $city = $meta['citta'][0] ?? $meta['_wv_citta'][0] ?? null;
        $country = $meta['nazione'][0] ?? null;
        $address = $meta['indirizzo'][0] ?? null;
        if ($cat_term_id) {
            $city = $city ?: (WS_Data::get_field('citta', 'categoria_evento_' . $cat_term_id) ?: null);
            $country = $country ?: (WS_Data::get_field('nazione', 'categoria_evento_' . $cat_term_id) ?: null);
            $address = $address ?: (WS_Data::get_field('indirizzo', 'categoria_evento_' . $cat_term_id) ?: null);
        }
        if ($is_virtual) {
            $city = $city ?: 'Online';
            $country = $country ?: '';
        } else {
            $city = $city ?: 'Napoli, Italy';
            $country = $country ?: 'Italy';
        }
        $address = $address ?: '';

        // Where to join, for the Hub to show a "Partecipa online" CTA
        // instead of a map pin — prefers our own embedded [ws_aula_virtuale]
        // page over the raw meeting link, same preference as the {luogo}
        // mail placeholder.
        $virtual_join_url = '';
        if ($is_virtual) {
            $aula_page_id = (int) ($meta['_ws_aula_page_id'][0] ?? 0);
            $virtual_join_url = ($aula_page_id && get_post_status($aula_page_id) === 'publish')
                ? get_permalink($aula_page_id)
                : (string) ($meta['link_virtuale'][0] ?? '');
        }

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
            'city'                => $city,
            'country'             => $country,
            'location_address'    => $address,
            'location_type'       => $is_virtual ? 'online' : 'in_presenza',
            'virtual_join_url'    => $virtual_join_url,
            // 'data_inizio' was never a real evento meta key (the field is
            // 'data_evento') — this was silently sending an empty start
            // date to the Hub for every event; fixed alongside the
            // virtual-eventi support since it touches the same payload.
            'start_date'          => $meta['data_evento'][0] ?? $meta['_wv_data_inizio'][0] ?? '',
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
