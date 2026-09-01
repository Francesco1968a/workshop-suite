<?php

if (!defined('ABSPATH')) exit;

/**
 * Shared read helpers for the `partecipante`/`iscrizione`/`evento` post types.
 *
 * Ported 1:1 from legacy Code Snippets helper functions. Kept here instead
 * of depending on the legacy snippets so this plugin has no runtime
 * dependency on snippets that may be deactivated later (deactivating "Admin
 * Contatti" already broke the list panel once this way, by removing
 * wv_stats_partecipante/wv_timeline_partecipante out from under it).
 */
final class WSMA_Data {

    /**
     * Shared handle for one-off inline CSS/JS blocks that previously
     * printed as raw <style>/<script> tags directly in HTML output —
     * WP.org guidelines require these go through wp_add_inline_style()/
     * wp_add_inline_script(), which need a registered handle to attach
     * to. `false` as the src registers a handle with no actual file,
     * exactly for this "inline content only" case (a documented core
     * pattern, not a workaround).
     */
    public static function enqueue_inline_style(string $css): void {
        if (!wp_style_is('ws-inline', 'registered')) {
            wp_register_style('ws-inline', false);
        }
        if (!wp_style_is('ws-inline', 'enqueued')) {
            wp_enqueue_style('ws-inline');
        }
        wp_add_inline_style('ws-inline', $css);
    }

    public static function enqueue_inline_script(string $js): void {
        if (!wp_script_is('ws-inline', 'registered')) {
            wp_register_script('ws-inline', false, [], WSMA_VERSION, true);
        }
        if (!wp_script_is('ws-inline', 'enqueued')) {
            wp_enqueue_script('ws-inline');
        }
        wp_add_inline_script('ws-inline', $js);
    }

    public static function get_field(string $field, $id = false) {
        if (function_exists('get_field')) {
            return \get_field($field, $id);
        }
        if (is_string($id) && strpos($id, 'wsma_categoria_evento_') === 0) {
            $term_id = (int) str_replace('wsma_categoria_evento_', '', $id);
            return get_term_meta($term_id, $field, true);
        }
        if ($id === false) {
            $id = get_the_ID();
        }
        if (is_numeric($id)) {
            return get_post_meta((int)$id, $field, true);
        }
        return null;
    }

    public static function update_field(string $field, $value, $id = false) {
        if (function_exists('update_field')) {
            return \update_field($field, $value, $id);
        }
        if (is_string($id) && strpos($id, 'wsma_categoria_evento_') === 0) {
            $term_id = (int) str_replace('wsma_categoria_evento_', '', $id);
            return update_term_meta($term_id, $field, $value);
        }
        if ($id === false) {
            $id = get_the_ID();
        }
        if (is_numeric($id)) {
            return update_post_meta((int)$id, $field, $value);
        }
        return false;
    }

    // -----------------------------------------------------------------------
    // Thread helpers — centralise the repeated wv_thread read/push/write
    // pattern that was duplicated verbatim in 5 different classes.
    // -----------------------------------------------------------------------

    public const META_THREAD = 'wv_thread';

    /**
     * Read the thread log for an iscrizione.
     *
     * @return array<int, array<string, string>> ordered log entries
     */
    public static function get_thread(int $isc_id): array {
        $raw = get_post_meta($isc_id, self::META_THREAD, true);
        $thread = $raw ? json_decode($raw, true) : [];
        return is_array($thread) ? $thread : [];
    }

