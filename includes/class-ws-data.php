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
final class WS_Data {

    public static function get_field(string $field, $id = false) {
        if (function_exists('get_field')) {
            return \get_field($field, $id);
        }
        if (is_string($id) && strpos($id, 'categoria_evento_') === 0) {
            $term_id = (int) str_replace('categoria_evento_', '', $id);
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
        if (is_string($id) && strpos($id, 'categoria_evento_') === 0) {
            $term_id = (int) str_replace('categoria_evento_', '', $id);
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
            'post_type'      => 'iscrizione',
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
            if ($stato === 'Confermato')  $conf++; elseif ($stato === 'Richiesta') $ric++;
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
            'post_type'      => 'iscrizione',
            'posts_per_page' => -1,
            'meta_query'     => [['key' => 'partecipante', 'value' => $pid, 'compare' => '=']],
            'no_found_rows'  => true,
        ]);
        if (!empty($iscrizioni)) {
            update_postmeta_cache(wp_list_pluck($iscrizioni, 'ID'));
        }
        $events = [];
        foreach ($iscrizioni as $isc) {
            $eid = self::get_field('evento', $isc->ID);
            $ev_label = $eid ? get_the_title($eid) : '?';
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
            'post_type' => 'iscrizione', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC',
            'meta_query' => [['key' => 'evento', 'value' => $evento_id, 'compare' => '=']], 'no_found_rows' => true,
        ]);
        return $q->posts;
    }

    /** Only Confermato registrations occupy a slot, weighted by num_persone for group bookings. */
    public static function count_confermati(int $evento_id): int {
        static $cache = [];
        if (isset($cache[$evento_id])) return $cache[$evento_id];

        $q = new WP_Query([
            'post_type' => 'iscrizione', 'posts_per_page' => -1, 'fields' => 'ids',
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
            'post_type' => 'iscrizione', 'posts_per_page' => -1, 'fields' => 'ids',
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
        return self::data_fine($evento_id) < date('Y-m-d');
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
        $all_cats = get_terms(['taxonomy' => 'categoria_evento', 'hide_empty' => false]);
        if (is_wp_error($all_cats)) return null;
        foreach ($all_cats as $cat) {
            $cat_url = self::get_field('url_pagina', 'categoria_evento_' . $cat->term_id);
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

        $secret = get_user_meta($user_id, 'fvw_ics_secret_key', true);
        if (!$secret) {
            $secret = wp_generate_password(32, false);
            update_user_meta($user_id, 'fvw_ics_secret_key', $secret);
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
            update_user_meta($user_id, 'fvw_ics_secret_key', $new_secret);
        }
        return self::calendar_token($user_id);
    }

    public static function calendar_url(?int $user_id = null): string {
        return home_url('/wv-calendar/' . self::calendar_token($user_id) . '.ics');
    }

    public static function find_iscrizione(int $partecipante_id, int $evento_id): int {
        $q = new WP_Query([
            'post_type' => 'iscrizione', 'posts_per_page' => 1, 'fields' => 'ids',
            'meta_query' => ['relation' => 'AND',
                ['key' => 'partecipante', 'value' => $partecipante_id, 'compare' => '='],
                ['key' => 'evento', 'value' => $evento_id, 'compare' => '=']],
            'no_found_rows' => true,
        ]);
        return (int) ($q->posts[0] ?? 0);
    }

    public static function find_partecipante_by_email(string $email): int {
        $email = sanitize_email($email);
        if (!$email) return 0;
        $q = new WP_Query([
            'post_type' => 'partecipante', 'posts_per_page' => 1, 'fields' => 'ids',
            'meta_query' => [['key' => 'email', 'value' => $email, 'compare' => '=']], 'no_found_rows' => true,
        ]);
        return (int) ($q->posts[0] ?? 0);
    }

    public static function evento_label(int $evento_id): string {
        $terms = get_the_terms($evento_id, 'categoria_evento');
        $cat = $terms ? $terms[0]->name : '—';
        return $cat . ' – ' . self::format_periodo($evento_id);
    }

    /** Ported 1:1 from the legacy `wv_render_template()`. */
    public static function render_template(string $template, int $iscrizione_id): string {
        if (!$template) return '';

        $pid = (int) get_field('partecipante', $iscrizione_id);
        $eid = (int) get_field('evento', $iscrizione_id);

        $nome = $pid ? get_field('nome', $pid) : '';
        $cognome = $pid ? get_field('cognome', $pid) : '';
        $email = $pid ? get_field('email', $pid) : '';
        $telefono = $pid ? get_field('telefono', $pid) : '';
        $citta = $pid ? get_field('citta', $pid) : '';

        $terms = $eid ? get_the_terms($eid, 'categoria_evento') : null;
        $categoria_nome = $terms ? $terms[0]->name : '';
        $categoria_url = $terms ? get_field('url_pagina', 'categoria_evento_' . $terms[0]->term_id) : '';

        $d1 = $eid ? get_field('data_evento', $eid) : '';
        $d2 = $eid ? (get_field('data_fine', $eid) ?: $d1) : '';
        $data_inizio = $d1 ? date_i18n('j F Y', strtotime($d1)) : '';
        $data_fine = $d2 ? date_i18n('j F Y', strtotime($d2)) : '';
        $ora_inizio = $eid ? get_field('ora_inizio', $eid) : '';
        $ora_fine = $eid ? get_field('ora_fine', $eid) : '';
        $periodo = $eid ? self::format_periodo($eid) : '';

        $s = $eid ? self::stato_posti($eid) : ['totali' => 0, 'disponibili' => 0];
        $anticipo = (float) get_field('anticipo', $iscrizione_id);
        $saldo = (float) get_field('saldo', $iscrizione_id);
        $msg_orig = get_field('messaggio_originale', $iscrizione_id) ?: get_field('note', $iscrizione_id);

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
        ];

        return strtr($template, $placeholders);
    }

    /** Ported 1:1 from the legacy `wv_genera_ics()` (single-event .ics for the Conferma attachment). */
    public static function genera_ics(int $evento_id): string {
        if (!$evento_id) return '';

        $data_inizio = get_field('data_evento', $evento_id);
        $data_fine = get_field('data_fine', $evento_id) ?: $data_inizio;
        $ora_inizio = get_field('ora_inizio', $evento_id) ?: '09:00';
        $ora_fine = get_field('ora_fine', $evento_id) ?: '18:00';

        if (!$data_inizio) return '';

        $terms = get_the_terms($evento_id, 'categoria_evento');
        $cat_name = $terms ? $terms[0]->name : 'Workshop';

        $dtstart = date('Ymd\THis', strtotime($data_inizio . ' ' . $ora_inizio));
        $dtend = date('Ymd\THis', strtotime($data_fine . ' ' . $ora_fine));
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
        $d = get_field('data_evento', $evento_id);
        return $d ? date_i18n('D d M Y', strtotime($d)) : '';
    }

    /** @return int[] iscrizione IDs */
    public static function iscrizioni_partecipante(int $partecipante_id): array {
        $q = new WP_Query([
            'post_type' => 'iscrizione', 'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => [['key' => 'partecipante', 'value' => $partecipante_id, 'compare' => '=']], 'no_found_rows' => true,
        ]);
        return $q->posts;
    }
}
