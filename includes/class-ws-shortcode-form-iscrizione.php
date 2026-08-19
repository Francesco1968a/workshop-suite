<?php

if (!defined('ABSPATH')) exit;

/**
 * Shortcode [fv_form_iscrizione] or [fvw_form_iscrizione]
 * Renders a standalone, responsive registration form for workshops.
 */
final class WS_Shortcode_Form_Iscrizione implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('fv_form_iscrizione', [$this, 'render_shortcode']);
        add_shortcode('fvw_form_iscrizione', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void {
        if (is_admin()) return;
        
        wp_register_style(
            'ws-form-iscrizione',
            WS_URL . 'assets/css/ws-form-iscrizione.css',
            [],
            WS_VERSION
        );

        wp_register_script(
            'ws-form-iscrizione',
            WS_URL . 'assets/js/ws-form-iscrizione.js',
            [],
            WS_VERSION,
            true
        );

        $form_iscrizione_vars = [
            'restUrl' => esc_url_raw(rest_url('workshop-suite/v1/iscrizione/invia')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'i18n'    => [
                'sending' => __('Invio in corso...', 'workshop-suite'),
                'success' => __('Richiesta inviata con successo!', 'workshop-suite'),
                'error'   => __('Si è verificato un errore. Riprova più tardi.', 'workshop-suite'),
            ],
        ];
        wp_localize_script('ws-form-iscrizione', 'WS_Form_Vars', $form_iscrizione_vars);
        wp_localize_script('ws-form-iscrizione', 'FVW_Form_Vars', $form_iscrizione_vars);
    }

    public function render_shortcode($atts = []): string {
        $atts = is_array($atts) ? $atts : [];

        wp_enqueue_style('ws-form-iscrizione');
        wp_enqueue_script('ws-form-iscrizione');

        $atts = shortcode_atts([
            'evento_id' => 0,
        ], $atts, 'fv_form_iscrizione');

        $evento_id = (int) $atts['evento_id'];
        
        // Se non specificato nello shortcode, prova a rilevare l'evento o la categoria dalla pagina corrente
        global $post;
        if (!$evento_id && $post) {
            if ($post->post_type === 'evento') {
                $evento_id = $post->ID;
            } else {
                $url = get_permalink($post->ID);
                $term = WS_Data::find_categoria_by_url($url);
                if ($term) {
                    $eventi = WS_Fluent_Forms_Intake::eventi_per_categoria_array($term->term_id);
                    if (!empty($eventi)) {
                        $evento_id = $eventi[0]['id'];
                    }
                }
            }
        }

        ob_start();
        ?>
        <div class="ws-form-wrapper" id="ws-form-container">
            <form id="ws-registration-form" class="ws-form">
                <input type="hidden" name="evento_id" value="<?php echo esc_attr($evento_id); ?>">
                
                <!-- Honeypot anti-spam -->
                <div class="ws-honeypot" aria-hidden="true">
                    <input type="text" name="website_url" tabindex="-1" autocomplete="off">
                </div>

                <div class="ws-form-row ws-form-row-2">
                    <div class="ws-form-group">
                        <label for="ws-nome"><?php esc_html_e('Nome', 'workshop-suite'); ?> <span class="req">*</span></label>
                        <input type="text" id="ws-nome" name="nome" class="ws-input" required placeholder="Es. Mario">
                    </div>
                    <div class="ws-form-group">
                        <label for="ws-cognome"><?php esc_html_e('Cognome', 'workshop-suite'); ?> <span class="req">*</span></label>
                        <input type="text" id="ws-cognome" name="cognome" class="ws-input" required placeholder="Es. Rossi">
                    </div>
                </div>

                <div class="ws-form-row ws-form-row-2">
                    <div class="ws-form-group">
                        <label for="ws-email"><?php esc_html_e('Email', 'workshop-suite'); ?> <span class="req">*</span></label>
                        <input type="email" id="ws-email" name="email" class="ws-input" required placeholder="mario.rossi@email.com">
                    </div>
                    <div class="ws-form-group">
                        <label for="ws-telefono"><?php esc_html_e('Telefono / WhatsApp', 'workshop-suite'); ?></label>
                        <input type="tel" id="ws-telefono" name="telefono" class="ws-input" placeholder="+39 333 1234567">
                    </div>
                </div>

                <div class="ws-form-row ws-form-row-2">
                    <div class="ws-form-group">
                        <label for="ws-citta"><?php esc_html_e('Città di provenienza', 'workshop-suite'); ?></label>
                        <input type="text" id="ws-citta" name="citta" class="ws-input" placeholder="Es. Milano">
                    </div>
                    <div class="ws-form-group">
                        <label for="ws-persone"><?php esc_html_e('Numero persone', 'workshop-suite'); ?></label>
                        <select id="ws-persone" name="numero_persone" class="ws-select">
                            <option value="1">1 persona</option>
                            <option value="2">2 persone</option>
                            <option value="3">3 persone</option>
                            <option value="4">4+ persone</option>
                        </select>
                    </div>
                </div>

                <div class="ws-form-group">
                    <label for="ws-messaggio"><?php esc_html_e('Messaggio / Richiesta informazioni', 'workshop-suite'); ?></label>
                    <textarea id="ws-messaggio" name="messaggio" class="ws-textarea" rows="4" placeholder="<?php esc_attr_e('Scrivi qui le tue domande o note particolari...', 'workshop-suite'); ?>"></textarea>
                </div>

                <div class="ws-form-actions">
                    <button type="submit" class="ws-btn-submit" id="ws-submit-btn">
                        <span><?php esc_html_e('Invia Richiesta Iscrizione', 'workshop-suite'); ?></span>
                    </button>
                </div>

                <div id="ws-form-message" class="ws-form-response"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
