<?php
/**
 * WPML LifterLMS Cache Handler
 * 
 * Implements caching, optimization features, and performance monitoring
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache Handler Class
 */
class WPML_LifterLMS_Cache {
    
    /**
     * Cache group
     * @var string
     */
    private $cache_group = 'wpml_lifterlms';
    
    /**
     * Cache expiration time
     * @var int
     */
    private $cache_expiration = 3600; // 1 hour
    
    /**
     * Initialize the component
     */
    public function init() {
        // Cache management hooks
        add_action('wpml_lifterlms_clear_cache', array($this, 'clear_all_cache'));
        add_action('save_post', array($this, 'clear_post_cache'), 10, 2);
        add_action('created_term', array($this, 'clear_taxonomy_cache'), 10, 3);
        add_action('edited_term', array($this, 'clear_taxonomy_cache'), 10, 3);
        
        // Performance optimization hooks
        add_filter('wpml_lifterlms_get_translations', array($this, 'cache_translations'), 10, 3);
        add_filter('wpml_lifterlms_get_course_data', array($this, 'cache_course_data'), 10, 2);
        add_filter('wpml_lifterlms_get_user_progress', array($this, 'cache_user_progress'), 10, 2);
        
        // Object cache integration
        if (wp_using_ext_object_cache()) {
            add_action('init', array($this, 'setup_object_cache'));
        }
    }
    
    /**
     * Setup object cache
     */
    public function setup_object_cache() {
        // Configure object cache groups
        wp_cache_add_global_groups(array($this->cache_group));
    }
    
    /**
     * Cache translations
     * @param array $translations
     * @param int $element_id
     * @param string $element_type
     * @return array
     */
    public function cache_translations($translations, $element_id, $element_type) {
        $cache_key = $this->get_cache_key('translations', $element_id, $element_type);
        
        // Try to get from cache first
        $cached_translations = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_translations !== false) {
            return $cached_translations;
        }
        
        // Cache the translations
        wp_cache_set($cache_key, $translations, $this->cache_group, $this->cache_expiration);
        
