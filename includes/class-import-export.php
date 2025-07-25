<?php
/**
 * Project Gallery Import/Export System
 * Advanced data management for project galleries
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProjectGalleryImportExport {
    
    private $upload_dir;
    private $allowed_extensions = array('csv', 'xlsx', 'json');
    private $max_file_size = 50 * 1024 * 1024; // 50MB
    
    public function __construct() {
        // Ana AJAX handler'ları
        add_action('wp_ajax_export_projects', array($this, 'export_projects'));
        add_action('wp_ajax_import_projects', array($this, 'import_projects'));
        add_action('wp_ajax_backup_gallery_data', array($this, 'backup_gallery_data'));
        add_action('wp_ajax_restore_gallery_data', array($this, 'restore_gallery_data'));
        
        // Gelişmiş özellikler için yeni handler'lar
        add_action('wp_ajax_export_projects_csv', array($this, 'export_projects_csv'));
        add_action('wp_ajax_export_projects_xlsx', array($this, 'export_projects_xlsx'));
        add_action('wp_ajax_import_projects_progress', array($this, 'import_projects_with_progress'));
        add_action('wp_ajax_validate_import_file', array($this, 'validate_import_file'));
        add_action('wp_ajax_get_import_preview', array($this, 'get_import_preview'));
        add_action('wp_ajax_download_import_template', array($this, 'download_import_template'));
        
        // Upload dizinini ayarla
        $upload_info = wp_upload_dir();
        $this->upload_dir = $upload_info['basedir'] . '/project-gallery-imports/';
        
        // Upload dizinini oluştur
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
            
            // Güvenlik için .htaccess dosyası ekle
            $htaccess_content = "# Project Gallery Import Directory\n";
            $htaccess_content .= "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";
            file_put_contents($this->upload_dir . '.htaccess', $htaccess_content);
        }
        
        // Admin menü sayfası ekle
        add_action('admin_menu', array($this, 'add_import_export_menu'), 25);
    }
    
    /**
     * İçe/Dışa Aktarma admin menüsü ekle
     */
    public function add_import_export_menu() {
        add_submenu_page(
            'edit.php?post_type=proje',
            'İçe/Dışa Aktarma',
            'İçe/Dışa Aktarma',
            'manage_options',
            'project-import-export',
            array($this, 'import_export_page')
        );
    }
    
    /**
     * İçe/Dışa Aktarma sayfası
     */
    public function import_export_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'export';
        ?>
        <div class="wrap">
            <h1>📦 Proje İçe/Dışa Aktarma</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?post_type=proje&page=project-import-export&tab=export" 
                   class="nav-tab <?php echo $current_tab === 'export' ? 'nav-tab-active' : ''; ?>">
                    📤 Dışa Aktarma
                </a>
                <a href="?post_type=proje&page=project-import-export&tab=import" 
                   class="nav-tab <?php echo $current_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    📥 İçe Aktarma
                </a>
                <a href="?post_type=proje&page=project-import-export&tab=backup" 
                   class="nav-tab <?php echo $current_tab === 'backup' ? 'nav-tab-active' : ''; ?>">
                    💾 Yedekleme
                </a>
                <a href="?post_type=proje&page=project-import-export&tab=templates" 
                   class="nav-tab <?php echo $current_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
                    📋 Şablonlar
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'export':
                        $this->render_export_tab();
                        break;
                    case 'import':
                        $this->render_import_tab();
                        break;
                    case 'backup':
                        $this->render_backup_tab();
                        break;
                    case 'templates':
                        $this->render_templates_tab();
                        break;
                }
                ?>
            </div>
        </div>
        
        <style>
        .import-export-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .export-options, .import-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .option-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .option-card:hover {
            border-color: #007cba;
            box-shadow: 0 4px 12px rgba(0,123,186,0.2);
        }
        .option-card.selected {
            border-color: #007cba;
            background: #f0f8ff;
        }
        .option-icon {
            font-size: 3em;
            margin-bottom: 15px;
            display: block;
        }
        .progress-container {
            display: none;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin: 20px 0;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007cba, #00a0d2);
            width: 0%;
            transition: width 0.3s ease;
            position: relative;
        }
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
            font-size: 12px;
        }
        .file-preview {
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
            max-height: 300px;
            overflow-y: auto;
        }
        .validation-results {
            margin: 15px 0;
        }
        .validation-success {
            color: #46b450;
            background: #f0fff0;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #46b450;
        }
        .validation-error {
            color: #dc3232;
            background: #fff0f0;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #dc3232;
        }
        .template-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .template-info h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .template-description {
            color: #666;
            font-size: 14px;
        }
        .drag-drop-area {
            border: 2px dashed #007cba;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #f8fcff;
            margin: 20px 0;
            transition: all 0.3s ease;
        }
        .drag-drop-area.dragover {
            background: #e6f3ff;
            border-color: #0056b3;
        }
        .drag-drop-area input[type="file"] {
            display: none;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab değiştirme
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).attr('href').split('tab=')[1];
                window.history.pushState({}, '', $(this).attr('href'));
                loadTab(tab);
            });
            
            // Export format seçimi
            $('.export-format-option').on('click', function() {
                $('.export-format-option').removeClass('selected');
                $(this).addClass('selected');
                $('#export-format').val($(this).data('format'));
            });
            
            // Import format seçimi
            $('.import-format-option').on('click', function() {
                $('.import-format-option').removeClass('selected');
                $(this).addClass('selected');
                $('#import-format').val($(this).data('format'));
            });
            
            // Drag & Drop
            setupDragDrop();
            
            // File validation
            $('#import-file').on('change', function() {
                validateImportFile(this.files[0]);
            });
        });
        
        function setupDragDrop() {
            var $ = jQuery;
            var $dropArea = $('.drag-drop-area');
            
            $dropArea.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            $dropArea.on('dragleave dragend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });
            
            $dropArea.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $('#import-file')[0].files = files;
                    validateImportFile(files[0]);
                }
            });
        }
        
        function validateImportFile(file) {
            var $ = jQuery;
            
            if (!file) return;
            
            // Dosya boyutu kontrolü
            if (file.size > <?php echo $this->max_file_size; ?>) {
                showValidationError('Dosya boyutu çok büyük. Maksimum 50MB yükleyebilirsiniz.');
                return;
            }
            
            // Dosya türü kontrolü
            var allowedTypes = <?php echo json_encode($this->allowed_extensions); ?>;
            var fileExtension = file.name.split('.').pop().toLowerCase();
            
            if (!allowedTypes.includes(fileExtension)) {
                showValidationError('Desteklenmeyen dosya türü. Sadece CSV, XLSX ve JSON dosyaları yükleyebilirsiniz.');
                return;
            }
            
            // AJAX ile dosya doğrulama
            var formData = new FormData();
            formData.append('action', 'validate_import_file');
            formData.append('import_file', file);
            formData.append('nonce', '<?php echo wp_create_nonce('project_gallery_import_nonce'); ?>');
            
            showProgress(0, 'Dosya doğrulanıyor...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    hideProgress();
                    if (response.success) {
                        showValidationSuccess('Dosya doğrulandı! ' + response.data.message);
                        showFilePreview(response.data.preview);
                        $('#start-import').prop('disabled', false);
                    } else {
                        showValidationError(response.data);
                    }
                },
                error: function() {
                    hideProgress();
                    showValidationError('Dosya doğrulama sırasında bir hata oluştu.');
                }
            });
        }
        
        function showValidationSuccess(message) {
            var $ = jQuery;
            $('.validation-results').html('<div class="validation-success">✅ ' + message + '</div>');
        }
        
        function showValidationError(message) {
            var $ = jQuery;
            $('.validation-results').html('<div class="validation-error">❌ ' + message + '</div>');
            $('#start-import').prop('disabled', true);
        }
        
        function showFilePreview(preview) {
            var $ = jQuery;
            $('.file-preview').html('<h4>📋 Dosya Önizleme:</h4>' + preview).show();
        }
        
        function showProgress(percentage, message) {
            var $ = jQuery;
            $('.progress-container').show();
            $('.progress-fill').css('width', percentage + '%');
            $('.progress-text').text(percentage + '%');
            $('#progress-message').text(message || '');
        }
        
        function hideProgress() {
            var $ = jQuery;
            $('.progress-container').hide();
        }
        </script>
        <?php
    }
    
    /**
     * Dışa aktarma sekmesi
     */
    private function render_export_tab() {
        ?>
        <div class="import-export-section">
            <h2>📤 Proje Dışa Aktarma</h2>
            <p>Projelerinizi farklı formatlarda dışa aktararak başka sistemlerde kullanabilir veya yedek alabilirsiniz.</p>
            
            <div class="export-options">
                <div class="option-card export-format-option" data-format="csv">
                    <span class="option-icon">📊</span>
                    <h3>CSV Formatı</h3>
                    <p>Excel ve diğer elektronik tablolarda açılabilir</p>
                    <small>Tablo halinde, filtrelenebilir veriler</small>
                </div>
                
                <div class="option-card export-format-option" data-format="xlsx">
                    <span class="option-icon">📈</span>
                    <h3>Excel Formatı (XLSX)</h3>
                    <p>Microsoft Excel için optimize edilmiş</p>
                    <small>Formatlı tablolar, grafikler dahil</small>
                </div>
                
                <div class="option-card export-format-option" data-format="json">
                    <span class="option-icon">⚙️</span>
                    <h3>JSON Formatı</h3>
                    <p>Programcılar ve API entegrasyonu için</p>
                    <small>Tüm metadata ve ilişkiler dahil</small>
                </div>
            </div>
            
            <form id="export-form">
                <input type="hidden" id="export-format" name="format" value="">
                
                <h3>🎯 Dışa Aktarma Seçenekleri</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Kategoriler</th>
                        <td>
                            <?php
                            $categories = get_terms(array(
                                'taxonomy' => 'proje_kategorisi',
                                'hide_empty' => false
                            ));
                            
                            if (!empty($categories) && !is_wp_error($categories)) {
                                echo '<div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
                                echo '<label><input type="checkbox" id="select-all-categories"> <strong>Tümünü Seç</strong></label><br><br>';
                                
                                foreach ($categories as $category) {
                                    echo '<label style="display: block; margin: 5px 0;">';
                                    echo '<input type="checkbox" name="categories[]" value="' . $category->term_id . '"> ';
                                    echo esc_html($category->name) . ' (' . $category->count . ' proje)';
                                    echo '</label>';
                                }
                                echo '</div>';
                            } else {
                                echo '<p>Henüz kategori oluşturulmamış.</p>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Görsel Dosyaları</th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_images" value="1">
                                Görsel dosyalarını da dahil et (ZIP arşivi olarak)
                            </label>
                            <p class="description">⚠️ Bu seçenek dosya boyutunu önemli ölçüde artırır</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Meta Veriler</th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_meta" value="1" checked>
                                Özel alanlar ve meta verileri dahil et
                            </label><br>
                            <label>
                                <input type="checkbox" name="include_analytics" value="1">
                                Analitik verilerini dahil et
                            </label><br>
                            <label>
                                <input type="checkbox" name="include_translations" value="1">
                                Çoklu dil çevirilerini dahil et
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Tarih Aralığı</th>
                        <td>
                            <label>
                                Başlangıç: <input type="date" name="date_from">
                                Bitiş: <input type="date" name="date_to">
                            </label>
                            <p class="description">Boş bırakırsanız tüm projeler dahil edilir</p>
                        </td>
                    </tr>
                </table>
                
                <div class="progress-container">
                    <h4>⏳ Dışa Aktarma İşlemi</h4>
                    <div class="progress-bar">
                        <div class="progress-fill">
                            <span class="progress-text">0%</span>
                        </div>
                    </div>
                    <div id="progress-message">Hazırlanıyor...</div>
                </div>
                
                <p class="submit">
                    <button type="button" id="start-export" class="button button-primary button-large" disabled>
                        📤 Dışa Aktarmayı Başlat
                    </button>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Format seçildiğinde export butonunu aktif et
            $('.export-format-option').on('click', function() {
                $('#start-export').prop('disabled', false);
            });
            
            // Tümünü seç/bırak
            $('#select-all-categories').on('change', function() {
                $('input[name="categories[]"]').prop('checked', this.checked);
            });
            
            // Export başlat
            $('#start-export').on('click', function() {
                var format = $('#export-format').val();
                if (!format) {
                    alert('Lütfen bir format seçin.');
                    return;
                }
                
                startExport();
            });
        });
        
        function startExport() {
            var $ = jQuery;
            var formData = $('#export-form').serialize();
            formData += '&action=export_projects_advanced&nonce=<?php echo wp_create_nonce('project_gallery_export_nonce'); ?>';
            
            showProgress(0, 'Projeler hazırlanıyor...');
            
            // Export'u başlat
            $.post(ajaxurl, formData)
                .progress(function(e) {
                    if (e.lengthComputable) {
                        var percentage = Math.round((e.loaded / e.total) * 100);
                        showProgress(percentage, 'İndiriliyor... ' + percentage + '%');
                    }
                })
                .done(function(response) {
                    hideProgress();
                    if (response.success) {
                        // Dosyayı indir
                        window.location.href = response.data.download_url;
                        
                        // Başarı mesajı
                        showNotification('✅ Dışa aktarma tamamlandı! Dosya indiriliyor...', 'success');
                    } else {
                        showNotification('❌ Hata: ' + response.data, 'error');
                    }
                })
                .fail(function() {
                    hideProgress();
                    showNotification('❌ Dışa aktarma sırasında bir hata oluştu.', 'error');
                });
        }
        </script>
        <?php
    }
    
    /**
     * İçe aktarma sekmesi
     */
    private function render_import_tab() {
        ?>
        <div class="import-export-section">
            <h2>📥 Proje İçe Aktarma</h2>
            <p>CSV, Excel (XLSX) veya JSON formatındaki dosyalardan projeleri sisteminize aktarabilirsiniz.</p>
            
            <div class="drag-drop-area" onclick="document.getElementById('import-file').click();">
                <span style="font-size: 3em; display: block; margin-bottom: 15px;">📁</span>
                <h3>Dosyayı buraya sürükleyin veya tıklayarak seçin</h3>
                <p>CSV, XLSX veya JSON • Maksimum 50MB</p>
                <input type="file" id="import-file" accept=".csv,.xlsx,.json">
            </div>
            
            <div class="validation-results"></div>
            <div class="file-preview" style="display: none;"></div>
            
            <form id="import-form" style="display: none;">
                <h3>⚙️ İçe Aktarma Ayarları</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Mevcut Projeler</th>
                        <td>
                            <label>
                                <input type="radio" name="duplicate_action" value="skip" checked>
                                Aynı isimdeki projeleri atla
                            </label><br>
                            <label>
                                <input type="radio" name="duplicate_action" value="update">
                                Mevcut projeleri güncelle
                            </label><br>
                            <label>
                                <input type="radio" name="duplicate_action" value="duplicate">
                                Yeni proje olarak ekle (kopya oluştur)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Kategoriler</th>
                        <td>
                            <label>
                                <input type="checkbox" name="create_categories" value="1" checked>
                                Mevcut olmayan kategorileri otomatik oluştur
                            </label><br>
                            <label>
                                <input type="checkbox" name="import_category_descriptions" value="1">
                                Kategori açıklamalarını da içe aktar
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Görseller</th>
                        <td>
                            <label>
                                <input type="radio" name="image_import" value="url" checked>
                                URL'lerden görselleri indir
                            </label><br>
                            <label>
                                <input type="radio" name="image_import" value="skip">
                                Görselleri atla (sadece metin verileri)
                            </label><br>
                            <label>
                                <input type="checkbox" name="optimize_images" value="1" checked>
                                Görselleri otomatik optimize et
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Yazar</th>
                        <td>
                            <select name="default_author">
                                <option value="current"><?php echo wp_get_current_user()->display_name; ?> (Mevcut kullanıcı)</option>
                                <?php
                                $users = get_users(array('capability' => 'edit_posts'));
                                foreach ($users as $user) {
                                    echo '<option value="' . $user->ID . '">' . esc_html($user->display_name) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Dosyada yazar bilgisi olmayan projeler için</p>
                        </td>
                    </tr>
                </table>
                
                <div class="progress-container">
                    <h4>⏳ İçe Aktarma İşlemi</h4>
                    <div class="progress-bar">
                        <div class="progress-fill">
                            <span class="progress-text">0%</span>
                        </div>
                    </div>
                    <div id="progress-message">Hazırlanıyor...</div>
                    <div id="import-details" style="margin-top: 10px; font-size: 12px; color: #666;"></div>
                </div>
                
                <p class="submit">
                    <button type="button" id="start-import" class="button button-primary button-large" disabled>
                        📥 İçe Aktarmayı Başlat
                    </button>
                    <button type="button" id="cancel-import" class="button" style="display: none;">
                        ❌ İptal Et
                    </button>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var importInProgress = false;
            
            // Dosya seçildiğinde formu göster
            $('#import-file').on('change', function() {
                if (this.files.length > 0) {
                    $('#import-form').show();
                }
            });
            
            // Import başlat
            $('#start-import').on('click', function() {
                if (importInProgress) return;
                startImport();
            });
            
            // Import iptal et
            $('#cancel-import').on('click', function() {
                if (confirm('İçe aktarma işlemini iptal etmek istediğinizden emin misiniz?')) {
                    cancelImport();
                }
            });
        });
        
        function startImport() {
            var $ = jQuery;
            importInProgress = true;
            
            var formData = new FormData($('#import-form')[0]);
            formData.append('action', 'import_projects_progress');
            formData.append('import_file', $('#import-file')[0].files[0]);
            formData.append('nonce', '<?php echo wp_create_nonce('project_gallery_import_nonce'); ?>');
            
            $('#start-import').prop('disabled', true).text('⏳ İşlem devam ediyor...');
            $('#cancel-import').show();
            
            showProgress(0, 'İçe aktarma başlatılıyor...');
            
            // Chunked import için AJAX
            startChunkedImport(formData);
        }
        
        function startChunkedImport(formData) {
            var $ = jQuery;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    
                    // Upload progress
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percentage = Math.round((e.loaded / e.total) * 100);
                            showProgress(percentage, 'Dosya yükleniyor... ' + percentage + '%');
                        }
                    });
                    
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        showProgress(100, 'İçe aktarma tamamlandı!');
                        showImportResults(response.data);
                    } else {
                        showProgress(0, 'Hata oluştu: ' + response.data);
                    }
                    completeImport();
                },
                error: function(xhr, status, error) {
                    showProgress(0, 'Bağlantı hatası: ' + error);
                    completeImport();
                }
            });
        }
        
        function showImportResults(results) {
            var $ = jQuery;
            var html = '<div class="import-results" style="background: #f0fff0; border: 1px solid #46b450; border-radius: 4px; padding: 15px; margin: 15px 0;">';
            html += '<h4 style="margin: 0 0 10px 0; color: #46b450;">✅ İçe Aktarma Tamamlandı</h4>';
            html += '<ul style="margin: 0; padding-left: 20px;">';
            html += '<li><strong>' + results.imported + '</strong> proje başarıyla içe aktarıldı</li>';
            html += '<li><strong>' + results.updated + '</strong> mevcut proje güncellendi</li>';
            html += '<li><strong>' + results.skipped + '</strong> proje atlandı</li>';
            if (results.errors > 0) {
                html += '<li style="color: #dc3232;"><strong>' + results.errors + '</strong> hatada proje işlenemedi</li>';
            }
            html += '</ul>';
            
            if (results.error_details && results.error_details.length > 0) {
                html += '<details style="margin-top: 10px;"><summary>Hata Detayları</summary>';
                html += '<ul style="color: #dc3232; font-size: 12px;">';
                results.error_details.forEach(function(error) {
                    html += '<li>' + error + '</li>';
                });
                html += '</ul></details>';
            }
            
            html += '</div>';
            
            $('#import-details').html(html);
        }
        
        function completeImport() {
            var $ = jQuery;
            importInProgress = false;
            $('#start-import').prop('disabled', false).text('📥 İçe Aktarmayı Başlat');
            $('#cancel-import').hide();
        }
        
        function cancelImport() {
            // Import cancel logic here
            var $ = jQuery;
            importInProgress = false;
            hideProgress();
            completeImport();
        }
        </script>
        <?php
    }
    
    /**
     * Export projects to JSON/CSV
     */
    public function export_projects() {
        check_ajax_referer('project_gallery_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'project-gallery'));
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'json');
        $include_images = filter_var($_POST['include_images'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $categories = array_map('intval', $_POST['categories'] ?? array());
        
        $projects = $this->get_projects_for_export($categories);
        
        if ($format === 'csv') {
            $this->export_csv($projects);
        } else {
            $this->export_json($projects, $include_images);
        }
    }
    
    /**
     * Import projects from JSON/CSV
     */
    public function import_projects() {
        check_ajax_referer('project_gallery_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'project-gallery'));
        }
        
        if (!isset($_FILES['import_file'])) {
            wp_send_json_error('No file uploaded.');
        }
        
        $file = $_FILES['import_file'];
        $update_existing = filter_var($_POST['update_existing'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        $file_content = file_get_contents($file['tmp_name']);
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        try {
            if ($file_extension === 'json') {
                $result = $this->import_json($file_content, $update_existing);
            } elseif ($file_extension === 'csv') {
                $result = $this->import_csv($file_content, $update_existing);
            } else {
                throw new Exception('Unsupported file format.');
            }
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Create backup of all gallery data
     */
    public function backup_gallery_data() {
        check_ajax_referer('project_gallery_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'project-gallery'));
        }
        
        $backup_data = array(
            'version' => PROJECT_GALLERY_VERSION,
            'timestamp' => current_time('mysql'),
            'projects' => $this->get_projects_for_export(),
            'categories' => $this->get_categories_for_export(),
            'settings' => $this->get_settings_for_export(),
            'analytics' => $this->get_analytics_for_export()
        );
        
        $filename = 'project-gallery-backup-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate');
        
        echo json_encode($backup_data, JSON_PRETTY_PRINT);
        wp_die();
    }
    
    /**
     * Restore from backup
     */
    public function restore_gallery_data() {
        check_ajax_referer('project_gallery_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'project-gallery'));
        }
        
        if (!isset($_FILES['backup_file'])) {
            wp_send_json_error('No backup file uploaded.');
        }
        
        $file_content = file_get_contents($_FILES['backup_file']['tmp_name']);
        $backup_data = json_decode($file_content, true);
        
        if (!$backup_data) {
            wp_send_json_error('Invalid backup file format.');
        }
        
        try {
            $result = $this->restore_from_backup($backup_data);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Get projects for export
     */
    private function get_projects_for_export($categories = array(), $options = array()) {
        $args = array(
            'post_type' => 'proje',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'draft', 'private')
        );
        
        // Kategori filtresi
        if (!empty($categories)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'proje_kategorisi',
                    'field' => 'term_id',
                    'terms' => $categories
                )
            );
        }
        
        // Tarih filtresi
        if (!empty($options['date_from']) || !empty($options['date_to'])) {
            $date_query = array();
            
            if (!empty($options['date_from'])) {
                $date_query['after'] = $options['date_from'];
            }
            
            if (!empty($options['date_to'])) {
                $date_query['before'] = $options['date_to'];
            }
            
            $args['date_query'] = array($date_query);
        }
        
        $projects = get_posts($args);
        $export_data = array();
        
        foreach ($projects as $project) {
            $project_data = array(
                'id' => $project->ID,
                'title' => $project->post_title,
                'slug' => $project->post_name,
                'content' => $project->post_content,
                'excerpt' => $project->post_excerpt,
                'status' => $project->post_status,
                'date' => $project->post_date,
                'modified' => $project->post_modified,
                'author' => get_userdata($project->post_author)->display_name,
                'categories' => array(),
                'featured_image' => '',
                'gallery_images' => array(),
                'custom_fields' => array(),
                'meta_data' => array()
            );
            
            // Kategoriler
            $terms = get_the_terms($project->ID, 'proje_kategorisi');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $project_data['categories'][] = $term->name;
                }
            }
            
            // Öne çıkan görsel
            if (has_post_thumbnail($project->ID)) {
                $project_data['featured_image'] = get_the_post_thumbnail_url($project->ID, 'full');
            }
            
            // Galeri resimleri
            $gallery_images = get_post_meta($project->ID, '_project_gallery_images', true);
            if ($gallery_images) {
                $image_ids = explode(',', $gallery_images);
                foreach ($image_ids as $image_id) {
                    if ($image_id) {
                        $image_url = wp_get_attachment_url($image_id);
                        if ($image_url) {
                            $project_data['gallery_images'][] = $image_url;
                        }
                    }
                }
            }
            
            // Özel alanlar (custom fields sınıfından)
            if (isset($options['include_meta']) && $options['include_meta']) {
                $custom_fields = get_post_meta($project->ID);
                foreach ($custom_fields as $key => $value) {
                    if (strpos($key, '_') !== 0) { // Private meta'ları hariç tut
                        $project_data['custom_fields'][$key] = is_array($value) ? $value[0] : $value;
                    }
                }
            }
            
            // Analitik veriler
            if (isset($options['include_analytics']) && $options['include_analytics']) {
                global $wpdb;
                $analytics = $wpdb->get_row($wpdb->prepare(
                    "SELECT SUM(views) as views, SUM(likes) as likes, SUM(shares) as shares 
                     FROM {$wpdb->prefix}project_gallery_analytics 
                     WHERE project_id = %d",
                    $project->ID
                ));
                
                if ($analytics) {
                    $project_data['views'] = $analytics->views ?: 0;
                    $project_data['likes'] = $analytics->likes ?: 0;
                    $project_data['shares'] = $analytics->shares ?: 0;
                }
            }
            
            // Çeviriler
            if (isset($options['include_translations']) && $options['include_translations']) {
                global $wpdb;
                $translations = $wpdb->get_results($wpdb->prepare(
                    "SELECT language_code, translated_title, translated_content, translated_excerpt 
                     FROM {$wpdb->prefix}project_gallery_translations 
                     WHERE project_id = %d",
                    $project->ID
                ));
                
                $project_data['translations'] = array();
                foreach ($translations as $translation) {
                    $project_data['translations'][$translation->language_code] = array(
                        'title' => $translation->translated_title,
                        'content' => $translation->translated_content,
                        'excerpt' => $translation->translated_excerpt
                    );
                }
            }
            
            $export_data[] = $project_data;
        }
        
        return $export_data;
    }
    
    /**
     * Export to JSON format
     */
    private function export_json($projects, $include_images = false) {
        $export_data = array(
            'version' => PROJECT_GALLERY_VERSION,
            'export_date' => current_time('mysql'),
            'projects' => $projects
        );
        
        if ($include_images) {
            $export_data['images'] = $this->get_images_data($projects);
        }
        
        $filename = 'project-gallery-export-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate');
        
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        wp_die();
    }
    
    /**
     * Export to CSV format
     */
    private function export_csv($projects) {
        $filename = 'project-gallery-export-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'ID', 'Title', 'Content', 'Status', 'Date', 
            'Featured Image', 'Gallery Images', 'Categories'
        ));
        
        // CSV data
        foreach ($projects as $project) {
            fputcsv($output, array(
                $project['id'],
                $project['title'],
                strip_tags($project['content']),
                $project['status'],
                $project['date'],
                $project['featured_image'],
                implode(';', $project['gallery_images']),
                implode(';', array_column($project['categories'], 'name'))
            ));
        }
        
        fclose($output);
        wp_die();
    }
    
    /**
     * Import from JSON
     */
    private function import_json($file_content, $update_existing) {
        $data = json_decode($file_content, true);
        
        if (!$data || !isset($data['projects'])) {
            throw new Exception('Invalid JSON format.');
        }
        
        $imported = 0;
        $updated = 0;
        $errors = array();
        
        foreach ($data['projects'] as $project_data) {
            try {
                $result = $this->import_single_project($project_data, $update_existing);
                if ($result === 'imported') {
                    $imported++;
                } elseif ($result === 'updated') {
                    $updated++;
                }
            } catch (Exception $e) {
                $errors[] = sprintf('Project "%s": %s', $project_data['title'], $e->getMessage());
            }
        }
        
        return array(
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors
        );
    }
    
    /**
     * Import single project
     */
    private function import_single_project($project_data, $update_existing) {
        // Check if project exists
        $existing_project = get_page_by_title($project_data['title'], OBJECT, 'proje');
        
        if ($existing_project && !$update_existing) {
            throw new Exception('Project already exists and update is disabled.');
        }
        
        $post_data = array(
            'post_title' => $project_data['title'],
            'post_content' => $project_data['content'],
            'post_excerpt' => $project_data['excerpt'] ?? '',
            'post_status' => $project_data['status'] ?? 'publish',
            'post_type' => 'proje'
        );
        
        if ($existing_project) {
            $post_data['ID'] = $existing_project->ID;
            $post_id = wp_update_post($post_data);
            $result = 'updated';
        } else {
            $post_id = wp_insert_post($post_data);
            $result = 'imported';
        }
        
        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }
        
        // Set featured image
        if (!empty($project_data['featured_image'])) {
            set_post_thumbnail($post_id, $project_data['featured_image']);
        }
        
        // Set gallery images
        if (!empty($project_data['gallery_images'])) {
            update_post_meta($post_id, '_project_gallery_images', implode(',', $project_data['gallery_images']));
        }
        
        // Set categories
        if (!empty($project_data['categories'])) {
            $term_ids = array();
            foreach ($project_data['categories'] as $category) {
                $term = get_term_by('slug', $category['slug'], 'proje-kategori');
                if (!$term) {
                    $term = wp_insert_term($category['name'], 'proje-kategori', array('slug' => $category['slug']));
                    if (!is_wp_error($term)) {
                        $term_ids[] = $term['term_id'];
                    }
                } else {
                    $term_ids[] = $term->term_id;
                }
            }
            wp_set_post_terms($post_id, $term_ids, 'proje-kategori');
        }
        
        return $result;
    }
    
    /**
     * Get categories for export
     */
    private function get_categories_for_export() {
        $categories = get_terms(array(
            'taxonomy' => 'proje-kategori',
            'hide_empty' => false
        ));
        
        return array_map(function($term) {
            return array(
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'parent' => $term->parent,
                'count' => $term->count
            );
        }, $categories);
    }
    
    /**
     * Get settings for export
     */
    private function get_settings_for_export() {
        return get_option('project_gallery_settings', array());
    }
    
    /**
     * Get analytics for export
     */
    private function get_analytics_for_export() {
        global $wpdb;
        
        $analytics = new ProjectGalleryAnalytics();
        return $analytics->get_dashboard_data('1_year');
    }
    
    /**
     * Restore from backup
     */
    private function restore_from_backup($backup_data) {
        if (!isset($backup_data['version']) || !isset($backup_data['projects'])) {
            throw new Exception('Invalid backup format.');
        }
        
        // Restore projects
        $project_result = $this->import_json(json_encode($backup_data), true);
        
        // Restore settings
        if (isset($backup_data['settings'])) {
            update_option('project_gallery_settings', $backup_data['settings']);
        }
        
        // Restore categories
        if (isset($backup_data['categories'])) {
            $this->restore_categories($backup_data['categories']);
        }
        
        return array(
            'message' => 'Backup restored successfully.',
            'projects' => $project_result
        );
    }
    
    /**
     * Restore categories
     */
    private function restore_categories($categories) {
        foreach ($categories as $category_data) {
            $existing = get_term_by('slug', $category_data['slug'], 'proje-kategori');
            
            if (!$existing) {
                wp_insert_term($category_data['name'], 'proje-kategori', array(
                    'slug' => $category_data['slug'],
                    'description' => $category_data['description'],
                    'parent' => $category_data['parent']
                ));
            }
        }
    }
    
    /**
     * XLSX import with progress (fallback to CSV for now)
     */
    private function import_xlsx_with_progress($file_content, $options) {
        // Gerçek projede PhpSpreadsheet kullanılacak
        // Şimdilik CSV import kullan
        return $this->import_csv_with_progress($file_content, $options);
    }
    
    /**
     * JSON import with progress
     */
    private function import_json_with_progress($file_content, $options) {
        $data = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Geçersiz JSON formatı: ' . json_last_error_msg());
        }
        
        $result = array(
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => array()
        );
        
        $total_records = count($data);
        $processed = 0;
        
        foreach ($data as $project_data) {
            try {
                $import_result = $this->import_single_project_from_json($project_data, $options);
                $result[$import_result]++;
                
            } catch (Exception $e) {
                $result['errors']++;
                $result['error_details'][] = "Kayıt " . ($processed + 1) . ": " . $e->getMessage();
            }
            
            $processed++;
            
            // Progress update
            if ($processed % 10 === 0) {
                usleep(100000); // 100ms delay
            }
        }
        
        return $result;
    }
    
    /**
     * JSON'dan tek proje import et
     */
    private function import_single_project_from_json($project_data, $options) {
        if (empty($project_data['title'])) {
            throw new Exception('Proje başlığı boş olamaz');
        }
        
        $title = sanitize_text_field($project_data['title']);
        
        // Mevcut proje kontrolü
        $existing_project = get_page_by_title($title, OBJECT, 'proje');
        
        if ($existing_project) {
            switch ($options['duplicate_action']) {
                case 'skip':
                    return 'skipped';
                    
                case 'update':
                    return $this->update_existing_project($existing_project->ID, $project_data, $options);
                    
                case 'duplicate':
                    $title .= ' - Kopya ' . date('Y-m-d H:i:s');
                    break;
            }
        }
        
        // Yeni proje oluştur
        $post_data = array(
            'post_title' => $title,
            'post_content' => wp_kses_post($project_data['content'] ?? ''),
            'post_excerpt' => sanitize_textarea_field($project_data['excerpt'] ?? ''),
            'post_status' => sanitize_text_field($project_data['status'] ?? 'publish'),
            'post_type' => 'proje',
            'post_author' => $options['default_author'],
            'post_date' => sanitize_text_field($project_data['date'] ?? ''),
        );
        
        $project_id = wp_insert_post($post_data);
        
        if (is_wp_error($project_id)) {
            throw new Exception('Proje oluşturulamadı: ' . $project_id->get_error_message());
        }
        
        // Kategorileri ata (array format)
        if (!empty($project_data['categories']) && is_array($project_data['categories'])) {
            $this->assign_project_categories($project_id, implode(';', $project_data['categories']), $options);
        }
        
        // Görselleri işle
        if (!empty($project_data['featured_image'])) {
            $featured_image_id = $this->import_image_from_url($project_data['featured_image'], $project_id, $options);
            if ($featured_image_id) {
                set_post_thumbnail($project_id, $featured_image_id);
            }
        }
        
        if (!empty($project_data['gallery_images']) && is_array($project_data['gallery_images'])) {
            $gallery_ids = array();
            foreach ($project_data['gallery_images'] as $url) {
                $image_id = $this->import_image_from_url($url, $project_id, $options);
                if ($image_id) {
                    $gallery_ids[] = $image_id;
                }
            }
            
            if (!empty($gallery_ids)) {
                update_post_meta($project_id, '_project_gallery_images', implode(',', $gallery_ids));
            }
        }
        
        // Özel alanlar
        if (!empty($project_data['custom_fields']) && is_array($project_data['custom_fields'])) {
            foreach ($project_data['custom_fields'] as $key => $value) {
                update_post_meta($project_id, sanitize_key($key), sanitize_text_field($value));
            }
        }
        
        // Meta veriler
        if (!empty($project_data['meta_data']) && is_array($project_data['meta_data'])) {
            foreach ($project_data['meta_data'] as $key => $value) {
                update_post_meta($project_id, sanitize_key($key), $value);
            }
        }
        
        return 'imported';
    }
    
    /**
     * Mevcut projeyi güncelle
     */
    private function update_existing_project($project_id, $project_data, $options) {
        $post_data = array(
            'ID' => $project_id,
            'post_title' => sanitize_text_field($project_data['Başlık'] ?? $project_data['title']),
            'post_content' => wp_kses_post($project_data['İçerik'] ?? $project_data['content'] ?? ''),
            'post_excerpt' => sanitize_textarea_field($project_data['Özet'] ?? $project_data['excerpt'] ?? ''),
            'post_status' => sanitize_text_field($project_data['Durum'] ?? $project_data['status'] ?? 'publish'),
        );
        
        $result = wp_update_post($post_data);
        
        if (is_wp_error($result)) {
            throw new Exception('Proje güncellenemedi: ' . $result->get_error_message());
        }
        
        return 'updated';
    }
}