<?php

if (!defined('ABSPATH')) exit;

/** Hides nav menu items titled "Admin Workshop..." from non-admins. Ported from the legacy "Workshop CRM 4 Dashboard" snippet. */
final class WSMA_Admin_Nav_Filter implements WSMA_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_filter('wp_get_nav_menu_items', [$this, 'filter_items']);
    }

    public function filter_items($items) {
        if (!is_array($items)) return $items;
        if (current_user_can('manage_options')) return $items;
        foreach ($items as $k => $it) {
            if (isset($it->title) && stripos($it->title, 'admin workshop') !== false) unset($items[$k]);
        }
        return $items;
    }
}
