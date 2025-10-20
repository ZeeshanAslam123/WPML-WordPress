<?php
/**
 * WPML LifterLMS Post Types Handler
 * 
 * Handles translation registration and management for all LifterLMS post types
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post Types Handler Class
 */
class WPML_LifterLMS_Post_Types {
    
    /**
     * LifterLMS post types configuration
     * @var array
     */
    private $post_types_config = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setup_post_types_config();
    }
    
    /**
     * Initialize the component
     */
    public function init() {
        add_action('init', array($this, 'register_post_types_for_translation'), 20);
        add_action('wpml_loaded', array($this, 'setup_wpml_integration'));
        
        // Handle post duplication and synchronization
        add_action('wpml_pro_translation_completed', array($this, 'handle_translation_completed'), 10, 3);
        add_action('save_post', array($this, 'handle_post_save'), 10, 2);
        
        // Handle post relationships
        add_filter('wpml_element_language_details', array($this, 'handle_element_language_details'), 10, 2);
    }
    
    /**
     * Setup post types configuration
     */
    private function setup_post_types_config() {
        $this->post_types_config = array(
            // Core content types - fully translatable
            'course' => array(
                'mode' => 'translate',
                'display_as_translated' => true,
                'duplicate_media' => true,
                'sync_custom_fields' => false,
                'sync_taxonomies' => true,
                'sync_comments' => false,
                'priority' => 'high'
            ),
            'lesson' => array(
                'mode' => 'translate',
                'display_as_translated' => true,
                'duplicate_media' => true,
                'sync_custom_fields' => false,
                'sync_taxonomies' => true,
                'sync_comments' => false,
                'priority' => 'high'
            ),
            'llms_quiz' => array(
                'mode' => 'translate',
                'display_as_translated' => true,
                'duplicate_media' => false,
                'sync_custom_fields' => false,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'high'
            ),
            'llms_question' => array(
                'mode' => 'translate',
                'display_as_translated' => true,
                'duplicate_media' => false,
                'sync_custom_fields' => false,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'high'
            ),
            'llms_membership' => array(
                'mode' => 'translate',
                'display_as_translated' => true,
                'duplicate_media' => true,
                'sync_custom_fields' => false,
                'sync_taxonomies' => true,
                'sync_comments' => false,
                'priority' => 'high'
            ),
            
            // Structural types - duplicate only
            'section' => array(
                'mode' => 'duplicate',
                'display_as_translated' => false,
                'duplicate_media' => false,
                'sync_custom_fields' => true,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'medium'
            ),
            
            // User-specific content - translate but maintain relationships
            'llms_certificate' => array(
                'mode' => 'translate',
                'display_as_translated' => true,
                'duplicate_media' => true,
                'sync_custom_fields' => false,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'medium'
            ),
            'llms_my_certificate' => array(
                'mode' => 'do_not_translate',
                'display_as_translated' => false,
                'duplicate_media' => false,
                'sync_custom_fields' => false,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'low'
            ),
            'llms_achievement' => array(
                'mode' => 'translate',
                'display_as_translated' => true,
                'duplicate_media' => true,
                'sync_custom_fields' => false,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'medium'
            ),
            
            // Email templates - fully translatable
            'llms_email' => array(
                'mode' => 'translate',
                'display_as_translated' => true,
                'duplicate_media' => false,
                'sync_custom_fields' => false,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'high'
            ),
            
            // E-commerce types - duplicate with shared data
            'llms_coupon' => array(
                'mode' => 'duplicate',
                'display_as_translated' => true,
                'duplicate_media' => false,
                'sync_custom_fields' => true,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'medium'
            ),
            'llms_order' => array(
                'mode' => 'do_not_translate',
                'display_as_translated' => false,
                'duplicate_media' => false,
                'sync_custom_fields' => false,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'low'
            ),
            'llms_transaction' => array(
                'mode' => 'do_not_translate',
                'display_as_translated' => false,
                'duplicate_media' => false,
                'sync_custom_fields' => false,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'low'
            ),
            'llms_access_plan' => array(
                'mode' => 'translate',
                'display_as_translated' => true,
                'duplicate_media' => false,
                'sync_custom_fields' => false,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'high'
            ),
            'llms_voucher' => array(
                'mode' => 'duplicate',
                'display_as_translated' => true,
                'duplicate_media' => false,
                'sync_custom_fields' => true,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'medium'
            ),
            
            // System types
            'llms_engagement' => array(
                'mode' => 'translate',
                'display_as_translated' => true,
                'duplicate_media' => false,
                'sync_custom_fields' => false,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'medium'
            ),
            'llms_form' => array(
                'mode' => 'translate',
                'display_as_translated' => true,
                'duplicate_media' => false,
                'sync_custom_fields' => false,
                'sync_taxonomies' => false,
                'sync_comments' => false,
                'priority' => 'medium'
            )
        );
        
        // Allow filtering of post types configuration
        $this->post_types_config = apply_filters('wpml_lifterlms_post_types_config', $this->post_types_config);
    }
    
    /**
     * Register post types for translation
     */
    public function register_post_types_for_translation() {
        global $sitepress;
        
        if (!$sitepress) {
            return;
        }
        
        foreach ($this->post_types_config as $post_type => $config) {
            // Register post type for translation
            do_action('wpml_register_single_string', 'WordPress', 'Post Type: ' . $post_type, $post_type);
            
            // Set translation mode
            $sitepress->set_element_language_details(
                0,
                'post_' . $post_type,
                null,
                null,
                $config['mode']
            );
            
            // Configure display settings
            if ($config['display_as_translated']) {
                add_filter('wpml_display_as_translated_post_type', function($post_types) use ($post_type) {
                    $post_types[] = $post_type;
                    return $post_types;
                });
            }
        }
        
        // Register custom post type strings for translation
        $this->register_post_type_strings();
    }
    
    /**
     * Setup WPML integration
     */
    public function setup_wpml_integration() {
        // Add post types to WPML configuration
        add_filter('wpml_config_array', array($this, 'add_wpml_config'));
        
        // Handle post type queries
        add_filter('wpml_should_use_display_as_translated_snippet', array($this, 'handle_display_as_translated'), 10, 3);
        
        // Handle post relationships
        add_filter('wpml_element_type', array($this, 'handle_element_type'), 10, 2);
    }
    
    /**
     * Register post type strings for translation
     */
    private function register_post_type_strings() {
        // Get all LifterLMS post type objects
        $post_types = get_post_types(array(), 'objects');
        
        foreach ($post_types as $post_type => $post_type_obj) {
            if (!$this->is_lifterlms_post_type($post_type)) {
                continue;
            }
            
            // Register labels for translation
            if (isset($post_type_obj->labels)) {
                foreach ($post_type_obj->labels as $label_key => $label_value) {
                    if (!empty($label_value)) {
                        do_action('wpml_register_single_string', 
                            'LifterLMS Post Types', 
                            $post_type . '_' . $label_key, 
                            $label_value
                        );
                    }
                }
            }
        }
    }
    
    /**
     * Add WPML configuration
     * @param array $config
     * @return array
     */
    public function add_wpml_config($config) {
        // Add post types configuration
        foreach ($this->post_types_config as $post_type => $type_config) {
            $config['wpml-config']['post-types']['post-type'][] = array(
                'value' => $post_type,
                'translate' => $type_config['mode'] === 'translate' ? '1' : '0',
                'display-as-translated' => $type_config['display_as_translated'] ? '1' : '0'
            );
        }
        
        return $config;
    }
    
    /**
     * Handle display as translated
     * @param bool $use_snippet
     * @param string $post_type
     * @param int $post_id
     * @return bool
     */
    public function handle_display_as_translated($use_snippet, $post_type, $post_id) {
        if ($this->is_lifterlms_post_type($post_type)) {
            $config = $this->get_post_type_config($post_type);
            return $config && $config['display_as_translated'];
        }
        
        return $use_snippet;
    }
    
    /**
     * Handle element type
     * @param string $element_type
     * @param object $element
     * @return string
     */
    public function handle_element_type($element_type, $element) {
        if (is_object($element) && isset($element->post_type)) {
            if ($this->is_lifterlms_post_type($element->post_type)) {
                return 'post_' . $element->post_type;
            }
        }
        
        return $element_type;
    }
    
    /**
     * Handle translation completed
     * @param int $new_post_id
     * @param array $fields
     * @param object $job
     */
    public function handle_translation_completed($new_post_id, $fields, $job) {
        $original_post_id = $job->original_doc_id;
        $original_post = get_post($original_post_id);
        
        if (!$original_post || !$this->is_lifterlms_post_type($original_post->post_type)) {
            return;
        }
        
        // Handle post-specific translation completion
        $this->sync_post_relationships($original_post_id, $new_post_id);
        $this->sync_post_meta($original_post_id, $new_post_id, $original_post->post_type);
    }
    
    /**
     * Handle post save
     * @param int $post_id
     * @param WP_Post $post
     */
    public function handle_post_save($post_id, $post) {
        if (!$this->is_lifterlms_post_type($post->post_type)) {
            return;
        }
        
        // Update translation status
        $this->update_translation_status($post_id, $post->post_type);
    }
    
    /**
     * Handle element language details
     * @param array $details
     * @param array $element
     * @return array
     */
    public function handle_element_language_details($details, $element) {
        if (isset($element['element_type']) && strpos($element['element_type'], 'post_') === 0) {
            $post_type = str_replace('post_', '', $element['element_type']);
            
            if ($this->is_lifterlms_post_type($post_type)) {
                $config = $this->get_post_type_config($post_type);
                if ($config) {
                    $details['translation_mode'] = $config['mode'];
                }
            }
        }
        
        return $details;
    }
    
    /**
     * Sync post relationships
     * @param int $original_post_id
     * @param int $translated_post_id
     */
    private function sync_post_relationships($original_post_id, $translated_post_id) {
        // Handle course-lesson relationships
        $this->sync_course_lesson_relationships($original_post_id, $translated_post_id);
        
        // Handle quiz relationships
        $this->sync_quiz_relationships($original_post_id, $translated_post_id);
        
        // Handle membership relationships
        $this->sync_membership_relationships($original_post_id, $translated_post_id);
    }
    
    /**
     * Sync course-lesson relationships
     * @param int $original_post_id
     * @param int $translated_post_id
     */
    private function sync_course_lesson_relationships($original_post_id, $translated_post_id) {
        $original_post = get_post($original_post_id);
        $translated_post = get_post($translated_post_id);
        
        if ($original_post->post_type === 'course') {
            // Get course lessons
            $lessons = get_post_meta($original_post_id, '_llms_lessons', true);
            if ($lessons) {
                $translated_lessons = array();
                foreach ($lessons as $lesson_id) {
                    $translated_lesson_id = apply_filters('wpml_object_id', $lesson_id, 'lesson', false, $translated_post->post_language);
                    if ($translated_lesson_id) {
                        $translated_lessons[] = $translated_lesson_id;
                    }
                }
                update_post_meta($translated_post_id, '_llms_lessons', $translated_lessons);
            }
        }
    }
    
    /**
     * Sync quiz relationships
     * @param int $original_post_id
     * @param int $translated_post_id
     */
    private function sync_quiz_relationships($original_post_id, $translated_post_id) {
        $original_post = get_post($original_post_id);
        
        if ($original_post->post_type === 'lesson') {
            // Get lesson quiz
            $quiz_id = get_post_meta($original_post_id, '_llms_quiz', true);
            if ($quiz_id) {
                $translated_quiz_id = apply_filters('wpml_object_id', $quiz_id, 'llms_quiz', false, get_post_meta($translated_post_id, '_wpml_language_code', true));
                if ($translated_quiz_id) {
                    update_post_meta($translated_post_id, '_llms_quiz', $translated_quiz_id);
                }
            }
        }
    }
    
    /**
     * Sync membership relationships
     * @param int $original_post_id
     * @param int $translated_post_id
     */
    private function sync_membership_relationships($original_post_id, $translated_post_id) {
        // Handle membership-specific relationships
        // This can be extended based on specific membership requirements
    }
    
    /**
     * Sync post meta
     * @param int $original_post_id
     * @param int $translated_post_id
     * @param string $post_type
     */
    private function sync_post_meta($original_post_id, $translated_post_id, $post_type) {
        $config = $this->get_post_type_config($post_type);
        
        if (!$config || !$config['sync_custom_fields']) {
            return;
        }
        
        // Get meta fields that should be synced
        $sync_fields = $this->get_sync_meta_fields($post_type);
        
        foreach ($sync_fields as $meta_key) {
            $meta_value = get_post_meta($original_post_id, $meta_key, true);
            if ($meta_value !== '') {
                update_post_meta($translated_post_id, $meta_key, $meta_value);
            }
        }
    }
    
    /**
     * Get meta fields that should be synced
     * @param string $post_type
     * @return array
     */
    private function get_sync_meta_fields($post_type) {
        $sync_fields = array();
        
        switch ($post_type) {
            case 'course':
                $sync_fields = array(
                    '_llms_length',
                    '_llms_difficulty',
                    '_llms_track',
                    '_llms_course_prerequisites',
                    '_llms_course_capacity',
                    '_llms_enable_capacity',
                    '_llms_start_date',
                    '_llms_end_date'
                );
                break;
                
            case 'lesson':
                $sync_fields = array(
                    '_llms_parent_course',
                    '_llms_parent_section',
                    '_llms_order',
                    '_llms_lesson_prerequisite',
                    '_llms_drip_method',
                    '_llms_drip_days',
                    '_llms_drip_date'
                );
                break;
                
            case 'llms_quiz':
                $sync_fields = array(
                    '_llms_attempts_allowed',
                    '_llms_limit_attempts',
                    '_llms_time_limit',
                    '_llms_limit_time',
                    '_llms_passing_percent',
                    '_llms_show_correct_answer',
                    '_llms_random_questions'
                );
                break;
        }
        
        return apply_filters('wpml_lifterlms_sync_meta_fields', $sync_fields, $post_type);
    }
    
    /**
     * Update translation status
     * @param int $post_id
     * @param string $post_type
     */
    private function update_translation_status($post_id, $post_type) {
        if (!$this->is_lifterlms_post_type($post_type)) {
            return;
        }
        
        // Mark post as needing translation update
        update_post_meta($post_id, '_wpml_lifterlms_needs_update', time());
    }
    
    /**
     * Check if post type is LifterLMS post type
     * @param string $post_type
     * @return bool
     */
    private function is_lifterlms_post_type($post_type) {
        return isset($this->post_types_config[$post_type]) || 
               strpos($post_type, 'llms_') === 0 || 
               in_array($post_type, array('course', 'lesson', 'section'));
    }
    
    /**
     * Get post type configuration
     * @param string $post_type
     * @return array|null
     */
    private function get_post_type_config($post_type) {
        return isset($this->post_types_config[$post_type]) ? $this->post_types_config[$post_type] : null;
    }
    
    /**
     * Get all configured post types
     * @return array
     */
    public function get_post_types() {
        return array_keys($this->post_types_config);
    }
    
    /**
     * Get post type configuration
     * @param string $post_type
     * @return array
     */
    public function get_config($post_type = null) {
        if ($post_type) {
            return $this->get_post_type_config($post_type);
        }
        
        return $this->post_types_config;
    }
}

