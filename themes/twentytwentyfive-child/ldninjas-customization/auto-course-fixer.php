<?php
/**
 * WPML LifterLMS Auto Course Fixer
 * 
 * Automatically fixes course relationships when WPML translations are completed
 * Uses the exact same logic as the manual "Fix Relationships" button
 * 
 * @package TwentyTwentyFive_Child
 * @version 2.0.0
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
     * Constructor
     */
    private function __construct() {
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
     * Initialize hooks - simple and direct
     */
    private function init_hooks() {
        // Primary hook - save_post catches all post saves including WPML translations
        add_action('save_post', array($this, 'on_post_saved'), 20, 3);
        
        // WPML translation completion hook as backup
        add_action('wpml_pro_translation_completed', array($this, 'on_translation_completed'), 10, 3);
    }
    
    /**
     * Handle WPML translation completion
     * 
     * @param int $new_post_id New translated post ID
     * @param array $fields Translation fields
     * @param object $job Translation job
     */
    public function on_translation_completed($new_post_id, $fields, $job) {
        $post = get_post($new_post_id);
        if (!$post) {
            return;
        }
        
        // Only process course-related content
        $course_related_types = array('course', 'section', 'lesson', 'llms_quiz');
        if (!in_array($post->post_type, $course_related_types)) {
            return;
        }
        
        // Find the course and fix relationships
        $this->fix_relationships_for_post($new_post_id, $post->post_type);
    }
    
    /**
     * Handle post save events
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public function on_post_saved($post_id, $post, $update) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Only process course-related content
        $course_related_types = array('course', 'section', 'lesson', 'llms_quiz');
        if (!in_array($post->post_type, $course_related_types)) {
            return;
        }
        
        // Get post language - only process non-English posts
        $language = $this->get_post_language($post_id);
        if (!$language || $language === 'en') {
            return;
        }
        
        // Fix relationships for this post
        $this->fix_relationships_for_post($post_id, $post->post_type);
    }
    
    /**
     * Fix relationships for a post - uses the exact same logic as the manual button
     * 
     * @param int $post_id Post ID
     * @param string $post_type Post type
     */
    private function fix_relationships_for_post($post_id, $post_type) {
        // Find the English course ID
        $english_course_id = $this->find_english_course_id($post_id, $post_type);
        
        if (!$english_course_id) {
            return;
        }
        
        // Use the exact same logic as the manual "Fix Relationships" button
        try {
            $fixer = new WPML_LLMS_Course_Fixer();
            $result = $fixer->fix_course_relationships($english_course_id);
            
            // Log success (optional)
            if (function_exists('wpml_llms_log')) {
                wpml_llms_log('Auto-fixed relationships for course ' . $english_course_id . ' (triggered by ' . $post_type . ' ' . $post_id . ')', 'success');
            }
            
        } catch (Exception $e) {
            // Log error (optional)
            if (function_exists('wpml_llms_log')) {
                wpml_llms_log('Auto-fix failed for course ' . $english_course_id . ': ' . $e->getMessage(), 'error');
            }
        }
    }
    
    /**
     * Find the English course ID for any course-related post
     * 
     * @param int $post_id Post ID
     * @param string $post_type Post type
     * @return int|null English course ID or null if not found
     */
    private function find_english_course_id($post_id, $post_type) {
        // Get the English version of this post
        $english_post_id = apply_filters('wpml_object_id', $post_id, $post_type, false, 'en');
        
        if (!$english_post_id) {
            return null;
        }
        
        // If it's already a course, return it
        if ($post_type === 'course') {
            return $english_post_id;
        }
        
        // For sections, lessons, quizzes - find the parent course
        switch ($post_type) {
            case 'section':
                // Section -> Course
                return get_post_meta($english_post_id, '_llms_parent_course', true);
                
            case 'lesson':
                // Lesson -> Section -> Course
                $parent_section = get_post_meta($english_post_id, '_llms_parent_section', true);
                if ($parent_section) {
                    return get_post_meta($parent_section, '_llms_parent_course', true);
                }
                break;
                
            case 'llms_quiz':
                // Quiz -> Lesson -> Section -> Course
                $parent_lesson = get_post_meta($english_post_id, '_llms_parent_lesson', true);
                if ($parent_lesson) {
                    $parent_section = get_post_meta($parent_lesson, '_llms_parent_section', true);
                    if ($parent_section) {
                        return get_post_meta($parent_section, '_llms_parent_course', true);
                    }
                }
                break;
        }
        
        return null;
    }
    
    /**
     * Get post language using WPML
     * 
     * @param int $post_id Post ID
     * @return string|null Language code or null
     */
    private function get_post_language($post_id) {
        // Get the post type to construct the correct element type
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }
        
        // Method 1: Use WPML filter (preferred)
        $element_type = 'post_' . $post->post_type;
        $language = apply_filters('wpml_element_language_code', null, array(
            'element_id' => $post_id,
            'element_type' => $element_type
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
}

// Auto-fixer class loaded - initialization handled by functions.php
WPML_LLMS_Auto_Course_Fixer::get_instance();