<?php

if (!defined('ABSPATH')) exit;

/**
 * Class WSMA_Taxonomy_Registry
 * 
 * Fetches and synchronizes categories and event types from Woorkshoop Global Hub.
 * Acts as the Single Source of Truth across all client installations.
 */
final class WSMA_Taxonomy_Registry {

    const HUB_TAXONOMY_URL = 'https://wsmaker.pro/wp-json/woorkshoop-hub/v1/taxonomies';
    const CACHE_KEY        = '_wsma_hub_taxonomies_cache';
    const CACHE_TTL        = 86400; // 24 hours

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_local_endpoints']);
    }

    public static function register_local_endpoints(): void {
        register_rest_route('workshop-suite/v1', '/taxonomies', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_taxonomies_response'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public static function get_taxonomies_response(): WP_REST_Response {
        return new WP_REST_Response(self::get_all_taxonomies(), 200);
    }

    public static function get_all_taxonomies(): array {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        // Fetch from Central Hub
        $response = wp_remote_get(self::HUB_TAXONOMY_URL, [
            'timeout'   => 5,
            'sslverify' => false,
            'headers'   => [
                'Accept' => 'application/json',
            ],
        ]);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($body['success']) && !empty($body['categories'])) {
                set_transient(self::CACHE_KEY, $body, self::CACHE_TTL);
                return $body;
            }
        }

        // Graceful Fallback if Hub is temporarily unreachable
        $fallback = self::get_default_taxonomies();
        set_transient(self::CACHE_KEY, $fallback, 3600); // 1 hour retry
        return $fallback;
    }

    public static function get_categories(): array {
        $data = self::get_all_taxonomies();
        return $data['categories'] ?? [];
    }

    public static function get_event_types(): array {
        $data = self::get_all_taxonomies();
        return $data['event_types'] ?? [];
    }

    public static function refresh(): array {
        delete_transient(self::CACHE_KEY);
        return self::get_all_taxonomies();
    }

    private static function get_default_taxonomies(): array {
        return [
            'success'     => true,
            'source'      => 'local_fallback',
            'categories'  => [
                ['slug' => 'fotografia', 'name' => 'Fotografia & Video', 'name_en' => 'Photography & Video', 'icon' => '📷'],
                ['slug' => 'cucina', 'name' => 'Cucina, Pasticceria & Vino', 'name_en' => 'Culinary, Pastry & Wine', 'icon' => '🍳'],
                ['slug' => 'yoga', 'name' => 'Yoga, Meditazione & Benessere', 'name_en' => 'Yoga, Wellness & Retreats', 'icon' => '🧘'],
                ['slug' => 'pittura', 'name' => 'Pittura, Disegno & Scultura', 'name_en' => 'Visual Arts, Painting & Sculpture', 'icon' => '🎨'],
                ['slug' => 'artigianato', 'name' => 'Cucito, Ceramica & Artigianato', 'name_en' => 'Crafts, Sewing & Ceramics', 'icon' => '🧵'],
                ['slug' => 'sport', 'name' => 'Sport, Trekking & Outdoor', 'name_en' => 'Sports, Fitness & Outdoor', 'icon' => '🏃'],
                ['slug' => 'musica', 'name' => 'Musica, Canto & Produzione', 'name_en' => 'Music, Audio & Vocal', 'icon' => '🎵'],
                ['slug' => 'teatro', 'name' => 'Teatro & Recitazione', 'name_en' => 'Theatre, Acting & Performance', 'icon' => '🎭'],
                ['slug' => 'scrittura', 'name' => 'Scrittura Creativa & Storytelling', 'name_en' => 'Creative Writing & Storytelling', 'icon' => '✍️'],
                ['slug' => 'botanica', 'name' => 'Botanica & Flower Design', 'name_en' => 'Botany, Gardening & Floral Design', 'icon' => '🪴'],
                ['slug' => 'business', 'name' => 'Business & Masterclass', 'name_en' => 'Business & Masterclasses', 'icon' => '💼'],
            ],
            'event_types' => [
                ['slug' => 'in_person', 'name' => 'In Presenza / On-Location', 'name_en' => 'In-Person', 'icon' => '📍'],
                ['slug' => 'online_live', 'name' => 'Live Online (Zoom/Meet)', 'name_en' => 'Live Online', 'icon' => '💻'],
                ['slug' => 'retreat', 'name' => 'Ritiro & Weekend Camp', 'name_en' => 'Weekend Retreat', 'icon' => '🏕️'],
            ],
        ];
    }
}
