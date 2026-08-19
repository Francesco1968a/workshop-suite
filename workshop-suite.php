<?php
/**
 * Plugin Name: Workshop Suite — All-in-One Event Manager, CRM & Social Banner Builder
 * Plugin URI: https://francescoverolino.com/workshop-suite
 * Description: The complete all-in-one suite for WordPress to manage workshops, courses and events, participant CRM with timeline tracking, automatic T-15 email reminders, and social poster graphic builder.
 * Version: 1.0.0
 * Author: Francesco Verolino
 * Author URI: https://francescoverolino.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: workshop-suite
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('WS_PATH', plugin_dir_path(__FILE__));
define('WS_URL', plugin_dir_url(__FILE__));
define('WS_VERSION', '1.0.0');

// Backward-compatibility constants
if (!defined('FVW_PATH')) define('FVW_PATH', WS_PATH);
if (!defined('FVW_URL')) define('FVW_URL', WS_URL);
if (!defined('FVW_VERSION')) define('FVW_VERSION', WS_VERSION);

add_action('init', function () {
    load_plugin_textdomain('workshop-suite', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Inject Custom CSS in frontend and admin head
add_action('wp_head', function () {
    $custom_css = WS_Settings::get('custom_css', '');
    if (!empty($custom_css)) {
        echo '<style id="ws-custom-css">' . wp_strip_all_tags($custom_css) . '</style>' . "\n";
    }
});
add_action('admin_head', function () {
    $custom_css = WS_Settings::get('custom_css', '');
    if (!empty($custom_css)) {
        echo '<style id="ws-custom-css">' . wp_strip_all_tags($custom_css) . '</style>' . "\n";
    }
});

// Pure-PHP IMAP client (webklex/php-imap)
if (file_exists(WS_PATH . 'vendor/autoload.php')) {
    require_once WS_PATH . 'vendor/autoload.php';
}

/**
 * Modules implement should_load() + register()
 */
interface WS_Module {
    public function should_load(): bool;
    public function register(): void;
}
if (!interface_exists('FVW_Module')) {
    interface_FVW_Module_shim:
    class_alias('WS_Module', 'FVW_Module');
}

final class WS_Plugin {
    /** @var WS_Module[] */
    private array $modules = [];

    public function boot(): void {
        $this->modules = [
            new WS_Post_Types(),
            new WS_Rest_Partecipanti(),
            new WS_Shortcode_Partecipanti_Lista(),
            new WS_Rest_Partecipante(),
            new WS_Shortcode_Partecipante(),
            new WS_Rest_Archivio(),
            new WS_Shortcode_Archivio(),
            new WS_Rest_Calendario(),
            new WS_Shortcode_Calendario(),
            new WS_Ics_Feed(),
            new WS_Rest_Riepilogo(),
            new WS_Shortcode_Riepilogo(),
            new WS_Rest_Admin(),
            new WS_Shortcode_Admin(),
            new WS_Admin_Nav_Filter(),
            new WS_T15_Reminder(),
            new WS_Rest_Messaggi(),
            new WS_Rest_Mail_Inbox(),
            new WS_Shortcode_Messaggi(),
            new WS_Mail_Poller(),
            new WS_Shortcode_Prossimo(),
            new WS_Shortcode_Eventi_Categoria(),
            new WS_Shortcode_Programma(),
            new WS_Fluent_Forms_Intake(),
            new WS_Rest_Intake(),
            new WS_Shortcode_Form_Iscrizione(),
            new WS_Ringraziamento(),
            new WS_Shortcode_Ringraziamento(),
            new WS_Shortcode_Locandine(),
            new WS_Shortcode_Proponente(),
            new WS_Mail_Templates(),
            new WS_I18n(),
            new WS_Admin_Settings_Page(),
            new WS_License_Manager(),
            new WS_Hub_Sync(),
            new WS_Onboarding_Wizard(),
        ];
        foreach ($this->modules as $module) {
            if ($module->should_load()) {
                $module->register();
            }
        }
    }
}

