<?php

if (!defined('ABSPATH')) exit;

/**
 * Native WP Admin settings page for WSMaker.
 */
final class WSMA_Admin_Settings_Page implements WSMA_Module {

    public function should_load(): bool {
        return is_admin();
    }

    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu_page'], 10);
        add_action('admin_menu', [$this, 'add_trailing_submenus'], 99);
        add_action('admin_enqueue_scripts', [$this, 'inject_admin_menu_separator_css']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_settings_page_styles']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_mail_settings_save']);
        add_action('admin_init', [$this, 'handle_proponente_settings_save']);
        add_action('admin_init', [$this, 'handle_modules_settings_save']);
    }

    public function handle_modules_settings_save(): void {
        if (isset($_POST['ws_save_modules_action']) && check_admin_referer('wsma_save_modules_nonce')) {
            if (current_user_can('manage_options')) {
                $current = WSMA_Settings::get_all();
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only checked with !empty() below (cast to 1/0), never output or stored raw.
                $submitted_modules = isset($_POST['ws_modules']) ? (array) wp_unslash($_POST['ws_modules']) : [];
                
                $known_modules = [
                    'courses_academy', 'voucher_pdf', 'ai_assistant', 'multi_docente', 
                    'stripe_payments', 'woocommerce', 'webhooks', 'calendar_sync',
                    'fluentcrm_marketing', 'sms_whatsapp_gateway', 'poster_studio', 'whatsapp_widget'
                ];

                $updated_active = [];
                foreach ($known_modules as $mod) {
                    $updated_active[$mod] = !empty($submitted_modules[$mod]) ? 1 : 0;
                }

                $current['active_modules'] = $updated_active;
                WSMA_Settings::update_all($current);

                wp_safe_redirect(admin_url('admin.php?page=workshop-suite-settings&tab=modules&updated=1'));
                exit;
            }
        }
    }

    public function handle_proponente_settings_save(): void {
        if (isset($_POST['ws_save_proponente_action']) && check_admin_referer('wsma_save_proponente_nonce')) {
            if (current_user_can('manage_options')) {
                $current = WSMA_Settings::get_all();
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized individually below (sanitize_text_field/sanitize_email/esc_url_raw/etc).
                $data = isset($_POST['ws_proponente']) ? (array) wp_unslash($_POST['ws_proponente']) : [];

                $current['proponente_nome']      = sanitize_text_field($data['proponente_nome'] ?? '');
                $current['proponente_ruolo']     = sanitize_text_field($data['proponente_ruolo'] ?? '');
                $current['proponente_bio']       = sanitize_textarea_field($data['proponente_bio'] ?? '');
                $current['proponente_foto']      = esc_url_raw($data['proponente_foto'] ?? '');
                $current['proponente_lingue']    = isset($data['proponente_lingue']) && is_array($data['proponente_lingue']) ? array_values(array_map('sanitize_text_field', $data['proponente_lingue'])) : [];
                $current['proponente_sito']      = esc_url_raw($data['proponente_sito'] ?? '');
                $current['proponente_email']     = sanitize_email($data['proponente_email'] ?? '');
                $current['proponente_telefono']  = sanitize_text_field($data['proponente_telefono'] ?? '');
                $current['proponente_citta']     = sanitize_text_field($data['proponente_citta'] ?? '');
                $current['proponente_instagram'] = sanitize_text_field($data['proponente_instagram'] ?? '');
                $current['proponente_facebook']  = esc_url_raw($data['proponente_facebook'] ?? '');
                $current['proponente_youtube']   = esc_url_raw($data['proponente_youtube'] ?? '');
                $current['proponente_linkedin']  = esc_url_raw($data['proponente_linkedin'] ?? '');
                $current['proponente_tiktok']    = sanitize_text_field($data['proponente_tiktok'] ?? '');
                $current['proponente_x']         = sanitize_text_field($data['proponente_x'] ?? '');

                WSMA_Settings::update_all($current);
                wp_safe_redirect(admin_url('admin.php?page=workshop-suite-settings&tab=proponente&updated=1'));
                exit;
            }
        }
    }

    public function handle_mail_settings_save(): void {
        if (isset($_POST['ws_save_mail_action']) && check_admin_referer('wsma_save_mail_nonce')) {
            if (current_user_can('manage_options')) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized inside WSMA_Mail_Inbox::save_settings().
                WSMA_Mail_Inbox::save_settings(isset($_POST['ws_mail']) ? (array) wp_unslash($_POST['ws_mail']) : []);

                // Save anti-spam and rate limiting options
                if (isset($_POST['ws_security'])) {
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized individually below (!empty()/absint-style (int) cast).
                    $sec = (array) wp_unslash($_POST['ws_security']);
                    $current_settings = WSMA_Settings::get_all();
                    $current_settings['intake_rate_limit_enabled']  = !empty($sec['intake_rate_limit_enabled']) ? 1 : 0;
                    $current_settings['intake_rate_limit_requests'] = max(1, (int) ($sec['intake_rate_limit_requests'] ?? 5));
                    $current_settings['intake_rate_limit_window']   = max(10, (int) ($sec['intake_rate_limit_window'] ?? 60));
                    $current_settings['intake_honeypot_enabled']    = !empty($sec['intake_honeypot_enabled']) ? 1 : 0;
                    $current_settings['intake_geolocation_enabled'] = !empty($sec['intake_geolocation_enabled']) ? 1 : 0;
                    $current_settings['intake_geolocation_api_url'] = esc_url_raw(trim((string) ($sec['intake_geolocation_api_url'] ?? '')));
                    WSMA_Settings::update_all($current_settings);
                }

                wp_safe_redirect(admin_url('admin.php?page=workshop-suite-settings&tab=mail&updated=1'));
                exit;
            }
        }
    }

    /**
     * Registered on admin_enqueue_scripts (not admin_head, which fires
     * after WordPress's own admin_print_styles point) — applies to the
     * left sidebar menu on every admin page, so no page-check gating.
     */
    /**
     * All the settings-page-only CSS that used to print as raw <style>
     * tags directly inside render_page() (called too late for that,
     * during content rendering — same reasoning as
     * inject_admin_menu_separator_css() above). One consolidated method,
     * gated on the settings page overall rather than per-tab, since the
     * unused rules for a tab that isn't currently shown are harmless.
     */
    public function enqueue_settings_page_styles(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page-slug check to decide whether to enqueue styles, no form data is processed here.
        if (empty($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== 'workshop-suite-settings') return;

        WSMA_Data::enqueue_inline_style(
            // General tab: tipi di evento theme-aware inputs.
            '.ws-theme-light #ws-event-types-container input { background: #ffffff !important; color: #0f172a !important; border: 1px solid #cbd5e1 !important; }'
            . '.ws-theme-light #ws-event-types-container button { background: #fff1f2 !important; border-color: #f43f5e !important; color: #e11d48 !important; }'
            . '.ws-theme-dark #ws-event-types-container input { background: rgba(255,255,255,0.05) !important; color: #ffffff !important; border: 1px solid rgba(255,255,255,0.2) !important; }'
            // Modules tab: FluentCRM combo dropdown.
            . '.ws-combo-dropdown { display: none; position: absolute; top: 100%; left: 0; right: 0; margin-top: 2px; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; max-height: 160px; overflow-y: auto; z-index: 100001; box-shadow: 0 8px 24px rgba(0,0,0,.12); }'
            . '.ws-combo-dropdown .ws-combo-item { padding: 7px 10px; font-size: 13px; cursor: pointer; }'
            . '.ws-combo-dropdown .ws-combo-item:hover, .ws-combo-dropdown .ws-combo-item.ws-combo-item--active { background: #f1f5f9; }'
            . '.ws-combo-dropdown .ws-combo-empty { padding: 7px 10px; font-size: 12px; color: #94a3b8; }'
            // Modules tab: module-card toggle switch.
            . '.ws-toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }'
            . '.ws-toggle-switch input { opacity: 0; width: 0; height: 0; }'
            . '.ws-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #c3c4c7; transition: .2s; border-radius: 24px; }'
            . '.ws-toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .2s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }'
            . '.ws-toggle-switch input:checked + .ws-toggle-slider { background-color: #2271b1; }'
            . '.ws-toggle-switch input:checked + .ws-toggle-slider:before { transform: translateX(20px); }'
        );
    }

    public function inject_admin_menu_separator_css(): void {
        WSMA_Data::enqueue_inline_style(
            '#adminmenu .toplevel_page_workshop-suite-dashboard .wp-submenu li.ws-menu-separator {'
            . 'border-top: 1px solid rgba(240, 246, 252, 0.18) !important;'
            . 'margin-top: 6px !important;'
            . 'padding-top: 4px !important;'
            . '}'
            . '#adminmenu .toplevel_page_workshop-suite-dashboard .wp-submenu li.ws-menu-separator a {'
            . 'font-weight: 600 !important;'
            . '}'
        );
    }

    public function add_menu_page(): void {
        // Main Top Level Menu -> Loads Vue Dashboard
        add_menu_page(
            __('WSMaker Dashboard', 'wsmaker'),
            __('WSMaker', 'wsmaker'),
            'manage_options',
            'workshop-suite-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-tickets-alt',
            30
        );

        // Submenu 1: Dashboard
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Dashboard', 'wsmaker'),
            __('Dashboard', 'wsmaker'),
            'manage_options',
            'workshop-suite-dashboard',
            [$this, 'render_dashboard']
        );

        // Submenu 2: Categories & Types
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Categories & Types', 'wsmaker'),
            __('Categories & Types', 'wsmaker'),
            'manage_options',
            'workshop-suite-categorie',
            [$this, 'render_riepilogo']
        );

        // Submenu 3: Events & Registrations
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Events & Registrations', 'wsmaker'),
            __('Events & Registrations', 'wsmaker'),
            'manage_options',
            'workshop-suite-eventi',
            [$this, 'render_eventi_partecipanti']
        );

        // Note: the "Aula Virtuale" menu entry itself is registered by the
        // PRO plugin (positioned between its own Corsi/Studenti items) —
        // see WS_Pro_Course_Builder_Page::add_menu_page(). render_aula_virtuale()
        // below stays here since the panel it renders (Eventi/Categoria/
        // Partecipanti, filtered to Modalità=virtuale) is core functionality.

        // Submenu 4: Poster Templates (Conditional module)
        if (WSMA_Settings::is_module_active('poster_studio', true)) {
            add_submenu_page(
                'workshop-suite-dashboard',
                __('Poster Templates', 'wsmaker'),
                __('Poster Templates', 'wsmaker'),
                'manage_options',
                'workshop-suite-locandine',
                [$this, 'render_locandine']
            );
        }

        // Submenu 5: Contacts & Participants
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Contacts & Participants', 'wsmaker'),
            __('Contacts & Participants', 'wsmaker'),
            'manage_options',
            'workshop-suite-partecipanti',
            [$this, 'render_partecipanti']
        );

        // Submenu 6: Mail Inbox
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Mail Inbox', 'wsmaker'),
            __('Mail Inbox', 'wsmaker'),
            'manage_options',
            'workshop-suite-messaggi',
            [$this, 'render_messaggi']
        );

        // Submenu 7: Admin Calendar
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Admin Calendar', 'wsmaker'),
            __('Admin Calendar', 'wsmaker'),
            'manage_options',
            'workshop-suite-calendario',
            [$this, 'render_calendario']
        );

        // Submenu 8: Events Archive
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Events Archive', 'wsmaker'),
            __('Events Archive', 'wsmaker'),
            'manage_options',
            'workshop-suite-archivio',
            [$this, 'render_archivio']
        );
    }

    public function add_trailing_submenus(): void {
        global $submenu;

        // Submenu: Moduli & Add-on (con separatore visuale sopra)
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Moduli & Add-on', 'wsmaker'),
            __('Moduli & Add-on', 'wsmaker'),
            'manage_options',
            'admin.php?page=workshop-suite-settings&tab=modules'
        );

        // Submenu: Settings
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Settings', 'wsmaker'),
            __('Settings', 'wsmaker'),
            'manage_options',
            'workshop-suite-settings',
            [$this, 'render_page']
        );

        // Mark Moduli & Add-on item with a separator CSS class
        if (isset($submenu['workshop-suite-dashboard'])) {
            foreach ($submenu['workshop-suite-dashboard'] as &$item) {
                if (isset($item[2]) && strpos($item[2], 'tab=modules') !== false) {
                    if (isset($item[4])) {
                        $item[4] .= ' ws-menu-separator';
                    } else {
                        $item[4] = 'ws-menu-separator';
                    }
                }
            }
        }
    }

    /** Enqueues one Vue bundle's JS/CSS + WSMA_CONFIG, without emitting any markup. */
    private function enqueue_panel_assets(string $handle, string $js_file, string $css_file, array $extra_config = []): void {
        $asset_js  = WSMA_PATH . $js_file;
        $asset_css = WSMA_PATH . $css_file;

        // Ensure the Vue ESM script loads with type="module".
        // This filter is normally registered by the matching WSMA_Shortcode_* class,
        // but in WP Admin the shortcode is never invoked, so we register it here.
        static $module_filters = [];
        if (!isset($module_filters[$handle])) {
            $module_filters[$handle] = true;
            add_filter('script_loader_tag', function (string $tag, string $h) use ($handle): string {
                if ($h !== $handle) return $tag;
                if (strpos($tag, 'type=') !== false) return $tag;
                return str_replace(' src=', ' type="module" src=', $tag);
            }, 10, 2);
        }

        wp_enqueue_script(
            $handle,
            WSMA_URL . $js_file,
            [],
            file_exists($asset_js) ? (string) filemtime($asset_js) : WSMA_VERSION,
            true
        );

        if (file_exists($asset_css)) {
            wp_enqueue_style(
                $handle,
                WSMA_URL . $css_file,
                [],
                (string) filemtime($asset_css)
            );
        }

        $config = array_merge([
            'restUrl'       => esc_url_raw(rest_url('workshop-suite/v1/')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'brandName'     => WSMA_Settings::get('site_brand_name', 'WSMaker'),
        ], $extra_config);
        wp_localize_script($handle, 'WSMA_CONFIG', $config);
    }

    private function render_panel_wrapper(string $app_id, string $handle, string $js_file, string $css_file, array $extra_config = []): void {
        if (!current_user_can('manage_options')) return;

        $this->enqueue_panel_assets($handle, $js_file, $css_file, $extra_config);
        ?>
        <div class="wrap ws-admin-wrap">
            <div id="<?php echo esc_attr($app_id); ?>"></div>
        </div>
        <?php
    }

    public function render_dashboard(): void {
        $this->render_panel_wrapper('ws-riepilogo-app', 'ws-riepilogo', 'assets/dist/riepilogo.js', 'assets/dist/riepilogo.css');
    }

    public function render_riepilogo(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sets a default initial route for the Vue app, no form data is processed.
        if (!isset($_GET['vista'])) {
            $_GET['vista'] = 'categorie';
        }
        $this->render_panel_wrapper('ws-admin-app', 'ws-admin', 'assets/dist/admin.js', 'assets/dist/admin.css');
    }

    /**
     * "Aula Virtuale" — the exact same Eventi/Partecipanti tab as
     * workshop-suite-eventi, just pre-filtered to Modalità = virtuale. Not
     * a separate panel: a live workshop is still just an evento with a
     * date and capacity, so it reuses booking/reminder/calendar as-is
     * instead of a parallel system.
     */
    public function render_aula_virtuale(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sets a default initial route for the Vue app, no form data is processed.
        if (!isset($_GET['vista'])) {
            $_GET['vista'] = 'eventi';
        }
        $this->render_panel_wrapper('ws-admin-app', 'ws-admin', 'assets/dist/admin.js', 'assets/dist/admin.css', ['panelMode' => 'virtuale']);
    }

    public function render_eventi_partecipanti(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sets a default initial route for the Vue app, no form data is processed.
        if (!isset($_GET['vista'])) {
            $_GET['vista'] = 'eventi';
        }
        $this->render_panel_wrapper('ws-admin-app', 'ws-admin', 'assets/dist/admin.js', 'assets/dist/admin.css');
    }

    public function render_locandine(): void {
        $this->render_panel_wrapper('ws-locandine-app', 'ws-locandine', 'assets/dist/locandine.js', 'assets/dist/locandine.css');
    }

    /**
     * The list (partecipanti-lista.js) and the single-record detail card
     * (partecipante.js) are two separate bundles — on the legacy
     * shortcode-embedded page ("Admin Contatti") both were placed on the
     * same page, and clicking a name in the list just reloads the same
     * URL with ?pid=X#wvpx-scheda, which the detail bundle reads itself
     * and scrolls to. Mount both here too so the wp-admin menu page has
     * the same click-to-open-scheda behavior as that original page.
     */
    public function render_partecipanti(): void {
        if (!current_user_can('manage_options')) return;

        $this->enqueue_panel_assets('ws-partecipante', 'assets/dist/partecipante.js', 'assets/dist/partecipante.css');
        $this->enqueue_panel_assets('ws-partecipanti-lista', 'assets/dist/partecipanti-lista.js', 'assets/dist/partecipanti-lista.css');
        ?>
        <div class="wrap ws-admin-wrap">
            <div id="ws-partecipante-app"></div>
            <div id="ws-partecipanti-lista-app"></div>
        </div>
        <?php
    }

    public function render_messaggi(): void {
        $this->render_panel_wrapper('ws-messaggi-app', 'ws-messaggi', 'assets/dist/messaggi.js', 'assets/dist/messaggi.css');
    }

    public function render_calendario(): void {
        $this->render_panel_wrapper('ws-calendario-app', 'ws-calendario', 'assets/dist/calendario.js', 'assets/dist/calendario.css');
    }

    public function render_archivio(): void {
        $this->render_panel_wrapper('ws-archivio-app', 'ws-archivio', 'assets/dist/archivio.js', 'assets/dist/archivio.css');
    }

    public function render_event_types_panel(): void {
        if (!current_user_can('manage_options')) return;
        $settings = WSMA_Settings::get_all();
        $default_theme = WSMA_Settings::get('default_theme_mode', 'dark');
        ?>
        <div class="wrap ws-theme-wrapper ws-theme-<?php echo esc_attr($default_theme); ?> ws-dashboard-wrapper" id="ws-dashboard-wrapper">
            
            <div class="ws-theme-switch-bar">
                <span class="ws-s1"><?php esc_html_e('Tema:', 'wsmaker'); ?></span>
                <button type="button" class="ws-theme-btn <?php echo esc_attr($default_theme === 'dark' ? 'active' : ''); ?>" id="btn-theme-dark" onclick="fvwSetTheme('dark')">🌙 Dark</button>
                <button type="button" class="ws-theme-btn <?php echo esc_attr($default_theme === 'light' ? 'active' : ''); ?>" id="btn-theme-light" onclick="fvwSetTheme('light')">☀️ Light</button>
            </div>

            <div class="ws-s2">
                <h2 class="ws-s3"><?php esc_html_e('🏷️ Tipi di Evento', 'wsmaker'); ?></h2>
                <p class="ws-s4"><?php esc_html_e('Gestisci la lista dei tipi di evento predefiniti (es. Workshop, Viaggio Fotografico, Masterclass). Puoi modificarli o aggiungerne di nuovi.', 'wsmaker'); ?></p>

                <form method="post" action="options.php">
                    <?php settings_fields('wsma_settings_group'); ?>
                    
                    <input type="hidden" name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[site_brand_name]" value="<?php echo esc_attr($settings['site_brand_name']); ?>">
                    <input type="hidden" name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[sender_name]" value="<?php echo esc_attr($settings['sender_name']); ?>">
                    <input type="hidden" name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[sender_email]" value="<?php echo esc_attr($settings['sender_email']); ?>">
                    <input type="hidden" name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[currency_symbol]" value="<?php echo esc_attr($settings['currency_symbol']); ?>">
                    <input type="hidden" name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[default_theme_mode]" value="<?php echo esc_attr($settings['default_theme_mode']); ?>">
                    <input type="hidden" name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[enable_t15_reminders]" value="<?php echo esc_attr($settings['enable_t15_reminders']); ?>">

                    <div class="ws-s5" id="ws-event-types-container">
                        <?php 
                        $types = $settings['event_types'] ?? ['Workshop', 'Viaggio Fotografico', 'Masterclass'];
                        foreach ($types as $t) : 
                        ?>
                            <div class="ws-type-row ws-s6">
                                <input name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[event_types][]" type="text" value="<?php echo esc_attr($t); ?>" class="regular-text ws-flex-input" placeholder="Es. Workshop">
                                <button type="button" class="button button-secondary ws-s7" onclick="this.parentNode.remove()">✕ Rimuovi</button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="ws-s8">
                        <button type="button" class="button button-secondary" onclick="fvwAddEventTypeRow()">+ <?php esc_html_e('Aggiungi Tipo Evento', 'wsmaker'); ?></button>
                        <?php submit_button(__('Salva Tipi Evento', 'wsmaker'), 'primary', 'submit', false); ?>
                    </div>
                </form>
            </div>
        </div>

        <?php
        WSMA_Data::enqueue_inline_script(
            'function fvwAddEventTypeRow() {'
            . 'var container = document.getElementById("ws-event-types-container");'
            . 'var row = document.createElement("div");'
            . 'row.className = "ws-type-row";'
            . 'row.style.cssText = "display:flex;gap:10px;align-items:center;";'
            . 'row.innerHTML = \'<input name="' . esc_js(WSMA_Settings::OPTION_KEY) . '[event_types][]" type="text" value="" class="regular-text ws-flex-input" placeholder="Es. Corso Online">\' +'
            . '\'<button type="button" class="button button-secondary ws-s7" onclick="this.parentNode.remove()">✕ Rimuovi</button>\';'
            . 'container.appendChild(row);'
            . '}'
            . 'function fvwSetTheme(theme) {'
            . 'var wrapper = document.getElementById("ws-dashboard-wrapper");'
            . 'var btnDark = document.getElementById("btn-theme-dark");'
            . 'var btnLight = document.getElementById("btn-theme-light");'
            . 'if (theme === "light") {'
            . 'wrapper.classList.remove("ws-theme-dark"); wrapper.classList.add("ws-theme-light");'
            . 'if (btnLight) btnLight.classList.add("active"); if (btnDark) btnDark.classList.remove("active");'
            . '} else {'
            . 'wrapper.classList.remove("ws-theme-light"); wrapper.classList.add("ws-theme-dark");'
            . 'if (btnDark) btnDark.classList.add("active"); if (btnLight) btnLight.classList.remove("active");'
            . '}'
            . 'localStorage.setItem("ws_user_theme", theme);'
            . '}'
            . '(function() {'
            . 'var saved = localStorage.getItem("ws_user_theme");'
            . 'if (saved === "light" || saved === "dark") { fvwSetTheme(saved); }'
            . '})();'
        );
        ?>
        <?php
    }

    public function register_settings(): void {
        register_setting('wsma_settings_group', WSMA_Settings::OPTION_KEY, [
            'sanitize_callback' => function ($input) {
                if (!is_array($input)) return [];
                $current = WSMA_Settings::get_all();
                $current['site_brand_name']      = sanitize_text_field($input['site_brand_name'] ?? '');
                $current['sender_name']          = sanitize_text_field($input['sender_name'] ?? '');
                $current['sender_email']         = sanitize_email($input['sender_email'] ?? '');
                $current['currency_symbol']      = sanitize_text_field($input['currency_symbol'] ?? '€');
                $current['enable_t15_reminders'] = !empty($input['enable_t15_reminders']) ? 1 : 0;
                $current['default_theme_mode']   = in_array($input['default_theme_mode'] ?? '', ['dark', 'light'], true) ? $input['default_theme_mode'] : 'dark';
                return $current;
            }
        ]);
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) return;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab-selection param for the settings page UI, no form data is processed.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';

        if ($tab === 'proponente') {
            wp_enqueue_media();
        }

        $asset_css = WSMA_PATH . 'assets/dist/admin.css';
        if (file_exists($asset_css)) {
            wp_enqueue_style('ws-admin-settings-css', WSMA_URL . 'assets/dist/admin.css', [], (string) filemtime($asset_css));
        }

        $settings = WSMA_Settings::get_all();
        $license  = WSMA_License_Manager::get_license_data();
        ?>
        <div class="wrap ws-admin-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('WSMaker Settings', 'wsmaker'); ?></h1>
            <hr class="wp-header-end">
            
            <nav class="nav-tab-wrapper wp-clearfix ws-s9">
                <a href="?page=workshop-suite-settings&tab=general" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General', 'wsmaker'); ?></a>
                <a href="?page=workshop-suite-settings&tab=modules" class="nav-tab <?php echo $tab === 'modules' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Modules & Add-ons', 'wsmaker'); ?></a>
                <a href="?page=workshop-suite-settings&tab=proponente" class="nav-tab <?php echo $tab === 'proponente' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Trainer Profile / Bio', 'wsmaker'); ?></a>
                <a href="?page=workshop-suite-settings&tab=mail" class="nav-tab <?php echo $tab === 'mail' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Mail Configuration', 'wsmaker'); ?></a>
                <a href="?page=workshop-suite-settings&tab=shortcodes" class="nav-tab <?php echo $tab === 'shortcodes' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Shortcodes', 'wsmaker'); ?></a>
                <a href="?page=workshop-suite-settings&tab=license" class="nav-tab <?php echo $tab === 'license' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('License', 'wsmaker'); ?></a>
            </nav>

            <?php if ($tab === 'general') : ?>
                <form method="post" action="options.php">
                    <?php settings_fields('wsma_settings_group'); ?>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="site_brand_name"><?php esc_html_e('Nome Brand / Organizzazione', 'wsmaker'); ?></label>
                                </th>
                                <td>
                                    <input name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[site_brand_name]" type="text" id="site_brand_name" value="<?php echo esc_attr($settings['site_brand_name']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Usato nelle firme email automatiche e nei calendari ICS.', 'wsmaker'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sender_name"><?php esc_html_e('Nome Mittente Email', 'wsmaker'); ?></label>
                                </th>
                                <td>
                                    <input name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[sender_name]" type="text" id="sender_name" value="<?php echo esc_attr($settings['sender_name']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Nome visualizzato dal destinatario come mittente delle notifiche.', 'wsmaker'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sender_email"><?php esc_html_e('Email Organizzazione', 'wsmaker'); ?></label>
                                </th>
                                <td>
                                    <input name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[sender_email]" type="email" id="sender_email" value="<?php echo esc_attr($settings['sender_email']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Indirizzo da cui partiranno le mail di risposta/promemoria, ed a cui arriveranno gli avvisi di nuove richieste dal form di iscrizione.', 'wsmaker'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="currency_symbol"><?php esc_html_e('Simbolo Valuta', 'wsmaker'); ?></label>
                                </th>
                                <td>
                                    <input name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[currency_symbol]" type="text" id="currency_symbol" value="<?php echo esc_attr($settings['currency_symbol']); ?>" class="small-text">
                                    <p class="description"><?php esc_html_e('Simbolo valuta mostrato nei form di iscrizione (es. €).', 'wsmaker'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="default_theme_mode"><?php esc_html_e('Tema Frontend Predefinito', 'wsmaker'); ?></label>
                                </th>
                                <td>
                                    <select name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[default_theme_mode]" id="default_theme_mode">
                                        <option value="dark" <?php selected('dark', $settings['default_theme_mode']); ?>><?php esc_html_e('Tema Dark (Scuro)', 'wsmaker'); ?></option>
                                        <option value="light" <?php selected('light', $settings['default_theme_mode']); ?>><?php esc_html_e('Tema Light (Chiaro)', 'wsmaker'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Stile grafico applicato agli shortcode e ai moduli pubblici nel frontend del sito.', 'wsmaker'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Automazioni Promemoria', 'wsmaker'); ?></th>
                                <td>
                                    <fieldset>
                                        <label for="enable_t15_reminders">
                                            <input name="<?php echo esc_attr(WSMA_Settings::OPTION_KEY); ?>[enable_t15_reminders]" type="checkbox" id="enable_t15_reminders" value="1" <?php checked(1, $settings['enable_t15_reminders']); ?>>
                                            <?php esc_html_e('Abilita invio automatico promemoria a 15 giorni dall\'evento (T-15)', 'wsmaker'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Invia in automatico una mail riassuntiva ai partecipanti confermati 15 giorni prima della data inizio.', 'wsmaker'); ?></p>
                                    </fieldset>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button(__('Salva Impostazioni', 'wsmaker')); ?>
                </form>
            <?php elseif ($tab === 'modules') : 
                $is_pro_active = defined('WS_PRO_VERSION');
                $active_mods = (array) ($settings['active_modules'] ?? []);
                $available_modules = [
                    'global_hub_pro' => [
                        'icon'        => '🌐',
                        'title'       => __('Woorkshoop Global Hub & World Map Sync', 'wsmaker'),
                        'desc'        => __('Sincronizza in automatico i tuoi workshop ed eventi sulla directory globale woorkshoop.space / wsmaker.pro e sulla mappa interattiva mondiale.', 'wsmaker'),
                        'badge'       => 'GLOBAL HUB',
                        'is_pro'      => false,
                        'default'     => 1,
                    ],
                    'courses_academy' => [
                        'icon'        => '🎬',
                        'title'       => __('Workshop Online & LMS', 'wsmaker'),
                        'desc'        => __('Piattaforma videocorsi on-demand, masterclass registrate, gestione moduli/lezioni con player video avanzato e streaming Zoom/Meet.', 'wsmaker'),
                        'badge'       => 'LMS',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'voucher_pdf' => [
                        'icon'        => '🎟️',
                        'title'       => __('Voucher di Partecipazione & PDF Pass', 'wsmaker'),
                        'desc'        => __('Genera e invia in automatico all\'email di conferma il Voucher/Pass PDF personalizzato con QR-code e dettagli logistici per il corsista.', 'wsmaker'),
                        'badge'       => 'CONFIRMATION',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'ai_assistant' => [
                        'icon'        => '🤖',
                        'title'       => __('AI Workshop Assistant & Copywriter', 'wsmaker'),
                        'desc'        => __('Assistente AI per generare descrizioni accattivanti dei corsi, programmi didattici, testi per i post di Instagram e risposte email agli allievi.', 'wsmaker'),
                        'badge'       => 'AI ENGINE',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'stripe_payments' => [
                        'icon'        => '💳',
                        'title'       => __('Pagamenti Nativi Stripe & Apple Pay', 'wsmaker'),
                        'desc'        => __('Incasso istantaneo di anticipi o saldi tramite carta di credito, Apple Pay e Google Pay con zero plugin intermedi.', 'wsmaker'),
                        'badge'       => 'PAYMENTS',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'calendar_sync' => [
                        'icon'        => '📅',
                        'title'       => __('Google Calendar & Apple iCal Auto-Sync', 'wsmaker'),
                        'desc'        => __('Feed ICS dinamico che sincronizza in tempo reale le date dei workshop sul calendario personale del docente e dei partecipanti.', 'wsmaker'),
                        'badge'       => 'CALENDAR',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'webhooks' => [
                        'icon'        => '🔌',
                        'title'       => __('Webhook & Connettori Zapier / Make', 'wsmaker'),
                        'desc'        => __('Invia payload JSON in tempo reale a Make.com, Zapier, Zoho CRM o Google Sheets ad ogni nuova iscrizione o conferma pagamento.', 'wsmaker'),
                        'badge'       => 'INTEGRATION',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'poster_studio' => [
                        'icon'        => '🎨',
                        'title'       => __('Poster Studio & Social Banner Builder', 'wsmaker'),
                        'desc'        => __('Generatore grafico in-browser di locandine pronte per Instagram Feed (1:1), Stories (9:16) e Facebook con rendering HTML5 Canvas.', 'wsmaker'),
                        'badge'       => 'GRAPHIC',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'analytics' => [
                        'icon'        => '📊',
                        'title'       => __('Statistiche & Analytics', 'wsmaker'),
                        'desc'        => __('Estende il pannello Riepilogo con incassi (30 giorni e totali) suddivisi per categoria, e — se il modulo Marketing è attivo — coupon emessi/utilizzati e sconto concesso.', 'wsmaker'),
                        'badge'       => 'ANALYTICS',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'marketing' => [
                        'icon'        => '🎁',
                        'title'       => __('Marketing: Fidelity & Coupon', 'wsmaker'),
                        'desc'        => __('Coupon manuali per offerte speciali (Earlybird ecc.) e programma fedeltà automatico: chi partecipa a più eventi/corsi negli ultimi 12 mesi riceve uno sconto crescente. Funziona sia con i pagamenti Stripe nativi che con WooCommerce.', 'wsmaker'),
                        'badge'       => 'MARKETING',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'fluentcrm' => [
                        'icon'        => '📇',
                        'title'       => __('Connettore FluentCRM', 'wsmaker'),
                        'desc'        => __('Aggiunge automaticamente ogni nuova richiesta dal form di iscrizione come contatto FluentCRM, con tag e/o lista configurabili.', 'wsmaker'),
                        'badge'       => 'CRM',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'woocommerce' => [
                        'icon'        => '🛒',
                        'title'       => __('Integrazione Carrello WooCommerce', 'wsmaker'),
                        'desc'        => __('Sincronizza i workshop come prodotti nel carrello WooCommerce per utilizzare i tuoi gateway e la fatturazione elettronica.', 'wsmaker'),
                        'badge'       => 'ECOMMERCE',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'academy' => [
                        'icon'        => '🏫',
                        'title'       => __('Academy: Multi-Docente & Ruoli', 'wsmaker'),
                        'desc'        => __('Account Docente/Manager con permessi separati, categorie e corsi assegnati per docente, con isolamento dati completo tra docenti.', 'wsmaker'),
                        'badge'       => 'ACADEMY',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                ];
            ?>
                <?php if (!$is_pro_active) : ?>
                    <div class="ws-s10">
                        <div>
                            <span class="ws-s11">WSMaker Free</span>
                            <h3 class="ws-s12">Sblocca il Potere Completo con WSMaker PRO</h3>
                            <p class="ws-s13">Installa l'Add-on PRO per sbloccare la Piattaforma Corsi Didattici, Live Streaming Zoom, Pagamenti Stripe, AI Copywriter e Voucher PDF.</p>
                        </div>
                        <a href="https://wsmaker.pro/#pricing" target="_blank" class="button button-primary ws-s14">
                            Sblocca Tutti i Moduli PRO (99€) →
                        </a>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php wp_nonce_field('wsma_save_modules_nonce'); ?>
                    <input type="hidden" name="ws_save_modules_action" value="1">

                    <div class="ws-s15">
                        <?php foreach ($available_modules as $mod_key => $mod_data) : 
                            $is_locked = ($mod_data['is_pro'] && !$is_pro_active);
                            $is_active = $is_locked ? false : (isset($active_mods[$mod_key]) ? !empty($active_mods[$mod_key]) : $mod_data['default']);
                        ?>
                            <div class="ws-card-native ws-mod-card <?php echo esc_attr($is_locked ? 'ws-mod-card--locked' : ($is_active ? 'ws-mod-card--active' : 'ws-mod-card--inactive')); ?><?php echo esc_attr($mod_data['is_pro'] ? ' ws-mod-card--pro' : ''); ?><?php echo esc_attr($mod_key === 'academy' ? ' ws-mod-card--academy' : ''); ?>">
                                <div>
                                    <div class="ws-s16">
                                        <div class="ws-s17">
                                            <span class="ws-s18"><?php echo esc_html($mod_data['icon']); ?></span>
                                            <?php if ($mod_data['is_pro']): ?>
                                                <span class="ws-pro-badge <?php echo esc_attr($is_pro_active ? 'ws-pro-badge--unlocked' : 'ws-pro-badge--locked'); ?>">
                                                    <?php if ($is_pro_active): ?>
                                                        <?php echo esc_html($mod_key === 'academy' ? '✓ PRO & ACADEMY UNLOCKED' : '✓ PRO UNLOCKED'); ?>
                                                    <?php else: ?>
                                                        🔒 PRO REQUIRED
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="ws-s19">
                                                    <?php echo esc_html($mod_data['badge']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Toggle Switch -->
                                        <?php if ($is_locked): ?>
                                            <a class="ws-s20" href="https://wsmaker.pro/#pricing" target="_blank">
                                                🔒 Sblocca PRO
                                            </a>
                                        <?php else: ?>
                                            <label class="ws-toggle-switch">
                                                <input type="checkbox" name="ws_modules[<?php echo esc_attr($mod_key); ?>]" value="1" <?php checked(true, $is_active); ?> onchange="wsToggleModuleLive(this, '<?php echo esc_attr($mod_key); ?>')">
                                                <span class="ws-toggle-slider"></span>
                                            </label>
                                        <?php endif; ?>
                                    </div>

                                    <h3 class="ws-mod-title<?php echo esc_attr($is_locked ? ' ws-mod-title--locked' : ''); ?>">
                                        <?php echo esc_html($mod_data['title']); ?>
                                    </h3>
                                    <p class="ws-s21">
                                        <?php echo esc_html($mod_data['desc']); ?>
                                    </p>
                                </div>

                                <div class="ws-s22">
                                    <span class="ws-mod-status-label ws-mod-status <?php echo esc_attr($is_locked ? 'ws-mod-status--locked' : ($is_active ? 'ws-mod-status--active' : 'ws-mod-status--inactive')); ?>">
                                        ● <?php echo $is_locked ? esc_html__('Richiede PRO', 'wsmaker') : ($is_active ? esc_html__('Attivo', 'wsmaker') : esc_html__('Disattivato', 'wsmaker')); ?>
                                    </span>
                                    <span class="ws-s23">
                                        <code>ws-mod-<?php echo esc_html($mod_key); ?></code>
                                    </span>
                                    <?php if ($mod_key === 'fluentcrm' && !$is_locked): ?>
                                        <button type="button" class="button button-small ws-fluentcrm-configure-btn" style="margin-left:auto;<?php echo $is_active ? '' : 'display:none;'; ?>" onclick="wsOpenFluentCrmModal()">⚙️ <?php esc_html_e('Configura', 'wsmaker'); ?></button>
                                    <?php endif; ?>
                                    <?php if ($mod_key === 'stripe_payments' && !$is_locked): ?>
                                        <button type="button" class="button button-small ws-stripe-configure-btn" style="margin-left:auto;<?php echo $is_active ? '' : 'display:none;'; ?>" onclick="wsOpenStripeModal()">⚙️ <?php esc_html_e('Configura', 'wsmaker'); ?></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- FluentCRM config modal -->
                    <div id="ws-fluentcrm-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:100000;align-items:center;justify-content:center;">
                        <div style="background:#fff;border-radius:12px;width:100%;max-width:460px;padding:24px 26px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                <h2 style="margin:0;font-size:18px;">📇 <?php esc_html_e('Connettore FluentCRM', 'wsmaker'); ?></h2>
                                <span id="ws-fluentcrm-detected-badge" style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;"></span>
                            </div>
                            <p style="font-size:13px;color:#64748b;margin-top:4px;">
                                <?php esc_html_e('Ogni nuova richiesta dal form di iscrizione viene aggiunta come contatto, con il tag e/o la lista qui sotto (creati automaticamente se non esistono).', 'wsmaker'); ?>
                            </p>

                            <div style="margin:18px 0 14px;position:relative;">
                                <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Tag</label>
                                <input type="text" id="ws-fluentcrm-tag" autocomplete="off" placeholder="WSMaker" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;">
                                <div id="ws-fluentcrm-tag-dropdown" class="ws-combo-dropdown"></div>
                                <p class="description" style="margin:4px 0 0;">Lascia vuoto per non applicare nessun tag.</p>
                            </div>
                            <div style="position:relative;">
                                <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Lista</label>
                                <input type="text" id="ws-fluentcrm-list" autocomplete="off" placeholder="(opzionale)" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;">
                                <div id="ws-fluentcrm-list-dropdown" class="ws-combo-dropdown"></div>
                                <p class="description" style="margin:4px 0 0;">Lascia vuoto per non aggiungere a nessuna lista.</p>
                            </div>

                            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:22px;">
                                <button type="button" class="button" onclick="wsCloseFluentCrmModal()"><?php esc_html_e('Annulla', 'wsmaker'); ?></button>
                                <button type="button" class="button button-primary" onclick="wsSaveFluentCrmModal()">💾 <?php esc_html_e('Salva', 'wsmaker'); ?></button>
                            </div>
                            <p id="ws-fluentcrm-modal-msg" style="font-size:12px;margin:10px 0 0;"></p>
                        </div>
                    </div>

                    <!-- Stripe config modal -->
                    <div id="ws-stripe-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:100000;align-items:center;justify-content:center;overflow-y:auto;padding:30px 0;">
                        <div style="background:#fff;border-radius:12px;width:100%;max-width:520px;padding:24px 26px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
                            <h2 style="margin:0 0 6px;font-size:18px;">💳 <?php esc_html_e('Stripe', 'wsmaker'); ?></h2>
                            <p style="font-size:13px;color:#64748b;margin-top:4px;">
                                <?php esc_html_e('Pagamento acconto/saldo workshop tramite Stripe Checkout (pagina ospitata da Stripe — Apple Pay/Google Pay compaiono automaticamente quando supportati).', 'wsmaker'); ?>
                            </p>

                            <div style="margin:16px 0;">
                                <label style="display:block;font-weight:600;margin-bottom:8px;font-size:13px;">Modalità</label>
                                <label style="margin-right:16px;font-size:13px;"><input type="radio" name="ws-stripe-mode" id="ws-stripe-mode-test" value="test"> Test</label>
                                <label style="font-size:13px;"><input type="radio" name="ws-stripe-mode" id="ws-stripe-mode-live" value="live"> Live (pagamenti reali)</label>
                            </div>

                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin-bottom:12px;">
                                <h4 style="margin:0 0 10px;font-size:13px;">Chiavi Test</h4>
                                <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Publishable key</label>
                                <input type="text" id="ws-stripe-test-pk" placeholder="pk_test_..." style="width:100%;padding:7px 9px;border:1px solid #cbd5e1;border-radius:6px;margin-bottom:10px;">
                                <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Secret key</label>
                                <input type="password" id="ws-stripe-test-sk" placeholder="sk_test_..." style="width:100%;padding:7px 9px;border:1px solid #cbd5e1;border-radius:6px;" autocomplete="off">
                                <label style="font-size:12px;color:#64748b;display:block;margin-top:4px;" id="ws-stripe-test-sk-clear-wrap"><input type="checkbox" id="ws-stripe-test-sk-clear"> Rimuovi</label>
                            </div>

                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin-bottom:12px;">
                                <h4 style="margin:0 0 10px;font-size:13px;">Chiavi Live</h4>
                                <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Publishable key</label>
                                <input type="text" id="ws-stripe-live-pk" placeholder="pk_live_..." style="width:100%;padding:7px 9px;border:1px solid #cbd5e1;border-radius:6px;margin-bottom:10px;">
                                <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Secret key</label>
                                <input type="password" id="ws-stripe-live-sk" placeholder="sk_live_..." style="width:100%;padding:7px 9px;border:1px solid #cbd5e1;border-radius:6px;" autocomplete="off">
                                <label style="font-size:12px;color:#64748b;display:block;margin-top:4px;" id="ws-stripe-live-sk-clear-wrap"><input type="checkbox" id="ws-stripe-live-sk-clear"> Rimuovi</label>
                            </div>

                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin-bottom:16px;">
                                <h4 style="margin:0 0 8px;font-size:13px;">Webhook</h4>
                                <p style="font-size:12px;color:#64748b;margin:0 0 8px;">Su Stripe → Sviluppatori → Webhook, crea un endpoint verso questo URL, evento <code>checkout.session.completed</code>:</p>
                                <div id="ws-stripe-webhook-url" style="background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:6px 10px;font-family:monospace;font-size:12px;margin-bottom:10px;word-break:break-all;"></div>
                                <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px;">Signing secret</label>
                                <input type="password" id="ws-stripe-webhook-secret" placeholder="whsec_..." style="width:100%;padding:7px 9px;border:1px solid #cbd5e1;border-radius:6px;" autocomplete="off">
                                <label style="font-size:12px;color:#64748b;display:block;margin-top:4px;" id="ws-stripe-webhook-secret-clear-wrap"><input type="checkbox" id="ws-stripe-webhook-secret-clear"> Rimuovi</label>
                            </div>

                            <div style="display:flex;justify-content:flex-end;gap:8px;">
                                <button type="button" class="button" onclick="wsCloseStripeModal()"><?php esc_html_e('Annulla', 'wsmaker'); ?></button>
                                <button type="button" class="button button-primary" onclick="wsSaveStripeModal()">💾 <?php esc_html_e('Salva', 'wsmaker'); ?></button>
                            </div>
                            <p id="ws-stripe-modal-msg" style="font-size:12px;margin:10px 0 0;"></p>
                        </div>
                    </div>

                    <div class="ws-card-native" style="margin-top: 24px; padding: 20px; border-left: 4px solid #00d2b4; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        <div>
                            <h4 style="margin: 0 0 6px 0; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                                <span>🌐</span> <?php esc_html_e('Sincronizzazione Manuale Global Hub & Mappa Mondiale', 'wsmaker'); ?>
                            </h4>
                            <p style="margin: 0; font-size: 13px; color: #64748b;">
                                <?php esc_html_e('Invia in un colpo solo tutti i tuoi workshop ed eventi pubblicati alla directory wsmaker.pro e alla mappa interattiva.', 'wsmaker'); ?>
                            </p>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span id="ws-sync-hub-msg" style="font-size: 13px; font-weight: 600;"></span>
                            <button type="button" class="button button-primary" id="btn-sync-hub-now" onclick="wsSyncHubNow(this)" style="background: linear-gradient(135deg, #0088ff, #00d2b4); border-color: transparent; color: #fff; font-weight: 600; padding: 4px 14px; height: 34px;">
                                🚀 <?php esc_html_e('Sincronizza tutti i Workshop con l\'Hub adesso', 'wsmaker'); ?>
                            </button>
                        </div>
                    </div>

                    <?php ob_start(); ?>
                    function wsToggleModuleLive(checkbox, modKey) {
                        const card = checkbox.closest('.ws-card-native');
                        const statusLabel = card.querySelector('.ws-mod-status-label');
                        const isActive = checkbox.checked;

                        card.style.borderColor = isActive ? '#2271b1' : '#cbd5e1';
                        if (statusLabel) {
                            statusLabel.textContent = isActive ? '● Attivo' : '● Disattivato';
                            statusLabel.style.color = isActive ? '#10b981' : '#94a3b8';
                        }

                        card.querySelectorAll('[class*="-configure-btn"]').forEach(btn => {
                            btn.style.display = isActive ? '' : 'none';
                        });

                        fetch('<?php echo esc_url(rest_url('workshop-suite/v1/modules/toggle')); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                            },
                            body: JSON.stringify({
                                module: modKey,
                                active: isActive
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            console.log('WS Module Toggled:', data);
                        })
                        .catch(err => console.error('Error toggling module:', err));
                    }

                    function wsComboSetup(input, dropdown, getOptions) {
                        function escapeHtml(s) {
                            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                        }
                        function renderList(opts) {
                            if (!opts.length) {
                                dropdown.innerHTML = '<div class="ws-combo-empty">Nessun suggerimento — puoi digitarne uno nuovo</div>';
                            } else {
                                dropdown.innerHTML = opts.map(o => `<div class="ws-combo-item">${escapeHtml(o)}</div>`).join('');
                            }
                            dropdown.style.display = 'block';
                        }
                        function renderAll() {
                            renderList(getOptions());
                        }
                        function renderFiltered() {
                            const q = input.value.trim().toLowerCase();
                            renderList(getOptions().filter(o => !q || o.toLowerCase().includes(q)));
                        }
                        input.addEventListener('focus', renderAll);
                        input.addEventListener('click', renderAll);
                        input.addEventListener('input', renderFiltered);
                        dropdown.addEventListener('mousedown', (e) => {
                            const item = e.target.closest('.ws-combo-item');
                            if (!item) return;
                            e.preventDefault();
                            input.value = item.textContent;
                            dropdown.style.display = 'none';
                        });
                        document.addEventListener('click', (e) => {
                            if (e.target !== input && !dropdown.contains(e.target)) {
                                dropdown.style.display = 'none';
                            }
                        });
                    }

                    let wsFluentCrmTagOptions = [];
                    let wsFluentCrmListOptions = [];
                    let wsFluentCrmComboReady = false;

                    function wsOpenFluentCrmModal() {
                        const overlay = document.getElementById('ws-fluentcrm-modal-overlay');
                        const msg = document.getElementById('ws-fluentcrm-modal-msg');
                        msg.textContent = '';
                        overlay.style.display = 'flex';

                        if (!wsFluentCrmComboReady) {
                            wsComboSetup(
                                document.getElementById('ws-fluentcrm-tag'),
                                document.getElementById('ws-fluentcrm-tag-dropdown'),
                                () => wsFluentCrmTagOptions
                            );
                            wsComboSetup(
                                document.getElementById('ws-fluentcrm-list'),
                                document.getElementById('ws-fluentcrm-list-dropdown'),
                                () => wsFluentCrmListOptions
                            );
                            wsFluentCrmComboReady = true;
                        }

                        fetch('<?php echo esc_url(rest_url('workshop-suite/v1/fluentcrm/options')); ?>', {
                            headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' }
                        })
                        .then(res => res.json())
                        .then(data => {
                            const badge = document.getElementById('ws-fluentcrm-detected-badge');
                            if (data.detected) {
                                badge.textContent = '● Rilevato';
                                badge.style.background = '#ecfdf5';
                                badge.style.color = '#065f46';
                            } else {
                                badge.textContent = '○ Non rilevato';
                                badge.style.background = '#f1f5f9';
                                badge.style.color = '#64748b';
                            }

                            document.getElementById('ws-fluentcrm-tag').value = data.settings.tag || '';
                            document.getElementById('ws-fluentcrm-list').value = data.settings.list || '';
                            wsFluentCrmTagOptions = data.tags || [];
                            wsFluentCrmListOptions = data.lists || [];
                        })
                        .catch(() => { msg.textContent = 'Errore nel caricamento.'; msg.style.color = '#ef4444'; });
                    }

                    function wsCloseFluentCrmModal() {
                        document.getElementById('ws-fluentcrm-modal-overlay').style.display = 'none';
                        document.getElementById('ws-fluentcrm-tag-dropdown').style.display = 'none';
                        document.getElementById('ws-fluentcrm-list-dropdown').style.display = 'none';
                    }

                    function wsSaveFluentCrmModal() {
                        const msg = document.getElementById('ws-fluentcrm-modal-msg');
                        msg.textContent = 'Salvataggio...';
                        msg.style.color = '#64748b';

                        fetch('<?php echo esc_url(rest_url('workshop-suite/v1/fluentcrm/save')); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                            },
                            body: JSON.stringify({
                                tag: document.getElementById('ws-fluentcrm-tag').value,
                                list: document.getElementById('ws-fluentcrm-list').value
                            })
                        })
                        .then(res => res.json())
                        .then(() => {
                            msg.textContent = '✅ Salvato.';
                            msg.style.color = '#10b981';
                            setTimeout(wsCloseFluentCrmModal, 700);
                        })
                        .catch(() => { msg.textContent = 'Errore di rete.'; msg.style.color = '#ef4444'; });
                    }

                    function wsOpenStripeModal() {
                        const overlay = document.getElementById('ws-stripe-modal-overlay');
                        const msg = document.getElementById('ws-stripe-modal-msg');
                        msg.textContent = '';
                        overlay.style.display = 'flex';

                        fetch('<?php echo esc_url(rest_url('workshop-suite/v1/stripe/options')); ?>', {
                            headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' }
                        })
                        .then(res => res.json())
                        .then(data => {
                            document.getElementById('ws-stripe-mode-' + (data.mode === 'live' ? 'live' : 'test')).checked = true;
                            document.getElementById('ws-stripe-test-pk').value = data.test_publishable_key || '';
                            document.getElementById('ws-stripe-live-pk').value = data.live_publishable_key || '';
                            document.getElementById('ws-stripe-webhook-url').textContent = data.webhook_url || '';

                            ['test-sk', 'live-sk', 'webhook-secret'].forEach((suffix, i) => {
                                const hasKey = [data.has_test_secret_key, data.has_live_secret_key, data.has_webhook_secret][i];
                                const input = document.getElementById('ws-stripe-' + suffix);
                                input.value = '';
                                input.placeholder = hasKey ? '•••••••••••••••• (già impostata)' : input.dataset.placeholder || input.placeholder;
                                document.getElementById('ws-stripe-' + suffix + '-clear-wrap').style.display = hasKey ? '' : 'none';
                                document.getElementById('ws-stripe-' + suffix + '-clear').checked = false;
                            });
                        })
                        .catch(() => { msg.textContent = 'Errore nel caricamento.'; msg.style.color = '#ef4444'; });
                    }

                    function wsCloseStripeModal() {
                        document.getElementById('ws-stripe-modal-overlay').style.display = 'none';
                    }

                    function wsSaveStripeModal() {
                        const msg = document.getElementById('ws-stripe-modal-msg');
                        msg.textContent = 'Salvataggio...';
                        msg.style.color = '#64748b';

                        fetch('<?php echo esc_url(rest_url('workshop-suite/v1/stripe/save')); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                            },
                            body: JSON.stringify({
                                mode: document.getElementById('ws-stripe-mode-live').checked ? 'live' : 'test',
                                test_publishable_key: document.getElementById('ws-stripe-test-pk').value,
                                live_publishable_key: document.getElementById('ws-stripe-live-pk').value,
                                test_secret_key: document.getElementById('ws-stripe-test-sk').value,
                                live_secret_key: document.getElementById('ws-stripe-live-sk').value,
                                webhook_secret: document.getElementById('ws-stripe-webhook-secret').value,
                                clear_test_secret_key: document.getElementById('ws-stripe-test-sk-clear').checked,
                                clear_live_secret_key: document.getElementById('ws-stripe-live-sk-clear').checked,
                                clear_webhook_secret: document.getElementById('ws-stripe-webhook-secret-clear').checked
                            })
                        })
                        .then(res => res.json())
                        .then(() => {
                            msg.textContent = '✅ Salvato.';
                            msg.style.color = '#10b981';
                            setTimeout(wsCloseStripeModal, 700);
                        })
                        .catch(() => { msg.textContent = 'Errore di rete.'; msg.style.color = '#ef4444'; });
                    }

                    function wsSyncHubNow(btn) {
                        const msgEl = document.getElementById('ws-sync-hub-msg');
                        btn.disabled = true;
                        btn.textContent = '⏳ Sincronizzazione in corso...';
                        if (msgEl) {
                            msgEl.textContent = '';
                            msgEl.style.color = '#2271b1';
                        }

                        fetch('<?php echo esc_url(rest_url('workshop-suite/v1/admin/sync-hub-now')); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            btn.disabled = false;
                            btn.textContent = '🚀 Sincronizza tutti i Workshop con l\'Hub adesso';
                            if (msgEl) {
                                msgEl.textContent = data.msg || 'Sincronizzazione completata!';
                                msgEl.style.color = data.success ? '#10b981' : '#ef4444';
                            }
                        })
                        .catch(err => {
                            btn.disabled = false;
                            btn.textContent = '🚀 Sincronizza tutti i Workshop con l\'Hub adesso';
                            if (msgEl) {
                                msgEl.textContent = 'Errore di rete.';
                                msgEl.style.color = '#ef4444';
                            }
                        });
                    }
                    <?php WSMA_Data::enqueue_inline_script(ob_get_clean()); ?>

                    <div class="ws-s24">
                        <?php submit_button(__('Salva Stato Moduli', 'wsmaker')); ?>
                    </div>
                </form>
            <?php elseif ($tab === 'proponente') : 
                $lingue_attive = (array) ($settings['proponente_lingue'] ?? ['Italiano']);
                $lingue_disponibili = [
                    'Italiano'   => '🇮🇹 Italiano',
                    'Inglese'    => '🇬🇧 English (Inglese)',
                    'Spagnolo'   => '🇪🇸 Español (Spagnolo)',
                    'Francese'   => '🇫🇷 Français (Francese)',
                    'Tedesco'    => '🇩🇪 Deutsch (Tedesco)',
                    'Portoghese' => '🇵🇹 Português (Portoghese)',
                ];
            ?>
                <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success-message flag after a redirect, no form data is processed. ?>
                <?php if (isset($_GET['updated']) && sanitize_text_field(wp_unslash($_GET['updated'])) === '1') : ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e('Profilo del proponente e scheda Bio salvati con successo.', 'wsmaker'); ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php wp_nonce_field('wsma_save_proponente_nonce'); ?>
                    <input type="hidden" name="ws_save_proponente_action" value="1">

                    <div class="ws-s25">
                        
                        <!-- Colonna 1: Bio & Dati Personali -->
                        <div class="ws-card-native">
                            <h2 class="ws-s26">
                                👤 <?php esc_html_e('Dati Docente / Proponente', 'wsmaker'); ?>
                            </h2>

                            <!-- Foto Profilo con Uploader WP Media -->
                            <div class="ws-s27">
                                <label class="ws-s28"><?php esc_html_e('Foto Profilo / Avatar', 'wsmaker'); ?></label>
                                <div class="ws-s29">
                                    <div class="ws-s30" id="ws-photo-preview-wrap">
                                        <?php if (!empty($settings['proponente_foto'])) : ?>
                                            <img src="<?php echo esc_url($settings['proponente_foto']); ?>" id="ws-photo-img" class="ws-photo-preview">
                                        <?php else : ?>
                                            <span class="ws-s31" id="ws-photo-placeholder">👤</span>
                                            <img class="ws-s32" src="" id="ws-photo-img">
                                        <?php endif; ?>
                                    </div>
                                    <div class="ws-s33">
                                        <input type="hidden" name="ws_proponente[proponente_foto]" id="ws_proponente_foto" value="<?php echo esc_attr($settings['proponente_foto']); ?>">
                                        <button type="button" class="button button-secondary" id="ws-upload-photo-btn"><?php esc_html_e('Carica / Scegli Foto', 'wsmaker'); ?></button>
                                        <button type="button" class="button button-link-delete ws-remove-photo-btn<?php echo empty($settings['proponente_foto']) ? ' ws-hidden' : ''; ?>" id="ws-remove-photo-btn"><?php esc_html_e('Rimuovi', 'wsmaker'); ?></button>
                                        <p class="description ws-s34"><?php esc_html_e('Consigliata immagine quadrata ad alta risoluzione (min. 400x400 px).', 'wsmaker'); ?></p>
                                    </div>
                                </div>
                            </div>

                            <table class="form-table ws-s35" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="prop_nome"><?php esc_html_e('Nome & Cognome', 'wsmaker'); ?></label></th>
                                        <td>
                                            <input name="ws_proponente[proponente_nome]" type="text" id="prop_nome" value="<?php echo esc_attr($settings['proponente_nome']); ?>" class="regular-text" placeholder="Es. Francesco Verolino">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="prop_ruolo"><?php esc_html_e('Titolo / Ruolo', 'wsmaker'); ?></label></th>
                                        <td>
                                            <input name="ws_proponente[proponente_ruolo]" type="text" id="prop_ruolo" value="<?php echo esc_attr($settings['proponente_ruolo']); ?>" class="regular-text" placeholder="Es. Masterclass Trainer & Docente">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="prop_citta"><?php esc_html_e('Sede / Città Base', 'wsmaker'); ?></label></th>
                                        <td>
                                            <input name="ws_proponente[proponente_citta]" type="text" id="prop_citta" value="<?php echo esc_attr($settings['proponente_citta']); ?>" class="regular-text" placeholder="Es. Napoli, Italia">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="prop_bio"><?php esc_html_e('Biografia / Presentazione', 'wsmaker'); ?></label></th>
                                        <td>
                                            <textarea name="ws_proponente[proponente_bio]" id="prop_bio" rows="6" class="large-text" placeholder="<?php esc_attr_e('Descrivi la tua esperienza, il tuo approccio didattico e i traguardi raggiunti...', 'wsmaker'); ?>"><?php echo esc_textarea($settings['proponente_bio']); ?></textarea>
                                            <p class="description"><?php esc_html_e('Questa biografia verrà mostrata nella scheda docente sul tuo sito e sul portale mondiale Workshop Hub.', 'wsmaker'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Lingue Parlate', 'wsmaker'); ?></th>
                                        <td>
                                            <div class="ws-s36">
                                                <?php foreach ($lingue_disponibili as $key => $label) : ?>
                                                    <label class="ws-s37">
                                                        <input type="checkbox" name="ws_proponente[proponente_lingue][]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $lingue_attive, true)); ?>>
                                                        <?php echo esc_html($label); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <p class="description ws-s38"><?php esc_html_e('Indica le lingue in cui puoi condurre i workshop o comunicare con i corsisti.', 'wsmaker'); ?></p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Colonna 2: Contatti & Canali Social -->
                        <div class="ws-s39">
                            
                            <!-- Card Contatti -->
                            <div class="ws-card-native">
                                <h2 class="ws-s26">
                                    🌐 <?php esc_html_e('Contatti & Presenza Web', 'wsmaker'); ?>
                                </h2>
                                <table class="form-table ws-s35" role="presentation">
                                    <tbody>
                                        <tr>
                                            <th scope="row"><label for="prop_sito"><?php esc_html_e('Sito Web Ufficiale', 'wsmaker'); ?></label></th>
                                            <td>
                                                <input name="ws_proponente[proponente_sito]" type="url" id="prop_sito" value="<?php echo esc_attr($settings['proponente_sito']); ?>" class="regular-text" placeholder="https://tuosito.com">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="prop_email"><?php esc_html_e('Email Pubblica', 'wsmaker'); ?></label></th>
                                            <td>
                                                <input name="ws_proponente[proponente_email]" type="email" id="prop_email" value="<?php echo esc_attr($settings['proponente_email']); ?>" class="regular-text" placeholder="info@tuosito.com">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="prop_tel"><?php esc_html_e('Telefono / WhatsApp', 'wsmaker'); ?></label></th>
                                            <td>
                                                <input name="ws_proponente[proponente_telefono]" type="text" id="prop_tel" value="<?php echo esc_attr($settings['proponente_telefono']); ?>" class="regular-text" placeholder="+39 333 1234567">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Card Social Links -->
                            <div class="ws-card-native">
                                <h2 class="ws-s26">
                                    📱 <?php esc_html_e('Canali Social', 'wsmaker'); ?>
                                </h2>
                                <table class="form-table ws-s35" role="presentation">
                                    <tbody>
                                        <tr>
                                            <th scope="row"><label for="prop_ig">Instagram</label></th>
                                            <td>
                                                <input name="ws_proponente[proponente_instagram]" type="text" id="prop_ig" value="<?php echo esc_attr($settings['proponente_instagram']); ?>" class="regular-text" placeholder="username o URL">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="prop_fb">Facebook</label></th>
                                            <td>
                                                <input name="ws_proponente[proponente_facebook]" type="url" id="prop_fb" value="<?php echo esc_attr($settings['proponente_facebook']); ?>" class="regular-text" placeholder="https://facebook.com/pagina">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="prop_yt">YouTube</label></th>
                                            <td>
                                                <input name="ws_proponente[proponente_youtube]" type="url" id="prop_yt" value="<?php echo esc_attr($settings['proponente_youtube']); ?>" class="regular-text" placeholder="https://youtube.com/@canale">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="prop_li">LinkedIn</label></th>
                                            <td>
                                                <input name="ws_proponente[proponente_linkedin]" type="url" id="prop_li" value="<?php echo esc_attr($settings['proponente_linkedin']); ?>" class="regular-text" placeholder="https://linkedin.com/in/profilo">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="prop_tt">TikTok</label></th>
                                            <td>
                                                <input name="ws_proponente[proponente_tiktok]" type="text" id="prop_tt" value="<?php echo esc_attr($settings['proponente_tiktok']); ?>" class="regular-text" placeholder="@username">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="prop_x">X (Twitter)</label></th>
                                            <td>
                                                <input name="ws_proponente[proponente_x]" type="text" id="prop_x" value="<?php echo esc_attr($settings['proponente_x']); ?>" class="regular-text" placeholder="@username">
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Card Shortcode -->
                            <div class="ws-card-native ws-s40">
                                <h2 class="ws-s41"><?php esc_html_e('💡 Come mostrare la Bio nel sito', 'wsmaker'); ?></h2>
                                <p class="ws-s42"><?php esc_html_e('Incolla questo shortcode in qualsiasi pagina, articolo o footer:', 'wsmaker'); ?></p>
                                <code class="ws-s43">[ws_proponente]</code>
                            </div>

                        </div>
                    </div>

                    <?php submit_button(__('Salva Profilo Proponente', 'wsmaker')); ?>
                </form>

                <?php
                WSMA_Data::enqueue_inline_script(<<<'JS'
                document.addEventListener('DOMContentLoaded', function() {
                    var btnUpload = document.getElementById('ws-upload-photo-btn');
                    var btnRemove = document.getElementById('ws-remove-photo-btn');
                    var inputPhoto = document.getElementById('ws_proponente_foto');
                    var imgPreview = document.getElementById('ws-photo-img');
                    var placeholder = document.getElementById('ws-photo-placeholder');

                    if (btnUpload) {
                        btnUpload.addEventListener('click', function(e) {
                            e.preventDefault();
                            var frame = wp.media({
                                title: 'Seleziona Foto Profilo Proponente',
                                button: { text: 'Usa questa foto' },
                                multiple: false
                            });

                            frame.on('select', function() {
                                var attachment = frame.state().get('selection').first().toJSON();
                                if (attachment && attachment.url) {
                                    inputPhoto.value = attachment.url;
                                    imgPreview.src = attachment.url;
                                    imgPreview.style.display = 'block';
                                    if (placeholder) placeholder.style.display = 'none';
                                    if (btnRemove) btnRemove.style.display = 'inline-block';
                                }
                            });

                            frame.open();
                        });
                    }

                    if (btnRemove) {
                        btnRemove.addEventListener('click', function(e) {
                            e.preventDefault();
                            inputPhoto.value = '';
                            imgPreview.src = '';
                            imgPreview.style.display = 'none';
                            if (placeholder) placeholder.style.display = 'block';
                            btnRemove.style.display = 'none';
                        });
                    }
                });
                JS
                );
                ?>
            <?php elseif ($tab === 'mail') :
                $mail_settings = WSMA_Mail_Inbox::get_public_settings();
                $has_password  = !empty($mail_settings['has_password']);
            ?>
                <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success-message flag after a redirect, no form data is processed. ?>
                <?php if (isset($_GET['updated']) && sanitize_text_field(wp_unslash($_GET['updated'])) === '1') : ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e('Impostazioni della casella mail salvate e protette con crittografia AES-256.', 'wsmaker'); ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php wp_nonce_field('wsma_save_mail_nonce'); ?>
                    <input type="hidden" name="ws_save_mail_action" value="1">

                    <div class="ws-s44">
                        <!-- Column 1: IMAP (Reading) -->
                        <div class="ws-card-native">
                            <h2 class="ws-s26"><?php esc_html_e('Lettura Mail (IMAP)', 'wsmaker'); ?></h2>
                            
                            <table class="form-table ws-s35" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="imap_host"><?php esc_html_e('Host IMAP', 'wsmaker'); ?></label></th>
                                        <td><input name="ws_mail[host]" type="text" id="imap_host" value="<?php echo esc_attr($mail_settings['host']); ?>" class="regular-text" placeholder="imap.zoho.com"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="imap_port"><?php esc_html_e('Porta IMAP', 'wsmaker'); ?></label></th>
                                        <td><input name="ws_mail[port]" type="number" id="imap_port" value="<?php echo esc_attr($mail_settings['port']); ?>" class="small-text" placeholder="993"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="imap_enc"><?php esc_html_e('Crittografia', 'wsmaker'); ?></label></th>
                                        <td>
                                            <select name="ws_mail[encryption]" id="imap_enc">
                                                <option value="ssl" <?php selected('ssl', $mail_settings['encryption']); ?>>SSL</option>
                                                <option value="tls" <?php selected('tls', $mail_settings['encryption']); ?>>TLS</option>
                                                <option value="" <?php selected('', $mail_settings['encryption']); ?>>Nessuna</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="imap_username"><?php esc_html_e('Utente IMAP', 'wsmaker'); ?></label></th>
                                        <td><input name="ws_mail[username]" type="text" id="imap_username" value="<?php echo esc_attr($mail_settings['username']); ?>" class="regular-text" placeholder="info@domain.com"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="imap_password"><?php esc_html_e('Password Account', 'wsmaker'); ?></label></th>
                                        <td>
                                            <input name="ws_mail[password]" type="password" id="imap_password" value="" class="regular-text" placeholder="<?php echo esc_attr($has_password ? __('•••••••• (Inalterata)', 'wsmaker') : __('Inserisci password', 'wsmaker')); ?>">
                                            <?php if ($has_password) : ?>
                                                <p class="description ws-s45">🔒 <?php esc_html_e('Password protetta con cifratura OpenSSL AES-256.', 'wsmaker'); ?></p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Column 2: SMTP & Sender (Writing) -->
                        <div class="ws-card-native">
                            <h2 class="ws-s26"><?php esc_html_e('Invio Mail (SMTP / Mittente)', 'wsmaker'); ?></h2>
                            
                            <table class="form-table ws-s35" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="reply_from_name"><?php esc_html_e('Nome Mittente', 'wsmaker'); ?></label></th>
                                        <td><input name="ws_mail[reply_from_name]" type="text" id="reply_from_name" value="<?php echo esc_attr($mail_settings['reply_from_name']); ?>" class="regular-text" placeholder="Francesco Verolino"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="reply_from_email"><?php esc_html_e('Email Mittente', 'wsmaker'); ?></label></th>
                                        <td><input name="ws_mail[reply_from_email]" type="email" id="reply_from_email" value="<?php echo esc_attr($mail_settings['reply_from_email']); ?>" class="regular-text" placeholder="workshop@domain.com"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="smtp_host"><?php esc_html_e('Host SMTP', 'wsmaker'); ?></label></th>
                                        <td><input name="ws_mail[smtp_host]" type="text" id="smtp_host" value="<?php echo esc_attr($mail_settings['smtp_host']); ?>" class="regular-text" placeholder="smtp.zoho.com"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="smtp_port"><?php esc_html_e('Porta SMTP', 'wsmaker'); ?></label></th>
                                        <td><input name="ws_mail[smtp_port]" type="number" id="smtp_port" value="<?php echo esc_attr($mail_settings['smtp_port']); ?>" class="small-text" placeholder="587"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="smtp_enc"><?php esc_html_e('Crittografia', 'wsmaker'); ?></label></th>
                                        <td>
                                            <select name="ws_mail[smtp_encryption]" id="smtp_enc">
                                                <option value="tls" <?php selected('tls', $mail_settings['smtp_encryption']); ?>>TLS</option>
                                                <option value="ssl" <?php selected('ssl', $mail_settings['smtp_encryption']); ?>>SSL</option>
                                                <option value="" <?php selected('', $mail_settings['smtp_encryption']); ?>>Nessuna</option>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Anti-Spam & Rate Limiting Section -->
                    <div class="ws-card-native ws-s46">
                        <h2 class="ws-s26"><?php esc_html_e('Protezione Anti-Spam & Rate Limit Iscrizioni', 'wsmaker'); ?></h2>
                        <p class="description ws-s47"><?php esc_html_e('Proteggi il server e l\'endpoint delle iscrizioni da invii automatici di bot e attacchi flood.', 'wsmaker'); ?></p>
                        
                        <table class="form-table ws-s35" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Rate Limiting IP', 'wsmaker'); ?></th>
                                    <td>
                                        <label for="intake_rate_limit_enabled">
                                            <input name="ws_security[intake_rate_limit_enabled]" type="checkbox" id="intake_rate_limit_enabled" value="1" <?php checked(1, $settings['intake_rate_limit_enabled'] ?? 1); ?>>
                                            <?php esc_html_e('Attiva limitazione richieste per indirizzo IP', 'wsmaker'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="intake_rate_limit_requests"><?php esc_html_e('Soglia Massima Richieste', 'wsmaker'); ?></label></th>
                                    <td>
                                        <input name="ws_security[intake_rate_limit_requests]" type="number" id="intake_rate_limit_requests" value="<?php echo esc_attr($settings['intake_rate_limit_requests'] ?? 5); ?>" class="small-text" min="1" max="100">
                                        <span class="description"><?php esc_html_e('Numero massimo di iscrizioni consentite dallo stesso IP (default: 5).', 'wsmaker'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="intake_rate_limit_window"><?php esc_html_e('Finestra Temporale (secondi)', 'wsmaker'); ?></label></th>
                                    <td>
                                        <input name="ws_security[intake_rate_limit_window]" type="number" id="intake_rate_limit_window" value="<?php echo esc_attr($settings['intake_rate_limit_window'] ?? 60); ?>" class="small-text" min="10" max="3600">
                                        <span class="description"><?php esc_html_e('Intervallo di tempo per il calcolo del limite (default: 60 secondi).', 'wsmaker'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Protezione Honeypot', 'wsmaker'); ?></th>
                                    <td>
                                        <label for="intake_honeypot_enabled">
                                            <input name="ws_security[intake_honeypot_enabled]" type="checkbox" id="intake_honeypot_enabled" value="1" <?php checked(1, $settings['intake_honeypot_enabled'] ?? 1); ?>>
                                            <?php esc_html_e('Blocca invii con campo trappola compilato (anti-bot invisibile)', 'wsmaker'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Geolocalizzazione IP', 'wsmaker'); ?></th>
                                    <td>
                                        <label for="intake_geolocation_enabled">
                                            <input name="ws_security[intake_geolocation_enabled]" type="checkbox" id="intake_geolocation_enabled" value="1" <?php checked(1, $settings['intake_geolocation_enabled'] ?? 0); ?>>
                                            <?php esc_html_e('Aggiungi città/paese dedotti dall\'IP nella mail di notifica (invia l\'IP a ip-api.com)', 'wsmaker'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="intake_geolocation_api_url"><?php esc_html_e('URL servizio geolocalizzazione', 'wsmaker'); ?></label></th>
                                    <td>
                                        <input name="ws_security[intake_geolocation_api_url]" type="url" id="intake_geolocation_api_url" value="<?php echo esc_attr($settings['intake_geolocation_api_url'] ?? ''); ?>" class="regular-text" placeholder="https://tuoservizio.esempio.com/lookup">
                                        <p class="description"><?php esc_html_e('Facoltativo. Lascia vuoto per usare ip-api.com (gratuito, adatto a un uso non commerciale). Se hai un tuo provider di geolocalizzazione (per un uso commerciale conforme ai termini, o per volumi elevati), inserisci qui il suo URL: deve accettare l\'IP e rispondere con un JSON contenente i campi "city" e "country".', 'wsmaker'); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php submit_button(__('Salva Configurazione Posta & Sicurezza', 'wsmaker')); ?>
                </form>
            <?php elseif ($tab === 'shortcodes') : ?>
                <div class="ws-card-native">
                    <h2 class="ws-s35"><?php esc_html_e('Elenco Shortcode Disponibili', 'wsmaker'); ?></h2>
                    <p class="description ws-s48">
                        <?php esc_html_e('Copia e incolla questi shortcode nelle pagine WordPress del tuo sito per integrare le funzionalità di WSMaker.', 'wsmaker'); ?>
                    </p>

                    <?php
                    $shortcodes = [
                        // ── Pagina & contenuto workshop ──
                        ['tag' => '[wsma_workshop_page slug="..."]', 'alias' => null, 'title' => 'Pagina Workshop Predefinita (consigliato)', 'desc' => 'Pagina completa generata automaticamente: hero image, intro, programma, requisiti, note importanti, poi eventi in programma e form iscrizione. Compilabile dal form Categoria.', 'badge' => 'Pubblico'],
                        ['tag' => '[wsma_workshop_text slug="..." field="intro"]', 'alias' => null, 'title' => 'Blocco di testo/foto Categoria (singolo)', 'desc' => 'Incorpora un solo campo (field: intro, program, requirements, important_notes, city, country, address, photo) per comporre una pagina su misura invece della [wsma_workshop_page] completa.', 'badge' => 'Pubblico'],
                        ['tag' => '[wsma_eventi_categoria slug="..."]', 'alias' => null, 'title' => 'Griglia Eventi per Categoria', 'desc' => 'Visualizza l\'elenco di tutti gli eventi associati ad una specifica categoria. Già incluso in [wsma_workshop_page], usalo da solo solo se componi la pagina a mano.', 'badge' => 'Pubblico'],
                        ['tag' => '[wsma_workshop_prossimo]', 'alias' => null, 'title' => 'Prossimo Workshop in Arrivo', 'desc' => 'Mostra un box evidenziato con la scheda del prossimo evento in programma.', 'badge' => 'Pubblico'],
                        ['tag' => '[workshop_programma]', 'alias' => null, 'title' => 'Programma Evento', 'desc' => 'Visualizza il programma dettagliato, gli orari e i posti disponibili per un determinato workshop.', 'badge' => 'Pubblico'],
                        ['tag' => '[wsma_prezzo slug="..."]', 'alias' => null, 'title' => 'Prezzo Categoria', 'desc' => 'Stampa il prezzo di listino della categoria (rilevata automaticamente dalla pagina, o via attributo slug).', 'badge' => 'Pubblico'],
                        ['tag' => '[wsma_acconto slug="..."]', 'alias' => null, 'title' => 'Acconto Richiesto Categoria', 'desc' => 'Stampa l\'importo dell\'acconto richiesto per prenotare la categoria.', 'badge' => 'Pubblico'],
                        // ── Prenotazione & pagamento ──
                        ['tag' => '[wsma_form_iscrizione]', 'alias' => null, 'title' => 'Form Iscrizione Workshop Standalone', 'desc' => 'Mostra il modulo d\'iscrizione reattivo frontend per permettere ai candidati di registrarsi ai workshop disponibili.', 'badge' => 'Pubblico'],
                        ['tag' => '[wsma_acquista slug="..."]', 'alias' => null, 'title' => 'Pulsante Acquista (WooCommerce / Stripe)', 'desc' => 'Si adatta da solo: se l\'evento ha un prodotto WooCommerce collegato mostra il link diretto al prodotto, altrimenti — se Stripe è configurato e la categoria richiede un acconto — mostra un mini-form che crea la prenotazione e apre subito il checkout.', 'badge' => 'PRO'],
                        ['tag' => '[ws_pagamento iscrizione_id="..." tipo="acconto|saldo"]', 'alias' => null, 'title' => 'Pulsante Pagamento Manuale Stripe', 'desc' => 'Pulsante "Paga ora" per una specifica iscrizione già esistente — usalo se vuoi costruire a mano una pagina/link di pagamento invece di affidarti al link automatico in mail.', 'badge' => 'PRO'],
                        ['tag' => '[wsma_aula_virtuale evento_id="..."]', 'alias' => null, 'title' => 'Aula Virtuale', 'desc' => 'Incorpora la videochiamata dell\'evento (Jitsi integrato, o pulsante verso Zoom/Meet/altro). Creata automaticamente quando l\'evento è impostato su Modalità = Aula virtuale.', 'badge' => 'Pubblico'],
                        ['tag' => '[workshop_ringraziamento_panel]', 'alias' => null, 'title' => 'Pagina di Ringraziamento Post-Iscrizione', 'desc' => 'Pagina di conferma visualizzata all\'utente dopo l\'invio della candidatura.', 'badge' => 'Pubblico'],
                        // ── Altro contenuto pubblico ──
                        ['tag' => '[wsma_proponente]', 'alias' => null, 'title' => 'Scheda Profilo Docente / Bio', 'desc' => 'Mostra la scheda biografica del docente/proponente completa di foto, bio, badge lingue parlate, social e contatti diretti.', 'badge' => 'Pubblico'],
                        // ── Pannelli amministrazione frontend (richiedono login) ──
                        ['tag' => '[workshop_admin]', 'alias' => null, 'title' => 'Dashboard Amministrazione Frontend', 'desc' => 'Pannello completo di gestione bacheca per amministratori (richiede login).', 'badge' => 'Admin'],
                        ['tag' => '[workshop_riepilogo]', 'alias' => null, 'title' => 'Riepilogo Partecipazioni / Eventi', 'desc' => 'Tabella di gestione iscrizioni, pagamenti anticipi e saldi (richiede login).', 'badge' => 'Admin'],
                        ['tag' => '[workshop_partecipanti_lista]', 'alias' => null, 'title' => 'Anagrafica Partecipanti', 'desc' => 'Gestione completa dei contatti e dello storico clienti (richiede login).', 'badge' => 'Admin'],
                        ['tag' => '[workshop_messaggi]', 'alias' => null, 'title' => 'Mail Box / Comunicazioni', 'desc' => 'Pannello di invio e gestione mail ai partecipanti (richiede login).', 'badge' => 'Admin'],
                        ['tag' => '[workshop_calendar_panel]', 'alias' => null, 'title' => 'Admin Calendar & Sync ICS', 'desc' => 'Pannello per la sottoscrizione ai feed del calendario iCal/ICS (richiede login).', 'badge' => 'Admin'],
                        ['tag' => '[workshop_archivio]', 'alias' => null, 'title' => 'Archivio Storico Eventi', 'desc' => 'Consultazione dei workshop passati e conclusi (richiede login).', 'badge' => 'Admin'],
                    ];
                    ?>
                    <table class="widefat striped ws-s53">
                        <thead>
                            <tr>
                                <th class="ws-s54"><?php esc_html_e('Funzionalità', 'wsmaker'); ?></th>
                                <th class="ws-s55"><?php esc_html_e('Descrizione', 'wsmaker'); ?></th>
                                <th class="ws-s56"><?php esc_html_e('Shortcode Tag', 'wsmaker'); ?></th>
                                <th class="ws-s57"><?php esc_html_e('Azione', 'wsmaker'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shortcodes as $sc) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($sc['title']); ?></strong>
                                        <br>
                                        <span class="ws-badge-native ws-sc-badge <?php echo esc_attr($sc['badge'] === 'Admin' ? 'ws-sc-badge--admin' : ($sc['badge'] === 'PRO' ? 'ws-sc-badge--pro' : 'ws-sc-badge--default')); ?>">
                                            <?php echo esc_html($sc['badge']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($sc['desc']); ?></td>
                                    <td>
                                        <code><?php echo esc_html($sc['tag']); ?></code>
                                        <?php if ($sc['alias']) : ?>
                                            <br><small class="ws-s58"><?php echo esc_html($sc['alias']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ws-s59">
                                        <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($sc['tag']); ?>'); this.innerText='✓ Copiato!'; setTimeout(() => this.innerText='Copia', 2000);">
                                            <?php esc_html_e('Copia', 'wsmaker'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <form method="post" action="options.php">
                    <?php settings_fields('wsma_license_group'); ?>
                    
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="ws_license_key"><?php esc_html_e('Chiave di Licenza', 'wsmaker'); ?></label></th>
                                <td>
                                    <input name="<?php echo esc_attr(WSMA_License_Manager::LICENSE_OPTION_KEY); ?>[key]" type="password" id="ws_license_key" value="<?php echo esc_attr($license['key']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Inserisci la chiave di licenza acquistata per abilitare il supporto e gli aggiornamenti automatici.', 'wsmaker'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Stato Licenza', 'wsmaker'); ?></th>
                                <td>
                                    <?php if ($license['status'] === 'active') : ?>
                                        <span class="dashicons dashicons-yes-alt ws-s60"></span>
                                        <strong class="ws-s45"><?php esc_html_e('ATTIVA', 'wsmaker'); ?></strong> (Piano: <?php echo esc_html(strtoupper($license['type'])); ?>)
                                        <?php if ($license['expires']) : ?>
                                            — <?php /* translators: %s: license expiration date */ printf(esc_html__('Scade il: %s', 'wsmaker'), esc_html($license['expires'])); ?>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-dismiss ws-s61"></span>
                                        <strong class="ws-s62"><?php esc_html_e('NON ATTIVA / NON VALIDA', 'wsmaker'); ?></strong>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button(__('Salva e Verifica Licenza', 'wsmaker')); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}
