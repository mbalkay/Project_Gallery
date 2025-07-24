<?php
/**
 * Project Gallery Import/Export System
 * Advanced data management for project galleries
 */

if (!defined('ABSPATH')) {
    exit;
}

class ProjectGalleryImportExport {
    
    public function __construct() {
        add_action('wp_ajax_export_projects', array($this, 'export_projects'));
        add_action('wp_ajax_import_projects', array($this, 'import_projects'));
        add_action('wp_ajax_backup_gallery_data', array($this, 'backup_gallery_data'));
        add_action('wp_ajax_restore_gallery_data', array($this, 'restore_gallery_data'));
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
    private function get_projects_for_export($categories = array()) {
        $args = array(
            'post_type' => 'proje',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_project_gallery_images',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_project_gallery_images',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        if (!empty($categories)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'proje-kategori',
                    'field' => 'term_id',
                    'terms' => $categories
                )
            );
        }
        
        $projects = get_posts($args);
        $export_data = array();
        
        foreach ($projects as $project) {
            $gallery_images = get_post_meta($project->ID, '_project_gallery_images', true);
            $categories = wp_get_post_terms($project->ID, 'proje-kategori');
            
            $export_data[] = array(
                'id' => $project->ID,
                'title' => $project->post_title,
                'content' => $project->post_content,
                'excerpt' => $project->post_excerpt,
                'status' => $project->post_status,
                'date' => $project->post_date,
                'featured_image' => get_post_thumbnail_id($project->ID),
                'gallery_images' => $gallery_images ? explode(',', $gallery_images) : array(),
                'categories' => array_map(function($term) {
                    return array(
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug
                    );
                }, $categories),
                'meta' => get_post_meta($project->ID)
            );
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
}