<?php

if (!defined('ABSPATH')) exit;

/**
 * Ported from legacy snippet 19 "CAMPI ACF: Template email su categoria".
 * Registers the category-level Mail #1 (Risposta) template fields and the
 * per-iscrizione tracking fields (`messaggio_originale`, `mail_*_sent_at`,
 * `replied_at`) that WSMA_Data, WSMA_Rest_Messaggi and WSMA_Rest_Riepilogo
 * already read/write without formally owning the field-group registration.
 *
 * The legacy `wv_send_template_email()` sent via `wp_mail()`, which
 * FluentSMTP silently forces to the wrong sender address (same bug fixed
 * this session for Conferma/T-15/Ringraziamento) — this port sends via
 * `WSMA_Mail_Inbox::send_reply()` instead and logs to `wv_thread`, matching
 * the pattern used everywhere else. Only Mail #1 (Risposta) is actually
 * called by live code (WSMA_Fluent_Forms_Intake on submission); the legacy
 * Mail #2/#3/#4 (Follow-up/Reminder/Welcome) machinery was never wired to
 * an active cron in this codebase and has no caller — kept only as inert
 * template fields for backward field-data compatibility, not re-wired.
 */
final class WSMA_Mail_Templates implements WSMA_Module {

    private const META_THREAD = 'wv_thread';

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        // Field data lives in native post/term meta (WSMA_Data::get_field/
        // update_field) regardless of whether ACF is active — nothing to
        // register here.
    }

    private static function default_template(string $key): string {
        $site_name = get_option('blogname') ?: 'Staff';
        $d = [
            'oggetto_risposta' => 'La tua richiesta per {categoria_nome}',
            'mail_risposta' =>
                "Ciao {nome},\n\n" .
                "Grazie per il tuo interesse a \"{categoria_nome}\" del {periodo}.\n\n" .
                "Ho ricevuto la tua richiesta e ti risponderò personalmente nelle prossime ore con tutte le informazioni e per chiarire eventuali dubbi.\n\n" .
                "A presto,\n" . $site_name,
        ];
        return $d[$key] ?? '';
    }

    public static function get_rendered(int $iscrizione_id, string $key): string {
        $eid = (int) WSMA_Data::get_field('evento', $iscrizione_id);
        $tpl = '';
        if ($eid) {
            $terms = get_the_terms($eid, 'wsma_categoria_evento');
            if ($terms) {
                $tpl = WSMA_Data::get_field($key, 'wsma_categoria_evento_' . $terms[0]->term_id);
            }
        }
        if (!$tpl) $tpl = self::default_template($key);
        return WSMA_Data::render_template($tpl, $iscrizione_id);
    }

    /** Sends Mail #1 (Risposta) and updates tracking + thread log. Only 'mail_risposta' is called by live code. */
    public static function send_template_email(int $iscrizione_id, string $template_key): bool {
        $pid = (int) WSMA_Data::get_field('partecipante', $iscrizione_id);
        if (!$pid) return false;
        $email = WSMA_Data::get_field('email', $pid);
        if (!$email) return false;

        $subject_key = 'oggetto_' . str_replace('mail_', '', $template_key);
        $subject = self::get_rendered($iscrizione_id, $subject_key);
        $body = self::get_rendered($iscrizione_id, $template_key);
        if (!$subject || !$body) return false;

        $result = WSMA_Mail_Inbox::send_reply($email, $subject, $body);
        if (!$result['ok']) return false;

        $tracking_map = [
            'mail_risposta' => 'mail_risposta_sent_at',
            'mail_followup' => 'mail_followup_sent_at',
            'mail_reminder' => 'mail_reminder_sent_at',
            'mail_welcome'  => 'mail_welcome_sent_at',
        ];
        if (isset($tracking_map[$template_key])) {
            WSMA_Data::update_field($tracking_map[$template_key], current_time('Y-m-d H:i:s'), $iscrizione_id);
        }

        WSMA_Data::append_thread($iscrizione_id, 'out', $subject, $body);

        return true;
    }
}
