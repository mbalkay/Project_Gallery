<?php
/**
 * Project Gallery Multi-language Support
 * WPML ve Polylang entegrasyonu ile çoklu dil desteği
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProjectGalleryMultiLang {
    
    private $current_lang;
    private $default_lang;
    private $supported_langs;
    private $plugin_type;
    
    public function __construct() {
        $this->detect_plugin();
        $this->init_hooks();
    }
    
    /**
     * Çoklu dil plugin'ini tespit et (WPML veya Polylang)
     */
    private function detect_plugin() {
        if (defined('ICL_LANGUAGE_CODE')) {
            // WPML aktif
            $this->plugin_type = 'wpml';
            $this->current_lang = ICL_LANGUAGE_CODE;
            $this->default_lang = wpml_get_default_language();
            $this->supported_langs = apply_filters('wpml_active_languages', NULL, 'orderby=id&order=desc');
        } elseif (function_exists('pll_current_language')) {
            // Polylang aktif
            $this->plugin_type = 'polylang';
            $this->current_lang = pll_current_language();
            $this->default_lang = pll_default_language();
            $this->supported_langs = pll_the_languages(array('raw' => 1));
        } else {
            // Hiçbiri aktif değil
            $this->plugin_type = 'none';
            $this->current_lang = get_locale();
            $this->default_lang = get_locale();
            $this->supported_langs = array();
        }
    }
    
    /**
     * Hook'ları başlat
     */
    private function init_hooks() {
        // Admin panel hooks
        add_action('admin_menu', array($this, 'add_translation_menu'), 20);
        add_action('add_meta_boxes', array($this, 'add_translation_metabox'));
        add_action('save_post', array($this, 'save_translation_data'));
        
        // Frontend hooks
        add_filter('project_gallery_shortcode_atts', array($this, 'filter_shortcode_by_language'), 10, 2);
        add_filter('pre_get_posts', array($this, 'filter_projects_by_language'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_multilang_scripts'));
        
        // AJAX hooks
        add_action('wp_ajax_get_project_translations', array($this, 'ajax_get_translations'));
        add_action('wp_ajax_create_project_translation', array($this, 'ajax_create_translation'));
        add_action('wp_ajax_sync_project_gallery', array($this, 'ajax_sync_gallery'));
        
        // Database hooks
        add_action('init', array($this, 'create_translation_tables'));
        add_action('wp_loaded', array($this, 'migrate_existing_projects'));
    }
    
    /**
     * Çeviri tablosu oluştur
     */
    public function create_translation_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'project_gallery_translations';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            project_id bigint(20) NOT NULL,
            language_code varchar(10) NOT NULL,
            translated_title text,
            translated_content longtext,
            translated_excerpt text,
            translated_meta text,
            translation_group varchar(32),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY language_code (language_code),
            KEY translation_group (translation_group),
            UNIQUE KEY unique_project_lang (project_id, language_code)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Admin menüsüne çeviri yönetimi ekle
     */
    public function add_translation_menu() {
        add_submenu_page(
            'edit.php?post_type=proje',
            'Çeviri Yönetimi',
            'Çeviriler',
            'manage_options',
            'project-translations',
            array($this, 'translation_management_page')
        );
    }
    
    /**
     * Çeviri yönetim sayfası
     */
    public function translation_management_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        ?>
        <div class="wrap">
            <h1>🌍 Proje Çeviri Yönetimi</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?post_type=proje&page=project-translations&tab=overview" 
                   class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    📊 Genel Bakış
                </a>
                <a href="?post_type=proje&page=project-translations&tab=languages" 
                   class="nav-tab <?php echo $current_tab === 'languages' ? 'nav-tab-active' : ''; ?>">
                    🗣️ Dil Ayarları
                </a>
                <a href="?post_type=proje&page=project-translations&tab=sync" 
                   class="nav-tab <?php echo $current_tab === 'sync' ? 'nav-tab-active' : ''; ?>">
                    🔄 Senkronizasyon
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'overview':
                        $this->render_overview_tab();
                        break;
                    case 'languages':
                        $this->render_languages_tab();
                        break;
                    case 'sync':
                        $this->render_sync_tab();
                        break;
                }
                ?>
            </div>
        </div>
        
        <style>
        .translation-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #007cba;
            display: block;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .language-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .lang-flag {
            width: 24px;
            height: 16px;
            display: inline-block;
            margin-right: 10px;
            border-radius: 2px;
        }
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #f0f0f0;
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007cba, #00a0d2);
            transition: width 0.3s ease;
        }
        </style>
        <?php
    }
    
    /**
     * Genel bakış sekmesi
     */
    private function render_overview_tab() {
        global $wpdb;
        
        $total_projects = wp_count_posts('proje')->publish;
        $total_translations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}project_gallery_translations");
        $languages_with_content = $wpdb->get_var("SELECT COUNT(DISTINCT language_code) FROM {$wpdb->prefix}project_gallery_translations");
        
        ?>
        <div class="translation-stats">
            <div class="stat-card">
                <span class="stat-number"><?php echo $total_projects; ?></span>
                <div class="stat-label">Toplam Proje</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $total_translations; ?></span>
                <div class="stat-label">Çeviri Kaydı</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $languages_with_content; ?></span>
                <div class="stat-label">Aktif Dil</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $this->plugin_type; ?></span>
                <div class="stat-label">Çeviri Plugin'i</div>
            </div>
        </div>
        
        <h3>📋 Çeviri Durumu</h3>
        <?php
        $projects = get_posts(array(
            'post_type' => 'proje',
            'posts_per_page' => 10,
            'post_status' => 'publish'
        ));
        
        if (!empty($projects)) {
            echo '<div class="wp-list-table widefat">';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>Proje</th>';
            foreach ($this->supported_langs as $lang_code => $lang_info) {
                $flag = $this->get_language_flag($lang_code);
                echo '<th>' . $flag . ' ' . $lang_info['native_name'] . '</th>';
            }
            echo '</tr></thead><tbody>';
            
            foreach ($projects as $project) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($project->post_title) . '</strong></td>';
                
                foreach ($this->supported_langs as $lang_code => $lang_info) {
                    $translation = $this->get_project_translation($project->ID, $lang_code);
                    $status = $translation ? '✅' : '❌';
                    $status_text = $translation ? 'Çevrildi' : 'Çevrilmedi';
                    echo '<td title="' . $status_text . '">' . $status . '</td>';
                }
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
        }
    }
    
    /**
     * Dil ayarları sekmesi
     */
    private function render_languages_tab() {
        ?>
        <h3>🗣️ Desteklenen Diller</h3>
        <p>Plugin türü: <strong><?php echo ucfirst($this->plugin_type); ?></strong></p>
        
        <?php if ($this->plugin_type === 'none'): ?>
            <div class="notice notice-warning">
                <p><strong>Uyarı:</strong> WPML veya Polylang plugin'i tespit edilmedi. Çoklu dil desteği için bu plugin'lerden birini yükleyin.</p>
            </div>
        <?php else: ?>
            <div class="language-list">
                <?php foreach ($this->supported_langs as $lang_code => $lang_info): ?>
                    <div class="language-card">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <?php echo $this->get_language_flag($lang_code); ?>
                                <strong><?php echo $lang_info['native_name']; ?></strong>
                                <span style="color: #666;">（<?php echo $lang_code; ?>）</span>
                            </div>
                            <div>
                                <?php if ($lang_code === $this->default_lang): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <small>Varsayılan</small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php
                        $completion = $this->get_language_completion($lang_code);
                        ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $completion; ?>%"></div>
                        </div>
                        <small><?php echo $completion; ?>% tamamlandı</small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <h3>⚙️ Çeviri Ayarları</h3>
        <form method="post" action="options.php">
            <?php settings_fields('project_gallery_multilang'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Otomatik Çeviri</th>
                    <td>
                        <label>
                            <input type="checkbox" name="multilang_auto_translate" value="1" 
                                   <?php checked(get_option('multilang_auto_translate', 0)); ?>>
                            Yeni projeler için otomatik çeviri önerileri göster
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Galeri Senkronizasyonu</th>
                    <td>
                        <label>
                            <input type="checkbox" name="multilang_sync_gallery" value="1" 
                                   <?php checked(get_option('multilang_sync_gallery', 1)); ?>>
                            Galeri resimlerini tüm dillerde senkronize et
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Dil Seçici</th>
                    <td>
                        <label>
                            <input type="checkbox" name="multilang_show_selector" value="1" 
                                   <?php checked(get_option('multilang_show_selector', 1)); ?>>
                            Frontend'de dil seçici göster
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button('Ayarları Kaydet'); ?>
        </form>
        <?php
    }
    
    /**
     * Senkronizasyon sekmesi
     */
    private function render_sync_tab() {
        ?>
        <h3>🔄 Çeviri Senkronizasyonu</h3>
        <p>Mevcut projeleri çoklu dil sistemine dahil edin ve çeviriler arasında veri senkronizasyonu yapın.</p>
        
        <div class="sync-actions">
            <button type="button" id="sync-existing-projects" class="button button-primary">
                📥 Mevcut Projeleri İçe Aktar
            </button>
            <button type="button" id="sync-galleries" class="button">
                🖼️ Galerileri Senkronize Et
            </button>
            <button type="button" id="sync-categories" class="button">
                🏷️ Kategorileri Senkronize Et
            </button>
        </div>
        
        <div id="sync-progress" style="display: none;">
            <h4>⏳ İşlem Devam Ediyor...</h4>
            <div class="progress-bar">
                <div class="progress-fill" id="sync-progress-bar" style="width: 0%"></div>
            </div>
            <div id="sync-status">Hazırlanıyor...</div>
        </div>
        
        <div id="sync-results" style="display: none;">
            <h4>✅ İşlem Tamamlandı</h4>
            <div id="sync-details"></div>
        </div>
        <?php
    }
    
    /**
     * Çeviri meta box'ı ekle
     */
    public function add_translation_metabox() {
        if ($this->plugin_type !== 'none') {
            add_meta_box(
                'project_translations',
                '🌍 Proje Çevirileri',
                array($this, 'translation_metabox_content'),
                'proje',
                'side',
                'high'
            );
        }
    }
    
    /**
     * Çeviri meta box içeriği
     */
    public function translation_metabox_content($post) {
        wp_nonce_field('project_translation_nonce', 'project_translation_nonce');
        
        ?>
        <div id="translation-manager">
            <p><strong>Mevcut Dil:</strong> <?php echo $this->get_language_flag($this->current_lang) . ' ' . $this->current_lang; ?></p>
            
            <?php if (!empty($this->supported_langs)): ?>
                <h4>📝 Diğer Dillerdeki Çeviriler:</h4>
                <div class="translation-links">
                    <?php foreach ($this->supported_langs as $lang_code => $lang_info): ?>
                        <?php if ($lang_code !== $this->current_lang): ?>
                            <div class="translation-item">
                                <?php echo $this->get_language_flag($lang_code); ?>
                                <strong><?php echo $lang_info['native_name']; ?></strong>
                                
                                <?php
                                $translation = $this->get_project_translation($post->ID, $lang_code);
                                if ($translation) {
                                    echo '<span style="color: #46b450;">✅ Çevrildi</span>';
                                    echo '<br><button type="button" class="button-link edit-translation" data-lang="' . $lang_code . '" data-post="' . $post->ID . '">Düzenle</button>';
                                } else {
                                    echo '<span style="color: #dc3232;">❌ Çevrilmedi</span>';
                                    echo '<br><button type="button" class="button-link create-translation" data-lang="' . $lang_code . '" data-post="' . $post->ID . '">Çeviri Oluştur</button>';
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div id="translation-form" style="display: none;">
                <h4>Çeviri Düzenle</h4>
                <form id="translation-edit-form">
                    <p>
                        <label><strong>Başlık:</strong></label>
                        <input type="text" id="translation-title" style="width: 100%;">
                    </p>
                    <p>
                        <label><strong>Açıklama:</strong></label>
                        <textarea id="translation-excerpt" rows="3" style="width: 100%;"></textarea>
                    </p>
                    <p>
                        <label><strong>İçerik:</strong></label>
                        <textarea id="translation-content" rows="5" style="width: 100%;"></textarea>
                    </p>
                    <p>
                        <button type="button" id="save-translation" class="button button-primary">Kaydet</button>
                        <button type="button" id="cancel-translation" class="button">İptal</button>
                    </p>
                </form>
            </div>
        </div>
        
        <style>
        .translation-item {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            background: #f9f9f9;
        }
        .translation-links {
            max-height: 300px;
            overflow-y: auto;
        }
        .lang-flag {
            width: 20px;
            height: 14px;
            display: inline-block;
            margin-right: 8px;
            border-radius: 2px;
            background: #ddd;
        }
        #translation-form {
            border: 1px solid #007cba;
            padding: 15px;
            border-radius: 4px;
            background: #f0f8ff;
            margin-top: 15px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var currentLang = '';
            var currentPost = <?php echo $post->ID; ?>;
            
            $('.create-translation, .edit-translation').on('click', function() {
                currentLang = $(this).data('lang');
                var isEdit = $(this).hasClass('edit-translation');
                
                $('#translation-form h4').text(isEdit ? 'Çeviri Düzenle' : 'Yeni Çeviri Oluştur');
                
                if (isEdit) {
                    // Mevcut çeviriyi yükle
                    loadTranslation(currentPost, currentLang);
                } else {
                    // Boş form göster
                    $('#translation-title').val('');
                    $('#translation-excerpt').val('');
                    $('#translation-content').val('');
                }
                
                $('#translation-form').show();
            });
            
            $('#cancel-translation').on('click', function() {
                $('#translation-form').hide();
            });
            
            $('#save-translation').on('click', function() {
                saveTranslation(currentPost, currentLang);
            });
            
            function loadTranslation(postId, langCode) {
                $.post(ajaxurl, {
                    action: 'get_project_translations',
                    post_id: postId,
                    lang_code: langCode,
                    nonce: '<?php echo wp_create_nonce('project_translation_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#translation-title').val(response.data.title || '');
                        $('#translation-excerpt').val(response.data.excerpt || '');
                        $('#translation-content').val(response.data.content || '');
                    }
                });
            }
            
            function saveTranslation(postId, langCode) {
                var data = {
                    action: 'create_project_translation',
                    post_id: postId,
                    lang_code: langCode,
                    title: $('#translation-title').val(),
                    excerpt: $('#translation-excerpt').val(),
                    content: $('#translation-content').val(),
                    nonce: '<?php echo wp_create_nonce('project_translation_nonce'); ?>'
                };
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert('Çeviri başarıyla kaydedildi!');
                        location.reload();
                    } else {
                        alert('Hata: ' + response.data);
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Dil completion oranını hesapla
     */
    private function get_language_completion($lang_code) {
        global $wpdb;
        
        $total_projects = wp_count_posts('proje')->publish;
        if ($total_projects == 0) return 100;
        
        $translated_projects = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT project_id) FROM {$wpdb->prefix}project_gallery_translations WHERE language_code = %s",
            $lang_code
        ));
        
        return round(($translated_projects / $total_projects) * 100);
    }
    
    /**
     * Dil flag'i getir (basit implementasyon)
     */
    private function get_language_flag($lang_code) {
        $flags = array(
            'tr' => '🇹🇷',
            'en' => '🇺🇸',
            'de' => '🇩🇪',
            'fr' => '🇫🇷',
            'es' => '🇪🇸',
            'it' => '🇮🇹',
            'ru' => '🇷🇺',
            'ar' => '🇸🇦',
            'zh' => '🇨🇳',
            'ja' => '🇯🇵'
        );
        
        return isset($flags[$lang_code]) ? $flags[$lang_code] : '🌐';
    }
    
    /**
     * Proje çevirisini getir
     */
    public function get_project_translation($project_id, $lang_code) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}project_gallery_translations WHERE project_id = %d AND language_code = %s",
            $project_id,
            $lang_code
        ));
    }
    
    /**
     * Shortcode'u dile göre filtrele
     */
    public function filter_shortcode_by_language($atts, $shortcode) {
        if (isset($atts['lang'])) {
            $this->current_lang = sanitize_text_field($atts['lang']);
        }
        
        return $atts;
    }
    
    /**
     * Projeleri dile göre filtrele
     */
    public function filter_projects_by_language($query) {
        if (!is_admin() && $query->is_main_query() && 
            ($query->is_post_type_archive('proje') || $query->is_tax('proje_kategorisi'))) {
            
            if ($this->plugin_type !== 'none') {
                // Dil filtresini uygula
                $meta_query = $query->get('meta_query') ?: array();
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_project_language',
                        'value' => $this->current_lang,
                        'compare' => '='
                    ),
                    array(
                        'key' => '_project_language',
                        'compare' => 'NOT EXISTS'
                    )
                );
                $query->set('meta_query', $meta_query);
            }
        }
    }
    
    /**
     * Çoklu dil script'lerini yükle
     */
    public function enqueue_multilang_scripts() {
        if ($this->plugin_type !== 'none' && get_option('multilang_show_selector', 1)) {
            wp_enqueue_script(
                'project-gallery-multilang',
                PROJECT_GALLERY_PLUGIN_URL . 'assets/js/multilang.js',
                array('jquery'),
                PROJECT_GALLERY_VERSION,
                true
            );
            
            wp_localize_script('project-gallery-multilang', 'projectGalleryMultiLang', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('project_multilang_nonce'),
                'current_lang' => $this->current_lang,
                'languages' => $this->supported_langs
            ));
        }
    }
    
    /**
     * AJAX: Çeviri getir
     */
    public function ajax_get_translations() {
        check_ajax_referer('project_translation_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $lang_code = sanitize_text_field($_POST['lang_code']);
        
        $translation = $this->get_project_translation($post_id, $lang_code);
        
        if ($translation) {
            wp_send_json_success(array(
                'title' => $translation->translated_title,
                'excerpt' => $translation->translated_excerpt,
                'content' => $translation->translated_content
            ));
        } else {
            wp_send_json_error('Çeviri bulunamadı');
        }
    }
    
    /**
     * AJAX: Çeviri oluştur/güncelle
     */
    public function ajax_create_translation() {
        check_ajax_referer('project_translation_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Yetki yok');
        }
        
        global $wpdb;
        
        $post_id = intval($_POST['post_id']);
        $lang_code = sanitize_text_field($_POST['lang_code']);
        $title = sanitize_text_field($_POST['title']);
        $excerpt = sanitize_textarea_field($_POST['excerpt']);
        $content = wp_kses_post($_POST['content']);
        
        $result = $wpdb->replace(
            $wpdb->prefix . 'project_gallery_translations',
            array(
                'project_id' => $post_id,
                'language_code' => $lang_code,
                'translated_title' => $title,
                'translated_excerpt' => $excerpt,
                'translated_content' => $content,
                'translation_group' => wp_generate_uuid4()
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result !== false) {
            wp_send_json_success('Çeviri kaydedildi');
        } else {
            wp_send_json_error('Veritabanı hatası');
        }
    }
    
    /**
     * AJAX: Galeri senkronize et
     */
    public function ajax_sync_gallery() {
        check_ajax_referer('project_multilang_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Yetki yok');
        }
        
        $synced = 0;
        $projects = get_posts(array(
            'post_type' => 'proje',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        foreach ($projects as $project) {
            $gallery_images = get_post_meta($project->ID, '_project_gallery_images', true);
            if ($gallery_images) {
                // Tüm çevirilerde aynı galeriyi kullan
                foreach ($this->supported_langs as $lang_code => $lang_info) {
                    if ($lang_code !== $this->current_lang) {
                        $translation = $this->get_project_translation($project->ID, $lang_code);
                        if ($translation) {
                            update_post_meta($project->ID, '_project_gallery_images_' . $lang_code, $gallery_images);
                            $synced++;
                        }
                    }
                }
            }
        }
        
        wp_send_json_success("$synced galeri senkronize edildi");
    }
    
    /**
     * Mevcut projeleri çoklu dil sistemine dahil et
     */
    public function migrate_existing_projects() {
        if (get_option('project_gallery_multilang_migrated')) {
            return;
        }
        
        if ($this->plugin_type === 'none') {
            return;
        }
        
        $projects = get_posts(array(
            'post_type' => 'proje',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        foreach ($projects as $project) {
            // Varsayılan dil olarak işaretle
            update_post_meta($project->ID, '_project_language', $this->default_lang);
        }
        
        update_option('project_gallery_multilang_migrated', true);
    }
    
    /**
     * Dil seçici widget'ı render et
     */
    public function render_language_selector() {
        if ($this->plugin_type === 'none' || !get_option('multilang_show_selector', 1)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="project-gallery-lang-selector">
            <select id="project-gallery-language" onchange="projectGalleryChangeLang(this.value)">
                <?php foreach ($this->supported_langs as $lang_code => $lang_info): ?>
                    <option value="<?php echo esc_attr($lang_code); ?>" 
                            <?php selected($lang_code, $this->current_lang); ?>>
                        <?php echo $this->get_language_flag($lang_code) . ' ' . esc_html($lang_info['native_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <style>
        .project-gallery-lang-selector {
            margin-bottom: 20px;
            text-align: right;
        }
        #project-gallery-language {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            font-size: 14px;
        }
        </style>
        
        <script>
        function projectGalleryChangeLang(lang) {
            if (typeof projectGalleryMultiLang !== 'undefined') {
                projectGalleryMultiLang.switchLanguage(lang);
            }
        }
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode için dil seçici ekle
     */
    public function add_language_selector_to_shortcode($content, $atts) {
        if (isset($atts['show_lang_selector']) && $atts['show_lang_selector'] === 'true') {
            $selector = $this->render_language_selector();
            return $selector . $content;
        }
        
        return $content;
    }
    
    /**
     * Çeviri verilerini kaydet (WordPress save_post hook'u için)
     */
    public function save_translation_data($post_id) {
        // Otomatik kaydetme işlemlerini kontrol et
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Post tipini kontrol et
        if (get_post_type($post_id) !== 'proje') {
            return;
        }
        
        // Yetki kontrolü
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Nonce kontrolü (metabox'tan geliyorsa)
        if (isset($_POST['project_translation_nonce']) && 
            !wp_verify_nonce($_POST['project_translation_nonce'], 'project_translation_nonce')) {
            return;
        }
        
        // Çoklu dil sistemi aktif değilse çık
        if ($this->plugin_type === 'none') {
            return;
        }
        
        // Projenin dil bilgisini kaydet
        if (!get_post_meta($post_id, '_project_language', true)) {
            update_post_meta($post_id, '_project_language', $this->current_lang);
        }
        
        // Translation group oluştur (eğer yoksa)
        $translation_group = get_post_meta($post_id, '_project_translation_group', true);
        if (!$translation_group) {
            $translation_group = wp_generate_uuid4();
            update_post_meta($post_id, '_project_translation_group', $translation_group);
        }
        
        // Bu fonksiyon çoklu dil plugin'leri için gerekli meta verileri yönetir
        // Gerçek çeviri işlemi AJAX ile yapılacak
    }
    
    /**
     * Plugin bilgilerini getir
     */
    public function get_plugin_info() {
        return array(
            'plugin_type' => $this->plugin_type,
            'current_lang' => $this->current_lang,
            'default_lang' => $this->default_lang,
            'supported_langs' => $this->supported_langs
        );
    }
}