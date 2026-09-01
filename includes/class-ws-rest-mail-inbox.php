<?php

if (!defined('ABSPATH')) exit;

/** REST endpoints for the IMAP settings panel + connection test. */
final class WSMA_Rest_Mail_Inbox implements WSMA_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        $perm = fn() => current_user_can('manage_options');
        $ns = 'workshop-suite/v1';

        register_rest_route($ns, '/admin/mail-inbox/impostazioni', ['methods' => 'GET', 'callback' => [$this, 'get_impostazioni'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/mail-inbox/impostazioni', ['methods' => 'POST', 'callback' => [$this, 'save_impostazioni'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/mail-inbox/test', ['methods' => 'POST', 'callback' => [$this, 'test'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/mail-inbox/test-invio', ['methods' => 'POST', 'callback' => [$this, 'test_invio'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/mail-inbox/controlla-ora', ['methods' => 'POST', 'callback' => [$this, 'controlla_ora'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/mail-inbox/poll-status', ['methods' => 'GET', 'callback' => [$this, 'poll_status'], 'permission_callback' => $perm]);
    }

    public function controlla_ora(): WP_REST_Response {
        $poller = new WSMA_Mail_Poller();
        return new WP_REST_Response($poller->poll());
    }

    public function poll_status(): WP_REST_Response {
        return new WP_REST_Response([
            'last_poll' => WSMA_Mail_Poller::last_poll(),
            'next_poll' => wp_next_scheduled(WSMA_Mail_Poller::CRON_HOOK) ? wp_date('Y-m-d H:i:s', wp_next_scheduled(WSMA_Mail_Poller::CRON_HOOK)) : '',
        ]);
    }

    public function get_impostazioni(): WP_REST_Response {
        return new WP_REST_Response(WSMA_Mail_Inbox::get_public_settings());
    }

    public function save_impostazioni(WP_REST_Request $request): WP_REST_Response {
        WSMA_Mail_Inbox::save_settings([
            'host' => $request->get_param('host'),
            'port' => $request->get_param('port'),
            'encryption' => $request->get_param('encryption'),
            'username' => $request->get_param('username'),
            'folder' => $request->get_param('folder'),
            'password' => $request->get_param('password'),
            'reply_from_name' => $request->get_param('reply_from_name'),
            'reply_from_email' => $request->get_param('reply_from_email'),
            'smtp_host' => $request->get_param('smtp_host'),
            'smtp_port' => $request->get_param('smtp_port'),
            'smtp_encryption' => $request->get_param('smtp_encryption'),
        ]);
        return new WP_REST_Response(['msg' => 'Impostazioni salvate.'] + WSMA_Mail_Inbox::get_public_settings());
    }

    public function test(): WP_REST_Response {
        return new WP_REST_Response(WSMA_Mail_Inbox::test_connection());
    }

    public function test_invio(WP_REST_Request $request): WP_REST_Response {
        $to = sanitize_email((string) $request->get_param('to'));
        if (!$to) {
            return new WP_REST_Response(['ok' => false, 'msg' => 'Indirizzo di destinazione non valido.']);
        }
        $result = WSMA_Mail_Inbox::send_reply($to, '[Test] Configurazione mail Workshop', "Questa è una mail di prova per verificare l'invio SMTP dal pannello fv-workshop.");
        return new WP_REST_Response($result);
    }
}
