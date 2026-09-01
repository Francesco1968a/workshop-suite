<?php
/**
 * Plugin Name: WSMaker — All-in-One Event Manager, CRM & Social Banner Builder
 * Plugin URI: https://wsmaker.pro/
 * Description: The complete all-in-one suite for WordPress to manage workshops, courses and events, participant CRM with timeline tracking, automatic T-15 email reminders, and social poster graphic builder.
 * Version: 1.1.4
 * Author: Francesco Verolino
 * Author URI: https://francescoverolino.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wsmaker
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) exit;

define('WSMA_PATH', plugin_dir_path(__FILE__));
define('WSMA_URL', plugin_dir_url(__FILE__));
define('WSMA_VERSION', '1.1.4');

// Translations are loaded automatically by WordPress.org since WP 4.6 —
// no load_plugin_textdomain() call needed for a wordpress.org-hosted plugin.


// Pure-PHP IMAP client (webklex/php-imap)
if (file_exists(WSMA_PATH . 'vendor/autoload.php')) {
    require_once WSMA_PATH . 'vendor/autoload.php';
}

/**
 * Modules implement should_load() + register()
 */
interface WSMA_Module {
    public function should_load(): bool;
    public function register(): void;
}
final class WSMA_Plugin {
    /** @var WSMA_Module[] */
    private array $modules = [];

    public function boot(): void {
        $this->modules = [
            new WSMA_Post_Types(),
            new WSMA_Rest_Partecipanti(),
            new WSMA_Shortcode_Partecipanti_Lista(),
            new WSMA_Rest_Partecipante(),
            new WSMA_Shortcode_Partecipante(),
            new WSMA_Rest_Archivio(),
            new WSMA_Shortcode_Archivio(),
            new WSMA_Rest_Calendario(),
            new WSMA_Shortcode_Calendario(),
            new WSMA_Ics_Feed(),
            new WSMA_Rest_Riepilogo(),
            new WSMA_Shortcode_Riepilogo(),
            new WSMA_Rest_Admin(),
            new WSMA_Shortcode_Admin(),
            new WSMA_Shortcode_Categorie(),
            new WSMA_Shortcode_Aula_Virtuale(),
            new WSMA_Shortcode_Workshop_Page(),
            new WSMA_Shortcode_Workshop_Text(),
            new WSMA_Admin_Nav_Filter(),
            new WSMA_T15_Reminder(),
            new WSMA_Rest_Messaggi(),
            new WSMA_Rest_Mail_Inbox(),
            new WSMA_Shortcode_Messaggi(),
            new WSMA_Mail_Poller(),
            new WSMA_Webhooks(),
            new WSMA_Iscrizioni_List(),
            new WSMA_Shortcode_Prossimo(),
            new WSMA_Shortcode_Prezzo(),
            new WSMA_Shortcode_Eventi_Categoria(),
            new WSMA_Shortcode_Programma(),
            new WSMA_Shortcode_Acquista(),
            new WSMA_Fluent_Forms_Intake(),
            new WSMA_Rest_Intake(),
            new WSMA_Shortcode_Form_Iscrizione(),
            new WSMA_Ringraziamento(),
            new WSMA_Shortcode_Ringraziamento(),
            new WSMA_Shortcode_Locandine(),
            new WSMA_Shortcode_Proponente(),
            new WSMA_Mail_Templates(),
            new WSMA_I18n(),
            new WSMA_Admin_Settings_Page(),
            new WSMA_License_Manager(),
            new WSMA_Hub_Sync(),
            new WSMA_Onboarding_Wizard(),
        ];
        foreach ($this->modules as $module) {
            if ($module->should_load()) {
                $module->register();
            }
        }
    }
}

// Load Class Includes
require_once WSMA_PATH . 'includes/class-ws-i18n.php';
require_once WSMA_PATH . 'includes/class-ws-settings.php';
require_once WSMA_PATH . 'includes/class-ws-license-manager.php';
require_once WSMA_PATH . 'includes/class-ws-admin-settings-page.php';
require_once WSMA_PATH . 'includes/class-ws-post-types.php';
require_once WSMA_PATH . 'includes/class-ws-data.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-base.php';
require_once WSMA_PATH . 'includes/class-ws-rest-partecipanti.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-partecipanti-lista.php';
require_once WSMA_PATH . 'includes/class-ws-rest-partecipante.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-partecipante.php';
require_once WSMA_PATH . 'includes/class-ws-rest-archivio.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-archivio.php';
require_once WSMA_PATH . 'includes/class-ws-rest-calendario.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-calendario.php';
require_once WSMA_PATH . 'includes/class-ws-ics-feed.php';
require_once WSMA_PATH . 'includes/class-ws-rest-riepilogo.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-riepilogo.php';
require_once WSMA_PATH . 'includes/class-ws-rest-admin.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-admin.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-categorie.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-aula-virtuale.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-workshop-page.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-workshop-text.php';
require_once WSMA_PATH . 'includes/class-ws-webhooks.php';
require_once WSMA_PATH . 'includes/class-ws-iscrizioni-list.php';
require_once WSMA_PATH . 'includes/class-ws-admin-nav-filter.php';
require_once WSMA_PATH . 'includes/class-ws-t15-reminder.php';
require_once WSMA_PATH . 'includes/class-ws-rest-messaggi.php';
require_once WSMA_PATH . 'includes/class-ws-mail-inbox.php';
require_once WSMA_PATH . 'includes/class-ws-rest-mail-inbox.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-messaggi.php';
require_once WSMA_PATH . 'includes/class-ws-mail-poller.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-prossimo.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-prezzo.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-eventi-categoria.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-programma.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-acquista.php';
require_once WSMA_PATH . 'includes/class-ws-fluent-forms-intake.php';
require_once WSMA_PATH . 'includes/class-ws-rest-intake.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-form-iscrizione.php';
require_once WSMA_PATH . 'includes/class-ws-ringraziamento.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-ringraziamento.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-locandine.php';
require_once WSMA_PATH . 'includes/class-ws-shortcode-proponente.php';
require_once WSMA_PATH . 'includes/class-ws-mail-templates.php';
require_once WSMA_PATH . 'includes/class-ws-hub-sync.php';
require_once WSMA_PATH . 'includes/class-ws-taxonomy-registry.php';
require_once WSMA_PATH . 'includes/class-ws-rest-modules-toggle.php';
require_once WSMA_PATH . 'includes/class-ws-onboarding-wizard.php';

WSMA_Rest_Modules_Toggle::init();

register_activation_hook(__FILE__, ['WSMA_Onboarding_Wizard', 'on_activate']);

add_action('plugins_loaded', function () {
    // Bootstrap
    (new WSMA_Plugin())->boot();
    WSMA_Taxonomy_Registry::init();
});
