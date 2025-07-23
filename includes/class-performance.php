<?php
/**
 * Project Gallery Performance Optimization System
 * Advanced caching, lazy loading, and performance features
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProjectGalleryPerformance {
    
    private $cache_duration = 3600; // 1 hour
    
    public function __construct() {
        add_action('init', array($this, 'init_performance_features'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_performance_scripts'));
        add_action('wp_head', array($this, 'add_performance_meta_tags'));
        add_action('wp_footer', array($this, 'add_progressive_loading_script'));
        add_filter('wp_get_attachment_image_attributes', array($this, 'add_lazy_loading_attributes'), 10, 3);
        add_action('wp_ajax_load_more_projects', array($this, 'load_more_projects'));
        add_action('wp_ajax_nopriv_load_more_projects', array($this, 'load_more_projects'));
        add_action('wp_ajax_preload_images', array($this, 'preload_images'));
        add_action('wp_ajax_nopriv_preload_images', array($this, 'preload_images'));
    }
    
    /**
     * Initialize performance features
     */
    public function init_performance_features() {
        // Enable WebP support
        add_filter('wp_generate_attachment_metadata', array($this, 'generate_webp_versions'));
        
        // Enable progressive JPEG
        add_filter('jpeg_quality', array($this, 'set_progressive_jpeg_quality'));
        
        // Add custom image sizes for performance
        add_image_size('project_thumb_webp', 300, 200, true);
        add_image_size('project_medium_webp', 600, 400, true);
        add_image_size('project_large_webp', 1200, 800, true);
    }
    
    /**
     * Enqueue performance optimization scripts
     */
    public function enqueue_performance_scripts() {
        // Intersection Observer for lazy loading
        wp_enqueue_script(
            'project-gallery-performance',
            PROJECT_GALLERY_PLUGIN_URL . 'assets/js/performance.js',
            array('jquery'),
            PROJECT_GALLERY_VERSION,
            true
        );
        
        wp_localize_script('project-gallery-performance', 'projectGalleryPerf', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('project_gallery_perf_nonce'),
            'lazy_loading' => get_option('project_gallery_lazy_loading', true),
            'progressive_loading' => get_option('project_gallery_progressive_loading', true),
            'preload_threshold' => get_option('project_gallery_preload_threshold', 0.1),
            'batch_size' => get_option('project_gallery_batch_size', 6)
        ));
    }
    
    /**
     * Add performance meta tags
     */
    public function add_performance_meta_tags() {
        if (is_singular('proje') || is_post_type_archive('proje')) {
            echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' . "\n";
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
            echo '<link rel="dns-prefetch" href="//images.unsplash.com">' . "\n";
            echo '<link rel="dns-prefetch" href="//cdn.jsdelivr.net">' . "\n";
        }
    }
    
    /**
     * Generate WebP versions of images
     */
    public function generate_webp_versions($metadata) {
        if (!function_exists('imagewebp')) {
            return $metadata;
        }
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $metadata['file'];
        
        if (file_exists($file_path)) {
            $this->create_webp_version($file_path);
            
            // Create WebP versions for all sizes
            if (isset($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size => $data) {
                    $size_path = dirname($file_path) . '/' . $data['file'];
                    if (file_exists($size_path)) {
                        $this->create_webp_version($size_path);
                    }
                }
            }
        }
        
        return $metadata;
    }
    
    /**
     * Create WebP version of image
     */
    private function create_webp_version($file_path) {
        $info = pathinfo($file_path);
        $webp_path = $info['dirname'] . '/' . $info['filename'] . '.webp';
        
        if (file_exists($webp_path)) {
            return; // WebP version already exists
        }
        
        $extension = strtolower($info['extension']);
        $image = null;
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($file_path);
                break;
            case 'png':
                $image = imagecreatefrompng($file_path);
                break;
            case 'gif':
                $image = imagecreatefromgif($file_path);
                break;
        }
        
        if ($image) {
            imagewebp($image, $webp_path, 85); // 85% quality
            imagedestroy($image);
        }
    }
    
    /**
     * Set progressive JPEG quality
     */
    public function set_progressive_jpeg_quality($quality) {
        return 85; // Optimized quality for progressive JPEG
    }
    
    /**
     * Add lazy loading attributes
     */
    public function add_lazy_loading_attributes($attr, $attachment, $size) {
        if (is_admin()) {
            return $attr;
        }
        
        $lazy_loading = get_option('project_gallery_lazy_loading', true);
        
        if ($lazy_loading && strpos($attr['class'], 'project-gallery') !== false) {
            $attr['loading'] = 'lazy';
            $attr['data-src'] = $attr['src'];
            $attr['src'] = $this->get_placeholder_image();
            $attr['class'] .= ' lazy-load';
        }
        
        return $attr;
    }
    
    /**
     * Get placeholder image
     */
    private function get_placeholder_image() {
        // Generate a lightweight SVG placeholder
        $svg = '<svg width="400" height="300" xmlns="http://www.w3.org/2000/svg">
                  <rect width="100%" height="100%" fill="#f0f0f0"/>
                  <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="14" fill="#999" text-anchor="middle" dy=".3em">Loading...</text>
                </svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
    
    /**
     * Load more projects via AJAX
     */
    public function load_more_projects() {
        check_ajax_referer('project_gallery_perf_nonce', 'nonce');
        
        $page = intval($_POST['page']);
        $posts_per_page = intval($_POST['posts_per_page']) ?: 6;
        $category = sanitize_text_field($_POST['category'] ?? '');
        
        $args = array(
            'post_type' => 'proje',
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            'paged' => $page,
            'meta_query' => array(
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        if (!empty($category)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'proje-kategori',
                    'field' => 'slug',
                    'terms' => $category
                )
            );
        }
        
        $query = new WP_Query($args);
        $projects = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $projects[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'excerpt' => get_the_excerpt(),
                    'permalink' => get_permalink(),
                    'featured_image' => get_the_post_thumbnail_url(get_the_ID(), 'project_medium_webp'),
                    'featured_image_fallback' => get_the_post_thumbnail_url(get_the_ID(), 'medium'),
                    'categories' => wp_get_post_terms(get_the_ID(), 'proje-kategori', array('fields' => 'names'))
                );
            }
            wp_reset_postdata();
        }
        
        wp_send_json_success(array(
            'projects' => $projects,
            'has_more' => $page < $query->max_num_pages
        ));
    }
    
    /**
     * Preload images for better UX
     */
    public function preload_images() {
        check_ajax_referer('project_gallery_perf_nonce', 'nonce');
        
        $image_urls = array_map('sanitize_text_field', $_POST['image_urls'] ?? array());
        $preloaded = array();
        
        foreach ($image_urls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $preloaded[] = $url;
            }
        }
        
        wp_send_json_success(array(
            'preloaded' => $preloaded,
            'count' => count($preloaded)
        ));
    }
    
    /**
     * Add progressive loading script
     */
    public function add_progressive_loading_script() {
        if (!is_singular('proje') && !is_post_type_archive('proje')) {
            return;
        }
        
        ?>
        <script>
        // Progressive Enhancement for Project Gallery
        (function() {
            'use strict';
            
            // Feature detection
            const supportsWebP = () => {
                const canvas = document.createElement('canvas');
                canvas.width = 1;
                canvas.height = 1;
                return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
            };
            
            const supportsIntersectionObserver = 'IntersectionObserver' in window;
            const supportsRequestIdleCallback = 'requestIdleCallback' in window;
            
            // Add feature detection classes
            document.documentElement.classList.add(supportsWebP() ? 'webp' : 'no-webp');
            document.documentElement.classList.add(supportsIntersectionObserver ? 'intersection-observer' : 'no-intersection-observer');
            
            // Lazy loading fallback for older browsers
            if (!supportsIntersectionObserver) {
                const lazyImages = document.querySelectorAll('.lazy-load');
                
                const lazyLoad = () => {
                    lazyImages.forEach(img => {
                        if (img.getBoundingClientRect().top < window.innerHeight + 100) {
                            img.src = img.dataset.src;
                            img.classList.remove('lazy-load');
                        }
                    });
                };
                
                window.addEventListener('scroll', lazyLoad);
                window.addEventListener('resize', lazyLoad);
                lazyLoad(); // Initial check
            }
            
            // Performance monitoring
            if ('performance' in window && 'timing' in performance) {
                window.addEventListener('load', () => {
                    const timing = performance.timing;
                    const loadTime = timing.loadEventEnd - timing.navigationStart;
                    
                    // Send performance data to analytics (if available)
                    if (typeof gtag !== 'undefined') {
                        gtag('event', 'timing_complete', {
                            'name': 'page_load_time',
                            'value': loadTime
                        });
                    }
                });
            }
            
            // Preload critical images
            const preloadCriticalImages = () => {
                const criticalImages = document.querySelectorAll('[data-critical="true"]');
                
                criticalImages.forEach(img => {
                    const link = document.createElement('link');
                    link.rel = 'preload';
                    link.as = 'image';
                    link.href = img.src || img.dataset.src;
                    document.head.appendChild(link);
                });
            };
            
            // Progressive image enhancement
            const enhanceImages = () => {
                const images = document.querySelectorAll('.project-gallery img');
                
                images.forEach(img => {
                    // Add loading indicator
                    img.addEventListener('loadstart', () => {
                        img.classList.add('loading');
                    });
                    
                    // Remove loading indicator when loaded
                    img.addEventListener('load', () => {
                        img.classList.remove('loading');
                        img.classList.add('loaded');
                    });
                    
                    // Handle loading errors
                    img.addEventListener('error', () => {
                        img.classList.remove('loading');
                        img.classList.add('error');
                        
                        // Fallback to original format if WebP fails
                        if (img.src.includes('.webp')) {
                            img.src = img.src.replace('.webp', '.jpg');
                        }
                    });
                });
            };
            
            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    preloadCriticalImages();
                    enhanceImages();
                });
            } else {
                preloadCriticalImages();
                enhanceImages();
            }
            
            // Idle callback optimization
            const idleCallback = supportsRequestIdleCallback ? requestIdleCallback : setTimeout;
            
            idleCallback(() => {
                // Non-critical optimizations
                console.log('Project Gallery: Performance optimizations loaded');
            });
            
        })();
        </script>
        
        <style>
        /* Progressive enhancement styles */
        .project-gallery img.loading {
            opacity: 0.5;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading-shimmer 1.5s infinite;
        }
        
        .project-gallery img.loaded {
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .project-gallery img.error {
            opacity: 0.6;
            filter: grayscale(100%);
        }
        
        @keyframes loading-shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        /* WebP support styles */
        .webp .project-gallery img[data-webp] {
            background-image: attr(data-webp url);
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .project-gallery img.loading {
                animation: none;
            }
            
            .project-gallery * {
                transition: none !important;
                animation: none !important;
            }
        }
        
        /* High contrast support */
        @media (prefers-contrast: high) {
            .project-gallery {
                filter: contrast(1.2);
            }
        }
        
        /* Connection-aware loading */
        @media (prefers-reduced-data: reduce) {
            .project-gallery img {
                image-rendering: optimizeSpeed;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Get cached project data
     */
    public function get_cached_project_data($project_id, $force_refresh = false) {
        $cache_key = 'project_gallery_' . $project_id;
        
        if (!$force_refresh) {
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        // Generate fresh data
        $project_data = array(
            'id' => $project_id,
            'title' => get_the_title($project_id),
            'content' => get_post_field('post_content', $project_id),
            'featured_image' => get_the_post_thumbnail_url($project_id, 'full'),
            'gallery_images' => $this->get_optimized_gallery_images($project_id),
            'categories' => wp_get_post_terms($project_id, 'proje-kategori'),
            'last_modified' => get_post_modified_time('U', false, $project_id)
        );
        
        // Cache for 1 hour
        set_transient($cache_key, $project_data, $this->cache_duration);
        
        return $project_data;
    }
    
    /**
     * Get optimized gallery images
     */
    private function get_optimized_gallery_images($project_id) {
        $gallery_images = get_post_meta($project_id, '_project_gallery_images', true);
        
        if (!$gallery_images) {
            return array();
        }
        
        $image_ids = explode(',', $gallery_images);
        $optimized_images = array();
        
        foreach ($image_ids as $image_id) {
            $image_id = intval(trim($image_id));
            
            if ($image_id) {
                $optimized_images[] = array(
                    'id' => $image_id,
                    'url' => wp_get_attachment_url($image_id),
                    'thumbnail' => wp_get_attachment_image_url($image_id, 'project_thumb_webp'),
                    'medium' => wp_get_attachment_image_url($image_id, 'project_medium_webp'),
                    'large' => wp_get_attachment_image_url($image_id, 'project_large_webp'),
                    'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
                    'caption' => wp_get_attachment_caption($image_id),
                    'sizes' => wp_get_attachment_metadata($image_id)
                );
            }
        }
        
        return $optimized_images;
    }
    
    /**
     * Clear project cache
     */
    public function clear_project_cache($project_id) {
        delete_transient('project_gallery_' . $project_id);
    }
    
    /**
     * Clear all gallery cache
     */
    public function clear_all_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_project_gallery_%' 
             OR option_name LIKE '_transient_timeout_project_gallery_%'"
        );
    }
}