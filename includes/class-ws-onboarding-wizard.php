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
final class WS_Onboarding_Wizard implements WS_Module {

    private const OPTION_COMPLETED = 'ws_onboarding_completed';
    private const TRANSIENT_SHOW = 'ws_show_onboarding';
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
        add_action('admin_menu', [$this, 'add_hidden_page']);
    }

    public function maybe_redirect(): void {
        if (!get_transient(self::TRANSIENT_SHOW)) return;
        if (!current_user_can('manage_options')) return;
        if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON) || wp_doing_cron()) return;
        if (isset($_GET['page']) && $_GET['page'] === self::PAGE_SLUG) return;

        delete_transient(self::TRANSIENT_SHOW);
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE_SLUG));
        exit;
    }

    /** Hidden page — not added to any menu, only reachable via the redirect above or its own URL. */
    public function add_hidden_page(): void {
        add_submenu_page(
            null,
            __('Configurazione guidata', 'workshop-suite'),
            '',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void {
        if (!current_user_can('manage_options')) return;

        $step = isset($_GET['step']) && $_GET['step'] === '2' ? 2 : 1;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ws_wizard_nonce_field'])) {
            if (!wp_verify_nonce($_POST['ws_wizard_nonce_field'], 'ws_wizard_nonce')) {
                wp_die(esc_html__('Richiesta non valida, riprova.', 'workshop-suite'));
            }

            if (isset($_POST['ws_wizard_step1_submit'])) {
                $settings = WS_Settings::get_all();
                $settings['active_modules']['global_hub_pro'] = !empty($_POST['ws_hub_consent']) ? 1 : 0;
                WS_Settings::update_all($settings);
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

        $asset_css = WS_PATH . 'assets/dist/admin.css';
        if (file_exists($asset_css)) {
            wp_enqueue_style('fvw-admin-settings-css', WS_URL . 'assets/dist/admin.css', [], (string) filemtime($asset_css));
        }

        ?>
        <div class="wrap fvw-wizard-wrap">
            <div class="fvw-wizard-box">
                <div class="fvw-wizard-steps">
                    <span class="fvw-wizard-step<?php echo $step === 1 ? ' fvw-wizard-step--active' : ''; ?>">1. <?php esc_html_e('Condivisione dati', 'workshop-suite'); ?></span>
                    <span class="fvw-wizard-step<?php echo $step === 2 ? ' fvw-wizard-step--active' : ''; ?>">2. <?php esc_html_e('Casella mail', 'workshop-suite'); ?></span>
                </div>

                <?php if ($step === 1): $this->render_step1(); else: $this->render_step2(); endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_step1(): void {
        ?>
        <h1><?php esc_html_e('Benvenuto in Workshop Suite 👋', 'workshop-suite'); ?></h1>
        <p class="fvw-wizard-lead"><?php esc_html_e('Prima di iniziare, una scelta che riguarda solo te: vuoi condividere i tuoi eventi pubblicati con la directory pubblica di Workshop Suite?', 'workshop-suite'); ?></p>

        <div class="fvw-wizard-disclosure">
            <p><strong><?php esc_html_e('Cosa viene inviato, se accetti:', 'workshop-suite'); ?></strong></p>
            <p><?php esc_html_e('Titolo, descrizione, date e luogo, categoria, prezzo e acconto, posti disponibili, immagine in evidenza, URL della pagina di prenotazione, e il tuo profilo pubblico da proponente (nome, ruolo, bio, foto, sito, lingue) come lo configurerai nelle impostazioni.', 'workshop-suite'); ?></p>
            <p><?php esc_html_e('Va a workshopsuite.pro, la directory pubblica del progetto Workshop Suite — serve a farti trovare da chi cerca corsi come i tuoi. Puoi cambiare questa scelta in qualsiasi momento da Impostazioni → Moduli & Add-ons.', 'workshop-suite'); ?></p>
        </div>

        <form method="post">
            <?php wp_nonce_field('ws_wizard_nonce', 'ws_wizard_nonce_field'); ?>
            <label class="fvw-wizard-choice">
                <input type="radio" name="ws_hub_consent" value="1">
                <span><strong><?php esc_html_e('Sì, condividi i miei eventi', 'workshop-suite'); ?></strong> — <?php esc_html_e('aumenta la visibilità, aiuta anche la bio proponente ad avere uno scopo', 'workshop-suite'); ?></span>
            </label>
            <label class="fvw-wizard-choice">
                <input type="radio" name="ws_hub_consent" value="0" checked>
                <span><strong><?php esc_html_e('No, resta tutto sul mio sito', 'workshop-suite'); ?></strong> — <?php esc_html_e('nessun dato lascia il tuo sito', 'workshop-suite'); ?></span>
            </label>

            <div class="fvw-wizard-actions">
                <button type="submit" name="ws_wizard_step1_submit" value="1" class="button button-primary button-hero"><?php esc_html_e('Continua →', 'workshop-suite'); ?></button>
            </div>
        </form>
        <?php
    }

    private function render_step2(): void {
        ?>
        <h1><?php esc_html_e('Casella mail per la messaggistica', 'workshop-suite'); ?></h1>
        <p class="fvw-wizard-lead"><?php esc_html_e('Workshop Suite può inviare conferme, promemoria e rispondere ai partecipanti direttamente dalla tua casella mail. Puoi configurarla ora, oppure più tardi da Impostazioni → Mail.', 'workshop-suite'); ?></p>

        <form method="post">
            <?php wp_nonce_field('ws_wizard_nonce', 'ws_wizard_nonce_field'); ?>
            <div class="fvw-wizard-actions">
                <button type="submit" name="ws_wizard_finish" value="1" class="button button-secondary"><?php esc_html_e('Configura dopo', 'workshop-suite'); ?></button>
                <button type="submit" name="ws_wizard_finish" value="1" data-go-mail="1" onclick="document.getElementById('fvw-wizard-go-mail').value='1';" class="button button-primary button-hero"><?php esc_html_e('Configura ora →', 'workshop-suite'); ?></button>
                <input type="hidden" id="fvw-wizard-go-mail" name="ws_go_to_mail" value="0">
            </div>
        </form>
        <?php
    }
}
