<?php

if (!defined('ABSPATH')) exit;

/**
 * Fires JSON payloads to configurable outbound webhook URLs (Zapier,
 * Make.com, Zoho CRM, Google Sheets via their own "catch webhook"
 * triggers) on two events: a new iscrizione, and a booking/payment
 * confirmation. Was previously just a settings-page toggle with zero
 * code behind it — this is the actual implementation.
 *
 * The two trigger points are both WordPress-native hooks rather than
 * calls added at every place an iscrizione gets created or confirmed
 * (there are several: FluentForms intake, manual admin creation,
 * WooCommerce/Stripe gateways in PRO, the Tutor migrator...) — using
 * `wp_insert_post`'s own $update flag and `updated_post_meta`/
 * `added_post_meta` on the `stato` key catches every one of those call
 * sites automatically, including future ones, without needing to know
 * about them.
 */
final class WS_Webhooks implements WS_Module {

    private const OPTION = 'ws_webhooks';
    private const LOG_OPTION = 'ws_webhooks_log';
    private const LOG_MAX = 20;

    public function should_load(): bool {
        return WS_Settings::is_module_active('webhooks', false);
    }

    public function register(): void {
        add_action('wp_insert_post', [$this, 'on_post_inserted'], 20, 3);
        add_action('added_post_meta', [$this, 'on_meta_changed'], 10, 4);
        add_action('updated_post_meta', [$this, 'on_meta_changed'], 10, 4);
        add_action('admin_menu', [$this, 'add_admin_menu'], 58);
        add_action('admin_post_ws_webhooks_save', [$this, 'handle_save']);
        add_action('admin_post_ws_webhooks_test', [$this, 'handle_test']);
    }

    // ───────────────────────── triggers ─────────────────────────

    public function on_post_inserted(int $post_id, WP_Post $post, bool $update): void {
        if ($update || $post->post_type !== 'iscrizione') return;
        // Fired at the tail end of wp_insert_post, before the caller has
        // had a chance to attach partecipante/evento/corso meta — defer
        // to the next request cycle isn't practical here, so just read
        // whatever meta exists right now (a moment later, via 'shutdown',
        // catches the fuller record without slowing the actual request).
        add_action('shutdown', function () use ($post_id) {
            $this->dispatch('iscrizione_created', $this->build_payload($post_id));
        });
    }

    public function on_meta_changed($meta_id, int $post_id, string $meta_key, $meta_value): void {
        if ($meta_key !== 'stato' || $meta_value !== 'confermato') return;
        if (get_post_type($post_id) !== 'iscrizione') return;
        $this->dispatch('confirmed', $this->build_payload($post_id));
    }

    private function build_payload(int $isc_id): array {
        $pid = (int) WS_Data::get_field('partecipante', $isc_id);
        $eid = (int) WS_Data::get_field('evento', $isc_id);
        $cid = (int) WS_Data::get_field('corso', $isc_id);

        return [
            'iscrizione_id' => $isc_id,
            'tipo' => (string) WS_Data::get_field('tipo_iscrizione', $isc_id),
            'stato' => (string) WS_Data::get_field('stato', $isc_id),
            'partecipante' => $pid ? [
                'nome' => (string) WS_Data::get_field('nome', $pid),
                'cognome' => (string) WS_Data::get_field('cognome', $pid),
                'email' => (string) WS_Data::get_field('email', $pid),
                'telefono' => (string) WS_Data::get_field('telefono', $pid),
            ] : null,
            'evento' => $eid ? ['id' => $eid, 'titolo' => get_the_title($eid)] : null,
            'corso' => $cid ? ['id' => $cid, 'titolo' => get_the_title($cid)] : null,
            'site' => home_url(),
            'timestamp' => current_time('mysql'),
        ];
    }

    private function dispatch(string $event, array $payload): void {
        foreach (self::get_endpoints() as $endpoint) {
            if (empty($endpoint['url']) || empty($endpoint['events'][$event])) continue;
            $this->send($endpoint['url'], $event, $payload, false);
        }
    }

