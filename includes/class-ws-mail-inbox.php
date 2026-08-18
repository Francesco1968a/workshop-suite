<?php

if (!defined('ABSPATH')) exit;

use Webklex\PHPIMAP\ClientManager;

/**
 * IMAP connection settings + connectivity test for the reply-tracking
 * mailbox (e.g. workshop@francescoverolino.com), part of the messaging-
 * system rework. No native `ext-imap` on this server — uses the pure-PHP
 * webklex/php-imap library instead of touching shared system packages
 * (this server hosts multiple sites).
 *
 * The password is write-only from the REST API's perspective: settings
 * GET never returns it, only whether one is stored (`has_password`).
 */
final class WS_Mail_Inbox {

    private const OPTION = 'fvw_imap_settings';

    public static function default_settings(): array {
        return [
            'host' => 'imap.zoho.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => '',
            'folder' => 'INBOX',
            'reply_from_name' => 'Francesco Verolino',
            'reply_from_email' => '',
            'smtp_host' => 'smtp.zoho.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
        ];
    }

    /** Non-secret settings only — never includes the password. */
    public static function get_public_settings(): array {
        $stored = get_option(self::OPTION, []);
        $settings = array_merge(self::default_settings(), array_intersect_key($stored, self::default_settings()));
        $settings['has_password'] = !empty($stored['password']);
        return $settings;
    }

