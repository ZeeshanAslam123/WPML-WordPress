<?php
/**
 * WPML LifterLMS Complete Progress Synchronization
 * 
 * Automatically synchronizes ALL LifterLMS progress across all language versions:
 * - Lessons, Courses, Sections, Quizzes completion/incompletion
 * - Course enrollment progress transfer
 * - Bidirectional synchronization between all languages
 * - Real-time progress updates across translations
 * 
 * When a user completes any LifterLMS object in one language, it's automatically 
 * marked as completed in ALL translated versions. When enrolling in a course,
 * existing progress from other language versions is automatically transferred.
 * 
 * @package WPML_LifterLMS
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPML LifterLMS Progress Synchronizer
 */
class WPML_LLMS_Progress_Sync {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Hook into LifterLMS specific completion events
        add_action('lifterlms_lesson_completed', array($this, 'sync_lesson_progress'), 10, 2);
        add_action('lifterlms_course_completed', array($this, 'sync_course_progress'), 10, 2);
        add_action('lifterlms_section_completed', array($this, 'sync_section_progress'), 10, 2);
        add_action('lifterlms_quiz_completed', array($this, 'sync_quiz_progress'), 10, 3);
        
        // Hook into general completion (covers all object types)
        add_action('llms_mark_complete', array($this, 'sync_object_progress'), 10, 4);
        
        // Hook into incompletion as well
        add_action('llms_mark_incomplete', array($this, 'sync_object_progress_incomplete'), 10, 4);
        
        // Hook into course enrollment to sync initial progress
        add_action('llms_user_enrolled_in_course', array($this, 'sync_course_enrollment_progress'), 10, 2);
        
        // Hook into course unenrollment
        add_action('llms_user_removed_from_course', array($this, 'sync_course_unenrollment_progress'), 10, 2);
        
