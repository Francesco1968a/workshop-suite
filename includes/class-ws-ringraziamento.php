<?php

if (!defined('ABSPATH')) exit;

/**
 * Ported from legacy snippet 50 "Mail di Ringraziamento" (Mail #4, T+2gg
 * after the event concludes, to Confermato participants).
 *
 * No AI or FluentCRM involved in the legacy code — it used plain
 * per-category ACF templates rendered via `wv_render_template()` (a
 * placeholder-substitution function, same family as
 * `WSMA_Data::render_template()`), with a fallback to a hardcoded Italian
 * default body. That part is a clean, behavior-preserving port.
 *
 * BEHAVIOR CHANGE (user-confirmed 2026-08-17): the legacy code only
 * created a DRAFT for manual review/send (`wv_crea_bozza()`, defined by an
 * unrelated legacy snippet); this port sends automatically via cron using
 * the same direct-send pattern as Conferma (WSMA_Rest_Riepilogo) and T-15
 * (WSMA_T15_Reminder): WSMA_Data::render_template() + WSMA_Mail_Inbox::send_reply(),
 * logging to `wv_thread`, updating `mail_ringraziamento_sent_at` on success.
 *
 * The `oggetto_ringraziamento` / `mail_ringraziamento` ACF fields on
 * categoria_evento are a NEW field group not registered anywhere else in
 * the plugin — no collision with existing WSMA_Post_Types fields.
 *
 * The admin UI (settings + pending-list + send/skip actions) is a REST API
 * here, consumed by the Vue panel `panels/Ringraziamento.vue` registered
 * via WSMA_Shortcode_Ringraziamento — see that class for the shortcode.
 */
final class WSMA_Ringraziamento implements WSMA_Module {

