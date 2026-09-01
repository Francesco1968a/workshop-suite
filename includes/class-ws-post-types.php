<?php

if (!defined('ABSPATH')) exit;

/**
 * Registers the core data model: CPTs (`wsma_evento`, `wsma_partecipante`,
 * `wsma_iscrizione`) and taxonomy (`wsma_categoria_evento`) — this is the
 * single most foundational piece of the whole system (every other panel/
 * query depends on these post types existing).
 *
 * Field data (dates, seats, contact info, category text/photo, etc.) is
 * read/written entirely through native WordPress post/term meta via
 * WSMA_Data::get_field()/update_field() — no ACF dependency. An earlier
 * version optionally routed through ACF's own get_field()/update_field()
 * when present, but that indirection was the exact thing that silently
 * broke category photos/text on production during the CPT/taxonomy
 * rename (ACF's own post-id-string convention for term-attached fields
 * wasn't updated everywhere it needed to be) — plain post/term meta has
 * no equivalent hidden convention to get wrong.
 */
final class WSMA_Post_Types implements WSMA_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        $this->migrate_legacy_slugs();
        add_action('init', [$this, 'register_post_types']);
        add_action('admin_menu', [$this, 'reorder_submenu'], 999);
    }

    /**
     * One-time DB migration: the CPTs/taxonomy were registered under
     * `ws_evento`/`ws_partecipante`/`ws_iscrizione`/`categoria_evento`
     * before the WordPress.org review required a 4+ character prefix.
     * Existing installs have real data stored under those slugs in
     * wp_posts.post_type / wp_term_taxonomy.taxonomy, so the slug rename
     * below must rewrite those rows — a code-only rename would silently
     * orphan every existing evento/partecipante/iscrizione/categoria.
     */
    public function migrate_legacy_slugs(): void {
        if (get_option('wsma_cpt_taxonomy_migration_done')) return;

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-time bulk
        // slug rewrite, guarded by the option check above so it only ever
        // runs once per install; no WP API renames a post type/taxonomy
        // across existing rows, and looping wp_update_post() per row here
        // would be far slower and (as found in production) risks silently
        // corrupting unrelated post_content via its implicit unslashing.
        // Nothing to cache: this touches every matching row exactly once,
        // there's no repeated read to speed up.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
            'wsma_evento', 'ws_evento'
        ));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
            'wsma_partecipante', 'ws_partecipante'
        ));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
            'wsma_iscrizione', 'ws_iscrizione'
        ));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->term_taxonomy} SET taxonomy = %s WHERE taxonomy = %s",
            'wsma_categoria_evento', 'categoria_evento'
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

        // Raw $wpdb queries bypass the object cache — on installs with a
        // persistent cache backend (Redis/Memcached), every already-cached
        // post/term would keep serving the pre-migration post_type/taxonomy
        // until it naturally expired, without this.
        wp_cache_flush();

        update_option('wsma_cpt_taxonomy_migration_done', 1);
    }

    /**
     * "Richieste" (the iscrizione CPT) gets auto-added to the Workshop
     * Suite submenu during init, ahead of every add_submenu_page() call
     * that only runs later on admin_menu — landing it at the very top of
     * the list. Moves it to sit right before "Mail Box" instead, which
     * reads better next to the other communication-related panel.
     */
    public function reorder_submenu(): void {
        global $submenu;
        $parent = 'workshop-suite-dashboard';
        if (empty($submenu[$parent])) return;

        $richieste_key = null;
        $mailbox_index = null;
        foreach ($submenu[$parent] as $i => $item) {
            if (($item[2] ?? '') === 'edit.php?post_type=wsma_iscrizione') $richieste_key = $i;
            if (($item[2] ?? '') === 'workshop-suite-messaggi') $mailbox_index = $i;
        }
        if ($richieste_key === null || $mailbox_index === null || $richieste_key === $mailbox_index) return;

        $richieste_item = $submenu[$parent][$richieste_key];
        unset($submenu[$parent][$richieste_key]);
        $items = array_values($submenu[$parent]);

        $insert_at = null;
        foreach ($items as $i => $item) {
            if (($item[2] ?? '') === 'workshop-suite-messaggi') { $insert_at = $i; break; }
        }
        if ($insert_at === null) return;

        array_splice($items, $insert_at, 0, [$richieste_item]);
        $submenu[$parent] = $items;
    }

    public function register_post_types(): void {
        register_post_type('wsma_evento', [
            'labels' => [
                'name'          => __('Eventi', 'wsmaker'),
                'singular_name' => __('Evento', 'wsmaker'),
            ],
            'public' => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon' => 'dashicons-calendar-alt', 'supports' => ['title'], 'has_archive' => false,
        ]);
        register_post_type('wsma_partecipante', [
            'labels' => [
                'name'          => __('Partecipanti', 'wsmaker'),
                'singular_name' => __('Partecipante', 'wsmaker'),
            ],
            'public' => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon' => 'dashicons-groups', 'supports' => ['title'], 'has_archive' => false,
        ]);
        register_post_type('wsma_iscrizione', [
            'labels' => [
                'name'          => __('Iscrizioni', 'wsmaker'),
                'singular_name' => __('Iscrizione', 'wsmaker'),
                'menu_name'     => __('Richieste', 'wsmaker'),
            ],
            'public' => false, 'show_ui' => true, 'show_in_menu' => 'workshop-suite-dashboard',
            'menu_icon' => 'dashicons-tickets-alt', 'supports' => ['title'], 'has_archive' => false,
        ]);
        register_taxonomy('wsma_categoria_evento', ['wsma_evento'], [
            'labels' => [
                'name'          => __('Categorie Workshop', 'wsmaker'),
                'singular_name' => __('Categoria', 'wsmaker'),
            ],
            'hierarchical' => true, 'show_ui' => true, 'show_admin_column' => true,
        ]);
    }

}
