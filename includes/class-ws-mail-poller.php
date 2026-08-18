<?php

if (!defined('ABSPATH')) exit;

/**
 * Polls the configured mailbox every 15 minutes, matches inbound messages
 * to a partecipante by sender email, and appends them to that contact's
 * most recent iscrizione thread (same `wv_thread` meta the reply-compose
 * feature writes to, direction:'in'). Closes the loop the user asked for:
 * primo messaggio → rispondi da qui → la risposta vera arriva qui.
 *
 * Uses the message's real Date header, not the time it was polled —
 * explicitly confirmed as important by the user.
 */
final class WS_Mail_Poller implements WS_Module {

    public const CRON_HOOK = 'fvw_cron_mail_poll';
    private const META_THREAD = 'wv_thread';
    private const META_LAST_POLL = 'fvw_mail_poll_last';
    public const META_LAST_ERROR = 'fvw_mail_poll_last_error';

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_filter('cron_schedules', [$this, 'add_schedule']);
        add_action('init', [$this, 'ensure_scheduled']);
        add_action(self::CRON_HOOK, [$this, 'poll']);
        add_action('admin_notices', [$this, 'display_admin_notice']);
    }

    public function display_admin_notice(): void {
        if (!current_user_can('manage_options')) return;
        $error = self::last_error();
        if (!$error) return;

        // Display notice only on FV Workshop admin pages
        $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
        if (strpos($page, 'workshop-suite') !== 0) return;

        ?>
        <div class="notice notice-error is-dismissible" style="padding:10px 14px;border-radius:6px;background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);">
            <p style="margin:0;font-weight:600;">
                ⚠️ <?php printf(esc_html__('Attenzione: Il controllo automatico della casella mail (Cron IMAP) è fallito il %s con errore:', 'workshop-suite'), esc_html($error['date'] ?? '')); ?>
                <code style="background:rgba(0,0,0,0.3);padding:2px 6px;border-radius:4px;color:#fca5a5;"><?php echo esc_html($error['msg'] ?? ''); ?></code>
            </p>
        </div>
        <?php
    }

    public function add_schedule(array $schedules): array {
        $schedules['fvw_15min'] = ['interval' => 15 * 60, 'display' => 'Ogni 15 minuti (FV Workshop)'];
        return $schedules;
    }

    public function ensure_scheduled(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'fvw_15min', self::CRON_HOOK);
        }
    }

    /** @return array{processed: int, matched: int, error?: string} */
    public function poll(): array {
        if (!WS_Mail_Inbox::is_configured()) {
            return ['processed' => 0, 'matched' => 0, 'error' => 'Casella mail non configurata'];
        }

        $processed = 0;
        $matched = 0;

        try {
            $client = WS_Mail_Inbox::build_client();
            $client->connect();

            $folder_name = WS_Mail_Inbox::configured_folder();
            $folder = $client->getFolder($folder_name);
            if (!$folder) {
                throw new \RuntimeException("Cartella IMAP '{$folder_name}' non trovata.");
            }

            $messages = $folder->query()->unseen()->get();

            foreach ($messages as $message) {
                $processed++;
                $from = $message->getFrom();
                $from_email = $from && isset($from[0]) ? strtolower($from[0]->mail) : '';
                $subject = (string) $message->getSubject();
                $body = (string) ($message->getTextBody() ?: strip_tags((string) $message->getHTMLBody()));
                $date_obj = $message->getDate();
                $date_mysql = $date_obj ? $date_obj->get()->format('Y-m-d H:i:s') : current_time('mysql');

                if ($from_email) {
                    $pid = WS_Data::find_partecipante_by_email($from_email);
                    if ($pid) {
                        $isc_id = $this->latest_iscrizione_for($pid);
                        if ($isc_id) {
                            WS_Data::append_thread($isc_id, 'in', $subject, $body, $date_mysql, true);
                            $matched++;
                        }
                    }
                }

                $message->setFlag('Seen');
            }

            $client->disconnect();
            update_option(self::META_LAST_POLL, current_time('mysql'), false);
            delete_option(self::META_LAST_ERROR);

            return ['processed' => $processed, 'matched' => $matched];
        } catch (\Throwable $e) {
            $err_msg = $e->getMessage();
            error_log('[FVW Mail Poller Error] ' . $err_msg);
            update_option(self::META_LAST_ERROR, [
                'date' => current_time('mysql'),
                'msg'  => $err_msg,
            ], false);

            return ['processed' => $processed, 'matched' => $matched, 'error' => $err_msg];
        }
    }

    private function latest_iscrizione_for(int $pid): int {
        $q = new WP_Query([
            'post_type' => 'iscrizione', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids',
            'meta_query' => [['key' => 'partecipante', 'value' => $pid, 'compare' => '=']],
        ]);
        return $q->posts ? (int) $q->posts[0] : 0;
    }

    public static function last_poll(): string {
        return (string) get_option(self::META_LAST_POLL, '');
    }

    /** @return array{date: string, msg: string}|null */
    public static function last_error(): ?array {
        $err = get_option(self::META_LAST_ERROR, null);
        return is_array($err) ? $err : null;
    }
}
