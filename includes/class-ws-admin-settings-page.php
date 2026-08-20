<?php

if (!defined('ABSPATH')) exit;

/**
 * Native WP Admin settings page for Workshop Suite.
 */
final class WS_Admin_Settings_Page implements WS_Module {

    public function should_load(): bool {
        return is_admin();
    }

    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu_page'], 10);
        add_action('admin_menu', [$this, 'add_trailing_submenus'], 99);
        add_action('admin_head', [$this, 'inject_admin_menu_separator_css']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_mail_settings_save']);
        add_action('admin_init', [$this, 'handle_proponente_settings_save']);
        add_action('admin_init', [$this, 'handle_modules_settings_save']);
    }

    public function handle_modules_settings_save(): void {
        if (isset($_POST['ws_save_modules_action']) && check_admin_referer('ws_save_modules_nonce')) {
            if (current_user_can('manage_options')) {
                $current = WS_Settings::get_all();
                $submitted_modules = $_POST['ws_modules'] ?? [];
                
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
                WS_Settings::update_all($current);

                wp_redirect(admin_url('admin.php?page=workshop-suite-settings&tab=modules&updated=1'));
                exit;
            }
        }
    }

    public function handle_proponente_settings_save(): void {
        if (isset($_POST['ws_save_proponente_action']) && check_admin_referer('ws_save_proponente_nonce')) {
            if (current_user_can('manage_options')) {
                $current = WS_Settings::get_all();
                $data = $_POST['ws_proponente'] ?? [];

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

                WS_Settings::update_all($current);
                wp_redirect(admin_url('admin.php?page=workshop-suite-settings&tab=proponente&updated=1'));
                exit;
            }
        }
    }

    public function handle_mail_settings_save(): void {
        if (isset($_POST['ws_save_mail_action']) && check_admin_referer('ws_save_mail_nonce')) {
            if (current_user_can('manage_options')) {
                WS_Mail_Inbox::save_settings($_POST['ws_mail'] ?? []);

                // Save anti-spam and rate limiting options
                if (isset($_POST['ws_security'])) {
                    $sec = $_POST['ws_security'];
                    $current_settings = WS_Settings::get_all();
                    $current_settings['intake_rate_limit_enabled']  = !empty($sec['intake_rate_limit_enabled']) ? 1 : 0;
                    $current_settings['intake_rate_limit_requests'] = max(1, (int) ($sec['intake_rate_limit_requests'] ?? 5));
                    $current_settings['intake_rate_limit_window']   = max(10, (int) ($sec['intake_rate_limit_window'] ?? 60));
                    $current_settings['intake_honeypot_enabled']    = !empty($sec['intake_honeypot_enabled']) ? 1 : 0;
                    WS_Settings::update_all($current_settings);
                }

                wp_redirect(admin_url('admin.php?page=workshop-suite-settings&tab=mail&updated=1'));
                exit;
            }
        }
    }

