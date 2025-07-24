<?php
/**
 * Custom Fields Management Class
 * 
 * Handles project custom fields functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProjectGalleryCustomFields {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('add_meta_boxes', array($this, 'add_custom_fields_meta_box'));
        add_action('save_post', array($this, 'save_custom_fields'));
        add_action('wp_ajax_save_custom_fields_config', array($this, 'save_custom_fields_config'));
    }
    
    /**
     * Admin menü ekleme
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=proje',
            'Özel Alanlar',
            'Özel Alanlar',
            'manage_options',
            'project-custom-fields',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin ayarları kayıt
     */
    public function admin_init() {
        register_setting('project_custom_fields_group', 'project_custom_fields_config');
    }
    
    /**
     * Admin sayfası
     */
    public function admin_page() {
        $config = get_option('project_custom_fields_config', array());
        ?>
        <div class="wrap">
            <h1>🏷️ Proje Özel Alanları</h1>
            <p>Projelerde gösterilecek özel alanları bu sayfadan yönetebilirsiniz. Alanlar kategori bilgisinin yanında görüntülenecektir.</p>
            
            <div class="custom-fields-container">
                <div class="custom-fields-form">
                    <form id="custom-fields-form">
                        <?php wp_nonce_field('custom_fields_nonce', 'custom_fields_nonce'); ?>
                        
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label>Aktif Özel Alanlar</label>
                                    </th>
                                    <td>
                                        <div id="custom-fields-list">
                                            <?php if (!empty($config)): ?>
                                                <?php foreach ($config as $index => $field): ?>
                                                    <div class="custom-field-item" data-index="<?php echo $index; ?>">
                                                        <div class="field-controls">
                                                            <span class="sort-handle">⋮⋮</span>
                                                            <input type="text" 
                                                                   name="field_label[]" 
                                                                   value="<?php echo esc_attr($field['label']); ?>" 
                                                                   placeholder="Alan Adı (örn: Proje Yılı)" 
                                                                   class="regular-text" />
                                                            <input type="text" 
                                                                   name="field_key[]" 
                                                                   value="<?php echo esc_attr($field['key']); ?>" 
                                                                   placeholder="alan_anahtari" 
                                                                   class="regular-text field-key" />
                                                            <select name="field_type[]" class="field-type">
                                                                <option value="text" <?php selected($field['type'], 'text'); ?>>Metin</option>
                                                                <option value="number" <?php selected($field['type'], 'number'); ?>>Sayı</option>
                                                                <option value="date" <?php selected($field['type'], 'date'); ?>>Tarih</option>
                                                                <option value="url" <?php selected($field['type'], 'url'); ?>>URL</option>
                                                                <option value="email" <?php selected($field['type'], 'email'); ?>>E-posta</option>
                                                                <option value="textarea" <?php selected($field['type'], 'textarea'); ?>>Çok Satırlı Metin</option>
                                                            </select>
                                                            <button type="button" class="button remove-field">🗑️</button>
                                                        </div>
                                                        <div class="field-description">
                                                            <small>Anahtar: <code>_project_<?php echo esc_html($field['key']); ?></code></small>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="no-fields">Henüz özel alan tanımlanmamış.</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="field-actions">
                                            <button type="button" id="add-custom-field" class="button button-primary">➕ Yeni Alan Ekle</button>
                                            <button type="submit" class="button button-secondary">💾 Değişiklikleri Kaydet</button>
                                        </div>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">
                                        <label>Önceden Tanımlı Alanlar</label>
                                    </th>
                                    <td>
                                        <div class="predefined-fields">
                                            <button type="button" class="button add-predefined" data-label="Proje Yılı" data-key="proje_yili" data-type="number">📅 Proje Yılı</button>
                                            <button type="button" class="button add-predefined" data-label="Proje Şehir" data-key="proje_sehir" data-type="text">🏙️ Proje Şehir</button>
                                            <button type="button" class="button add-predefined" data-label="Müşteri" data-key="musteri" data-type="text">👤 Müşteri</button>
                                            <button type="button" class="button add-predefined" data-label="Proje Süresi" data-key="proje_suresi" data-type="text">⏱️ Proje Süresi</button>
                                            <button type="button" class="button add-predefined" data-label="Bütçe" data-key="butce" data-type="text">💰 Bütçe</button>
                                            <button type="button" class="button add-predefined" data-label="Ekip Boyutu" data-key="ekip_boyutu" data-type="number">👥 Ekip Boyutu</button>
                                            <button type="button" class="button add-predefined" data-label="Web Sitesi" data-key="web_sitesi" data-type="url">🌐 Web Sitesi</button>
                                        </div>
                                        <p class="description">Yukarıdaki düğmelere tıklayarak hızlıca önceden tanımlı alanları ekleyebilirsiniz.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </form>
                </div>
                
                <div class="custom-fields-preview">
                    <h3>Önizleme</h3>
                    <div class="preview-container">
                        <div class="single-project-header-preview">
                            <h4>Örnek Proje Başlığı</h4>
                            <div class="project-meta-preview">
                                <div class="single-project-categories">
                                    <a href="#" class="single-project-category">Mimari</a>
                                    <a href="#" class="single-project-category">Modern</a>
                                </div>
                                <div class="single-project-custom-fields" id="preview-custom-fields">
                                    <?php if (!empty($config)): ?>
                                        <?php foreach ($config as $field): ?>
                                            <div class="custom-field-display">
                                                <span class="field-label"><?php echo esc_html($field['label']); ?>:</span>
                                                <span class="field-value">Örnek değer</span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="no-custom-fields">Henüz özel alan yok</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .custom-fields-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .custom-field-item {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .field-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .sort-handle {
            cursor: move;
            color: #666;
            font-size: 16px;
            user-select: none;
        }
        
        .field-controls input,
        .field-controls select {
            flex: 1;
        }
        
        .field-key {
            font-family: monospace;
            background: #fff;
        }
        
        .field-description {
            font-size: 12px;
            color: #666;
        }
        
        .field-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        
        .predefined-fields {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .predefined-fields .button {
            white-space: nowrap;
        }
        
        .custom-fields-preview {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 32px;
        }
        
        .preview-container {
            border: 2px dashed #ddd;
            padding: 20px;
            border-radius: 6px;
            background: #fafafa;
        }
        
        .single-project-header-preview h4 {
            text-align: center;
            font-size: 24px;
            margin: 0 0 15px 0;
            padding: 10px 0;
            border-top: 1px solid #ccc;
            border-bottom: 1px solid #ccc;
        }
        
        .project-meta-preview {
            text-align: center;
        }
        
        .single-project-categories {
            margin-bottom: 15px;
        }
        
        .single-project-category {
            display: inline-block;
            background: #007cba;
            color: white;
            padding: 4px 8px;
            margin: 2px;
            border-radius: 3px;
            text-decoration: none;
            font-size: 12px;
        }
        
        .single-project-custom-fields {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
        }
        
        .custom-field-display {
            background: #f0f0f1;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .field-label {
            font-weight: 600;
            color: #50575e;
        }
        
        .field-value {
            color: #1d2327;
        }
        
        .no-fields,
        .no-custom-fields {
            text-align: center;
            color: #666;
            font-style: italic;
        }
        
        .sortable-ghost {
            opacity: 0.4;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let fieldIndex = <?php echo count($config); ?>;
            
            // Add new custom field
            $('#add-custom-field').on('click', function() {
                addCustomField();
            });
            
            // Add predefined field
            $('.add-predefined').on('click', function() {
                const label = $(this).data('label');
                const key = $(this).data('key');
                const type = $(this).data('type');
                addCustomField(label, key, type);
            });
            
            // Remove field
            $(document).on('click', '.remove-field', function() {
                $(this).closest('.custom-field-item').remove();
                updatePreview();
                checkEmptyState();
            });
            
            // Update preview on field changes
            $(document).on('input change', '.custom-field-item input, .custom-field-item select', function() {
                updatePreview();
            });
            
            // Save configuration
            $('#custom-fields-form').on('submit', function(e) {
                e.preventDefault();
                saveConfiguration();
            });
            
            // Auto-generate key from label
            $(document).on('input', 'input[name="field_label[]"]', function() {
                const $this = $(this);
                const $keyInput = $this.siblings('.field-key');
                if (!$keyInput.data('manual-edit')) {
                    const label = $this.val();
                    const key = label.toLowerCase()
                        .replace(/ğ/g, 'g')
                        .replace(/ü/g, 'u')
                        .replace(/ş/g, 's')
                        .replace(/ı/g, 'i')
                        .replace(/ö/g, 'o')
                        .replace(/ç/g, 'c')
                        .replace(/[^a-z0-9]/g, '_')
                        .replace(/_+/g, '_')
                        .replace(/^_|_$/g, '');
                    $keyInput.val(key);
                }
            });
            
            // Mark key as manually edited
            $(document).on('input', '.field-key', function() {
                $(this).data('manual-edit', true);
            });
            
            function addCustomField(label = '', key = '', type = 'text') {
                const fieldHtml = `
                    <div class="custom-field-item" data-index="${fieldIndex}">
                        <div class="field-controls">
                            <span class="sort-handle">⋮⋮</span>
                            <input type="text" name="field_label[]" value="${label}" placeholder="Alan Adı (örn: Proje Yılı)" class="regular-text" />
                            <input type="text" name="field_key[]" value="${key}" placeholder="alan_anahtari" class="regular-text field-key" />
                            <select name="field_type[]" class="field-type">
                                <option value="text" ${type === 'text' ? 'selected' : ''}>Metin</option>
                                <option value="number" ${type === 'number' ? 'selected' : ''}>Sayı</option>
                                <option value="date" ${type === 'date' ? 'selected' : ''}>Tarih</option>
                                <option value="url" ${type === 'url' ? 'selected' : ''}>URL</option>
                                <option value="email" ${type === 'email' ? 'selected' : ''}>E-posta</option>
                                <option value="textarea" ${type === 'textarea' ? 'selected' : ''}>Çok Satırlı Metin</option>
                            </select>
                            <button type="button" class="button remove-field">🗑️</button>
                        </div>
                        <div class="field-description">
                            <small>Anahtar: <code>_project_${key}</code></small>
                        </div>
                    </div>
                `;
                
                if ($('#custom-fields-list .no-fields').length) {
                    $('#custom-fields-list').html(fieldHtml);
                } else {
                    $('#custom-fields-list').append(fieldHtml);
                }
                
                fieldIndex++;
                updatePreview();
            }
            
            function updatePreview() {
                const fields = [];
                $('.custom-field-item').each(function() {
                    const label = $(this).find('input[name="field_label[]"]').val();
                    if (label.trim()) {
                        fields.push(label);
                    }
                });
                
                if (fields.length > 0) {
                    let previewHtml = '';
                    fields.forEach(function(label) {
                        previewHtml += `
                            <div class="custom-field-display">
                                <span class="field-label">${label}:</span>
                                <span class="field-value">Örnek değer</span>
                            </div>
                        `;
                    });
                    $('#preview-custom-fields').html(previewHtml);
                } else {
                    $('#preview-custom-fields').html('<p class="no-custom-fields">Henüz özel alan yok</p>');
                }
            }
            
            function checkEmptyState() {
                if ($('.custom-field-item').length === 0) {
                    $('#custom-fields-list').html('<p class="no-fields">Henüz özel alan tanımlanmamış.</p>');
                }
            }
            
            function saveConfiguration() {
                const fields = [];
                $('.custom-field-item').each(function() {
                    const label = $(this).find('input[name="field_label[]"]').val();
                    const key = $(this).find('input[name="field_key[]"]').val();
                    const type = $(this).find('select[name="field_type[]"]').val();
                    
                    if (label.trim() && key.trim()) {
                        fields.push({
                            label: label.trim(),
                            key: key.trim(),
                            type: type
                        });
                    }
                });
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'save_custom_fields_config',
                        fields: fields,
                        nonce: $('#custom-fields-form input[name="custom_fields_nonce"]').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            $('<div class="notice notice-success is-dismissible"><p>✅ Özel alanlar başarıyla kaydedildi!</p></div>')
                                .insertAfter('.wrap h1').delay(3000).fadeOut();
                        } else {
                            $('<div class="notice notice-error is-dismissible"><p>❌ Kaydetme sırasında hata oluştu.</p></div>')
                                .insertAfter('.wrap h1');
                        }
                    },
                    error: function() {
                        $('<div class="notice notice-error is-dismissible"><p>❌ AJAX hatası oluştu.</p></div>')
                            .insertAfter('.wrap h1');
                    }
                });
            }
            
            // Initialize sortable
            if (typeof Sortable !== 'undefined') {
                new Sortable(document.getElementById('custom-fields-list'), {
                    handle: '.sort-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost'
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Özel alan yapılandırmasını kaydet
     */
    public function save_custom_fields_config() {
        if (!wp_verify_nonce($_POST['nonce'], 'custom_fields_nonce') || !current_user_can('manage_options')) {
            wp_die('Yetkisiz erişim');
        }
        
        $fields = isset($_POST['fields']) ? $_POST['fields'] : array();
        $sanitized_fields = array();
        
        foreach ($fields as $field) {
            if (!empty($field['label']) && !empty($field['key'])) {
                $sanitized_fields[] = array(
                    'label' => sanitize_text_field($field['label']),
                    'key' => sanitize_key($field['key']),
                    'type' => sanitize_text_field($field['type'])
                );
            }
        }
        
        update_option('project_custom_fields_config', $sanitized_fields);
        wp_send_json_success();
    }
    
    /**
     * Özel alanlar meta box ekleme
     */
    public function add_custom_fields_meta_box() {
        $config = get_option('project_custom_fields_config', array());
        
        if (!empty($config)) {
            add_meta_box(
                'project_custom_fields_meta',
                '🏷️ Proje Bilgileri',
                array($this, 'custom_fields_meta_box'),
                'proje',
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Özel alanlar meta box içeriği
     */
    public function custom_fields_meta_box($post) {
        wp_nonce_field('project_custom_fields_meta', 'project_custom_fields_nonce');
        
        $config = get_option('project_custom_fields_config', array());
        ?>
        <div class="custom-fields-meta-box">
            <?php foreach ($config as $field): ?>
                <div class="custom-field-row">
                    <label for="project_<?php echo esc_attr($field['key']); ?>">
                        <strong><?php echo esc_html($field['label']); ?></strong>
                    </label>
                    <?php
                    $field_name = "_project_{$field['key']}";
                    $field_value = get_post_meta($post->ID, $field_name, true);
                    $field_id = "project_{$field['key']}";
                    
                    switch ($field['type']) {
                        case 'textarea':
                            echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" rows="3" style="width: 100%;">' . esc_textarea($field_value) . '</textarea>';
                            break;
                        case 'number':
                            echo '<input type="number" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" style="width: 100%;" />';
                            break;
                        case 'date':
                            echo '<input type="date" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" style="width: 100%;" />';
                            break;
                        case 'url':
                            echo '<input type="url" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_url($field_value) . '" style="width: 100%;" placeholder="https://" />';
                            break;
                        case 'email':
                            echo '<input type="email" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" style="width: 100%;" />';
                            break;
                        default:
                            echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" style="width: 100%;" />';
                            break;
                    }
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <style>
        .custom-fields-meta-box .custom-field-row {
            margin-bottom: 15px;
        }
        
        .custom-fields-meta-box label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .custom-fields-meta-box input,
        .custom-fields-meta-box textarea {
            font-size: 13px;
        }
        </style>
        <?php
    }
    
    /**
     * Özel alanları kaydet
     */
    public function save_custom_fields($post_id) {
        // Verify nonce
        if (!isset($_POST['project_custom_fields_nonce']) || 
            !wp_verify_nonce($_POST['project_custom_fields_nonce'], 'project_custom_fields_meta')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check post type
        if (get_post_type($post_id) !== 'proje') {
            return;
        }
        
        $config = get_option('project_custom_fields_config', array());
        
        foreach ($config as $field) {
            $field_name = "_project_{$field['key']}";
            
            if (isset($_POST[$field_name])) {
                $value = $_POST[$field_name];
                
                // Sanitize based on field type
                switch ($field['type']) {
                    case 'url':
                        $value = esc_url_raw($value);
                        break;
                    case 'email':
                        $value = sanitize_email($value);
                        break;
                    case 'number':
                        $value = intval($value);
                        break;
                    case 'textarea':
                        $value = sanitize_textarea_field($value);
                        break;
                    default:
                        $value = sanitize_text_field($value);
                        break;
                }
                
                if (!empty($value)) {
                    update_post_meta($post_id, $field_name, $value);
                } else {
                    delete_post_meta($post_id, $field_name);
                }
            }
        }
    }
    
    /**
     * Özel alanları al
     */
    public static function get_project_custom_fields($post_id) {
        $config = get_option('project_custom_fields_config', array());
        $fields = array();
        
        foreach ($config as $field) {
            $field_name = "_project_{$field['key']}";
            $value = get_post_meta($post_id, $field_name, true);
            
            if (!empty($value)) {
                $fields[] = array(
                    'label' => $field['label'],
                    'value' => $value,
                    'type' => $field['type'],
                    'key' => $field['key']
                );
            }
        }
        
        return $fields;
    }
}

// Initialize the class
new ProjectGalleryCustomFields();