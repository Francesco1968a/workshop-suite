<?php

if (!defined('ABSPATH')) exit;

/**
 * REST endpoint backing the "Messaggi" dashboard tab: the interactive
 * mapping of who needs which mail, requested by the user as the point of
 * the messaging-system rework ("mi interessa più che altro una mappatura
 * interattiva con le informazioni di quale mail deve partire").
 *
 * Read-only listing here; the actual send actions reuse existing
 * endpoints (WS_Rest_Riepilogo::conferma_iscrizione for Conferma) or a
 * thin per-row wrapper around WS_T15_Reminder::send_one() for T-15.
 */
final class WS_Rest_Messaggi implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        $perm = fn() => current_user_can('manage_options');
        $ns = 'workshop-suite/v1';

        register_rest_route($ns, '/admin/messaggi-tab', ['methods' => 'GET', 'callback' => [$this, 'get_messaggi_tab'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/messaggi/t15/(?P<id>\d+)', ['methods' => 'POST', 'callback' => [$this, 'invia_t15_singolo'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/messaggi/(?P<id>\d+)/bozza', ['methods' => 'POST', 'callback' => [$this, 'salva_bozza'], 'permission_callback' => $perm]);
        register_rest_route($ns, '/admin/messaggi/(?P<id>\d+)/invia-risposta', ['methods' => 'POST', 'callback' => [$this, 'invia_risposta'], 'permission_callback' => $perm]);
    }

    private const META_DRAFT = 'wv_reply_draft';
    private const META_THREAD = 'wv_thread';

    private function get_thread(int $isc_id): array {
        $raw = get_post_meta($isc_id, self::META_THREAD, true);
        $thread = $raw ? json_decode($raw, true) : [];
        return is_array($thread) ? $thread : [];
    }

    private function riga_iscrizione(int $isc_id, string $tipo, ?int $giorni_a_evento = null): array {
        $pid = (int) WS_Data::get_field('partecipante', $isc_id);
        $eid = (int) WS_Data::get_field('evento', $isc_id);
        return [
            'isc_id' => $isc_id,
            'tipo' => $tipo,
            'nome' => $pid ? trim(WS_Data::get_field('nome', $pid) . ' ' . WS_Data::get_field('cognome', $pid)) : get_the_title($isc_id),
            'email' => $pid ? (WS_Data::get_field('email', $pid) ?: '') : '',
            'telefono' => $pid ? (WS_Data::get_field('telefono', $pid) ?: '') : '',
            'citta' => $pid ? (WS_Data::get_field('citta', $pid) ?: '') : '',
            'evento_label' => $eid ? WS_Data::evento_label($eid) : '',
            'giorni_a_evento' => $giorni_a_evento,
            'messaggio_originale' => WS_Data::get_field('messaggio_originale', $isc_id) ?: '',
            // The form-submission date — an iscrizione is created at the
            // moment FluentForm submits it, so its own post date is that date.
            'messaggio_originale_data' => get_the_date('d/m/Y H:i', $isc_id),
            'note' => (string) WS_Data::get_field('note', $isc_id),
            'reply_draft' => (string) get_post_meta($isc_id, self::META_DRAFT, true),
            'thread' => $this->get_thread($isc_id),
        ];
    }

    /**
     * Every iscrizione on a non-concluded event, not just the ones needing
     * an action — confirming someone (or their T-15 reminder going out)
     * doesn't close the relationship, so they shouldn't disappear from the
     * contact list once handled.
     */
    public function get_messaggi_tab(): WP_REST_Response {
        $oggi = date('Y-m-d');

        $eventi_attivi = get_posts([
            'post_type' => 'evento', 'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => [['key' => 'data_fine', 'value' => $oggi, 'compare' => '>=', 'type' => 'DATE']],
        ]);

        $t15_pending = array_map('intval', WS_T15_Reminder::pending());
        $contatti = [];

        if ($eventi_attivi) {
            $iscrizioni = get_posts([
                'post_type' => 'iscrizione', 'posts_per_page' => -1,
                'meta_query' => [['key' => 'evento', 'value' => $eventi_attivi, 'compare' => 'IN']],
            ]);
            foreach ($iscrizioni as $isc) {
                $stato = WS_Data::get_field('stato', $isc->ID);
                if ($stato !== 'confermato') {
                    $tipo = 'conferma';
                } elseif (in_array($isc->ID, $t15_pending, true)) {
                    $tipo = 't15';
                } else {
                    $tipo = 'ok';
                }

                $eid = (int) WS_Data::get_field('evento', $isc->ID);
                $data_ev = WS_Data::get_field('data_evento', $eid);
                $giorni = $data_ev ? (int) floor((strtotime($data_ev) - strtotime($oggi)) / 86400) : null;

                $contatti[] = $this->riga_iscrizione($isc->ID, $tipo, $giorni);
            }
        }

        $priorita = ['conferma' => 0, 't15' => 1, 'ok' => 2];
        usort($contatti, fn($a, $b) => $priorita[$a['tipo']] <=> $priorita[$b['tipo']] ?: ($a['giorni_a_evento'] <=> $b['giorni_a_evento']));

        return new WP_REST_Response([
            'contatti' => $contatti,
        ]);
    }

    public function invia_t15_singolo(WP_REST_Request $request) {
        $isc_id = (int) $request['id'];
        if (get_post_type($isc_id) !== 'iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }
        $reminder = new WS_T15_Reminder();
        $ok = $reminder->send_one($isc_id);
        return new WP_REST_Response(['sent' => $ok, 'msg' => $ok ? 'Promemoria inviato.' : 'Invio fallito (email mancante o casella mail non configurata?).']);
    }

    public function salva_bozza(WP_REST_Request $request) {
        $isc_id = (int) $request['id'];
        if (get_post_type($isc_id) !== 'iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }
        $body = sanitize_textarea_field((string) $request->get_param('body'));
        update_post_meta($isc_id, self::META_DRAFT, $body);
        return new WP_REST_Response(['msg' => 'Bozza salvata.', 'reply_draft' => $body]);
    }

    public function invia_risposta(WP_REST_Request $request) {
        $isc_id = (int) $request['id'];
        if (get_post_type($isc_id) !== 'iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }

        $pid = (int) WS_Data::get_field('partecipante', $isc_id);
        $email = $pid ? WS_Data::get_field('email', $pid) : '';
        $body = sanitize_textarea_field((string) $request->get_param('body'));
        $subject = sanitize_text_field((string) $request->get_param('subject')) ?: 'Re: la tua richiesta';

        if (!$email || !$body) {
            return new WP_Error('invalid', 'Email del partecipante o testo mancante.', ['status' => 400]);
        }

        // Sent via direct Zoho SMTP (WS_Mail_Inbox::send_reply), not wp_mail():
        // FluentSMTP routes this site through Amazon SES with a forced sender
        // address, which would silently override any From we set and likely
        // reject an unverified sender anyway.
        $result = WS_Mail_Inbox::send_reply($email, $subject, $body);
        if (!$result['ok']) {
            return new WP_Error('send_failed', $result['msg'], ['status' => 500]);
        }

        $thread = $this->get_thread($isc_id);
        $thread[] = ['direction' => 'out', 'subject' => $subject, 'body' => $body, 'date' => current_time('mysql')];
        update_post_meta($isc_id, self::META_THREAD, wp_json_encode($thread));
        update_post_meta($isc_id, self::META_DRAFT, '');

        return new WP_REST_Response(['msg' => 'Risposta inviata.', 'thread' => $thread, 'reply_draft' => '']);
    }
}
