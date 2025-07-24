<?php
/**
 * Project Gallery Advanced Lightbox
 * Gelişmiş lightbox özellikleri: zoom, döndürme, sosyal paylaşım, EXIF, karşılaştırma
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProjectGalleryAdvancedLightbox {
    
    private $settings;
    private $current_project_id;
    
    public function __construct() {
        $this->settings = get_option('project_gallery_lightbox_settings', $this->get_default_settings());
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_lightbox_assets'));
        add_action('wp_footer', array($this, 'render_lightbox_html'));
        
        // AJAX hooks
        add_action('wp_ajax_get_image_exif', array($this, 'ajax_get_image_exif'));
        add_action('wp_ajax_nopriv_get_image_exif', array($this, 'ajax_get_image_exif'));
        add_action('wp_ajax_download_image', array($this, 'ajax_download_image'));
        add_action('wp_ajax_nopriv_download_image', array($this, 'ajax_download_image'));
        add_action('wp_ajax_share_image', array($this, 'ajax_share_image'));
        add_action('wp_ajax_nopriv_share_image', array($this, 'ajax_share_image'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_lightbox_settings_menu'), 30);
        add_action('admin_init', array($this, 'register_lightbox_settings'));
        
        // Shortcode için lightbox aktifleştir
        add_filter('project_gallery_shortcode_output', array($this, 'add_lightbox_attributes'), 10, 2);
    }
    
    /**
     * Varsayılan ayarları getir
     */
    private function get_default_settings() {
        return array(
            'enable_zoom' => true,
            'enable_rotate' => true,
            'enable_fullscreen' => true,
            'enable_social_sharing' => true,
            'enable_download' => true,
            'enable_exif' => true,
            'enable_comparison' => true,
            'enable_slideshow' => true,
            'enable_thumbnails' => true,
            'enable_keyboard_nav' => true,
            'zoom_levels' => array(0.5, 1, 1.5, 2, 3, 4, 5),
            'slideshow_duration' => 3000,
            'animation_duration' => 300,
            'background_color' => '#000000',
            'background_opacity' => 0.9,
            'border_radius' => 8,
            'show_image_counter' => true,
            'show_image_title' => true,
            'show_image_description' => true,
            'social_platforms' => array('facebook', 'twitter', 'pinterest', 'whatsapp'),
            'download_permission' => 'logged_in', // 'all', 'logged_in', 'none'
            'mobile_gestures' => true,
            'mouse_wheel_zoom' => true,
            'auto_fit' => true,
            'preload_next_image' => true
        );
    }
    
    /**
     * Lightbox asset'lerini yükle
     */
    public function enqueue_lightbox_assets() {
        wp_enqueue_script(
            'project-gallery-lightbox-advanced',
            PROJECT_GALLERY_PLUGIN_URL . 'assets/js/lightbox-advanced.js',
            array('jquery'),
            PROJECT_GALLERY_VERSION,
            true
        );
        
        wp_enqueue_style(
            'project-gallery-lightbox-advanced',
            PROJECT_GALLERY_PLUGIN_URL . 'assets/css/lightbox-advanced.css',
            array(),
            PROJECT_GALLERY_VERSION
        );
        
        // Hammer.js for touch gestures
        wp_enqueue_script(
            'hammerjs',
            'https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js',
            array(),
            '2.0.8',
            true
        );
        
        // Localize script
        wp_localize_script('project-gallery-lightbox-advanced', 'projectGalleryLightbox', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('project_gallery_lightbox_nonce'),
            'settings' => $this->settings,
            'strings' => array(
                'loading' => __('Yükleniyor...', 'project-gallery'),
                'error' => __('Resim yüklenemedi', 'project-gallery'),
                'close' => __('Kapat', 'project-gallery'),
                'next' => __('Sonraki', 'project-gallery'),
                'previous' => __('Önceki', 'project-gallery'),
                'zoom_in' => __('Yakınlaştır', 'project-gallery'),
                'zoom_out' => __('Uzaklaştır', 'project-gallery'),
                'rotate_left' => __('Sola Döndür', 'project-gallery'),
                'rotate_right' => __('Sağa Döndür', 'project-gallery'),
                'fullscreen' => __('Tam Ekran', 'project-gallery'),
                'share' => __('Paylaş', 'project-gallery'),
                'download' => __('İndir', 'project-gallery'),
                'exif' => __('EXIF Bilgileri', 'project-gallery'),
                'comparison' => __('Karşılaştır', 'project-gallery'),
                'slideshow_play' => __('Slayt Gösterisini Başlat', 'project-gallery'),
                'slideshow_pause' => __('Slayt Gösterisini Duraklat', 'project-gallery'),
                'thumbnails' => __('Küçük Resimler', 'project-gallery'),
                'image_info' => __('Resim Bilgileri', 'project-gallery'),
                'no_permission' => __('Bu resmi indirme yetkiniz yok', 'project-gallery'),
                'download_failed' => __('İndirme başarısız', 'project-gallery'),
                'share_copied' => __('Bağlantı panoya kopyalandı', 'project-gallery'),
                'keyboard_shortcuts' => __('Klavye Kısayolları', 'project-gallery'),
                'help_text' => array(
                    'arrows' => __('Ok tuşları: Resimler arası geçiş', 'project-gallery'),
                    'space' => __('Boşluk: Slayt gösterisini başlat/duraklat', 'project-gallery'),
                    'escape' => __('ESC: Lightbox\'ı kapat', 'project-gallery'),
                    'plus_minus' => __('+ / -: Yakınlaştır / Uzaklaştır', 'project-gallery'),
                    'r' => __('R: Resmi sağa döndür', 'project-gallery'),
                    'f' => __('F: Tam ekran', 'project-gallery'),
                    'i' => __('I: Resim bilgilerini göster/gizle', 'project-gallery'),
                    't' => __('T: Küçük resimleri göster/gizle', 'project-gallery')
                )
            ),
            'share_urls' => array(
                'facebook' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
                'twitter' => 'https://twitter.com/intent/tweet?url={url}&text={title}',
                'pinterest' => 'https://pinterest.com/pin/create/button/?url={url}&media={image}&description={title}',
                'whatsapp' => 'https://wa.me/?text={title} {url}',
                'telegram' => 'https://t.me/share/url?url={url}&text={title}',
                'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}'
            )
        ));
    }
    
    /**
     * Lightbox HTML'ini render et
     */
    public function render_lightbox_html() {
        ?>
        <div id="project-gallery-lightbox" class="pgal-lightbox" style="display: none;">
            <!-- Ana lightbox container -->
            <div class="pgal-lightbox-overlay"></div>
            
            <!-- Lightbox içeriği -->
            <div class="pgal-lightbox-container">
                <!-- Üst toolbar -->
                <div class="pgal-lightbox-toolbar pgal-toolbar-top">
                    <div class="pgal-toolbar-left">
                        <div class="pgal-image-counter">
                            <span class="pgal-current-index">1</span>
                            <span class="pgal-separator">/</span>
                            <span class="pgal-total-count">1</span>
                        </div>
                        <div class="pgal-image-title"></div>
                    </div>
                    
                    <div class="pgal-toolbar-right">
                        <button class="pgal-btn pgal-btn-help" title="<?php _e('Yardım', 'project-gallery'); ?>">
                            <span class="pgal-icon-help">?</span>
                        </button>
                        <button class="pgal-btn pgal-btn-close" title="<?php _e('Kapat', 'project-gallery'); ?>">
                            <span class="pgal-icon-close">×</span>
                        </button>
                    </div>
                </div>
                
                <!-- Sol navigasyon -->
                <div class="pgal-nav pgal-nav-prev">
                    <button class="pgal-btn pgal-btn-nav" title="<?php _e('Önceki', 'project-gallery'); ?>">
                        <span class="pgal-icon-arrow-left">‹</span>
                    </button>
                </div>
                
                <!-- Sağ navigasyon -->
                <div class="pgal-nav pgal-nav-next">
                    <button class="pgal-btn pgal-btn-nav" title="<?php _e('Sonraki', 'project-gallery'); ?>">
                        <span class="pgal-icon-arrow-right">›</span>
                    </button>
                </div>
                
                <!-- Ana resim alanı -->
                <div class="pgal-lightbox-content">
                    <div class="pgal-image-container">
                        <div class="pgal-loading">
                            <div class="pgal-spinner"></div>
                            <div class="pgal-loading-text"><?php _e('Yükleniyor...', 'project-gallery'); ?></div>
                        </div>
                        
                        <div class="pgal-image-wrapper">
                            <img class="pgal-main-image" src="" alt="" />
                        </div>
                        
                        <!-- Karşılaştırma modu için ikinci resim -->
                        <div class="pgal-comparison-wrapper" style="display: none;">
                            <img class="pgal-comparison-image" src="" alt="" />
                            <div class="pgal-comparison-slider">
                                <div class="pgal-comparison-handle"></div>
                            </div>
                        </div>
                        
                        <!-- Zoom indicator -->
                        <div class="pgal-zoom-indicator">
                            <span class="pgal-zoom-level">100%</span>
                        </div>
                    </div>
                </div>
                
                <!-- Alt toolbar -->
                <div class="pgal-lightbox-toolbar pgal-toolbar-bottom">
                    <div class="pgal-toolbar-left">
                        <!-- Zoom kontrolları -->
                        <div class="pgal-zoom-controls">
                            <button class="pgal-btn pgal-btn-zoom-out" title="<?php _e('Uzaklaştır', 'project-gallery'); ?>">
                                <span class="pgal-icon-zoom-out">−</span>
                            </button>
                            <button class="pgal-btn pgal-btn-zoom-fit" title="<?php _e('Ekrana Sığdır', 'project-gallery'); ?>">
                                <span class="pgal-icon-zoom-fit">⌂</span>
                            </button>
                            <button class="pgal-btn pgal-btn-zoom-in" title="<?php _e('Yakınlaştır', 'project-gallery'); ?>">
                                <span class="pgal-icon-zoom-in">+</span>
                            </button>
                        </div>
                        
                        <!-- Döndürme kontrolları -->
                        <div class="pgal-rotate-controls">
                            <button class="pgal-btn pgal-btn-rotate-left" title="<?php _e('Sola Döndür', 'project-gallery'); ?>">
                                <span class="pgal-icon-rotate-left">↶</span>
                            </button>
                            <button class="pgal-btn pgal-btn-rotate-right" title="<?php _e('Sağa Döndür', 'project-gallery'); ?>">
                                <span class="pgal-icon-rotate-right">↷</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="pgal-toolbar-center">
                        <!-- Oynatma kontrolları -->
                        <div class="pgal-playback-controls">
                            <button class="pgal-btn pgal-btn-slideshow" title="<?php _e('Slayt Gösterisi', 'project-gallery'); ?>">
                                <span class="pgal-icon-play">▶</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="pgal-toolbar-right">
                        <!-- Sosyal medya paylaşımı -->
                        <div class="pgal-social-controls">
                            <button class="pgal-btn pgal-btn-share" title="<?php _e('Paylaş', 'project-gallery'); ?>">
                                <span class="pgal-icon-share">⤴</span>
                            </button>
                        </div>
                        
                        <!-- Diğer kontroller -->
                        <div class="pgal-other-controls">
                            <button class="pgal-btn pgal-btn-info" title="<?php _e('Resim Bilgileri', 'project-gallery'); ?>">
                                <span class="pgal-icon-info">ⓘ</span>
                            </button>
                            <button class="pgal-btn pgal-btn-compare" title="<?php _e('Karşılaştır', 'project-gallery'); ?>">
                                <span class="pgal-icon-compare">⚖</span>
                            </button>
                            <button class="pgal-btn pgal-btn-download" title="<?php _e('İndir', 'project-gallery'); ?>">
                                <span class="pgal-icon-download">⬇</span>
                            </button>
                            <button class="pgal-btn pgal-btn-fullscreen" title="<?php _e('Tam Ekran', 'project-gallery'); ?>">
                                <span class="pgal-icon-fullscreen">⛶</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Küçük resimler -->
                <div class="pgal-thumbnails-container" style="display: none;">
                    <div class="pgal-thumbnails-wrapper">
                        <div class="pgal-thumbnails-list"></div>
                    </div>
                    <button class="pgal-btn pgal-btn-thumbnails-toggle" title="<?php _e('Küçük Resimleri Gizle', 'project-gallery'); ?>">
                        <span class="pgal-icon-thumbnails">▼</span>
                    </button>
                </div>
            </div>
            
            <!-- Bilgi paneli -->
            <div class="pgal-info-panel" style="display: none;">
                <div class="pgal-info-header">
                    <h3><?php _e('Resim Bilgileri', 'project-gallery'); ?></h3>
                    <button class="pgal-btn pgal-btn-close-info">×</button>
                </div>
                <div class="pgal-info-content">
                    <div class="pgal-info-section pgal-image-meta">
                        <h4><?php _e('Genel Bilgiler', 'project-gallery'); ?></h4>
                        <div class="pgal-info-item">
                            <label><?php _e('Dosya Adı:', 'project-gallery'); ?></label>
                            <span class="pgal-filename"></span>
                        </div>
                        <div class="pgal-info-item">
                            <label><?php _e('Boyut:', 'project-gallery'); ?></label>
                            <span class="pgal-filesize"></span>
                        </div>
                        <div class="pgal-info-item">
                            <label><?php _e('Çözünürlük:', 'project-gallery'); ?></label>
                            <span class="pgal-dimensions"></span>
                        </div>
                        <div class="pgal-info-item">
                            <label><?php _e('Yüklenme Tarihi:', 'project-gallery'); ?></label>
                            <span class="pgal-upload-date"></span>
                        </div>
                    </div>
                    
                    <div class="pgal-info-section pgal-exif-data" style="display: none;">
                        <h4><?php _e('EXIF Bilgileri', 'project-gallery'); ?></h4>
                        <div class="pgal-exif-content"></div>
                    </div>
                    
                    <div class="pgal-info-section pgal-image-stats">
                        <h4><?php _e('İstatistikler', 'project-gallery'); ?></h4>
                        <div class="pgal-info-item">
                            <label><?php _e('Görüntülenme:', 'project-gallery'); ?></label>
                            <span class="pgal-view-count">-</span>
                        </div>
                        <div class="pgal-info-item">
                            <label><?php _e('Beğeni:', 'project-gallery'); ?></label>
                            <span class="pgal-like-count">-</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Paylaşım paneli -->
            <div class="pgal-share-panel" style="display: none;">
                <div class="pgal-share-header">
                    <h3><?php _e('Paylaş', 'project-gallery'); ?></h3>
                    <button class="pgal-btn pgal-btn-close-share">×</button>
                </div>
                <div class="pgal-share-content">
                    <div class="pgal-share-buttons">
                        <?php if (in_array('facebook', $this->settings['social_platforms'])): ?>
                        <button class="pgal-share-btn pgal-share-facebook" data-platform="facebook">
                            <span class="pgal-share-icon">f</span>
                            <span class="pgal-share-text">Facebook</span>
                        </button>
                        <?php endif; ?>
                        
                        <?php if (in_array('twitter', $this->settings['social_platforms'])): ?>
                        <button class="pgal-share-btn pgal-share-twitter" data-platform="twitter">
                            <span class="pgal-share-icon">𝕏</span>
                            <span class="pgal-share-text">Twitter</span>
                        </button>
                        <?php endif; ?>
                        
                        <?php if (in_array('pinterest', $this->settings['social_platforms'])): ?>
                        <button class="pgal-share-btn pgal-share-pinterest" data-platform="pinterest">
                            <span class="pgal-share-icon">P</span>
                            <span class="pgal-share-text">Pinterest</span>
                        </button>
                        <?php endif; ?>
                        
                        <?php if (in_array('whatsapp', $this->settings['social_platforms'])): ?>
                        <button class="pgal-share-btn pgal-share-whatsapp" data-platform="whatsapp">
                            <span class="pgal-share-icon">W</span>
                            <span class="pgal-share-text">WhatsApp</span>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pgal-share-url">
                        <label><?php _e('Bağlantı:', 'project-gallery'); ?></label>
                        <div class="pgal-share-url-container">
                            <input type="text" class="pgal-share-url-input" readonly />
                            <button class="pgal-btn pgal-btn-copy" title="<?php _e('Kopyala', 'project-gallery'); ?>">
                                <span class="pgal-icon-copy">📋</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Yardım paneli -->
            <div class="pgal-help-panel" style="display: none;">
                <div class="pgal-help-header">
                    <h3><?php _e('Klavye Kısayolları', 'project-gallery'); ?></h3>
                    <button class="pgal-btn pgal-btn-close-help">×</button>
                </div>
                <div class="pgal-help-content">
                    <div class="pgal-help-section">
                        <h4><?php _e('Navigasyon', 'project-gallery'); ?></h4>
                        <div class="pgal-help-item">
                            <kbd>←</kbd><kbd>→</kbd>
                            <span><?php _e('Önceki/Sonraki resim', 'project-gallery'); ?></span>
                        </div>
                        <div class="pgal-help-item">
                            <kbd>ESC</kbd>
                            <span><?php _e('Lightbox\'ı kapat', 'project-gallery'); ?></span>
                        </div>
                    </div>
                    
                    <div class="pgal-help-section">
                        <h4><?php _e('Kontroller', 'project-gallery'); ?></h4>
                        <div class="pgal-help-item">
                            <kbd>+</kbd><kbd>-</kbd>
                            <span><?php _e('Yakınlaştır/Uzaklaştır', 'project-gallery'); ?></span>
                        </div>
                        <div class="pgal-help-item">
                            <kbd>R</kbd>
                            <span><?php _e('Sağa döndür', 'project-gallery'); ?></span>
                        </div>
                        <div class="pgal-help-item">
                            <kbd>F</kbd>
                            <span><?php _e('Tam ekran', 'project-gallery'); ?></span>
                        </div>
                        <div class="pgal-help-item">
                            <kbd>SPACE</kbd>
                            <span><?php _e('Slayt gösterisi başlat/duraklat', 'project-gallery'); ?></span>
                        </div>
                    </div>
                    
                    <div class="pgal-help-section">
                        <h4><?php _e('Paneller', 'project-gallery'); ?></h4>
                        <div class="pgal-help-item">
                            <kbd>I</kbd>
                            <span><?php _e('Resim bilgileri', 'project-gallery'); ?></span>
                        </div>
                        <div class="pgal-help-item">
                            <kbd>T</kbd>
                            <span><?php _e('Küçük resimler', 'project-gallery'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Shortcode çıktısına lightbox attribute'ları ekle
     */
    public function add_lightbox_attributes($output, $atts) {
        // Lightbox data attribute'larını ekle
        $lightbox_data = array(
            'lightbox' => 'project-gallery',
            'lightbox-settings' => json_encode($this->settings)
        );
        
        $data_attrs = '';
        foreach ($lightbox_data as $key => $value) {
            $data_attrs .= ' data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        
        // Gallery container'a attribute'ları ekle
        $output = str_replace(
            'class="project-gallery"',
            'class="project-gallery pgal-lightbox-enabled"' . $data_attrs,
            $output
        );
        
        // Her resme lightbox attribute'ları ekle
        $output = preg_replace_callback(
            '/<img([^>]+)class="([^"]*)"([^>]*)>/i',
            function($matches) {
                $class = $matches[2] . ' pgal-lightbox-image';
                return '<img' . $matches[1] . 'class="' . $class . '"' . $matches[3] . ' data-pgal-lightbox="true">';
            },
            $output
        );
        
        return $output;
    }
    
    /**
     * AJAX: EXIF bilgilerini getir
     */
    public function ajax_get_image_exif() {
        check_ajax_referer('project_gallery_lightbox_nonce', 'nonce');
        
        $attachment_id = intval($_POST['attachment_id']);
        
        if (!$attachment_id) {
            wp_send_json_error('Geçersiz resim ID');
        }
        
        $exif_data = $this->get_image_exif($attachment_id);
        
        if (empty($exif_data)) {
            wp_send_json_error('EXIF bilgisi bulunamadı');
        }
        
        wp_send_json_success($exif_data);
    }
    
    /**
     * Resmin EXIF bilgilerini getir
     */
    private function get_image_exif($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            return array();
        }
        
        $exif = @exif_read_data($file_path, 0, true);
        
        if (!$exif) {
            return array();
        }
        
        $formatted_exif = array();
        
        // Kamera bilgileri
        if (isset($exif['IFD0']['Make'])) {
            $formatted_exif['Kamera Markası'] = $exif['IFD0']['Make'];
        }
        
        if (isset($exif['IFD0']['Model'])) {
            $formatted_exif['Kamera Modeli'] = $exif['IFD0']['Model'];
        }
        
        // Çekim ayarları
        if (isset($exif['EXIF']['ExposureTime'])) {
            $formatted_exif['Deklanşör Hızı'] = $this->format_exposure_time($exif['EXIF']['ExposureTime']);
        }
        
        if (isset($exif['EXIF']['FNumber'])) {
            $formatted_exif['Diyafram'] = 'f/' . $this->format_fnumber($exif['EXIF']['FNumber']);
        }
        
        if (isset($exif['EXIF']['ISOSpeedRatings'])) {
            $formatted_exif['ISO'] = $exif['EXIF']['ISOSpeedRatings'];
        }
        
        if (isset($exif['EXIF']['FocalLength'])) {
            $formatted_exif['Odak Uzaklığı'] = $this->format_focal_length($exif['EXIF']['FocalLength']) . ' mm';
        }
        
        if (isset($exif['EXIF']['Flash'])) {
            $formatted_exif['Flaş'] = $this->format_flash($exif['EXIF']['Flash']);
        }
        
        // Tarih ve saat
        if (isset($exif['EXIF']['DateTimeOriginal'])) {
            $formatted_exif['Çekim Tarihi'] = date('d.m.Y H:i:s', strtotime($exif['EXIF']['DateTimeOriginal']));
        }
        
        // GPS bilgileri
        if (isset($exif['GPS'])) {
            $gps = $this->format_gps_coordinates($exif['GPS']);
            if ($gps) {
                $formatted_exif['Konum'] = $gps;
            }
        }
        
        // Yazılım
        if (isset($exif['IFD0']['Software'])) {
            $formatted_exif['Yazılım'] = $exif['IFD0']['Software'];
        }
        
        return $formatted_exif;
    }
    
    /**
     * Pozlama süresini formatla
     */
    private function format_exposure_time($exposure) {
        if (strpos($exposure, '/') !== false) {
            $parts = explode('/', $exposure);
            if (count($parts) == 2 && $parts[1] != 0) {
                $decimal = $parts[0] / $parts[1];
                if ($decimal >= 1) {
                    return $decimal . 's';
                } else {
                    return $exposure . 's';
                }
            }
        }
        return $exposure . 's';
    }
    
    /**
     * F-number'ı formatla
     */
    private function format_fnumber($fnumber) {
        if (strpos($fnumber, '/') !== false) {
            $parts = explode('/', $fnumber);
            if (count($parts) == 2 && $parts[1] != 0) {
                return round($parts[0] / $parts[1], 1);
            }
        }
        return $fnumber;
    }
    
    /**
     * Odak uzaklığını formatla
     */
    private function format_focal_length($focal_length) {
        if (strpos($focal_length, '/') !== false) {
            $parts = explode('/', $focal_length);
            if (count($parts) == 2 && $parts[1] != 0) {
                return round($parts[0] / $parts[1]);
            }
        }
        return $focal_length;
    }
    
    /**
     * Flaş bilgisini formatla
     */
    private function format_flash($flash) {
        $flash_modes = array(
            0 => 'Flaş patlamadı',
            1 => 'Flaş patladı',
            5 => 'Flaş patladı, geri dönüş algılanmadı',
            7 => 'Flaş patladı, geri dönüş algılandı',
            9 => 'Flaş patladı, zorunlu mod',
            13 => 'Flaş patladı, zorunlu mod, geri dönüş algılanmadı',
            15 => 'Flaş patladı, zorunlu mod, geri dönüş algılandı',
            16 => 'Flaş patlamadı, zorunlu mod',
            24 => 'Flaş patlamadı, otomatik mod',
            25 => 'Flaş patladı, otomatik mod',
            29 => 'Flaş patladı, otomatik mod, geri dönüş algılanmadı',
            31 => 'Flaş patladı, otomatik mod, geri dönüş algılandı'
        );
        
        return isset($flash_modes[$flash]) ? $flash_modes[$flash] : 'Bilinmeyen';
    }
    
    /**
     * GPS koordinatlarını formatla
     */
    private function format_gps_coordinates($gps) {
        if (!isset($gps['GPSLatitude']) || !isset($gps['GPSLongitude'])) {
            return null;
        }
        
        $lat = $this->gps_to_decimal($gps['GPSLatitude'], $gps['GPSLatitudeRef']);
        $lon = $this->gps_to_decimal($gps['GPSLongitude'], $gps['GPSLongitudeRef']);
        
        return round($lat, 6) . ', ' . round($lon, 6);
    }
    
    /**
     * GPS koordinatını decimal'e çevir
     */
    private function gps_to_decimal($coordinate, $hemisphere) {
        $degrees = count($coordinate) > 0 ? $this->gps_fraction_to_decimal($coordinate[0]) : 0;
        $minutes = count($coordinate) > 1 ? $this->gps_fraction_to_decimal($coordinate[1]) : 0;
        $seconds = count($coordinate) > 2 ? $this->gps_fraction_to_decimal($coordinate[2]) : 0;
        
        $flip = ($hemisphere == 'W' || $hemisphere == 'S') ? -1 : 1;
        
        return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
    }
    
    /**
     * GPS fraction'ı decimal'e çevir
     */
    private function gps_fraction_to_decimal($fraction) {
        $parts = explode('/', $fraction);
        if (count($parts) <= 0) return 0;
        if (count($parts) == 1) return $parts[0];
        return floatval($parts[0]) / floatval($parts[1]);
    }
    
    /**
     * AJAX: Resmi indir
     */
    public function ajax_download_image() {
        check_ajax_referer('project_gallery_lightbox_nonce', 'nonce');
        
        $attachment_id = intval($_POST['attachment_id']);
        
        if (!$attachment_id) {
            wp_send_json_error('Geçersiz resim ID');
        }
        
        // İndirme yetkisi kontrolü
        if (!$this->can_download_image($attachment_id)) {
            wp_send_json_error('Bu resmi indirme yetkiniz yok');
        }
        
        $file_path = get_attached_file($attachment_id);
        $file_url = wp_get_attachment_url($attachment_id);
        
        if (!$file_path || !$file_url) {
            wp_send_json_error('Dosya bulunamadı');
        }
        
        // İndirme sayacını artır
        $this->increment_download_count($attachment_id);
        
        wp_send_json_success(array(
            'download_url' => $file_url,
            'filename' => basename($file_path)
        ));
    }
    
    /**
     * Resmi indirme yetkisi kontrolü
     */
    private function can_download_image($attachment_id) {
        switch ($this->settings['download_permission']) {
            case 'all':
                return true;
                
            case 'logged_in':
                return is_user_logged_in();
                
            case 'none':
                return false;
                
            default:
                return current_user_can('manage_options');
        }
    }
    
    /**
     * İndirme sayacını artır
     */
    private function increment_download_count($attachment_id) {
        $count = get_post_meta($attachment_id, '_pgal_download_count', true);
        $count = $count ? intval($count) + 1 : 1;
        update_post_meta($attachment_id, '_pgal_download_count', $count);
    }
    
    /**
     * AJAX: Resmi paylaş
     */
    public function ajax_share_image() {
        check_ajax_referer('project_gallery_lightbox_nonce', 'nonce');
        
        $attachment_id = intval($_POST['attachment_id']);
        $platform = sanitize_text_field($_POST['platform']);
        
        if (!$attachment_id) {
            wp_send_json_error('Geçersiz resim ID');
        }
        
        $image_url = wp_get_attachment_url($attachment_id);
        $image_title = get_the_title($attachment_id);
        $page_url = get_permalink();
        
        // Paylaşım sayacını artır
        $this->increment_share_count($attachment_id, $platform);
        
        wp_send_json_success(array(
            'image_url' => $image_url,
            'image_title' => $image_title,
            'page_url' => $page_url
        ));
    }
    
    /**
     * Paylaşım sayacını artır
     */
    private function increment_share_count($attachment_id, $platform) {
        $count_key = '_pgal_share_count_' . $platform;
        $count = get_post_meta($attachment_id, $count_key, true);
        $count = $count ? intval($count) + 1 : 1;
        update_post_meta($attachment_id, $count_key, $count);
        
        // Toplam paylaşım sayısı
        $total_count = get_post_meta($attachment_id, '_pgal_total_share_count', true);
        $total_count = $total_count ? intval($total_count) + 1 : 1;
        update_post_meta($attachment_id, '_pgal_total_share_count', $total_count);
    }
    
    /**
     * Admin menüsüne lightbox ayarları ekle
     */
    public function add_lightbox_settings_menu() {
        add_submenu_page(
            'edit.php?post_type=proje',
            'Lightbox Ayarları',
            'Lightbox',
            'manage_options',
            'project-lightbox-settings',
            array($this, 'lightbox_settings_page')
        );
    }
    
    /**
     * Lightbox ayarları sayfası
     */
    public function lightbox_settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_lightbox_settings();
            echo '<div class="notice notice-success"><p>Ayarlar kaydedildi!</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1>🔍 Lightbox Ayarları</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('project_gallery_lightbox_settings', 'lightbox_settings_nonce'); ?>
                
                <div class="postbox">
                    <h2 class="hndle">⚙️ Genel Ayarlar</h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Aktif Özellikler</th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="enable_zoom" value="1" <?php checked($this->settings['enable_zoom']); ?>>
                                            🔍 Zoom (Yakınlaştırma)
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="enable_rotate" value="1" <?php checked($this->settings['enable_rotate']); ?>>
                                            🔄 Döndürme
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="enable_fullscreen" value="1" <?php checked($this->settings['enable_fullscreen']); ?>>
                                            🖥️ Tam Ekran
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="enable_social_sharing" value="1" <?php checked($this->settings['enable_social_sharing']); ?>>
                                            📤 Sosyal Medya Paylaşımı
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="enable_download" value="1" <?php checked($this->settings['enable_download']); ?>>
                                            ⬇️ İndirme
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="enable_exif" value="1" <?php checked($this->settings['enable_exif']); ?>>
                                            📊 EXIF Bilgileri
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="enable_comparison" value="1" <?php checked($this->settings['enable_comparison']); ?>>
                                            ⚖️ Karşılaştırma Modu
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="enable_slideshow" value="1" <?php checked($this->settings['enable_slideshow']); ?>>
                                            🎬 Slayt Gösterisi
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="enable_thumbnails" value="1" <?php checked($this->settings['enable_thumbnails']); ?>>
                                            🖼️ Küçük Resimler
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="enable_keyboard_nav" value="1" <?php checked($this->settings['enable_keyboard_nav']); ?>>
                                            ⌨️ Klavye Navigasyonu
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">İndirme İzni</th>
                                <td>
                                    <select name="download_permission">
                                        <option value="all" <?php selected($this->settings['download_permission'], 'all'); ?>>
                                            Herkes
                                        </option>
                                        <option value="logged_in" <?php selected($this->settings['download_permission'], 'logged_in'); ?>>
                                            Sadece Üyeler
                                        </option>
                                        <option value="none" <?php selected($this->settings['download_permission'], 'none'); ?>>
                                            Kimse
                                        </option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Slayt Gösterisi Süresi</th>
                                <td>
                                    <input type="number" name="slideshow_duration" value="<?php echo $this->settings['slideshow_duration']; ?>" min="1000" max="10000" step="500">
                                    <span class="description">milisaniye (1000 = 1 saniye)</span>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Animasyon Süresi</th>
                                <td>
                                    <input type="number" name="animation_duration" value="<?php echo $this->settings['animation_duration']; ?>" min="100" max="1000" step="50">
                                    <span class="description">milisaniye</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle">🎨 Görünüm Ayarları</h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Arkaplan Rengi</th>
                                <td>
                                    <input type="color" name="background_color" value="<?php echo $this->settings['background_color']; ?>">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Arkaplan Saydamlığı</th>
                                <td>
                                    <input type="range" name="background_opacity" min="0.1" max="1" step="0.1" value="<?php echo $this->settings['background_opacity']; ?>" oninput="this.nextElementSibling.value = this.value">
                                    <output><?php echo $this->settings['background_opacity']; ?></output>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Köşe Yuvarlaklığı</th>
                                <td>
                                    <input type="range" name="border_radius" min="0" max="20" value="<?php echo $this->settings['border_radius']; ?>" oninput="this.nextElementSibling.value = this.value">
                                    <output><?php echo $this->settings['border_radius']; ?></output> px
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">Bilgi Gösterimi</th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="show_image_counter" value="1" <?php checked($this->settings['show_image_counter']); ?>>
                                            Resim sayacı (1/10)
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="show_image_title" value="1" <?php checked($this->settings['show_image_title']); ?>>
                                            Resim başlığı
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="show_image_description" value="1" <?php checked($this->settings['show_image_description']); ?>>
                                            Resim açıklaması
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle">📱 Mobil ve Touch Ayarları</h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Mobil Özellikler</th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="mobile_gestures" value="1" <?php checked($this->settings['mobile_gestures']); ?>>
                                            👆 Touch/Swipe Desteği
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="mouse_wheel_zoom" value="1" <?php checked($this->settings['mouse_wheel_zoom']); ?>>
                                            🖱️ Mouse Wheel ile Zoom
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="auto_fit" value="1" <?php checked($this->settings['auto_fit']); ?>>
                                            📱 Otomatik Ekrana Sığdır
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="preload_next_image" value="1" <?php checked($this->settings['preload_next_image']); ?>>
                                            ⚡ Sonraki Resmi Ön Yükle
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle">📤 Sosyal Medya Ayarları</h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Aktif Platformlar</th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="social_platforms[]" value="facebook" <?php checked(in_array('facebook', $this->settings['social_platforms'])); ?>>
                                            Facebook
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="social_platforms[]" value="twitter" <?php checked(in_array('twitter', $this->settings['social_platforms'])); ?>>
                                            Twitter/X
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="social_platforms[]" value="pinterest" <?php checked(in_array('pinterest', $this->settings['social_platforms'])); ?>>
                                            Pinterest
                                        </label><br>
                                        <label>
                                            <input type="checkbox" name="social_platforms[]" value="whatsapp" <?php checked(in_array('whatsapp', $this->settings['social_platforms'])); ?>>
                                            WhatsApp
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php submit_button('Ayarları Kaydet', 'primary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * WordPress Settings API ile lightbox ayarlarını kaydet
     */
    public function register_lightbox_settings() {
        register_setting(
            'project_gallery_lightbox_settings_group',
            'project_gallery_lightbox_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_lightbox_settings'),
                'default' => $this->get_default_settings()
            )
        );
    }
    
    /**
     * Lightbox ayarları sanitization
     */
    public function sanitize_lightbox_settings($input) {
        $sanitized = array();
        $defaults = $this->get_default_settings();
        
        foreach ($defaults as $key => $default_value) {
            if (isset($input[$key])) {
                if (is_bool($default_value)) {
                    $sanitized[$key] = (bool) $input[$key];
                } elseif (is_int($default_value)) {
                    $sanitized[$key] = intval($input[$key]);
                } elseif (is_float($default_value)) {
                    $sanitized[$key] = floatval($input[$key]);
                } elseif (is_array($default_value)) {
                    $sanitized[$key] = (array) $input[$key];
                } else {
                    $sanitized[$key] = sanitize_text_field($input[$key]);
                }
            } else {
                $sanitized[$key] = $default_value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Lightbox ayarları kaydet
     */
    private function save_lightbox_settings() {
        if (!wp_verify_nonce($_POST['lightbox_settings_nonce'], 'project_gallery_lightbox_settings')) {
            return;
        }
        
        $settings = array();
        
        foreach ($this->get_default_settings() as $key => $default_value) {
            if (isset($_POST[$key])) {
                if (is_bool($default_value)) {
                    $settings[$key] = (bool) $_POST[$key];
                } elseif (is_int($default_value)) {
                    $settings[$key] = intval($_POST[$key]);
                } elseif (is_float($default_value)) {
                    $settings[$key] = floatval($_POST[$key]);
                } elseif (is_array($default_value)) {
                    $settings[$key] = (array) $_POST[$key];
                } else {
                    $settings[$key] = sanitize_text_field($_POST[$key]);
                }
            } else {
                // Checkbox'lar için false değer
                if (is_bool($default_value)) {
                    $settings[$key] = false;
                } else {
                    $settings[$key] = $default_value;
                }
            }
        }
        
        update_option('project_gallery_lightbox_settings', $settings);
        $this->settings = $settings;
    }
    
    /**
     * Lightbox ayarlarını getir
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Lightbox ayarlarını güncelle
     */
    public function update_settings($new_settings) {
        $this->settings = array_merge($this->settings, $new_settings);
        update_option('project_gallery_lightbox_settings', $this->settings);
    }
}