    public const DAYS_OFFSET = 2;
    public const META_SENT = 'mail_ringraziamento_sent_at';
    public const META_THREAD = 'wv_thread';
    public const CRON_HOOK = 'ws_cron_ringraziamento_daily';
    private const LEGACY_CRON_HOOK = 'fvw_cron_ringraziamento_daily';

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('init', [$this, 'migrate_legacy_cron']);
        add_action('init', [$this, 'ensure_scheduled']);
        add_action(self::CRON_HOOK, [$this, 'process']);
        add_action('acf/init', [$this, 'register_fields']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /** One-time cleanup of the orphaned pre-rename event. */
    public function migrate_legacy_cron(): void {
        if (wp_next_scheduled(self::LEGACY_CRON_HOOK)) {
            wp_clear_scheduled_hook(self::LEGACY_CRON_HOOK);
        }
    }

    public function register_routes(): void {
        $perm = fn() => current_user_can('manage_options');

        register_rest_route('workshop-suite/v1', '/ringraziamento/pannello', [
            'methods' => 'GET', 'callback' => [$this, 'get_pannello'], 'permission_callback' => $perm,
        ]);
        register_rest_route('workshop-suite/v1', '/ringraziamento/impostazioni', [
            'methods' => 'POST', 'callback' => [$this, 'save_impostazioni'], 'permission_callback' => $perm,
        ]);
        register_rest_route('workshop-suite/v1', '/ringraziamento/iscrizione/(?P<id>\d+)/invia', [
            'methods' => 'POST', 'callback' => [$this, 'invia_una'], 'permission_callback' => $perm,
        ]);
        register_rest_route('workshop-suite/v1', '/ringraziamento/iscrizione/(?P<id>\d+)/salta', [
            'methods' => 'POST', 'callback' => [$this, 'salta_una'], 'permission_callback' => $perm,
        ]);
        register_rest_route('workshop-suite/v1', '/ringraziamento/invia-tutte', [
            'methods' => 'POST', 'callback' => [$this, 'invia_tutte'], 'permission_callback' => $perm,
        ]);
    }

    private function riga(WP_Post $isc): array {
        $p = WSMA_Data::get_field('partecipante', $isc->ID);
        $e = WSMA_Data::get_field('evento', $isc->ID);
        return [
            'isc_id' => $isc->ID,
            'nome' => $p ? trim(WSMA_Data::get_field('nome', $p) . ' ' . WSMA_Data::get_field('cognome', $p)) : '?',
            'evento' => $e ? get_the_title($e) : '?',
            'email' => $p ? WSMA_Data::get_field('email', $p) : '',
        ];
    }

    public function get_pannello(): WP_REST_Response {
        $oggi = array_map([$this, 'riga'], self::pending(self::DAYS_OFFSET));

        $prossimi = [];
        for ($d = self::DAYS_OFFSET + 9; $d > self::DAYS_OFFSET; $d--) {
            $iscs = self::pending($d);
            if ($iscs) $prossimi[] = ['giorni' => $d, 'iscrizioni' => array_map([$this, 'riga'], $iscs)];
        }

        $next_cron = wp_next_scheduled(self::CRON_HOOK);

        return new WP_REST_Response([
            'autosend' => get_option('wsma_ringraziamento_autosend', '1') === '1',
            'oggi' => $oggi,
            'prossimi' => $prossimi,
            'next_cron' => $next_cron ? date_i18n('d/m/Y H:i', $next_cron) : null,
        ]);
    }

    public function save_impostazioni(WP_REST_Request $request): WP_REST_Response {
        update_option('wsma_ringraziamento_autosend', $request->get_param('autosend') ? '1' : '0');
        return new WP_REST_Response(['ok' => true]);
    }

    public function invia_una(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $isc_id = (int) $request['id'];
        if (get_post_type($isc_id) !== 'wsma_iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }
        if (!$this->send_one($isc_id)) {
            return new WP_Error('send_failed', 'Invio fallito (email mancante o già inviata).', ['status' => 500]);
        }
        return new WP_REST_Response(['ok' => true]);
    }

    public function salta_una(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $isc_id = (int) $request['id'];
        if (get_post_type($isc_id) !== 'wsma_iscrizione') {
            return new WP_Error('not_found', 'Iscrizione non trovata', ['status' => 404]);
        }
        WSMA_Data::update_field(self::META_SENT, current_time('mysql'), $isc_id);
        return new WP_REST_Response(['ok' => true]);
    }

    public function invia_tutte(): WP_REST_Response {
        return new WP_REST_Response(['inviate' => $this->process()]);
    }

    public function ensure_scheduled(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(strtotime('tomorrow 10:00'), 'daily', self::CRON_HOOK);
        }
    }

    public function register_fields(): void {
        if (!function_exists('acf_add_local_field_group')) return;

        acf_add_local_field_group([
            'key' => 'group_cat_ringraziamento',
            'title' => 'Mail #4 Ringraziamento (template)',
            'fields' => [
                ['key' => 'field_cat_ogg_ring', 'label' => 'Oggetto', 'name' => 'oggetto_ringraziamento', 'type' => 'text'],
                ['key' => 'field_cat_mail_ring', 'label' => 'Corpo', 'name' => 'mail_ringraziamento', 'type' => 'textarea', 'rows' => 10],
            ],
            'location' => [[['param' => 'taxonomy', 'operator' => '==', 'value' => 'wsma_categoria_evento']]],
        ]);
    }

    /** @return int[] iscrizione IDs — Confermate, evento concluso esattamente N giorni fa, non ancora inviate. */
    public static function pending(int $days_after = self::DAYS_OFFSET): array {
        $target_date = wp_date('Y-m-d', strtotime("-{$days_after} days"));

        $eventi = get_posts([
            'post_type'      => 'wsma_evento',
            'posts_per_page' => -1,
            'meta_query'     => [
                ['key' => 'data_fine', 'value' => $target_date, 'compare' => '='],
            ],
            'fields' => 'ids',
        ]);

        if (empty($eventi)) return [];

        return get_posts([
            'post_type'      => 'wsma_iscrizione',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => 'evento', 'value' => $eventi, 'compare' => 'IN'],
                ['key' => 'stato',  'value' => 'confermato', 'compare' => '='],
                ['relation' => 'OR',
                 ['key' => self::META_SENT, 'compare' => 'NOT EXISTS'],
                 ['key' => self::META_SENT, 'value' => '', 'compare' => '=']],
            ],
        ]);
    }

    public function process(int $days_after = self::DAYS_OFFSET): int {
        if (get_option('wsma_ringraziamento_autosend', '1') !== '1') return 0;

        $n = 0;
        foreach (self::pending($days_after) as $isc) {
            if ($this->send_one((int) $isc->ID)) $n++;
        }
        return $n;
    }

    public function send_one(int $isc_id): bool {
        if (!$isc_id) return false;
        if (WSMA_Data::get_field(self::META_SENT, $isc_id)) return false;

        $pid = (int) WSMA_Data::get_field('partecipante', $isc_id);
        $eid = (int) WSMA_Data::get_field('evento', $isc_id);
        if (!$pid || !$eid) return false;

        $email = WSMA_Data::get_field('email', $pid);
        if (!$email) return false;

        $terms = get_the_terms($eid, 'wsma_categoria_evento');
        $cat_id = $terms ? $terms[0]->term_id : 0;

        $oggetto_tpl = $cat_id ? WSMA_Data::get_field('oggetto_ringraziamento', 'wsma_categoria_evento_' . $cat_id) : '';
        $mail_tpl = $cat_id ? WSMA_Data::get_field('mail_ringraziamento', 'wsma_categoria_evento_' . $cat_id) : '';

        if (!$mail_tpl) {
            $body = $this->default_body($isc_id);
            $subject = $oggetto_tpl
                ? WSMA_Data::render_template($oggetto_tpl, $isc_id)
                : ('Grazie per aver reso speciale ' . WSMA_Data::format_periodo($eid));
        } else {
            $subject = WSMA_Data::render_template($oggetto_tpl ?: 'Grazie!', $isc_id);
            $body = WSMA_Data::render_template($mail_tpl, $isc_id);
        }

        if (!$body) return false;

        $result = WSMA_Mail_Inbox::send_reply($email, $subject, $body);
        if (!$result['ok']) return false;

        WSMA_Data::update_field(self::META_SENT, current_time('mysql'), $isc_id);
        WSMA_Data::append_thread($isc_id, 'out', $subject, $body);

        return true;
    }

    public function default_body(int $isc_id): string {
        $pid = WSMA_Data::get_field('partecipante', $isc_id);
        $nome = $pid ? WSMA_Data::get_field('nome', $pid) : '';
        $eid = WSMA_Data::get_field('evento', $isc_id);
        $terms = $eid ? get_the_terms($eid, 'wsma_categoria_evento') : null;
        $cat_name = $terms ? $terms[0]->name : 'questa esperienza';
        $periodo = $eid ? WSMA_Data::format_periodo($eid) : '';

        return "Ciao {$nome},\n\n"
             . "grazie per aver reso speciale {$cat_name} del {$periodo}.\n\n"
             . "I partecipanti sono il 50% dell'esperienza, e questa volta non ha fatto eccezione.\n\n"
             . "Ti lascio un Drive condiviso dove puoi caricare gli scatti che vuoi condividere col gruppo. "
             . "Mi farebbe piacere rivedere le tue foto e magari ripubblicarne qualcuna (chiedendoti prima il permesso).\n\n"
             . "[LINK DRIVE]\n\n"
             . "A presto, ovunque ci porti la prossima volta,\n"
             . "Francesco";
    }

}
