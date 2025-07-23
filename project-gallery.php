<?php
/**
 * Plugin Name: Project Gallery
 * Plugin URI: https://github.com/mbalkay/Project_Gallery
 * Description: WordPress proje galerisi eklentisi - projelerinizi kategorilere ayırarak görsel galeri olarak sunmanızı sağlar.
 * Version: 1.0.0
 * Author: mbalkay
 * License: GPL v2 or later
 * Text Domain: project-gallery
 * Domain Path: /languages
 */

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

// Plugin sabitleri
define('PROJECT_GALLERY_VERSION', '1.0.0');
define('PROJECT_GALLERY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PROJECT_GALLERY_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Ana Plugin Sınıfı
 */
class ProjectGallery {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_project_gallery'));
        add_shortcode('proje_galerisi', array($this, 'project_gallery_shortcode'));
        add_filter('single_template', array($this, 'single_project_template'));
        add_filter('archive_template', array($this, 'archive_project_template'));
        add_action('wp_ajax_get_project_images', array($this, 'ajax_get_project_images'));
        add_action('wp_ajax_nopriv_get_project_images', array($this, 'ajax_get_project_images'));
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
            
            // Bulk add photos
            function openMediaFrame(multiple = true) {
                if (frame) {
                    frame.open();
                    return;
                }
                
                frame = wp.media({
                    title: multiple ? 'Toplu Fotoğraf Seç' : 'Fotoğraf Seç',
                    button: {
                        text: multiple ? 'Fotoğrafları Ekle' : 'Fotoğraf Ekle'
                    },
                    multiple: multiple
                });
                
                frame.on('select', function() {
                    var selection = frame.state().get('selection');
                    var newIds = [];
                    var previewHtml = '';
                    
                    selection.map(function(attachment) {
                        attachment = attachment.toJSON();
                        if (currentImages.indexOf(attachment.id.toString()) === -1) {
                            newIds.push(attachment.id);
                            currentImages.push(attachment.id.toString());
                        }
                    });
                    
                    // Regenerate all preview HTML
                    currentImages.forEach(function(imageId) {
                        if (imageId) {
                            var attachment = selection.findWhere({id: parseInt(imageId)});
                            if (attachment) {
                                attachment = attachment.toJSON();
                            } else {
                                // For existing images, we need to make an AJAX call or use a simpler approach
                                // For now, let's just create a placeholder structure
                                attachment = {
                                    id: imageId,
                                    sizes: {
                                        thumbnail: {
                                            url: '' // Will be populated by existing images
                                        }
                                    },
                                    filename: 'image-' + imageId + '.jpg'
                                };
                            }
                            
                            previewHtml += '<div class="gallery-image-preview" data-id="' + imageId + '">';
                            previewHtml += '<div class="image-container">';
                            if (attachment.sizes && attachment.sizes.thumbnail) {
                                previewHtml += '<img src="' + attachment.sizes.thumbnail.url + '" />';
                            }
                            previewHtml += '<div class="image-overlay">';
                            previewHtml += '<button type="button" class="remove-gallery-image" data-id="' + imageId + '" title="Kaldır">×</button>';
                            previewHtml += '<div class="image-info">';
                            previewHtml += '<span class="image-name">' + (attachment.filename || 'image.jpg') + '</span>';
                            previewHtml += '</div></div></div></div>';
                        }
                    });
                    
                    $('#project-gallery-images').val(currentImages.join(','));
                    
                    // If we have new images, rebuild the entire preview
                    if (newIds.length > 0) {
                        // Simpler approach: reload the page or trigger a refresh
                        // For now, just append new images
                        newIds.forEach(function(imageId) {
                            var attachment = selection.findWhere({id: parseInt(imageId)}).toJSON();
                            var newImageHtml = '<div class="gallery-image-preview" data-id="' + imageId + '">';
                            newImageHtml += '<div class="image-container">';
                            newImageHtml += '<img src="' + attachment.sizes.thumbnail.url + '" />';
                            newImageHtml += '<div class="image-overlay">';
                            newImageHtml += '<button type="button" class="remove-gallery-image" data-id="' + imageId + '" title="Kaldır">×</button>';
                            newImageHtml += '<div class="image-info">';
                            newImageHtml += '<span class="image-name">' + attachment.filename + '</span>';
                            newImageHtml += '</div></div></div></div>';
                            $('#project-gallery-preview').append(newImageHtml);
                        });
                    }
                    
                    updateUI();
                    initSortable();
                });
                
                frame.open();
            }
            
            // Initialize sortable functionality
            function initSortable() {
                $('#project-gallery-preview').sortable({
                    items: '.gallery-image-preview',
                    placeholder: 'gallery-sortable-placeholder',
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
            
            // Event handlers
            $('#project-gallery-button, #project-gallery-button-empty').on('click', function(e) {
                e.preventDefault();
                updateCurrentImages();
                openMediaFrame(true);
            });
            
            $('#project-gallery-add-single').on('click', function(e) {
                e.preventDefault();
                updateCurrentImages();
                openMediaFrame(false);
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
                $(this).closest('.gallery-image-preview').remove();
                updateUI();
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
}

// Plugin'i başlat
new ProjectGallery();

/**
 * Plugin aktivasyonu
 */
register_activation_hook(__FILE__, 'project_gallery_activation');
function project_gallery_activation() {
    // Rewrite rules'ları yenile
    flush_rewrite_rules();
}

/**
 * Plugin deaktivasyonu
 */
register_deactivation_hook(__FILE__, 'project_gallery_deactivation');
function project_gallery_deactivation() {
    // Rewrite rules'ları yenile
    flush_rewrite_rules();
}