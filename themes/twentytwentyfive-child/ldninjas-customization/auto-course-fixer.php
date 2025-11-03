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

        // add_action('save_post', array($this, 'on_post_saved'), 20, 3);
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
        
        $course_related_types = array('course', 'section', 'lesson', 'llms_quiz');
        if (!in_array($post->post_type, $course_related_types)) {
            return;
        }
        
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

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        $course_related_types = array('course', 'section', 'lesson', 'llms_quiz');
        if (!in_array($post->post_type, $course_related_types)) {
            return;
        }
        
        $language = $this->get_post_language($post_id);
        if (!$language || $language === 'en') {
            return;
        }
        
        $this->fix_relationships_for_post($post_id, $post->post_type);
    }
    
    /**
     * Fix relationships for a post - uses the exact same logic as the manual button
     * 
     * @param int $post_id Post ID
     * @param string $post_type Post type
     */
    private function fix_relationships_for_post($post_id, $post_type) {

        $english_course_id = $this->find_english_course_id($post_id, $post_type);
        
        if (!$english_course_id) {
            return;
        }

        $fixer = new WPML_LLMS_Course_Fixer();
        $fixer->fix_course_relationships($english_course_id);
    }
    
    /**
     * Find the English course ID for any course-related post
     * 
     * @param int $post_id Post ID
     * @param string $post_type Post type
     * @return int|null English course ID or null if not found
     */
    private function find_english_course_id($post_id, $post_type) {

        $english_post_id = apply_filters('wpml_object_id', $post_id, $post_type, false, 'en');
        
        if (!$english_post_id) {
            return null;
        }
        
        if ($post_type === 'course') {
            return $english_post_id;
        }
        
        switch ($post_type) {
            case 'section':
                return get_post_meta($english_post_id, '_llms_parent_course', true);
                
            case 'lesson':
                $parent_section = get_post_meta($english_post_id, '_llms_parent_section', true);
                if ($parent_section) {
                    return get_post_meta($parent_section, '_llms_parent_course', true);
                }
                break;
                
            case 'llms_quiz':
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

        $post = get_post($post_id);
        if (!$post) {
            return null;
        }
        
        $element_type = 'post_' . $post->post_type;
        $language = apply_filters('wpml_element_language_code', null, array(
            'element_id' => $post_id,
            'element_type' => $element_type
        ));
        
        if ($language) {
            return $language;
        }
        
        if (function_exists('wpml_get_language_information')) {
            $lang_info = wpml_get_language_information(null, $post_id);
            return isset($lang_info['language_code']) ? $lang_info['language_code'] : null;
        }
        
        return null;
    }
}

WPML_LLMS_Auto_Course_Fixer::get_instance();
