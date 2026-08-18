<?php

if (!defined('ABSPATH')) exit;

/**
 * Live `/wv-calendar/[token].ics` subscription feed for Google/Apple/Outlook.
 * Ported 1:1 from the legacy "Calendari esterni" snippet so that snippet can
 * be fully deactivated. This is real infrastructure (external calendar apps
 * poll this URL on their own schedule), not just an admin panel — must keep
 * behaving identically: same rewrite rule, same ICS output byte-for-byte.
 */
final class WS_Ics_Feed implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('init', [$this, 'add_rewrite_rule']);
        add_filter('query_vars', [$this, 'add_query_var']);
        add_action('template_redirect', [$this, 'maybe_output']);
    }

    public function add_rewrite_rule(): void {
        add_rewrite_rule('^wv-calendar/([a-f0-9]+)\.ics$', 'index.php?wv_cal_token=$matches[1]', 'top');
    }

    public function add_query_var(array $vars): array {
        $vars[] = 'wv_cal_token';
        return $vars;
    }

    public function maybe_output(): void {
        $token = get_query_var('wv_cal_token');
        if (!$token) return;

        if (!$this->is_valid_token($token)) {
            status_header(403);
            exit('Token non valido.');
        }

        $this->output_ics();
        exit;
    }

    private function is_valid_token(string $token): bool {
        // Check legacy global token for backward compatibility
        if (hash_equals(WS_Data::legacy_calendar_token(), $token)) {
            return true;
        }

        // Check if token matches any administrator user
        $admins = get_users(['capability' => 'manage_options', 'fields' => 'ID']);
        foreach ($admins as $uid) {
            $user_token = WS_Data::calendar_token((int) $uid);
            if (hash_equals($user_token, $token)) {
                return true;
            }
        }

        return false;
    }

    private function ics_escape($s): string {
        $s = (string) $s;
        return str_replace(['\\', "\r\n", "\n", "\r", ';', ','], ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'], $s);
    }

    /** RFC 5545: lines max 75 chars, folded with CRLF + space continuation. */
    private function ics_fold(string $line): string {
        if (strlen($line) <= 75) return $line . "\r\n";
        $out = '';
        $first = true;
        while (strlen($line) > 0) {
            $chunk_len = $first ? 75 : 74;
            $chunk = substr($line, 0, $chunk_len);
            $line = substr($line, $chunk_len);
            $out .= ($first ? '' : ' ') . $chunk . "\r\n";
            $first = false;
        }
        return $out;
    }

    private function output_ics(): void {
        nocache_headers();
        $domain = parse_url(home_url(), PHP_URL_HOST) ?: 'localhost';
        $site_name = get_option('blogname') ?: 'Workshop CRM';
        $filename = sanitize_title($site_name) . '-workshop.ics';

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');

        $due_anni_fa = date('Y-m-d', strtotime('-2 years'));

        $eventi = get_posts([
            'post_type' => 'evento',
            'posts_per_page' => -1,
            'meta_key' => 'data_evento',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'meta_query' => [
                ['key' => 'data_evento', 'value' => $due_anni_fa, 'compare' => '>=', 'type' => 'DATE'],
            ],
        ]);

        $tz = 'Europe/Rome';

        $out = '';
        $out .= $this->ics_fold('BEGIN:VCALENDAR');
        $out .= $this->ics_fold('VERSION:2.0');
        $out .= $this->ics_fold('PRODID:-//' . $site_name . '//Workshop CRM//IT');
        $out .= $this->ics_fold('CALSCALE:GREGORIAN');
        $out .= $this->ics_fold('METHOD:PUBLISH');
        $out .= $this->ics_fold('X-WR-CALNAME:Workshop ' . $site_name);
        $out .= $this->ics_fold('X-WR-TIMEZONE:' . $tz);
        $out .= $this->ics_fold('X-PUBLISHED-TTL:PT1H');

        $out .= $this->ics_fold('BEGIN:VTIMEZONE');
        $out .= $this->ics_fold('TZID:Europe/Rome');
        $out .= $this->ics_fold('BEGIN:STANDARD');
        $out .= $this->ics_fold('DTSTART:19701025T030000');
        $out .= $this->ics_fold('TZOFFSETFROM:+0200');
        $out .= $this->ics_fold('TZOFFSETTO:+0100');
        $out .= $this->ics_fold('RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU');
        $out .= $this->ics_fold('TZNAME:CET');
        $out .= $this->ics_fold('END:STANDARD');
        $out .= $this->ics_fold('BEGIN:DAYLIGHT');
        $out .= $this->ics_fold('DTSTART:19700329T020000');
        $out .= $this->ics_fold('TZOFFSETFROM:+0100');
        $out .= $this->ics_fold('TZOFFSETTO:+0200');
        $out .= $this->ics_fold('RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU');
        $out .= $this->ics_fold('TZNAME:CEST');
        $out .= $this->ics_fold('END:DAYLIGHT');
        $out .= $this->ics_fold('END:VTIMEZONE');

        foreach ($eventi as $ev) {
            $date_inizio = WS_Data::get_field('data_evento', $ev->ID);
            if (!$date_inizio) continue;
            $date_fine   = WS_Data::get_field('data_fine', $ev->ID) ?: $date_inizio;
            $ora_inizio  = WS_Data::get_field('ora_inizio', $ev->ID) ?: '09:00';
            $ora_fine    = WS_Data::get_field('ora_fine', $ev->ID) ?: '18:00';

            $terms = get_the_terms($ev->ID, 'categoria_evento');
            $cat_name = $terms ? $terms[0]->name : 'Workshop';
            $cat_url  = $terms ? WS_Data::get_field('url_pagina', 'categoria_evento_' . $terms[0]->term_id) : '';

            $iscrizioni = WS_Data::iscrizioni_evento($ev->ID);
            $confermati = [];
            $richieste = [];
            foreach ($iscrizioni as $isc) {
                $pid = WS_Data::get_field('partecipante', $isc);
                if (!$pid) continue;
                $st = WS_Data::get_field('stato', $isc);
                $nome = trim(WS_Data::get_field('nome', $pid) . ' ' . WS_Data::get_field('cognome', $pid));
                $tel = WS_Data::get_field('telefono', $pid);
                $email = WS_Data::get_field('email', $pid);
                $line = $nome;
                if ($tel)   $line .= ' · ' . $tel;
                if ($email) $line .= ' · ' . $email;
                if ($st === 'confermato') $confermati[] = $line;
                else                      $richieste[]  = $line;
            }

            $posti_totali = (int) WS_Data::get_field('posti_totali', $ev->ID);
            $n_conf = count($confermati);
            $n_rich = count($richieste);

            $summary = $cat_name . ' · ' . $n_conf . '/' . $posti_totali;
            if ($n_rich > 0) $summary .= ' (+' . $n_rich . ' rich.)';

            $desc = [];
            $desc[] = 'Workshop: ' . $cat_name;
            $desc[] = 'Posti: ' . $n_conf . ' su ' . $posti_totali;
            if ($cat_url) $desc[] = 'Pagina: ' . $cat_url;
            $desc[] = '';
            $desc[] = '=== CONFERMATI (' . $n_conf . ') ===';
            if ($n_conf) {
                foreach ($confermati as $c) $desc[] = '- ' . $c;
            } else {
                $desc[] = '(nessuno)';
            }
            if ($n_rich > 0) {
                $desc[] = '';
                $desc[] = '=== RICHIESTE (' . $n_rich . ') ===';
                foreach ($richieste as $r) $desc[] = '- ' . $r;
            }
            $description = implode("\n", $desc);

            $dtstart = date('Ymd\THis', strtotime($date_inizio . ' ' . $ora_inizio));
            $dtend   = date('Ymd\THis', strtotime($date_fine   . ' ' . $ora_fine));
            $uid     = 'wv-evento-' . $ev->ID . '@' . $domain;
            $dtstamp = gmdate('Ymd\THis\Z');
            $modified = gmdate('Ymd\THis\Z', strtotime($ev->post_modified_gmt));
            $dtstamp = gmdate('Ymd\THis\Z');
            $modified = gmdate('Ymd\THis\Z', strtotime($ev->post_modified_gmt));

            $out .= $this->ics_fold('BEGIN:VEVENT');
            $out .= $this->ics_fold('UID:' . $uid);
            $out .= $this->ics_fold('DTSTAMP:' . $dtstamp);
            $out .= $this->ics_fold('LAST-MODIFIED:' . $modified);
            $out .= $this->ics_fold('DTSTART;TZID=Europe/Rome:' . $dtstart);
            $out .= $this->ics_fold('DTEND;TZID=Europe/Rome:' . $dtend);
            $out .= $this->ics_fold('SUMMARY:' . $this->ics_escape($summary));
            $out .= $this->ics_fold('DESCRIPTION:' . $this->ics_escape($description));
            if ($cat_url) $out .= $this->ics_fold('URL:' . $this->ics_escape($cat_url));
            $out .= $this->ics_fold('STATUS:CONFIRMED');
            $out .= $this->ics_fold('END:VEVENT');
        }

        $out .= $this->ics_fold('END:VCALENDAR');

        echo $out;
    }
}
