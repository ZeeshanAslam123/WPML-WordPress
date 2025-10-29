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
        
        // CRITICAL: Hook into quiz attempts retrieval to sync across all translated quizzes
        add_filter('llms_student_get_quiz_data', array($this, 'sync_quiz_attempts_across_translations'), 10, 2);
        
        // CRITICAL: Hook into template loading to sync attempt data across translations
        add_action('lifterlms_single_quiz_before_summary', array($this, 'sync_current_attempt_data'), 5);
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
        
        $this->log('Starting lesson progress sync for user ' . $user_id . ' in lesson ' . $lesson_id, 'info');
        
        try {
            // Get all translations of this lesson
            $translations = $this->get_lesson_translations($lesson_id);
            
            if (empty($translations)) {
                $this->log('No translations found for lesson ' . $lesson_id, 'info');
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
                    $this->log('User ' . $user_id . ' already completed lesson ' . $translated_lesson_id . ' (' . $lang_code . ')', 'info');
                    continue;
                }
                
                // Mark lesson as complete in translated version
                $completion_result = llms_mark_complete($user_id, $translated_lesson_id, 'lesson', 'wpml_progress_sync');
                
                if ($completion_result) {
                    $synced_count++;
                    $this->log('✅ Marked lesson ' . $translated_lesson_id . ' (' . $lang_code . ') as complete for user ' . $user_id, 'success');
                    
                    // Clear progress cache for lesson, section, and course
                    $this->clear_progress_cache($user_id, $translated_lesson_id, 'lesson');
                } else {
                    $this->log('❌ Failed to mark lesson ' . $translated_lesson_id . ' (' . $lang_code . ') as complete for user ' . $user_id, 'error');
                }
            }
            
            if ($synced_count > 0) {
                $this->log('✅ Successfully synced lesson progress to ' . $synced_count . ' translated lessons', 'success');
                
                // CRITICAL: Clear course progress cache for ALL translated courses
                $this->clear_all_course_progress_cache($user_id, $lesson_id);
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_lesson_progress_synced', $user_id, $lesson_id, $translations, $synced_count);
            } else {
                $this->log('No new lesson progress sync needed', 'info');
            }
            
        } catch (Exception $e) {
            $this->log('Error during lesson progress sync: ' . $e->getMessage(), 'error');
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
        
        $this->log('Starting course progress sync for user ' . $user_id . ' in course ' . $course_id, 'info');
        
        try {
            // Get all translations of this course
            $translations = $this->get_object_translations($course_id, 'course');
            
            if (empty($translations)) {
                $this->log('No translations found for course ' . $course_id, 'info');
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
                    $this->log('User ' . $user_id . ' already completed course ' . $translated_course_id . ' (' . $lang_code . ')', 'info');
                    continue;
                }
                
                // Mark course as complete in translated version
                $completion_result = llms_mark_complete($user_id, $translated_course_id, 'course', 'wpml_progress_sync');
                
                if ($completion_result) {
                    $synced_count++;
                    $this->log('✅ Marked course ' . $translated_course_id . ' (' . $lang_code . ') as complete for user ' . $user_id, 'success');
                    
                    // Clear progress cache for this course
                    $this->clear_progress_cache($user_id, $translated_course_id, 'course');
                } else {
                    $this->log('❌ Failed to mark course ' . $translated_course_id . ' (' . $lang_code . ') as complete for user ' . $user_id, 'error');
                }
            }
            
            if ($synced_count > 0) {
                $this->log('✅ Successfully synced course progress to ' . $synced_count . ' translated courses', 'success');
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_course_progress_synced', $user_id, $course_id, $translations, $synced_count);
            } else {
                $this->log('No new course progress sync needed', 'info');
            }
            
        } catch (Exception $e) {
            $this->log('Error during course progress sync: ' . $e->getMessage(), 'error');
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
        
        $this->log('Starting section progress sync for user ' . $user_id . ' in section ' . $section_id, 'info');
        
        try {
            // Get all translations of this section
            $translations = $this->get_object_translations($section_id, 'section');
            
            if (empty($translations)) {
                $this->log('No translations found for section ' . $section_id, 'info');
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
                    $this->log('User ' . $user_id . ' already completed section ' . $translated_section_id . ' (' . $lang_code . ')', 'info');
                    continue;
                }
                
                // Mark section as complete in translated version
                $completion_result = llms_mark_complete($user_id, $translated_section_id, 'section', 'wpml_progress_sync');
                
                if ($completion_result) {
                    $synced_count++;
                    $this->log('✅ Marked section ' . $translated_section_id . ' (' . $lang_code . ') as complete for user ' . $user_id, 'success');
                    
                    // Clear progress cache for this section and its parent course
                    $this->clear_progress_cache($user_id, $translated_section_id, 'section');
                } else {
                    $this->log('❌ Failed to mark section ' . $translated_section_id . ' (' . $lang_code . ') as complete for user ' . $user_id, 'error');
                }
            }
            
            if ($synced_count > 0) {
                $this->log('✅ Successfully synced section progress to ' . $synced_count . ' translated sections', 'success');
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_section_progress_synced', $user_id, $section_id, $translations, $synced_count);
            } else {
                $this->log('No new section progress sync needed', 'info');
            }
            
        } catch (Exception $e) {
            $this->log('Error during section progress sync: ' . $e->getMessage(), 'error');
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
        
        $this->log('Starting quiz progress sync for user ' . $user_id . ' in quiz ' . $quiz_id, 'info');
        
        try {
            // Get all translations of this quiz
            $translations = $this->get_object_translations($quiz_id, 'llms_quiz');
            
            if (empty($translations)) {
                $this->log('No translations found for quiz ' . $quiz_id, 'info');
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
                    $this->log('User ' . $user_id . ' already completed quiz ' . $translated_quiz_id . ' (' . $lang_code . ')', 'info');
                    continue;
                }
                
                // Mark quiz as complete in translated version
                $completion_result = llms_mark_complete($user_id, $translated_quiz_id, 'llms_quiz', 'wpml_progress_sync');
                
                if ($completion_result) {
                    $synced_count++;
                    $this->log('✅ Marked quiz ' . $translated_quiz_id . ' (' . $lang_code . ') as complete for user ' . $user_id, 'success');
                    
                    // Clear progress cache for course containing this quiz
                    $this->clear_progress_cache($user_id, $translated_quiz_id, 'llms_quiz');
                } else {
                    $this->log('❌ Failed to mark quiz ' . $translated_quiz_id . ' (' . $lang_code . ') as complete for user ' . $user_id, 'error');
                }
            }
            
            if ($synced_count > 0) {
                $this->log('✅ Successfully synced quiz progress to ' . $synced_count . ' translated quizzes', 'success');
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_quiz_progress_synced', $user_id, $quiz_id, $translations, $synced_count);
            } else {
                $this->log('No new quiz progress sync needed', 'info');
            }
            
        } catch (Exception $e) {
            $this->log('Error during quiz progress sync: ' . $e->getMessage(), 'error');
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
        
        $this->log('Starting enrollment progress sync for user ' . $user_id . ' in course ' . $course_id, 'info');
        
        try {
            // Get all translations of this course
            $translations = $this->get_object_translations($course_id, 'course');
            
            if (empty($translations)) {
                $this->log('No translations found for course ' . $course_id, 'info');
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
                        $this->log('Found existing progress (' . $progress . '%) in course ' . $translated_course_id . ' (' . $lang_code . ')', 'info');
                        break; // Use the first one we find with progress
                    }
                }
            }
            
            // If we found progress in another language, sync the individual lesson/quiz completions
            if ($source_progress > 0 && $source_course_id) {
                $this->sync_detailed_course_progress($user_id, $source_course_id, $course_id);
            }
            
        } catch (Exception $e) {
            $this->log('Error during enrollment progress sync: ' . $e->getMessage(), 'error');
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
        
        $this->log('Starting unenrollment progress sync for user ' . $user_id . ' in course ' . $course_id, 'info');
        
        // For now, we'll just log this event. In the future, we might want to
        // remove progress from all language versions or handle it differently
        // based on site requirements
        
        $this->log('User ' . $user_id . ' was unenrolled from course ' . $course_id . '. Progress sync handling can be customized here.', 'info');
    }
    
    /**
     * Sync detailed course progress (lessons, quizzes) from source to target course
     * 
     * @param int $user_id User ID
     * @param int $source_course_id Source course ID (with existing progress)
     * @param int $target_course_id Target course ID (newly enrolled)
     */
    private function sync_detailed_course_progress($user_id, $source_course_id, $target_course_id) {
        $this->log('Syncing detailed progress from course ' . $source_course_id . ' to course ' . $target_course_id, 'info');
        
        try {
            $student = new LLMS_Student($user_id);
            $source_course = llms_get_post($source_course_id);
            $target_course = llms_get_post($target_course_id);
            
            if (!$source_course || !$target_course) {
                $this->log('Could not load source or target course objects', 'error');
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
                            $this->log('✅ Synced lesson ' . $target_lesson_id . ' completion from enrollment progress', 'success');
                            
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
                            $this->log('✅ Synced quiz ' . $target_quiz_id . ' completion from enrollment progress', 'success');
                            
                            // Clear progress cache for this quiz and its course
                            $this->clear_progress_cache($user_id, $target_quiz_id, 'llms_quiz');
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log('Error during detailed course progress sync: ' . $e->getMessage(), 'error');
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
            $this->log('Object type ' . $object_type . ' not supported for sync', 'info');
            return;
        }
        
        $this->log('Starting object progress sync for user ' . $user_id . ' in ' . $object_type . ' ' . $object_id, 'info');
        
        try {
            // Get all translations of this object
            $translations = $this->get_object_translations($object_id, $object_type);
            
            if (empty($translations)) {
                $this->log('No translations found for ' . $object_type . ' ' . $object_id, 'info');
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
                    $this->log('User ' . $user_id . ' already completed ' . $object_type . ' ' . $translated_object_id . ' (' . $lang_code . ')', 'info');
                    continue;
                }
                
                // Mark object as complete in translated version
                $completion_result = llms_mark_complete($user_id, $translated_object_id, $object_type, 'wpml_progress_sync');
                
                if ($completion_result) {
                    $synced_count++;
                    $this->log('✅ Marked ' . $object_type . ' ' . $translated_object_id . ' (' . $lang_code . ') as complete for user ' . $user_id, 'success');
                    
                    // Clear progress cache for this object
                    $this->clear_progress_cache($user_id, $translated_object_id, $object_type);
                } else {
                    $this->log('❌ Failed to mark ' . $object_type . ' ' . $translated_object_id . ' (' . $lang_code . ') as complete for user ' . $user_id, 'error');
                }
            }
            
            if ($synced_count > 0) {
                $this->log('✅ Successfully synced ' . $object_type . ' progress to ' . $synced_count . ' translations', 'success');
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_object_progress_synced', $user_id, $object_id, $object_type, $translations, $synced_count);
            } else {
                $this->log('No new ' . $object_type . ' progress sync needed', 'info');
            }
            
        } catch (Exception $e) {
            $this->log('Error during ' . $object_type . ' progress sync: ' . $e->getMessage(), 'error');
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
            $this->log('Object type ' . $object_type . ' not supported for incompletion sync', 'info');
            return;
        }
        
        $this->log('Starting object incompletion sync for user ' . $user_id . ' in ' . $object_type . ' ' . $object_id, 'info');
        
        try {
            // Get all translations of this object
            $translations = $this->get_object_translations($object_id, $object_type);
            
            if (empty($translations)) {
                $this->log('No translations found for ' . $object_type . ' ' . $object_id, 'info');
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
                    $this->log('User ' . $user_id . ' already has incomplete ' . $object_type . ' ' . $translated_object_id . ' (' . $lang_code . ')', 'info');
                    continue;
                }
                
                // Mark object as incomplete in translated version
                $incompletion_result = llms_mark_incomplete($user_id, $translated_object_id, $object_type, 'wpml_progress_sync');
                
                if ($incompletion_result) {
                    $synced_count++;
                    $this->log('✅ Marked ' . $object_type . ' ' . $translated_object_id . ' (' . $lang_code . ') as incomplete for user ' . $user_id, 'success');
                    
                    // Clear progress cache for this object
                    $this->clear_progress_cache($user_id, $translated_object_id, $object_type);
                } else {
                    $this->log('❌ Failed to mark ' . $object_type . ' ' . $translated_object_id . ' (' . $lang_code . ') as incomplete for user ' . $user_id, 'error');
                }
            }
            
            if ($synced_count > 0) {
                $this->log('✅ Successfully synced ' . $object_type . ' incompletion to ' . $synced_count . ' translations', 'success');
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_object_incompletion_synced', $user_id, $object_id, $object_type, $translations, $synced_count);
            } else {
                $this->log('No new ' . $object_type . ' incompletion sync needed', 'info');
            }
            
        } catch (Exception $e) {
            $this->log('Error during ' . $object_type . ' incompletion sync: ' . $e->getMessage(), 'error');
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
    private function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $timestamp = current_time('Y-m-d H:i:s');
            $log_message = "[{$timestamp}] WPML-LLMS Progress Sync ({$level}): {$message}";
            error_log($log_message);
        }
    }
    
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
                        $this->log('Cleared section progress cache for section ' . $section_id, 'info');
                    }
                    
                    // Clear course cache for the course containing this lesson
                    $course_id = $lesson->get_parent_course();
                    if ($course_id) {
                        $student->set('course_' . $course_id . '_progress', '');
                        $this->log('Cleared course progress cache for course ' . $course_id, 'info');
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
                        $this->log('Cleared course progress cache for course ' . $course_id, 'info');
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
                            $this->log('Cleared course progress cache for course ' . $course_id . ' (via quiz)', 'info');
                        }
                    }
                    break;
            }
            
            // Force refresh of any cached progress data
            wp_cache_delete($user_id, 'llms_student_progress');
            
            $this->log('✅ Cleared progress cache for ' . $object_type . ' ' . $object_id . ' (user ' . $user_id . ')', 'success');
            
        } catch (Exception $e) {
            $this->log('Error clearing progress cache: ' . $e->getMessage(), 'error');
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
                    $this->log('🔄 Cleared course progress cache for course ' . $translated_course_id . ' (' . $lang_code . ')', 'info');
                }
            }
            
            // Also clear the original course cache
            $student->set('course_' . $course_id . '_progress', '');
            $this->log('✅ Cleared course progress cache for all translated courses', 'success');
            
        } catch (Exception $e) {
            $this->log('❌ Error clearing all course progress cache: ' . $e->getMessage(), 'error');
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
                        $this->log('✅ Found lesson ' . $lesson_id . ' completed in translation ' . $translated_lesson_id . ' (' . $lang_code . ')', 'info');
                        return true; // Found completion in another language!
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log('❌ Error checking lesson completion across translations: ' . $e->getMessage(), 'error');
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

    /**
     * CRITICAL METHOD: Sync quiz attempts across ALL translated versions
     * 
     * This fixes the quiz attempt dropdown synchronization issue.
     * When LifterLMS retrieves quiz attempts, we intercept that retrieval
     * and also get attempts from ALL translated versions of that quiz.
     * 
     * @param array $attempts Original attempts array
     * @param LLMS_Quiz $quiz Quiz object
     * @return array Combined attempts from all language versions
     */
    public function sync_quiz_attempts_across_translations($attempts, $quiz) {
        try {
            // Get the current quiz ID
            $quiz_id = $quiz->get('id');
            
            // Get all translated versions of this quiz
            $active_languages = apply_filters('wpml_active_languages', null);
            
            if (!$active_languages) {
                return $attempts;
            }
            
            // Get current student
            $student = llms_get_student();
            if (!$student) {
                return $attempts;
            }
            
            // Collect attempts from all translated quiz versions
            $all_attempts = $attempts; // Start with current attempts
            $processed_quiz_ids = array($quiz_id); // Track processed IDs to avoid duplicates
            
            foreach ($active_languages as $lang_code => $lang_info) {
                $translated_quiz_id = apply_filters('wpml_object_id', $quiz_id, 'quiz', false, $lang_code);
                
                if ($translated_quiz_id && $translated_quiz_id != $quiz_id && !in_array($translated_quiz_id, $processed_quiz_ids)) {
                    // Get attempts from this translated quiz
                    $translated_attempts = $student->quizzes()->get_attempts_by_quiz(
                        $translated_quiz_id,
                        array(
                            'per_page' => 25,
                            'sort'     => array(
                                'attempt' => 'DESC',
                            ),
                        )
                    );
                    
                    if (!empty($translated_attempts)) {
                        $all_attempts = array_merge($all_attempts, $translated_attempts);
                        $this->log('✅ Found ' . count($translated_attempts) . ' quiz attempts from translation ' . $translated_quiz_id . ' (' . $lang_code . ')', 'info');
                    }
                    
                    $processed_quiz_ids[] = $translated_quiz_id;
                }
            }
            
            // Sort all attempts by attempt number (descending)
            if (!empty($all_attempts)) {
                usort($all_attempts, function($a, $b) {
                    return $b->get('attempt') - $a->get('attempt');
                });
                
                $this->log('✅ Quiz attempt dropdown sync: Combined ' . count($all_attempts) . ' attempts from all languages', 'info');
            }
            
            return $all_attempts;
            
        } catch (Exception $e) {
            $this->log('❌ Error syncing quiz attempts across translations: ' . $e->getMessage(), 'error');
            return $attempts;
        }
    }

    /**
     * CRITICAL METHOD: Sync current attempt data across translations
     * 
     * This fixes the issue where attempt data (grades, results) differs between languages.
     * When viewing an attempt that shows 0% or incomplete status, we check if there's a 
     * completed attempt from a translated quiz version and redirect to that instead.
     */
    public function sync_current_attempt_data() {
        try {
            // Only run on quiz results pages with attempt_key
            $attempt_key = llms_filter_input_sanitize_string(INPUT_GET, 'attempt_key');
            if (!$attempt_key) {
                return;
            }
            
            // Get current student and attempt
            $student = llms_get_student();
            if (!$student) {
                return;
            }
            
            $current_attempt = $student->quizzes()->get_attempt_by_key($attempt_key);
            if (!$current_attempt) {
                return;
            }
            
            // Check if current attempt has poor data (0% grade or incomplete status)
            $current_grade = $current_attempt->get('grade');
            $current_status = $current_attempt->get('status');
            
            // If current attempt has good data, no need to sync
            if ($current_grade > 0 || in_array($current_status, array('passed', 'complete'))) {
                return;
            }
            
            $this->log("🔍 Current attempt shows poor data (grade: {$current_grade}%, status: {$current_status}). Checking translations...", 'info');
            
            // Current attempt has poor data - check translations
            $quiz_id = $current_attempt->get('quiz_id');
            $student_id = $current_attempt->get('student_id');
            $attempt_number = $current_attempt->get('attempt');
            
            // Get all translated versions of this quiz
            $active_languages = apply_filters('wpml_active_languages', null);
            if (!$active_languages) {
                return;
            }
            
            // Check attempts from all translated quiz versions
            foreach ($active_languages as $lang_code => $lang_info) {
                $translated_quiz_id = apply_filters('wpml_object_id', $quiz_id, 'quiz', false, $lang_code);
                
                if ($translated_quiz_id && $translated_quiz_id != $quiz_id) {
                    // Get attempts from this translated quiz
                    $translated_attempts = $student->quizzes()->get_attempts_by_quiz(
                        $translated_quiz_id,
                        array(
                            'per_page' => 25,
                            'sort'     => array(
                                'attempt' => 'DESC',
                            ),
                        )
                    );
                    
                    // Look for an attempt with the same attempt number but better data
                    foreach ($translated_attempts as $translated_attempt) {
                        if ($translated_attempt->get('attempt') == $attempt_number) {
                            $translated_grade = $translated_attempt->get('grade');
                            $translated_status = $translated_attempt->get('status');
                            
                            // If translated attempt has better data, redirect to it
                            if ($translated_grade > $current_grade || in_array($translated_status, array('passed', 'complete'))) {
                                $better_attempt_url = $translated_attempt->get_permalink();
                                
                                $this->log("✅ Found better attempt data in translation {$translated_quiz_id} (grade: {$translated_grade}%, status: {$translated_status}). Redirecting...", 'info');
                                
                                // Use JavaScript redirect to avoid header issues
                                echo '<script type="text/javascript">
                                    console.log("WPML-LifterLMS: Redirecting to better attempt data...");
                                    window.location.href = "' . esc_url($better_attempt_url) . '";
                                </script>';
                                return;
                            }
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log('❌ Error syncing current attempt data: ' . $e->getMessage(), 'error');
        }
    }
}

// Initialize the progress synchronizer
new WPML_LLMS_Progress_Sync();
