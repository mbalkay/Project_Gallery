<?php
/**
 * Project Gallery Video Support System
 * Advanced video integration for project galleries
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProjectGalleryVideo {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_video_meta_box'));
        add_action('save_post', array($this, 'save_video_meta'));
        add_action('wp_ajax_get_video_thumbnail', array($this, 'get_video_thumbnail'));
        add_action('wp_ajax_validate_video_url', array($this, 'validate_video_url'));
        add_shortcode('proje_video_galerisi', array($this, 'video_gallery_shortcode'));
    }
    
    /**
     * Add video meta box
     */
    public function add_video_meta_box() {
        add_meta_box(
            'project_video_gallery',
            '🎬 Proje Video Galerisi',
            array($this, 'video_meta_box_callback'),
            'proje',
            'normal',
            'high'
        );
    }
    
    /**
     * Video meta box callback
     */
    public function video_meta_box_callback($post) {
        wp_nonce_field('project_video_meta_nonce', 'project_video_meta_nonce');
        
        $videos = get_post_meta($post->ID, '_project_videos', true);
        $videos = $videos ? json_decode($videos, true) : array();
        
        ?>
        <div id="project-video-gallery-container">
            <div class="video-gallery-header">
                <p class="description">
                    🎥 YouTube, Vimeo, MP4 video dosyaları ve diğer video kaynaklarını ekleyebilirsiniz.
                </p>
                <button type="button" class="button button-primary" id="add-video-btn">
                    ➕ Video Ekle
                </button>
                <button type="button" class="button" id="clear-all-videos-btn">
                    🗑️ Tümünü Temizle
                </button>
            </div>
            
            <div id="video-gallery-list" class="video-gallery-list">
                <?php if (!empty($videos)): ?>
                    <?php foreach ($videos as $index => $video): ?>
                        <div class="video-item" data-index="<?php echo $index; ?>">
                            <?php echo $this->render_video_item($video, $index); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <input type="hidden" id="project-videos-data" name="project_videos" value="<?php echo esc_attr(json_encode($videos)); ?>">
        </div>
        
        <!-- Video Add Modal -->
        <div id="video-add-modal" class="video-modal" style="display: none;">
            <div class="video-modal-content">
                <div class="video-modal-header">
                    <h3>🎬 Video Ekle</h3>
                    <span class="video-modal-close">&times;</span>
                </div>
                <div class="video-modal-body">
                    <div class="video-input-group">
                        <label>Video Türü:</label>
                        <select id="video-type-select">
                            <option value="youtube">📺 YouTube</option>
                            <option value="vimeo">🎭 Vimeo</option>
                            <option value="mp4">📹 MP4 Dosya</option>
                            <option value="embed">🔗 Embed Kodu</option>
                        </select>
                    </div>
                    
                    <div class="video-input-group" id="video-url-group">
                        <label>Video URL:</label>
                        <input type="text" id="video-url-input" placeholder="https://www.youtube.com/watch?v=...">
                        <button type="button" class="button" id="validate-video-btn">✅ Kontrol Et</button>
                    </div>
                    
                    <div class="video-input-group" id="video-file-group" style="display: none;">
                        <label>Video Dosyası:</label>
                        <button type="button" class="button" id="upload-video-btn">📁 Dosya Seç</button>
                        <span id="selected-video-file"></span>
                    </div>
                    
                    <div class="video-input-group" id="video-embed-group" style="display: none;">
                        <label>Embed Kodu:</label>
                        <textarea id="video-embed-input" placeholder="<iframe src=..."></textarea>
                    </div>
                    
                    <div class="video-input-group">
                        <label>Video Başlığı:</label>
                        <input type="text" id="video-title-input" placeholder="Video başlığı...">
                    </div>
                    
                    <div class="video-input-group">
                        <label>Video Açıklaması:</label>
                        <textarea id="video-description-input" placeholder="Video açıklaması..."></textarea>
                    </div>
                    
                    <div class="video-preview" id="video-preview" style="display: none;">
                        <h4>👁️ Önizleme:</h4>
                        <div id="video-preview-content"></div>
                    </div>
                </div>
                <div class="video-modal-footer">
                    <button type="button" class="button button-primary" id="save-video-btn">💾 Video Ekle</button>
                    <button type="button" class="button" id="cancel-video-btn">❌ İptal</button>
                </div>
            </div>
        </div>
        
        <style>
        .video-gallery-list {
            margin-top: 15px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .video-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #f9f9f9;
            position: relative;
        }
        
        .video-item:hover {
            border-color: #0073aa;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .video-thumbnail {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .video-info h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            font-weight: 600;
        }
        
        .video-info p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        
        .video-controls {
            margin-top: 10px;
            display: flex;
            gap: 5px;
        }
        
        .video-controls button {
            font-size: 11px;
            padding: 3px 8px;
        }
        
        .video-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 100000;
        }
        
        .video-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 90%;
            overflow-y: auto;
        }
        
        .video-modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .video-modal-close {
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .video-modal-body {
            padding: 20px;
        }
        
        .video-input-group {
            margin-bottom: 15px;
        }
        
        .video-input-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .video-input-group input,
        .video-input-group select,
        .video-input-group textarea {
            width: 100%;
            max-width: 100%;
        }
        
        .video-preview {
            margin-top: 20px;
            padding: 15px;
            background: #f0f0f0;
            border-radius: 5px;
        }
        
        .video-modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            text-align: right;
        }
        
        .video-modal-footer .button {
            margin-left: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * Render video item
     */
    private function render_video_item($video, $index) {
        $thumbnail = $this->get_video_thumbnail_url($video);
        $type_icon = $this->get_video_type_icon($video['type']);
        
        ob_start();
        ?>
        <div class="video-thumbnail-container">
            <?php if ($thumbnail): ?>
                <img src="<?php echo esc_url($thumbnail); ?>" alt="Video Thumbnail" class="video-thumbnail">
            <?php else: ?>
                <div class="video-thumbnail video-placeholder">
                    <span style="font-size: 48px;"><?php echo $type_icon; ?></span>
                </div>
            <?php endif; ?>
            <div class="video-play-overlay">▶️</div>
        </div>
        
        <div class="video-info">
            <h4><?php echo $type_icon; ?> <?php echo esc_html($video['title'] ?: 'Untitled Video'); ?></h4>
            <p class="video-type"><?php echo esc_html(ucfirst($video['type'])); ?></p>
            <?php if (!empty($video['description'])): ?>
                <p class="video-description"><?php echo esc_html(wp_trim_words($video['description'], 15)); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="video-controls">
            <button type="button" class="button button-small edit-video-btn" data-index="<?php echo $index; ?>">
                ✏️ Düzenle
            </button>
            <button type="button" class="button button-small delete-video-btn" data-index="<?php echo $index; ?>">
                🗑️ Sil
            </button>
            <button type="button" class="button button-small preview-video-btn" data-index="<?php echo $index; ?>">
                👁️ Önizle
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get video type icon
     */
    private function get_video_type_icon($type) {
        $icons = array(
            'youtube' => '📺',
            'vimeo' => '🎭',
            'mp4' => '📹',
            'embed' => '🎬'
        );
        
        return $icons[$type] ?? '🎥';
    }
    
    /**
     * Save video meta
     */
    public function save_video_meta($post_id) {
        if (!isset($_POST['project_video_meta_nonce']) || 
            !wp_verify_nonce($_POST['project_video_meta_nonce'], 'project_video_meta_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['project_videos'])) {
            $videos = stripslashes($_POST['project_videos']);
            $videos_array = json_decode($videos, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                update_post_meta($post_id, '_project_videos', $videos);
            }
        }
    }
    
    /**
     * Get video thumbnail via AJAX
     */
    public function get_video_thumbnail() {
        check_ajax_referer('project_gallery_nonce', 'nonce');
        
        $video_url = sanitize_text_field($_POST['video_url']);
        $video_type = sanitize_text_field($_POST['video_type']);
        
        $thumbnail_url = $this->extract_video_thumbnail($video_url, $video_type);
        $video_info = $this->extract_video_info($video_url, $video_type);
        
        wp_send_json_success(array(
            'thumbnail' => $thumbnail_url,
            'info' => $video_info
        ));
    }
    
    /**
     * Validate video URL via AJAX
     */
    public function validate_video_url() {
        check_ajax_referer('project_gallery_nonce', 'nonce');
        
        $video_url = sanitize_text_field($_POST['video_url']);
        $video_type = sanitize_text_field($_POST['video_type']);
        
        $is_valid = $this->validate_video_source($video_url, $video_type);
        
        if ($is_valid) {
            $video_info = $this->extract_video_info($video_url, $video_type);
            wp_send_json_success($video_info);
        } else {
            wp_send_json_error('Invalid video URL or unsupported format.');
        }
    }
    
    /**
     * Extract video thumbnail
     */
    private function extract_video_thumbnail($url, $type) {
        switch ($type) {
            case 'youtube':
                if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
                    return 'https://img.youtube.com/vi/' . $matches[1] . '/maxresdefault.jpg';
                }
                break;
                
            case 'vimeo':
                if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
                    $vimeo_data = wp_remote_get("https://vimeo.com/api/v2/video/{$matches[1]}.json");
                    if (!is_wp_error($vimeo_data)) {
                        $data = json_decode(wp_remote_retrieve_body($vimeo_data), true);
                        return $data[0]['thumbnail_large'] ?? null;
                    }
                }
                break;
        }
        
        return null;
    }
    
    /**
     * Extract video info
     */
    private function extract_video_info($url, $type) {
        switch ($type) {
            case 'youtube':
                if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
                    return array(
                        'id' => $matches[1],
                        'title' => $this->get_youtube_title($matches[1]),
                        'embed_url' => "https://www.youtube.com/embed/{$matches[1]}"
                    );
                }
                break;
                
            case 'vimeo':
                if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
                    $vimeo_data = wp_remote_get("https://vimeo.com/api/v2/video/{$matches[1]}.json");
                    if (!is_wp_error($vimeo_data)) {
                        $data = json_decode(wp_remote_retrieve_body($vimeo_data), true);
                        return array(
                            'id' => $matches[1],
                            'title' => $data[0]['title'] ?? 'Vimeo Video',
                            'embed_url' => "https://player.vimeo.com/video/{$matches[1]}"
                        );
                    }
                }
                break;
                
            case 'mp4':
                return array(
                    'id' => basename($url),
                    'title' => basename($url),
                    'direct_url' => $url
                );
        }
        
        return array();
    }
    
    /**
     * Get YouTube video title
     */
    private function get_youtube_title($video_id) {
        $api_url = "https://www.googleapis.com/youtube/v3/videos?id={$video_id}&key=YOUR_API_KEY&part=snippet";
        // For demo purposes, return a generic title
        return "YouTube Video";
    }
    
    /**
     * Validate video source
     */
    private function validate_video_source($url, $type) {
        switch ($type) {
            case 'youtube':
                return preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url);
                
            case 'vimeo':
                return preg_match('/vimeo\.com\/(\d+)/', $url);
                
            case 'mp4':
                return filter_var($url, FILTER_VALIDATE_URL) && pathinfo($url, PATHINFO_EXTENSION) === 'mp4';
                
            default:
                return false;
        }
    }
    
    /**
     * Get video thumbnail URL from video data
     */
    private function get_video_thumbnail_url($video) {
        if (!empty($video['thumbnail'])) {
            return $video['thumbnail'];
        }
        
        return $this->extract_video_thumbnail($video['url'], $video['type']);
    }
    
    /**
     * Video gallery shortcode
     */
    public function video_gallery_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'columns' => 3,
            'autoplay' => false,
            'controls' => true,
            'muted' => false
        ), $atts);
        
        if (!$atts['id']) {
            return '<p>Video gallery project ID required.</p>';
        }
        
        $videos = get_post_meta($atts['id'], '_project_videos', true);
        $videos = $videos ? json_decode($videos, true) : array();
        
        if (empty($videos)) {
            return '<p>No videos found for this project.</p>';
        }
        
        ob_start();
        ?>
        <div class="project-video-gallery columns-<?php echo intval($atts['columns']); ?>">
            <?php foreach ($videos as $index => $video): ?>
                <div class="video-gallery-item" data-video-index="<?php echo $index; ?>">
                    <div class="video-thumbnail-container">
                        <?php $thumbnail = $this->get_video_thumbnail_url($video); ?>
                        <?php if ($thumbnail): ?>
                            <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($video['title']); ?>" class="video-thumbnail">
                        <?php endif; ?>
                        <div class="video-play-overlay">
                            <span class="play-icon">▶️</span>
                        </div>
                    </div>
                    <div class="video-info">
                        <h3 class="video-title"><?php echo esc_html($video['title']); ?></h3>
                        <?php if (!empty($video['description'])): ?>
                            <p class="video-description"><?php echo esc_html($video['description']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Video Modal -->
        <div id="video-lightbox-modal" class="video-lightbox-modal" style="display: none;">
            <div class="video-lightbox-content">
                <div class="video-lightbox-header">
                    <span class="video-lightbox-close">&times;</span>
                </div>
                <div class="video-lightbox-body">
                    <div id="video-player-container"></div>
                </div>
                <div class="video-lightbox-footer">
                    <button type="button" class="video-nav-btn" id="prev-video-btn">◀ Önceki</button>
                    <span id="video-counter"></span>
                    <button type="button" class="video-nav-btn" id="next-video-btn">Sonraki ▶</button>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        (function($) {
            var videos = <?php echo json_encode($videos); ?>;
            var currentVideoIndex = 0;
            
            $('.video-gallery-item').on('click', function() {
                currentVideoIndex = parseInt($(this).data('video-index'));
                openVideoModal();
            });
            
            function openVideoModal() {
                var video = videos[currentVideoIndex];
                var playerHtml = generateVideoPlayer(video);
                
                $('#video-player-container').html(playerHtml);
                $('#video-counter').text((currentVideoIndex + 1) + '/' + videos.length);
                $('#video-lightbox-modal').show();
            }
            
            function generateVideoPlayer(video) {
                switch(video.type) {
                    case 'youtube':
                        return '<iframe width="100%" height="400" src="' + video.embed_url + '?autoplay=1" frameborder="0" allowfullscreen></iframe>';
                    case 'vimeo':
                        return '<iframe width="100%" height="400" src="' + video.embed_url + '?autoplay=1" frameborder="0" allowfullscreen></iframe>';
                    case 'mp4':
                        return '<video width="100%" height="400" controls autoplay><source src="' + video.url + '" type="video/mp4"></video>';
                    case 'embed':
                        return video.embed_code;
                    default:
                        return '<p>Unsupported video format</p>';
                }
            }
            
            $('.video-lightbox-close').on('click', function() {
                $('#video-lightbox-modal').hide();
                $('#video-player-container').empty();
            });
            
            $('#prev-video-btn').on('click', function() {
                currentVideoIndex = currentVideoIndex > 0 ? currentVideoIndex - 1 : videos.length - 1;
                openVideoModal();
            });
            
            $('#next-video-btn').on('click', function() {
                currentVideoIndex = currentVideoIndex < videos.length - 1 ? currentVideoIndex + 1 : 0;
                openVideoModal();
            });
            
            $(document).on('keydown', function(e) {
                if ($('#video-lightbox-modal').is(':visible')) {
                    if (e.key === 'Escape') {
                        $('.video-lightbox-close').click();
                    } else if (e.key === 'ArrowLeft') {
                        $('#prev-video-btn').click();
                    } else if (e.key === 'ArrowRight') {
                        $('#next-video-btn').click();
                    }
                }
            });
        })(jQuery);
        </script>
        
        <style>
        .project-video-gallery {
            display: grid;
            gap: 20px;
            margin: 20px 0;
        }
        
        .project-video-gallery.columns-1 { grid-template-columns: 1fr; }
        .project-video-gallery.columns-2 { grid-template-columns: repeat(2, 1fr); }
        .project-video-gallery.columns-3 { grid-template-columns: repeat(3, 1fr); }
        .project-video-gallery.columns-4 { grid-template-columns: repeat(4, 1fr); }
        
        .video-gallery-item {
            cursor: pointer;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .video-gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .video-thumbnail-container {
            position: relative;
            overflow: hidden;
        }
        
        .video-thumbnail {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .video-gallery-item:hover .video-thumbnail {
            transform: scale(1.05);
        }
        
        .video-play-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.7);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .video-gallery-item:hover .video-play-overlay {
            background: rgba(0,0,0,0.9);
            transform: translate(-50%, -50%) scale(1.1);
        }
        
        .play-icon {
            font-size: 24px;
            color: white;
        }
        
        .video-info {
            padding: 15px;
            background: white;
        }
        
        .video-title {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .video-description {
            margin: 0;
            font-size: 14px;
            color: #666;
        }
        
        .video-lightbox-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .video-lightbox-content {
            width: 90%;
            max-width: 800px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .video-lightbox-header {
            padding: 10px 15px;
            background: #f0f0f0;
            text-align: right;
        }
        
        .video-lightbox-close {
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .video-lightbox-body {
            padding: 0;
        }
        
        .video-lightbox-footer {
            padding: 15px;
            background: #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .video-nav-btn {
            background: #0073aa;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .project-video-gallery.columns-3,
            .project-video-gallery.columns-4 {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .project-video-gallery {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
}