<?php

if (!defined('ABSPATH')) exit;

/**
 * Adds CRM-style columns (name, email, phone, city, event, status, source
 * page) to WordPress's own native `iscrizione` post-type list table —
 * the same raw "form submissions" log view FluentForms' own Entries
 * screen offers, but built on data this plugin already owns (partecipante/
 * iscrizione), not a separate log. Deliberately built as native list-table
 * columns rather than a custom Vue admin page: it's free sorting, search,
 * bulk actions, and screen-options column toggling from WordPress core,
 * with no new REST endpoint or JS bundle to maintain.
 */
final class WSMA_Iscrizioni_List implements WSMA_Module {

    public function should_load(): bool {
        return is_admin();
    }

    public function register(): void {
        add_filter('manage_edit-iscrizione_columns', [$this, 'add_columns']);
        add_action('manage_iscrizione_posts_custom_column', [$this, 'render_column'], 10, 2);
        add_filter('manage_edit-iscrizione_sortable_columns', [$this, 'sortable_columns']);
        add_filter('pre_get_posts', [$this, 'extend_admin_search']);
        add_action('restrict_manage_posts', [$this, 'render_stato_filter']);
        add_action('pre_get_posts', [$this, 'apply_stato_filter']);
    }

    public function add_columns(array $columns): array {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['ws_nome']       = __('Nome', 'wsmaker');
                $new['ws_email']      = __('Email', 'wsmaker');
                $new['ws_telefono']   = __('Telefono', 'wsmaker');
                $new['ws_citta']      = __('Città', 'wsmaker');
                $new['wsma_evento']     = __('Evento / Corso', 'wsmaker');
                $new['ws_stato']      = __('Stato', 'wsmaker');
                $new['ws_provenienza'] = __('Pagina di Provenienza', 'wsmaker');
            }
        }
        unset($new['comments']);
        return $new;
    }

    public function render_column(string $column, int $post_id): void {
        switch ($column) {
            case 'ws_nome':
                $pid = (int) WSMA_Data::get_field('partecipante', $post_id);
                echo esc_html($pid ? trim(WSMA_Data::get_field('nome', $pid) . ' ' . WSMA_Data::get_field('cognome', $pid)) : '—');
                break;
            case 'ws_email':
                $pid = (int) WSMA_Data::get_field('partecipante', $post_id);
                echo esc_html($pid ? (string) WSMA_Data::get_field('email', $pid) : '—');
                break;
            case 'ws_telefono':
                $pid = (int) WSMA_Data::get_field('partecipante', $post_id);
                echo esc_html($pid ? (string) WSMA_Data::get_field('telefono', $pid) : '—');
                break;
            case 'ws_citta':
                $pid = (int) WSMA_Data::get_field('partecipante', $post_id);
                echo esc_html($pid ? (string) WSMA_Data::get_field('citta', $pid) : '—');
                break;
            case 'wsma_evento':
                $eid = (int) WSMA_Data::get_field('evento', $post_id);
                $cid = (int) WSMA_Data::get_field('corso', $post_id);
                if ($eid) echo esc_html(WSMA_Data::evento_label($eid));
                elseif ($cid) echo esc_html(get_the_title($cid));
                else echo '—';
                break;
            case 'ws_stato':
                $stato = (string) WSMA_Data::get_field('stato', $post_id);
                $is_confermato = $stato === 'confermato';
                echo '<span style="color:' . ($is_confermato ? '#065f46' : '#92400e') . ';font-weight:600;">';
                echo $is_confermato ? '✓ ' . esc_html__('Confermato', 'wsmaker') : '● ' . esc_html__('Richiesta', 'wsmaker');
                echo '</span>';
                break;
            case 'ws_provenienza':
                $url = (string) get_post_meta($post_id, 'pagina_provenienza', true);
                if ($url) {
                    echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer" style="word-break:break-all;">' . esc_html(wp_parse_url($url, PHP_URL_PATH) ?: $url) . '</a>';
                } else {
                    echo '—';
                }
                break;
        }
    }

    public function sortable_columns(array $columns): array {
        $columns['ws_stato'] = 'ws_stato';
        return $columns;
    }

    /** Estende la ricerca nativa (che di default guarda solo il titolo) a email/nome/telefono del partecipante collegato. */
    public function extend_admin_search(WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'wsma_iscrizione') return;
        $search = trim((string) $query->get('s'));
        if (!$search) return;

        $partecipanti = get_posts([
            'post_type' => 'wsma_partecipante', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'email', 'value' => $search, 'compare' => 'LIKE'],
                ['key' => 'nome', 'value' => $search, 'compare' => 'LIKE'],
                ['key' => 'cognome', 'value' => $search, 'compare' => 'LIKE'],
                ['key' => 'telefono', 'value' => $search, 'compare' => 'LIKE'],
            ],
        ]);
        if (!$partecipanti) return;

        // WordPress's own title search (via 's') would otherwise be
        // dropped if we swap to meta_query/post__in directly — instead,
        // OR the matching iscrizioni in via post__in while clearing 's'
        // so it doesn't additionally AND-filter by title.
        $iscrizioni = get_posts([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => [['key' => 'partecipante', 'value' => $partecipanti, 'compare' => 'IN']],
        ]);
        if (!$iscrizioni) return;

        $query->set('s', '');
        $query->set('post__in', $iscrizioni);
    }

    public function render_stato_filter(): void {
        global $typenow;
        if ($typenow !== 'wsma_iscrizione') return;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin list-table filter (same pattern as WordPress core's own post_status filter), not a form submission.
        $current = isset($_GET['ws_stato_filter']) ? sanitize_key(wp_unslash($_GET['ws_stato_filter'])) : '';
        ?>
        <select name="ws_stato_filter">
            <option value=""><?php esc_html_e('Tutti gli stati', 'wsmaker'); ?></option>
            <option value="confermato" <?php selected($current, 'confermato'); ?>><?php esc_html_e('Confermato', 'wsmaker'); ?></option>
            <option value="richiesta" <?php selected($current, 'richiesta'); ?>><?php esc_html_e('Richiesta', 'wsmaker'); ?></option>
        </select>
        <?php
    }

    public function apply_stato_filter(WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'wsma_iscrizione') return;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin list-table filter (same pattern as WordPress core's own post_status filter), not a form submission.
        $stato = isset($_GET['ws_stato_filter']) ? sanitize_key(wp_unslash($_GET['ws_stato_filter'])) : '';
        if (!in_array($stato, ['confermato', 'richiesta'], true)) return;

        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = ['key' => 'stato', 'value' => $stato, 'compare' => '='];
        $query->set('meta_query', $meta_query);
    }
}