    /** Encrypt plain password with OpenSSL using site AUTH key */
    public static function encrypt_password(string $plain): string {
        if (empty($plain)) return '';
        $key = substr(hash('sha256', wp_salt('auth')), 0, 32);
        $iv  = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);
        $encrypted = openssl_encrypt($plain, 'AES-256-CBC', $key, 0, $iv);
        return 'enc:' . $encrypted;
    }

    /** Decrypt password stored in options. Backward-compatible with unencrypted legacy passwords. */
    public static function get_decrypted_password(): string {
        $stored = get_option(self::OPTION, []);
        $pass   = $stored['password'] ?? '';
        if (empty($pass)) return '';
        if (strpos($pass, 'enc:') !== 0) {
            return $pass; // Legacy plain text
        }
        $raw = substr($pass, 4);
        $key = substr(hash('sha256', wp_salt('auth')), 0, 32);
        $iv  = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);
        $decrypted = openssl_decrypt($raw, 'AES-256-CBC', $key, 0, $iv);
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * @param array $data host/port/encryption/username/folder + optional
     *   'password' (only overwritten if non-empty, so re-saving the form
     *   without retyping the password keeps the existing one).
     */
    public static function save_settings(array $data): void {
        $stored = get_option(self::OPTION, []);
        $next = [
            'host' => sanitize_text_field($data['host'] ?? self::default_settings()['host']),
            'port' => (int) ($data['port'] ?? self::default_settings()['port']),
            'encryption' => in_array($data['encryption'] ?? '', ['ssl', 'tls', ''], true) ? $data['encryption'] : 'ssl',
            'username' => sanitize_text_field($data['username'] ?? ''),
            'folder' => sanitize_text_field($data['folder'] ?? 'INBOX'),
            'reply_from_name' => sanitize_text_field($data['reply_from_name'] ?? self::default_settings()['reply_from_name']),
            'reply_from_email' => sanitize_email($data['reply_from_email'] ?? ''),
            'smtp_host' => sanitize_text_field($data['smtp_host'] ?? self::default_settings()['smtp_host']),
            'smtp_port' => (int) ($data['smtp_port'] ?? self::default_settings()['smtp_port']),
            'smtp_encryption' => in_array($data['smtp_encryption'] ?? '', ['ssl', 'tls', ''], true) ? $data['smtp_encryption'] : 'tls',
            'password' => $stored['password'] ?? '',
        ];
        if (!empty($data['password'])) {
            $next['password'] = self::encrypt_password((string) $data['password']);
        }
        update_option(self::OPTION, $next, false);
    }

    public static function is_configured(): bool {
        $stored = get_option(self::OPTION, []);
        return !empty($stored['host']) && !empty($stored['username']) && !empty(self::get_decrypted_password());
    }

    public static function configured_folder(): string {
        $stored = get_option(self::OPTION, []);
        return $stored['folder'] ?? 'INBOX';
    }

    public static function build_client(): \Webklex\PHPIMAP\Client {
        $stored = get_option(self::OPTION, []);
        $cm = new ClientManager();
        return $cm->make([
            'host'            => $stored['host'] ?? '',
            'port'            => (int) ($stored['port'] ?? 993),
            'protocol'        => 'imap',
            'encryption'      => $stored['encryption'] ?? 'ssl',
            'validate_cert'   => true,
            'username'        => $stored['username'] ?? '',
            'password'        => self::get_decrypted_password(),
            'authentication'  => null,
            'timeout'         => 10,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Sends via the Zoho account directly over SMTP, bypassing wp_mail()/
     * FluentSMTP entirely. Necessary because FluentSMTP routes this site's
     * mail through Amazon SES with `force_from_email` on — any From header
     * passed to wp_mail() gets silently overridden to info@francescoverolino.it,
     * and SES would likely reject an unverified sender anyway. Zoho already
     * recognizes workshop@francescoverolino.com as a valid "send as" alias
     * for the info@ account, so authenticating as info@ (same credentials
     * already stored for IMAP) and setting From to the alias works cleanly.
     *
     * @param array $attachments Optional list of ['content' => string, 'filename' => string, 'type' => string]
     * @return array{ok: bool, msg: string}
     */
    public static function send_reply(string $to, string $subject, string $body, array $attachments = []): array {
        $stored = get_option(self::OPTION, []);
        $pass   = self::get_decrypted_password();
        if (empty($stored['username']) || empty($pass)) {
            return ['ok' => false, 'msg' => 'Credenziali casella mail non configurate.'];
        }
        $from_email = $stored['reply_from_email'] ?? '';
        if (!$from_email) {
            return ['ok' => false, 'msg' => 'Indirizzo mittente risposte non configurato.'];
        }

        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $stored['smtp_host'] ?? 'smtp.zoho.com';
            $mail->Port = (int) ($stored['smtp_port'] ?? 587);
            $mail->SMTPSecure = ($stored['smtp_encryption'] ?? 'tls') ?: false;
            $mail->SMTPAuth = true;
            $mail->Username = $stored['username'];
            $mail->Password = $pass;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($from_email, $stored['reply_from_name'] ?? '');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);

            foreach ($attachments as $att) {
                $mail->addStringAttachment($att['content'], $att['filename'], 'base64', $att['type'] ?? 'application/octet-stream');
            }

            $mail->send();
            return ['ok' => true, 'msg' => 'Inviata.'];
        } catch (\Throwable $e) {
            $detail = $mail->ErrorInfo ?: $e->getMessage();
            return ['ok' => false, 'msg' => 'Invio fallito: ' . $detail];
        }
    }

    /** @return array{ok: bool, msg: string, folders?: string[], unseen?: int} */
    public static function test_connection(): array {
        $stored = get_option(self::OPTION, []);
        if (empty($stored['host']) || empty($stored['username']) || empty($stored['password'])) {
            return ['ok' => false, 'msg' => 'Host, utente e password sono obbligatori.'];
        }

        try {
            $client = self::build_client();
            $client->connect();

            $folders = [];
            foreach ($client->getFolders() as $folder) {
                $folders[] = $folder->path;
            }

            $target = sanitize_text_field($stored['folder'] ?? 'INBOX');
            $inbox = $client->getFolder($target);
            $unseen = $inbox ? $inbox->query()->unseen()->count() : 0;

            $client->disconnect();

            return ['ok' => true, 'msg' => 'Connessione riuscita.', 'folders' => $folders, 'unseen' => $unseen];
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => 'Connessione fallita: ' . $e->getMessage()];
        }
    }
}
