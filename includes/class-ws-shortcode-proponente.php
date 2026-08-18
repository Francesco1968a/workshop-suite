<?php

if (!defined('ABSPATH')) exit;

/**
 * Public Shortcode: [ws_proponente] / [workshop_suite_bio]
 * Renders an elegant, responsive biography card for the workshop trainer/proposer.
 */
final class WS_Shortcode_Proponente implements WS_Module {

    public function should_load(): bool {
        return true;
    }

    public function register(): void {
        add_shortcode('ws_proponente', [$this, 'render']);
        add_shortcode('workshop_suite_bio', [$this, 'render']);
    }

    public function render($atts = []): string {
        $settings = WS_Settings::get_all();

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
                            🌐 <?php esc_html_e('Sito Ufficiale', 'workshop-suite'); ?>
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

        <style>
        .ws-bio-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            max-width: 680px;
            margin: 24px auto;
            font-family: inherit;
            color: #1e293b;
        }
        .ws-bio-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 18px;
        }
        .ws-bio-avatar-wrap {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            border: 2px solid #2271b1;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ws-bio-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .ws-bio-avatar-placeholder {
            font-size: 32px;
            color: #94a3b8;
        }
        .ws-bio-titles {
            flex: 1;
        }
        .ws-bio-name {
            margin: 0 0 4px 0;
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .ws-bio-role {
            font-size: 14px;
            font-weight: 600;
            color: #2271b1;
            margin-bottom: 4px;
        }
        .ws-bio-location {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .ws-bio-languages {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .ws-bio-lang-badge {
            font-size: 11px;
            font-weight: 600;
            background: #f1f5f9;
            color: #334155;
            padding: 2px 8px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .ws-bio-content {
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 20px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }
        .ws-bio-footer {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }
        .ws-bio-socials {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .ws-bio-social-btn {
            display: inline-flex;
            align-items: center;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 4px 10px;
            border-radius: 6px;
            transition: all 0.15s ease;
        }
        .ws-bio-social-btn:hover {
            background: #0f172a;
            color: #ffffff;
            border-color: #0f172a;
        }
        .ws-bio-actions {
            display: flex;
            gap: 8px;
        }
        .ws-bio-action-btn {
            display: inline-flex;
            align-items: center;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 6px;
            transition: all 0.15s ease;
        }
        .ws-bio-site-btn {
            background: #2271b1;
            color: #ffffff;
        }
        .ws-bio-site-btn:hover {
            background: #135e96;
            color: #ffffff;
        }
        .ws-bio-wa-btn {
            background: #25d366;
            color: #ffffff;
        }
        .ws-bio-wa-btn:hover {
            background: #1ebe57;
            color: #ffffff;
        }
        </style>
        <?php
        return ob_get_clean();
    }
}
