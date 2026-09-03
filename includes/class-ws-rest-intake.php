<?php

if (!defined('ABSPATH')) exit;

/**
 * Public REST endpoint for native form submissions.
 * Handles attendee creation, workshop registration and instant response email.
 */
final class WSMA_Rest_Intake implements WSMA_Module {

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
                    'required'          => true,
                    'type'              => 'string',
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
        $settings = WSMA_Settings::get_all();

        // 1. Honeypot check
        if (!empty($settings['intake_honeypot_enabled'])) {
            if (!empty($request->get_param('website_url'))) {
                // Silently discard bot submission
                return new WP_REST_Response(['success' => true, 'message' => __('Richiesta ricevuta.', 'wsmaker')], 200);
            }
        }

        // REMOTE_ADDR by default — HTTP_X_FORWARDED_FOR is client-supplied
        // and trivially spoofable (each fake value mints a fresh rate-limit
        // bucket, defeating the limiter and growing the transients table).
        // Only trust it if a site owner explicitly opts in via this filter
        // because they know their own reverse-proxy setup is stripping/
        // overwriting the header before it reaches PHP.
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';
        if (apply_filters('wsma_intake_trust_x_forwarded_for', false) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        }
        $ip_clean = trim(explode(',', $ip)[0]);

        // 2. IP Rate Limit check
        if (!empty($settings['intake_rate_limit_enabled'])) {
            $transient_key = 'wsma_rl_' . md5($ip_clean);
            $count = (int) get_transient($transient_key);
            $max_requests = (int) ($settings['intake_rate_limit_requests'] ?? 5);
            $window = (int) ($settings['intake_rate_limit_window'] ?? 60);

            if ($count >= $max_requests) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Hai inviato troppe richieste in poco tempo. Attendi qualche minuto prima di riprovare.', 'wsmaker'),
                ], 429);
            }

            set_transient($transient_key, $count + 1, $window);
        }

        $evento_id = $request->get_param('evento_id');
        if (!$evento_id || get_post_type($evento_id) !== 'wsma_evento') {
            return new WP_REST_Response(['success' => false, 'message' => __('Evento selezionato non valido.', 'wsmaker')], 400);
        }

        if (WSMA_Data::evento_concluso($evento_id)) {
            return new WP_REST_Response(['success' => false, 'message' => __('Questo evento si è già concluso.', 'wsmaker')], 400);
        }

        $first_name  = trim($request->get_param('nome'));
        $last_name   = trim($request->get_param('cognome'));
        $email       = trim($request->get_param('email'));
        $phone       = trim($request->get_param('telefono'));
        $citta       = trim($request->get_param('citta'));
        $message     = trim($request->get_param('messaggio'));
        $num_persone = max(1, (int) $request->get_param('numero_persone'));

        if (!$email || !is_email($email) || !$first_name) {
            return new WP_REST_Response(['success' => false, 'message' => __('Nome ed email sono obbligatori.', 'wsmaker')], 400);
        }

        if (!$message) {
            return new WP_REST_Response(['success' => false, 'message' => __('Il messaggio è obbligatorio.', 'wsmaker')], 400);
        }

        $titolo = trim($first_name . ' ' . $last_name);

        $pid = WSMA_Data::find_partecipante_by_email($email);
        if (!$pid) {
            $pid = wp_insert_post([
                'post_type'   => 'wsma_partecipante',
                'post_status' => 'publish',
                'post_title'  => $titolo
            ]);
            if (!$pid || is_wp_error($pid)) {
                return new WP_REST_Response(['success' => false, 'message' => __('Errore durante il salvataggio dei dati.', 'wsmaker')], 500);
            }
        }

        WSMA_Data::update_field('nome', $first_name, $pid);
        WSMA_Data::update_field('cognome', $last_name, $pid);
        WSMA_Data::update_field('telefono', $phone, $pid);
        WSMA_Data::update_field('email', $email, $pid);
        if ($citta) WSMA_Data::update_field('citta', $citta, $pid);
        wp_update_post(['ID' => $pid, 'post_title' => $titolo]);

        $existing_isc = WSMA_Data::find_iscrizione($pid, $evento_id);
        if ($existing_isc) {
            $old_msg = (string) WSMA_Data::get_field('messaggio_originale', $existing_isc);
            $stamp   = current_time('d/m/Y H:i');
            $new_msg = $old_msg ? ($old_msg . "\n\n— [{$stamp}] Nuovo messaggio dal form —\n" . $message) : $message;
            WSMA_Data::update_field('messaggio_originale', $new_msg, $existing_isc);
            WSMA_Data::update_field('note', $new_msg, $existing_isc);

            self::notify_organizer($existing_isc, $titolo, $evento_id, $email, $phone, $citta, $num_persone, $message, true, $ip_clean);

            /** @param int $isc_id, int $partecipante_id — extension point (see WS_FluentCRM_Bridge). */
            do_action('wsma_iscrizione_created', $existing_isc, $pid);

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Abbiamo già una tua richiesta per questo evento. Abbiamo aggiornato il tuo messaggio!', 'wsmaker')
            ], 200);
        }

        $isc = wp_insert_post([
            'post_type'   => 'wsma_iscrizione',
            'post_status' => 'publish',
            'post_title'  => $titolo . ' → ' . WSMA_Data::evento_label($evento_id),
        ]);

        if (!$isc || is_wp_error($isc)) {
            return new WP_REST_Response(['success' => false, 'message' => __('Errore durante la creazione dell\'iscrizione.', 'wsmaker')], 500);
        }

        WSMA_Data::update_field('partecipante', $pid, $isc);
        WSMA_Data::update_field('evento', $evento_id, $isc);
        WSMA_Data::update_field('stato', 'richiesta', $isc);
        WSMA_Data::update_field('anticipo', 0, $isc);
        WSMA_Data::update_field('saldo', 0, $isc);
        WSMA_Data::update_field('messaggio_originale', $message, $isc);
        WSMA_Data::update_field('note', $message, $isc);
        update_post_meta($isc, 'num_persone', $num_persone);

        // window.location.href dal client (più affidabile dell'header
        // Referer, che alcuni browser/estensioni per privacy omettono),
        // con l'header come ripiego.
        $pagina_provenienza = esc_url_raw((string) $request->get_param('pagina_provenienza'));
        if (!$pagina_provenienza) $pagina_provenienza = esc_url_raw((string) $request->get_header('referer'));
        if ($pagina_provenienza) update_post_meta($isc, 'pagina_provenienza', $pagina_provenienza);

        // Manda mail di risposta istantanea
        WSMA_Mail_Templates::send_template_email($isc, 'mail_risposta');

        self::notify_organizer($isc, $titolo, $evento_id, $email, $phone, $citta, $num_persone, $message, false, $ip_clean);

        do_action('wsma_iscrizione_created', $isc, $pid);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Richiesta di iscrizione inviata con successo! Ti abbiamo inviato un\'email di conferma.', 'wsmaker')
        ], 200);
    }

    /**
     * Avvisa l'organizzatore di una nuova richiesta (o di un messaggio
     * aggiuntivo su una richiesta esistente) — il form nativo, a
     * differenza dell'integrazione Fluent Forms che sostituisce, non ha
     * un pannello di notifiche proprio: senza questo, l'unico modo per
     * accorgersi di una richiesta sarebbe controllare a mano il pannello
     * Riepilogo.
     *
     * Ordine di ricerca del destinatario:
     *  1. "Email Mittente" (reply_from_email, Configurazione Posta) — se
     *     l'organizzatore ha già configurato un mailbox reale lì, è
     *     l'indirizzo più naturale, letto direttamente anche se il resto
     *     dell'account IMAP non è connesso/funzionante (basta il campo
     *     testo, non serve una connessione riuscita).
     *  2. "Email Organizzazione" (sender_email, Generale) — ripiego per chi
     *     non vuole configurare un intero mailbox solo per un avviso.
     *  3. Email admin del sito — ultimo ripiego, garantisce che una
     *     notifica arrivi sempre da qualche parte.
     * Usa wp_mail() nativo, non il canale SMTP diretto via Zoho (quello
     * resta specifico del tracciamento risposte in Configurazione Posta).
     */
    private static function notify_organizer(int $isc_id, string $nome, int $evento_id, string $email, string $phone, string $citta, int $num_persone, string $message, bool $is_update, string $ip = ''): void {
        $to = class_exists('WSMA_Mail_Inbox') ? (string) (WSMA_Mail_Inbox::get_public_settings()['reply_from_email'] ?? '') : '';
        if (!$to && class_exists('WSMA_Settings')) $to = (string) WSMA_Settings::get('sender_email', '');
        if (!$to) $to = (string) get_option('admin_email');
        if (!$to) return;

        $evento_label = WSMA_Data::evento_label($evento_id);
        $subject = $is_update
            /* translators: %1$s: participant name, %2$s: event label */
            ? sprintf(__('Nuovo messaggio da %1$s — %2$s', 'wsmaker'), $nome, $evento_label)
            /* translators: %1$s: participant name, %2$s: event label */
            : sprintf(__('Nuova richiesta di iscrizione: %1$s — %2$s', 'wsmaker'), $nome, $evento_label);

        $geo_enabled = class_exists('WSMA_Settings') && !empty(WSMA_Settings::get('intake_geolocation_enabled', 0));
        $geo = ($ip && $geo_enabled) ? self::geolocate_ip($ip) : '';

        $body = sprintf(
            "%s\n\nEvento: %s\nEmail: %s\nTelefono: %s\nCittà: %s\nNumero persone: %d\nIP: %s\n\nMessaggio:\n%s\n\nGestiscila dal pannello Riepilogo.",
            $is_update ? __('Messaggio aggiuntivo su una richiesta già esistente:', 'wsmaker') : __('Nuova richiesta ricevuta dal form:', 'wsmaker'),
            $evento_label,
            $email,
            $phone ?: '—',
            $citta ?: '—',
            $num_persone,
            $ip ? ($ip . ($geo ? " ({$geo})" : '')) : '—',
            $message ?: '—'
        );

        wp_mail($to, $subject, $body);
    }

    /**
     * Best-effort reverse IP geolocation for the admin notification email
     * only (never blocks/fails the actual registration if unavailable).
     * Uses ip-api.com's free, keyless endpoint by default — one anonymous
     * outbound request per unique visitor IP, cached for 7 days so the same
     * visitor submitting again (e.g. a follow-up message) doesn't trigger a
     * second lookup. A short 2s timeout and full try/catch-equivalent error
     * handling (wp_remote_get returns a WP_Error instead of throwing) mean
     * a slow/unreachable geolocation service can never delay or break form
     * submission itself — worst case, the email just omits the city/country.
     *
     * ip-api.com's free tier is documented for non-commercial use; site
     * owners running a commercial business (the normal case for WSMaker)
     * who want a provider with clear commercial terms — or their own
     * account for higher volume — can point this at any endpoint that
     * accepts a trailing IP and returns JSON with `city`/`country` (and
     * ideally `status`), via Impostazioni → Configurazione Posta → URL
     * servizio geolocalizzazione, or the `wsma_geolocation_api_url` filter
     * for full control (headers, auth, a completely different response
     * shape parsed via `wsma_geolocation_parsed` instead).
     */
    private static function geolocate_ip(string $ip): string {
        if (!filter_var($ip, FILTER_VALIDATE_IP) || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return '';
        }

        $cache_key = 'wsma_geo_' . md5($ip);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $custom_url = class_exists('WSMA_Settings') ? trim((string) WSMA_Settings::get('intake_geolocation_api_url', '')) : '';
        $default_url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,city';
        $api_url = apply_filters('wsma_geolocation_api_url', $custom_url ?: $default_url, $ip);

        $response = wp_remote_get($api_url, [
            'timeout' => 2,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        // Lets a custom provider with a different JSON shape (e.g. no
        // top-level `status` field, or city/country under different keys)
        // return the final "City, Country" string directly, bypassing the
        // ip-api.com-shaped parsing below entirely.
        $override = apply_filters('wsma_geolocation_parsed', null, $response, $ip);
        if (is_string($override)) {
            set_transient($cache_key, $override, 7 * DAY_IN_SECONDS);
            return $override;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            set_transient($cache_key, '', DAY_IN_SECONDS);
            return '';
        }

        $parts = array_filter([$data['city'] ?? '', $data['country'] ?? '']);
        $geo = implode(', ', $parts);

        set_transient($cache_key, $geo, 7 * DAY_IN_SECONDS);

        return $geo;
    }
}