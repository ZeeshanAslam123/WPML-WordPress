<?php
/**
 * WPML LifterLMS Auto Course Fixer
 * 
 * Automatically fixes course relationships when WPML translations are completed
 * 
 * @package TwentyTwentyFive_Child
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPML_LLMS_Auto_Course_Fixer {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Cache for processed courses to prevent duplicate fixes
     */
    private $processed_courses = array();
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->processed_courses = array();
        $this->log('WPML_LLMS_Auto_Course_Fixer initialized', 'info');
        $this->init_hooks();
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Primary hook - WPML translation completion (most targeted)
        add_action('wpml_pro_translation_completed', array($this, 'on_translation_completed'), 10, 3);
        
        // Additional WPML hooks for different translation methods
        add_action('icl_make_duplicate', array($this, 'on_wpml_duplicate'), 10, 4);
        
        // More reliable hooks - save_post for all course-related content
        add_action('save_post', array($this, 'on_post_saved'), 25, 3);
        
        // Clean up processed courses cache periodically
        add_action('wp_scheduled_delete', array($this, 'cleanup_cache'));
        
        // Test hook to verify our system is working
        add_action('init', array($this, 'test_hook_firing'));
    }
    
    /**
     * Handle WPML translation completion
     * 
     * @param int $new_post_id The newly translated post ID
     * @param array $fields Translation fields
     * @param object $job Translation job object
     */
    public function on_translation_completed($new_post_id, $fields, $job) {
        // Log all translation completions for debugging
        $post = get_post($new_post_id);
        $post_type = $post ? $post->post_type : 'unknown';
        $this->log('WPML translation completed - Post ID: ' . $new_post_id . ', Type: ' . $post_type, 'info');
        
        // Handle different post types that are part of a course
        if (!$post) {
            return;
        }
        
        $course_related_types = array('course', 'section', 'lesson', 'llms_quiz');
        
        if (!in_array($post->post_type, $course_related_types)) {
            return;
        }
        
        $this->log('Translation completed for ' . $post->post_type . ': ' . $post->post_title . ' (ID: ' . $new_post_id . ')', 'info');
        
        // Find the related course and execute the fix
        $this->handle_course_related_translation($new_post_id, $post->post_type);
    }
    
    /**
     * Handle WPML duplicate creation (icl_make_duplicate hook)
     * 
     * @param int $master_post_id Original post ID
     * @param string $lang Target language
     * @param array $postarr Post data
     * @param int $id New duplicate post ID
     */
    public function on_wpml_duplicate($master_post_id, $lang, $postarr, $id) {
        $post = get_post($id);
        if (!$post) {
            return;
        }
        
        $this->log('WPML duplicate created - Original: ' . $master_post_id . ', New: ' . $id . ', Type: ' . $post->post_type . ', Lang: ' . $lang, 'info');
        
        $course_related_types = array('course', 'section', 'lesson', 'llms_quiz');
        
        if (in_array($post->post_type, $course_related_types)) {
            $this->handle_course_related_translation($id, $post->post_type);
        }
    }
    
    /**
     * Handle any post save events - comprehensive handler for all course-related content
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function on_post_saved($post_id, $post, $update) {
        // Skip if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only handle course-related post types
        $course_related_types = array('course', 'section', 'lesson', 'llms_quiz');
        if (!in_array($post->post_type, $course_related_types)) {
            return;
        }
        
        // Check if this is a translated post (not English)
        $language = $this->get_post_language($post_id);
        if ($language === 'en' || !$language) {
            return; // Skip English posts and unknown languages
        }
        
        $this->log('Post saved: ' . $post->post_type . ' "' . $post->post_title . '" (ID: ' . $post_id . ', Lang: ' . $language . ', Update: ' . ($update ? 'yes' : 'no') . ')', 'info');
        
        // Handle based on post type
        if ($post->post_type === 'course') {
            // For courses, execute fix directly
            $this->execute_relationship_fix($post_id, 'course_saved');
        } else {
            // For sections, lessons, quizzes - find the related course
            $this->handle_course_related_translation($post_id, $post->post_type);
        }
    }
    
    /**
     * Test hook to verify our system is working
     */
    public function test_hook_firing() {
        $this->log('Auto-fixer test hook fired on init - system is working!', 'info');
    }
    
    /**
     * Handle translation completion for course-related content
     * 
     * @param int $translated_post_id The translated post ID
     * @param string $post_type The post type that was translated
     */
    private function handle_course_related_translation($translated_post_id, $post_type) {
        $course_id = null;
        
        switch ($post_type) {
            case 'course':
                $course_id = $translated_post_id;
                break;
                
            case 'section':
                // Get the parent course for this section
                $parent_course = get_post_meta($translated_post_id, '_llms_parent_course', true);
                if ($parent_course) {
                    // Get the English version of the parent course
                    $course_id = apply_filters('wpml_object_id', $parent_course, 'course', false, 'en');
                }
                break;
                
            case 'lesson':
                // Get the parent section, then the parent course
                $parent_section = get_post_meta($translated_post_id, '_llms_parent_section', true);
                if ($parent_section) {
                    $parent_course = get_post_meta($parent_section, '_llms_parent_course', true);
                    if ($parent_course) {
                        $course_id = apply_filters('wpml_object_id', $parent_course, 'course', false, 'en');
                    }
                }
                break;
                
            case 'llms_quiz':
                // Get the parent lesson, then section, then course
                $parent_lesson = get_post_meta($translated_post_id, '_llms_parent_lesson', true);
                if ($parent_lesson) {
                    $parent_section = get_post_meta($parent_lesson, '_llms_parent_section', true);
                    if ($parent_section) {
                        $parent_course = get_post_meta($parent_section, '_llms_parent_course', true);
                        if ($parent_course) {
                            $course_id = apply_filters('wpml_object_id', $parent_course, 'course', false, 'en');
                        }
                    }
                }
                break;
        }
        
        if ($course_id) {
            $this->log('Found related English course ID: ' . $course_id . ' for ' . $post_type . ' ' . $translated_post_id, 'info');
            // Use the translated post to find the course, but fix relationships for the English course
            $this->execute_relationship_fix($translated_post_id, 'translation_completed_' . $post_type);
        } else {
            $this->log('Could not find related English course for ' . $post_type . ' ' . $translated_post_id, 'warning');
        }
    }
    
    /**
     * Execute relationship fix with optimizations
     * 
     * @param int $translated_course_id The translated course ID
     * @param string $trigger What triggered this fix
     */
    private function execute_relationship_fix($translated_course_id, $trigger = 'unknown') {
        // Get the original English course
        $original_id = apply_filters('wpml_object_id', $translated_course_id, 'course', false, 'en');
        
        if (!$original_id || $original_id === $translated_course_id) {
            $this->log('No English original found for course ' . $translated_course_id, 'warning');
            return;
        }
        
        // Check if we've already processed this course recently (prevent duplicates)
        $cache_key = $original_id . '_' . $translated_course_id;
        if (isset($this->processed_courses[$cache_key])) {
            $last_processed = $this->processed_courses[$cache_key];
            if ((time() - $last_processed) < 300) { // 5 minutes cooldown
                $this->log('Skipping duplicate fix for course ' . $original_id . ' (processed ' . (time() - $last_processed) . 's ago)', 'info');
                return;
            }
        }
        
        // Mark as being processed
        $this->processed_courses[$cache_key] = time();
        
        $this->log('Executing auto-fix for course ' . $original_id . ' (trigger: ' . $trigger . ')', 'info');
        
        // Execute the fix immediately
        $this->execute_auto_fix($original_id);
    }
    
    /**
     * Execute the automatic relationship fix
     * 
     * @param int $original_course_id The original English course ID
     */
    public function execute_auto_fix($original_course_id) {
        $start_time = microtime(true);
        
        $this->log('Starting auto-fix for course ' . $original_course_id, 'info');
        
        // Validate course exists
        $course = get_post($original_course_id);
        if (!$course || $course->post_type !== 'course') {
            $this->log('Invalid course ID for auto-fix: ' . $original_course_id, 'error');
            return false;
        }
        
        // Check if the course fixer class is available
        if (!class_exists('WPML_LLMS_Course_Fixer')) {
            $this->log('WPML_LLMS_Course_Fixer class not available', 'error');
            return false;
        }
        
        try {
            // Initialize the course fixer
            $fixer = new WPML_LLMS_Course_Fixer();
            
            // Execute the fix
            $result = $fixer->fix_course_relationships($original_course_id);
            
            $execution_time = round((microtime(true) - $start_time), 2);
            
            if ($result['success']) {
                $stats = $result['stats'];
                $this->log('AUTO-FIX SUCCESS for course ' . $original_course_id . ' in ' . $execution_time . 's - ' . 
                          'Relationships: ' . $stats['relationships_fixed'] . ', ' .
                          'Sections: ' . $stats['sections_synced'] . ', ' .
                          'Lessons: ' . $stats['lessons_synced'] . ', ' .
                          'Quizzes: ' . $stats['quizzes_synced'], 'success');
                
                // Store success in transient for admin notice
                set_transient('wpml_llms_auto_fix_success_' . $original_course_id, array(
                    'course_title' => $course->post_title,
                    'stats' => $stats,
                    'execution_time' => $execution_time,
                    'timestamp' => current_time('mysql')
                ), 3600); // 1 hour
                
                return true;
            } else {
                $this->log('AUTO-FIX FAILED for course ' . $original_course_id . ': ' . $result['error'], 'error');
                
                // Store failure in transient for admin notice
                set_transient('wpml_llms_auto_fix_error_' . $original_course_id, array(
                    'course_title' => $course->post_title,
                    'error' => $result['error'],
                    'timestamp' => current_time('mysql')
                ), 3600); // 1 hour
                
                return false;
            }
            
        } catch (Exception $e) {
            $execution_time = round((microtime(true) - $start_time), 2);
            $this->log('AUTO-FIX EXCEPTION for course ' . $original_course_id . ' after ' . $execution_time . 's: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Get post language using WPML
     * 
     * @param int $post_id Post ID
     * @return string|null Language code or null
     */
    private function get_post_language($post_id) {
        // Method 1: Use WPML filter (preferred)
        $language = apply_filters('wpml_element_language_code', null, array(
            'element_id' => $post_id,
            'element_type' => 'post_course'
        ));
        
        if ($language) {
            return $language;
        }
        
        // Method 2: Fallback to WPML function
        if (function_exists('wpml_get_language_information')) {
            $lang_info = wpml_get_language_information(null, $post_id);
            return isset($lang_info['language_code']) ? $lang_info['language_code'] : null;
        }
        
        return null;
    }
    
    /**
     * Clean up processed courses cache
     */
    public function cleanup_cache() {
        $current_time = time();
        
        foreach ($this->processed_courses as $key => $timestamp) {
            // Remove entries older than 1 hour
            if (($current_time - $timestamp) > 3600) {
                unset($this->processed_courses[$key]);
            }
        }
        
        $this->log('Cleaned up processed courses cache', 'info');
    }
    
    /**
     * Log messages with context
     * 
     * @param string $message Log message
     * @param string $type Log type (info, success, warning, error)
     */
    private function log($message, $type = 'info') {
        // Use the existing logging function if available
        if (function_exists('wpml_llms_log')) {
            wpml_llms_log('[AUTO-FIXER] ' . $message, $type);
        } elseif (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WPML-LLMS-AUTO] ' . strtoupper($type) . ': ' . $message);
        }
    }
    
    /**
     * Get statistics about auto-fixes
     * 
     * @return array Statistics
     */
    public function get_stats() {
        return array(
            'processed_courses_count' => count($this->processed_courses),
            'processed_courses' => $this->processed_courses,
            'hooks_registered' => array(
                'wpml_pro_translation_completed',
                'icl_make_duplicate',
                'save_post'
            )
        );
    }
    
    /**
     * Check if auto-fixer is working properly
     * 
     * @return array Health check results
     */
    public function health_check() {
        $checks = array();
        
        // Check if WPML is active
        $checks['wpml_active'] = class_exists('SitePress');
        
        // Check if LifterLMS is active
        $checks['lifterlms_active'] = class_exists('LifterLMS');
        
        // Check if course fixer is available
        $checks['course_fixer_available'] = class_exists('WPML_LLMS_Course_Fixer');
        
        // Check if hooks are properly registered
        $checks['hooks_registered'] = has_action('wpml_pro_translation_completed') && 
                                     has_action('icl_make_duplicate') &&
                                     has_action('save_post');
        
        // Check if required WPML functions exist
        $checks['wpml_functions_available'] = function_exists('wpml_get_language_information') && 
                                             has_filter('wpml_pro_translation_completed');
        
        return $checks;
    }
}

// Initialize the auto-fixer (singleton pattern)
WPML_LLMS_Auto_Course_Fixer::get_instance();

// Debug: Write to a custom log file to verify our code is running
if (function_exists('error_log')) {
    error_log('[WPML-AUTO-FIXER] Auto-fixer file loaded and initialized at ' . date('Y-m-d H:i:s'), 3, get_stylesheet_directory() . '/auto-fixer-debug.log');
}

/**
 * Helper function to get auto-fixer instance
 * 
 * @return WPML_LLMS_Auto_Course_Fixer
 */
function wpml_llms_get_auto_fixer() {
    return WPML_LLMS_Auto_Course_Fixer::get_instance();
}

/**
 * Admin notice for successful auto-fixes
 */
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check for recent successful auto-fixes
    global $wpdb;
    $transients = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_wpml_llms_auto_fix_success_%' 
         LIMIT 5"
    );
    
    foreach ($transients as $transient) {
        $data = maybe_unserialize($transient->option_value);
        if ($data && is_array($data)) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>WPML Auto-Fix:</strong> Successfully fixed relationships for course "' . 
                 esc_html($data['course_title']) . '" in ' . $data['execution_time'] . 's</p>';
            echo '</div>';
            
            // Delete the transient after showing
            delete_transient(str_replace('_transient_', '', $transient->option_name));
        }
    }
});