// Load Class Includes
require_once WS_PATH . 'includes/class-ws-i18n.php';
require_once WS_PATH . 'includes/class-ws-settings.php';
require_once WS_PATH . 'includes/class-ws-license-manager.php';
require_once WS_PATH . 'includes/class-ws-admin-settings-page.php';
require_once WS_PATH . 'includes/class-ws-post-types.php';
require_once WS_PATH . 'includes/class-ws-data.php';
require_once WS_PATH . 'includes/class-ws-shortcode-base.php';
require_once WS_PATH . 'includes/class-ws-rest-partecipanti.php';
require_once WS_PATH . 'includes/class-ws-shortcode-partecipanti-lista.php';
require_once WS_PATH . 'includes/class-ws-rest-partecipante.php';
require_once WS_PATH . 'includes/class-ws-shortcode-partecipante.php';
require_once WS_PATH . 'includes/class-ws-rest-archivio.php';
require_once WS_PATH . 'includes/class-ws-shortcode-archivio.php';
require_once WS_PATH . 'includes/class-ws-rest-calendario.php';
require_once WS_PATH . 'includes/class-ws-shortcode-calendario.php';
require_once WS_PATH . 'includes/class-ws-ics-feed.php';
require_once WS_PATH . 'includes/class-ws-rest-riepilogo.php';
require_once WS_PATH . 'includes/class-ws-shortcode-riepilogo.php';
require_once WS_PATH . 'includes/class-ws-rest-admin.php';
require_once WS_PATH . 'includes/class-ws-shortcode-admin.php';
require_once WS_PATH . 'includes/class-ws-admin-nav-filter.php';
require_once WS_PATH . 'includes/class-ws-t15-reminder.php';
require_once WS_PATH . 'includes/class-ws-rest-messaggi.php';
require_once WS_PATH . 'includes/class-ws-mail-inbox.php';
require_once WS_PATH . 'includes/class-ws-rest-mail-inbox.php';
require_once WS_PATH . 'includes/class-ws-shortcode-messaggi.php';
require_once WS_PATH . 'includes/class-ws-mail-poller.php';
require_once WS_PATH . 'includes/class-ws-shortcode-prossimo.php';
require_once WS_PATH . 'includes/class-ws-shortcode-eventi-categoria.php';
require_once WS_PATH . 'includes/class-ws-shortcode-programma.php';
require_once WS_PATH . 'includes/class-ws-fluent-forms-intake.php';
require_once WS_PATH . 'includes/class-ws-rest-intake.php';
require_once WS_PATH . 'includes/class-ws-shortcode-form-iscrizione.php';
require_once WS_PATH . 'includes/class-ws-ringraziamento.php';
require_once WS_PATH . 'includes/class-ws-shortcode-ringraziamento.php';
require_once WS_PATH . 'includes/class-ws-shortcode-locandine.php';
require_once WS_PATH . 'includes/class-ws-shortcode-proponente.php';
require_once WS_PATH . 'includes/class-ws-mail-templates.php';
require_once WS_PATH . 'includes/class-ws-hub-sync.php';
require_once WS_PATH . 'includes/class-ws-taxonomy-registry.php';
require_once WS_PATH . 'includes/class-ws-rest-modules-toggle.php';
require_once WS_PATH . 'includes/class-ws-onboarding-wizard.php';

WS_Rest_Modules_Toggle::init();

register_activation_hook(__FILE__, ['WS_Onboarding_Wizard', 'on_activate']);

// Class Aliases for full backward compatibility
class_alias('WS_Data', 'FVW_Data');
class_alias('WS_Settings', 'FVW_Settings');
class_alias('WS_Mail_Inbox', 'FVW_Mail_Inbox');
class_alias('WS_License_Manager', 'FVW_License_Manager');
class_alias('WS_T15_Reminder', 'FVW_T15_Reminder');
class_alias('WS_Mail_Templates', 'FVW_Mail_Templates');
class_alias('WS_Mail_Poller', 'FVW_Mail_Poller');
class_alias('WS_Fluent_Forms_Intake', 'FVW_Fluent_Forms_Intake');
class_alias('WS_Ringraziamento', 'FVW_Ringraziamento');
class_alias('WS_Post_Types', 'FVW_Post_Types');
class_alias('WS_Ics_Feed', 'FVW_Ics_Feed');
class_alias('WS_Rest_Admin', 'FVW_Rest_Admin');
class_alias('WS_Rest_Riepilogo', 'FVW_Rest_Riepilogo');
class_alias('WS_Rest_Partecipanti', 'FVW_Rest_Partecipanti');
class_alias('WS_Rest_Partecipante', 'FVW_Rest_Partecipante');
class_alias('WS_Rest_Messaggi', 'FVW_Rest_Messaggi');
class_alias('WS_Rest_Archivio', 'FVW_Rest_Archivio');
class_alias('WS_Rest_Calendario', 'FVW_Rest_Calendario');
class_alias('WS_Rest_Intake', 'FVW_Rest_Intake');
class_alias('WS_Rest_Mail_Inbox', 'FVW_Rest_Mail_Inbox');

add_action('plugins_loaded', function () {
    // Bootstrap
    (new WS_Plugin())->boot();
    WS_Taxonomy_Registry::init();
});