        // CRITICAL: Hook into is_complete filter to check across all translated lessons
        add_filter('llms_is_lesson_complete', array($this, 'check_lesson_complete_across_translations'), 10, 4);
    }
    
    /**
     * Sync lesson progress across all language versions
     * 
     * @param int $user_id User ID who completed the lesson
     * @param int $lesson_id Lesson ID that was completed
     */
    public function sync_lesson_progress($user_id, $lesson_id) {
        // Prevent infinite loops
        if (doing_action('lifterlms_lesson_completed') > 1) {
            return;
        }
        
        
        try {
            // Get all translations of this lesson
            $translations = $this->get_lesson_translations($lesson_id);
            
            if (empty($translations)) {
                return;
            }
            
            $synced_count = 0;
            
            foreach ($translations as $lang_code => $translation_data) {
                $translated_lesson_id = $translation_data['id'];
                
                // Skip if it's the same lesson (original)
                if ($translated_lesson_id == $lesson_id) {
                    continue;
                }
                
                // Check if lesson is already completed in this translation
                if (llms_is_complete($user_id, $translated_lesson_id, 'lesson')) {
                    continue;
                }
                
                // Mark lesson as complete in translated version
                $completion_result = llms_mark_complete($user_id, $translated_lesson_id, 'lesson', 'wpml_progress_sync');
                
                if ($completion_result) {
                    $synced_count++;
                    
                    // Clear progress cache for lesson, section, and course
                    $this->clear_progress_cache($user_id, $translated_lesson_id, 'lesson');
                } else {
                }
            }
            
            if ($synced_count > 0) {
                
                // CRITICAL: Clear course progress cache for ALL translated courses
                $this->clear_all_course_progress_cache($user_id, $lesson_id);
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_lesson_progress_synced', $user_id, $lesson_id, $translations, $synced_count);
            } else {
            }
            
        } catch (Exception $e) {
        }
    }
    
    /**
     * Sync course progress across all language versions
     * 
     * @param int $user_id User ID who completed the course
     * @param int $course_id Course ID that was completed
     */
    public function sync_course_progress($user_id, $course_id) {
        // Prevent infinite loops
        if (doing_action('lifterlms_course_completed') > 1) {
            return;
        }
        
        
        try {
            // Get all translations of this course
            $translations = $this->get_object_translations($course_id, 'course');
            
            if (empty($translations)) {
                return;
            }
            
            $synced_count = 0;
            
            foreach ($translations as $lang_code => $translation_data) {
                $translated_course_id = $translation_data['id'];
                
                // Skip if it's the same course (original)
                if ($translated_course_id == $course_id) {
                    continue;
                }
                
                // Check if course is already completed in this translation
                if (llms_is_complete($user_id, $translated_course_id, 'course')) {
                    continue;
                }
                
                // Mark course as complete in translated version
                $completion_result = llms_mark_complete($user_id, $translated_course_id, 'course', 'wpml_progress_sync');
                
                if ($completion_result) {
                    $synced_count++;
                    
                    // Clear progress cache for this course
                    $this->clear_progress_cache($user_id, $translated_course_id, 'course');
                } else {
                }
            }
            
            if ($synced_count > 0) {
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_course_progress_synced', $user_id, $course_id, $translations, $synced_count);
            } else {
            }
            
        } catch (Exception $e) {
        }
    }
    
    /**
     * Sync section progress across all language versions
     * 
     * @param int $user_id User ID who completed the section
     * @param int $section_id Section ID that was completed
     */
    public function sync_section_progress($user_id, $section_id) {
        // Prevent infinite loops
        if (doing_action('lifterlms_section_completed') > 1) {
            return;
        }
        
        
        try {
            // Get all translations of this section
            $translations = $this->get_object_translations($section_id, 'section');
            
            if (empty($translations)) {
                return;
            }
            
            $synced_count = 0;
            
            foreach ($translations as $lang_code => $translation_data) {
                $translated_section_id = $translation_data['id'];
                
                // Skip if it's the same section (original)
                if ($translated_section_id == $section_id) {
                    continue;
                }
                
                // Check if section is already completed in this translation
                if (llms_is_complete($user_id, $translated_section_id, 'section')) {
                    continue;
                }
                
                // Mark section as complete in translated version
                $completion_result = llms_mark_complete($user_id, $translated_section_id, 'section', 'wpml_progress_sync');
                
                if ($completion_result) {
                    $synced_count++;
                    
                    // Clear progress cache for this section and its parent course
                    $this->clear_progress_cache($user_id, $translated_section_id, 'section');
                } else {
                }
            }
            
            if ($synced_count > 0) {
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_section_progress_synced', $user_id, $section_id, $translations, $synced_count);
            } else {
            }
            
        } catch (Exception $e) {
        }
    }
    
    /**
     * Sync quiz progress across all language versions
     * 
     * @param int $user_id User ID who completed the quiz
     * @param int $quiz_id Quiz ID that was completed
     * @param object $attempt Quiz attempt object
     */
    public function sync_quiz_progress($user_id, $quiz_id, $attempt) {
        // Prevent infinite loops
        if (doing_action('lifterlms_quiz_completed') > 1) {
            return;
        }
        
        
        try {
            // Get all translations of this quiz
            $translations = $this->get_object_translations($quiz_id, 'llms_quiz');
            
            if (empty($translations)) {
                return;
            }
            
            $synced_count = 0;
            
            foreach ($translations as $lang_code => $translation_data) {
                $translated_quiz_id = $translation_data['id'];
                
                // Skip if it's the same quiz (original)
                if ($translated_quiz_id == $quiz_id) {
                    continue;
                }
                
                // Check if quiz is already completed in this translation
                if (llms_is_complete($user_id, $translated_quiz_id, 'llms_quiz')) {
                    continue;
                }
                
                // Mark quiz as complete in translated version
                $completion_result = llms_mark_complete($user_id, $translated_quiz_id, 'llms_quiz', 'wpml_progress_sync');
                
                if ($completion_result) {
                    $synced_count++;
                    
                    // Clear progress cache for course containing this quiz
                    $this->clear_progress_cache($user_id, $translated_quiz_id, 'llms_quiz');
                } else {
                }
            }
            
            if ($synced_count > 0) {
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_quiz_progress_synced', $user_id, $quiz_id, $translations, $synced_count);
            } else {
            }
            
        } catch (Exception $e) {
        }
    }
    
    /**
     * Sync course enrollment progress when user enrolls in a course
     * This ensures that if user has progress in other language versions, it gets synced
     * 
     * @param int $user_id User ID who enrolled
     * @param int $course_id Course ID that was enrolled in
     */
    public function sync_course_enrollment_progress($user_id, $course_id) {
        // Prevent infinite loops
        if (doing_action('llms_user_enrolled_in_course') > 1) {
            return;
        }
        
        
        try {
            // Get all translations of this course
            $translations = $this->get_object_translations($course_id, 'course');
            
            if (empty($translations)) {
                return;
            }
            
            // Check if user has progress in any other language version
            $source_progress = null;
            $source_course_id = null;
            
            foreach ($translations as $lang_code => $translation_data) {
                $translated_course_id = $translation_data['id'];
                
                // Skip if it's the same course (original)
                if ($translated_course_id == $course_id) {
                    continue;
                }
                
                // Check if user is enrolled and has progress in this translation
                $student = new LLMS_Student($user_id);
                if ($student->is_enrolled($translated_course_id)) {
                    $progress = $student->get_progress($translated_course_id, 'course');
                    if ($progress > 0) {
                        $source_progress = $progress;
                        $source_course_id = $translated_course_id;
                        break; // Use the first one we find with progress
                    }
                }
            }
            
            // If we found progress in another language, sync the individual lesson/quiz completions
            if ($source_progress > 0 && $source_course_id) {
                $this->sync_detailed_course_progress($user_id, $source_course_id, $course_id);
            }
            
        } catch (Exception $e) {
        }
    }
    
    /**
     * Sync course unenrollment progress when user is removed from a course
     * 
     * @param int $user_id User ID who was unenrolled
     * @param int $course_id Course ID that was unenrolled from
     */
    public function sync_course_unenrollment_progress($user_id, $course_id) {
        // Prevent infinite loops
        if (doing_action('llms_user_removed_from_course') > 1) {
            return;
        }
        
        
        // For now, we'll just log this event. In the future, we might want to
        // remove progress from all language versions or handle it differently
        // based on site requirements
        
    }
    
    /**
     * Sync detailed course progress (lessons, quizzes) from source to target course
     * 
     * @param int $user_id User ID
     * @param int $source_course_id Source course ID (with existing progress)
     * @param int $target_course_id Target course ID (newly enrolled)
     */
    private function sync_detailed_course_progress($user_id, $source_course_id, $target_course_id) {
        
        try {
            $student = new LLMS_Student($user_id);
            $source_course = llms_get_post($source_course_id);
            $target_course = llms_get_post($target_course_id);
            
            if (!$source_course || !$target_course) {
                return;
            }
            
            // Get all lessons from source course
            $source_lessons = $source_course->get_lessons('ids');
            
            foreach ($source_lessons as $source_lesson_id) {
                // Check if this lesson is completed in source course
                if ($student->is_complete($source_lesson_id, 'lesson')) {
                    // Find the corresponding lesson in target course
                    $target_lesson_id = apply_filters('wpml_object_id', $source_lesson_id, 'lesson', false, null);
                    
                    if ($target_lesson_id && $target_lesson_id != $source_lesson_id) {
                        // Mark lesson as complete in target course
                        $completion_result = llms_mark_complete($user_id, $target_lesson_id, 'lesson', 'wpml_enrollment_progress_sync');
                        
                        if ($completion_result) {
                            
                            // Clear progress cache for this lesson and its course
                            $this->clear_progress_cache($user_id, $target_lesson_id, 'lesson');
                        }
                    }
                }
            }
            
            // Get all quizzes from source course
            $source_quizzes = $source_course->get_quizzes();
            
            foreach ($source_quizzes as $source_quiz_id) {
                // Check if this quiz is completed in source course
                if ($student->is_complete($source_quiz_id, 'llms_quiz')) {
                    // Find the corresponding quiz in target course
                    $target_quiz_id = apply_filters('wpml_object_id', $source_quiz_id, 'llms_quiz', false, null);
                    
                    if ($target_quiz_id && $target_quiz_id != $source_quiz_id) {
                        // Mark quiz as complete in target course
                        $completion_result = llms_mark_complete($user_id, $target_quiz_id, 'llms_quiz', 'wpml_enrollment_progress_sync');
                        
                        if ($completion_result) {
                            
                            // Clear progress cache for this quiz and its course
                            $this->clear_progress_cache($user_id, $target_quiz_id, 'llms_quiz');
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
        }
    }
    
    /**
     * Sync object progress (lessons, sections, courses) across all language versions
     * 
     * @param int $user_id User ID
     * @param int $object_id Object ID (lesson, section, course)
     * @param string $object_type Object type (lesson, section, course)
     * @param string $trigger Trigger that caused the completion
     */
    public function sync_object_progress($user_id, $object_id, $object_type, $trigger) {
        // Prevent infinite loops and only sync if not already triggered by our sync
        if (doing_action('llms_mark_complete') > 1 || $trigger === 'wpml_progress_sync') {
            return;
        }
        
        // Handle all supported object types
        $supported_types = array('lesson', 'section', 'course', 'llms_quiz');
        if (!in_array($object_type, $supported_types)) {
            return;
        }
        
        
        try {
            // Get all translations of this object
            $translations = $this->get_object_translations($object_id, $object_type);
            
            if (empty($translations)) {
                return;
            }
            
            $synced_count = 0;
            
            foreach ($translations as $lang_code => $translation_data) {
                $translated_object_id = $translation_data['id'];
                
                // Skip if it's the same object (original)
                if ($translated_object_id == $object_id) {
                    continue;
                }
                
                // Check if object is already completed in this translation
                if (llms_is_complete($user_id, $translated_object_id, $object_type)) {
                    continue;
                }
                
                // Mark object as complete in translated version
                $completion_result = llms_mark_complete($user_id, $translated_object_id, $object_type, 'wpml_progress_sync');
                
                if ($completion_result) {
                    $synced_count++;
                    
                    // Clear progress cache for this object
                    $this->clear_progress_cache($user_id, $translated_object_id, $object_type);
                } else {
                }
            }
            
            if ($synced_count > 0) {
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_object_progress_synced', $user_id, $object_id, $object_type, $translations, $synced_count);
            } else {
            }
            
        } catch (Exception $e) {
        }
    }
    
    /**
     * Sync object incompletion across all language versions
     * 
     * @param int $user_id User ID
     * @param int $object_id Object ID (lesson, section, course)
     * @param string $object_type Object type (lesson, section, course)
     * @param string $trigger Trigger that caused the incompletion
     */
    public function sync_object_progress_incomplete($user_id, $object_id, $object_type, $trigger) {
        // Prevent infinite loops and only sync if not already triggered by our sync
        if (doing_action('llms_mark_incomplete') > 1 || $trigger === 'wpml_progress_sync') {
            return;
        }
        
        // Handle all supported object types
        $supported_types = array('lesson', 'section', 'course', 'llms_quiz');
        if (!in_array($object_type, $supported_types)) {
            return;
        }
        
        
        try {
            // Get all translations of this object
            $translations = $this->get_object_translations($object_id, $object_type);
            
            if (empty($translations)) {
                return;
            }
            
            $synced_count = 0;
            
            foreach ($translations as $lang_code => $translation_data) {
                $translated_object_id = $translation_data['id'];
                
                // Skip if it's the same object (original)
                if ($translated_object_id == $object_id) {
                    continue;
                }
                
                // Check if object is already incomplete in this translation
                if (!llms_is_complete($user_id, $translated_object_id, $object_type)) {
                    continue;
                }
                
                // Mark object as incomplete in translated version
                $incompletion_result = llms_mark_incomplete($user_id, $translated_object_id, $object_type, 'wpml_progress_sync');
                
                if ($incompletion_result) {
                    $synced_count++;
                    
                    // Clear progress cache for this object
                    $this->clear_progress_cache($user_id, $translated_object_id, $object_type);
                } else {
                }
            }
            
            if ($synced_count > 0) {
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_object_incompletion_synced', $user_id, $object_id, $object_type, $translations, $synced_count);
            } else {
            }
            
        } catch (Exception $e) {
        }
    }
    
    /**
     * Get all translations of a lesson
     * 
     * @param int $lesson_id Lesson ID
     * @return array Array of translations
     */
    private function get_lesson_translations($lesson_id) {
        return $this->get_object_translations($lesson_id, 'lesson');
    }
    
    /**
     * Get all translations of an object (lesson, section, course)
     * 
     * @param int $object_id Object ID
     * @param string $object_type Object type
     * @return array Array of translations
     */
    private function get_object_translations($object_id, $object_type) {
        if (!function_exists('icl_get_languages')) {
            return array();
        }
        
        $translations = array();
        $languages = icl_get_languages('skip_missing=0');
        
        // Map object types to WPML post types
        $post_type_map = array(
            'lesson' => 'lesson',
            'section' => 'section',
            'course' => 'course',
            'llms_quiz' => 'llms_quiz'
        );
        
        $wpml_post_type = isset($post_type_map[$object_type]) ? $post_type_map[$object_type] : $object_type;
        
        foreach ($languages as $lang_code => $language) {
            $translated_id = apply_filters('wpml_object_id', $object_id, $wpml_post_type, false, $lang_code);
            
            if ($translated_id && $translated_id != $object_id) {
                $translated_post = get_post($translated_id);
                if ($translated_post && $translated_post->post_status === 'publish') {
                    $translations[$lang_code] = array(
                        'id' => $translated_id,
                        'title' => $translated_post->post_title,
                        'language' => $language['native_name']
                    );
                }
            }
        }
        
        return $translations;
    }
    
    /**
     * Log messages for debugging
     * 
     * @param string $message Log message
     * @param string $level Log level (info, success, error)
     */

    
    /**
     * Clear progress cache for a specific object and related objects
     * This ensures progress bars are updated immediately after sync
     * 
     * @param int $user_id User ID
     * @param int $object_id Object ID (lesson, course, section, quiz)
     * @param string $object_type Object type
     */
    private function clear_progress_cache($user_id, $object_id, $object_type) {
        try {
            $student = new LLMS_Student($user_id);
            
            // Clear cache for the specific object
            switch ($object_type) {
                case 'lesson':
                    // Clear lesson cache (if any) - set to empty string to trigger recalculation
                    $student->set('lesson_' . $object_id . '_progress', '');
                    
                    // Clear section cache for the section containing this lesson
                    $lesson = new LLMS_Lesson($object_id);
                    $section_id = $lesson->get_parent_section();
                    if ($section_id) {
                        $student->set('section_' . $section_id . '_progress', '');
                    }
                    
                    // Clear course cache for the course containing this lesson
                    $course_id = $lesson->get_parent_course();
                    if ($course_id) {
                        $student->set('course_' . $course_id . '_progress', '');
                    }
                    break;
                    
                case 'section':
                    // Clear section cache
                    $student->set('section_' . $object_id . '_progress', '');
                    
                    // Clear course cache for the course containing this section
                    $section = new LLMS_Section($object_id);
                    $course_id = $section->get_parent_course();
                    if ($course_id) {
                        $student->set('course_' . $course_id . '_progress', '');
                    }
                    break;
                    
                case 'course':
                    // Clear course cache
                    $student->set('course_' . $object_id . '_progress', '');
                    break;
                    
                case 'llms_quiz':
                    // Clear quiz cache (if any)
                    $student->set('llms_quiz_' . $object_id . '_progress', '');
                    
                    // Clear course cache for the course containing this quiz
                    $quiz = new LLMS_Quiz($object_id);
                    $lesson_id = $quiz->get('lesson_id');
                    if ($lesson_id) {
                        $lesson = new LLMS_Lesson($lesson_id);
                        $course_id = $lesson->get_parent_course();
                        if ($course_id) {
                            $student->set('course_' . $course_id . '_progress', '');
                        }
                    }
                    break;
            }
            
            // Force refresh of any cached progress data
            wp_cache_delete($user_id, 'llms_student_progress');
            
            
        } catch (Exception $e) {
        }
    }

    /**
     * Clear course progress cache for ALL translated courses
     * 
     * This is critical for fixing progress bar display across languages.
     * When a lesson is completed, we need to clear course progress cache
     * for ALL language versions of the course.
     * 
     * @param int $user_id User ID
     * @param int $lesson_id Original lesson ID
     */
    private function clear_all_course_progress_cache($user_id, $lesson_id) {
        try {
            $student = new LLMS_Student($user_id);
            
            // Get the course ID from the lesson
            $lesson = new LLMS_Lesson($lesson_id);
            $course_id = $lesson->get_parent_course();
            
            if (!$course_id) {
                return;
            }
            
            // Get all translated versions of this course
            $active_languages = apply_filters('wpml_active_languages', null);
            
            foreach ($active_languages as $lang_code => $lang_info) {
                $translated_course_id = apply_filters('wpml_object_id', $course_id, 'course', false, $lang_code);
                
                if ($translated_course_id && $translated_course_id != $course_id) {
                    // Clear course progress cache for this translated course
                    $student->set('course_' . $translated_course_id . '_progress', '');
                }
            }
            
            // Also clear the original course cache
            $student->set('course_' . $course_id . '_progress', '');
            
        } catch (Exception $e) {
        }
    }

    /**
     * CRITICAL METHOD: Check lesson completion across ALL translated versions
     * 
     * This is the core fix for progress bar synchronization.
     * When LifterLMS checks if a lesson is complete, we intercept that check
     * and also look for completion in ALL translated versions of that lesson.
     * 
     * @param bool $is_complete Original completion status
     * @param int $lesson_id Lesson ID being checked
     * @param string $type Object type (should be 'lesson')
     * @param LLMS_Student $student Student object
     * @return bool True if lesson is complete in ANY language version
     */
    public function check_lesson_complete_across_translations($is_complete, $lesson_id, $type, $student) {
        // If already complete, no need to check further
        if ($is_complete) {
            return $is_complete;
        }
        
        // Only process lessons
        if ($type !== 'lesson') {
            return $is_complete;
        }
        
        try {
            // Get all translated versions of this lesson
            $active_languages = apply_filters('wpml_active_languages', null);
            
            if (!$active_languages) {
                return $is_complete;
            }
            
            foreach ($active_languages as $lang_code => $lang_info) {
                $translated_lesson_id = apply_filters('wpml_object_id', $lesson_id, 'lesson', false, $lang_code);
                
                if ($translated_lesson_id && $translated_lesson_id != $lesson_id) {
                    // Check if this translated lesson is complete using direct database query
                    if ($this->is_lesson_complete_in_database($student->get_id(), $translated_lesson_id)) {
                        return true; // Found completion in another language!
                    }
                }
            }
            
        } catch (Exception $e) {
        }
        
        return $is_complete;
    }

    /**
     * Check if a lesson is complete in the database directly
     * 
     * @param int $user_id User ID
     * @param int $lesson_id Lesson ID
     * @return bool True if lesson is complete
     */
    private function is_lesson_complete_in_database($user_id, $lesson_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}lifterlms_user_postmeta 
             WHERE user_id = %d AND post_id = %d AND meta_key = '_is_complete' 
             ORDER BY updated_date DESC LIMIT 1",
            $user_id,
            $lesson_id
        ));
        
        return ($result === 'yes');
    }
}

// Initialize the progress synchronizer
new WPML_LLMS_Progress_Sync();
