<?php

if (!defined('ABSPATH')) exit;

/**
 * Public Shortcode: [wsma_proponente]
 * Renders an elegant, responsive biography card for the workshop trainer/proposer.
 */
final class WSMA_Shortcode_Proponente implements WSMA_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('wsma_proponente', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_style']);
    }

    /**
     * Was a ~145-line inline <style> block re-printed into the page on
     * every render() call — moved to an external, browser-cacheable file.
     * Enqueued unconditionally (not gated by has_shortcode() on
     * post_content) since the shortcode can also be dropped in via a
     * widget, template part, or another shortcode's output that
     * has_shortcode() wouldn't see — a few KB of CSS on every frontend
     * page load is a safer trade than the card silently rendering
     * unstyled on a page-builder-composed page.
     */
    public function maybe_enqueue_style(): void {
        $css_path = WSMA_PATH . 'assets/dist/proponente.css';
        if (!file_exists($css_path)) return;
        wp_enqueue_style('ws-proponente', WSMA_URL . 'assets/dist/proponente.css', [], (string) filemtime($css_path));
    }

    public function render($atts = []): string {
        $settings = WSMA_Settings::get_all();

        $nome      = $settings['proponente_nome'] ?: get_option('blogname');
        $ruolo     = $settings['proponente_ruolo'] ?: '';
        $bio       = $settings['proponente_bio'] ?: '';
        $foto      = $settings['proponente_foto'] ?: '';
        $lingue    = (array) ($settings['proponente_lingue'] ?? ['Italiano']);
        $citta     = $settings['proponente_citta'] ?: '';
        $sito      = $settings['proponente_sito'] ?: '';
        $email     = $settings['proponente_email'] ?: '';
        $telefono  = $settings['proponente_telefono'] ?: '';
        $instagram = $settings['proponente_instagram'] ?: '';
        $facebook  = $settings['proponente_facebook'] ?: '';
        $youtube   = $settings['proponente_youtube'] ?: '';
        $linkedin  = $settings['proponente_linkedin'] ?: '';
        $tiktok    = $settings['proponente_tiktok'] ?: '';
        $x_tw      = $settings['proponente_x'] ?: '';

        $flag_map = [
            'Italiano'   => '🇮🇹 Italiano',
            'Inglese'    => '🇬🇧 English',
            'Spagnolo'   => '🇪🇸 Español',
            'Francese'   => '🇫🇷 Français',
            'Tedesco'    => '🇩🇪 Deutsch',
            'Portoghese' => '🇵🇹 Português',
        ];

        ob_start();
        ?>
        <div class="ws-bio-card">
            <div class="ws-bio-header">
                <div class="ws-bio-avatar-wrap">
                    <?php if (!empty($foto)) : ?>
                        <img src="<?php echo esc_url($foto); ?>" alt="<?php echo esc_attr($nome); ?>" class="ws-bio-avatar">
                    <?php else : ?>
                        <div class="ws-bio-avatar-placeholder">👤</div>
                    <?php endif; ?>
                </div>

                <div class="ws-bio-titles">
                    <h3 class="ws-bio-name"><?php echo esc_html($nome); ?></h3>
                    <?php if (!empty($ruolo)) : ?>
                        <div class="ws-bio-role"><?php echo esc_html($ruolo); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($citta)) : ?>
                        <div class="ws-bio-location">📍 <?php echo esc_html($citta); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($lingue)) : ?>
                        <div class="ws-bio-languages">
                            <?php foreach ($lingue as $lang) : ?>
                                <span class="ws-bio-lang-badge"><?php echo esc_html($flag_map[$lang] ?? $lang); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($bio)) : ?>
                <div class="ws-bio-content">
                    <?php echo nl2br(esc_html($bio)); ?>
                </div>
            <?php endif; ?>

            <div class="ws-bio-footer">
                <div class="ws-bio-socials">
                    <?php if (!empty($instagram)) : 
                        $ig_url = strpos($instagram, 'http') === 0 ? $instagram : 'https://instagram.com/' . ltrim($instagram, '@');
                    ?>
                        <a href="<?php echo esc_url($ig_url); ?>" target="_blank" rel="noopener noreferrer" class="ws-bio-social-btn" title="Instagram">
                            Instagram
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($facebook)) : ?>
                        <a href="<?php echo esc_url($facebook); ?>" target="_blank" rel="noopener noreferrer" class="ws-bio-social-btn" title="Facebook">
                            Facebook
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($youtube)) : ?>
                        <a href="<?php echo esc_url($youtube); ?>" target="_blank" rel="noopener noreferrer" class="ws-bio-social-btn" title="YouTube">
                            YouTube
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($linkedin)) : ?>
                        <a href="<?php echo esc_url($linkedin); ?>" target="_blank" rel="noopener noreferrer" class="ws-bio-social-btn" title="LinkedIn">
                            LinkedIn
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($tiktok)) : 
                        $tt_url = strpos($tiktok, 'http') === 0 ? $tiktok : 'https://tiktok.com/@' . ltrim($tiktok, '@');
                    ?>
                        <a href="<?php echo esc_url($tt_url); ?>" target="_blank" rel="noopener noreferrer" class="ws-bio-social-btn" title="TikTok">
                            TikTok
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($x_tw)) : 
                        $x_url = strpos($x_tw, 'http') === 0 ? $x_tw : 'https://x.com/' . ltrim($x_tw, '@');
                    ?>
                        <a href="<?php echo esc_url($x_url); ?>" target="_blank" rel="noopener noreferrer" class="ws-bio-social-btn" title="X">
                            X
                        </a>
                    <?php endif; ?>
                </div>

                <div class="ws-bio-actions">
                    <?php if (!empty($sito)) : ?>
                        <a href="<?php echo esc_url($sito); ?>" target="_blank" rel="noopener noreferrer" class="ws-bio-action-btn ws-bio-site-btn">
                            🌐 <?php esc_html_e('Official Website', 'wsmaker'); ?>
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($telefono)) : 
                        $clean_tel = preg_replace('/[^0-9+]/', '', $telefono);
                    ?>
                        <a href="https://wa.me/<?php echo esc_attr(ltrim($clean_tel, '+')); ?>" target="_blank" rel="noopener noreferrer" class="ws-bio-action-btn ws-bio-wa-btn">
                            💬 WhatsApp
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        return ob_get_clean();
    }
}
