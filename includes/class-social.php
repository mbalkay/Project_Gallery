<?php
/**
 * Project Gallery Social Sharing System
 * Advanced social media integration and sharing features
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProjectGallerySocial {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_social_scripts'));
        add_action('wp_head', array($this, 'add_social_meta_tags'));
        add_action('wp_footer', array($this, 'add_social_sharing_modal'));
        add_shortcode('project_social_share', array($this, 'social_share_shortcode'));
        add_action('wp_ajax_share_project', array($this, 'track_project_share'));
        add_action('wp_ajax_nopriv_share_project', array($this, 'track_project_share'));
        add_action('wp_ajax_generate_share_image', array($this, 'generate_share_image'));
    }
    
    /**
     * Enqueue social sharing scripts
     */
    public function enqueue_social_scripts() {
        if (is_singular('proje')) {
            wp_enqueue_script(
                'project-gallery-social',
                PROJECT_GALLERY_PLUGIN_URL . 'assets/js/social.js',
                array('jquery'),
                PROJECT_GALLERY_VERSION,
                true
            );
            
            wp_localize_script('project-gallery-social', 'projectGallerySocial', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('project_gallery_social_nonce'),
                'project_id' => get_the_ID(),
                'project_title' => get_the_title(),
                'project_url' => get_permalink(),
                'project_image' => get_the_post_thumbnail_url(get_the_ID(), 'large'),
                'site_name' => get_bloginfo('name'),
                'strings' => array(
                    'share_success' => __('Successfully shared!', 'project-gallery'),
                    'copy_success' => __('Link copied to clipboard!', 'project-gallery'),
                    'share_error' => __('Error sharing project.', 'project-gallery')
                )
            ));
        }
    }
    
    /**
     * Add social meta tags for better sharing
     */
    public function add_social_meta_tags() {
        if (!is_singular('proje')) {
            return;
        }
        
        global $post;
        
        $project_title = get_the_title();
        $project_description = get_the_excerpt() ?: wp_trim_words(get_the_content(), 30);
        $project_image = get_the_post_thumbnail_url($post->ID, 'large');
        $project_url = get_permalink();
        $site_name = get_bloginfo('name');
        
        // Open Graph meta tags
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($project_title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($project_description) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($project_url) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
        
        if ($project_image) {
            echo '<meta property="og:image" content="' . esc_url($project_image) . '">' . "\n";
            echo '<meta property="og:image:width" content="1200">' . "\n";
            echo '<meta property="og:image:height" content="630">' . "\n";
        }
        
        // Twitter Card meta tags
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($project_title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($project_description) . '">' . "\n";
        
        if ($project_image) {
            echo '<meta name="twitter:image" content="' . esc_url($project_image) . '">' . "\n";
        }
        
        // Pinterest meta tags
        if ($project_image) {
            echo '<meta property="og:image:alt" content="' . esc_attr($project_title) . '">' . "\n";
        }
        
        // LinkedIn meta tags
        echo '<meta property="article:author" content="' . esc_attr(get_the_author()) . '">' . "\n";
        echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c')) . '">' . "\n";
        
        // WhatsApp sharing optimization
        echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '">' . "\n";
        
        // Schema.org structured data
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $project_title,
            'description' => $project_description,
            'url' => $project_url,
            'image' => $project_image,
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author()
            ),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => $site_name,
                'url' => home_url()
            )
        );
        
        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
    
    /**
     * Add social sharing modal
     */
    public function add_social_sharing_modal() {
        if (!is_singular('proje')) {
            return;
        }
        
        ?>
        <div id="social-share-modal" class="social-share-modal" style="display: none;">
            <div class="social-share-modal-content">
                <div class="social-share-header">
                    <h3>📤 Projeyi Paylaş</h3>
                    <span class="social-share-close">&times;</span>
                </div>
                
                <div class="social-share-body">
                    <div class="social-share-platforms">
                        <button class="social-share-btn facebook" data-platform="facebook">
                            📘 Facebook
                        </button>
                        <button class="social-share-btn twitter" data-platform="twitter">
                            🐦 Twitter
                        </button>
                        <button class="social-share-btn linkedin" data-platform="linkedin">
                            💼 LinkedIn
                        </button>
                        <button class="social-share-btn pinterest" data-platform="pinterest">
                            📌 Pinterest
                        </button>
                        <button class="social-share-btn whatsapp" data-platform="whatsapp">
                            💬 WhatsApp
                        </button>
                        <button class="social-share-btn telegram" data-platform="telegram">
                            ✈️ Telegram
                        </button>
                        <button class="social-share-btn email" data-platform="email">
                            📧 E-posta
                        </button>
                        <button class="social-share-btn copy-link" data-platform="copy">
                            🔗 Linki Kopyala
                        </button>
                    </div>
                    
                    <div class="social-share-custom">
                        <h4>🎨 Özel Paylaşım</h4>
                        <div class="custom-share-options">
                            <button class="custom-share-btn" id="generate-share-image">
                                🖼️ Paylaşım Görseli Oluştur
                            </button>
                            <button class="custom-share-btn" id="create-story">
                                📱 Story Şablonu
                            </button>
                            <button class="custom-share-btn" id="qr-code-generate">
                                📱 QR Kod Oluştur
                            </button>
                        </div>
                    </div>
                    
                    <div class="social-share-stats">
                        <h4>📊 Paylaşım İstatistikleri</h4>
                        <div class="share-stats-grid">
                            <div class="stat-item">
                                <span class="stat-number" id="total-shares">0</span>
                                <span class="stat-label">Toplam Paylaşım</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number" id="this-week-shares">0</span>
                                <span class="stat-label">Bu Hafta</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number" id="popular-platform">Facebook</span>
                                <span class="stat-label">En Popüler</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- QR Code Modal -->
        <div id="qr-code-modal" class="social-share-modal" style="display: none;">
            <div class="social-share-modal-content">
                <div class="social-share-header">
                    <h3>📱 QR Kod</h3>
                    <span class="qr-modal-close">&times;</span>
                </div>
                <div class="social-share-body">
                    <div class="qr-code-container">
                        <div id="qr-code-display"></div>
                        <p>Bu QR kodu taratarak projeyi görüntüleyebilirsiniz.</p>
                        <button class="button" id="download-qr">📥 QR Kodu İndir</button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .social-share-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .social-share-modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .social-share-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .social-share-header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .social-share-close,
        .qr-modal-close {
            font-size: 24px;
            cursor: pointer;
            color: white;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        
        .social-share-close:hover,
        .qr-modal-close:hover {
            opacity: 1;
        }
        
        .social-share-body {
            padding: 20px;
        }
        
        .social-share-platforms {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 25px;
        }
        
        .social-share-btn {
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
        }
        
        .social-share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .social-share-btn.facebook { background: #1877F2; }
        .social-share-btn.twitter { background: #1DA1F2; }
        .social-share-btn.linkedin { background: #0A66C2; }
        .social-share-btn.pinterest { background: #E60023; }
        .social-share-btn.whatsapp { background: #25D366; }
        .social-share-btn.telegram { background: #26A5E4; }
        .social-share-btn.email { background: #EA4335; }
        .social-share-btn.copy-link { background: #6C757D; }
        
        .social-share-custom {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .social-share-custom h4 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #333;
        }
        
        .custom-share-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .custom-share-btn {
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .custom-share-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .social-share-stats {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .social-share-stats h4 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #333;
        }
        
        .share-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .stat-number {
            display: block;
            font-size: 18px;
            font-weight: 700;
            color: #495057;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6c757d;
        }
        
        .qr-code-container {
            text-align: center;
        }
        
        #qr-code-display {
            margin: 20px auto;
            padding: 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            display: inline-block;
        }
        
        @media (max-width: 768px) {
            .social-share-platforms {
                grid-template-columns: 1fr;
            }
            
            .share-stats-grid {
                grid-template-columns: 1fr;
            }
            
            .custom-share-options {
                flex-direction: column;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Social share shortcode
     */
    public function social_share_shortcode($atts) {
        $atts = shortcode_atts(array(
            'style' => 'buttons', // buttons, icons, minimal
            'platforms' => 'facebook,twitter,linkedin,pinterest',
            'show_counts' => false,
            'size' => 'medium'
        ), $atts);
        
        $platforms = explode(',', $atts['platforms']);
        $project_id = get_the_ID();
        
        if (!$project_id) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="project-social-share style-<?php echo esc_attr($atts['style']); ?> size-<?php echo esc_attr($atts['size']); ?>">
            <?php foreach ($platforms as $platform): ?>
                <?php $platform = trim($platform); ?>
                <button class="social-share-trigger" 
                        data-platform="<?php echo esc_attr($platform); ?>"
                        data-project-id="<?php echo esc_attr($project_id); ?>">
                    <?php echo $this->get_platform_icon($platform); ?>
                    <?php if ($atts['style'] === 'buttons'): ?>
                        <span><?php echo $this->get_platform_name($platform); ?></span>
                    <?php endif; ?>
                    <?php if ($atts['show_counts']): ?>
                        <span class="share-count" data-platform="<?php echo esc_attr($platform); ?>">0</span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
            
            <button class="social-share-more" id="open-share-modal">
                ➕ Daha Fazla
            </button>
        </div>
        
        <style>
        .project-social-share {
            display: flex;
            gap: 10px;
            align-items: center;
            margin: 20px 0;
        }
        
        .project-social-share.style-minimal {
            gap: 5px;
        }
        
        .social-share-trigger,
        .social-share-more {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }
        
        .social-share-trigger:hover,
        .social-share-more:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
            transform: translateY(-1px);
        }
        
        .project-social-share.size-small .social-share-trigger {
            padding: 6px 8px;
            font-size: 12px;
        }
        
        .project-social-share.size-large .social-share-trigger {
            padding: 12px 16px;
            font-size: 16px;
        }
        
        .share-count {
            background: #007cba;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .project-social-share {
                flex-wrap: wrap;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Track project share
     */
    public function track_project_share() {
        check_ajax_referer('project_gallery_social_nonce', 'nonce');
        
        $project_id = intval($_POST['project_id']);
        $platform = sanitize_text_field($_POST['platform']);
        
        // Update share count
        $current_shares = get_post_meta($project_id, '_social_shares', true);
        $current_shares = $current_shares ? json_decode($current_shares, true) : array();
        
        if (!isset($current_shares[$platform])) {
            $current_shares[$platform] = 0;
        }
        
        $current_shares[$platform]++;
        $current_shares['total'] = array_sum(array_filter($current_shares, 'is_numeric'));
        $current_shares['last_shared'] = current_time('mysql');
        
        update_post_meta($project_id, '_social_shares', json_encode($current_shares));
        
        // Track in analytics if available
        if (class_exists('ProjectGalleryAnalytics')) {
            // This would integrate with the analytics system
        }
        
        wp_send_json_success(array(
            'shares' => $current_shares,
            'message' => 'Share tracked successfully'
        ));
    }
    
    /**
     * Generate custom share image
     */
    public function generate_share_image() {
        check_ajax_referer('project_gallery_social_nonce', 'nonce');
        
        $project_id = intval($_POST['project_id']);
        $style = sanitize_text_field($_POST['style'] ?? 'default');
        
        // This would generate a custom share image
        // For now, return the featured image URL
        $share_image = get_the_post_thumbnail_url($project_id, 'large');
        
        wp_send_json_success(array(
            'image_url' => $share_image,
            'message' => 'Share image generated'
        ));
    }
    
    /**
     * Get platform icon
     */
    private function get_platform_icon($platform) {
        $icons = array(
            'facebook' => '📘',
            'twitter' => '🐦',
            'linkedin' => '💼',
            'pinterest' => '📌',
            'whatsapp' => '💬',
            'telegram' => '✈️',
            'email' => '📧'
        );
        
        return $icons[$platform] ?? '🔗';
    }
    
    /**
     * Get platform name
     */
    private function get_platform_name($platform) {
        $names = array(
            'facebook' => 'Facebook',
            'twitter' => 'Twitter',
            'linkedin' => 'LinkedIn',
            'pinterest' => 'Pinterest',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'email' => 'E-posta'
        );
        
        return $names[$platform] ?? ucfirst($platform);
    }
    
    /**
     * Get project share counts
     */
    public function get_project_share_counts($project_id) {
        $shares = get_post_meta($project_id, '_social_shares', true);
        return $shares ? json_decode($shares, true) : array();
    }
    
    /**
     * Get most shared projects
     */
    public function get_most_shared_projects($limit = 10) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_social_shares'
            ORDER BY CAST(JSON_EXTRACT(meta_value, '$.total') AS UNSIGNED) DESC
            LIMIT %d
        ", $limit));
        
        $projects = array();
        foreach ($results as $result) {
            $shares = json_decode($result->meta_value, true);
            $projects[] = array(
                'id' => $result->post_id,
                'title' => get_the_title($result->post_id),
                'shares' => $shares
            );
        }
        
        return $projects;
    }
}