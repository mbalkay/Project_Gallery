<?php
/**
 * Project Gallery Advanced Search and Filter System
 * Intelligent search, filtering, and recommendation engine
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProjectGallerySearch {
    
    public function __construct() {
        add_action('init', array($this, 'init_search_features'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_search_scripts'));
        add_action('wp_ajax_advanced_search', array($this, 'ajax_advanced_search'));
        add_action('wp_ajax_nopriv_advanced_search', array($this, 'ajax_advanced_search'));
        add_action('wp_ajax_get_search_suggestions', array($this, 'get_search_suggestions'));
        add_action('wp_ajax_nopriv_get_search_suggestions', array($this, 'get_search_suggestions'));
        add_action('wp_ajax_save_search_preference', array($this, 'save_search_preference'));
        add_action('wp_ajax_get_recommendations', array($this, 'get_recommendations'));
        add_action('wp_ajax_nopriv_get_recommendations', array($this, 'get_recommendations'));
        add_shortcode('project_search', array($this, 'search_widget_shortcode'));
        add_shortcode('project_filters', array($this, 'filter_widget_shortcode'));
    }
    
    /**
     * Initialize search features
     */
    public function init_search_features() {
        // Create search index table if needed
        $this->create_search_index();
        
        // Hook into post save to update search index
        add_action('save_post', array($this, 'update_search_index'));
        add_action('delete_post', array($this, 'remove_from_search_index'));
    }
    
    /**
     * Create search index table
     */
    private function create_search_index() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'project_gallery_search_index';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            title text NOT NULL,
            content text,
            excerpt text,
            categories text,
            tags text,
            meta_keywords text,
            search_vector text,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            FULLTEXT KEY search_content (title, content, excerpt, categories, tags, meta_keywords),
            KEY last_updated (last_updated)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Enqueue search scripts
     */
    public function enqueue_search_scripts() {
        wp_enqueue_script(
            'project-gallery-search',
            PROJECT_GALLERY_PLUGIN_URL . 'assets/js/search.js',
            array('jquery'),
            PROJECT_GALLERY_VERSION,
            true
        );
        
        wp_localize_script('project-gallery-search', 'projectGallerySearch', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('project_gallery_search_nonce'),
            'strings' => array(
                'searching' => __('Searching...', 'project-gallery'),
                'no_results' => __('No projects found.', 'project-gallery'),
                'load_more' => __('Load More', 'project-gallery'),
                'showing_results' => __('Showing %d of %d results', 'project-gallery'),
                'search_placeholder' => __('Search projects...', 'project-gallery'),
                'filter_by_category' => __('Filter by category', 'project-gallery'),
                'sort_by' => __('Sort by', 'project-gallery')
            ),
            'settings' => array(
                'enable_autocomplete' => get_option('project_gallery_enable_autocomplete', true),
                'min_search_length' => get_option('project_gallery_min_search_length', 3),
                'results_per_page' => get_option('project_gallery_search_results_per_page', 12),
                'enable_fuzzy_search' => get_option('project_gallery_enable_fuzzy_search', true)
            )
        ));
    }
    
    /**
     * Advanced search AJAX handler
     */
    public function ajax_advanced_search() {
        check_ajax_referer('project_gallery_search_nonce', 'nonce');
        
        $search_query = sanitize_text_field($_POST['query'] ?? '');
        $categories = array_map('intval', $_POST['categories'] ?? array());
        $sort_by = sanitize_text_field($_POST['sort_by'] ?? 'relevance');
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 12);
        $filters = array(
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
            'author' => intval($_POST['author'] ?? 0),
            'has_featured_image' => filter_var($_POST['has_featured_image'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'min_images' => intval($_POST['min_images'] ?? 0)
        );
        
        try {
            $results = $this->perform_advanced_search($search_query, $categories, $sort_by, $page, $per_page, $filters);
            
            // Track search query for analytics
            $this->track_search_query($search_query, count($results['projects']));
            
            wp_send_json_success($results);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Perform advanced search
     */
    private function perform_advanced_search($query, $categories, $sort_by, $page, $per_page, $filters) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        $search_table = $wpdb->prefix . 'project_gallery_search_index';
        
        // Build the search query
        $where_conditions = array("p.post_type = 'proje'", "p.post_status = 'publish'");
        $join_clauses = array();
        $orderby_clause = '';
        
        // Text search
        if (!empty($query)) {
            if (get_option('project_gallery_enable_fuzzy_search', true)) {
                // Use FULLTEXT search for better relevance
                $query_escaped = esc_sql($query);
                $join_clauses[] = "INNER JOIN $search_table si ON p.ID = si.post_id";
                $where_conditions[] = "MATCH(si.title, si.content, si.excerpt, si.categories, si.tags) AGAINST('$query_escaped' IN NATURAL LANGUAGE MODE)";
                
                if ($sort_by === 'relevance') {
                    $orderby_clause = "ORDER BY MATCH(si.title, si.content, si.excerpt, si.categories, si.tags) AGAINST('$query_escaped' IN NATURAL LANGUAGE MODE) DESC";
                }
            } else {
                // Fallback to LIKE search
                $like_query = '%' . $wpdb->esc_like($query) . '%';
                $where_conditions[] = "(p.post_title LIKE %s OR p.post_content LIKE %s OR p.post_excerpt LIKE %s)";
            }
        }
        
        // Category filter
        if (!empty($categories)) {
            $category_ids = implode(',', array_map('intval', $categories));
            $join_clauses[] = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
            $join_clauses[] = "INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
            $where_conditions[] = "tt.taxonomy = 'proje-kategori' AND tt.term_id IN ($category_ids)";
        }
        
        // Date filters
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "p.post_date >= '" . esc_sql($filters['date_from']) . "'";
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "p.post_date <= '" . esc_sql($filters['date_to']) . " 23:59:59'";
        }
        
        // Author filter
        if (!empty($filters['author'])) {
            $where_conditions[] = "p.post_author = " . intval($filters['author']);
        }
        
        // Featured image filter
        if ($filters['has_featured_image']) {
            $join_clauses[] = "INNER JOIN {$wpdb->postmeta} pm_thumb ON p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id'";
        }
        
        // Minimum images filter
        if ($filters['min_images'] > 0) {
            $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} pm_gallery ON p.ID = pm_gallery.post_id AND pm_gallery.meta_key = '_project_gallery_images'";
            $where_conditions[] = "(LENGTH(pm_gallery.meta_value) - LENGTH(REPLACE(pm_gallery.meta_value, ',', '')) + 1) >= " . intval($filters['min_images']);
        }
        
        // Sort options
        if (empty($orderby_clause)) {
            switch ($sort_by) {
                case 'date_desc':
                    $orderby_clause = "ORDER BY p.post_date DESC";
                    break;
                case 'date_asc':
                    $orderby_clause = "ORDER BY p.post_date ASC";
                    break;
                case 'title_asc':
                    $orderby_clause = "ORDER BY p.post_title ASC";
                    break;
                case 'title_desc':
                    $orderby_clause = "ORDER BY p.post_title DESC";
                    break;
                case 'popular':
                    $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} pm_views ON p.ID = pm_views.post_id AND pm_views.meta_key = '_project_views'";
                    $orderby_clause = "ORDER BY CAST(pm_views.meta_value AS UNSIGNED) DESC";
                    break;
                default:
                    $orderby_clause = "ORDER BY p.post_date DESC";
            }
        }
        
        // Build final query
        $join_clause = !empty($join_clauses) ? implode(' ', array_unique($join_clauses)) : '';
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        // Get total count
        $count_sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p $join_clause $where_clause";
        
        if (!empty($query) && !get_option('project_gallery_enable_fuzzy_search', true)) {
            $total_count = $wpdb->get_var($wpdb->prepare($count_sql, $like_query, $like_query, $like_query));
        } else {
            $total_count = $wpdb->get_var($count_sql);
        }
        
        // Get results
        $results_sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p $join_clause $where_clause $orderby_clause LIMIT $offset, $per_page";
        
        if (!empty($query) && !get_option('project_gallery_enable_fuzzy_search', true)) {
            $project_ids = $wpdb->get_col($wpdb->prepare($results_sql, $like_query, $like_query, $like_query));
        } else {
            $project_ids = $wpdb->get_col($results_sql);
        }
        
        // Format results
        $projects = array();
        foreach ($project_ids as $project_id) {
            $projects[] = $this->format_search_result($project_id, $query);
        }
        
        return array(
            'projects' => $projects,
            'total' => intval($total_count),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total_count / $per_page),
            'query' => $query,
            'filters_applied' => $this->get_applied_filters($categories, $filters)
        );
    }
    
    /**
     * Format search result
     */
    private function format_search_result($project_id, $query = '') {
        $project = get_post($project_id);
        $gallery_images = get_post_meta($project_id, '_project_gallery_images', true);
        $categories = wp_get_post_terms($project_id, 'proje-kategori');
        
        // Highlight search terms in title and excerpt
        $highlighted_title = $this->highlight_search_terms($project->post_title, $query);
        $highlighted_excerpt = $this->highlight_search_terms(get_the_excerpt($project), $query);
        
        return array(
            'id' => $project_id,
            'title' => $highlighted_title,
            'original_title' => $project->post_title,
            'excerpt' => $highlighted_excerpt,
            'permalink' => get_permalink($project_id),
            'featured_image' => get_the_post_thumbnail_url($project_id, 'medium'),
            'gallery_count' => $gallery_images ? count(explode(',', $gallery_images)) : 0,
            'categories' => array_map(function($term) {
                return array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug
                );
            }, $categories),
            'date' => get_the_date('', $project),
            'author' => get_the_author_meta('display_name', $project->post_author),
            'relevance_score' => $this->calculate_relevance_score($project, $query)
        );
    }
    
    /**
     * Highlight search terms
     */
    private function highlight_search_terms($text, $query) {
        if (empty($query)) {
            return $text;
        }
        
        $terms = explode(' ', $query);
        foreach ($terms as $term) {
            $term = trim($term);
            if (strlen($term) > 2) {
                $text = preg_replace('/(' . preg_quote($term, '/') . ')/i', '<mark>$1</mark>', $text);
            }
        }
        
        return $text;
    }
    
    /**
     * Calculate relevance score
     */
    private function calculate_relevance_score($project, $query) {
        if (empty($query)) {
            return 0;
        }
        
        $score = 0;
        $query_terms = explode(' ', strtolower($query));
        
        $title = strtolower($project->post_title);
        $content = strtolower(strip_tags($project->post_content));
        
        foreach ($query_terms as $term) {
            $term = trim($term);
            if (strlen($term) > 2) {
                // Title matches are worth more
                $score += substr_count($title, $term) * 3;
                
                // Content matches
                $score += substr_count($content, $term);
            }
        }
        
        return $score;
    }
    
    /**
     * Get search suggestions
     */
    public function get_search_suggestions() {
        check_ajax_referer('project_gallery_search_nonce', 'nonce');
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        
        if (strlen($query) < 2) {
            wp_send_json_success(array());
        }
        
        global $wpdb;
        
        // Get suggestions from project titles
        $title_suggestions = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT post_title 
            FROM {$wpdb->posts} 
            WHERE post_type = 'proje' 
            AND post_status = 'publish' 
            AND post_title LIKE %s 
            ORDER BY post_title 
            LIMIT 5
        ", '%' . $wpdb->esc_like($query) . '%'));
        
        // Get suggestions from categories
        $category_suggestions = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT t.name 
            FROM {$wpdb->terms} t 
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
            WHERE tt.taxonomy = 'proje-kategori' 
            AND t.name LIKE %s 
            ORDER BY t.name 
            LIMIT 3
        ", '%' . $wpdb->esc_like($query) . '%'));
        
        // Get popular search terms
        $popular_searches = $this->get_popular_search_terms($query, 3);
        
        $suggestions = array_merge($title_suggestions, $category_suggestions, $popular_searches);
        $suggestions = array_unique($suggestions);
        $suggestions = array_slice($suggestions, 0, 8);
        
        wp_send_json_success($suggestions);
    }
    
    /**
     * Save search preference
     */
    public function save_search_preference() {
        check_ajax_referer('project_gallery_search_nonce', 'nonce');
        
        $preference_type = sanitize_text_field($_POST['type']);
        $preference_value = sanitize_text_field($_POST['value']);
        
        $user_id = get_current_user_id();
        if ($user_id) {
            $preferences = get_user_meta($user_id, 'project_gallery_search_prefs', true);
            $preferences = $preferences ? $preferences : array();
            
            $preferences[$preference_type] = $preference_value;
            
            update_user_meta($user_id, 'project_gallery_search_prefs', $preferences);
        } else {
            // Store in session for non-logged-in users
            if (!session_id()) {
                session_start();
            }
            
            if (!isset($_SESSION['project_gallery_search_prefs'])) {
                $_SESSION['project_gallery_search_prefs'] = array();
            }
            
            $_SESSION['project_gallery_search_prefs'][$preference_type] = $preference_value;
        }
        
        wp_send_json_success();
    }
    
    /**
     * Get recommendations
     */
    public function get_recommendations() {
        check_ajax_referer('project_gallery_search_nonce', 'nonce');
        
        $project_id = intval($_POST['project_id'] ?? 0);
        $type = sanitize_text_field($_POST['type'] ?? 'related');
        
        try {
            $recommendations = $this->generate_recommendations($project_id, $type);
            wp_send_json_success($recommendations);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Generate recommendations
     */
    private function generate_recommendations($project_id, $type) {
        switch ($type) {
            case 'related':
                return $this->get_related_projects($project_id);
            case 'popular':
                return $this->get_popular_projects();
            case 'recent':
                return $this->get_recent_projects();
            case 'similar_category':
                return $this->get_similar_category_projects($project_id);
            default:
                return array();
        }
    }
    
    /**
     * Get related projects
     */
    private function get_related_projects($project_id, $limit = 6) {
        $current_categories = wp_get_post_terms($project_id, 'proje-kategori', array('fields' => 'ids'));
        
        if (empty($current_categories)) {
            return $this->get_recent_projects($limit);
        }
        
        $args = array(
            'post_type' => 'proje',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__not_in' => array($project_id),
            'tax_query' => array(
                array(
                    'taxonomy' => 'proje-kategori',
                    'field' => 'term_id',
                    'terms' => $current_categories,
                    'operator' => 'IN'
                )
            ),
            'meta_query' => array(
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $projects = get_posts($args);
        
        return array_map(function($project) {
            return $this->format_search_result($project->ID);
        }, $projects);
    }
    
    /**
     * Search widget shortcode
     */
    public function search_widget_shortcode($atts) {
        $atts = shortcode_atts(array(
            'style' => 'default',
            'show_filters' => true,
            'show_suggestions' => true,
            'placeholder' => 'Search projects...'
        ), $atts);
        
        ob_start();
        ?>
        <div class="project-search-widget style-<?php echo esc_attr($atts['style']); ?>">
            <div class="search-form-container">
                <form class="project-search-form" id="project-search-form">
                    <div class="search-input-container">
                        <input type="text" 
                               id="project-search-input" 
                               placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                               autocomplete="off">
                        <button type="submit" class="search-submit-btn">
                            🔍
                        </button>
                        <div class="search-loading" style="display: none;">⏳</div>
                    </div>
                    
                    <?php if ($atts['show_suggestions']): ?>
                    <div class="search-suggestions" id="search-suggestions" style="display: none;">
                        <div class="suggestions-list"></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_filters']): ?>
                    <div class="search-filters">
                        <div class="filter-group">
                            <select id="category-filter" multiple>
                                <option value="">All Categories</option>
                                <?php
                                $categories = get_terms(array(
                                    'taxonomy' => 'proje-kategori',
                                    'hide_empty' => false
                                ));
                                foreach ($categories as $category):
                                ?>
                                <option value="<?php echo esc_attr($category->term_id); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select id="sort-filter">
                                <option value="relevance">Relevance</option>
                                <option value="date_desc">Newest First</option>
                                <option value="date_asc">Oldest First</option>
                                <option value="title_asc">Title A-Z</option>
                                <option value="title_desc">Title Z-A</option>
                                <option value="popular">Most Popular</option>
                            </select>
                        </div>
                        
                        <button type="button" class="advanced-filters-toggle">
                            ⚙️ Advanced
                        </button>
                    </div>
                    
                    <div class="advanced-filters" style="display: none;">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>Date From:</label>
                                <input type="date" id="date-from-filter">
                            </div>
                            <div class="filter-group">
                                <label>Date To:</label>
                                <input type="date" id="date-to-filter">
                            </div>
                        </div>
                        
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>
                                    <input type="checkbox" id="featured-image-filter">
                                    Has Featured Image
                                </label>
                            </div>
                            <div class="filter-group">
                                <label>Min Images:</label>
                                <input type="number" id="min-images-filter" min="0" max="50" step="1">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="search-results-container">
                <div class="search-results-header" style="display: none;">
                    <div class="results-info"></div>
                    <div class="view-options">
                        <button class="view-toggle active" data-view="grid">⊞</button>
                        <button class="view-toggle" data-view="list">☰</button>
                    </div>
                </div>
                
                <div class="search-results" id="search-results"></div>
                
                <div class="search-pagination" style="display: none;">
                    <button class="load-more-btn">Load More</button>
                </div>
            </div>
        </div>
        
        <style>
        .project-search-widget {
            max-width: 800px;
            margin: 20px auto;
        }
        
        .search-form-container {
            margin-bottom: 20px;
        }
        
        .search-input-container {
            position: relative;
            display: flex;
            align-items: center;
            border: 2px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        
        #project-search-input {
            flex: 1;
            padding: 12px 16px;
            border: none;
            font-size: 16px;
            outline: none;
        }
        
        .search-submit-btn {
            padding: 12px 16px;
            background: #0073aa;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .search-loading {
            position: absolute;
            right: 50px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 8px 8px;
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .suggestion-item {
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .suggestion-item:hover {
            background: #f0f0f0;
        }
        
        .search-filters {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .advanced-filters {
            margin-top: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .search-results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .view-toggle {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            margin-left: 5px;
        }
        
        .view-toggle.active {
            background: #0073aa;
            color: white;
        }
        
        .search-results {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .search-results.list-view {
            grid-template-columns: 1fr;
        }
        
        .search-result-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .search-result-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .result-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        
        .result-content {
            padding: 15px;
        }
        
        .result-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 8px 0;
        }
        
        .result-title mark {
            background: #ffeb3b;
            padding: 1px 2px;
        }
        
        .result-excerpt {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .result-meta {
            font-size: 12px;
            color: #999;
            display: flex;
            justify-content: space-between;
        }
        
        .search-pagination {
            text-align: center;
            margin-top: 30px;
        }
        
        .load-more-btn {
            padding: 12px 24px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .search-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .search-results {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Update search index
     */
    public function update_search_index($post_id) {
        if (get_post_type($post_id) !== 'proje') {
            return;
        }
        
        global $wpdb;
        
        $post = get_post($post_id);
        
        // Get categories and handle potential WP_Error
        $categories = wp_get_post_terms($post_id, 'proje-kategori', array('fields' => 'names'));
        if (is_wp_error($categories)) {
            $categories = array();
        }
        
        // Get tags and handle potential WP_Error
        $tags = wp_get_post_terms($post_id, 'post_tag', array('fields' => 'names'));
        if (is_wp_error($tags)) {
            $tags = array();
        }
        
        $meta_keywords = get_post_meta($post_id, '_project_keywords', true);
        
        $search_vector = $this->generate_search_vector($post, $categories, $tags, $meta_keywords);
        
        $table_name = $wpdb->prefix . 'project_gallery_search_index';
        
        $wpdb->replace(
            $table_name,
            array(
                'post_id' => $post_id,
                'title' => $post->post_title,
                'content' => strip_tags($post->post_content),
                'excerpt' => $post->post_excerpt,
                'categories' => implode(' ', is_array($categories) ? $categories : array()),
                'tags' => implode(' ', is_array($tags) ? $tags : array()),
                'meta_keywords' => $meta_keywords,
                'search_vector' => $search_vector
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Generate search vector
     */
    private function generate_search_vector($post, $categories, $tags, $meta_keywords) {
        // Ensure arrays are valid before implode
        $categories = is_array($categories) ? $categories : array();
        $tags = is_array($tags) ? $tags : array();
        
        $content = array(
            $post->post_title,
            strip_tags($post->post_content),
            $post->post_excerpt,
            implode(' ', $categories),
            implode(' ', $tags),
            $meta_keywords
        );
        
        return implode(' ', array_filter($content));
    }
    
    /**
     * Track search query
     */
    private function track_search_query($query, $results_count) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'project_gallery_search_log';
        
        // Create table if it doesn't exist
        $wpdb->query("CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            query text NOT NULL,
            results_count int NOT NULL,
            user_ip varchar(45),
            search_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY search_time (search_time),
            KEY results_count (results_count)
        )");
        
        $wpdb->insert(
            $table_name,
            array(
                'query' => $query,
                'results_count' => $results_count,
                'user_ip' => $this->get_user_ip()
            ),
            array('%s', '%d', '%s')
        );
    }
    
    /**
     * Get user IP
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                return $_SERVER[$key];
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Get popular search terms
     */
    private function get_popular_search_terms($query = '', $limit = 5) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'project_gallery_search_log';
        
        $where_clause = '';
        if (!empty($query)) {
            $where_clause = $wpdb->prepare("WHERE query LIKE %s", '%' . $wpdb->esc_like($query) . '%');
        }
        
        $results = $wpdb->get_col($wpdb->prepare("
            SELECT query 
            FROM $table_name 
            $where_clause
            AND search_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY query 
            ORDER BY COUNT(*) DESC 
            LIMIT %d
        ", $limit));
        
        return $results ?: array();
    }
    
    /**
     * Get applied filters summary
     */
    private function get_applied_filters($categories, $filters) {
        $applied = array();
        
        if (!empty($categories)) {
            $category_names = array();
            foreach ($categories as $cat_id) {
                $term = get_term($cat_id);
                if ($term) {
                    $category_names[] = $term->name;
                }
            }
            $applied['categories'] = $category_names;
        }
        
        if (!empty($filters['date_from'])) {
            $applied['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $applied['date_to'] = $filters['date_to'];
        }
        
        if ($filters['has_featured_image']) {
            $applied['has_featured_image'] = true;
        }
        
        if ($filters['min_images'] > 0) {
            $applied['min_images'] = $filters['min_images'];
        }
        
        return $applied;
    }
    
    /**
     * Remove from search index
     */
    public function remove_from_search_index($post_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'project_gallery_search_index';
        
        $wpdb->delete(
            $table_name,
            array('post_id' => $post_id),
            array('%d')
        );
    }
    
    /**
     * Get popular projects
     */
    private function get_popular_projects($limit = 6) {
        $args = array(
            'post_type' => 'proje',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_key' => '_project_views',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $projects = get_posts($args);
        
        return array_map(function($project) {
            return $this->format_search_result($project->ID);
        }, $projects);
    }
    
    /**
     * Get recent projects
     */
    private function get_recent_projects($limit = 6) {
        $args = array(
            'post_type' => 'proje',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $projects = get_posts($args);
        
        return array_map(function($project) {
            return $this->format_search_result($project->ID);
        }, $projects);
    }
    
    /**
     * Get similar category projects
     */
    private function get_similar_category_projects($project_id, $limit = 6) {
        return $this->get_related_projects($project_id, $limit);
    }
    
    /**
     * Filter widget shortcode
     */
    public function filter_widget_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_categories' => true,
            'show_sort' => true,
            'show_search' => false,
            'style' => 'horizontal'
        ), $atts);
        
        ob_start();
        ?>
        <div class="project-filter-widget style-<?php echo esc_attr($atts['style']); ?>">
            <?php if ($atts['show_search']): ?>
            <div class="filter-search">
                <input type="text" id="filter-search-input" placeholder="Quick search...">
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_categories']): ?>
            <div class="filter-categories">
                <label>Category:</label>
                <select id="filter-category-select">
                    <option value="">All Categories</option>
                    <?php
                    $categories = get_terms(array(
                        'taxonomy' => 'proje-kategori',
                        'hide_empty' => false
                    ));
                    foreach ($categories as $category):
                    ?>
                    <option value="<?php echo esc_attr($category->slug); ?>">
                        <?php echo esc_html($category->name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_sort']): ?>
            <div class="filter-sort">
                <label>Sort by:</label>
                <select id="filter-sort-select">
                    <option value="date_desc">Newest First</option>
                    <option value="date_asc">Oldest First</option>
                    <option value="title_asc">Title A-Z</option>
                    <option value="title_desc">Title Z-A</option>
                    <option value="popular">Most Popular</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .project-filter-widget {
            display: flex;
            gap: 15px;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        
        .project-filter-widget.style-vertical {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-search,
        .filter-categories,
        .filter-sort {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-search input,
        .filter-categories select,
        .filter-sort select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 150px;
        }
        
        @media (max-width: 768px) {
            .project-filter-widget {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-search,
            .filter-categories,
            .filter-sort {
                justify-content: space-between;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
}