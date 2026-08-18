<?php

if (!defined('ABSPATH')) exit;

/**
 * REST endpoints backing the Partecipante detail/scheda panel.
 * Ports the read + save logic from the legacy `workshop_partecipante`
 * shortcode 1:1 (same ACF fields, same stats/timeline sources).
 */
final class WS_Rest_Partecipante implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        $perm = fn() => current_user_can('manage_options');

        register_rest_route('workshop-suite/v1', '/partecipante/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_partecipante'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('workshop-suite/v1', '/partecipante/(?P<id>\d+)/anagrafica', [
            'methods'             => 'POST',
            'callback'            => [$this, 'save_anagrafica'],
            'permission_callback' => $perm,
        ]);

        register_rest_route('workshop-suite/v1', '/partecipante/(?P<id>\d+)/note', [
            'methods'             => 'POST',
            'callback'            => [$this, 'save_note'],
            'permission_callback' => $perm,
        ]);
    }

    private function find(int $pid) {
        if (!$pid || get_post_type($pid) !== 'partecipante') return null;
        return $pid;
    }

    public function get_partecipante(WP_REST_Request $request) {
        $pid = $this->find((int) $request['id']);
        if (!$pid) return new WP_Error('not_found', 'Partecipante non trovato', ['status' => 404]);

        return new WP_REST_Response($this->serialize($pid));
    }

    public function save_anagrafica(WP_REST_Request $request) {
        $pid = $this->find((int) $request['id']);
        if (!$pid) return new WP_Error('not_found', 'Partecipante non trovato', ['status' => 404]);

        foreach (['nome', 'cognome', 'email', 'telefono', 'citta'] as $f) {
            $val = sanitize_text_field((string) $request->get_param($f));
            if ($f === 'email') $val = sanitize_email($val);
            WS_Data::update_field($f, $val, $pid);
        }
        $new_title = trim(WS_Data::get_field('nome', $pid) . ' ' . WS_Data::get_field('cognome', $pid));
        if ($new_title) wp_update_post(['ID' => $pid, 'post_title' => $new_title]);

        return new WP_REST_Response($this->serialize($pid));
    }

    public function save_note(WP_REST_Request $request) {
        $pid = $this->find((int) $request['id']);
        if (!$pid) return new WP_Error('not_found', 'Partecipante non trovato', ['status' => 404]);

        $note = sanitize_textarea_field((string) $request->get_param('note_interne'));
        WS_Data::update_field('note_interne', $note, $pid);

        return new WP_REST_Response(['note_interne' => $note]);
    }

    private function serialize(int $pid): array {
        $nome    = WS_Data::get_field('nome', $pid);
        $cognome = WS_Data::get_field('cognome', $pid);
        $email   = WS_Data::get_field('email', $pid);
        $tel     = WS_Data::get_field('telefono', $pid);
        $citta   = WS_Data::get_field('citta', $pid);
        $note    = WS_Data::get_field('note_interne', $pid) ?: '';

        $stats    = WS_Data::stats_partecipante($pid);
        $timeline = WS_Data::timeline_partecipante($pid);

        $tel_clean = preg_replace('/\D/', '', (string) $tel);
        $wa_link = $tel_clean ? 'https://wa.me/' . ($tel_clean[0] === '3' ? '39' . $tel_clean : $tel_clean) : '';

        return [
            'id'       => $pid,
            'nome'     => $nome ?: '',
            'cognome'  => $cognome ?: '',
            'email'    => $email ?: '',
            'telefono' => $tel ?: '',
            'citta'    => $citta ?: '',
            'note_interne' => $note,
            'wa_link'  => $wa_link,
            'stats' => [
                'totali'          => (int) $stats['totali'],
                'confermate'      => (int) $stats['confermate'],
                'richieste'       => (int) $stats['richieste'],
                'abbandonate'     => (int) $stats['abbandonate'],
                'conv_pct'        => (int) $stats['conv_pct'],
                'mail_inviate'    => (int) $stats['mail_inviate'],
                'prima_iscr_fmt'  => $stats['prima_iscr'] ? date_i18n('d/m/Y', strtotime($stats['prima_iscr'])) : null,
                'giorni_ultimo'   => $stats['giorni_ultimo'],
            ],
            'timeline' => array_map(function ($e) {
                $when = strtotime($e['t']);
                return [
                    'icon'    => $e['icon'],
                    'titolo'  => $e['titolo'],
                    'evento'  => $e['evento'],
                    'extra'   => $e['extra'],
                    'data_fmt' => date_i18n('d/m/Y H:i', $when),
                    'rel_fmt'  => human_time_diff($when, current_time('timestamp')) . ' fa',
                ];
            }, $timeline),
        ];
    }
}