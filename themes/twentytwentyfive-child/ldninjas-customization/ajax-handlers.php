<?php
/**
 * WPML LifterLMS AJAX Handlers
 * 
 * Handles AJAX requests for course relationship fixing
 * 
 * @package TwentyTwentyFive_Child
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPML_LLMS_Ajax_Handlers {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_wpml_llms_fix_relationships', array($this, 'handle_fix_relationships'));
        add_action('wp_ajax_wpml_llms_get_course_info', array($this, 'handle_get_course_info'));
    }
    
    /**
     * Handle fix relationships AJAX request
     */
    public function handle_fix_relationships() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpml_llms_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $course_id = intval($_POST['course_id']);
        
        if (!$course_id) {
            wp_send_json_error(array(
                'message' => __('Invalid course ID', 'twentytwentyfive-child')
            ));
        }
        
        // Initialize the course fixer
        $fixer = new WPML_LLMS_Course_Fixer();
        
        $result = $fixer->fix_course_relationships($course_id);
        
        wp_send_json_success(array(
            'message' => __('Relationships fixed successfully', 'twentytwentyfive-child'),
            'logs' => $result['logs'],
            'stats' => $result['stats']
        ));
    }
    
    /**
     * Handle get course info AJAX request
     */
    public function handle_get_course_info() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpml_llms_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $course_id = intval($_POST['course_id']);
        
        if (!$course_id) {
            wp_send_json_error(array(
                'message' => __('Invalid course ID', 'twentytwentyfive-child')
            ));
        }
        
        $course_info = $this->get_course_information($course_id);
        
        wp_send_json_success($course_info);
    }
    
    /**
     * Get detailed course information
     */
    private function get_course_information($course_id) {
        $course = get_post($course_id);
        
        if (!$course || $course->post_type !== 'course') {
            return array(
                'error' => __('Course not found', 'twentytwentyfive-child')
            );
        }
        
        $info = array(
            'id' => $course_id,
            'title' => $course->post_title,
            'status' => $course->post_status,
            'language' => $this->get_post_language($course_id),
            'translations' => $this->get_course_translations($course_id),
            'lessons_count' => $this->count_course_lessons($course_id),
            'students_count' => $this->count_course_students($course_id)
        );
        
        return $info;
    }
    
    /**
     * Get post language using WPML
     */
    private function get_post_language($post_id) {
        if (function_exists('wpml_get_language_information')) {
            $lang_info = wpml_get_language_information(null, $post_id);
            return isset($lang_info['language_code']) ? $lang_info['language_code'] : 'unknown';
        }
        
        return 'unknown';
    }
    
    /**
     * Get course translations
     */
    private function get_course_translations($course_id) {
        $translations = array();
        
        if (function_exists('wpml_get_language_information')) {
            // Get all available languages
            $languages = apply_filters('wpml_active_languages', null);
            
            foreach ($languages as $lang_code => $language) {
                $translated_id = apply_filters('wpml_object_id', $course_id, 'course', false, $lang_code);
                
                if ($translated_id && $translated_id !== $course_id) {
                    $translated_post = get_post($translated_id);
                    if ($translated_post) {
                        $translations[$lang_code] = array(
                            'id' => $translated_id,
                            'title' => $translated_post->post_title,
                            'status' => $translated_post->post_status,
                            'language_name' => $language['native_name']
                        );
                    }
                }
            }
        }
        
        return $translations;
    }
    
    /**
     * Count course lessons
     */
    private function count_course_lessons($course_id) {
        if (!class_exists('LLMS_Course')) {
            return 0;
        }
        
        $course = new LLMS_Course($course_id);
        $lessons = $course->get_lessons('ids');
        
        return is_array($lessons) ? count($lessons) : 0;
    }
    
    /**
     * Count course students
     */
    private function count_course_students($course_id) {
        if (!class_exists('LLMS_Course')) {
            return 0;
        }
        
        $course = new LLMS_Course($course_id);
        $students = $course->get_students();
        
        return is_array($students) ? count($students) : 0;
    }
    
    /**
     * Sanitize and validate AJAX input
     */
    private function sanitize_ajax_input($input, $type = 'text') {
        switch ($type) {
            case 'int':
                return intval($input);
            case 'email':
                return sanitize_email($input);
            case 'url':
                return esc_url_raw($input);
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }
    
    /**
     * Log AJAX activity
     */
    private function log_ajax_activity($action, $data = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'action' => $action,
            'data' => $data,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        
    }
}

new WPML_LLMS_Ajax_Handlers();