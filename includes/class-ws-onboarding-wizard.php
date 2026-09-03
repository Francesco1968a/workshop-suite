<?php

if (!defined('ABSPATH')) exit;

/**
 * First-run onboarding wizard, shown once right after activation.
 *
 * Two steps, in this order (deliberate — data-sharing consent comes before
 * mail setup, since the Trainer Bio panel and the hub directory only make
 * sense once the admin has already decided whether their data leaves the
 * site at all):
 *   1. Global Hub data-sharing consent (mirrors the disclosure in readme.txt)
 *   2. Mailbox setup, with "Configura ora" (jumps into the real Mail
 *      Configuration tab) or "Configura dopo" (skips, no fields duplicated
 *      here — the real IMAP/SMTP form already exists and is fully working
 *      in class-ws-admin-settings-page.php's 'mail' tab)
 *
 * Triggered via register_activation_hook() in workshop-suite.php, which
 * only fires on an actual (re)activation of the plugin — not on every
 * page load of an already-active site, and not retroactively for sites
 * that were already using the plugin before this wizard existed.
 */
final class WSMA_Onboarding_Wizard implements WSMA_Module {

    private const OPTION_COMPLETED = 'wsma_onboarding_completed';
    private const TRANSIENT_SHOW = 'wsma_show_onboarding';
    private const PAGE_SLUG = 'workshop-suite-wizard';

