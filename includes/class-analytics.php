<?php
/**
 * Project Gallery Analytics System
 * Advanced analytics and insights for project galleries
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProjectGalleryAnalytics {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'project_gallery_analytics';
        
        add_action('wp_ajax_track_gallery_view', array($this, 'track_gallery_view'));
        add_action('wp_ajax_nopriv_track_gallery_view', array($this, 'track_gallery_view'));
        add_action('wp_ajax_track_image_view', array($this, 'track_image_view'));
        add_action('wp_ajax_nopriv_track_image_view', array($this, 'track_image_view'));
        add_action('wp_ajax_track_project_view', array($this, 'track_project_view'));
        add_action('wp_ajax_nopriv_track_project_view', array($this, 'track_project_view'));
    }
    
    /**
     * Create analytics table
     */
    public function create_analytics_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            project_id bigint(20) DEFAULT NULL,
            image_id bigint(20) DEFAULT NULL,
            user_ip varchar(45) NOT NULL,
            user_agent text,
            referrer text,
            session_id varchar(255),
            device_type varchar(20),
            browser varchar(50),
            country varchar(5),
            view_time datetime DEFAULT CURRENT_TIMESTAMP,
            duration int DEFAULT 0,
            metadata text,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY project_id (project_id),
            KEY view_time (view_time),
            KEY device_type (device_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Track gallery view
     */
    public function track_gallery_view() {
        check_ajax_referer('project_gallery_nonce', 'nonce');
        
        $category = sanitize_text_field($_POST['category'] ?? '');
        $layout = sanitize_text_field($_POST['layout'] ?? '');
        
        $this->record_event('gallery_view', null, null, array(
            'category' => $category,
            'layout' => $layout
        ));
        
        wp_die();
    }
    
    /**
     * Track image view in lightbox
     */
    public function track_image_view() {
        check_ajax_referer('project_gallery_nonce', 'nonce');
        
        $image_id = intval($_POST['image_id']);
        $project_id = intval($_POST['project_id']);
        $position = intval($_POST['position']);
        
        $this->record_event('image_view', $project_id, $image_id, array(
            'position' => $position
        ));
        
        wp_die();
    }
    
    /**
     * Track project page view
     */
    public function track_project_view() {
        check_ajax_referer('project_gallery_nonce', 'nonce');
        
        $project_id = intval($_POST['project_id']);
        $source = sanitize_text_field($_POST['source'] ?? '');
        
        $this->record_event('project_view', $project_id, null, array(
            'source' => $source
        ));
        
        wp_die();
    }
    
    /**
     * Record analytics event
     */
    private function record_event($event_type, $project_id = null, $image_id = null, $metadata = array()) {
        global $wpdb;
        
        // Get user information
        $user_ip = $this->get_user_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $session_id = $this->get_session_id();
        
        // Parse user agent
        $device_info = $this->parse_user_agent($user_agent);
        
        $wpdb->insert(
            $this->table_name,
            array(
                'event_type' => $event_type,
                'project_id' => $project_id,
                'image_id' => $image_id,
                'user_ip' => $user_ip,
                'user_agent' => $user_agent,
                'referrer' => $referrer,
                'session_id' => $session_id,
                'device_type' => $device_info['device'],
                'browser' => $device_info['browser'],
                'metadata' => json_encode($metadata),
                'view_time' => current_time('mysql')
            ),
            array('%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get analytics dashboard data
     */
    public function get_dashboard_data($period = '30_days') {
        global $wpdb;
        
        $where_clause = $this->get_period_where_clause($period);
        
        // Total views
        $total_views = $wpdb->get_var("
            SELECT COUNT(*) FROM $this->table_name 
            WHERE $where_clause
        ");
        
        // Popular projects
        $popular_projects = $wpdb->get_results("
            SELECT project_id, COUNT(*) as views
            FROM $this->table_name 
            WHERE event_type = 'project_view' AND $where_clause
            GROUP BY project_id 
            ORDER BY views DESC 
            LIMIT 10
        ");
        
        // Device breakdown
        $device_stats = $wpdb->get_results("
            SELECT device_type, COUNT(*) as count
            FROM $this->table_name 
            WHERE $where_clause
            GROUP BY device_type
        ");
        
        // Daily views
        $daily_views = $wpdb->get_results("
            SELECT DATE(view_time) as date, COUNT(*) as views
            FROM $this->table_name 
            WHERE $where_clause
            GROUP BY DATE(view_time)
            ORDER BY date DESC
            LIMIT 30
        ");
        
        // Browser stats
        $browser_stats = $wpdb->get_results("
            SELECT browser, COUNT(*) as count
            FROM $this->table_name 
            WHERE $where_clause
            GROUP BY browser
            ORDER BY count DESC
            LIMIT 5
        ");
        
        return array(
            'total_views' => $total_views,
            'popular_projects' => $popular_projects,
            'device_stats' => $device_stats,
            'daily_views' => $daily_views,
            'browser_stats' => $browser_stats
        );
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get or create session ID
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['pg_session_id'])) {
            $_SESSION['pg_session_id'] = uniqid('pg_', true);
        }
        
        return $_SESSION['pg_session_id'];
    }
    
    /**
     * Parse user agent
     */
    private function parse_user_agent($user_agent) {
        $device = 'desktop';
        $browser = 'unknown';
        
        // Detect device
        if (preg_match('/Mobile|Android|iPhone|iPad/', $user_agent)) {
            $device = 'mobile';
        } elseif (preg_match('/Tablet|iPad/', $user_agent)) {
            $device = 'tablet';
        }
        
        // Detect browser
        if (preg_match('/Chrome/', $user_agent)) {
            $browser = 'chrome';
        } elseif (preg_match('/Firefox/', $user_agent)) {
            $browser = 'firefox';
        } elseif (preg_match('/Safari/', $user_agent)) {
            $browser = 'safari';
        } elseif (preg_match('/Edge/', $user_agent)) {
            $browser = 'edge';
        }
        
        return array('device' => $device, 'browser' => $browser);
    }
    
    /**
     * Get period where clause
     */
    private function get_period_where_clause($period) {
        switch ($period) {
            case '7_days':
                return "view_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30_days':
                return "view_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '3_months':
                return "view_time >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            case '1_year':
                return "view_time >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "1=1";
        }
    }
}