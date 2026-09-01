<?php

if (!defined('ABSPATH')) exit;

/**
 * Shortcode [ws_form_iscrizione] — [fv_form_iscrizione] and
 * [fvw_form_iscrizione] remain registered as permanent aliases for
 * backward compatibility with existing page content.
 * Renders a standalone, responsive registration form for workshops.
 */
final class WSMA_Shortcode_Form_Iscrizione implements WSMA_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('wsma_form_iscrizione', [$this, 'render_shortcode']);
        add_shortcode('ws_form_iscrizione', [$this, 'render_shortcode']); // legacy alias, existing page content
        add_shortcode('fv_form_iscrizione', [$this, 'render_shortcode']); // legacy alias, existing page content
        add_shortcode('fvw_form_iscrizione', [$this, 'render_shortcode']); // legacy alias, existing page content
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void {
        if (is_admin()) return;
        
        $css_path = WSMA_PATH . 'assets/css/ws-form-iscrizione.css';
        $js_path = WSMA_PATH . 'assets/js/ws-form-iscrizione.js';

        wp_register_style(
            'ws-form-iscrizione',
            WSMA_URL . 'assets/css/ws-form-iscrizione.css',
            [],
            file_exists($css_path) ? (string) filemtime($css_path) : WSMA_VERSION
        );

        wp_register_script(
            'ws-form-iscrizione',
            WSMA_URL . 'assets/js/ws-form-iscrizione.js',
            [],
            file_exists($js_path) ? (string) filemtime($js_path) : WSMA_VERSION,
            true
        );

        $form_iscrizione_vars = [
            'restUrl' => esc_url_raw(rest_url('workshop-suite/v1/iscrizione/invia')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'i18n'    => [
                'sending' => __('Invio in corso...', 'wsmaker'),
                'success' => __('Richiesta inviata con successo!', 'wsmaker'),
                'error'   => __('Si è verificato un errore. Riprova più tardi.', 'wsmaker'),
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
            'categoria' => '',
        ], $atts, 'fv_form_iscrizione');

        $evento_id = (int) $atts['evento_id'];

        // Categoria esplicita (attributo) o rilevata dalla pagina corrente.
        $categoria_term = null;
        if ($atts['categoria']) {
            $categoria_term = get_term_by('slug', sanitize_title($atts['categoria']), 'wsma_categoria_evento');
            if (is_wp_error($categoria_term)) $categoria_term = null;
        }

        global $post;
        if (!$categoria_term && !$evento_id && $post) {
            if ($post->post_type === 'wsma_evento') {
                $evento_id = $post->ID;
            } else {
                $url = get_permalink($post->ID);
                $categoria_term = WSMA_Data::find_categoria_by_url($url);
            }
        }

        // Se la pagina/attributo è legata a una categoria, filtra il dropdown
        // "Data di interesse" solo sugli eventi di quella categoria.
        $eventi_categoria = [];
        if ($categoria_term) {
            $eventi_categoria = WSMA_Fluent_Forms_Intake::eventi_per_categoria_array($categoria_term->term_id);
            if (!$evento_id && !empty($eventi_categoria)) {
                $evento_id = $eventi_categoria[0]['id'];
            }
        }
        $show_dropdown = count($eventi_categoria) > 1;

        $theme = class_exists('WSMA_Settings') ? WSMA_Settings::get('default_theme_mode', 'dark') : 'dark';
        $theme = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';

        ob_start();
        ?>
        <div class="ws-theme-wrapper ws-theme-<?php echo esc_attr($theme); ?>">
        <div class="ws-form-wrapper" id="ws-form-container">
            <form id="ws-registration-form" class="ws-form">
                <?php if ($show_dropdown): ?>
                    <div class="ws-form-group">
                        <label for="ws-evento-id"><?php esc_html_e('Data di interesse', 'wsmaker'); ?> <span class="req">*</span></label>
                        <select id="ws-evento-id" name="evento_id" class="ws-select" required>
                            <option value=""><?php esc_html_e('Seleziona una data', 'wsmaker'); ?></option>
                            <?php foreach ($eventi_categoria as $ev): ?>
                                <option value="<?php echo esc_attr($ev['id']); ?>" <?php selected($evento_id, $ev['id']); ?>><?php echo esc_html($ev['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="evento_id" value="<?php echo esc_attr($evento_id); ?>">
                <?php endif; ?>

                <!-- Honeypot anti-spam -->
                <div class="ws-honeypot" aria-hidden="true">
                    <input type="text" name="website_url" tabindex="-1" autocomplete="off">
                </div>

                <div class="ws-form-row ws-form-row-2">
                    <div class="ws-form-group">
                        <label for="ws-nome"><?php esc_html_e('Nome', 'wsmaker'); ?> <span class="req">*</span></label>
                        <input type="text" id="ws-nome" name="nome" class="ws-input" autocomplete="off" required placeholder="Es. Mario">
                    </div>
                    <div class="ws-form-group">
                        <label for="ws-cognome"><?php esc_html_e('Cognome', 'wsmaker'); ?> <span class="req">*</span></label>
                        <input type="text" id="ws-cognome" name="cognome" class="ws-input" autocomplete="off" required placeholder="Es. Rossi">
                    </div>
                </div>

                <div class="ws-form-row ws-form-row-2">
                    <div class="ws-form-group">
                        <label for="ws-email"><?php esc_html_e('Email', 'wsmaker'); ?> <span class="req">*</span></label>
                        <input type="email" id="ws-email" name="email" class="ws-input" autocomplete="off" required placeholder="mario.rossi@email.com">
                    </div>
                    <div class="ws-form-group">
                        <label for="ws-telefono"><?php esc_html_e('Telefono / WhatsApp', 'wsmaker'); ?></label>
                        <input type="tel" id="ws-telefono" name="telefono" class="ws-input" autocomplete="off" placeholder="+39 333 1234567">
                    </div>
                </div>

                <div class="ws-form-row ws-form-row-2">
                    <div class="ws-form-group">
                        <label for="ws-citta"><?php esc_html_e('Città di provenienza', 'wsmaker'); ?></label>
                        <input type="text" id="ws-citta" name="citta" class="ws-input" autocomplete="off" placeholder="Es. Milano">
                    </div>
                    <div class="ws-form-group">
                        <label for="ws-persone"><?php esc_html_e('Numero persone', 'wsmaker'); ?></label>
                        <select id="ws-persone" name="numero_persone" class="ws-select">
                            <option value="1">1 persona</option>
                            <option value="2">2 persone</option>
                            <option value="3">3 persone</option>
                            <option value="4">4+ persone</option>
                        </select>
                    </div>
                </div>

                <div class="ws-form-group">
                    <label for="ws-messaggio"><?php esc_html_e('Messaggio / Richiesta informazioni', 'wsmaker'); ?> <span class="req">*</span></label>
                    <textarea id="ws-messaggio" name="messaggio" class="ws-textarea" rows="4" required placeholder="<?php esc_attr_e('Scrivi qui le tue domande o note particolari...', 'wsmaker'); ?>"></textarea>
                </div>

                <div class="ws-form-actions">
                    <button type="submit" class="ws-btn-submit" id="ws-submit-btn">
                        <span><?php esc_html_e('Invia Richiesta Iscrizione', 'wsmaker'); ?></span>
                    </button>
                </div>

                <div id="ws-form-message" class="ws-form-response"></div>
            </form>
        </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
