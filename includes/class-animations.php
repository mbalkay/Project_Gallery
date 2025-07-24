<?php
/**
 * Project Gallery Animations System
 * Gelişmiş animasyon ve efekt sistemi: fade, slide, zoom, flip, parallax
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProjectGalleryAnimations {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('project_gallery_animation_settings', $this->get_default_settings());
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_animation_assets'));
        add_action('wp_head', array($this, 'add_custom_animation_styles'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_animations_menu'), 35);
        add_action('admin_init', array($this, 'register_animation_settings'));
        
        // Shortcode filtreleri
        add_filter('project_gallery_shortcode_output', array($this, 'add_animation_attributes'), 10, 2);
        add_filter('project_gallery_item_classes', array($this, 'add_item_animation_classes'), 10, 2);
        
        // AJAX hooks
        add_action('wp_ajax_preview_animation', array($this, 'ajax_preview_animation'));
        add_action('wp_ajax_save_animation_preset', array($this, 'ajax_save_animation_preset'));
    }
    
    /**
     * Varsayılan animasyon ayarları
     */
    private function get_default_settings() {
        return array(
            // Genel ayarlar
            'enable_animations' => true,
            'performance_mode' => false,
            'use_intersection_observer' => true,
            'animation_threshold' => 0.1,
            
            // Galeri giriş animasyonları
            'gallery_entrance' => 'fade-up',
            'gallery_entrance_duration' => 600,
            'gallery_entrance_delay' => 100,
            'gallery_entrance_stagger' => 150,
            
            // Hover animasyonları
            'hover_effect' => 'scale-rotate',
            'hover_duration' => 400,
            'hover_timing' => 'cubic-bezier(0.25, 0.8, 0.25, 1)',
            'hover_scale' => 1.05,
            'hover_rotate' => 2,
            'hover_blur' => 0,
            'hover_brightness' => 1.1,
            'hover_saturate' => 1.2,
            
            // Loading animasyonları
            'loading_animation' => 'skeleton',
            'loading_duration' => 1500,
            'loading_color_start' => '#f0f0f0',
            'loading_color_end' => '#e0e0e0',
            
            // Scroll animasyonları
            'scroll_animations' => true,
            'scroll_trigger_offset' => '10%',
            'scroll_repeat' => false,
            
            // Parallax efektleri
            'parallax_enabled' => true,
            'parallax_speed' => 0.5,
            'parallax_direction' => 'vertical',
            
            // Özel animasyon presetleri
            'animation_presets' => array(
                'minimal' => array(
                    'gallery_entrance' => 'fade',
                    'hover_effect' => 'scale',
                    'parallax_enabled' => false
                ),
                'dynamic' => array(
                    'gallery_entrance' => 'slide-up-bounce',
                    'hover_effect' => 'lift-rotate',
                    'parallax_enabled' => true
                ),
                'elegant' => array(
                    'gallery_entrance' => 'fade-zoom',
                    'hover_effect' => 'soft-scale',
                    'parallax_enabled' => false
                )
            )
        );
    }
    
    /**
     * Animasyon asset'lerini yükle
     */
    public function enqueue_animation_assets() {
        wp_enqueue_script(
            'project-gallery-animations',
            PROJECT_GALLERY_PLUGIN_URL . 'assets/js/animations.js',
            array('jquery'),
            PROJECT_GALLERY_VERSION,
            true
        );
        
        wp_enqueue_style(
            'project-gallery-animations',
            PROJECT_GALLERY_PLUGIN_URL . 'assets/css/animations.css',
            array(),
            PROJECT_GALLERY_VERSION
        );
        
        // Intersection Observer polyfill (eski tarayıcılar için)
        if ($this->settings['use_intersection_observer']) {
            wp_enqueue_script(
                'intersection-observer-polyfill',
                'https://polyfill.io/v3/polyfill.min.js?features=IntersectionObserver',
                array(),
                null,
                true
            );
        }
        
        // Localize script
        wp_localize_script('project-gallery-animations', 'projectGalleryAnimations', array(
            'settings' => $this->settings,
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('project_gallery_animations_nonce'),
            'animations' => $this->get_available_animations(),
            'strings' => array(
                'loading' => __('Yükleniyor...', 'project-gallery'),
                'animation_complete' => __('Animasyon tamamlandı', 'project-gallery'),
                'error' => __('Animasyon hatası', 'project-gallery')
            )
        ));
    }
    
    /**
     * Özel animasyon stillerini ekle
     */
    public function add_custom_animation_styles() {
        if (!$this->settings['enable_animations']) {
            return;
        }
        
        ?>
        <style id="project-gallery-custom-animations">
        :root {
            --pgal-animation-duration: <?php echo $this->settings['gallery_entrance_duration']; ?>ms;
            --pgal-animation-delay: <?php echo $this->settings['gallery_entrance_delay']; ?>ms;
            --pgal-animation-stagger: <?php echo $this->settings['gallery_entrance_stagger']; ?>ms;
            --pgal-hover-duration: <?php echo $this->settings['hover_duration']; ?>ms;
            --pgal-hover-timing: <?php echo $this->settings['hover_timing']; ?>;
            --pgal-hover-scale: <?php echo $this->settings['hover_scale']; ?>;
            --pgal-hover-rotate: <?php echo $this->settings['hover_rotate']; ?>deg;
            --pgal-hover-blur: <?php echo $this->settings['hover_blur']; ?>px;
            --pgal-hover-brightness: <?php echo $this->settings['hover_brightness']; ?>;
            --pgal-hover-saturate: <?php echo $this->settings['hover_saturate']; ?>;
            --pgal-loading-duration: <?php echo $this->settings['loading_duration']; ?>ms;
            --pgal-loading-color-start: <?php echo $this->settings['loading_color_start']; ?>;
            --pgal-loading-color-end: <?php echo $this->settings['loading_color_end']; ?>;
            --pgal-parallax-speed: <?php echo $this->settings['parallax_speed']; ?>;
        }
        
        <?php if ($this->settings['performance_mode']): ?>
        /* Performance mode - reduced animations */
        * {
            animation-duration: 0.2s !important;
            transition-duration: 0.2s !important;
        }
        <?php endif; ?>
        
        /* Özel hover efektleri */
        .project-gallery.pgal-hover-<?php echo $this->settings['hover_effect']; ?> .project-item:hover {
            <?php $this->render_hover_effect_css($this->settings['hover_effect']); ?>
        }
        
        /* Özel parallax stilleri */
        <?php if ($this->settings['parallax_enabled']): ?>
        .project-gallery.pgal-parallax .project-item {
            transform: translateZ(0);
            will-change: transform;
        }
        <?php endif; ?>
        </style>
        <?php
    }
    
    /**
     * Hover efekt CSS'ini render et
     */
    private function render_hover_effect_css($effect) {
        switch ($effect) {
            case 'scale':
                echo 'transform: scale(var(--pgal-hover-scale));';
                break;
                
            case 'scale-rotate':
                echo 'transform: scale(var(--pgal-hover-scale)) rotate(var(--pgal-hover-rotate));';
                break;
                
            case 'lift':
                echo 'transform: translateY(-8px);';
                echo 'box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);';
                break;
                
            case 'lift-rotate':
                echo 'transform: translateY(-8px) rotate(var(--pgal-hover-rotate));';
                echo 'box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);';
                break;
                
            case 'blur':
                echo 'filter: blur(var(--pgal-hover-blur));';
                break;
                
            case 'brightness':
                echo 'filter: brightness(var(--pgal-hover-brightness));';
                break;
                
            case 'saturate':
                echo 'filter: saturate(var(--pgal-hover-saturate));';
                break;
                
            case 'soft-scale':
                echo 'transform: scale(1.02);';
                echo 'filter: brightness(1.05);';
                break;
                
            case 'glow':
                echo 'box-shadow: 0 0 20px rgba(0, 123, 186, 0.4);';
                echo 'transform: scale(1.02);';
                break;
                
            default:
                echo 'transform: scale(var(--pgal-hover-scale));';
        }
    }
    
    /**
     * Shortcode çıktısına animasyon attribute'ları ekle
     */
    public function add_animation_attributes($output, $atts) {
        if (!$this->settings['enable_animations']) {
            return $output;
        }
        
        $animation_data = array(
            'animation-entrance' => $this->settings['gallery_entrance'],
            'animation-hover' => $this->settings['hover_effect'],
            'animation-duration' => $this->settings['gallery_entrance_duration'],
            'animation-stagger' => $this->settings['gallery_entrance_stagger'],
            'parallax' => $this->settings['parallax_enabled'] ? 'true' : 'false',
            'parallax-speed' => $this->settings['parallax_speed'],
            'intersection-observer' => $this->settings['use_intersection_observer'] ? 'true' : 'false'
        );
        
        $data_attrs = '';
        foreach ($animation_data as $key => $value) {
            $data_attrs .= ' data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }
        
        $classes = 'pgal-animated pgal-entrance-' . $this->settings['gallery_entrance'] . ' pgal-hover-' . $this->settings['hover_effect'];
        
        if ($this->settings['parallax_enabled']) {
            $classes .= ' pgal-parallax';
        }
        
        if ($this->settings['use_intersection_observer']) {
            $classes .= ' pgal-intersection-observer';
        }
        
        // Gallery container'a attribute'ları ekle
        $output = str_replace(
            'class="project-gallery"',
            'class="project-gallery ' . $classes . '"' . $data_attrs,
            $output
        );
        
        return $output;
    }
    
    /**
     * Proje item'larına animasyon sınıfları ekle
     */
    public function add_item_animation_classes($classes, $item_index) {
        if (!$this->settings['enable_animations']) {
            return $classes;
        }
        
        $classes .= ' pgal-animate-item';
        $classes .= ' pgal-animate-delay-' . ($item_index % 10); // 0-9 arası delay sınıfları
        
        if ($this->settings['loading_animation'] !== 'none') {
            $classes .= ' pgal-loading-' . $this->settings['loading_animation'];
        }
        
        return $classes;
    }
    
    /**
     * Kullanılabilir animasyonları getir
     */
    public function get_available_animations() {
        return array(
            'entrance' => array(
                'none' => __('Animasyon Yok', 'project-gallery'),
                'fade' => __('Solma', 'project-gallery'),
                'fade-up' => __('Aşağıdan Solma', 'project-gallery'),
                'fade-down' => __('Yukarıdan Solma', 'project-gallery'),
                'fade-left' => __('Soldan Solma', 'project-gallery'),
                'fade-right' => __('Sağdan Solma', 'project-gallery'),
                'slide-up' => __('Yukarı Kayma', 'project-gallery'),
                'slide-down' => __('Aşağı Kayma', 'project-gallery'),
                'slide-left' => __('Sola Kayma', 'project-gallery'),
                'slide-right' => __('Sağa Kayma', 'project-gallery'),
                'slide-up-bounce' => __('Yukarı Kayma + Zıplama', 'project-gallery'),
                'zoom-in' => __('Yakınlaştırma', 'project-gallery'),
                'zoom-out' => __('Uzaklaştırma', 'project-gallery'),
                'fade-zoom' => __('Solma + Zoom', 'project-gallery'),
                'flip-left' => __('Soldan Çevirme', 'project-gallery'),
                'flip-right' => __('Sağdan Çevirme', 'project-gallery'),
                'flip-up' => __('Yukarıdan Çevirme', 'project-gallery'),
                'flip-down' => __('Aşağıdan Çevirme', 'project-gallery'),
                'rotate-in' => __('Dönerek Giriş', 'project-gallery'),
                'bounce-in' => __('Zıplayarak Giriş', 'project-gallery'),
                'elastic-in' => __('Elastik Giriş', 'project-gallery'),
                'wave' => __('Dalga Efekti', 'project-gallery')
            ),
            'hover' => array(
                'none' => __('Efekt Yok', 'project-gallery'),
                'scale' => __('Büyütme', 'project-gallery'),
                'scale-rotate' => __('Büyütme + Döndürme', 'project-gallery'),
                'lift' => __('Kaldırma', 'project-gallery'),
                'lift-rotate' => __('Kaldırma + Döndürme', 'project-gallery'),
                'blur' => __('Bulanıklaştırma', 'project-gallery'),
                'brightness' => __('Parlaklık', 'project-gallery'),
                'saturate' => __('Doygunluk', 'project-gallery'),
                'soft-scale' => __('Yumuşak Büyütme', 'project-gallery'),
                'glow' => __('Parlama Efekti', 'project-gallery'),
                'tilt' => __('Eğilme', 'project-gallery'),
                'float' => __('Yüzme', 'project-gallery'),
                'pulse' => __('Nabız', 'project-gallery'),
                'swing' => __('Sallanma', 'project-gallery'),
                'wobble' => __('Titreme', 'project-gallery')
            ),
            'loading' => array(
                'none' => __('Yok', 'project-gallery'),
                'skeleton' => __('İskelet', 'project-gallery'),
                'pulse' => __('Nabız', 'project-gallery'),
                'shimmer' => __('Parıltı', 'project-gallery'),
                'wave' => __('Dalga', 'project-gallery'),
                'dots' => __('Noktalar', 'project-gallery'),
                'bars' => __('Çubuklar', 'project-gallery'),
                'spinner' => __('Döner', 'project-gallery')
            )
        );
    }
    
    /**
     * Admin menüsüne animasyon ayarları ekle
     */
    public function add_animations_menu() {
        add_submenu_page(
            'edit.php?post_type=proje',
            'Animasyon Ayarları',
            'Animasyonlar',
            'manage_options',
            'project-animations-settings',
            array($this, 'animations_settings_page')
        );
    }
    
    /**
     * Animasyon ayarları sayfası
     */
    public function animations_settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_animation_settings();
            echo '<div class="notice notice-success"><p>Ayarlar kaydedildi!</p></div>';
        }
        
        $animations = $this->get_available_animations();
        ?>
        <div class="wrap">
            <h1>🎬 Animasyon ve Efekt Ayarları</h1>
            
            <div class="pgal-admin-container">
                <div class="pgal-admin-main">
                    <form method="post" action="">
                        <?php wp_nonce_field('project_gallery_animation_settings', 'animation_settings_nonce'); ?>
                        
                        <!-- Ana Ayarlar -->
                        <div class="postbox">
                            <h2 class="hndle">⚙️ Genel Animasyon Ayarları</h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Animasyonları Etkinleştir</th>
                                        <td>
                                            <label class="pgal-switch">
                                                <input type="checkbox" name="enable_animations" value="1" <?php checked($this->settings['enable_animations']); ?>>
                                                <span class="pgal-slider"></span>
                                            </label>
                                            <p class="description">Tüm animasyonları tamamen devre dışı bırakır</p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Performans Modu</th>
                                        <td>
                                            <label class="pgal-switch">
                                                <input type="checkbox" name="performance_mode" value="1" <?php checked($this->settings['performance_mode']); ?>>
                                                <span class="pgal-slider"></span>
                                            </label>
                                            <p class="description">Yavaş cihazlar için animasyonları hızlandırır</p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Intersection Observer</th>
                                        <td>
                                            <label class="pgal-switch">
                                                <input type="checkbox" name="use_intersection_observer" value="1" <?php checked($this->settings['use_intersection_observer']); ?>>
                                                <span class="pgal-slider"></span>
                                            </label>
                                            <p class="description">Performanslı scroll tabanlı animasyonlar için</p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Animasyon Eşiği</th>
                                        <td>
                                            <input type="range" name="animation_threshold" min="0" max="1" step="0.1" value="<?php echo $this->settings['animation_threshold']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['animation_threshold']; ?></output>
                                            <p class="description">Animasyonun tetikleneceği görünürlük oranı (0-1)</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Giriş Animasyonları -->
                        <div class="postbox">
                            <h2 class="hndle">🎭 Galeri Giriş Animasyonları</h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Giriş Animasyonu</th>
                                        <td>
                                            <select name="gallery_entrance" id="gallery_entrance">
                                                <?php foreach ($animations['entrance'] as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>" <?php selected($this->settings['gallery_entrance'], $key); ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="button pgal-preview-btn" data-animation="entrance">
                                                👁️ Önizle
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Animasyon Süresi</th>
                                        <td>
                                            <input type="range" name="gallery_entrance_duration" min="200" max="2000" step="50" value="<?php echo $this->settings['gallery_entrance_duration']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['gallery_entrance_duration']; ?></output> ms
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Başlangıç Gecikmesi</th>
                                        <td>
                                            <input type="range" name="gallery_entrance_delay" min="0" max="1000" step="50" value="<?php echo $this->settings['gallery_entrance_delay']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['gallery_entrance_delay']; ?></output> ms
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Kademeli Gecikme</th>
                                        <td>
                                            <input type="range" name="gallery_entrance_stagger" min="0" max="500" step="25" value="<?php echo $this->settings['gallery_entrance_stagger']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['gallery_entrance_stagger']; ?></output> ms
                                            <p class="description">Her resim arasındaki gecikme</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Hover Efektleri -->
                        <div class="postbox">
                            <h2 class="hndle">🖱️ Hover Efektleri</h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Hover Efekti</th>
                                        <td>
                                            <select name="hover_effect" id="hover_effect">
                                                <?php foreach ($animations['hover'] as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>" <?php selected($this->settings['hover_effect'], $key); ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="button pgal-preview-btn" data-animation="hover">
                                                👁️ Önizle
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Hover Süresi</th>
                                        <td>
                                            <input type="range" name="hover_duration" min="100" max="1000" step="50" value="<?php echo $this->settings['hover_duration']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['hover_duration']; ?></output> ms
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Timing Fonksiyonu</th>
                                        <td>
                                            <select name="hover_timing">
                                                <option value="ease" <?php selected($this->settings['hover_timing'], 'ease'); ?>>Ease</option>
                                                <option value="ease-in" <?php selected($this->settings['hover_timing'], 'ease-in'); ?>>Ease In</option>
                                                <option value="ease-out" <?php selected($this->settings['hover_timing'], 'ease-out'); ?>>Ease Out</option>
                                                <option value="ease-in-out" <?php selected($this->settings['hover_timing'], 'ease-in-out'); ?>>Ease In Out</option>
                                                <option value="cubic-bezier(0.25, 0.8, 0.25, 1)" <?php selected($this->settings['hover_timing'], 'cubic-bezier(0.25, 0.8, 0.25, 1)'); ?>>Material Design</option>
                                                <option value="cubic-bezier(0.68, -0.55, 0.265, 1.55)" <?php selected($this->settings['hover_timing'], 'cubic-bezier(0.68, -0.55, 0.265, 1.55)'); ?>>Back</option>
                                            </select>
                                        </td>
                                    </tr>
                                    
                                    <tr class="pgal-hover-param" data-effects="scale,scale-rotate,soft-scale">
                                        <th scope="row">Büyütme Oranı</th>
                                        <td>
                                            <input type="range" name="hover_scale" min="1" max="1.5" step="0.01" value="<?php echo $this->settings['hover_scale']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['hover_scale']; ?></output>x
                                        </td>
                                    </tr>
                                    
                                    <tr class="pgal-hover-param" data-effects="scale-rotate,lift-rotate">
                                        <th scope="row">Döndürme Açısı</th>
                                        <td>
                                            <input type="range" name="hover_rotate" min="-10" max="10" step="0.5" value="<?php echo $this->settings['hover_rotate']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['hover_rotate']; ?></output>°
                                        </td>
                                    </tr>
                                    
                                    <tr class="pgal-hover-param" data-effects="blur">
                                        <th scope="row">Bulanıklaştırma</th>
                                        <td>
                                            <input type="range" name="hover_blur" min="0" max="10" step="0.5" value="<?php echo $this->settings['hover_blur']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['hover_blur']; ?></output>px
                                        </td>
                                    </tr>
                                    
                                    <tr class="pgal-hover-param" data-effects="brightness">
                                        <th scope="row">Parlaklık</th>
                                        <td>
                                            <input type="range" name="hover_brightness" min="0.5" max="2" step="0.1" value="<?php echo $this->settings['hover_brightness']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['hover_brightness']; ?></output>
                                        </td>
                                    </tr>
                                    
                                    <tr class="pgal-hover-param" data-effects="saturate">
                                        <th scope="row">Doygunluk</th>
                                        <td>
                                            <input type="range" name="hover_saturate" min="0" max="3" step="0.1" value="<?php echo $this->settings['hover_saturate']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['hover_saturate']; ?></output>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Loading Animasyonları -->
                        <div class="postbox">
                            <h2 class="hndle">⏳ Yükleme Animasyonları</h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Loading Animasyonu</th>
                                        <td>
                                            <select name="loading_animation">
                                                <?php foreach ($animations['loading'] as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>" <?php selected($this->settings['loading_animation'], $key); ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Loading Süresi</th>
                                        <td>
                                            <input type="range" name="loading_duration" min="500" max="3000" step="100" value="<?php echo $this->settings['loading_duration']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['loading_duration']; ?></output> ms
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Loading Renkleri</th>
                                        <td>
                                            <label>Başlangıç:</label>
                                            <input type="color" name="loading_color_start" value="<?php echo $this->settings['loading_color_start']; ?>">
                                            <label>Bitiş:</label>
                                            <input type="color" name="loading_color_end" value="<?php echo $this->settings['loading_color_end']; ?>">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Parallax Efektleri -->
                        <div class="postbox">
                            <h2 class="hndle">🌊 Parallax Efektleri</h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Parallax Etkinleştir</th>
                                        <td>
                                            <label class="pgal-switch">
                                                <input type="checkbox" name="parallax_enabled" value="1" <?php checked($this->settings['parallax_enabled']); ?>>
                                                <span class="pgal-slider"></span>
                                            </label>
                                            <p class="description">Scroll sırasında derinlik efekti</p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Parallax Hızı</th>
                                        <td>
                                            <input type="range" name="parallax_speed" min="0.1" max="2" step="0.1" value="<?php echo $this->settings['parallax_speed']; ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo $this->settings['parallax_speed']; ?></output>x
                                            <p class="description">Düşük değer = yavaş parallax, Yüksek değer = hızlı parallax</p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Parallax Yönü</th>
                                        <td>
                                            <select name="parallax_direction">
                                                <option value="vertical" <?php selected($this->settings['parallax_direction'], 'vertical'); ?>>Dikey</option>
                                                <option value="horizontal" <?php selected($this->settings['parallax_direction'], 'horizontal'); ?>>Yatay</option>
                                                <option value="both" <?php selected($this->settings['parallax_direction'], 'both'); ?>>Her İki Yön</option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Scroll Animasyonları -->
                        <div class="postbox">
                            <h2 class="hndle">📜 Scroll Animasyonları</h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">Scroll Animasyonları</th>
                                        <td>
                                            <label class="pgal-switch">
                                                <input type="checkbox" name="scroll_animations" value="1" <?php checked($this->settings['scroll_animations']); ?>>
                                                <span class="pgal-slider"></span>
                                            </label>
                                            <p class="description">Scroll ederken animasyonları tetikle</p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Tetikleme Ofseti</th>
                                        <td>
                                            <input type="text" name="scroll_trigger_offset" value="<?php echo $this->settings['scroll_trigger_offset']; ?>" placeholder="10%">
                                            <p class="description">Animasyonun tetikleneceği ekran pozisyonu (% veya px)</p>
                                        </td>
                                    </tr>
                                    
                                    <tr>
                                        <th scope="row">Animasyonu Tekrarla</th>
                                        <td>
                                            <label class="pgal-switch">
                                                <input type="checkbox" name="scroll_repeat" value="1" <?php checked($this->settings['scroll_repeat']); ?>>
                                                <span class="pgal-slider"></span>
                                            </label>
                                            <p class="description">Her scroll'da animasyonu yeniden oynat</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php submit_button('Ayarları Kaydet', 'primary', 'submit', false); ?>
                    </form>
                </div>
                
                <!-- Yan panel -->
                <div class="pgal-admin-sidebar">
                    <!-- Preset'ler -->
                    <div class="postbox">
                        <h2 class="hndle">🎨 Animasyon Preset'leri</h2>
                        <div class="inside">
                            <div class="pgal-presets">
                                <?php foreach ($this->settings['animation_presets'] as $preset_name => $preset_data): ?>
                                    <div class="pgal-preset-card">
                                        <h4><?php echo ucfirst($preset_name); ?></h4>
                                        <p class="pgal-preset-description">
                                            <?php echo $this->get_preset_description($preset_name); ?>
                                        </p>
                                        <button type="button" class="button pgal-apply-preset" data-preset="<?php echo $preset_name; ?>">
                                            Uygula
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <hr>
                            
                            <div class="pgal-custom-preset">
                                <h4>Özel Preset Kaydet</h4>
                                <input type="text" id="custom-preset-name" placeholder="Preset adı">
                                <button type="button" class="button pgal-save-preset">
                                    💾 Kaydet
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Önizleme -->
                    <div class="postbox">
                        <h2 class="hndle">👁️ Canlı Önizleme</h2>
                        <div class="inside">
                            <div class="pgal-preview-area">
                                <div class="pgal-preview-gallery">
                                    <div class="pgal-preview-item" data-index="0">
                                        <div class="pgal-preview-image">1</div>
                                    </div>
                                    <div class="pgal-preview-item" data-index="1">
                                        <div class="pgal-preview-image">2</div>
                                    </div>
                                    <div class="pgal-preview-item" data-index="2">
                                        <div class="pgal-preview-image">3</div>
                                    </div>
                                    <div class="pgal-preview-item" data-index="3">
                                        <div class="pgal-preview-image">4</div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="button button-primary pgal-replay-preview">
                                🔄 Animasyonu Tekrarla
                            </button>
                        </div>
                    </div>
                    
                    <!-- Performans bilgisi -->
                    <div class="postbox">
                        <h2 class="hndle">⚡ Performans Tavsiyeleri</h2>
                        <div class="inside">
                            <div class="pgal-performance-tips">
                                <div class="pgal-tip">
                                    <strong>🚀 Hızlı İpuçları:</strong>
                                    <ul>
                                        <li>Mobil cihazlar için animasyon sürelerini kısa tutun</li>
                                        <li>Çok fazla parallax efekti performansı düşürür</li>
                                        <li>Intersection Observer modern tarayıcılarda daha verimlidir</li>
                                        <li>Performans modunu yavaş cihazlarda etkinleştirin</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .pgal-admin-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            margin-top: 20px;
        }
        
        .pgal-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .pgal-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .pgal-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .pgal-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .pgal-slider {
            background-color: #007cba;
        }
        
        input:checked + .pgal-slider:before {
            transform: translateX(26px);
        }
        
        .pgal-preview-btn {
            margin-left: 10px;
        }
        
        .pgal-hover-param {
            display: none;
        }
        
        .pgal-hover-param.active {
            display: table-row;
        }
        
        .pgal-presets {
            display: grid;
            gap: 15px;
        }
        
        .pgal-preset-card {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        
        .pgal-preset-card h4 {
            margin: 0 0 8px 0;
            color: #007cba;
        }
        
        .pgal-preset-description {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .pgal-preview-area {
            background: #f0f0f0;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .pgal-preview-gallery {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .pgal-preview-item {
            background: white;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .pgal-preview-image {
            background: linear-gradient(45deg, #007cba, #00a0d2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 60px;
            font-weight: bold;
        }
        
        .pgal-tip {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007cba;
        }
        
        .pgal-tip ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        
        .pgal-tip li {
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        @media (max-width: 1200px) {
            .pgal-admin-container {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Hover efekt parametrelerini göster/gizle
            function toggleHoverParams() {
                var selectedEffect = $('#hover_effect').val();
                $('.pgal-hover-param').removeClass('active');
                $('.pgal-hover-param[data-effects*="' + selectedEffect + '"]').addClass('active');
            }
            
            $('#hover_effect').on('change', toggleHoverParams);
            toggleHoverParams();
            
            // Preset uygulama
            $('.pgal-apply-preset').on('click', function() {
                var presetName = $(this).data('preset');
                applyPreset(presetName);
            });
            
            // Animasyon önizleme
            $('.pgal-preview-btn').on('click', function() {
                var animationType = $(this).data('animation');
                previewAnimation(animationType);
            });
            
            // Önizleme tekrarla
            $('.pgal-replay-preview').on('click', function() {
                replayPreview();
            });
            
            // Özel preset kaydetme
            $('.pgal-save-preset').on('click', function() {
                var presetName = $('#custom-preset-name').val();
                if (presetName) {
                    saveCustomPreset(presetName);
                }
            });
            
            function applyPreset(presetName) {
                $.post(ajaxurl, {
                    action: 'get_animation_preset',
                    preset: presetName,
                    nonce: '<?php echo wp_create_nonce('project_gallery_animations_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var settings = response.data;
                        Object.keys(settings).forEach(function(key) {
                            var $field = $('[name="' + key + '"]');
                            if ($field.length) {
                                if ($field.attr('type') === 'checkbox') {
                                    $field.prop('checked', settings[key]);
                                } else {
                                    $field.val(settings[key]);
                                }
                            }
                        });
                        toggleHoverParams();
                        replayPreview();
                    }
                });
            }
            
            function previewAnimation(type) {
                var $preview = $('.pgal-preview-gallery');
                $preview.removeClass('pgal-preview-active');
                
                setTimeout(function() {
                    $preview.addClass('pgal-preview-active pgal-preview-' + type);
                }, 100);
            }
            
            function replayPreview() {
                var $preview = $('.pgal-preview-gallery');
                $preview.removeClass('pgal-preview-active');
                
                setTimeout(function() {
                    $preview.addClass('pgal-preview-active');
                }, 100);
            }
            
            function saveCustomPreset(name) {
                var formData = $('form').serializeArray();
                var settings = {};
                
                formData.forEach(function(item) {
                    settings[item.name] = item.value;
                });
                
                $.post(ajaxurl, {
                    action: 'save_animation_preset',
                    preset_name: name,
                    settings: settings,
                    nonce: '<?php echo wp_create_nonce('project_gallery_animations_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Preset başarıyla kaydedildi!');
                        location.reload();
                    } else {
                        alert('Preset kaydedilemedi: ' + response.data);
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Preset açıklamasını getir
     */
    private function get_preset_description($preset_name) {
        $descriptions = array(
            'minimal' => 'Sade ve hızlı animasyonlar. Performans odaklı.',
            'dynamic' => 'Canlı ve etkileşimli animasyonlar. Dikkat çekici.',
            'elegant' => 'Zarif ve profesyonel animasyonlar. İş siteleri için ideal.'
        );
        
        return isset($descriptions[$preset_name]) ? $descriptions[$preset_name] : '';
    }
    
    /**
     * Animasyon ayarlarını kaydet
     */
    private function save_animation_settings() {
        if (!wp_verify_nonce($_POST['animation_settings_nonce'], 'project_gallery_animation_settings')) {
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
        
        update_option('project_gallery_animation_settings', $settings);
        $this->settings = $settings;
    }
    
    /**
     * AJAX: Animasyon önizlemesi
     */
    public function ajax_preview_animation() {
        check_ajax_referer('project_gallery_animations_nonce', 'nonce');
        
        $animation_type = sanitize_text_field($_POST['animation_type']);
        $animation_name = sanitize_text_field($_POST['animation_name']);
        
        // Önizleme HTML'i oluştur
        ob_start();
        ?>
        <div class="pgal-animation-preview pgal-<?php echo $animation_type; ?>-<?php echo $animation_name; ?>">
            <div class="pgal-preview-item">Önizleme</div>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'css' => $this->generate_preview_css($animation_type, $animation_name)
        ));
    }
    
    /**
     * Önizleme CSS'i oluştur
     */
    private function generate_preview_css($type, $name) {
        // Bu fonksiyon animasyon türüne göre CSS oluşturur
        // Şimdilik basit bir implementasyon
        return ".pgal-{$type}-{$name} { animation: {$name} 1s ease-in-out; }";
    }
    
    /**
     * AJAX: Özel preset kaydet
     */
    public function ajax_save_animation_preset() {
        check_ajax_referer('project_gallery_animations_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetki yok');
        }
        
        $preset_name = sanitize_text_field($_POST['preset_name']);
        $settings = array_map('sanitize_text_field', $_POST['settings']);
        
        if (empty($preset_name)) {
            wp_send_json_error('Preset adı boş olamaz');
        }
        
        // Mevcut ayarları getir
        $current_settings = $this->settings;
        $current_settings['animation_presets'][$preset_name] = $settings;
        
        update_option('project_gallery_animation_settings', $current_settings);
        
        wp_send_json_success('Preset başarıyla kaydedildi');
    }
    
    /**
     * Animasyon ayarlarını getir
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Animasyon ayarlarını güncelle
     */
    public function update_settings($new_settings) {
        $this->settings = array_merge($this->settings, $new_settings);
        update_option('project_gallery_animation_settings', $this->settings);
    }
    
    /**
     * Animasyon register ayarları
     */
    public function register_animation_settings() {
        register_setting('project_gallery_animation_settings', 'project_gallery_animation_settings');
    }
}