    public function inject_admin_menu_separator_css(): void {
        ?>
        <style id="ws-admin-menu-divider-css">
            #adminmenu .toplevel_page_workshop-suite-dashboard .wp-submenu li.ws-menu-separator {
                border-top: 1px solid rgba(240, 246, 252, 0.18) !important;
                margin-top: 6px !important;
                padding-top: 4px !important;
            }
            #adminmenu .toplevel_page_workshop-suite-dashboard .wp-submenu li.ws-menu-separator a {
                font-weight: 600 !important;
            }
        </style>
        <?php
    }

    public function add_menu_page(): void {
        // Main Top Level Menu -> Loads Vue Dashboard
        add_menu_page(
            __('Workshop Suite Dashboard', 'workshop-suite'),
            __('Workshop Suite', 'workshop-suite'),
            'manage_options',
            'workshop-suite-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-tickets-alt',
            30
        );

        // Submenu 1: Dashboard
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Dashboard', 'workshop-suite'),
            __('Dashboard', 'workshop-suite'),
            'manage_options',
            'workshop-suite-dashboard',
            [$this, 'render_dashboard']
        );

        // Submenu 2: Categories & Types
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Categories & Types', 'workshop-suite'),
            __('Categories & Types', 'workshop-suite'),
            'manage_options',
            'workshop-suite-categorie',
            [$this, 'render_riepilogo']
        );

        // Submenu 3: Events & Registrations
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Events & Registrations', 'workshop-suite'),
            __('Events & Registrations', 'workshop-suite'),
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
        if (WS_Settings::is_module_active('poster_studio', true)) {
            add_submenu_page(
                'workshop-suite-dashboard',
                __('Poster Templates', 'workshop-suite'),
                __('Poster Templates', 'workshop-suite'),
                'manage_options',
                'workshop-suite-locandine',
                [$this, 'render_locandine']
            );
        }

        // Submenu 5: Contacts & Participants
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Contacts & Participants', 'workshop-suite'),
            __('Contacts & Participants', 'workshop-suite'),
            'manage_options',
            'workshop-suite-partecipanti',
            [$this, 'render_partecipanti']
        );

        // Submenu 6: Mail Inbox
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Mail Inbox', 'workshop-suite'),
            __('Mail Inbox', 'workshop-suite'),
            'manage_options',
            'workshop-suite-messaggi',
            [$this, 'render_messaggi']
        );

        // Submenu 7: Admin Calendar
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Admin Calendar', 'workshop-suite'),
            __('Admin Calendar', 'workshop-suite'),
            'manage_options',
            'workshop-suite-calendario',
            [$this, 'render_calendario']
        );

        // Submenu 8: Events Archive
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Events Archive', 'workshop-suite'),
            __('Events Archive', 'workshop-suite'),
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
            __('Moduli & Add-on', 'workshop-suite'),
            __('Moduli & Add-on', 'workshop-suite'),
            'manage_options',
            'admin.php?page=workshop-suite-settings&tab=modules'
        );

        // Submenu: Settings
        add_submenu_page(
            'workshop-suite-dashboard',
            __('Settings', 'workshop-suite'),
            __('Settings', 'workshop-suite'),
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

    /** Enqueues one Vue bundle's JS/CSS + WS_CONFIG, without emitting any markup. */
    private function enqueue_panel_assets(string $handle, string $js_file, string $css_file, array $extra_config = []): void {
        $asset_js  = WS_PATH . $js_file;
        $asset_css = WS_PATH . $css_file;

        // Ensure the Vue ESM script loads with type="module".
        // This filter is normally registered by the matching FVW_Shortcode_* class,
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
            WS_URL . $js_file,
            [],
            file_exists($asset_js) ? (string) filemtime($asset_js) : WS_VERSION,
            true
        );

        if (file_exists($asset_css)) {
            wp_enqueue_style(
                $handle,
                WS_URL . $css_file,
                [],
                (string) filemtime($asset_css)
            );
        }

        $config = array_merge([
            'restUrl'       => esc_url_raw(rest_url('workshop-suite/v1/')),
            'nonce'         => wp_create_nonce('wp_rest'),
            'brandName'     => WS_Settings::get('site_brand_name', 'Workshop Suite'),
        ], $extra_config);
        wp_localize_script($handle, 'WS_CONFIG', $config);
        wp_localize_script($handle, 'FVW_CONFIG', $config);
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
        if (!isset($_GET['vista'])) {
            $_GET['vista'] = 'eventi';
        }
        $this->render_panel_wrapper('ws-admin-app', 'ws-admin', 'assets/dist/admin.js', 'assets/dist/admin.css', ['panelMode' => 'virtuale']);
    }

    public function render_eventi_partecipanti(): void {
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
        $settings = WS_Settings::get_all();
        $default_theme = WS_Settings::get('default_theme_mode', 'dark');
        ?>
        <div class="wrap ws-theme-wrapper ws-theme-<?php echo esc_attr($default_theme); ?> ws-dashboard-wrapper" id="ws-dashboard-wrapper">
            
            <div class="ws-theme-switch-bar">
                <span class="ws-s1"><?php esc_html_e('Tema:', 'workshop-suite'); ?></span>
                <button type="button" class="ws-theme-btn <?php echo $default_theme === 'dark' ? 'active' : ''; ?>" id="btn-theme-dark" onclick="fvwSetTheme('dark')">🌙 Dark</button>
                <button type="button" class="ws-theme-btn <?php echo $default_theme === 'light' ? 'active' : ''; ?>" id="btn-theme-light" onclick="fvwSetTheme('light')">☀️ Light</button>
            </div>

            <div class="ws-s2">
                <h2 class="ws-s3"><?php esc_html_e('🏷️ Tipi di Evento', 'workshop-suite'); ?></h2>
                <p class="ws-s4"><?php esc_html_e('Gestisci la lista dei tipi di evento predefiniti (es. Workshop, Viaggio Fotografico, Masterclass). Puoi modificarli o aggiungerne di nuovi.', 'workshop-suite'); ?></p>

                <style>
                .ws-theme-light #ws-event-types-container input {
                    background: #ffffff !important;
                    color: #0f172a !important;
                    border: 1px solid #cbd5e1 !important;
                }
                .ws-theme-light #ws-event-types-container button {
                    background: #fff1f2 !important;
                    border-color: #f43f5e !important;
                    color: #e11d48 !important;
                }
                .ws-theme-dark #ws-event-types-container input {
                    background: rgba(255,255,255,0.05) !important;
                    color: #ffffff !important;
                    border: 1px solid rgba(255,255,255,0.2) !important;
                }
                </style>

                <form method="post" action="options.php">
                    <?php settings_fields('ws_settings_group'); ?>
                    
                    <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[site_brand_name]" value="<?php echo esc_attr($settings['site_brand_name']); ?>">
                    <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[sender_name]" value="<?php echo esc_attr($settings['sender_name']); ?>">
                    <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[sender_email]" value="<?php echo esc_attr($settings['sender_email']); ?>">
                    <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[currency_symbol]" value="<?php echo esc_attr($settings['currency_symbol']); ?>">
                    <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[default_theme_mode]" value="<?php echo esc_attr($settings['default_theme_mode']); ?>">
                    <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[enable_t15_reminders]" value="<?php echo esc_attr($settings['enable_t15_reminders']); ?>">

                    <div class="ws-s5" id="ws-event-types-container">
                        <?php 
                        $types = $settings['event_types'] ?? ['Workshop', 'Viaggio Fotografico', 'Masterclass'];
                        foreach ($types as $t) : 
                        ?>
                            <div class="ws-type-row ws-s6">
                                <input name="<?php echo WS_Settings::OPTION_KEY; ?>[event_types][]" type="text" value="<?php echo esc_attr($t); ?>" class="regular-text ws-flex-input" placeholder="Es. Workshop">
                                <button type="button" class="button button-secondary ws-s7" onclick="this.parentNode.remove()">✕ Rimuovi</button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="ws-s8">
                        <button type="button" class="button button-secondary" onclick="fvwAddEventTypeRow()">+ <?php esc_html_e('Aggiungi Tipo Evento', 'workshop-suite'); ?></button>
                        <?php submit_button(__('Salva Tipi Evento', 'workshop-suite'), 'primary', 'submit', false); ?>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function fvwAddEventTypeRow() {
            var container = document.getElementById('ws-event-types-container');
            var row = document.createElement('div');
            row.className = 'ws-type-row';
            row.style.cssText = 'display:flex;gap:10px;align-items:center;';
            row.innerHTML = '<input name="<?php echo WS_Settings::OPTION_KEY; ?>[event_types][]" type="text" value="" class="regular-text ws-flex-input" placeholder="Es. Corso Online">' +
                            '<button type="button" class="button button-secondary ws-s7" onclick="this.parentNode.remove()">✕ Rimuovi</button>';
            container.appendChild(row);
        }
        function fvwSetTheme(theme) {
            var wrapper = document.getElementById('ws-dashboard-wrapper');
            var btnDark = document.getElementById('btn-theme-dark');
            var btnLight = document.getElementById('btn-theme-light');

            if (theme === 'light') {
                wrapper.classList.remove('ws-theme-dark');
                wrapper.classList.add('ws-theme-light');
                if (btnLight) btnLight.classList.add('active');
                if (btnDark) btnDark.classList.remove('active');
            } else {
                wrapper.classList.remove('ws-theme-light');
                wrapper.classList.add('ws-theme-dark');
                if (btnDark) btnDark.classList.add('active');
                if (btnLight) btnLight.classList.remove('active');
            }
            localStorage.setItem('ws_user_theme', theme);
        }
        (function() {
            var saved = localStorage.getItem('ws_user_theme');
            if (saved === 'light' || saved === 'dark') {
                fvwSetTheme(saved);
            }
        })();
        </script>
        <?php
    }

    public function register_settings(): void {
        register_setting('ws_settings_group', WS_Settings::OPTION_KEY, [
            'sanitize_callback' => function ($input) {
                if (!is_array($input)) return [];
                $current = WS_Settings::get_all();
                $current['site_brand_name']      = sanitize_text_field($input['site_brand_name'] ?? '');
                $current['sender_name']          = sanitize_text_field($input['sender_name'] ?? '');
                $current['sender_email']         = sanitize_email($input['sender_email'] ?? '');
                $current['currency_symbol']      = sanitize_text_field($input['currency_symbol'] ?? '€');
                $current['enable_t15_reminders'] = !empty($input['enable_t15_reminders']) ? 1 : 0;
                $current['default_theme_mode']   = in_array($input['default_theme_mode'] ?? '', ['dark', 'light'], true) ? $input['default_theme_mode'] : 'dark';
                $current['custom_css']           = wp_strip_all_tags($input['custom_css'] ?? '');
                return $current;
            }
        ]);
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) return;

        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

        if ($tab === 'proponente') {
            wp_enqueue_media();
        }

        $asset_css = WS_PATH . 'assets/dist/admin.css';
        if (file_exists($asset_css)) {
            wp_enqueue_style('ws-admin-settings-css', WS_URL . 'assets/dist/admin.css', [], (string) filemtime($asset_css));
        }

        $settings = WS_Settings::get_all();
        $license  = WS_License_Manager::get_license_data();
        ?>
        <div class="wrap ws-admin-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Workshop Suite Settings', 'workshop-suite'); ?></h1>
            <hr class="wp-header-end">
            
            <nav class="nav-tab-wrapper wp-clearfix ws-s9">
                <a href="?page=workshop-suite-settings&tab=general" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General', 'workshop-suite'); ?></a>
                <a href="?page=workshop-suite-settings&tab=modules" class="nav-tab <?php echo $tab === 'modules' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Modules & Add-ons', 'workshop-suite'); ?></a>
                <a href="?page=workshop-suite-settings&tab=proponente" class="nav-tab <?php echo $tab === 'proponente' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Trainer Profile / Bio', 'workshop-suite'); ?></a>
                <a href="?page=workshop-suite-settings&tab=mail" class="nav-tab <?php echo $tab === 'mail' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Mail Configuration', 'workshop-suite'); ?></a>
                <a href="?page=workshop-suite-settings&tab=custom_css" class="nav-tab <?php echo $tab === 'custom_css' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Custom CSS Code', 'workshop-suite'); ?></a>
                <a href="?page=workshop-suite-settings&tab=shortcodes" class="nav-tab <?php echo $tab === 'shortcodes' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Shortcodes', 'workshop-suite'); ?></a>
                <a href="?page=workshop-suite-settings&tab=license" class="nav-tab <?php echo $tab === 'license' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('License', 'workshop-suite'); ?></a>
            </nav>

            <?php if ($tab === 'general') : ?>
                <form method="post" action="options.php">
                    <?php settings_fields('ws_settings_group'); ?>
                    <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[custom_css]" value="<?php echo esc_attr($settings['custom_css'] ?? ''); ?>">
                    
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="site_brand_name"><?php esc_html_e('Nome Brand / Organizzazione', 'workshop-suite'); ?></label>
                                </th>
                                <td>
                                    <input name="<?php echo WS_Settings::OPTION_KEY; ?>[site_brand_name]" type="text" id="site_brand_name" value="<?php echo esc_attr($settings['site_brand_name']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Usato nelle firme email automatiche e nei calendari ICS.', 'workshop-suite'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sender_name"><?php esc_html_e('Nome Mittente Email', 'workshop-suite'); ?></label>
                                </th>
                                <td>
                                    <input name="<?php echo WS_Settings::OPTION_KEY; ?>[sender_name]" type="text" id="sender_name" value="<?php echo esc_attr($settings['sender_name']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Nome visualizzato dal destinatario come mittente delle notifiche.', 'workshop-suite'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sender_email"><?php esc_html_e('Email Mittente Risposte', 'workshop-suite'); ?></label>
                                </th>
                                <td>
                                    <input name="<?php echo WS_Settings::OPTION_KEY; ?>[sender_email]" type="email" id="sender_email" value="<?php echo esc_attr($settings['sender_email']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Indirizzo predefinito da cui partiranno le mail di risposta e promemoria.', 'workshop-suite'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="currency_symbol"><?php esc_html_e('Simbolo Valuta', 'workshop-suite'); ?></label>
                                </th>
                                <td>
                                    <input name="<?php echo WS_Settings::OPTION_KEY; ?>[currency_symbol]" type="text" id="currency_symbol" value="<?php echo esc_attr($settings['currency_symbol']); ?>" class="small-text">
                                    <p class="description"><?php esc_html_e('Simbolo valuta mostrato nei form di iscrizione (es. €).', 'workshop-suite'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="default_theme_mode"><?php esc_html_e('Tema Frontend Predefinito', 'workshop-suite'); ?></label>
                                </th>
                                <td>
                                    <select name="<?php echo WS_Settings::OPTION_KEY; ?>[default_theme_mode]" id="default_theme_mode">
                                        <option value="dark" <?php selected('dark', $settings['default_theme_mode']); ?>><?php esc_html_e('Tema Dark (Scuro)', 'workshop-suite'); ?></option>
                                        <option value="light" <?php selected('light', $settings['default_theme_mode']); ?>><?php esc_html_e('Tema Light (Chiaro)', 'workshop-suite'); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e('Stile grafico applicato agli shortcode e ai moduli pubblici nel frontend del sito.', 'workshop-suite'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php esc_html_e('Automazioni Promemoria', 'workshop-suite'); ?></th>
                                <td>
                                    <fieldset>
                                        <label for="enable_t15_reminders">
                                            <input name="<?php echo WS_Settings::OPTION_KEY; ?>[enable_t15_reminders]" type="checkbox" id="enable_t15_reminders" value="1" <?php checked(1, $settings['enable_t15_reminders']); ?>>
                                            <?php esc_html_e('Abilita invio automatico promemoria a 15 giorni dall\'evento (T-15)', 'workshop-suite'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Invia in automatico una mail riassuntiva ai partecipanti confermati 15 giorni prima della data inizio.', 'workshop-suite'); ?></p>
                                    </fieldset>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button(__('Salva Impostazioni', 'workshop-suite')); ?>
                </form>
            <?php elseif ($tab === 'modules') : 
                $is_pro_active = defined('WS_PRO_VERSION');
                $active_mods = (array) ($settings['active_modules'] ?? []);
                $available_modules = [
                    'courses_academy' => [
                        'icon'        => '🎬',
                        'title'       => __('Courses & Video Academy (LMS)', 'workshop-suite'),
                        'desc'        => __('Piattaforma videocorsi on-demand, masterclass registrate, gestione moduli/lezioni con player video avanzato e streaming Zoom/Meet.', 'workshop-suite'),
                        'badge'       => 'ACADEMY',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'voucher_pdf' => [
                        'icon'        => '🎟️',
                        'title'       => __('Voucher di Partecipazione & PDF Pass', 'workshop-suite'),
                        'desc'        => __('Genera e invia in automatico all\'email di conferma il Voucher/Pass PDF personalizzato con QR-code e dettagli logistici per il corsista.', 'workshop-suite'),
                        'badge'       => 'CONFIRMATION',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'ai_assistant' => [
                        'icon'        => '🤖',
                        'title'       => __('AI Workshop Assistant & Copywriter', 'workshop-suite'),
                        'desc'        => __('Assistente AI per generare descrizioni accattivanti dei corsi, programmi didattici, testi per i post di Instagram e risposte email agli allievi.', 'workshop-suite'),
                        'badge'       => 'AI ENGINE',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'stripe_payments' => [
                        'icon'        => '💳',
                        'title'       => __('Pagamenti Nativi Stripe & Apple Pay', 'workshop-suite'),
                        'desc'        => __('Incasso istantaneo di anticipi o saldi tramite carta di credito, Apple Pay e Google Pay con zero plugin intermedi.', 'workshop-suite'),
                        'badge'       => 'PAYMENTS',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'multi_docente' => [
                        'icon'        => '🏢',
                        'title'       => __('Multi-Docente & Faculty Mode (Scuole)', 'workshop-suite'),
                        'desc'        => __('Permette di assegnare workshop a docenti o istruttori specifici, con schede bio dedicate e gestione faculty per scuole e accademie.', 'workshop-suite'),
                        'badge'       => 'FACULTY',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'sms_whatsapp_gateway' => [
                        'icon'        => '📱',
                        'title'       => __('SMS & WhatsApp Automation Gateway', 'workshop-suite'),
                        'desc'        => __('Invio automatico di conferme immediate e promemoria urgenti tramite SMS o WhatsApp API (Twilio / Meta Gateway).', 'workshop-suite'),
                        'badge'       => 'MESSAGING',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'calendar_sync' => [
                        'icon'        => '📅',
                        'title'       => __('Google Calendar & Apple iCal Auto-Sync', 'workshop-suite'),
                        'desc'        => __('Feed ICS dinamico che sincronizza in tempo reale le date dei workshop sul calendario personale del docente e dei partecipanti.', 'workshop-suite'),
                        'badge'       => 'CALENDAR',
                        'is_pro'      => false,
                        'default'     => 1,
                    ],
                    'fluentcrm_marketing' => [
                        'icon'        => '✉️',
                        'title'       => __('FluentCRM & Email Marketing Bridge', 'workshop-suite'),
                        'desc'        => __('Sincronizza in automatico i partecipanti nei funnel e nelle liste email di FluentCRM o Mailchimp per newsletter e promozioni.', 'workshop-suite'),
                        'badge'       => 'MARKETING',
                        'is_pro'      => false,
                        'default'     => 0,
                    ],
                    'webhooks' => [
                        'icon'        => '🔌',
                        'title'       => __('Webhook & Connettori Zapier / Make', 'workshop-suite'),
                        'desc'        => __('Invia payload JSON in tempo reale a Make.com, Zapier, Zoho CRM o Google Sheets ad ogni nuova iscrizione o conferma pagamento.', 'workshop-suite'),
                        'badge'       => 'INTEGRATION',
                        'is_pro'      => false,
                        'default'     => 0,
                    ],
                    'poster_studio' => [
                        'icon'        => '🎨',
                        'title'       => __('Poster Studio & Social Banner Builder', 'workshop-suite'),
                        'desc'        => __('Generatore grafico in-browser di locandine pronte per Instagram Feed (1:1), Stories (9:16) e Facebook con rendering HTML5 Canvas.', 'workshop-suite'),
                        'badge'       => 'GRAPHIC',
                        'is_pro'      => false,
                        'default'     => 1,
                    ],
                    'woocommerce' => [
                        'icon'        => '🛒',
                        'title'       => __('Integrazione Carrello WooCommerce', 'workshop-suite'),
                        'desc'        => __('Sincronizza i workshop come prodotti nel carrello WooCommerce per utilizzare i tuoi gateway e la fatturazione elettronica.', 'workshop-suite'),
                        'badge'       => 'ECOMMERCE',
                        'is_pro'      => true,
                        'default'     => 0,
                    ],
                    'whatsapp_widget' => [
                        'icon'        => '💬',
                        'title'       => __('WhatsApp Floating Quick Chat', 'workshop-suite'),
                        'desc'        => __('Pulsante galleggiante moderno e leggero per permettere ai visitatori di fare domande dirette sul workshop via WhatsApp.', 'workshop-suite'),
                        'badge'       => 'LEAD GEN',
                        'is_pro'      => false,
                        'default'     => 1,
                    ],
                    'global_hub_pro' => [
                        'icon'        => '🌐',
                        'title'       => __('Woorkshoop Global Hub & World Map Sync', 'workshop-suite'),
                        'desc'        => __('Sincronizza in automatico i tuoi workshop ed eventi sulla directory globale woorkshoop.space / workshopsuite.pro e sulla mappa interattiva mondiale.', 'workshop-suite'),
                        'badge'       => 'GLOBAL HUB',
                        'is_pro'      => false,
                        'default'     => 1,
                    ],
                ];
            ?>
                <?php if (!$is_pro_active) : ?>
                    <div class="ws-s10">
                        <div>
                            <span class="ws-s11">Workshop Suite Free</span>
                            <h3 class="ws-s12">Sblocca il Potere Completo con Workshop Suite PRO</h3>
                            <p class="ws-s13">Installa l'Add-on PRO per sbloccare la Piattaforma Corsi Didattici, Live Streaming Zoom, Pagamenti Stripe, AI Copywriter e Voucher PDF.</p>
                        </div>
                        <a href="https://workshopsuite.pro/#pricing" target="_blank" class="button button-primary ws-s14">
                            Sblocca Tutti i Moduli PRO (99€) →
                        </a>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php wp_nonce_field('ws_save_modules_nonce'); ?>
                    <input type="hidden" name="ws_save_modules_action" value="1">

                    <div class="ws-s15">
                        <?php foreach ($available_modules as $mod_key => $mod_data) : 
                            $is_locked = ($mod_data['is_pro'] && !$is_pro_active);
                            $is_active = $is_locked ? false : (isset($active_mods[$mod_key]) ? !empty($active_mods[$mod_key]) : $mod_data['default']);
                        ?>
                            <div class="ws-card-native ws-mod-card <?php echo $is_locked ? 'ws-mod-card--locked' : ($is_active ? 'ws-mod-card--active' : 'ws-mod-card--inactive'); ?>">
                                <div>
                                    <div class="ws-s16">
                                        <div class="ws-s17">
                                            <span class="ws-s18"><?php echo esc_html($mod_data['icon']); ?></span>
                                            <?php if ($mod_data['is_pro']): ?>
                                                <span class="ws-pro-badge <?php echo $is_pro_active ? 'ws-pro-badge--unlocked' : 'ws-pro-badge--locked'; ?>">
                                                    <?php echo $is_pro_active ? '✓ PRO UNLOCKED' : '🔒 PRO REQUIRED'; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="ws-s19">
                                                    <?php echo esc_html($mod_data['badge']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Toggle Switch -->
                                        <?php if ($is_locked): ?>
                                            <a class="ws-s20" href="https://workshopsuite.pro/#pricing" target="_blank">
                                                🔒 Sblocca PRO
                                            </a>
                                        <?php else: ?>
                                            <label class="ws-toggle-switch">
                                                <input type="checkbox" name="ws_modules[<?php echo esc_attr($mod_key); ?>]" value="1" <?php checked(true, $is_active); ?> onchange="wsToggleModuleLive(this, '<?php echo esc_attr($mod_key); ?>')">
                                                <span class="ws-toggle-slider"></span>
                                            </label>
                                        <?php endif; ?>
                                    </div>

                                    <h3 class="ws-mod-title<?php echo $is_locked ? ' ws-mod-title--locked' : ''; ?>">
                                        <?php echo esc_html($mod_data['title']); ?>
                                    </h3>
                                    <p class="ws-s21">
                                        <?php echo esc_html($mod_data['desc']); ?>
                                    </p>
                                </div>

                                <div class="ws-s22">
                                    <span class="ws-mod-status-label ws-mod-status <?php echo $is_locked ? 'ws-mod-status--locked' : ($is_active ? 'ws-mod-status--active' : 'ws-mod-status--inactive'); ?>">
                                        ● <?php echo $is_locked ? esc_html__('Richiede PRO', 'workshop-suite') : ($is_active ? esc_html__('Attivo', 'workshop-suite') : esc_html__('Disattivato', 'workshop-suite')); ?>
                                    </span>
                                    <span class="ws-s23">
                                        <code>ws-mod-<?php echo esc_html($mod_key); ?></code>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <style>
                    .ws-toggle-switch {
                        position: relative;
                        display: inline-block;
                        width: 44px;
                        height: 24px;
                        flex-shrink: 0;
                    }
                    .ws-toggle-switch input {
                        opacity: 0;
                        width: 0;
                        height: 0;
                    }
                    .ws-toggle-slider {
                        position: absolute;
                        cursor: pointer;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background-color: #c3c4c7;
                        transition: .2s;
                        border-radius: 24px;
                    }
                    .ws-toggle-slider:before {
                        position: absolute;
                        content: "";
                        height: 18px;
                        width: 18px;
                        left: 3px;
                        bottom: 3px;
                        background-color: white;
                        transition: .2s;
                        border-radius: 50%;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
                    }
                    .ws-toggle-switch input:checked + .ws-toggle-slider {
                        background-color: #2271b1;
                    }
                    .ws-toggle-switch input:checked + .ws-toggle-slider:before {
                        transform: translateX(20px);
                    }
                    </style>

                    <div class="ws-card-native" style="margin-top: 24px; padding: 20px; border-left: 4px solid #00d2b4; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                        <div>
                            <h4 style="margin: 0 0 6px 0; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                                <span>🌐</span> <?php esc_html_e('Sincronizzazione Manuale Global Hub & Mappa Mondiale', 'workshop-suite'); ?>
                            </h4>
                            <p style="margin: 0; font-size: 13px; color: #64748b;">
                                <?php esc_html_e('Invia in un colpo solo tutti i tuoi workshop ed eventi pubblicati alla directory workshopsuite.pro e alla mappa interattiva.', 'workshop-suite'); ?>
                            </p>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span id="ws-sync-hub-msg" style="font-size: 13px; font-weight: 600;"></span>
                            <button type="button" class="button button-primary" id="btn-sync-hub-now" onclick="wsSyncHubNow(this)" style="background: linear-gradient(135deg, #0088ff, #00d2b4); border-color: transparent; color: #fff; font-weight: 600; padding: 4px 14px; height: 34px;">
                                🚀 <?php esc_html_e('Sincronizza tutti i Workshop con l\'Hub adesso', 'workshop-suite'); ?>
                            </button>
                        </div>
                    </div>

                    <script>
                    function wsToggleModuleLive(checkbox, modKey) {
                        const card = checkbox.closest('.ws-card-native');
                        const statusLabel = card.querySelector('.ws-mod-status-label');
                        const isActive = checkbox.checked;

                        card.style.borderColor = isActive ? '#2271b1' : '#cbd5e1';
                        if (statusLabel) {
                            statusLabel.textContent = isActive ? '● Attivo' : '● Disattivato';
                            statusLabel.style.color = isActive ? '#10b981' : '#94a3b8';
                        }

                        fetch('<?php echo esc_url_raw(rest_url('workshop-suite/v1/modules/toggle')); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
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

                    function wsSyncHubNow(btn) {
                        const msgEl = document.getElementById('ws-sync-hub-msg');
                        btn.disabled = true;
                        btn.textContent = '⏳ Sincronizzazione in corso...';
                        if (msgEl) {
                            msgEl.textContent = '';
                            msgEl.style.color = '#2271b1';
                        }

                        fetch('<?php echo esc_url_raw(rest_url('workshop-suite/v1/admin/sync-hub-now')); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
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
                    </script>

                    <div class="ws-s24">
                        <?php submit_button(__('Salva Stato Moduli', 'workshop-suite')); ?>
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
                <?php if (isset($_GET['updated']) && $_GET['updated'] === '1') : ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e('Profilo del proponente e scheda Bio salvati con successo.', 'workshop-suite'); ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php wp_nonce_field('ws_save_proponente_nonce'); ?>
                    <input type="hidden" name="ws_save_proponente_action" value="1">

                    <div class="ws-s25">
                        
                        <!-- Colonna 1: Bio & Dati Personali -->
                        <div class="ws-card-native">
                            <h2 class="ws-s26">
                                👤 <?php esc_html_e('Dati Docente / Proponente', 'workshop-suite'); ?>
                            </h2>

                            <!-- Foto Profilo con Uploader WP Media -->
                            <div class="ws-s27">
                                <label class="ws-s28"><?php esc_html_e('Foto Profilo / Avatar', 'workshop-suite'); ?></label>
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
                                        <button type="button" class="button button-secondary" id="ws-upload-photo-btn"><?php esc_html_e('Carica / Scegli Foto', 'workshop-suite'); ?></button>
                                        <button type="button" class="button button-link-delete ws-remove-photo-btn<?php echo empty($settings['proponente_foto']) ? ' ws-hidden' : ''; ?>" id="ws-remove-photo-btn"><?php esc_html_e('Rimuovi', 'workshop-suite'); ?></button>
                                        <p class="description ws-s34"><?php esc_html_e('Consigliata immagine quadrata ad alta risoluzione (min. 400x400 px).', 'workshop-suite'); ?></p>
                                    </div>
                                </div>
                            </div>

                            <table class="form-table ws-s35" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="prop_nome"><?php esc_html_e('Nome & Cognome', 'workshop-suite'); ?></label></th>
                                        <td>
                                            <input name="ws_proponente[proponente_nome]" type="text" id="prop_nome" value="<?php echo esc_attr($settings['proponente_nome']); ?>" class="regular-text" placeholder="Es. Francesco Verolino">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="prop_ruolo"><?php esc_html_e('Titolo / Ruolo', 'workshop-suite'); ?></label></th>
                                        <td>
                                            <input name="ws_proponente[proponente_ruolo]" type="text" id="prop_ruolo" value="<?php echo esc_attr($settings['proponente_ruolo']); ?>" class="regular-text" placeholder="Es. Masterclass Trainer & Docente">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="prop_citta"><?php esc_html_e('Sede / Città Base', 'workshop-suite'); ?></label></th>
                                        <td>
                                            <input name="ws_proponente[proponente_citta]" type="text" id="prop_citta" value="<?php echo esc_attr($settings['proponente_citta']); ?>" class="regular-text" placeholder="Es. Napoli, Italia">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="prop_bio"><?php esc_html_e('Biografia / Presentazione', 'workshop-suite'); ?></label></th>
                                        <td>
                                            <textarea name="ws_proponente[proponente_bio]" id="prop_bio" rows="6" class="large-text" placeholder="<?php esc_attr_e('Descrivi la tua esperienza, il tuo approccio didattico e i traguardi raggiunti...', 'workshop-suite'); ?>"><?php echo esc_textarea($settings['proponente_bio']); ?></textarea>
                                            <p class="description"><?php esc_html_e('Questa biografia verrà mostrata nella scheda docente sul tuo sito e sul portale mondiale Workshop Hub.', 'workshop-suite'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Lingue Parlate', 'workshop-suite'); ?></th>
                                        <td>
                                            <div class="ws-s36">
                                                <?php foreach ($lingue_disponibili as $key => $label) : ?>
                                                    <label class="ws-s37">
                                                        <input type="checkbox" name="ws_proponente[proponente_lingue][]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $lingue_attive, true)); ?>>
                                                        <?php echo esc_html($label); ?>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <p class="description ws-s38"><?php esc_html_e('Indica le lingue in cui puoi condurre i workshop o comunicare con i corsisti.', 'workshop-suite'); ?></p>
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
                                    🌐 <?php esc_html_e('Contatti & Presenza Web', 'workshop-suite'); ?>
                                </h2>
                                <table class="form-table ws-s35" role="presentation">
                                    <tbody>
                                        <tr>
                                            <th scope="row"><label for="prop_sito"><?php esc_html_e('Sito Web Ufficiale', 'workshop-suite'); ?></label></th>
                                            <td>
                                                <input name="ws_proponente[proponente_sito]" type="url" id="prop_sito" value="<?php echo esc_attr($settings['proponente_sito']); ?>" class="regular-text" placeholder="https://tuosito.com">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="prop_email"><?php esc_html_e('Email Pubblica', 'workshop-suite'); ?></label></th>
                                            <td>
                                                <input name="ws_proponente[proponente_email]" type="email" id="prop_email" value="<?php echo esc_attr($settings['proponente_email']); ?>" class="regular-text" placeholder="info@tuosito.com">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><label for="prop_tel"><?php esc_html_e('Telefono / WhatsApp', 'workshop-suite'); ?></label></th>
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
                                    📱 <?php esc_html_e('Canali Social', 'workshop-suite'); ?>
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
                                <h2 class="ws-s41"><?php esc_html_e('💡 Come mostrare la Bio nel sito', 'workshop-suite'); ?></h2>
                                <p class="ws-s42"><?php esc_html_e('Incolla questo shortcode in qualsiasi pagina, articolo o footer:', 'workshop-suite'); ?></p>
                                <code class="ws-s43">[ws_proponente]</code>
                            </div>

                        </div>
                    </div>

                    <?php submit_button(__('Salva Profilo Proponente', 'workshop-suite')); ?>
                </form>

                <script>
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
                </script>
            <?php elseif ($tab === 'mail') : 
                $mail_settings = WS_Mail_Inbox::get_public_settings();
                $has_password  = !empty($mail_settings['has_password']);
            ?>
                <?php if (isset($_GET['updated']) && $_GET['updated'] === '1') : ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php esc_html_e('Impostazioni della casella mail salvate e protette con crittografia AES-256.', 'workshop-suite'); ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php wp_nonce_field('ws_save_mail_nonce'); ?>
                    <input type="hidden" name="ws_save_mail_action" value="1">

                    <div class="ws-s44">
                        <!-- Column 1: IMAP (Reading) -->
                        <div class="ws-card-native">
                            <h2 class="ws-s26"><?php esc_html_e('Lettura Mail (IMAP)', 'workshop-suite'); ?></h2>
                            
                            <table class="form-table ws-s35" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="imap_host"><?php esc_html_e('Host IMAP', 'workshop-suite'); ?></label></th>
                                        <td><input name="ws_mail[host]" type="text" id="imap_host" value="<?php echo esc_attr($mail_settings['host']); ?>" class="regular-text" placeholder="imap.zoho.com"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="imap_port"><?php esc_html_e('Porta IMAP', 'workshop-suite'); ?></label></th>
                                        <td><input name="ws_mail[port]" type="number" id="imap_port" value="<?php echo esc_attr($mail_settings['port']); ?>" class="small-text" placeholder="993"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="imap_enc"><?php esc_html_e('Crittografia', 'workshop-suite'); ?></label></th>
                                        <td>
                                            <select name="ws_mail[encryption]" id="imap_enc">
                                                <option value="ssl" <?php selected('ssl', $mail_settings['encryption']); ?>>SSL</option>
                                                <option value="tls" <?php selected('tls', $mail_settings['encryption']); ?>>TLS</option>
                                                <option value="" <?php selected('', $mail_settings['encryption']); ?>>Nessuna</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="imap_username"><?php esc_html_e('Utente IMAP', 'workshop-suite'); ?></label></th>
                                        <td><input name="ws_mail[username]" type="text" id="imap_username" value="<?php echo esc_attr($mail_settings['username']); ?>" class="regular-text" placeholder="info@domain.com"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="imap_password"><?php esc_html_e('Password Account', 'workshop-suite'); ?></label></th>
                                        <td>
                                            <input name="ws_mail[password]" type="password" id="imap_password" value="" class="regular-text" placeholder="<?php echo $has_password ? '•••••••• (Inalterata)' : 'Inserisci password'; ?>">
                                            <?php if ($has_password) : ?>
                                                <p class="description ws-s45">🔒 <?php esc_html_e('Password protetta con cifratura OpenSSL AES-256.', 'workshop-suite'); ?></p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Column 2: SMTP & Sender (Writing) -->
                        <div class="ws-card-native">
                            <h2 class="ws-s26"><?php esc_html_e('Invio Mail (SMTP / Mittente)', 'workshop-suite'); ?></h2>
                            
                            <table class="form-table ws-s35" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="reply_from_name"><?php esc_html_e('Nome Mittente', 'workshop-suite'); ?></label></th>
                                        <td><input name="ws_mail[reply_from_name]" type="text" id="reply_from_name" value="<?php echo esc_attr($mail_settings['reply_from_name']); ?>" class="regular-text" placeholder="Francesco Verolino"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="reply_from_email"><?php esc_html_e('Email Mittente', 'workshop-suite'); ?></label></th>
                                        <td><input name="ws_mail[reply_from_email]" type="email" id="reply_from_email" value="<?php echo esc_attr($mail_settings['reply_from_email']); ?>" class="regular-text" placeholder="workshop@domain.com"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="smtp_host"><?php esc_html_e('Host SMTP', 'workshop-suite'); ?></label></th>
                                        <td><input name="ws_mail[smtp_host]" type="text" id="smtp_host" value="<?php echo esc_attr($mail_settings['smtp_host']); ?>" class="regular-text" placeholder="smtp.zoho.com"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="smtp_port"><?php esc_html_e('Porta SMTP', 'workshop-suite'); ?></label></th>
                                        <td><input name="ws_mail[smtp_port]" type="number" id="smtp_port" value="<?php echo esc_attr($mail_settings['smtp_port']); ?>" class="small-text" placeholder="587"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="smtp_enc"><?php esc_html_e('Crittografia', 'workshop-suite'); ?></label></th>
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
                        <h2 class="ws-s26"><?php esc_html_e('Protezione Anti-Spam & Rate Limit Iscrizioni', 'workshop-suite'); ?></h2>
                        <p class="description ws-s47"><?php esc_html_e('Proteggi il server e l\'endpoint delle iscrizioni da invii automatici di bot e attacchi flood.', 'workshop-suite'); ?></p>
                        
                        <table class="form-table ws-s35" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Rate Limiting IP', 'workshop-suite'); ?></th>
                                    <td>
                                        <label for="intake_rate_limit_enabled">
                                            <input name="ws_security[intake_rate_limit_enabled]" type="checkbox" id="intake_rate_limit_enabled" value="1" <?php checked(1, $settings['intake_rate_limit_enabled'] ?? 1); ?>>
                                            <?php esc_html_e('Attiva limitazione richieste per indirizzo IP', 'workshop-suite'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="intake_rate_limit_requests"><?php esc_html_e('Soglia Massima Richieste', 'workshop-suite'); ?></label></th>
                                    <td>
                                        <input name="ws_security[intake_rate_limit_requests]" type="number" id="intake_rate_limit_requests" value="<?php echo esc_attr($settings['intake_rate_limit_requests'] ?? 5); ?>" class="small-text" min="1" max="100">
                                        <span class="description"><?php esc_html_e('Numero massimo di iscrizioni consentite dallo stesso IP (default: 5).', 'workshop-suite'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="intake_rate_limit_window"><?php esc_html_e('Finestra Temporale (secondi)', 'workshop-suite'); ?></label></th>
                                    <td>
                                        <input name="ws_security[intake_rate_limit_window]" type="number" id="intake_rate_limit_window" value="<?php echo esc_attr($settings['intake_rate_limit_window'] ?? 60); ?>" class="small-text" min="10" max="3600">
                                        <span class="description"><?php esc_html_e('Intervallo di tempo per il calcolo del limite (default: 60 secondi).', 'workshop-suite'); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Protezione Honeypot', 'workshop-suite'); ?></th>
                                    <td>
                                        <label for="intake_honeypot_enabled">
                                            <input name="ws_security[intake_honeypot_enabled]" type="checkbox" id="intake_honeypot_enabled" value="1" <?php checked(1, $settings['intake_honeypot_enabled'] ?? 1); ?>>
                                            <?php esc_html_e('Blocca invii con campo trappola compilato (anti-bot invisibile)', 'workshop-suite'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php submit_button(__('Salva Configurazione Posta & Sicurezza', 'workshop-suite')); ?>
                </form>
            <?php elseif ($tab === 'custom_css') : ?>
                <div class="ws-card-native">
                    <h2 class="ws-s35"><?php esc_html_e('Codice CSS Personalizzato', 'workshop-suite'); ?></h2>
                    <p class="description ws-s48">
                        <?php esc_html_e('A destra puoi aggiungere regole CSS personalizzate che sovrascriveranno lo stile dell\'applicazione. A sinistra è visibile il codice CSS predefinito di riferimento.', 'workshop-suite'); ?>
                    </p>

                    <div class="ws-s49">
                        <div>
                            <label class="ws-s50"><?php esc_html_e('Codice Esistente (CSS Predefinito)', 'workshop-suite'); ?></label>
                            <textarea readonly class="large-text code ws-s51"><?php 
                                $default_css_file = WS_PATH . 'assets/dist/admin.css';
                                echo file_exists($default_css_file) ? esc_html(file_get_contents($default_css_file)) : '/* CSS predefinito non trovato */'; 
                            ?></textarea>
                        </div>
                        <div>
                            <form method="post" action="options.php">
                                <?php settings_fields('ws_settings_group'); ?>
                                <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[site_brand_name]" value="<?php echo esc_attr($settings['site_brand_name']); ?>">
                                <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[sender_name]" value="<?php echo esc_attr($settings['sender_name']); ?>">
                                <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[sender_email]" value="<?php echo esc_attr($settings['sender_email']); ?>">
                                <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[currency_symbol]" value="<?php echo esc_attr($settings['currency_symbol']); ?>">
                                <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[enable_t15_reminders]" value="<?php echo esc_attr($settings['enable_t15_reminders']); ?>">
                                <input type="hidden" name="<?php echo WS_Settings::OPTION_KEY; ?>[default_theme_mode]" value="<?php echo esc_attr($settings['default_theme_mode']); ?>">

                                <label class="ws-s50"><?php esc_html_e('Codice Custom (CSS Personalizzato)', 'workshop-suite'); ?></label>
                                <textarea name="<?php echo WS_Settings::OPTION_KEY; ?>[custom_css]" class="large-text code ws-css-editor" placeholder="/* Scrivi qui il tuo codice CSS personalizzato */"><?php echo esc_html($settings['custom_css'] ?? ''); ?></textarea>
                                
                                <div class="ws-s52">
                                    <?php submit_button(__('Salva Codice Custom', 'workshop-suite'), 'primary', 'submit', false); ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'shortcodes') : ?>
                <div class="ws-card-native">
                    <h2 class="ws-s35"><?php esc_html_e('Elenco Shortcode Disponibili', 'workshop-suite'); ?></h2>
                    <p class="description ws-s48">
                        <?php esc_html_e('Copia e incolla questi shortcode nelle pagine WordPress del tuo sito per integrare le funzionalità di Workshop Suite.', 'workshop-suite'); ?>
                    </p>

                    <?php
                    $shortcodes = [
                        ['tag' => '[ws_workshop_page slug="..."]', 'alias' => null, 'title' => 'Pagina Workshop Predefinita (consigliato)', 'desc' => 'Pagina completa generata automaticamente: hero image, intro, programma, requisiti, note importanti, poi eventi in programma e form iscrizione. Compilabile dal form Categoria.', 'badge' => 'Pubblico'],
                        ['tag' => '[ws_aula_virtuale evento_id="..."]', 'alias' => null, 'title' => 'Aula Virtuale', 'desc' => 'Incorpora la videochiamata dell\'evento (Jitsi integrato, o pulsante verso Zoom/Meet/altro). Creata automaticamente quando l\'evento è impostato su Modalità = Aula virtuale.', 'badge' => 'Pubblico'],
                        ['tag' => '[fv_form_iscrizione]', 'alias' => '[fvw_form_iscrizione] / [ws_form_iscrizione]', 'title' => 'Form Iscrizione Workshop Standalone', 'desc' => 'Mostra il modulo d\'iscrizione reattivo frontend per permettere ai candidati di registrarsi ai workshop disponibili.', 'badge' => 'Pubblico'],
                        ['tag' => '[ws_proponente]', 'alias' => '[workshop_suite_bio]', 'title' => 'Scheda Profilo Docente / Bio', 'desc' => 'Mostra la scheda biografica del docente/proponente completa di foto, bio, badge lingue parlate, social e contatti diretti.', 'badge' => 'Pubblico'],
                        ['tag' => '[workshop_programma]', 'alias' => null, 'title' => 'Programma Evento', 'desc' => 'Visualizza il programma dettagliato, gli orari e i posti disponibili per un determinato workshop.', 'badge' => 'Pubblico'],
                        ['tag' => '[workshop_prossimo]', 'alias' => null, 'title' => 'Prossimo Workshop in Arrivo', 'desc' => 'Mostra un box evidenziato con la scheda del prossimo evento in programma.', 'badge' => 'Pubblico'],
                        ['tag' => '[eventi_categoria slug="..."]', 'alias' => null, 'title' => 'Griglia Eventi per Categoria', 'desc' => 'Visualizza l\'elenco di tutti gli eventi associati ad una specifica categoria. Già incluso in [ws_workshop_page], usalo da solo solo se componi la pagina a mano.', 'badge' => 'Pubblico'],
                        ['tag' => '[workshop_ringraziamento]', 'alias' => null, 'title' => 'Pagina di Ringraziamento Post-Iscrizione', 'desc' => 'Pagina di conferma visualizzata all\'utente dopo l\'invio della candidatura.', 'badge' => 'Pubblico'],
                        ['tag' => '[workshop_admin]', 'alias' => null, 'title' => 'Dashboard Amministrazione Frontend', 'desc' => 'Pannello completo di gestione bacheca per amministratori (richiede login).', 'badge' => 'Admin'],
                        ['tag' => '[workshop_riepilogo]', 'alias' => null, 'title' => 'Riepilogo Partecipazioni / Eventi', 'desc' => 'Tabella di gestione iscrizioni, pagamenti anticipi e saldi (richiede login).', 'badge' => 'Admin'],
                        ['tag' => '[workshop_partecipanti]', 'alias' => null, 'title' => 'Anagrafica Partecipanti', 'desc' => 'Gestione completa dei contatti e dello storico clienti (richiede login).', 'badge' => 'Admin'],
                        ['tag' => '[workshop_messaggi]', 'alias' => null, 'title' => 'Mail Box / Comunicazioni', 'desc' => 'Pannello di invio e gestione mail ai partecipanti (richiede login).', 'badge' => 'Admin'],
                        ['tag' => '[workshop_calendario]', 'alias' => null, 'title' => 'Admin Calendar & Sync ICS', 'desc' => 'Pannello per la sottoscrizione ai feed del calendario iCal/ICS (richiede login).', 'badge' => 'Admin'],
                        ['tag' => '[workshop_archivio]', 'alias' => null, 'title' => 'Archivio Storico Eventi', 'desc' => 'Consultazione dei workshop passati e conclusi (richiede login).', 'badge' => 'Admin']
                    ];
                    ?>
                    <table class="widefat striped ws-s53">
                        <thead>
                            <tr>
                                <th class="ws-s54"><?php esc_html_e('Funzionalità', 'workshop-suite'); ?></th>
                                <th class="ws-s55"><?php esc_html_e('Descrizione', 'workshop-suite'); ?></th>
                                <th class="ws-s56"><?php esc_html_e('Shortcode Tag', 'workshop-suite'); ?></th>
                                <th class="ws-s57"><?php esc_html_e('Azione', 'workshop-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shortcodes as $sc) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($sc['title']); ?></strong>
                                        <br>
                                        <span class="ws-badge-native ws-sc-badge <?php echo $sc['badge'] === 'Admin' ? 'ws-sc-badge--admin' : 'ws-sc-badge--default'; ?>">
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
                                            <?php esc_html_e('Copia', 'workshop-suite'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <form method="post" action="options.php">
                    <?php settings_fields('ws_license_group'); ?>
                    
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="ws_license_key"><?php esc_html_e('Chiave di Licenza', 'workshop-suite'); ?></label></th>
                                <td>
                                    <input name="<?php echo WS_License_Manager::LICENSE_OPTION_KEY; ?>[key]" type="password" id="ws_license_key" value="<?php echo esc_attr($license['key']); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Inserisci la chiave di licenza acquistata per abilitare il supporto e gli aggiornamenti automatici.', 'workshop-suite'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Stato Licenza', 'workshop-suite'); ?></th>
                                <td>
                                    <?php if ($license['status'] === 'active') : ?>
                                        <span class="dashicons dashicons-yes-alt ws-s60"></span>
                                        <strong class="ws-s45"><?php esc_html_e('ATTIVA', 'workshop-suite'); ?></strong> (Piano: <?php echo esc_html(strtoupper($license['type'])); ?>)
                                        <?php if ($license['expires']) : ?>
                                            — <?php /* translators: %s: license expiration date */ printf(esc_html__('Scade il: %s', 'workshop-suite'), esc_html($license['expires'])); ?>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-dismiss ws-s61"></span>
                                        <strong class="ws-s62"><?php esc_html_e('NON ATTIVA / NON VALIDA', 'workshop-suite'); ?></strong>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button(__('Salva e Verifica Licenza', 'workshop-suite')); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}