    /**
     * Append one entry to the thread log for an iscrizione and persist it.
     *
     * @param int    $isc_id    iscrizione post ID
     * @param string $direction 'out' (sent by staff) or 'in' (received)
     * @param string $subject   email subject
     * @param string $body      email body / plain text
     * @param string|null $date Optional date override (MySQL format). Defaults to current_time('mysql').
     * @param bool   $sort      When true, re-sort entries chronologically after appending (for inbound mail).
     */
    public static function append_thread(int $isc_id, string $direction, string $subject, string $body, ?string $date = null, bool $sort = false): void {
        $thread   = self::get_thread($isc_id);
        $thread[] = [
            'direction' => $direction,
            'subject'   => $subject,
            'body'      => $body,
            'date'      => $date ?? current_time('mysql'),
        ];
        if ($sort) {
            usort($thread, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));
        }
        update_post_meta($isc_id, self::META_THREAD, wp_json_encode($thread));
    }

    public static function stats_partecipante(int $pid): array {
        $iscrizioni = get_posts([
            'post_type'      => 'wsma_iscrizione',
            'posts_per_page' => -1,
            'meta_query'     => [['key' => 'partecipante', 'value' => $pid, 'compare' => '=']],
            'orderby'        => 'date',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);
        $tot = count($iscrizioni); $conf = $ab = $ric = 0; $mail_inviate = 0;
        $prima_iscr = $ultimo_contatto = null;

        if (!empty($iscrizioni)) {
            update_postmeta_cache(wp_list_pluck($iscrizioni, 'ID'));
        }

        foreach ($iscrizioni as $isc) {
            $stato = self::get_field('stato', $isc->ID);
            if ($stato === 'confermato')  $conf++; else $ric++;
            if (self::get_field('checkpoint_decision', $isc->ID) === 'abbandonato') $ab++;
            foreach (['mail_risposta_sent_at','mail_followup_sent_at','mail_welcome_sent_at'] as $k) {
                $v = self::get_field($k, $isc->ID);
                if ($v) { $mail_inviate++;
                    if (!$ultimo_contatto || strtotime($v) > strtotime($ultimo_contatto)) $ultimo_contatto = $v; }
            }
            if (!$prima_iscr) $prima_iscr = get_post_time('Y-m-d H:i:s', false, $isc->ID);
        }
        return ['totali' => $tot, 'confermate' => $conf, 'richieste' => $ric, 'abbandonate' => $ab,
            'conv_pct' => $tot ? round($conf * 100 / $tot) : 0, 'mail_inviate' => $mail_inviate,
            'prima_iscr' => $prima_iscr, 'ultimo_contatto' => $ultimo_contatto,
            'giorni_ultimo' => $ultimo_contatto ? round((time() - strtotime($ultimo_contatto)) / 86400) : null,
            'iscrizioni' => $iscrizioni];
    }

    public static function timeline_partecipante(int $pid): array {
        $iscrizioni = get_posts([
            'post_type'      => 'wsma_iscrizione',
            'posts_per_page' => -1,
            'meta_query'     => [['key' => 'partecipante', 'value' => $pid, 'compare' => '=']],
            'no_found_rows'  => true,
        ]);
        if (!empty($iscrizioni)) {
            update_postmeta_cache(wp_list_pluck($iscrizioni, 'ID'));
        }
        $events = [];
        foreach ($iscrizioni as $isc) {
            $tipo = self::get_field('tipo_iscrizione', $isc->ID) ?: 'workshop';
            if ($tipo === 'corso') {
                $cid = self::get_field('corso', $isc->ID);
                $ev_label = $cid ? get_the_title($cid) : '?';
            } else {
                $eid = self::get_field('evento', $isc->ID);
                $ev_label = $eid ? get_the_title($eid) : '?';
            }
            $msg_orig = self::get_field('messaggio_originale', $isc->ID);
            $events[] = ['t' => get_post_time('Y-m-d H:i:s', false, $isc->ID),
                'icon' => '📝', 'titolo' => 'Iscrizione creata', 'evento' => $ev_label,
                'extra' => $msg_orig ? '« ' . wp_trim_words($msg_orig, 30, '…') . ' »' : ''];

            $t = self::get_field('mail_risposta_sent_at', $isc->ID);
            if ($t) {
                $ai = self::get_field('mail_risposta_ai_used', $isc->ID);
                $is_skipped = $ai && strpos($ai, 'skipped') !== false;
                $events[] = ['t' => $t,
                    'icon' => $is_skipped ? '⏭' : ($ai === 'fallback' ? '✉' : '🤖'),
                    'titolo' => $is_skipped ? 'Mail #1 saltata' : ($ai === 'fallback' ? 'Mail #1 inviata (fallback)' : 'Mail #1 AI inviata'),
                    'evento' => $ev_label,
                    'extra' => (!$is_skipped && $ai && $ai !== 'fallback') ? 'motore: ' . $ai : ''];
            }
            $t = self::get_field('mail_followup_sent_at', $isc->ID);
            if ($t) $events[] = ['t' => $t, 'icon' => '✉', 'titolo' => 'Follow-up inviato', 'evento' => $ev_label, 'extra' => ''];
            $t = self::get_field('mail_welcome_sent_at', $isc->ID);
            if ($t) $events[] = ['t' => $t, 'icon' => '👋', 'titolo' => 'Mail "A presto" inviata', 'evento' => $ev_label, 'extra' => ''];

            $dec = self::get_field('checkpoint_decision', $isc->ID);
            $dec_at = self::get_field('checkpoint_decided_at', $isc->ID);
            if ($dec && $dec_at) {
                $labels = ['whatsapp' => '✓ Ha scritto su WhatsApp', 'send_followup' => '↪ Decisione: manda follow-up', 'abbandonato' => '⏸ Abbandonato'];
                $events[] = ['t' => $dec_at, 'icon' => '🔀', 'titolo' => $labels[$dec] ?? $dec, 'evento' => $ev_label, 'extra' => ''];
            }
            $t = self::get_field('replied_at', $isc->ID);
            if ($t) $events[] = ['t' => $t, 'icon' => '💬', 'titolo' => 'Risposta ricevuta', 'evento' => $ev_label, 'extra' => ''];

            $log_raw = self::get_field('stato_log', $isc->ID);
            if ($log_raw) {
                $log = json_decode($log_raw, true);
                if (is_array($log)) foreach ($log as $entry) {
                    $events[] = ['t' => $entry['t'], 'icon' => $entry['to'] === 'Confermato' ? '✅' : '🔵',
                        'titolo' => 'Stato: ' . $entry['from'] . ' → ' . $entry['to'],
                        'evento' => $ev_label, 'extra' => ''];
                }
            }
        }
        usort($events, fn($a, $b) => strtotime($b['t']) <=> strtotime($a['t']));
        return $events;
    }

    public static function iscrizioni_evento(int $evento_id): array {
        $q = new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC',
            'meta_query' => [['key' => 'evento', 'value' => $evento_id, 'compare' => '=']], 'no_found_rows' => true,
        ]);
        return $q->posts;
    }

    /** Only Confermato registrations occupy a slot, weighted by num_persone for group bookings. */
    public static function count_confermati(int $evento_id): int {
        static $cache = [];
        if (isset($cache[$evento_id])) return $cache[$evento_id];

        $q = new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => ['relation' => 'AND',
                ['key' => 'evento', 'value' => $evento_id, 'compare' => '='],
                ['key' => 'stato', 'value' => 'confermato', 'compare' => '=']],
            'no_found_rows' => true,
        ]);
        $totale_posti = 0;
        if (!empty($q->posts)) {
            update_postmeta_cache($q->posts);
            foreach ($q->posts as $isc_id) {
                $np = (int) get_post_meta($isc_id, 'num_persone', true);
                $totale_posti += max(1, $np);
            }
        }
        return $cache[$evento_id] = $totale_posti;
    }

    public static function count_richieste(int $evento_id): int {
        static $cache = [];
        if (isset($cache[$evento_id])) return $cache[$evento_id];

        $q = new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => ['relation' => 'AND',
                ['key' => 'evento', 'value' => $evento_id, 'compare' => '='],
                ['key' => 'stato', 'value' => 'richiesta', 'compare' => '=']],
            'no_found_rows' => true,
        ]);
        return $cache[$evento_id] = count($q->posts);
    }

    public static function stato_posti(int $evento_id): array {
        static $cache = [];
        if (isset($cache[$evento_id])) return $cache[$evento_id];

        $totali = (int) self::get_field('posti_totali', $evento_id);
        $occupati = self::count_confermati($evento_id);
        $disponibili = max(0, $totali - $occupati);
        return $cache[$evento_id] = ['totali' => $totali, 'occupati' => $occupati, 'disponibili' => $disponibili, 'sold_out' => $disponibili <= 0];
    }

    public static function data_fine(int $evento_id): string {
        return self::get_field('data_fine', $evento_id) ?: self::get_field('data_evento', $evento_id);
    }

    public static function evento_concluso(int $evento_id): bool {
        return self::data_fine($evento_id) < current_time('Y-m-d');
    }

    public static function format_periodo(int $evento_id): string {
        $d1 = self::get_field('data_evento', $evento_id);
        $d2 = self::get_field('data_fine', $evento_id);
        if (!$d1) return '';
        if ($d2 && $d2 !== $d1) {
            return date_i18n('d M', strtotime($d1)) . ' – ' . date_i18n('d M Y', strtotime($d2));
        }
        return date_i18n('d M Y', strtotime($d1));
    }

    /**
     * Ported from the legacy `wv_find_categoria_by_url()` (originally
     * defined in the "Integrazione Fluent Forms" snippet) — matches a
     * front-end page URL against each categoria_evento term's `url_pagina`
     * ACF field. Returns a WP_Term or null.
     */
    public static function find_categoria_by_url(string $url): ?WP_Term {
        $source_norm = untrailingslashit($url);
        $all_cats = get_terms(['taxonomy' => 'wsma_categoria_evento', 'hide_empty' => false]);
        if (is_wp_error($all_cats)) return null;
        foreach ($all_cats as $cat) {
            $cat_url = self::get_field('url_pagina', 'wsma_categoria_evento_' . $cat->term_id);
            if ($cat_url && untrailingslashit($cat_url) === $source_norm) {
                return $cat;
            }
        }
        return null;
    }

    public static function find_page_url_containing(string $shortcode_needle): string {
        static $cache = [];
        if (isset($cache[$shortcode_needle])) return $cache[$shortcode_needle];
        $pages = get_posts([
            'post_type' => 'page', 'posts_per_page' => 1, 'fields' => 'ids',
            's' => $shortcode_needle, 'no_found_rows' => true,
        ]);
        return $cache[$shortcode_needle] = $pages ? get_permalink($pages[0]) : '';
    }

    /**
     * Same derivation as the legacy `wv_cal_token()`/`wv_cal_url()` (still
     * live in the "Calendari esterni" snippet, which also serves the actual
     * `/wv-calendar/[token].ics` feed — that snippet stays active on
     * purpose, this is just the read-only URL display for the admin panel).
     */
    /**
     * Generates a per-user, revocable calendar token.
     * Fallback: includes legacy global hash for backward compatibility.
     */
    public static function calendar_token(?int $user_id = null): string {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        if (!$user_id) {
            return self::legacy_calendar_token();
        }

        $secret = get_user_meta($user_id, 'ws_ics_secret_key', true);
        if (!$secret) {
            // Migrate silently from the pre-rename meta key so already-active
            // .ics calendar subscriptions (which embed a token derived from
            // this secret) don't get silently invalidated by the rename.
            $secret = get_user_meta($user_id, 'fvw_ics_secret_key', true);
            if ($secret) {
                update_user_meta($user_id, 'ws_ics_secret_key', $secret);
            }
        }
        if (!$secret) {
            $secret = wp_generate_password(32, false);
            update_user_meta($user_id, 'ws_ics_secret_key', $secret);
        }

        return substr(hash_hmac('sha256', 'wv_cal_user_' . $user_id . '_' . $secret, wp_salt('auth')), 0, 32);
    }

    /** Legacy global token fallback to avoid breaking existing external calendar subscriptions */
    public static function legacy_calendar_token(): string {
        return substr(hash_hmac('sha256', 'wv_calendar_v1', wp_salt('auth')), 0, 32);
    }

    /** Revokes and regenerates the calendar token for a given user */
    public static function revoke_calendar_token(?int $user_id = null): string {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        if ($user_id) {
            $new_secret = wp_generate_password(32, false);
            update_user_meta($user_id, 'ws_ics_secret_key', $new_secret);
        }
        return self::calendar_token($user_id);
    }

    public static function calendar_url(?int $user_id = null): string {
        return home_url('/wv-calendar/' . self::calendar_token($user_id) . '.ics');
    }

    public static function find_iscrizione(int $partecipante_id, int $evento_id): int {
        $q = new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => 1, 'fields' => 'ids',
            'meta_query' => ['relation' => 'AND',
                ['key' => 'partecipante', 'value' => $partecipante_id, 'compare' => '='],
                ['key' => 'evento', 'value' => $evento_id, 'compare' => '=']],
            'no_found_rows' => true,
        ]);
        return (int) ($q->posts[0] ?? 0);
    }

    public static function find_iscrizione_corso(int $partecipante_id, int $corso_id): int {
        $q = new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => 1, 'fields' => 'ids',
            'meta_query' => ['relation' => 'AND',
                ['key' => 'partecipante', 'value' => $partecipante_id, 'compare' => '='],
                ['key' => 'corso', 'value' => $corso_id, 'compare' => '=']],
            'no_found_rows' => true,
        ]);
        return (int) ($q->posts[0] ?? 0);
    }

    public static function find_partecipante_by_email(string $email): int {
        $email = sanitize_email($email);
        if (!$email) return 0;
        $q = new WP_Query([
            'post_type' => 'wsma_partecipante', 'posts_per_page' => 1, 'fields' => 'ids',
            'meta_query' => [['key' => 'email', 'value' => $email, 'compare' => '=']], 'no_found_rows' => true,
        ]);
        return (int) ($q->posts[0] ?? 0);
    }

    public static function evento_label(int $evento_id): string {
        $terms = get_the_terms($evento_id, 'wsma_categoria_evento');
        $cat = $terms ? $terms[0]->name : '—';
        return $cat . ' – ' . self::format_periodo($evento_id);
    }

    /** Ported 1:1 from the legacy `wv_render_template()`. */
    public static function render_template(string $template, int $iscrizione_id): string {
        if (!$template) return '';

        $pid = (int) self::get_field('partecipante', $iscrizione_id);
        $eid = (int) self::get_field('evento', $iscrizione_id);

        $nome = $pid ? self::get_field('nome', $pid) : '';
        $cognome = $pid ? self::get_field('cognome', $pid) : '';
        $email = $pid ? self::get_field('email', $pid) : '';
        $telefono = $pid ? self::get_field('telefono', $pid) : '';
        $citta = $pid ? self::get_field('citta', $pid) : '';

        $terms = $eid ? get_the_terms($eid, 'wsma_categoria_evento') : null;
        $categoria_nome = $terms ? $terms[0]->name : '';
        $categoria_url = $terms ? self::get_field('url_pagina', 'wsma_categoria_evento_' . $terms[0]->term_id) : '';

        $d1 = $eid ? self::get_field('data_evento', $eid) : '';
        $d2 = $eid ? (self::get_field('data_fine', $eid) ?: $d1) : '';
        $data_inizio = $d1 ? date_i18n('j F Y', strtotime($d1)) : '';
        $data_fine = $d2 ? date_i18n('j F Y', strtotime($d2)) : '';
        $ora_inizio = $eid ? self::get_field('ora_inizio', $eid) : '';
        $ora_fine = $eid ? self::get_field('ora_fine', $eid) : '';
        $periodo = $eid ? self::format_periodo($eid) : '';

        $s = $eid ? self::stato_posti($eid) : ['totali' => 0, 'disponibili' => 0];
        $anticipo = (float) self::get_field('anticipo', $iscrizione_id);
        $saldo = (float) self::get_field('saldo', $iscrizione_id);
        $msg_orig = self::get_field('messaggio_originale', $iscrizione_id) ?: self::get_field('note', $iscrizione_id);

        // {luogo} resolves to the meeting link for virtual events and the
        // physical address otherwise — same placeholder, different content,
        // so mail templates don't need a modalità-specific variant.
        $modalita = $eid ? (self::get_field('modalita', $eid) ?: 'fisico') : 'fisico';
        if ($modalita === 'virtuale') {
            // For Jitsi (embedded), point to our own [ws_aula_virtuale] page
            // instead of the raw meet.jit.si URL — same "no visible YouTube/
            // Jitsi branding" preference already applied to lesson videos.
            // Zoom/Meet/altro keep going straight to the external link, same
            // as before this feature existed.
            $aula_page_id = $eid ? (int) get_post_meta($eid, '_ws_aula_page_id', true) : 0;
            $luogo = ($aula_page_id && get_post_status($aula_page_id) === 'publish')
                ? get_permalink($aula_page_id)
                : (string) self::get_field('link_virtuale', $eid);
        } else {
            $luogo = (string) self::get_field('indirizzo_geocoding', $eid);
        }

        $placeholders = [
            '{nome}' => $nome, '{cognome}' => $cognome, '{nome_completo}' => trim($nome . ' ' . $cognome),
            '{email}' => $email, '{telefono}' => $telefono, '{citta}' => $citta,
            '{categoria_nome}' => $categoria_nome, '{categoria_url}' => $categoria_url,
            '{periodo}' => $periodo, '{data_inizio}' => $data_inizio, '{data_fine}' => $data_fine,
            '{ora_inizio}' => $ora_inizio, '{ora_fine}' => $ora_fine,
            '{posti_totali}' => $s['totali'], '{posti_disponibili}' => $s['disponibili'],
            '{anticipo}' => '€ ' . number_format($anticipo, 2, ',', '.'),
            '{saldo}' => '€ ' . number_format($saldo, 2, ',', '.'),
            '{messaggio_originale}' => $msg_orig,
            '{luogo}' => $luogo,
            // Empty by default — PRO's Stripe connector fills this in via
            // the filter below when a deposit/balance is actually due and
            // no WooCommerce product already owns this event's payment
            // (mirrors the wsma_iscrizione_created generic-hook convention:
            // core has no idea Stripe exists).
            '{link_pagamento}' => '',
        ];

        $placeholders = apply_filters('wsma_confirmation_placeholders', $placeholders, $iscrizione_id);

        return strtr($template, $placeholders);
    }

    /**
     * Placeholder substitution for corso (course) template emails — kept
     * separate from render_template() since evento and corso placeholders
     * are incompatible sets (seats/dates vs. course title/access link).
     * $extra lets the caller (e.g. the access-link sender) merge in values
     * this function has no way to compute on its own, like {link_accesso}.
     */
    public static function render_template_corso(string $template, int $iscrizione_id, array $extra = []): string {
        if (!$template) return '';

        $pid = (int) self::get_field('partecipante', $iscrizione_id);
        $cid = (int) self::get_field('corso', $iscrizione_id);

        $nome = $pid ? self::get_field('nome', $pid) : '';
        $cognome = $pid ? self::get_field('cognome', $pid) : '';
        $email = $pid ? self::get_field('email', $pid) : '';

        $corso_titolo = $cid ? get_the_title($cid) : '';
        $instructor = $cid ? get_post_meta($cid, '_ws_course_instructor', true) : '';

        $placeholders = array_merge([
            '{nome}' => $nome, '{cognome}' => $cognome, '{nome_completo}' => trim($nome . ' ' . $cognome),
            '{email}' => $email,
            '{corso_titolo}' => $corso_titolo,
            '{instructor}' => $instructor,
            '{link_accesso}' => '',
        ], $extra);

        return strtr($template, $placeholders);
    }

    /** Ported 1:1 from the legacy `wv_genera_ics()` (single-event .ics for the Conferma attachment). */
    public static function genera_ics(int $evento_id): string {
        if (!$evento_id) return '';

        $data_inizio = self::get_field('data_evento', $evento_id);
        $data_fine = self::get_field('data_fine', $evento_id) ?: $data_inizio;
        $ora_inizio = self::get_field('ora_inizio', $evento_id) ?: '09:00';
        $ora_fine = self::get_field('ora_fine', $evento_id) ?: '18:00';

        if (!$data_inizio) return '';

        $terms = get_the_terms($evento_id, 'wsma_categoria_evento');
        $cat_name = $terms ? $terms[0]->name : 'Workshop';

        $dtstart = wp_date('Ymd\THis', strtotime($data_inizio . ' ' . $ora_inizio));
        $dtend = wp_date('Ymd\THis', strtotime($data_fine . ' ' . $ora_fine));
        $uid = 'wv-evento-' . $evento_id . '@francescoverolino.com';
        $dtstamp = gmdate('Ymd\THis\Z');

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Francesco Verolino//Workshop//IT\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . $uid . "\r\n";
        $ics .= "DTSTAMP:" . $dtstamp . "\r\n";
        $ics .= "DTSTART;TZID=Europe/Rome:" . $dtstart . "\r\n";
        $ics .= "DTEND;TZID=Europe/Rome:" . $dtend . "\r\n";
        $ics .= "SUMMARY:" . $cat_name . "\r\n";
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    public static function count_partecipanti(int $evento_id): int {
        return count(self::iscrizioni_evento($evento_id));
    }

    public static function format_data(int $evento_id): string {
        $d = self::get_field('data_evento', $evento_id);
        return $d ? date_i18n('D d M Y', strtotime($d)) : '';
    }

    /** @return int[] iscrizione IDs */
    public static function iscrizioni_partecipante(int $partecipante_id): array {
        $q = new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => [['key' => 'partecipante', 'value' => $partecipante_id, 'compare' => '=']], 'no_found_rows' => true,
        ]);
        return $q->posts;
    }

    /**
     * Course access control (Fase 3). There is no customer-facing WP login
     * in this system — partecipanti are matched by email, never tied to a
     * WP_User — so identity/entitlement for course content can't use
     * is_user_logged_in(). This generalizes the calendar_token() HMAC
     * pattern (see above) from a per-WP_User secret to a per-partecipante
     * one, stored in postmeta instead of usermeta.
     */

    /** Get-or-create the per-partecipante secret backing every token below. */
    public static function partecipante_access_secret(int $partecipante_id): string {
        $secret = get_post_meta($partecipante_id, '_ws_access_secret', true);
        if (!$secret) {
            $secret = wp_generate_password(32, false);
            update_post_meta($partecipante_id, '_ws_access_secret', $secret);
        }
        return $secret;
    }

    /**
     * Token proving "this partecipante was granted access to this specific
     * course" — scoped to $course_id so a leaked link for one course can't
     * be replayed against another. Sent once via email; does NOT itself
     * grant access (see has_course_access()) — it only proves identity.
     */
    public static function course_access_token(int $partecipante_id, int $course_id): string {
        $secret = self::partecipante_access_secret($partecipante_id);
        return substr(hash_hmac('sha256', 'ws_course_access_' . $partecipante_id . '_' . $course_id . '_' . $secret, wp_salt('auth')), 0, 32);
    }

    public static function verify_course_access_token(int $partecipante_id, int $course_id, string $token): bool {
        if (!$partecipante_id || !$token) return false;
        return hash_equals(self::course_access_token($partecipante_id, $course_id), $token);
    }

    /**
     * Identity token backing the long-lived "who is this visitor" cookie —
     * deliberately a different HMAC message than course_access_token() so
     * a leaked one-course email link can't be reused to forge the cookie.
     */
    public static function partecipante_identity_token(int $partecipante_id): string {
        $secret = self::partecipante_access_secret($partecipante_id);
        return substr(hash_hmac('sha256', 'ws_identity_' . $partecipante_id . '_' . $secret, wp_salt('auth')), 0, 32);
    }

    /** Rotates the secret, invalidating every previously-issued link/cookie for this partecipante in one step (e.g. on refund). */
    public static function revoke_partecipante_access(int $partecipante_id): void {
        update_post_meta($partecipante_id, '_ws_access_secret', wp_generate_password(32, false));
    }

    /**
     * The entitlement authority — does partecipante $partecipante_id
     * currently have confirmed access to $course_id? Re-checked live on
     * every call (never cached in the token/cookie), so revoking access
     * (un-confirming an iscrizione, a refund) takes effect immediately.
     */
    public static function has_course_access(int $partecipante_id, int $course_id): bool {
        if (!$partecipante_id || !$course_id) return false;

        $isc_corso = self::find_iscrizione_corso($partecipante_id, $course_id);
        if ($isc_corso && self::get_field('stato', $isc_corso) === 'confermato') {
            return true;
        }

        $linked_evento = (int) get_post_meta($course_id, '_ws_linked_workshop_id', true);
        if ($linked_evento) {
            $isc_workshop = self::find_iscrizione($partecipante_id, $linked_evento);
            if ($isc_workshop && self::get_field('stato', $isc_workshop) === 'confermato') {
                // Lazy shadow-iscrizione (tipo_iscrizione=corso), created only
                // now — the first time this confirmed attendee actually opens
                // the course — not eagerly when their workshop iscrizione was
                // confirmed. It exists purely as a container for course
                // progress tracking (Fase 5); entitlement itself still comes
                // from the workshop iscrizione, not this one.
                if (!$isc_corso) {
                    self::ensure_shadow_iscrizione_corso($partecipante_id, $course_id);
                }
                return true;
            }
        }

        return false;
    }

    /** Get-or-create a confermato tipo_iscrizione=corso row with no side effect beyond that. */
    private static function ensure_shadow_iscrizione_corso(int $partecipante_id, int $course_id): int {
        $existing = self::find_iscrizione_corso($partecipante_id, $course_id);
        if ($existing) return $existing;

        $isc_id = wp_insert_post([
            'post_type'   => 'wsma_iscrizione',
            'post_title'  => 'iscr-corso-' . $partecipante_id . '-' . $course_id,
            'post_status' => 'publish',
        ]);
        if (is_wp_error($isc_id) || !$isc_id) return 0;

        update_post_meta($isc_id, 'partecipante', $partecipante_id);
        update_post_meta($isc_id, 'tipo_iscrizione', 'corso');
        update_post_meta($isc_id, 'corso', $course_id);
        update_post_meta($isc_id, 'stato', 'confermato');

        return $isc_id;
    }

    /**
     * Lesson-completion tracking (Fase 5). Stored as a JSON array of lesson
     * post IDs on the corso iscrizione — same JSON-blob-in-postmeta
     * convention already used for wv_thread/stato_log.
     */
    private const META_COMPLETED_LESSONS = '_ws_completed_lessons';

    /** Idempotent — marking an already-completed lesson complete again is a no-op. */
    public static function mark_lesson_complete(int $iscrizione_id, int $lesson_id): void {
        $completed = self::completed_lesson_ids($iscrizione_id);
        if (!in_array($lesson_id, $completed, true)) {
            $completed[] = $lesson_id;
            update_post_meta($iscrizione_id, self::META_COMPLETED_LESSONS, wp_json_encode($completed));
        }
    }

    /** @return int[] */
    public static function completed_lesson_ids(int $iscrizione_id): array {
        $raw = get_post_meta($iscrizione_id, self::META_COMPLETED_LESSONS, true);
        $ids = $raw ? json_decode($raw, true) : [];
        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    /** @return array{completed: int[], total: int, pct: int} */
    public static function course_progress(int $iscrizione_id, int $course_id): array {
        $total_ids = get_posts([
            'post_type' => 'ws_lesson', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
            'meta_key' => '_ws_parent_course_id', 'meta_value' => $course_id, 'no_found_rows' => true,
        ]);
        $completed = $iscrizione_id ? self::completed_lesson_ids($iscrizione_id) : [];
        // Only count completions that still match a real, published lesson
        // of this course — a lesson unpublished/moved after being marked
        // complete shouldn't inflate the percentage past 100.
        $completed = array_values(array_intersect($completed, $total_ids));

        $total = count($total_ids);
        $pct = $total ? (int) round(count($completed) * 100 / $total) : 0;

        return ['completed' => $completed, 'total' => $total, 'pct' => $pct];
    }

    /** Cookie value for a resolved course visitor: "$partecipante_id|$identity_token". */
    public static function course_visitor_cookie_value(int $partecipante_id): string {
        return $partecipante_id . '|' . self::partecipante_identity_token($partecipante_id);
    }

    /**
     * Resolves the current visitor's partecipante ID from the long-lived
     * cookie (set by the course-engine module's template_redirect handler
     * after a valid one-time email link is consumed). Pure cookie read —
     * no GET/token handling here, since setting a cookie and redirecting
     * to strip the token from the URL has to happen before any output, at
     * template_redirect, not inside a the_content filter.
     */
    public static function resolve_course_visitor(): int {
        if (empty($_COOKIE['ws_course_auth'])) return 0;
        $parts = explode('|', sanitize_text_field(wp_unslash($_COOKIE['ws_course_auth'])), 2);
        if (count($parts) !== 2) return 0;
        [$pid_str, $sig] = $parts;
        $pid = (int) $pid_str;
        if (!$pid || !hash_equals(self::partecipante_identity_token($pid), $sig)) return 0;
        return $pid;
    }

    /**
     * Builds the one-time access link and sends it via the same direct-SMTP
     * channel conferma_iscrizione() uses (WSMA_Mail_Inbox::send_reply()),
     * logging to wv_thread the same way. $iscrizione_id (if it already
     * exists for this partecipante+corso) is only used to resolve a nicer
     * {nome}/{corso_titolo} via render_template_corso() and to log the
     * thread entry — the link itself is derived straight from the ids.
     */
    public static function send_course_access_email(int $partecipante_id, int $course_id): array {
        $email = self::get_field('email', $partecipante_id);
        if (!$email) {
            return ['ok' => false, 'msg' => 'Email partecipante mancante'];
        }

        $token = self::course_access_token($partecipante_id, $course_id);
        $link = add_query_arg(['wsat' => $token, 'wspid' => $partecipante_id], get_permalink($course_id));

        $iscrizione_id = self::find_iscrizione_corso($partecipante_id, $course_id);
        $default_subject = 'Il tuo accesso al corso "{corso_titolo}" è pronto';
        $default_body = "Ciao {nome},\n\nil tuo accesso al corso \"{corso_titolo}\" è attivo.\n\nClicca qui per iniziare:\n{link_accesso}\n\nA presto,\n{instructor}";

        $subject = self::render_template_corso($default_subject, $iscrizione_id, ['{link_accesso}' => $link]);
        $body = self::render_template_corso($default_body, $iscrizione_id, ['{link_accesso}' => $link]);

        $result = WSMA_Mail_Inbox::send_reply($email, $subject, $body);
        if ($result['ok'] && $iscrizione_id) {
            self::append_thread($iscrizione_id, 'out', $subject, $body);
        }
        return $result;
    }

    /**
     * The one place any payment connector calls once a real payment has
     * succeeded — gateway-agnostic on purpose, so Stripe (still just a
     * stub) and WooCommerce (Fase 6) funnel into the exact same completion
     * path instead of each duplicating "what happens when payment
     * succeeds". Idempotent: webhooks can and do fire more than once for
     * the same event, so a second call is a safe no-op.
     */
    public static function grant_course_access_after_payment(int $iscrizione_id, float $amount_paid): void {
        if (get_post_type($iscrizione_id) !== 'wsma_iscrizione') return;
        if (self::get_field('tipo_iscrizione', $iscrizione_id) !== 'corso') return;
        if (self::get_field('stato', $iscrizione_id) === 'confermato') return;

        self::update_field('stato', 'confermato', $iscrizione_id);
        $anticipo = (float) self::get_field('anticipo', $iscrizione_id);
        self::update_field('anticipo', $anticipo + $amount_paid, $iscrizione_id);

        $partecipante_id = (int) self::get_field('partecipante', $iscrizione_id);
        $course_id = (int) self::get_field('corso', $iscrizione_id);
        if ($partecipante_id && $course_id) {
            self::send_course_access_email($partecipante_id, $course_id);
        }
    }
}
