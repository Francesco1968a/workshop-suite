<?php

if (!defined('ABSPATH')) exit;

/**
 * Public REST endpoint for native form submissions.
 * Handles attendee creation, workshop registration and instant response email.
 */
final class WS_Rest_Intake implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('workshop-suite/v1', '/iscrizione/invia', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_submission'],
            'permission_callback' => '__return_true', // Public form submission
            'args' => [
                'evento_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'nome' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'cognome' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'email' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'telefono' => [
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'citta' => [
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'messaggio' => [
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'numero_persone' => [
                    'type'              => 'integer',
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'website_url' => [ // Honeypot field for anti-spam
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function handle_submission(WP_REST_Request $request): WP_REST_Response {
        $settings = WS_Settings::get_all();

        // 1. Honeypot check
        if (!empty($settings['intake_honeypot_enabled'])) {
            if (!empty($request->get_param('website_url'))) {
                // Silently discard bot submission
                return new WP_REST_Response(['success' => true, 'message' => __('Richiesta ricevuta.', 'workshop-suite')], 200);
            }
        }

        // 2. IP Rate Limit check
        if (!empty($settings['intake_rate_limit_enabled'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
            $ip_clean = explode(',', $ip)[0];
            $transient_key = 'ws_rl_' . md5($ip_clean);
            $count = (int) get_transient($transient_key);
            $max_requests = (int) ($settings['intake_rate_limit_requests'] ?? 5);
            $window = (int) ($settings['intake_rate_limit_window'] ?? 60);

            if ($count >= $max_requests) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Hai inviato troppe richieste in poco tempo. Attendi qualche minuto prima di riprovare.', 'workshop-suite'),
                ], 429);
            }

            set_transient($transient_key, $count + 1, $window);
        }

        $evento_id = $request->get_param('evento_id');
        if (!$evento_id || get_post_type($evento_id) !== 'evento') {
            return new WP_REST_Response(['success' => false, 'message' => __('Evento selezionato non valido.', 'workshop-suite')], 400);
        }

        if (WS_Data::evento_concluso($evento_id)) {
            return new WP_REST_Response(['success' => false, 'message' => __('Questo evento si è già concluso.', 'workshop-suite')], 400);
        }

        $first_name  = trim($request->get_param('nome'));
        $last_name   = trim($request->get_param('cognome'));
        $email       = trim($request->get_param('email'));
        $phone       = trim($request->get_param('telefono'));
        $citta       = trim($request->get_param('citta'));
        $message     = trim($request->get_param('messaggio'));
        $num_persone = max(1, (int) $request->get_param('numero_persone'));

        if (!$email || !is_email($email) || !$first_name) {
            return new WP_REST_Response(['success' => false, 'message' => __('Nome ed email sono obbligatori.', 'workshop-suite')], 400);
        }

        $titolo = trim($first_name . ' ' . $last_name);

        $pid = WS_Data::find_partecipante_by_email($email);
        if (!$pid) {
            $pid = wp_insert_post([
                'post_type'   => 'partecipante',
                'post_status' => 'publish',
                'post_title'  => $titolo
            ]);
            if (!$pid || is_wp_error($pid)) {
                return new WP_REST_Response(['success' => false, 'message' => __('Errore durante il salvataggio dei dati.', 'workshop-suite')], 500);
            }
        }

        WS_Data::update_field('nome', $first_name, $pid);
        WS_Data::update_field('cognome', $last_name, $pid);
        WS_Data::update_field('telefono', $phone, $pid);
        WS_Data::update_field('email', $email, $pid);
        if ($citta) WS_Data::update_field('citta', $citta, $pid);
        wp_update_post(['ID' => $pid, 'post_title' => $titolo]);

        $existing_isc = WS_Data::find_iscrizione($pid, $evento_id);
        if ($existing_isc) {
            $old_msg = (string) WS_Data::get_field('messaggio_originale', $existing_isc);
            $stamp   = current_time('d/m/Y H:i');
            $new_msg = $old_msg ? ($old_msg . "\n\n— [{$stamp}] Nuovo messaggio dal form —\n" . $message) : $message;
            WS_Data::update_field('messaggio_originale', $new_msg, $existing_isc);
            WS_Data::update_field('note', $new_msg, $existing_isc);
            
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Abbiamo già una tua richiesta per questo evento. Abbiamo aggiornato il tuo messaggio!', 'workshop-suite')
            ], 200);
        }

        $isc = wp_insert_post([
            'post_type'   => 'iscrizione',
            'post_status' => 'publish',
            'post_title'  => $titolo . ' → ' . WS_Data::evento_label($evento_id),
        ]);

        if (!$isc || is_wp_error($isc)) {
            return new WP_REST_Response(['success' => false, 'message' => __('Errore durante la creazione dell\'iscrizione.', 'workshop-suite')], 500);
        }

        WS_Data::update_field('partecipante', $pid, $isc);
        WS_Data::update_field('evento', $evento_id, $isc);
        WS_Data::update_field('stato', 'richiesta', $isc);
        WS_Data::update_field('anticipo', 0, $isc);
        WS_Data::update_field('saldo', 0, $isc);
        WS_Data::update_field('messaggio_originale', $message, $isc);
        WS_Data::update_field('note', $message, $isc);
        update_post_meta($isc, 'num_persone', $num_persone);

        // Manda mail di risposta istantanea
        WS_Mail_Templates::send_template_email($isc, 'mail_risposta');

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Richiesta di iscrizione inviata con successo! Ti abbiamo inviato un\'email di conferma.', 'workshop-suite')
        ], 200);
    }
}