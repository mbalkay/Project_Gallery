<?php
/**
 * Plugin Name: Project Gallery Pro
 * Plugin URI: https://github.com/mbalkay/Project_Gallery
 * Description: Professional WordPress project gallery plugin with advanced features, analytics, video support, and modern design capabilities.
 * Version: 2.0.0
 * Author: mbalkay
 * License: GPL v2 or later
 * Text Domain: project-gallery
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

// Plugin sabitleri
define('PROJECT_GALLERY_VERSION', '2.0.0');
define('PROJECT_GALLERY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PROJECT_GALLERY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PROJECT_GALLERY_DB_VERSION', '2.0');
define('PROJECT_GALLERY_MIN_PHP_VERSION', '7.4');
define('PROJECT_GALLERY_MIN_WP_VERSION', '5.0');

/**
 * Ana Plugin Sınıfı
 */
class ProjectGallery {
    
    private $analytics;
    private $import_export;
    private $video;
    private $performance;
    private $social;
    private $search;
    
    public function __construct() {
        // Load all advanced features
        $this->load_dependencies();
        $this->init_advanced_features();
        
        // Core hooks
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_project_gallery'));
        add_shortcode('proje_galerisi', array($this, 'project_gallery_shortcode'));
        add_filter('single_template', array($this, 'single_project_template'));
        add_filter('archive_template', array($this, 'archive_project_template'));
        add_action('wp_ajax_get_project_images', array($this, 'ajax_get_project_images'));
        add_action('wp_ajax_nopriv_get_project_images', array($this, 'ajax_get_project_images'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('wp_head', array($this, 'dynamic_gallery_styles'));
        
        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));
        register_uninstall_hook(__FILE__, array('ProjectGallery', 'uninstall_plugin'));
        
        // Update checker
        add_action('wp_loaded', array($this, 'check_for_updates'));
    }
    
    /**
     * Load all dependencies
     */
    private function load_dependencies() {
        require_once PROJECT_GALLERY_PLUGIN_DIR . 'includes/class-analytics.php';
        require_once PROJECT_GALLERY_PLUGIN_DIR . 'includes/class-import-export.php';
        require_once PROJECT_GALLERY_PLUGIN_DIR . 'includes/class-video.php';
        require_once PROJECT_GALLERY_PLUGIN_DIR . 'includes/class-performance.php';
        require_once PROJECT_GALLERY_PLUGIN_DIR . 'includes/class-social.php';
        require_once PROJECT_GALLERY_PLUGIN_DIR . 'includes/class-search.php';
    }
    
    /**
     * Initialize advanced features
     */
    private function init_advanced_features() {
        $this->analytics = new ProjectGalleryAnalytics();
        $this->import_export = new ProjectGalleryImportExport();
        $this->video = new ProjectGalleryVideo();
        $this->performance = new ProjectGalleryPerformance();
        $this->social = new ProjectGallerySocial();
        $this->search = new ProjectGallerySearch();
    }
    
    /**
     * Plugin başlatma
     */
    public function init() {
        $this->register_post_type();
        $this->register_taxonomy();
        $this->add_image_sizes();
    }
    
    /**
     * Özel yazı tipi kaydı
     */
    public function register_post_type() {
        $labels = array(
            'name' => 'Projeler',
            'singular_name' => 'Proje',
            'menu_name' => 'Projeler',
            'add_new' => 'Yeni Proje Ekle',
            'add_new_item' => 'Yeni Proje Ekle',
            'edit_item' => 'Proje Düzenle',
            'new_item' => 'Yeni Proje',
            'view_item' => 'Projeyi Görüntüle',
            'search_items' => 'Proje Ara',
            'not_found' => 'Proje bulunamadı',
            'not_found_in_trash' => 'Çöp kutusunda proje bulunamadı'
        );
        
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'proje'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-camera',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest' => true
        );
        