        return $translations;
    }
    
    /**
     * Cache course data
     * @param array $course_data
     * @param int $course_id
     * @return array
     */
    public function cache_course_data($course_data, $course_id) {
        $cache_key = $this->get_cache_key('course_data', $course_id);
        
        // Try to get from cache first
        $cached_data = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Cache the course data
        wp_cache_set($cache_key, $course_data, $this->cache_group, $this->cache_expiration);
        
        return $course_data;
    }
    
    /**
     * Cache user progress
     * @param array $progress_data
     * @param int $user_id
     * @return array
     */
    public function cache_user_progress($progress_data, $user_id) {
        $cache_key = $this->get_cache_key('user_progress', $user_id);
        
        // Try to get from cache first
        $cached_progress = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_progress !== false) {
            return $cached_progress;
        }
        
        // Cache the progress data
        wp_cache_set($cache_key, $progress_data, $this->cache_group, $this->cache_expiration);
        
        return $progress_data;
    }
    
    /**
     * Clear all cache
     */
    public function clear_all_cache() {
        // Clear WordPress object cache
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($this->cache_group);
        } else {
            wp_cache_flush();
        }
        
        // Clear transients
        $this->clear_transients();
        
        // Clear file cache if exists
        $this->clear_file_cache();
        
        do_action('wpml_lifterlms_cache_cleared');
    }
    
    /**
     * Clear post cache
     * @param int $post_id
     * @param WP_Post $post
     */
    public function clear_post_cache($post_id, $post) {
        // Only clear cache for LifterLMS post types
        $lifterlms_post_types = array('course', 'lesson', 'llms_quiz', 'llms_membership');
        
        if (!in_array($post->post_type, $lifterlms_post_types)) {
            return;
        }
        
        // Clear related caches
        $this->clear_post_related_cache($post_id, $post->post_type);
    }
    
    /**
     * Clear taxonomy cache
     * @param int $term_id
     * @param int $tt_id
     * @param string $taxonomy
     */
    public function clear_taxonomy_cache($term_id, $tt_id, $taxonomy) {
        // Only clear cache for LifterLMS taxonomies
        $lifterlms_taxonomies = array('course_cat', 'course_tag', 'course_difficulty', 'course_track', 'membership_cat', 'membership_tag');
        
        if (!in_array($taxonomy, $lifterlms_taxonomies)) {
            return;
        }
        
        // Clear related caches
        $this->clear_taxonomy_related_cache($term_id, $taxonomy);
    }
    
    /**
     * Clear post related cache
     * @param int $post_id
     * @param string $post_type
     */
    private function clear_post_related_cache($post_id, $post_type) {
        // Clear translations cache
        $cache_key = $this->get_cache_key('translations', $post_id, 'post_' . $post_type);
        wp_cache_delete($cache_key, $this->cache_group);
        
        // Clear course data cache
        if ($post_type === 'course') {
            $cache_key = $this->get_cache_key('course_data', $post_id);
            wp_cache_delete($cache_key, $this->cache_group);
        }
        
        // Clear related user progress cache
        $this->clear_user_progress_cache_for_post($post_id, $post_type);
    }
    
    /**
     * Clear taxonomy related cache
     * @param int $term_id
     * @param string $taxonomy
     */
    private function clear_taxonomy_related_cache($term_id, $taxonomy) {
        // Clear translations cache
        $cache_key = $this->get_cache_key('translations', $term_id, 'tax_' . $taxonomy);
        wp_cache_delete($cache_key, $this->cache_group);
        
        // Clear taxonomy query caches
        $this->clear_taxonomy_query_cache($taxonomy);
    }
    
    /**
     * Clear user progress cache for post
     * @param int $post_id
     * @param string $post_type
     */
    private function clear_user_progress_cache_for_post($post_id, $post_type) {
        // Get all users enrolled in this course/lesson
        if ($post_type === 'course') {
            $enrolled_users = llms_get_enrolled_students($post_id);
        } elseif ($post_type === 'lesson') {
            $course_id = get_post_meta($post_id, '_llms_parent_course', true);
            $enrolled_users = $course_id ? llms_get_enrolled_students($course_id) : array();
        } else {
            return;
        }
        
        // Clear progress cache for each user
        foreach ($enrolled_users as $user_id) {
            $cache_key = $this->get_cache_key('user_progress', $user_id);
            wp_cache_delete($cache_key, $this->cache_group);
        }
    }
    
    /**
     * Clear taxonomy query cache
     * @param string $taxonomy
     */
    private function clear_taxonomy_query_cache($taxonomy) {
        // Clear related query caches
        $cache_patterns = array(
            'taxonomy_query_' . $taxonomy,
            'course_catalog_' . $taxonomy,
            'membership_catalog_' . $taxonomy
        );
        
        foreach ($cache_patterns as $pattern) {
            $this->clear_cache_by_pattern($pattern);
        }
    }
    
    /**
     * Clear transients
     */
    private function clear_transients() {
        global $wpdb;
        
        // Delete all WPML LifterLMS transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_wpml_llms_%',
                '_transient_timeout_wpml_llms_%'
            )
        );
    }
    
    /**
     * Clear file cache
     */
    private function clear_file_cache() {
        $cache_dir = WP_CONTENT_DIR . '/cache/wpml-lifterlms/';
        
        if (is_dir($cache_dir)) {
            $this->delete_directory($cache_dir);
        }
    }
    
    /**
     * Clear cache by pattern
     * @param string $pattern
     */
    private function clear_cache_by_pattern($pattern) {
        // This is a simplified implementation
        // In a real-world scenario, you might need more sophisticated pattern matching
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group($this->cache_group);
        }
    }
    
    /**
     * Get cache key
     * @param string $type
     * @param mixed ...$args
     * @return string
     */
    private function get_cache_key($type, ...$args) {
        $key_parts = array($type);
        $key_parts = array_merge($key_parts, $args);
        
        // Add current language to cache key
        $current_language = apply_filters('wpml_current_language', null);
        if ($current_language) {
            $key_parts[] = $current_language;
        }
        
        return implode('_', $key_parts);
    }
    
    /**
     * Delete directory recursively
     * @param string $dir
     * @return bool
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Get cache statistics
     * @return array
     */
    public function get_cache_stats() {
        $stats = array(
            'cache_hits' => 0,
            'cache_misses' => 0,
            'cache_size' => 0,
            'cache_entries' => 0
        );
        
        // Get cache statistics if available
        if (function_exists('wp_cache_get_stats')) {
            $cache_stats = wp_cache_get_stats();
            if (isset($cache_stats[$this->cache_group])) {
                $stats = array_merge($stats, $cache_stats[$this->cache_group]);
            }
        }
        
        return $stats;
    }
    
    /**
     * Warm up cache
     */
    public function warm_up_cache() {
        // Pre-load frequently accessed data
        $this->warm_up_translations_cache();
        $this->warm_up_course_data_cache();
        $this->warm_up_taxonomy_cache();
        
        do_action('wpml_lifterlms_cache_warmed_up');
    }
    
    /**
     * Warm up translations cache
     */
    private function warm_up_translations_cache() {
        // Get all LifterLMS post types
        $post_types = array('course', 'lesson', 'llms_quiz', 'llms_membership');
        
        foreach ($post_types as $post_type) {
            $posts = get_posts(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'numberposts' => 50, // Limit to avoid memory issues
                'suppress_filters' => true
            ));
            
            foreach ($posts as $post) {
                // Pre-load translations
                apply_filters('wpml_get_element_translations', null, $post->ID, 'post_' . $post_type);
            }
        }
    }
    
    /**
     * Warm up course data cache
     */
    private function warm_up_course_data_cache() {
        $courses = get_posts(array(
            'post_type' => 'course',
            'post_status' => 'publish',
            'numberposts' => 20,
            'suppress_filters' => true
        ));
        
        foreach ($courses as $course) {
            // Pre-load course data
            $course_obj = new LLMS_Course($course->ID);
            apply_filters('wpml_lifterlms_get_course_data', array(), $course->ID);
        }
    }
    
    /**
     * Warm up taxonomy cache
     */
    private function warm_up_taxonomy_cache() {
        $taxonomies = array('course_cat', 'course_tag', 'course_difficulty', 'course_track', 'membership_cat', 'membership_tag');
        
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'number' => 50,
                'suppress_filters' => true
            ));
            
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    // Pre-load term translations
                    apply_filters('wpml_get_element_translations', null, $term->term_id, 'tax_' . $taxonomy);
                }
            }
        }
    }
    
    /**
     * Schedule cache cleanup
     */
    public function schedule_cache_cleanup() {
        if (!wp_next_scheduled('wpml_lifterlms_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wpml_lifterlms_cache_cleanup');
        }
        
        add_action('wpml_lifterlms_cache_cleanup', array($this, 'cleanup_expired_cache'));
    }
    
    /**
     * Cleanup expired cache
     */
    public function cleanup_expired_cache() {
        // Clean up expired transients
        $this->cleanup_expired_transients();
        
        // Clean up old file cache
        $this->cleanup_old_file_cache();
        
        do_action('wpml_lifterlms_cache_cleaned_up');
    }
    
    /**
     * Cleanup expired transients
     */
    private function cleanup_expired_transients() {
        global $wpdb;
        
        // Delete expired transients
        $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
            WHERE a.option_name LIKE '_transient_wpml_llms_%'
            AND a.option_name NOT LIKE '_transient_timeout_wpml_llms_%'
            AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
            AND b.option_value < UNIX_TIMESTAMP()"
        );
    }
    
    /**
     * Cleanup old file cache
     */
    private function cleanup_old_file_cache() {
        $cache_dir = WP_CONTENT_DIR . '/cache/wpml-lifterlms/';
        
        if (!is_dir($cache_dir)) {
            return;
        }
        
        $files = glob($cache_dir . '*');
        $expire_time = time() - $this->cache_expiration;
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $expire_time) {
                unlink($file);
            }
        }
    }
}

