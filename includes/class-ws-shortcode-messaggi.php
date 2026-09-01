<?php

if (!defined('ABSPATH')) exit;

/**
 * Standalone Messaggi panel — replaces the legacy `workshop_mail_inbox`
 * (AI draft inbox) on its own page, per the user's request to pull the
 * messaging system out of the Dashboard tabs entirely ("troppo casino").
 * List-of-contacts + detail pane, modeled visually on the legacy Mail Box
 * layout the user liked, but organized by nominativo instead of mail type.
 */
final class WSMA_Shortcode_Messaggi extends WSMA_Shortcode_Base {

    protected function handle(): string  { return 'ws-messaggi'; }
    protected function js_file(): string  { return 'assets/dist/messaggi.js'; }
    protected function css_file(): string { return 'assets/dist/messaggi.css'; }
    protected function app_id(): string   { return 'ws-messaggi-app'; }

    protected function access_denied_html(): string {
        return '<p>Accesso riservato.</p>';
    }

    public function register(): void {
        $this->add_shortcodes('workshop_messaggi');
        $this->add_module_type_filter();
    }
}
