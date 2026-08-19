<?php

if (!defined('ABSPATH')) exit;

/**
 * T-15 reminder: the "most important" mail per the user (2026-08-16
 * messaging-system rework) — sent to Confermato participants 15 days
 * before their event. No AI, no custom mail engine: content is authored
 * per-category in the Categorie tab, delivery goes through the same direct
 * Zoho SMTP path as Conferma/replies (WS_Mail_Inbox::send_reply()) —
 * originally routed through a FluentCRM tag+automation, switched over once
 * the SMTP channel was verified working, so there's one delivery mechanism
 * instead of two and no FluentCRM automation left to configure.
 *
 * Query pattern (exact-date match + not-yet-sent flag) mirrors the legacy
 * `wv_apresto_pending()` (T-10 "A presto"), just with a distinct meta key
 * (`wv_t15_sent_at`) so this doesn't collide with that still-active
 * legacy cron reading/writing `mail_welcome_sent_at`.
 */
final class WS_T15_Reminder implements WS_Module {

    public const DAYS_OFFSET = 15;
    public const META_SENT = 'wv_t15_sent_at';
    public const META_THREAD = 'wv_thread';
    public const CRON_HOOK = 'ws_cron_t15_reminder';
    private const LEGACY_CRON_HOOK = 'fvw_cron_t15_reminder';

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('init', [$this, 'migrate_legacy_cron']);
        add_action('init', [$this, 'ensure_scheduled']);
        add_action(self::CRON_HOOK, [$this, 'process']);
    }

    /** One-time cleanup of the orphaned pre-rename event. */
    public function migrate_legacy_cron(): void {
        if (wp_next_scheduled(self::LEGACY_CRON_HOOK)) {
            wp_clear_scheduled_hook(self::LEGACY_CRON_HOOK);
        }
    }

    public function ensure_scheduled(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(strtotime('tomorrow 09:15'), 'daily', self::CRON_HOOK);
        }
    }

    /** @return int[] iscrizione IDs */
    public static function pending(): array {
        $now = current_datetime();
        $today = $now->format('Y-m-d');
        $max_date = $now->modify('+' . self::DAYS_OFFSET . ' days')->format('Y-m-d');

        $eventi = get_posts([
            'post_type'      => 'evento',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [[
                'key'     => 'data_evento',
                'value'   => [$today, $max_date],
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ]],
        ]);
        if (!$eventi) return [];

        return get_posts([
            'post_type'      => 'iscrizione',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'evento', 'value' => $eventi, 'compare' => 'IN'],
                ['key' => 'stato', 'value' => 'confermato', 'compare' => '='],
                ['relation' => 'OR',
                    ['key' => self::META_SENT, 'compare' => 'NOT EXISTS'],
                    ['key' => self::META_SENT, 'value' => '', 'compare' => '=']],
            ],
        ]);
    }

    /** @return int how many were sent */
    public function process(): int {
        $n = 0;
        foreach (self::pending() as $isc_id) {
            if ($this->send_one((int) $isc_id)) $n++;
        }
        return $n;
    }

    public function send_one(int $isc_id): bool {
        $pid = (int) WS_Data::get_field('partecipante', $isc_id);
        $eid = (int) WS_Data::get_field('evento', $isc_id);
        if (!$pid || !$eid) return false;

        $email = WS_Data::get_field('email', $pid);
        if (!$email) return false;

        $terms = get_the_terms($eid, 'categoria_evento');
        $cat_id = $terms ? $terms[0]->term_id : 0;

        $oggetto_tpl = $cat_id ? WS_Data::get_field('oggetto_t15', 'categoria_evento_' . $cat_id) : '';
        $mail_tpl = $cat_id ? WS_Data::get_field('mail_t15', 'categoria_evento_' . $cat_id) : '';
        if (!$oggetto_tpl) $oggetto_tpl = self::default_oggetto();
        if (!$mail_tpl) $mail_tpl = self::default_mail();

        $subject = WS_Data::render_template($oggetto_tpl, $isc_id);
        $body = WS_Data::render_template($mail_tpl, $isc_id);

        $result = WS_Mail_Inbox::send_reply($email, $subject, $body);
        if (!$result['ok']) return false;

        WS_Data::update_field(self::META_SENT, current_time('mysql'), $isc_id);
        WS_Data::append_thread($isc_id, 'out', $subject, $body);

        return true;
    }

    public static function default_oggetto(): string {
        return 'Mancano 15 giorni a {categoria_nome}';
    }

    public static function default_mail(): string {
        return "Ciao {nome},\n\n"
             . "tra 15 giorni ci vediamo per {categoria_nome}, il {periodo}!\n\n"
             . "Un paio di cose utili per organizzarti per tempo:\n"
             . "- Scarpe comode: cammineremo diverse ore\n"
             . "- Porta la tua fotocamera (va benissimo anche solo lo smartphone) e batteria/spazio di scorta\n"
             . "- Vestiti a strati, meteo permettendo\n\n"
             . "Nei prossimi giorni ti mando punto di ritrovo e orario esatto.\n\n"
             . "Per qualsiasi cosa nel frattempo, rispondimi pure a questa mail o scrivimi su WhatsApp.\n\n"
             . "A presto,\nFrancesco";
    }
}
