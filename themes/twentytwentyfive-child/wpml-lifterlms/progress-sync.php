<?php
/**
 * WPML LifterLMS Progress Synchronization
 * 
 * Automatically synchronizes lesson progress across all language versions.
 * When a user completes a lesson in one language, the same lesson is marked 
 * as completed in all translated versions of the course.
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
        // Hook into LifterLMS lesson completion
        add_action('lifterlms_lesson_completed', array($this, 'sync_lesson_progress'), 10, 2);
        
        // Hook into general completion (covers lessons, sections, courses)
        add_action('llms_mark_complete', array($this, 'sync_object_progress'), 10, 4);
        
        // Hook into incompletion as well
        add_action('llms_mark_incomplete', array($this, 'sync_object_progress_incomplete'), 10, 4);
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
                } else {
                    $this->log('❌ Failed to mark lesson ' . $translated_lesson_id . ' (' . $lang_code . ') as complete for user ' . $user_id, 'error');
                }
            }
            
            if ($synced_count > 0) {
                $this->log('✅ Successfully synced lesson progress to ' . $synced_count . ' translated lessons', 'success');
                
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
        
        // Only sync lessons for now (sections and courses are more complex)
        if ($object_type !== 'lesson') {
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
        
        // Only sync lessons for now
        if ($object_type !== 'lesson') {
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
            'course' => 'course'
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
}

// Initialize the progress synchronizer
new WPML_LLMS_Progress_Sync();
