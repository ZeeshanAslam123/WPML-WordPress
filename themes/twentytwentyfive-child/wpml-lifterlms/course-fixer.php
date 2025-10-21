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
    
    private $logs = array();
    private $stats = array(
        'courses_processed' => 0,
        'relationships_fixed' => 0,
        'sections_synced' => 0,
        'lessons_synced' => 0,
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
        $this->log('Starting relationship fix for course ID: ' . $course_id, 'info');
        
        try {
            // Validate course exists
            $course = get_post($course_id);
            if (!$course || $course->post_type !== 'course') {
                throw new Exception('Invalid course ID: ' . $course_id);
            }
            
            $this->log('Course found: ' . $course->post_title, 'success');
            
            // Get course translations
            $translations = $this->get_course_translations($course_id);
            $this->log('Found ' . count($translations) . ' translations', 'info');
            
            // Fix WPML relationships
            $this->fix_wpml_relationships($course_id, $translations);
            
            // Sync course sections
            $this->sync_course_sections($course_id, $translations);
            
            // Sync course lessons
            $this->sync_course_lessons($course_id, $translations);
            
            // Sync enrollments
            $this->sync_course_enrollments($course_id, $translations);
            
            // Update course metadata
            $this->sync_course_metadata($course_id, $translations);
            
            $this->stats['courses_processed']++;
            $this->stats['end_time'] = current_time('mysql');
            
            $this->log('Course relationship fix completed successfully', 'success');
            
            return array(
                'success' => true,
                'logs' => $this->logs,
                'stats' => $this->stats
            );
            
        } catch (Exception $e) {
            $this->stats['errors']++;
            $this->log('Error: ' . $e->getMessage(), 'error');
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => $this->logs,
                'stats' => $this->stats
            );
        }
    }
    
    /**
     * Get course translations
     */
    private function get_course_translations($course_id) {
        $translations = array();
        
        if (!function_exists('wpml_get_language_information')) {
            $this->log('WPML functions not available', 'warning');
            return $translations;
        }
        
        // Get all active languages
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
                    
                    $this->log('Found translation: ' . $translated_post->post_title . ' (' . $lang_code . ')', 'info');
                }
            }
        }
        
        return $translations;
    }
    
    /**
     * Fix WPML relationships
     */
    private function fix_wpml_relationships($course_id, $translations) {
        $this->log('Fixing WPML relationships...', 'info');
        
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
                    $this->log('Fixed WPML relationship for ' . $translation['title'], 'success');
                }
            } else {
                $this->log('WPML relationship already exists for ' . $translation['title'], 'info');
            }
        }
    }
    
    /**
     * Sync course sections
     */
    private function sync_course_sections($course_id, $translations) {
        $this->log('Syncing course sections...', 'info');
        
        if (!class_exists('LLMS_Course')) {
            $this->log('LifterLMS not available', 'warning');
            return;
        }
        
        $main_course = new LLMS_Course($course_id);
        $main_sections = $main_course->get_sections('ids');
        
        if (empty($main_sections)) {
            $this->log('No sections found in main course', 'info');
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            $this->log('Processing sections for ' . $translation['title'] . ' (' . $lang_code . ')', 'info');
            
            // Sync section relationships
            foreach ($main_sections as $section_id) {
                $translated_section_id = apply_filters('wpml_object_id', $section_id, 'section', false, $lang_code);
                
                if ($translated_section_id && $translated_section_id !== $section_id) {
                    // Fix section → course relationship
                    $current_parent_course = get_post_meta($translated_section_id, '_llms_parent_course', true);
                    if ($current_parent_course != $translation['id']) {
                        update_post_meta($translated_section_id, '_llms_parent_course', $translation['id']);
                        $this->log('Fixed section ' . $translated_section_id . ' parent course to ' . $translation['id'], 'success');
                        $this->stats['sections_synced']++;
                    } else {
                        $this->log('Section ' . $translated_section_id . ' already has correct parent course', 'info');
                    }
                } else {
                    $this->log('No translation found for section ' . $section_id . ' in ' . $lang_code, 'warning');
                }
            }
            
            $this->log('Completed section sync for ' . $translation['title'], 'success');
        }
    }
    
    /**
     * Sync course lessons
     */
    private function sync_course_lessons($course_id, $translations) {
        $this->log('Syncing course lessons...', 'info');
        
        if (!class_exists('LLMS_Course')) {
            $this->log('LifterLMS not available', 'warning');
            return;
        }
        
        $main_course = new LLMS_Course($course_id);
        $main_lessons = $main_course->get_lessons('ids');
        
        if (empty($main_lessons)) {
            $this->log('No lessons found in main course', 'info');
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            $this->log('Processing lessons for ' . $translation['title'] . ' (' . $lang_code . ')', 'info');
            
            // Sync lesson relationships
            foreach ($main_lessons as $lesson_id) {
                $translated_lesson_id = apply_filters('wpml_object_id', $lesson_id, 'lesson', false, $lang_code);
                
                if ($translated_lesson_id && $translated_lesson_id !== $lesson_id) {
                    // Fix lesson → course relationship
                    $current_parent_course = get_post_meta($translated_lesson_id, '_llms_parent_course', true);
                    if ($current_parent_course != $translation['id']) {
                        update_post_meta($translated_lesson_id, '_llms_parent_course', $translation['id']);
                        $this->log('Fixed lesson ' . $translated_lesson_id . ' parent course to ' . $translation['id'], 'success');
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
                                $this->log('Fixed lesson ' . $translated_lesson_id . ' parent section to ' . $translated_section_id, 'success');
                            } else {
                                $this->log('Lesson ' . $translated_lesson_id . ' already has correct parent section', 'info');
                            }
                        } else {
                            $this->log('No translated section found for lesson ' . $translated_lesson_id, 'warning');
                        }
                    } else {
                        $this->log('Main lesson ' . $lesson_id . ' has no parent section', 'info');
                    }
                    
                    $this->stats['lessons_synced']++;
                } else {
                    $this->log('No translation found for lesson ' . $lesson_id . ' in ' . $lang_code, 'warning');
                }
            }
            
            $this->log('Completed lesson sync for ' . $translation['title'], 'success');
        }
    }
    
    /**
     * Sync course enrollments
     */
    private function sync_course_enrollments($course_id, $translations) {
        $this->log('Syncing course enrollments...', 'info');
        
        if (!class_exists('LLMS_Student')) {
            $this->log('LifterLMS Student class not available', 'warning');
            return;
        }
        
        // Get students enrolled in main course
        $main_course = new LLMS_Course($course_id);
        $enrolled_students = $main_course->get_students();
        
        if (empty($enrolled_students)) {
            $this->log('No enrolled students found', 'info');
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            foreach ($enrolled_students as $student_id) {
                $student = new LLMS_Student($student_id);
                
                // Check if student is already enrolled in translated course
                if (!$student->is_enrolled($translation['id'])) {
                    // Enroll student in translated course
                    $student->enroll($translation['id']);
                    $this->stats['enrollments_synced']++;
                    
                    $this->log('Enrolled student ' . $student_id . ' in ' . $translation['title'], 'success');
                }
            }
        }
    }
    
    /**
     * Sync course metadata
     */
    private function sync_course_metadata($course_id, $translations) {
        $this->log('Syncing course metadata...', 'info');
        
        // Define metadata fields that should be synced
        $sync_fields = array(
            '_llms_length',
            '_llms_difficulty_id',
            '_llms_track_id',
            '_llms_course_image',
            '_llms_course_video_embed',
            '_llms_audio_embed',
            '_llms_prerequisite',
            '_llms_has_prerequisite'
        );
        
        foreach ($translations as $lang_code => $translation) {
            foreach ($sync_fields as $field) {
                $main_value = get_post_meta($course_id, $field, true);
                
                if (!empty($main_value)) {
                    update_post_meta($translation['id'], $field, $main_value);
                }
            }
            
            $this->log('Synced metadata for ' . $translation['title'], 'success');
        }
    }
    
    /**
     * Log a message
     */
    private function log($message, $type = 'info') {
        $timestamp = current_time('H:i:s');
        
        $this->logs[] = array(
            'timestamp' => $timestamp,
            'message' => $message,
            'type' => $type
        );
        
        // Also log to WordPress debug log if enabled
        wpml_llms_log($message, $type);
    }
    
    /**
     * Get processing statistics
     */
    public function get_stats() {
        return $this->stats;
    }
    
    /**
     * Get processing logs
     */
    public function get_logs() {
        return $this->logs;
    }
    
    /**
     * Clear logs and stats
     */
    public function reset() {
        $this->logs = array();
        $this->init_stats();
    }
}

new WPML_LLMS_Course_Fixer();
