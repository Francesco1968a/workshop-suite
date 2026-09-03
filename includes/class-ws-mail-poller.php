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
final class WSMA_Mail_Poller implements WSMA_Module {

    public const CRON_HOOK = 'wsma_cron_mail_poll';
    private const LEGACY_CRON_HOOK = 'ws_cron_mail_poll';
    private const LEGACY_CRON_HOOK_V0 = 'fvw_cron_mail_poll';
    private const META_THREAD = 'wv_thread';
    private const META_LAST_POLL = 'wsma_mail_poll_last';
    private const LEGACY_META_LAST_POLL = 'ws_mail_poll_last';
    private const LEGACY_META_LAST_POLL_V0 = 'fvw_mail_poll_last';
    public const META_LAST_ERROR = 'wsma_mail_poll_last_error';

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_filter('cron_schedules', [$this, 'add_schedule']);
        add_action('init', [$this, 'migrate_legacy_cron']);
        add_action('init', [$this, 'ensure_scheduled']);
        add_action(self::CRON_HOOK, [$this, 'poll']);
        add_action('admin_notices', [$this, 'display_admin_notice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_notice_style']);
    }

    /** One-time cleanup of orphaned pre-rename events, so they don't keep firing alongside the new one. */
    public function migrate_legacy_cron(): void {
        if (wp_next_scheduled(self::LEGACY_CRON_HOOK)) {
            wp_clear_scheduled_hook(self::LEGACY_CRON_HOOK);
        }
        if (wp_next_scheduled(self::LEGACY_CRON_HOOK_V0)) {
            wp_clear_scheduled_hook(self::LEGACY_CRON_HOOK_V0);
        }
    }

    /**
     * Registered on admin_enqueue_scripts (fires reliably before
     * WordPress's style-printing point) rather than inside
     * display_admin_notice() itself — that callback runs on admin_notices,
     * which is often too late to enqueue a style and have it print. Same
     * "only on workshop-suite pages, only when there's an error" guard as
     * the notice itself, so nothing is enqueued needlessly.
     */
    public function enqueue_notice_style(): void {
        if (!current_user_can('manage_options')) return;
        if (!self::last_error()) return;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page-slug check to decide whether to enqueue a style/notice, no form data is processed here.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'workshop-suite') !== 0) return;

        WSMA_Data::enqueue_inline_style(
            '.ws-mail-poll-error { padding: 10px 14px; border-radius: 6px; background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }'
            . '.ws-mail-poll-error p { margin: 0; font-weight: 600; }'
            . '.ws-mail-poll-error code { background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px; color: #fca5a5; }'
        );
    }

    public function display_admin_notice(): void {
        if (!current_user_can('manage_options')) return;
        $error = self::last_error();
        if (!$error) return;

        // Display notice only on WSMaker admin pages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page-slug check to decide whether to enqueue a style/notice, no form data is processed here.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'workshop-suite') !== 0) return;

        ?>
        <div class="notice notice-error is-dismissible ws-mail-poll-error">
            <p>
                ⚠️ <?php /* translators: %s: date and time of the last failed poll */ printf(esc_html__('Attenzione: Il controllo automatico della casella mail (Cron IMAP) è fallito il %s con errore:', 'wsmaker'), esc_html($error['date'] ?? '')); ?>
                <code><?php echo esc_html($error['msg'] ?? ''); ?></code>
            </p>
        </div>
        <?php
    }

    public function add_schedule(array $schedules): array {
        $schedules['wsma_15min'] = ['interval' => 15 * 60, 'display' => 'Ogni 15 minuti (WSMaker)'];
        return $schedules;
    }

    public function ensure_scheduled(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'wsma_15min', self::CRON_HOOK);
        }
    }

    /** @return array{processed: int, matched: int, error?: string} */
    public function poll(): array {
        if (!WSMA_Mail_Inbox::is_configured()) {
            return ['processed' => 0, 'matched' => 0, 'error' => 'Casella mail non configurata'];
        }

        $processed = 0;
        $matched = 0;

        try {
            $client = WSMA_Mail_Inbox::build_client();
            $client->connect();

            $folder_name = WSMA_Mail_Inbox::configured_folder();
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
                $body = (string) ($message->getTextBody() ?: wp_strip_all_tags((string) $message->getHTMLBody()));
                $date_obj = $message->getDate();
                $date_mysql = $date_obj ? $date_obj->get()->format('Y-m-d H:i:s') : current_time('mysql');

                if ($from_email) {
                    $pid = WSMA_Data::find_partecipante_by_email($from_email);
                    if ($pid) {
                        $isc_id = $this->latest_iscrizione_for($pid);
                        if ($isc_id) {
                            WSMA_Data::append_thread($isc_id, 'in', $subject, $body, $date_mysql, true);
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated behind WP_DEBUG above; not run in production.
                error_log('[WS Mail Poller Error] ' . $err_msg);
            }
            update_option(self::META_LAST_ERROR, [
                'date' => current_time('mysql'),
                'msg'  => $err_msg,
            ], false);

            return ['processed' => $processed, 'matched' => $matched, 'error' => $err_msg];
        }
    }

    private function latest_iscrizione_for(int $pid): int {
        $q = new WP_Query([
            'post_type' => 'wsma_iscrizione', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => [['key' => 'partecipante', 'value' => $pid, 'compare' => '=']],
        ]);
        return $q->posts ? (int) $q->posts[0] : 0;
    }

    public static function last_poll(): string {
        $val = get_option(self::META_LAST_POLL, '');
        if ($val === '') {
            $val = get_option(self::LEGACY_META_LAST_POLL, '');
        }
        if ($val === '') {
            $val = get_option(self::LEGACY_META_LAST_POLL_V0, '');
        }
        return (string) $val;
    }

    /** @return array{date: string, msg: string}|null */
    public static function last_error(): ?array {
        $err = get_option(self::META_LAST_ERROR, null);
        return is_array($err) ? $err : null;
    }
}