    private function send(string $url, string $event, array $payload, bool $blocking): array {
        $response = wp_remote_post($url, [
            'timeout' => $blocking ? 10 : 0.5,
            'blocking' => $blocking,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['event' => $event, 'data' => $payload]),
        ]);

        $result = ['url' => $url, 'event' => $event, 'time' => current_time('mysql')];
        if ($blocking) {
            $result['success'] = !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
            $result['status'] = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_code($response);
        }
        $this->log($result);
        return $result;
    }

    private function log(array $entry): void {
        $log = get_option(self::LOG_OPTION, []);
        array_unshift($log, $entry);
        $log = array_slice($log, 0, self::LOG_MAX);
        update_option(self::LOG_OPTION, $log, false);
    }

    // ───────────────────────── storage ─────────────────────────

    public static function get_endpoints(): array {
        $stored = get_option(self::OPTION, []);
        return is_array($stored) ? $stored : [];
    }

    // ───────────────────────── admin ─────────────────────────

    public function add_admin_menu(): void {
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Webhooks', 'workshop-suite'),
            __('🔌 Webhooks', 'workshop-suite'),
            'manage_options',
            'ws-webhooks',
            [$this, 'render_admin_page']
        );
    }

    public function handle_save(): void {
        if (!current_user_can('manage_options')) wp_die('Access Denied');
        check_admin_referer('ws_webhooks_save');

        $labels = (array) ($_POST['label'] ?? []);
        $urls = (array) ($_POST['url'] ?? []);
        $events_created = (array) ($_POST['event_created'] ?? []);
        $events_confirmed = (array) ($_POST['event_confirmed'] ?? []);

        $endpoints = [];
        foreach ($urls as $i => $raw_url) {
            $url = esc_url_raw(trim((string) $raw_url));
            if (!$url) continue;
            $endpoints[] = [
                'label' => sanitize_text_field((string) ($labels[$i] ?? '')) ?: $url,
                'url' => $url,
                'events' => [
                    'iscrizione_created' => !empty($events_created[$i]),
                    'confirmed' => !empty($events_confirmed[$i]),
                ],
            ];
        }
        update_option(self::OPTION, $endpoints);

        wp_safe_redirect(add_query_arg(['page' => 'ws-webhooks', 'saved' => 1], admin_url('admin.php')));
        exit;
    }

    public function handle_test(): void {
        if (!current_user_can('manage_options')) wp_die('Access Denied');
        check_admin_referer('ws_webhooks_test');

        $url = esc_url_raw((string) ($_POST['test_url'] ?? ''));
        if ($url) {
            $this->send($url, 'test', ['message' => 'Test da Workshop Suite', 'site' => home_url(), 'timestamp' => current_time('mysql')], true);
        }

        wp_safe_redirect(add_query_arg(['page' => 'ws-webhooks', 'tested' => 1], admin_url('admin.php')));
        exit;
    }

    public function render_admin_page(): void {
        $endpoints = self::get_endpoints();
        if (empty($_GET['saved']) === false || empty($endpoints)) {
            // Always show at least one empty row to fill in.
        }
        $log = get_option(self::LOG_OPTION, []);
        ?>
        <div class="wrap" style="max-width: 900px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            <h1>🔌 <?php esc_html_e('Webhooks', 'workshop-suite'); ?></h1>
            <p style="font-size: 14px; color: #64748b;">
                <?php esc_html_e('Invia un payload JSON a Zapier, Make.com, Zoho CRM o Google Sheets (tramite il loro trigger "Catch Webhook") ad ogni nuova iscrizione e/o conferma.', 'workshop-suite'); ?>
            </p>

            <?php if (!empty($_GET['saved'])): ?>
                <div style="background:#ecfdf5;border:1px solid #10b981;padding:12px 16px;border-radius:8px;color:#065f46;margin-bottom:16px;">✅ Salvato.</div>
            <?php endif; ?>
            <?php if (!empty($_GET['tested'])): ?>
                <div style="background:#eff6ff;border:1px solid #3b82f6;padding:12px 16px;border-radius:8px;color:#1e3a8a;margin-bottom:16px;">📡 Test inviato — controlla il log qui sotto per l'esito.</div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ws_webhooks_save'); ?>
                <input type="hidden" name="action" value="ws_webhooks_save">

                <table class="widefat" style="margin-bottom: 16px;">
                    <thead>
                        <tr>
                            <th style="width:20%">Nome</th>
                            <th>URL Webhook</th>
                            <th style="width:120px">Nuova iscrizione</th>
                            <th style="width:120px">Conferma</th>
                        </tr>
                    </thead>
                    <tbody id="ws-webhooks-rows">
                        <?php
                        $rows = $endpoints ?: [['label' => '', 'url' => '', 'events' => ['iscrizione_created' => false, 'confirmed' => false]]];
                        foreach ($rows as $i => $e): ?>
                            <tr>
                                <td><input type="text" name="label[]" value="<?php echo esc_attr($e['label'] ?? ''); ?>" placeholder="Es. Make.com" style="width:100%"></td>
                                <td><input type="url" name="url[]" value="<?php echo esc_attr($e['url'] ?? ''); ?>" placeholder="https://hook.us1.make.com/..." style="width:100%"></td>
                                <td style="text-align:center"><input type="checkbox" name="event_created[<?php echo (int) $i; ?>]" value="1" <?php checked(!empty($e['events']['iscrizione_created'])); ?>></td>
                                <td style="text-align:center"><input type="checkbox" name="event_confirmed[<?php echo (int) $i; ?>]" value="1" <?php checked(!empty($e['events']['confirmed'])); ?>></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><input type="text" name="label[]" placeholder="Es. Zapier" style="width:100%"></td>
                            <td><input type="url" name="url[]" placeholder="https://hooks.zapier.com/..." style="width:100%"></td>
                            <td style="text-align:center"><input type="checkbox" name="event_created[<?php echo count($rows); ?>]" value="1"></td>
                            <td style="text-align:center"><input type="checkbox" name="event_confirmed[<?php echo count($rows); ?>]" value="1"></td>
                        </tr>
                    </tbody>
                </table>
                <div class="hint" style="font-size:13px;color:#64748b;margin-bottom:16px;">Lascia l'URL vuoto per non salvare quella riga. Compila sempre l'ultima riga vuota per aggiungerne una nuova al prossimo salvataggio.</div>

                <button type="submit" class="button button-primary">💾 Salva webhook</button>
            </form>

            <?php if ($endpoints): ?>
                <h2 style="margin-top:32px;">Test rapido</h2>
                <?php foreach ($endpoints as $e): if (empty($e['url'])) continue; ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin:0 8px 8px 0;">
                        <?php wp_nonce_field('ws_webhooks_test'); ?>
                        <input type="hidden" name="action" value="ws_webhooks_test">
                        <input type="hidden" name="test_url" value="<?php echo esc_attr($e['url']); ?>">
                        <button type="submit" class="button">📡 Invia test a "<?php echo esc_html($e['label']); ?>"</button>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($log): ?>
                <h2 style="margin-top:32px;">Log recente</h2>
                <table class="widefat striped">
                    <thead><tr><th>Quando</th><th>Evento</th><th>URL</th><th>Esito</th></tr></thead>
                    <tbody>
                        <?php foreach ($log as $entry): ?>
                            <tr>
                                <td><?php echo esc_html($entry['time'] ?? ''); ?></td>
                                <td><?php echo esc_html($entry['event'] ?? ''); ?></td>
                                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html($entry['url'] ?? ''); ?></td>
                                <td>
                                    <?php if (array_key_exists('success', $entry)): ?>
                                        <?php echo $entry['success'] ? '✅ ' . esc_html((string) $entry['status']) : '❌ ' . esc_html((string) $entry['status']); ?>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">inviato (non verificato)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
