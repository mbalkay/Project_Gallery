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
            <p>
                <input type="button" id="project-gallery-button" class="button" value="Galeri Resimleri Seç" />
                <input type="hidden" id="project-gallery-images" name="project_gallery_images" value="<?php echo esc_attr($gallery_images); ?>" />
            </p>
            <div id="project-gallery-preview">
                <?php if ($gallery_images): ?>
                    <?php $image_ids = explode(',', $gallery_images); ?>
                    <?php foreach ($image_ids as $image_id): ?>
                        <?php if ($image_id): ?>
                            <div class="gallery-image-preview">
                                <?php echo wp_get_attachment_image($image_id, 'thumbnail'); ?>
                                <button type="button" class="remove-gallery-image" data-id="<?php echo $image_id; ?>">×</button>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var frame;
            
            $('#project-gallery-button').on('click', function(e) {
                e.preventDefault();
                
                if (frame) {
                    frame.open();
                    return;
                }
                
                frame = wp.media({
                    title: 'Galeri Resimleri Seç',
                    button: {
                        text: 'Resimleri Seç'
                    },
                    multiple: true
                });
                
                frame.on('select', function() {
                    var selection = frame.state().get('selection');
                    var image_ids = [];
                    var preview_html = '';
                    
                    selection.map(function(attachment) {
                        attachment = attachment.toJSON();
                        image_ids.push(attachment.id);
                        preview_html += '<div class="gallery-image-preview">';
                        preview_html += '<img src="' + attachment.sizes.thumbnail.url + '" />';
                        preview_html += '<button type="button" class="remove-gallery-image" data-id="' + attachment.id + '">×</button>';
                        preview_html += '</div>';
                    });
                    
                    $('#project-gallery-images').val(image_ids.join(','));
                    $('#project-gallery-preview').html(preview_html);
                });
                
                frame.open();
            });
            
            $(document).on('click', '.remove-gallery-image', function() {
                var imageId = $(this).data('id');
                var currentIds = $('#project-gallery-images').val().split(',');
                var newIds = currentIds.filter(function(id) {
                    return id != imageId;
                });
                
                $('#project-gallery-images').val(newIds.join(','));
                $(this).parent().remove();
            });
        });
        </script>
        
        <style>
        #project-gallery-preview {
            margin-top: 10px;
        }
        .gallery-image-preview {
            display: inline-block;
            position: relative;
            margin: 5px;
        }
        .gallery-image-preview img {
            max-width: 100px;
            height: auto;
        }
        .remove-gallery-image {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3232;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            cursor: pointer;
            font-size: 12px;
            line-height: 1;
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
                $image_url = wp_get_attachment_image_url($image_id, 'proje-large');
                $image_thumb = wp_get_attachment_image_url($image_id, 'proje-medium');
                $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                
                if ($image_url) {
                    $images[] = array(
                        'full' => $image_url,
                        'thumb' => $image_thumb,
                        'alt' => $image_alt
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