    public static function on_activate(): void {
        if (!get_option(self::OPTION_COMPLETED)) {
            set_transient(self::TRANSIENT_SHOW, 1, HOUR_IN_SECONDS);
        }
    }

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_action('admin_init', [$this, 'maybe_redirect']);
        add_action('admin_init', [$this, 'maybe_handle_submit']);
        add_action('admin_menu', [$this, 'add_hidden_page']);
    }

    public function maybe_redirect(): void {
        if (!get_transient(self::TRANSIENT_SHOW)) return;
        if (!current_user_can('manage_options')) return;
        if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON) || wp_doing_cron()) return;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page-slug check to skip a redirect, no form data is processed here.
        if (isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === self::PAGE_SLUG) return;

        delete_transient(self::TRANSIENT_SHOW);
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    /**
     * Handles the wizard's own form submissions. Must run on admin_init —
     * doing this from render() (the add_submenu_page callback) fires too
     * late, since core has already sent the admin-header.php output by the
     * time that callback runs, and wp_safe_redirect() at that point throws
     * "headers already sent".
     */
    public function maybe_handle_submit(): void {
        if (!current_user_can('manage_options')) return;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page-slug check before the real nonce check below.
        if (!isset($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== self::PAGE_SLUG) return;

        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        if ($request_method !== 'POST' || !isset($_POST['ws_wizard_nonce_field'])) return;

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ws_wizard_nonce_field'])), 'ws_wizard_nonce')) {
            wp_die(esc_html__('Richiesta non valida, riprova.', 'wsmaker'));
        }

        if (isset($_POST['ws_wizard_step1_submit'])) {
            $settings = WSMA_Settings::get_all();
            $settings['active_modules']['global_hub_pro'] = !empty($_POST['ws_hub_consent']) ? 1 : 0;
            WSMA_Settings::update_all($settings);
            wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG . '&step=2'));
            exit;
        }

        if (isset($_POST['ws_wizard_finish'])) {
            update_option(self::OPTION_COMPLETED, 1);
            $redirect = !empty($_POST['ws_go_to_mail'])
                ? admin_url('admin.php?page=workshop-suite-settings&tab=mail')
                : admin_url('admin.php?page=workshop-suite-settings');
            wp_safe_redirect($redirect);
            exit;
        }
    }

    /**
     * Hidden page — not added to any visible menu, only reachable via the
     * redirect above or its own URL. Registered under 'options.php' rather
     * than a null parent: a null parent slug leaves this page absent from
     * $submenu entirely, which breaks core's get_admin_page_title() lookup
     * in wp-admin/admin-header.php (strip_tags() gets a null $title on
     * PHP 8.1+, triggering a deprecated notice followed by "headers already
     * sent" once output has started) — this is the standard, safe way to
     * register a page that stays out of the menu while keeping a working
     * title lookup.
     */
    public function add_hidden_page(): void {
        add_submenu_page(
            'options.php',
            __('Configurazione guidata', 'wsmaker'),
            '',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void {
        if (!current_user_can('manage_options')) return;

        // Form submissions are handled earlier, on admin_init (see
        // maybe_handle_submit()) — by the time this callback runs, any
        // redirect they issue has already happened.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view routing (which step to display), no data is processed here.
        $step = isset($_GET['step']) && sanitize_text_field(wp_unslash($_GET['step'])) === '2' ? 2 : 1;

        $asset_css = WSMA_PATH . 'assets/dist/admin.css';
        if (file_exists($asset_css)) {
            wp_enqueue_style('ws-admin-settings-css', WSMA_URL . 'assets/dist/admin.css', [], (string) filemtime($asset_css));
        }

        ?>
        <div class="wrap ws-wizard-wrap">
            <div class="ws-wizard-box">
                <div class="ws-wizard-steps">
                    <span class="ws-wizard-step<?php echo $step === 1 ? ' ws-wizard-step--active' : ''; ?>">1. <?php esc_html_e('Condivisione dati', 'wsmaker'); ?></span>
                    <span class="ws-wizard-step<?php echo $step === 2 ? ' ws-wizard-step--active' : ''; ?>">2. <?php esc_html_e('Casella mail', 'wsmaker'); ?></span>
                </div>

                <?php if ($step === 1): $this->render_step1(); else: $this->render_step2(); endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_step1(): void {
        ?>
        <h1><?php esc_html_e('Benvenuto in WSMaker 👋', 'wsmaker'); ?></h1>
        <p class="ws-wizard-lead"><?php esc_html_e('Prima di iniziare, una scelta che riguarda solo te: vuoi condividere i tuoi eventi pubblicati con la directory pubblica di WSMaker?', 'wsmaker'); ?></p>

        <div class="ws-wizard-disclosure">
            <p><strong><?php esc_html_e('Cosa viene inviato, se accetti:', 'wsmaker'); ?></strong></p>
            <p><?php esc_html_e('Titolo, descrizione, date e luogo, categoria, prezzo e acconto, posti disponibili, immagine in evidenza, URL della pagina di prenotazione, e il tuo profilo pubblico da proponente (nome, ruolo, bio, foto, sito, lingue) come lo configurerai nelle impostazioni.', 'wsmaker'); ?></p>
            <p><?php esc_html_e('Va a wsmaker.pro, la directory pubblica del progetto WSMaker — serve a farti trovare da chi cerca corsi come i tuoi. Puoi cambiare questa scelta in qualsiasi momento da Impostazioni → Moduli & Add-ons.', 'wsmaker'); ?></p>
        </div>

        <form method="post">
            <?php wp_nonce_field('ws_wizard_nonce', 'ws_wizard_nonce_field'); ?>
            <label class="ws-wizard-choice">
                <input type="radio" name="ws_hub_consent" value="1">
                <span><strong><?php esc_html_e('Sì, condividi i miei eventi', 'wsmaker'); ?></strong> — <?php esc_html_e('aumenta la visibilità, aiuta anche la bio proponente ad avere uno scopo', 'wsmaker'); ?></span>
            </label>
            <label class="ws-wizard-choice">
                <input type="radio" name="ws_hub_consent" value="0" checked>
                <span><strong><?php esc_html_e('No, resta tutto sul mio sito', 'wsmaker'); ?></strong> — <?php esc_html_e('nessun dato lascia il tuo sito', 'wsmaker'); ?></span>
            </label>

            <div class="ws-wizard-actions">
                <button type="submit" name="ws_wizard_step1_submit" value="1" class="button button-primary button-hero"><?php esc_html_e('Continua →', 'wsmaker'); ?></button>
            </div>
        </form>
        <?php
    }

    private function render_step2(): void {
        ?>
        <h1><?php esc_html_e('Casella mail per la messaggistica', 'wsmaker'); ?></h1>
        <p class="ws-wizard-lead"><?php esc_html_e('WSMaker può inviare conferme, promemoria e rispondere ai partecipanti direttamente dalla tua casella mail. Puoi configurarla ora, oppure più tardi da Impostazioni → Mail.', 'wsmaker'); ?></p>

        <form method="post">
            <?php wp_nonce_field('ws_wizard_nonce', 'ws_wizard_nonce_field'); ?>
            <div class="ws-wizard-actions">
                <button type="submit" name="ws_wizard_finish" value="1" class="button button-secondary"><?php esc_html_e('Configura dopo', 'wsmaker'); ?></button>
                <button type="submit" name="ws_wizard_finish" value="1" data-go-mail="1" onclick="document.getElementById('ws-wizard-go-mail').value='1';" class="button button-primary button-hero"><?php esc_html_e('Configura ora →', 'wsmaker'); ?></button>
                <input type="hidden" id="ws-wizard-go-mail" name="ws_go_to_mail" value="0">
            </div>
        </form>
        <?php
    }
}
