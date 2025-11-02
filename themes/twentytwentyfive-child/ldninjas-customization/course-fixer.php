<?php
/**
 * WPML LifterLMS Course Fixer
 * 
 * Core logic for fixing relationships between WPML and LifterLMS
 * 
 * @package TwentyTwentyFive_Child
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPML_LLMS_Course_Fixer {
    
    private $stats = array(
        'courses_processed' => 0,
        'relationships_fixed' => 0,
        'sections_synced' => 0,
        'lessons_synced' => 0,
        'quizzes_synced' => 0,
        'questions_synced' => 0,
        'quiz_settings_synced' => 0,
        'access_plans_synced' => 0,
        'course_settings_synced' => 0,
        'errors' => 0
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_stats();
    }
    
    /**
     * Initialize statistics
     */
    private function init_stats() {
        $this->stats = array(
            'courses_processed' => 0,
            'relationships_fixed' => 0,
            'sections_synced' => 0,
            'lessons_synced' => 0,
            'quizzes_synced' => 0,
            'questions_synced' => 0,
            'quiz_settings_synced' => 0,
            'access_plans_synced' => 0,
            'course_settings_synced' => 0,
            'enrollments_synced' => 0,
            'errors' => 0,
            'start_time' => current_time('mysql'),
            'end_time' => null
        );
    }
    
    /**
     * Fix course relationships
     */
    public function fix_course_relationships($course_id) {
        
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'course') {
            throw new Exception('Invalid course ID: ' . $course_id);
        }
        
        $translations = $this->get_course_translations($course_id);
        
        $this->fix_wpml_relationships($course_id, $translations);
        
        $this->sync_course_sections($course_id, $translations);
        
        $this->sync_course_lessons($course_id, $translations);
        
        $this->sync_course_quizzes($course_id, $translations);
        
        $this->sync_access_plans($course_id, $translations);
        
        $this->sync_course_enrollments($course_id, $translations);
        
        $this->sync_course_metadata($course_id, $translations);
        
        $this->stats['courses_processed']++;
        $this->stats['end_time'] = current_time('mysql');
        
        return array(
            'success' => true,
            'stats' => $this->stats
        );
    }
    
    /**
     * Get course translations
     */
    private function get_course_translations($course_id) {
        $translations = array();
        
        if (!function_exists('wpml_get_language_information')) {
            return $translations;
        }
        
        $languages = apply_filters('wpml_active_languages', null);
        
        foreach ($languages as $lang_code => $language) {
            $translated_id = apply_filters('wpml_object_id', $course_id, 'course', false, $lang_code);
            
            if ($translated_id && $translated_id !== $course_id) {
                $translated_post = get_post($translated_id);
                if ($translated_post) {
                    $translations[$lang_code] = array(
                        'id' => $translated_id,
                        'title' => $translated_post->post_title,
                        'language' => $lang_code,
                        'language_name' => $language['native_name']
                    );
                    
                }
            }
        }
        
        return $translations;
    }
    
    /**
     * Fix WPML relationships
     */
    private function fix_wpml_relationships($course_id, $translations) {
        
        global $wpdb;
        
        foreach ($translations as $lang_code => $translation) {
            // Check if relationship exists in icl_translations table
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}icl_translations 
                WHERE element_id = %d AND element_type = 'post_course'",
                $translation['id']
            ));
            
            if (!$existing) {
                // Create missing translation relationship
                $trid = $wpdb->get_var($wpdb->prepare(
                    "SELECT trid FROM {$wpdb->prefix}icl_translations 
                    WHERE element_id = %d AND element_type = 'post_course'",
                    $course_id
                ));
                
                if ($trid) {
                    $wpdb->insert(
                        $wpdb->prefix . 'icl_translations',
                        array(
                            'element_type' => 'post_course',
                            'element_id' => $translation['id'],
                            'trid' => $trid,
                            'language_code' => $lang_code,
                            'source_language_code' => 'en'
                        ),
                        array('%s', '%d', '%d', '%s', '%s')
                    );
                    
                    $this->stats['relationships_fixed']++;
                }
            }
        }
    }
    
    /**
     * Sync course sections
     */
    private function sync_course_sections($course_id, $translations) {
        
        if (!class_exists('LLMS_Course')) {
            return;
        }
        
        $main_course = new LLMS_Course($course_id);
        $main_sections = $main_course->get_sections('ids');
        
        if (empty($main_sections)) {
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            
            // Sync section relationships
            foreach ($main_sections as $section_id) {
                $translated_section_id = apply_filters('wpml_object_id', $section_id, 'section', false, $lang_code);
                
                if ($translated_section_id && $translated_section_id !== $section_id) {
                    // Fix section → course relationship
                    $current_parent_course = get_post_meta($translated_section_id, '_llms_parent_course', true);
                    if ($current_parent_course != $translation['id']) {
                        update_post_meta($translated_section_id, '_llms_parent_course', $translation['id']);
                        $this->stats['sections_synced']++;
                    }
                    
                    // Fix section order meta key (critical for LifterLMS to find sections)
                    $main_section_order = get_post_meta($section_id, '_llms_order', true);
                    if ($main_section_order) {
                        $current_section_order = get_post_meta($translated_section_id, '_llms_order', true);
                        if ($current_section_order != $main_section_order) {
                            update_post_meta($translated_section_id, '_llms_order', $main_section_order);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Sync course lessons
     */
    private function sync_course_lessons($course_id, $translations) {
        
        if (!class_exists('LLMS_Course')) {
            return;
        }
        
        $main_course = new LLMS_Course($course_id);
        $main_lessons = $main_course->get_lessons('ids');
        
        if (empty($main_lessons)) {
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            
            foreach ($main_lessons as $lesson_id) {
                $translated_lesson_id = apply_filters('wpml_object_id', $lesson_id, 'lesson', false, $lang_code);
                
                if ($translated_lesson_id && $translated_lesson_id !== $lesson_id) {
                    // Fix lesson → course relationship
                    $current_parent_course = get_post_meta($translated_lesson_id, '_llms_parent_course', true);
                    if ($current_parent_course != $translation['id']) {
                        update_post_meta($translated_lesson_id, '_llms_parent_course', $translation['id']);
                    }
                    
                    // Fix lesson → section relationship
                    $main_lesson_section = get_post_meta($lesson_id, '_llms_parent_section', true);
                    if ($main_lesson_section) {
                        // Find the translated section
                        $translated_section_id = apply_filters('wpml_object_id', $main_lesson_section, 'section', false, $lang_code);
                        
                        if ($translated_section_id && $translated_section_id !== $main_lesson_section) {
                            $current_parent_section = get_post_meta($translated_lesson_id, '_llms_parent_section', true);
                            if ($current_parent_section != $translated_section_id) {
                                update_post_meta($translated_lesson_id, '_llms_parent_section', $translated_section_id);
                            }
                        }
                    }
                    
                    // Fix lesson order meta key (critical for LifterLMS ordering)
                    $main_lesson_order = get_post_meta($lesson_id, '_llms_order', true);
                    if ($main_lesson_order) {
                        $current_lesson_order = get_post_meta($translated_lesson_id, '_llms_order', true);
                        if ($current_lesson_order != $main_lesson_order) {
                            update_post_meta($translated_lesson_id, '_llms_order', $main_lesson_order);
                        }
                    }
                    
                    $this->stats['lessons_synced']++;
                }
            }
        }
    }
    
    /**
     * Sync course quizzes
     */
    private function sync_course_quizzes($course_id, $translations) {
        
        if (!class_exists('LLMS_Course')) {
            return;
        }
        
        $main_course = new LLMS_Course($course_id);
        $main_lessons = $main_course->get_lessons('lessons');
        
        if (empty($main_lessons)) {
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            
            foreach ($main_lessons as $main_lesson) {
                if (!$main_lesson->has_quiz()) {
                    continue;
                }
                
                $main_quiz = $main_lesson->get_quiz();
                if (!$main_quiz) {
                    continue;
                }
                
                $main_lesson_id = $main_lesson->get('id');
                $main_quiz_id = $main_quiz->get('id');
                
                $translated_lesson_id = apply_filters('wpml_object_id', $main_lesson_id, 'lesson', false, $lang_code);
                $translated_quiz_id = apply_filters('wpml_object_id', $main_quiz_id, 'llms_quiz', false, $lang_code);
                
                if ($translated_lesson_id && $translated_quiz_id && 
                    $translated_lesson_id !== $main_lesson_id && 
                    $translated_quiz_id !== $main_quiz_id) {
                    
                    $translated_lesson = llms_get_post($translated_lesson_id);
                    if ($translated_lesson) {
                        $current_lesson_quiz = $translated_lesson->get('quiz');
                        if ($current_lesson_quiz != $translated_quiz_id) {
                            $translated_lesson->set('quiz', $translated_quiz_id);
                        } else {
                        }
                    }

                    $translated_quiz = llms_get_post($translated_quiz_id);
                    if ($translated_quiz) {
                        $current_quiz_lesson = $translated_quiz->get('lesson_id');
                        if ($current_quiz_lesson != $translated_lesson_id) {
                            $translated_quiz->set('lesson_id', $translated_lesson_id);
                        } else {
                        }
                    }
                    
                    $this->stats['quizzes_synced']++;   
                }
            }
            
        }
        
        $this->sync_quiz_questions($course_id, $translations);
    }
    
    /**
     * Sync quiz-question relationships
     * Based on LifterLMS structure where questions use _llms_parent_id to reference their parent quiz
     */
    private function sync_quiz_questions($course_id, $translations) {
        
        if (!class_exists('LLMS_Course')) {
            return;
        }
        
        $default_lang = apply_filters('wpml_default_language', null) ?: 'en';
        
        // First, get all English questions and find their translations
        $english_questions = get_posts(array(
            'post_type' => 'llms_question',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'suppress_filters' => false,
            'lang' => $default_lang,
            'fields' => 'ids',
        ));
        
        
        foreach ($translations as $lang_code => $translation) {
            
            $questions_fixed = 0;
            
            foreach ($english_questions as $orig_question_id) {
                // Find the translated question
                $translated_question_id = apply_filters('wpml_object_id', $orig_question_id, 'llms_question', false, $lang_code);
                
                if (!$translated_question_id || $translated_question_id == $orig_question_id) {
                    continue;
                }
                
                // Get the original parent quiz ID from the original question
                $orig_quiz_id = get_post_meta($orig_question_id, '_llms_parent_id', true);
                
                if (!$orig_quiz_id) {
                    continue;
                }
                
                // Find the translated quiz ID
                $translated_quiz_id = apply_filters('wpml_object_id', $orig_quiz_id, 'llms_quiz', false, $lang_code);
                
                if (!$translated_quiz_id || !get_post($translated_quiz_id)) {
                    continue;
                }
                
                // Update the question's parent quiz reference
                $current_parent = get_post_meta($translated_question_id, '_llms_parent_id', true);
                
                
                if ($current_parent != $translated_quiz_id) {
                    update_post_meta($translated_question_id, '_llms_parent_id', $translated_quiz_id);
                    $questions_fixed++;
                    
                    // Sync question choices and meta
                    $this->sync_question_choices($orig_question_id, $translated_question_id);
                    
                    // Also sync the parent quiz meta if needed
                    $this->sync_quiz_meta($orig_quiz_id, $translated_quiz_id);
                } else {
                    
                    // Still sync choices even if parent is correct
                    $this->sync_question_choices($orig_question_id, $translated_question_id);
                }
            }
            
            if ($questions_fixed > 0) {
                $this->stats['questions_synced'] += $questions_fixed;
            }
        }
        
        // Verify quiz-question relationships after sync
        $this->verify_quiz_questions($course_id, $translations);
    }
    
    /**
     * Sync question choices and meta between original and translated questions
     * Based on your actual LifterLMS meta structure analysis
     */
    private function sync_question_choices($orig_question_id, $translated_question_id) {
        
        global $wpdb;

        // Get ALL meta keys from original question that start with _llms_choice_
        $choice_meta_keys = $wpdb->get_results($wpdb->prepare("
            SELECT meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id = %d 
            AND (meta_key LIKE '_llms_choice_%' 
                 OR meta_key IN ('_llms_question_type', '_llms_points', '_llms_multi_choices', 
                                '_llms_description_enabled', '_llms_video_enabled', '_llms_video_src'))
        ", $orig_question_id));
        
        
        $synced_choices = 0;
        
        foreach ($choice_meta_keys as $meta) {

            $current_value = get_post_meta($translated_question_id, $meta->meta_key, true);
            if ($current_value === $meta->meta_value) {
                continue;
            }

            $value_to_store = $meta->meta_value;
            
            if (is_serialized($meta->meta_value)) {
                $unserialized_value = maybe_unserialize($meta->meta_value);
                if ($unserialized_value !== false) {
                    $value_to_store = $unserialized_value;
                }
            }

            update_post_meta($translated_question_id, $meta->meta_key, $value_to_store);
            $synced_choices++;
        }
    }
    
    /**
     * Sync quiz meta settings between original and translated quizzes
     * Based on your analysis showing Urdu quiz missing most settings
     */
    private function sync_quiz_meta($orig_quiz_id, $translated_quiz_id) {

        global $wpdb;
        
        // Get ALL meta keys from original quiz (excluding lesson_id which should be different)
        $quiz_meta_keys = $wpdb->get_results($wpdb->prepare("
            SELECT meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id = %d 
            AND meta_key LIKE '_llms_%'
            AND meta_key NOT IN ('_llms_lesson_id')
        ", $orig_quiz_id));
        
        $synced_settings = 0;
        
        foreach ($quiz_meta_keys as $meta) {
            $current_value = get_post_meta($translated_quiz_id, $meta->meta_key, true);
            
            // Skip if values are the same
            if ($current_value === $meta->meta_value) {
                continue;
            }
            
            // Handle serialized data correctly - unserialize first, then let WordPress serialize it properly
            $value_to_store = $meta->meta_value;
            
            // Check if this is a serialized value that needs to be unserialized
            if (is_serialized($meta->meta_value)) {
                $unserialized_value = maybe_unserialize($meta->meta_value);
                if ($unserialized_value !== false) {
                    $value_to_store = $unserialized_value;
                }
            }
            
            // Update the meta value - WordPress will serialize arrays automatically
            update_post_meta($translated_quiz_id, $meta->meta_key, $value_to_store);
            $synced_settings++;
            
        }
        
        if ($synced_settings > 0) {
            $this->stats['quiz_settings_synced'] += $synced_settings;
        }
    }
    
    /**
     * Sync access plans between original and translated courses
     * Access plans are linked to courses via _llms_product_id meta key
     */
    private function sync_access_plans($course_id, $translations) {
        
        $default_lang = apply_filters('wpml_default_language', null) ?: 'en';
        
        // Get all access plans for the original course
        $original_access_plans = get_posts(array(
            'post_type' => 'llms_access_plan',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'meta_key' => '_llms_product_id',
            'meta_value' => $course_id,
            'suppress_filters' => false,
            'lang' => $default_lang,
            'fields' => 'ids',
        ));
        
        
        foreach ($translations as $lang_code => $translation) {
            
            $plans_synced = 0;
            
            foreach ($original_access_plans as $original_plan_id) {
                // Find the translated access plan
                $translated_plan_id = apply_filters('wpml_object_id', $original_plan_id, 'llms_access_plan', false, $lang_code);
                
                if (!$translated_plan_id || $translated_plan_id == $original_plan_id) {
                    continue;
                }
                
                $current_product_id = get_post_meta($translated_plan_id, '_llms_product_id', true);
                
                if ($current_product_id != $translation['id']) {
                    update_post_meta($translated_plan_id, '_llms_product_id', $translation['id']);
                    $plans_synced++;
                    $this->stats['access_plans_synced']++;
                } else {
                }
            }
        }
    }
    
    /**
     * Verify that translated quizzes can find their questions after sync
     */
    private function verify_quiz_questions($course_id, $translations) {
        
        if (!class_exists('LLMS_Course') || !class_exists('LLMS_Quiz')) {
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            $translated_course = new LLMS_Course($translation['id']);
            $translated_lessons = $translated_course->get_lessons('lessons');
            
            if (empty($translated_lessons)) {
                continue;
            }
            
            $quiz_verification_count = 0;
            $working_quizzes = 0;
            $broken_quizzes = 0;
            
            foreach ($translated_lessons as $lesson) {
                if (!$lesson->has_quiz()) {
                    continue;
                }
                
                $quiz = $lesson->get_quiz();
                if (!$quiz) {
                    continue;
                }
                
                $quiz_verification_count++;
                $quiz_id = $quiz->get('id');
                $questions = $quiz->get_questions('ids');
                
                if (empty($questions)) {
                    $broken_quizzes++;
                } else {
                    $working_quizzes++;
                }
            }
        }
    }
    
    /**
     * Sync course enrollments
     */
    private function sync_course_enrollments($course_id, $translations) {
        
        if (!class_exists('LLMS_Student')) {
            return;
        }
        
        // Get students enrolled in main course
        $main_course = new LLMS_Course($course_id);
        $enrolled_students = $main_course->get_students();
        
        if (empty($enrolled_students)) {
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            foreach ($enrolled_students as $student_id) {
                $student = new LLMS_Student($student_id);
                
                if (!$student->is_enrolled($translation['id'])) {
                    $student->enroll($translation['id']);
                    $this->stats['enrollments_synced']++;
                }
            }
        }
    }
    
    /**
     * Sync course metadata
     */
    private function sync_course_metadata($course_id, $translations) {
        
        // Comprehensive list of LifterLMS course meta fields to sync
        $sync_fields = array(
            // General Settings
            '_llms_length',                          // Course Length
            '_llms_post_course_difficulty',          // Course Difficulty Category
            '_llms_video_embed',                     // Featured Video
            '_llms_tile_featured_video',             // Display Featured Video on Course Tile
            '_llms_audio_embed',                     // Featured Audio
            
            // Sales Page Settings
            '_llms_sales_page_content_type',         // Sales Page Content Type
            '_llms_sales_page_content_page_id',      // Sales Page Content Page ID
            '_llms_sales_page_content_url',          // Sales Page Redirect URL
            
            // Restrictions Settings
            '_llms_content_restricted_message',      // Content Restricted Message
            '_llms_enrollment_period',               // Enable Enrollment Period
            '_llms_enrollment_start_date',           // Enrollment Start Date
            '_llms_enrollment_end_date',             // Enrollment End Date
            '_llms_enrollment_opens_message',        // Enrollment Opens Message
            '_llms_enrollment_closed_message',       // Enrollment Closed Message
            '_llms_time_period',                     // Enable Course Time Period
            '_llms_start_date',                      // Course Start Date
            '_llms_end_date',                        // Course End Date
            '_llms_course_opens_message',            // Course Opens Message
            '_llms_course_closed_message',           // Course Closed Message
            
            // Prerequisites Settings
            '_llms_has_prerequisite',                // Has Prerequisites
            '_llms_prerequisite',                    // Prerequisite Course ID
            '_llms_prerequisite_track',              // Prerequisite Track ID
            
            // Drip Settings
            '_llms_drip_method',                     // Drip Method
            '_llms_days_before_available',           // Days Before Available
            '_llms_date_available',                  // Date Available
            
            // Catalog Settings
            '_llms_catalog_visibility',              // Catalog Visibility
            '_llms_featured',                        // Featured Course
            
            // Engagement Settings
            '_llms_enable_capacity',                 // Enable Capacity
            '_llms_capacity',                        // Course Capacity
            '_llms_capacity_message',                // Capacity Reached Message
            
            // Legacy/Additional Fields
            '_llms_difficulty_id',                   // Legacy Difficulty ID
            '_llms_track_id',                        // Track ID
            '_llms_course_image',                    // Course Image
            '_llms_course_video_embed',              // Legacy Video Embed
            
            // Completion Settings
            '_llms_enable_completion_tracking',      // Enable Completion Tracking
            '_llms_completion_redirect',             // Completion Redirect
            '_llms_completion_redirect_url',         // Completion Redirect URL
            
            // Certificate & Achievement Settings
            '_llms_certificate',                     // Certificate Template
            '_llms_certificate_title',               // Certificate Title
            '_llms_achievement',                     // Achievement Template
            '_llms_achievement_title',               // Achievement Title
            
            // Notification Settings
            '_llms_enable_notifications',            // Enable Notifications
            
            // Custom Fields (if any)
            '_llms_custom_excerpt',                  // Custom Excerpt
            '_llms_points',                          // Points Awarded
            
            // Access Plan Related (if stored as course meta)
            '_llms_access_plans',                    // Access Plans
            
            // Reviews & Comments Settings (Complete)
            '_llms_enable_reviews',                  // Enable Reviews
            '_llms_reviews_enabled',                 // Reviews Enabled (primary field)
            '_llms_display_reviews',                 // Display Reviews on Course Page
            '_llms_num_reviews',                     // Number of Reviews to Display
            '_llms_multiple_reviews_disabled',       // Disable Multiple Reviews per User
            '_llms_enable_comments',                 // Enable Comments
            
            // Additional Course Meta Fields
            '_llms_instructors',                     // Course Instructors
            '_llms_course_tags',                     // Course Tags
            '_llms_course_categories',               // Course Categories
            '_llms_course_tracks',                   // Course Tracks
            '_llms_course_difficulty',               // Course Difficulty (taxonomy)
            '_llms_course_status',                   // Course Status
            '_llms_course_privacy',                  // Course Privacy Settings
            '_llms_course_forum',                    // Course Forum Settings
            '_llms_course_forum_enabled',            // Enable Course Forum
            '_llms_course_forum_id',                 // Course Forum ID
            
            // Engagement & Gamification
            '_llms_course_points',                   // Course Points
            '_llms_course_badges',                   // Course Badges
            '_llms_course_leaderboard',              // Course Leaderboard
            '_llms_course_social_sharing',           // Social Sharing Settings
            
            // Advanced Course Settings
            '_llms_course_retake_enabled',           // Allow Course Retakes
            '_llms_course_retake_limit',             // Course Retake Limit
            '_llms_course_time_limit',               // Course Time Limit
            '_llms_course_time_limit_enabled',       // Enable Course Time Limit
            '_llms_course_expiration',               // Course Expiration
            '_llms_course_expiration_enabled',       // Enable Course Expiration
        );
        
        // Add stats tracking
        $settings_synced = 0;
        
        foreach ($translations as $lang_code => $translation) {
            foreach ($sync_fields as $field) {
                $main_value = get_post_meta($course_id, $field, true);
                $current_value = get_post_meta($translation['id'], $field, true);
                
                // Only update if values are different or if main value exists and current doesn't
                if ($main_value !== $current_value && ($main_value !== '' || $current_value !== '')) {
                    if ($main_value === '' || $main_value === false || $main_value === null) {
                        // Delete the meta if main course doesn't have it
                        delete_post_meta($translation['id'], $field);
                    } else {
                        // Update with main course value
                        update_post_meta($translation['id'], $field, $main_value);
                    }
                    $settings_synced++;
                }
            }
            
            // Also sync post excerpt (used for sales page content)
            $main_post = get_post($course_id);
            $translated_post = get_post($translation['id']);
            
            if ($main_post && $translated_post && $main_post->post_excerpt !== $translated_post->post_excerpt) {
                wp_update_post(array(
                    'ID' => $translation['id'],
                    'post_excerpt' => $main_post->post_excerpt
                ));
                $settings_synced++;
            }
        }
        
        // Update stats
        $this->stats['course_settings_synced'] = $settings_synced;
    }

    /**
     * Get processing statistics
     */
    public function get_stats() {
        return $this->stats;
    }
    
    /**
     * Clear stats
     */
    public function reset() {
        $this->init_stats();
    }
}

new WPML_LLMS_Course_Fixer();
