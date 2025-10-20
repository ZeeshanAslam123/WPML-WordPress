<?php
/**
 * WPML LifterLMS Relationships Handler
 * 
 * Handles synchronization of relationships between translated LifterLMS content
 * This is crucial for making sections, lessons, quizzes appear in translated courses
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Relationships Handler Class
 */
class WPML_LifterLMS_Relationships {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Handle relationship synchronization when content is translated
        add_action('wpml_pro_translation_completed', array($this, 'sync_relationships_on_translation'), 10, 3);
        add_action('icl_make_duplicate', array($this, 'sync_relationships_on_duplicate'), 10, 4);
        
        // Handle relationship updates when content is saved
        add_action('save_post', array($this, 'sync_relationships_on_save'), 20, 3);
        
        // Note: We don't need frontend filtering hooks because LifterLMS uses WP_Query with meta_query
        // WPML automatically handles language filtering for WP_Query when relationships are properly synced
    }
    
    /**
     * Initialize the component
     */
    public function init() {
        // Component is initialized via constructor
        return true;
    }
    
    /**
     * Sync relationships when translation is completed
     * 
     * @param int $new_post_id
     * @param array $fields
     * @param object $job
     */
    public function sync_relationships_on_translation($new_post_id, $fields, $job) {
        if (!$new_post_id || !isset($job->original_doc_id)) {
            return;
        }
        
        $original_id = $job->original_doc_id;
        $post_type = get_post_type($new_post_id);
        
        switch ($post_type) {
            case 'course':
                $this->sync_course_relationships($original_id, $new_post_id);
                break;
            case 'section':
                $this->sync_section_relationships($original_id, $new_post_id);
                break;
            case 'lesson':
                $this->sync_lesson_relationships($original_id, $new_post_id);
                break;
            case 'llms_quiz':
                $this->sync_quiz_relationships($original_id, $new_post_id);
                break;
            case 'llms_access_plan':
                $this->sync_access_plan_relationships($original_id, $new_post_id);
                break;
        }
    }
    
    /**
     * Sync relationships when content is duplicated
     * 
     * @param int $master_post_id
     * @param string $lang
     * @param array $postarr
     * @param int $id
     */
    public function sync_relationships_on_duplicate($master_post_id, $lang, $postarr, $id) {
        if (!$id || !$master_post_id) {
            return;
        }
        
        $post_type = get_post_type($id);
        
        switch ($post_type) {
            case 'course':
                $this->sync_course_relationships($master_post_id, $id);
                break;
            case 'section':
                $this->sync_section_relationships($master_post_id, $id);
                break;
            case 'lesson':
                $this->sync_lesson_relationships($master_post_id, $id);
                break;
            case 'llms_quiz':
                $this->sync_quiz_relationships($master_post_id, $id);
                break;
            case 'llms_access_plan':
                $this->sync_access_plan_relationships($master_post_id, $id);
                break;
        }
    }
    
    /**
     * Sync relationships when content is saved
     * 
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     */
    public function sync_relationships_on_save($post_id, $post, $update) {
        // Only sync on updates, not new posts
        if (!$update) {
            return;
        }
        
        // Get all translations of this post
        $translations = apply_filters('wpml_get_element_translations', null, $post_id, 'post_' . $post->post_type);
        
        if (!$translations || count($translations) <= 1) {
            return;
        }
        
        // Sync relationships for all translations
        foreach ($translations as $lang => $translation) {
            if ($translation->element_id != $post_id) {
                $this->sync_relationships_on_translation($translation->element_id, array(), (object)array('original_doc_id' => $post_id));
            }
        }
    }
    
    /**
     * Sync course relationships (sections, access plans)
     * 
     * @param int $original_course_id
     * @param int $translated_course_id
     */
    private function sync_course_relationships($original_course_id, $translated_course_id) {
        // Debug logging
        error_log("WPML-LifterLMS: Syncing course relationships - Original: {$original_course_id}, Translated: {$translated_course_id}");
        
        // Get original course sections using LifterLMS method
        $original_sections = get_posts(array(
            'post_type' => 'section',
            'meta_key' => '_llms_parent_course',
            'meta_value' => $original_course_id,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'meta_value_num',
            'meta_key' => '_llms_order',
            'order' => 'ASC'
        ));
        
        error_log("WPML-LifterLMS: Found " . count($original_sections) . " sections for course {$original_course_id}");
        
        foreach ($original_sections as $section) {
            $translated_section_id = apply_filters('wpml_object_id', $section->ID, 'section', false, $this->get_post_language($translated_course_id));
            if ($translated_section_id) {
                error_log("WPML-LifterLMS: Syncing section {$section->ID} -> {$translated_section_id}");
                
                // Update section's parent course to point to translated course
                update_post_meta($translated_section_id, '_llms_parent_course', $translated_course_id);
                
                // Also sync the section's lessons
                $this->sync_section_relationships($section->ID, $translated_section_id);
            } else {
                error_log("WPML-LifterLMS: No translated section found for {$section->ID}");
            }
        }
        
        // Sync access plans
        $original_access_plans = get_posts(array(
            'post_type' => 'llms_access_plan',
            'meta_key' => '_llms_product_id',
            'meta_value' => $original_course_id,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        foreach ($original_access_plans as $plan) {
            $translated_plan_id = apply_filters('wpml_object_id', $plan->ID, 'llms_access_plan', false, $this->get_post_language($translated_course_id));
            if ($translated_plan_id) {
                update_post_meta($translated_plan_id, '_llms_product_id', $translated_course_id);
            }
        }
    }
    
    /**
     * Sync section relationships (lessons, parent course)
     * 
     * @param int $original_section_id
     * @param int $translated_section_id
     */
    private function sync_section_relationships($original_section_id, $translated_section_id) {
        // Get original section lessons using LifterLMS method
        $original_lessons = get_posts(array(
            'post_type' => 'lesson',
            'meta_key' => '_llms_parent_section',
            'meta_value' => $original_section_id,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'meta_value_num',
            'meta_key' => '_llms_order',
            'order' => 'ASC'
        ));
        
        foreach ($original_lessons as $lesson) {
            $translated_lesson_id = apply_filters('wpml_object_id', $lesson->ID, 'lesson', false, $this->get_post_language($translated_section_id));
            if ($translated_lesson_id) {
                // Update lesson's parent section to point to translated section
                update_post_meta($translated_lesson_id, '_llms_parent_section', $translated_section_id);
                
                // Also update lesson's parent course
                $translated_parent_course = get_post_meta($translated_section_id, '_llms_parent_course', true);
                if ($translated_parent_course) {
                    update_post_meta($translated_lesson_id, '_llms_parent_course', $translated_parent_course);
                }
                
                // Also sync the lesson's quiz
                $this->sync_lesson_relationships($lesson->ID, $translated_lesson_id);
            }
        }
        
        // Sync parent course (this should already be set, but ensure it's correct)
        $original_parent_course = get_post_meta($original_section_id, '_llms_parent_course', true);
        if ($original_parent_course) {
            $translated_parent_course = apply_filters('wpml_object_id', $original_parent_course, 'course', false, $this->get_post_language($translated_section_id));
            if ($translated_parent_course) {
                update_post_meta($translated_section_id, '_llms_parent_course', $translated_parent_course);
            }
        }
    }
    
    /**
     * Sync lesson relationships (parent section, quiz)
     * 
     * @param int $original_lesson_id
     * @param int $translated_lesson_id
     */
    private function sync_lesson_relationships($original_lesson_id, $translated_lesson_id) {
        // Sync parent section (should already be set, but ensure it's correct)
        $original_parent_section = get_post_meta($original_lesson_id, '_llms_parent_section', true);
        if ($original_parent_section) {
            $translated_parent_section = apply_filters('wpml_object_id', $original_parent_section, 'section', false, $this->get_post_language($translated_lesson_id));
            if ($translated_parent_section) {
                update_post_meta($translated_lesson_id, '_llms_parent_section', $translated_parent_section);
            }
        }
        
        // Sync parent course (should already be set, but ensure it's correct)
        $original_parent_course = get_post_meta($original_lesson_id, '_llms_parent_course', true);
        if ($original_parent_course) {
            $translated_parent_course = apply_filters('wpml_object_id', $original_parent_course, 'course', false, $this->get_post_language($translated_lesson_id));
            if ($translated_parent_course) {
                update_post_meta($translated_lesson_id, '_llms_parent_course', $translated_parent_course);
            }
        }
        
        // Sync quiz - check if lesson has a quiz
        $original_quiz = get_post_meta($original_lesson_id, '_llms_quiz', true);
        if ($original_quiz) {
            $translated_quiz = apply_filters('wpml_object_id', $original_quiz, 'llms_quiz', false, $this->get_post_language($translated_lesson_id));
            if ($translated_quiz) {
                update_post_meta($translated_lesson_id, '_llms_quiz', $translated_quiz);
                update_post_meta($translated_quiz, '_llms_parent_lesson', $translated_lesson_id);
            }
        }
        
        // Also check for quizzes that point to this lesson as parent
        $original_quizzes = get_posts(array(
            'post_type' => 'llms_quiz',
            'meta_key' => '_llms_parent_lesson',
            'meta_value' => $original_lesson_id,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        foreach ($original_quizzes as $quiz) {
            $translated_quiz_id = apply_filters('wpml_object_id', $quiz->ID, 'llms_quiz', false, $this->get_post_language($translated_lesson_id));
            if ($translated_quiz_id) {
                update_post_meta($translated_quiz_id, '_llms_parent_lesson', $translated_lesson_id);
                update_post_meta($translated_lesson_id, '_llms_quiz', $translated_quiz_id);
            }
        }
    }
    
    /**
     * Sync quiz relationships (parent lesson)
     * 
     * @param int $original_quiz_id
     * @param int $translated_quiz_id
     */
    private function sync_quiz_relationships($original_quiz_id, $translated_quiz_id) {
        // Sync parent lesson
        $original_parent_lesson = get_post_meta($original_quiz_id, '_llms_parent_lesson', true);
        if ($original_parent_lesson) {
            $translated_parent_lesson = apply_filters('wpml_object_id', $original_parent_lesson, 'lesson', false, $this->get_post_language($translated_quiz_id));
            if ($translated_parent_lesson) {
                update_post_meta($translated_quiz_id, '_llms_parent_lesson', $translated_parent_lesson);
                update_post_meta($translated_parent_lesson, '_llms_quiz', $translated_quiz_id);
            }
        }
    }
    
    /**
     * Sync access plan relationships (product)
     * 
     * @param int $original_plan_id
     * @param int $translated_plan_id
     */
    private function sync_access_plan_relationships($original_plan_id, $translated_plan_id) {
        // Sync product (course/membership)
        $original_product = get_post_meta($original_plan_id, '_llms_product_id', true);
        if ($original_product) {
            $product_type = get_post_type($original_product);
            $translated_product = apply_filters('wpml_object_id', $original_product, $product_type, false, $this->get_post_language($translated_plan_id));
            if ($translated_product) {
                update_post_meta($translated_plan_id, '_llms_product_id', $translated_product);
            }
        }
    }
    
    // Frontend filtering methods removed - not needed since LifterLMS uses WP_Query with meta_query
    // and WPML automatically handles language filtering for properly synced relationships
    
    /**
     * Get post language
     * 
     * @param int $post_id
     * @return string
     */
    private function get_post_language($post_id) {
        return apply_filters('wpml_element_language_code', null, array('element_id' => $post_id, 'element_type' => 'post_' . get_post_type($post_id)));
    }
    
    /**
     * Check if WPML is active
     * 
     * @return bool
     */
    private function is_wpml_active() {
        return defined('ICL_SITEPRESS_VERSION');
    }
}