        register_post_type('proje', $args);
    }
    
    /**
     * Özel taksonomi kaydı
     */
    public function register_taxonomy() {
        $labels = array(
            'name' => 'Proje Kategorileri',
            'singular_name' => 'Proje Kategorisi',
            'search_items' => 'Kategorileri Ara',
            'all_items' => 'Tüm Kategoriler',
            'parent_item' => 'Üst Kategori',
            'parent_item_colon' => 'Üst Kategori:',
            'edit_item' => 'Kategori Düzenle',
            'update_item' => 'Kategori Güncelle',
            'add_new_item' => 'Yeni Kategori Ekle',
            'new_item_name' => 'Yeni Kategori Adı',
            'menu_name' => 'Kategoriler'
        );
        
        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'proje-kategorisi'),
            'show_in_rest' => true
        );
        
        register_taxonomy('proje_kategorisi', array('proje'), $args);
    }
    
    /**
     * Görsel boyutları ekle
     */
    public function add_image_sizes() {
        add_image_size('proje-thumbnail', 400, 300, true);
        add_image_size('proje-medium', 800, 600, true);
        add_image_size('proje-large', 1200, 900, true);
    }
    
    /**
     * CSS ve JS dosyalarını yükle
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'project-gallery-style',
            PROJECT_GALLERY_PLUGIN_URL . 'assets/css/project-gallery.css',
            array(),
            PROJECT_GALLERY_VERSION
        );
        
        wp_enqueue_script(
            'project-gallery-script',
            PROJECT_GALLERY_PLUGIN_URL . 'assets/js/project-gallery.js',
            array('jquery'),
            PROJECT_GALLERY_VERSION,
            true
        );
        
        wp_localize_script('project-gallery-script', 'projectGallery', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('project_gallery_nonce')
        ));
        
        // Admin scripts
        if (is_admin()) {
            wp_enqueue_media();
            wp_enqueue_script('jquery-ui-sortable');
            
            // Add admin-specific styles
            wp_add_inline_style('project-gallery-style', '
                body.lightbox-open {
                    overflow: hidden;
                }
            ');
        }
    }
    
    /**
     * Meta box ekle
     */
    public function add_meta_boxes() {
        add_meta_box(
            'project_gallery_meta',
            'Proje Galerisi',
            array($this, 'project_gallery_meta_box'),
            'proje',
            'normal',
            'high'
        );
    }
    
    /**
     * Proje galerisi meta box içeriği
     */
    public function project_gallery_meta_box($post) {
        wp_nonce_field('project_gallery_meta_box', 'project_gallery_meta_box_nonce');
        
        $gallery_images = get_post_meta($post->ID, '_project_gallery_images', true);
        ?>
        <div id="project-gallery-container">
            <div class="gallery-actions">
                <input type="button" id="project-gallery-button" class="button button-primary" value="📷 Toplu Fotoğraf Ekle" />
                <input type="button" id="project-gallery-add-single" class="button" value="➕ Tek Fotoğraf Ekle" />
                <input type="button" id="project-gallery-clear" class="button" value="🗑️ Tümünü Temizle" />
                <input type="hidden" id="project-gallery-images" name="project_gallery_images" value="<?php echo esc_attr($gallery_images); ?>" />
            </div>
            
            <div class="gallery-help">
                <p><strong>💡 İpucu:</strong> Fotoğrafları sürükleyerek yeniden sıralayabilirsiniz. Lightbox'ta orijinal boyutlarda gösterilecektir.</p>
            </div>
            
            <div id="project-gallery-preview" class="gallery-grid">
                <?php if ($gallery_images): ?>
                    <?php $image_ids = explode(',', $gallery_images); ?>
                    <?php foreach ($image_ids as $image_id): ?>
                        <?php if ($image_id): ?>
                            <div class="gallery-image-preview" data-id="<?php echo $image_id; ?>">
                                <div class="image-container">
                                    <?php echo wp_get_attachment_image($image_id, 'thumbnail'); ?>
                                    <div class="image-overlay">
                                        <button type="button" class="remove-gallery-image" data-id="<?php echo $image_id; ?>" title="Kaldır">×</button>
                                        <div class="image-info">
                                            <span class="image-name"><?php echo basename(get_attached_file($image_id)); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="gallery-empty" <?php echo $gallery_images ? 'style="display:none;"' : ''; ?>>
                <div class="empty-state">
                    <div class="empty-icon">📸</div>
                    <h3>Henüz fotoğraf eklenmemiş</h3>
                    <p>Proje galeriniz için fotoğraflar ekleyin. Birden fazla fotoğrafı aynı anda seçebilirsiniz.</p>
                    <button type="button" id="project-gallery-button-empty" class="button button-primary button-large">Fotoğraf Ekle</button>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var frame;
            var currentImages = [];
            
            // Update current images array from hidden field
            function updateCurrentImages() {
                var imageIds = $('#project-gallery-images').val();
                currentImages = imageIds ? imageIds.split(',').filter(Boolean) : [];
            }
            
            // Update UI based on current state
            function updateUI() {
                var hasImages = currentImages.length > 0;
                $('#project-gallery-preview').toggle(hasImages);
                $('.gallery-empty').toggle(!hasImages);
            }
            
            // Add new images to gallery
            function addImagesToGallery(selection, replace = false) {
                if (replace) {
                    currentImages = [];
                    $('#project-gallery-preview').empty();
                }
                
                selection.map(function(attachment) {
                    attachment = attachment.toJSON();
                    var imageId = attachment.id.toString();
                    
                    // Check if image already exists
                    if (currentImages.indexOf(imageId) === -1) {
                        currentImages.push(imageId);
                        
                        // Add to preview
                        var newImageHtml = '<div class="gallery-image-preview" data-id="' + imageId + '">';
                        newImageHtml += '<div class="image-container">';
                        newImageHtml += '<img src="' + attachment.sizes.thumbnail.url + '" />';
                        newImageHtml += '<div class="image-overlay">';
                        newImageHtml += '<button type="button" class="remove-gallery-image" data-id="' + imageId + '" title="Kaldır">×</button>';
                        newImageHtml += '<div class="image-info">';
                        newImageHtml += '<span class="image-name">' + attachment.filename + '</span>';
                        newImageHtml += '</div></div></div></div>';
                        $('#project-gallery-preview').append(newImageHtml);
                    }
                });
                
                $('#project-gallery-images').val(currentImages.join(','));
                updateUI();
                initSortable();
            }
            
            // Initialize sortable functionality
            function initSortable() {
                if ($('#project-gallery-preview').hasClass('ui-sortable')) {
                    $('#project-gallery-preview').sortable('destroy');
                }
                
                $('#project-gallery-preview').sortable({
                    items: '.gallery-image-preview',
                    placeholder: 'gallery-sortable-placeholder',
                    tolerance: 'pointer',
                    update: function() {
                        var newOrder = [];
                        $(this).find('.gallery-image-preview').each(function() {
                            newOrder.push($(this).data('id'));
                        });
                        currentImages = newOrder.map(String);
                        $('#project-gallery-images').val(currentImages.join(','));
                    }
                });
            }
            
            // Open media frame
            function openMediaFrame(multiple = true, replace = false) {
                frame = wp.media({
                    title: multiple ? 'Toplu Fotoğraf Seç' : 'Fotoğraf Seç',
                    button: {
                        text: multiple ? 'Fotoğrafları Ekle' : 'Fotoğraf Ekle'
                    },
                    multiple: multiple
                });
                
                frame.on('select', function() {
                    var selection = frame.state().get('selection');
                    addImagesToGallery(selection, replace);
                });
                
                frame.open();
            }
            
            // Event handlers
            $('#project-gallery-button, #project-gallery-button-empty').on('click', function(e) {
                e.preventDefault();
                updateCurrentImages();
                openMediaFrame(true, false);
            });
            
            $('#project-gallery-add-single').on('click', function(e) {
                e.preventDefault();
                updateCurrentImages();
                openMediaFrame(false, false);
            });
            
            $('#project-gallery-clear').on('click', function(e) {
                e.preventDefault();
                if (confirm('Tüm fotoğrafları kaldırmak istediğinizden emin misiniz?')) {
                    currentImages = [];
                    $('#project-gallery-images').val('');
                    $('#project-gallery-preview').empty();
                    updateUI();
                }
            });
            
            $(document).on('click', '.remove-gallery-image', function() {
                var imageId = $(this).data('id').toString();
                currentImages = currentImages.filter(function(id) {
                    return id !== imageId;
                });
                
                $('#project-gallery-images').val(currentImages.join(','));
                $(this).closest('.gallery-image-preview').fadeOut(300, function() {
                    $(this).remove();
                    updateUI();
                });
            });
            
            // Initialize
            updateCurrentImages();
            updateUI();
            initSortable();
        });
        </script>
        
        <style>
        /* Gallery Admin Styles */
        #project-gallery-container {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        
        .gallery-actions {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .gallery-actions .button {
            margin-right: 10px;
        }
        
        .gallery-help {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 20px;
        }
        
        .gallery-help p {
            margin: 0;
            color: #004085;
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            min-height: 60px;
        }
        
        .gallery-image-preview {
            position: relative;
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            cursor: move;
            transition: all 0.3s ease;
        }
        
        .gallery-image-preview:hover {
            border-color: #007cba;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .gallery-image-preview.ui-sortable-helper {
            transform: rotate(3deg);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 1000;
        }
        
        .gallery-sortable-placeholder {
            border: 2px dashed #007cba;
            background: #e7f3ff;
            border-radius: 8px;
            opacity: 0.7;
        }
        
        .image-container {
            position: relative;
            aspect-ratio: 1;
        }
        
        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 5px;
        }
        
        .gallery-image-preview:hover .image-overlay {
            opacity: 1;
        }
        
        .remove-gallery-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #dc3232;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: background 0.3s ease;
        }
        
        .remove-gallery-image:hover {
            background: #a00;
        }
        
        .image-info {
            margin-top: auto;
        }
        
        .image-name {
            color: white;
            font-size: 10px;
            display: block;
            text-overflow: ellipsis;
            overflow: hidden;
            white-space: nowrap;
        }
        
        .gallery-empty {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border: 2px dashed #ddd;
            border-radius: 8px;
        }
        
        .empty-state {
            max-width: 300px;
            margin: 0 auto;
        }
        
        .empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .button-large {
            padding: 10px 20px !important;
            font-size: 14px !important;
        }
        
        /* Responsive admin styles */
        @media (max-width: 768px) {
            .gallery-grid {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
                gap: 10px;
            }
            
            .gallery-actions .button {
                display: block;
                margin: 5px 0;
                width: 100%;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Proje galerisi kaydet
     */
    public function save_project_gallery($post_id) {
        if (!isset($_POST['project_gallery_meta_box_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['project_gallery_meta_box_nonce'], 'project_gallery_meta_box')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (isset($_POST['post_type']) && 'proje' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }
        
        if (isset($_POST['project_gallery_images'])) {
            update_post_meta($post_id, '_project_gallery_images', sanitize_text_field($_POST['project_gallery_images']));
        }
    }
    
    /**
     * Proje galerisi kısa kodu
     */
    public function project_gallery_shortcode($atts) {
        $atts = shortcode_atts(array(
            'kategori' => '',
            'limit' => -1,
            'columns' => 3
        ), $atts, 'proje_galerisi');
        
        $args = array(
            'post_type' => 'proje',
            'posts_per_page' => $atts['limit'],
            'post_status' => 'publish'
        );
        
        if (!empty($atts['kategori'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'proje_kategorisi',
                    'field' => 'slug',
                    'terms' => $atts['kategori']
                )
            );
        }
        
        $projects = new WP_Query($args);
        
        if (!$projects->have_posts()) {
            return '<p>Proje bulunamadı.</p>';
        }
        
        ob_start();
        ?>
        <div class="project-gallery" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <?php while ($projects->have_posts()): $projects->the_post(); ?>
                <div class="project-item">
                    <a href="<?php the_permalink(); ?>" class="project-link">
                        <?php if (has_post_thumbnail()): ?>
                            <div class="project-thumbnail">
                                <?php the_post_thumbnail('proje-thumbnail'); ?>
                                <div class="project-overlay">
                                    <h3 class="project-title"><?php the_title(); ?></h3>
                                    <?php $categories = get_the_terms(get_the_ID(), 'proje_kategorisi'); ?>
                                    <?php if ($categories): ?>
                                        <div class="project-categories">
                                            <?php foreach ($categories as $category): ?>
                                                <span class="project-category"><?php echo esc_html($category->name); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
    
    /**
     * Tekil proje sayfası şablonu
     */
    public function single_project_template($single) {
        global $post;
        
        if ($post->post_type == 'proje') {
            if (file_exists(PROJECT_GALLERY_PLUGIN_DIR . 'templates/single-proje.php')) {
                return PROJECT_GALLERY_PLUGIN_DIR . 'templates/single-proje.php';
            }
        }
        
        return $single;
    }
    
    /**
     * Proje arşiv sayfası şablonu
     */
    public function archive_project_template($archive) {
        if (is_post_type_archive('proje') || is_tax('proje_kategorisi')) {
            if (file_exists(PROJECT_GALLERY_PLUGIN_DIR . 'templates/archive-proje.php')) {
                return PROJECT_GALLERY_PLUGIN_DIR . 'templates/archive-proje.php';
            }
        }
        
        return $archive;
    }
    
    /**
     * AJAX ile proje resimlerini getir
     */
    public function ajax_get_project_images() {
        if (!wp_verify_nonce($_POST['nonce'], 'project_gallery_nonce')) {
            wp_die('Güvenlik kontrolü başarısız.');
        }
        
        $project_id = intval($_POST['project_id']);
        $gallery_images = get_post_meta($project_id, '_project_gallery_images', true);
        
        if (!$gallery_images) {
            wp_die('Resim bulunamadı.');
        }
        
        $image_ids = explode(',', $gallery_images);
        $images = array();
        
        foreach ($image_ids as $image_id) {
            if ($image_id) {
                $image_full = wp_get_attachment_image_url($image_id, 'full');
                $image_large = wp_get_attachment_image_url($image_id, 'proje-large');
                $image_medium = wp_get_attachment_image_url($image_id, 'proje-medium');
                $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                $image_title = get_the_title($image_id);
                
                if ($image_full) {
                    $images[] = array(
                        'full' => $image_full,
                        'large' => $image_large,
                        'medium' => $image_medium,
                        'alt' => $image_alt,
                        'title' => $image_title,
                        'id' => $image_id
                    );
                }
            }
        }
        
        wp_send_json_success($images);
    }
    
    /**
     * Admin menüsü ekle
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=proje',
            'Galeri Ayarları',
            'Galeri Ayarları',
            'manage_options',
            'project-gallery-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Ayarları kaydet
     */
    public function settings_init() {
        register_setting('project_gallery_settings', 'project_gallery_options');
        
        // Ana galeri ayarları
        add_settings_section(
            'project_gallery_main_section',
            'Ana Galeri Ayarları',
            array($this, 'settings_section_callback'),
            'project_gallery_settings'
        );
        
        // Layout tipi
        add_settings_field(
            'layout_type',
            'Galeri Düzen Tipi',
            array($this, 'layout_type_callback'),
            'project_gallery_settings',
            'project_gallery_main_section'
        );
        
        // Sütun sayısı
        add_settings_field(
            'columns_count',
            'Sütun Sayısı',
            array($this, 'columns_count_callback'),
            'project_gallery_settings',
            'project_gallery_main_section'
        );
        
        // Resim aralığı
        add_settings_field(
            'image_spacing',
            'Resim Aralığı (px)',
            array($this, 'image_spacing_callback'),
            'project_gallery_settings',
            'project_gallery_main_section'
        );
        
        // Resim boyutu
        add_settings_field(
            'image_size',
            'Resim Boyutu',
            array($this, 'image_size_callback'),
            'project_gallery_settings',
            'project_gallery_main_section'
        );
        
        // Köşe yuvarlaklığı
        add_settings_field(
            'border_radius',
            'Köşe Yuvarlaklığı (px)',
            array($this, 'border_radius_callback'),
            'project_gallery_settings',
            'project_gallery_main_section'
        );
        
        // Hover efekti
        add_settings_field(
            'hover_effect',
            'Hover Efekti',
            array($this, 'hover_effect_callback'),
            'project_gallery_settings',
            'project_gallery_main_section'
        );
        
        // Responsive ayarları
        add_settings_section(
            'project_gallery_responsive_section',
            'Responsive Ayarları',
            array($this, 'responsive_section_callback'),
            'project_gallery_settings'
        );
        
        // Tablet sütun sayısı
        add_settings_field(
            'tablet_columns',
            'Tablet Sütun Sayısı',
            array($this, 'tablet_columns_callback'),
            'project_gallery_settings',
            'project_gallery_responsive_section'
        );
        
        // Mobil sütun sayısı
        add_settings_field(
            'mobile_columns',
            'Mobil Sütun Sayısı',
            array($this, 'mobile_columns_callback'),
            'project_gallery_settings',
            'project_gallery_responsive_section'
        );
    }
    
    /**
     * Ayarlar sayfası
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>🎨 Proje Galerisi Ayarları</h1>
            <p>Galeri görünümünü özelleştirmek için aşağıdaki ayarları kullanın.</p>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('project_gallery_settings');
                do_settings_sections('project_gallery_settings');
                ?>
                
                <div class="gallery-preview-section">
                    <h2>🖼️ Galeri Önizleme</h2>
                    <div id="gallery-preview" class="gallery-preview">
                        <div class="preview-item"><div class="preview-image">1</div></div>
                        <div class="preview-item"><div class="preview-image">2</div></div>
                        <div class="preview-item"><div class="preview-image">3</div></div>
                        <div class="preview-item"><div class="preview-image">4</div></div>
                        <div class="preview-item"><div class="preview-image">5</div></div>
                        <div class="preview-item"><div class="preview-image">6</div></div>
                    </div>
                </div>
                
                <?php submit_button('Ayarları Kaydet'); ?>
            </form>
            
            <style>
            .gallery-preview-section {
                background: #f1f1f1;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .gallery-preview {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-top: 15px;
                max-width: 600px;
            }
            .preview-item {
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .preview-image {
                background: linear-gradient(45deg, #007cba, #00a0d2);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 120px;
                font-weight: bold;
                font-size: 18px;
            }
            .form-table th {
                width: 200px;
            }
            .form-table td {
                padding: 15px 10px;
            }
            .description {
                font-style: italic;
                color: #666;
                margin-top: 5px;
            }
            </style>
            
            <script>
            jQuery(document).ready(function($) {
                function updatePreview() {
                    var columns = $('#columns_count').val() || 3;
                    var spacing = $('#image_spacing').val() || 15;
                    var borderRadius = $('#border_radius').val() || 8;
                    var layoutType = $('#layout_type').val() || 'grid';
                    
                    var preview = $('#gallery-preview');
                    preview.css({
                        'grid-template-columns': 'repeat(' + columns + ', 1fr)',
                        'gap': spacing + 'px'
                    });
                    
                    $('.preview-item').css('border-radius', borderRadius + 'px');
                    
                    if (layoutType === 'masonry') {
                        $('.preview-image').each(function(i) {
                            $(this).css('height', (100 + (i % 3) * 40) + 'px');
                        });
                    } else {
                        $('.preview-image').css('height', '120px');
                    }
                }
                
                $('#columns_count, #image_spacing, #border_radius, #layout_type').on('change input', updatePreview);
                updatePreview();
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * Callback fonksiyonları
     */
    public function settings_section_callback() {
        echo '<p>Galeri düzenini ve görünümünü bu ayarlarla özelleştirin.</p>';
    }
    
    public function responsive_section_callback() {
        echo '<p>Farklı ekran boyutları için sütun sayılarını ayarlayın.</p>';
    }
    
    public function layout_type_callback() {
        $options = get_option('project_gallery_options');
        $value = isset($options['layout_type']) ? $options['layout_type'] : 'grid';
        ?>
        <select id="layout_type" name="project_gallery_options[layout_type]">
            <option value="grid" <?php selected($value, 'grid'); ?>>📐 Grid (Düzenli Izgara)</option>
            <option value="masonry" <?php selected($value, 'masonry'); ?>>🧱 Masonry (Tuğla Düzeni)</option>
            <option value="justified" <?php selected($value, 'justified'); ?>>📏 Justified (Hizalanmış)</option>
            <option value="flexible" <?php selected($value, 'flexible'); ?>>🔄 Flexible (Esnek)</option>
        </select>
        <p class="description">Galeri düzen tipini seçin. Grid = düzenli, Masonry = farklı yükseklikler</p>
        <?php
    }
    
    public function columns_count_callback() {
        $options = get_option('project_gallery_options');
        $value = isset($options['columns_count']) ? $options['columns_count'] : 3;
        ?>
        <input type="range" id="columns_count" name="project_gallery_options[columns_count]" 
               min="1" max="6" value="<?php echo esc_attr($value); ?>" 
               oninput="this.nextElementSibling.value = this.value">
        <output><?php echo esc_attr($value); ?></output> sütun
        <p class="description">Masaüstü için sütun sayısı (1-6 arası)</p>
        <?php
    }
    
    public function image_spacing_callback() {
        $options = get_option('project_gallery_options');
        $value = isset($options['image_spacing']) ? $options['image_spacing'] : 15;
        ?>
        <input type="range" id="image_spacing" name="project_gallery_options[image_spacing]" 
               min="0" max="50" value="<?php echo esc_attr($value); ?>" 
               oninput="this.nextElementSibling.value = this.value">
        <output><?php echo esc_attr($value); ?></output> px
        <p class="description">Resimler arası boşluk (0-50px arası)</p>
        <?php
    }
    
    public function image_size_callback() {
        $options = get_option('project_gallery_options');
        $value = isset($options['image_size']) ? $options['image_size'] : 'proje-medium';
        ?>
        <select name="project_gallery_options[image_size]">
            <option value="proje-thumbnail" <?php selected($value, 'proje-thumbnail'); ?>>Küçük (300x200)</option>
            <option value="proje-medium" <?php selected($value, 'proje-medium'); ?>>Orta (600x400)</option>
            <option value="proje-large" <?php selected($value, 'proje-large'); ?>>Büyük (1200x800)</option>
            <option value="full" <?php selected($value, 'full'); ?>>Orijinal Boyut</option>
        </select>
        <p class="description">Galeride gösterilecek resim boyutunu seçin</p>
        <?php
    }
    
    public function border_radius_callback() {
        $options = get_option('project_gallery_options');
        $value = isset($options['border_radius']) ? $options['border_radius'] : 8;
        ?>
        <input type="range" id="border_radius" name="project_gallery_options[border_radius]" 
               min="0" max="30" value="<?php echo esc_attr($value); ?>" 
               oninput="this.nextElementSibling.value = this.value">
        <output><?php echo esc_attr($value); ?></output> px
        <p class="description">Köşe yuvarlaklığı (0-30px arası)</p>
        <?php
    }
    
    public function hover_effect_callback() {
        $options = get_option('project_gallery_options');
        $value = isset($options['hover_effect']) ? $options['hover_effect'] : 'scale';
        ?>
        <select name="project_gallery_options[hover_effect]">
            <option value="none" <?php selected($value, 'none'); ?>>Yok</option>
            <option value="scale" <?php selected($value, 'scale'); ?>>↗️ Büyütme</option>
            <option value="lift" <?php selected($value, 'lift'); ?>>⬆️ Kaldırma</option>
            <option value="fade" <?php selected($value, 'fade'); ?>>💫 Solma</option>
            <option value="rotate" <?php selected($value, 'rotate'); ?>>🔄 Döndürme</option>
        </select>
        <p class="description">Resim üzerine gelindiğinde gösterilecek efekt</p>
        <?php
    }
    
    public function tablet_columns_callback() {
        $options = get_option('project_gallery_options');
        $value = isset($options['tablet_columns']) ? $options['tablet_columns'] : 2;
        ?>
        <input type="range" name="project_gallery_options[tablet_columns]" 
               min="1" max="4" value="<?php echo esc_attr($value); ?>" 
               oninput="this.nextElementSibling.value = this.value">
        <output><?php echo esc_attr($value); ?></output> sütun
        <p class="description">Tablet (768px-1024px) için sütun sayısı</p>
        <?php
    }
    
    public function mobile_columns_callback() {
        $options = get_option('project_gallery_options');
        $value = isset($options['mobile_columns']) ? $options['mobile_columns'] : 1;
        ?>
        <input type="range" name="project_gallery_options[mobile_columns]" 
               min="1" max="3" value="<?php echo esc_attr($value); ?>" 
               oninput="this.nextElementSibling.value = this.value">
        <output><?php echo esc_attr($value); ?></output> sütun
        <p class="description">Mobil (max 768px) için sütun sayısı</p>
        <?php
    }
    
    /**
     * Admin için script ve stiller
     */
    public function admin_enqueue_scripts($hook) {
        if ($hook === 'proje_page_project-gallery-settings') {
            wp_enqueue_script('jquery');
        }
    }
    
    /**
     * Dinamik galeri stilleri
     */
    public function dynamic_gallery_styles() {
        if (!is_singular('proje')) return;
        
        $options = get_option('project_gallery_options');
        $layout_type = isset($options['layout_type']) ? $options['layout_type'] : 'grid';
        $columns = isset($options['columns_count']) ? $options['columns_count'] : 3;
        $spacing = isset($options['image_spacing']) ? $options['image_spacing'] : 15;
        $border_radius = isset($options['border_radius']) ? $options['border_radius'] : 8;
        $hover_effect = isset($options['hover_effect']) ? $options['hover_effect'] : 'scale';
        $tablet_columns = isset($options['tablet_columns']) ? $options['tablet_columns'] : 2;
        $mobile_columns = isset($options['mobile_columns']) ? $options['mobile_columns'] : 1;
        
        ?>
        <style id="project-gallery-dynamic-styles">
        .gallery-grid {
            <?php if ($layout_type === 'masonry'): ?>
            display: grid;
            grid-template-columns: repeat(<?php echo $columns; ?>, 1fr);
            grid-auto-rows: 10px;
            gap: <?php echo $spacing; ?>px;
            <?php elseif ($layout_type === 'justified'): ?>
            display: flex;
            flex-wrap: wrap;
            gap: <?php echo $spacing; ?>px;
            <?php elseif ($layout_type === 'flexible'): ?>
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: <?php echo $spacing; ?>px;
            <?php else: ?>
            display: grid;
            grid-template-columns: repeat(<?php echo $columns; ?>, 1fr);
            gap: <?php echo $spacing; ?>px;
            <?php endif; ?>
        }
        
        .gallery-image {
            border-radius: <?php echo $border_radius; ?>px;
        }
        
        .gallery-image-container {
            border-radius: <?php echo max(0, $border_radius - 2); ?>px;
        }
        
        <?php if ($layout_type === 'masonry'): ?>
        .gallery-image:nth-child(3n+1) { grid-row-end: span 20; }
        .gallery-image:nth-child(3n+2) { grid-row-end: span 25; }
        .gallery-image:nth-child(3n+3) { grid-row-end: span 22; }
        <?php endif; ?>
        
        <?php if ($layout_type === 'justified'): ?>
        .gallery-image {
            flex: 1 0 calc(<?php echo (100 / $columns); ?>% - <?php echo $spacing; ?>px);
            height: 250px;
        }
        .gallery-image-container {
            height: 100%;
        }
        .gallery-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        <?php endif; ?>
        
        <?php if ($hover_effect === 'scale'): ?>
        .gallery-image:hover .gallery-image-container img {
            transform: scale(1.05);
        }
        <?php elseif ($hover_effect === 'lift'): ?>
        .gallery-image:hover {
            transform: translateY(-5px);
        }
        <?php elseif ($hover_effect === 'fade'): ?>
        .gallery-image:hover {
            opacity: 0.8;
        }
        <?php elseif ($hover_effect === 'rotate'): ?>
        .gallery-image:hover .gallery-image-container img {
            transform: rotate(2deg) scale(1.02);
        }
        <?php endif; ?>
        
        /* Responsive Styles */
        @media (max-width: 1024px) {
            .gallery-grid {
                grid-template-columns: repeat(<?php echo $tablet_columns; ?>, 1fr) !important;
            }
            <?php if ($layout_type === 'justified'): ?>
            .gallery-image {
                flex: 1 0 calc(<?php echo (100 / $tablet_columns); ?>% - <?php echo $spacing; ?>px);
            }
            <?php endif; ?>
        }
        
        @media (max-width: 768px) {
            .gallery-grid {
                grid-template-columns: repeat(<?php echo $mobile_columns; ?>, 1fr) !important;
                gap: <?php echo max(10, $spacing - 5); ?>px;
            }
            <?php if ($layout_type === 'justified'): ?>
            .gallery-image {
                flex: 1 0 calc(<?php echo (100 / $mobile_columns); ?>% - <?php echo max(10, $spacing - 5); ?>px);
            }
            <?php endif; ?>
        }
        </style>
        <?php
    }
    
    /**
     * Plugin activation
     */
    public function activate_plugin() {
        // Create database tables
        $this->analytics->create_analytics_table();
        
        // Set default options
        $default_options = array(
            'layout_type' => 'grid',
            'columns_count' => 3,
            'image_spacing' => 15,
            'image_size' => 'proje-medium',
            'border_radius' => 8,
            'hover_effect' => 'scale',
            'tablet_columns' => 2,
            'mobile_columns' => 1,
            'enable_analytics' => true,
            'enable_social_sharing' => true,
            'enable_video_support' => true,
            'enable_advanced_search' => true,
            'enable_performance_optimization' => true,
            'lazy_loading' => true,
            'progressive_loading' => true,
            'webp_support' => true,
            'enable_import_export' => true
        );
        
        if (!get_option('project_gallery_options')) {
            add_option('project_gallery_options', $default_options);
        }
        
        // Set database version
        add_option('project_gallery_db_version', PROJECT_GALLERY_DB_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Schedule analytics cleanup
        if (!wp_next_scheduled('project_gallery_cleanup_analytics')) {
            wp_schedule_event(time(), 'weekly', 'project_gallery_cleanup_analytics');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate_plugin() {
        // Clear scheduled events
        wp_clear_scheduled_hook('project_gallery_cleanup_analytics');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall_plugin() {
        global $wpdb;
        
        // Remove all plugin options
        delete_option('project_gallery_options');
        delete_option('project_gallery_db_version');
        
        // Remove analytics data
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}project_gallery_analytics");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}project_gallery_search_index");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}project_gallery_search_log");
        
        // Remove all project posts and metadata
        $projects = get_posts(array(
            'post_type' => 'proje',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($projects as $project) {
            wp_delete_post($project->ID, true);
        }
        
        // Remove taxonomy terms
        $terms = get_terms(array(
            'taxonomy' => 'proje-kategori',
            'hide_empty' => false
        ));
        
        foreach ($terms as $term) {
            wp_delete_term($term->term_id, 'proje-kategori');
        }
        
        // Remove cached data
        wp_cache_flush();
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_updates() {
        $current_version = get_option('project_gallery_db_version');
        
        if ($current_version !== PROJECT_GALLERY_DB_VERSION) {
            $this->upgrade_plugin($current_version);
            update_option('project_gallery_db_version', PROJECT_GALLERY_DB_VERSION);
        }
    }
    
    /**
     * Upgrade plugin
     */
    private function upgrade_plugin($from_version) {
        // Update database schema if needed
        $this->analytics->create_analytics_table();
        
        // Add new options with defaults
        $options = get_option('project_gallery_options', array());
        $new_options = array(
            'enable_analytics' => true,
            'enable_social_sharing' => true,
            'enable_video_support' => true,
            'enable_advanced_search' => true,
            'enable_performance_optimization' => true,
            'lazy_loading' => true,
            'progressive_loading' => true,
            'webp_support' => true,
            'enable_import_export' => true
        );
        
        foreach ($new_options as $key => $value) {
            if (!isset($options[$key])) {
                $options[$key] = $value;
            }
        }
        
        update_option('project_gallery_options', $options);
        
        // Clear cache
        $this->performance->clear_all_cache();
    }
    
    /**
     * Get plugin instance for external access
     */
    public function get_analytics() {
        return $this->analytics;
    }
    
    public function get_import_export() {
        return $this->import_export;
    }
    
    public function get_video() {
        return $this->video;
    }
    
    public function get_performance() {
        return $this->performance;
    }
    
    public function get_social() {
        return $this->social;
    }
    
    public function get_search() {
        return $this->search;
    }
}

// Plugin'i başlat
$project_gallery_instance = new ProjectGallery();

/**
 * Plugin aktivasyonu
 */
register_activation_hook(__FILE__, 'project_gallery_activation');
function project_gallery_activation() {
    global $project_gallery_instance;
    $project_gallery_instance->activate_plugin();
}

/**
 * Plugin deaktivasyonu
 */
register_deactivation_hook(__FILE__, 'project_gallery_deactivation');
function project_gallery_deactivation() {
    global $project_gallery_instance;
    $project_gallery_instance->deactivate_plugin();
}