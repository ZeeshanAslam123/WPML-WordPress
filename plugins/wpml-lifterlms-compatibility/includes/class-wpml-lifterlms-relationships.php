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
        
        // Handle frontend queries to show correct translated relationships
        add_filter('llms_get_course_sections', array($this, 'filter_course_sections'), 10, 2);
        add_filter('llms_get_section_lessons', array($this, 'filter_section_lessons'), 10, 2);
        add_filter('llms_get_lesson_quiz', array($this, 'filter_lesson_quiz'), 10, 2);
        add_filter('llms_get_course_access_plans', array($this, 'filter_course_access_plans'), 10, 2);
        
        // Handle LifterLMS queries
        add_filter('posts_where', array($this, 'filter_posts_where'), 10, 2);
        add_filter('posts_join', array($this, 'filter_posts_join'), 10, 2);
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
        // Get original course sections
        $original_sections = get_post_meta($original_course_id, '_llms_sections', true);
        
        if (!empty($original_sections)) {
            $translated_sections = array();
            
            foreach ($original_sections as $section_id) {
                $translated_section_id = apply_filters('wpml_object_id', $section_id, 'section', false, $this->get_post_language($translated_course_id));
                if ($translated_section_id) {
                    $translated_sections[] = $translated_section_id;
                    
                    // Update section's parent course
                    update_post_meta($translated_section_id, '_llms_parent_course', $translated_course_id);
                }
            }
            
            // Update translated course sections
            if (!empty($translated_sections)) {
                update_post_meta($translated_course_id, '_llms_sections', $translated_sections);
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
        // Get original section lessons
        $original_lessons = get_post_meta($original_section_id, '_llms_lessons', true);
        
        if (!empty($original_lessons)) {
            $translated_lessons = array();
            
            foreach ($original_lessons as $lesson_id) {
                $translated_lesson_id = apply_filters('wpml_object_id', $lesson_id, 'lesson', false, $this->get_post_language($translated_section_id));
                if ($translated_lesson_id) {
                    $translated_lessons[] = $translated_lesson_id;
                    
                    // Update lesson's parent section
                    update_post_meta($translated_lesson_id, '_llms_parent_section', $translated_section_id);
                }
            }
            
            // Update translated section lessons
            if (!empty($translated_lessons)) {
                update_post_meta($translated_section_id, '_llms_lessons', $translated_lessons);
            }
        }
        
        // Sync parent course
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
        // Sync parent section
        $original_parent_section = get_post_meta($original_lesson_id, '_llms_parent_section', true);
        if ($original_parent_section) {
            $translated_parent_section = apply_filters('wpml_object_id', $original_parent_section, 'section', false, $this->get_post_language($translated_lesson_id));
            if ($translated_parent_section) {
                update_post_meta($translated_lesson_id, '_llms_parent_section', $translated_parent_section);
            }
        }
        
        // Sync parent course
        $original_parent_course = get_post_meta($original_lesson_id, '_llms_parent_course', true);
        if ($original_parent_course) {
            $translated_parent_course = apply_filters('wpml_object_id', $original_parent_course, 'course', false, $this->get_post_language($translated_lesson_id));
            if ($translated_parent_course) {
                update_post_meta($translated_lesson_id, '_llms_parent_course', $translated_parent_course);
            }
        }
        
        // Sync quiz
        $original_quiz = get_post_meta($original_lesson_id, '_llms_quiz', true);
        if ($original_quiz) {
            $translated_quiz = apply_filters('wpml_object_id', $original_quiz, 'llms_quiz', false, $this->get_post_language($translated_lesson_id));
            if ($translated_quiz) {
                update_post_meta($translated_lesson_id, '_llms_quiz', $translated_quiz);
                update_post_meta($translated_quiz, '_llms_parent_lesson', $translated_lesson_id);
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
    
    /**
     * Filter course sections to show translated versions
     * 
     * @param array $sections
     * @param int $course_id
     * @return array
     */
    public function filter_course_sections($sections, $course_id) {
        if (!$this->is_wpml_active() || !$sections) {
            return $sections;
        }
        
        $current_lang = apply_filters('wpml_current_language', null);
        $translated_sections = array();
        
        foreach ($sections as $section) {
            $translated_section_id = apply_filters('wpml_object_id', $section->ID, 'section', false, $current_lang);
            if ($translated_section_id) {
                $translated_sections[] = get_post($translated_section_id);
            }
        }
        
        return $translated_sections;
    }
    
    /**
     * Filter section lessons to show translated versions
     * 
     * @param array $lessons
     * @param int $section_id
     * @return array
     */
    public function filter_section_lessons($lessons, $section_id) {
        if (!$this->is_wpml_active() || !$lessons) {
            return $lessons;
        }
        
        $current_lang = apply_filters('wpml_current_language', null);
        $translated_lessons = array();
        
        foreach ($lessons as $lesson) {
            $translated_lesson_id = apply_filters('wpml_object_id', $lesson->ID, 'lesson', false, $current_lang);
            if ($translated_lesson_id) {
                $translated_lessons[] = get_post($translated_lesson_id);
            }
        }
        
        return $translated_lessons;
    }
    
    /**
     * Filter lesson quiz to show translated version
     * 
     * @param object $quiz
     * @param int $lesson_id
     * @return object
     */
    public function filter_lesson_quiz($quiz, $lesson_id) {
        if (!$this->is_wpml_active() || !$quiz) {
            return $quiz;
        }
        
        $current_lang = apply_filters('wpml_current_language', null);
        $translated_quiz_id = apply_filters('wpml_object_id', $quiz->ID, 'llms_quiz', false, $current_lang);
        
        if ($translated_quiz_id && $translated_quiz_id != $quiz->ID) {
            return get_post($translated_quiz_id);
        }
        
        return $quiz;
    }
    
    /**
     * Filter course access plans to show translated versions
     * 
     * @param array $plans
     * @param int $course_id
     * @return array
     */
    public function filter_course_access_plans($plans, $course_id) {
        if (!$this->is_wpml_active() || !$plans) {
            return $plans;
        }
        
        $current_lang = apply_filters('wpml_current_language', null);
        $translated_plans = array();
        
        foreach ($plans as $plan) {
            $translated_plan_id = apply_filters('wpml_object_id', $plan->ID, 'llms_access_plan', false, $current_lang);
            if ($translated_plan_id) {
                $translated_plans[] = get_post($translated_plan_id);
            }
        }
        
        return $translated_plans;
    }
    
    /**
     * Filter posts WHERE clause for LifterLMS queries
     * 
     * @param string $where
     * @param WP_Query $query
     * @return string
     */
    public function filter_posts_where($where, $query) {
        if (!$this->is_wpml_active() || is_admin()) {
            return $where;
        }
        
        // Only filter LifterLMS post types
        $post_type = $query->get('post_type');
        $lifterlms_types = array('course', 'lesson', 'section', 'llms_quiz', 'llms_access_plan');
        
        if (!in_array($post_type, $lifterlms_types)) {
            return $where;
        }
        
        // Let WPML handle the language filtering
        return $where;
    }
    
    /**
     * Filter posts JOIN clause for LifterLMS queries
     * 
     * @param string $join
     * @param WP_Query $query
     * @return string
     */
    public function filter_posts_join($join, $query) {
        if (!$this->is_wpml_active() || is_admin()) {
            return $join;
        }
        
        // Only filter LifterLMS post types
        $post_type = $query->get('post_type');
        $lifterlms_types = array('course', 'lesson', 'section', 'llms_quiz', 'llms_access_plan');
        
        if (!in_array($post_type, $lifterlms_types)) {
            return $join;
        }
        
        // Let WPML handle the language filtering
        return $join;
    }
    
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
