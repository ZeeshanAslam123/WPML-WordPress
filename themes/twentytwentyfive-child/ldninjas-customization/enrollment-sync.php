<?php
/**
 * WPML LifterLMS Enrollment Synchronization
 * 
 * Automatically synchronizes course enrollments across all language versions.
 * When a user enrolls in a course in one language, they are automatically 
 * enrolled in all translated versions of that course.
 * 
 * @package WPML_LifterLMS
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPML LifterLMS Enrollment Synchronizer
 */
class WPML_LLMS_Enrollment_Sync {
    
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
        // Hook into LifterLMS course enrollment
        add_action('llms_user_enrolled_in_course', array($this, 'sync_course_enrollment'), 10, 2);
        
        // Hook into LifterLMS membership enrollment (if needed in future)
        add_action('llms_user_added_to_membership_level', array($this, 'sync_membership_enrollment'), 10, 2);
    }
    
    /**
     * Sync course enrollment across all language versions
     * 
     * @param int $user_id User ID who was enrolled
     * @param int $course_id Course ID they were enrolled in
     */
    public function sync_course_enrollment($user_id, $course_id) {
        // Prevent infinite loops
        if (doing_action('llms_user_enrolled_in_course') > 1) {
            return;
        }
        
        // Check if sync is enabled
        if (!$this->is_sync_enabled()) {
            return;
        }
        
        $this->log('Starting enrollment sync for user ' . $user_id . ' in course ' . $course_id, 'info');
        
        try {
            // Get all translations of this course
            $translations = $this->get_course_translations($course_id);
            
            if (empty($translations)) {
                $this->log('No translations found for course ' . $course_id, 'info');
                return;
            }
            
            $enrolled_count = 0;
            
            foreach ($translations as $lang_code => $translation_data) {
                $translated_course_id = $translation_data['id'];
                
                // Skip if it's the same course (original)
                if ($translated_course_id == $course_id) {
                    continue;
                }
                
                // Check if user is already enrolled in this translation
                if (llms_is_user_enrolled($user_id, $translated_course_id)) {
                    $this->log('User ' . $user_id . ' already enrolled in course ' . $translated_course_id . ' (' . $lang_code . ')', 'info');
                    continue;
                }
                
                // Enroll user in translated course
                $enrollment_result = llms_enroll_student($user_id, $translated_course_id, 'wpml_sync');
                
                if ($enrollment_result) {
                    $enrolled_count++;
                    $this->log('✅ Enrolled user ' . $user_id . ' in course ' . $translated_course_id . ' (' . $lang_code . ')', 'success');
                } else {
                    $this->log('❌ Failed to enroll user ' . $user_id . ' in course ' . $translated_course_id . ' (' . $lang_code . ')', 'error');
                }
            }
            
            if ($enrolled_count > 0) {
                $this->log('✅ Successfully enrolled user in ' . $enrolled_count . ' translated courses', 'success');
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_enrollment_synced', $user_id, $course_id, $translations, $enrolled_count);
            } else {
                $this->log('No new enrollments needed', 'info');
            }
            
        } catch (Exception $e) {
            $this->log('Error during enrollment sync: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Sync membership enrollment across all language versions
     * 
     * @param int $user_id User ID who was enrolled
     * @param int $membership_id Membership ID they were enrolled in
     */
    public function sync_membership_enrollment($user_id, $membership_id) {
        // Prevent infinite loops
        if (doing_action('llms_user_added_to_membership_level') > 1) {
            return;
        }
        
        // Check if sync is enabled
        if (!$this->is_sync_enabled()) {
            return;
        }
        
        $this->log('Starting membership enrollment sync for user ' . $user_id . ' in membership ' . $membership_id, 'info');
        
        try {
            // Get all translations of this membership
            $translations = $this->get_membership_translations($membership_id);
            
            if (empty($translations)) {
                $this->log('No translations found for membership ' . $membership_id, 'info');
                return;
            }
            
            $enrolled_count = 0;
            
            foreach ($translations as $lang_code => $translation_data) {
                $translated_membership_id = $translation_data['id'];
                
                // Skip if it's the same membership (original)
                if ($translated_membership_id == $membership_id) {
                    continue;
                }
                
                // Check if user is already enrolled in this translation
                if (llms_is_user_enrolled($user_id, $translated_membership_id)) {
                    $this->log('User ' . $user_id . ' already enrolled in membership ' . $translated_membership_id . ' (' . $lang_code . ')', 'info');
                    continue;
                }
                
                // Enroll user in translated membership
                $enrollment_result = llms_enroll_student($user_id, $translated_membership_id, 'wpml_sync');
                
                if ($enrollment_result) {
                    $enrolled_count++;
                    $this->log('✅ Enrolled user ' . $user_id . ' in membership ' . $translated_membership_id . ' (' . $lang_code . ')', 'success');
                } else {
                    $this->log('❌ Failed to enroll user ' . $user_id . ' in membership ' . $translated_membership_id . ' (' . $lang_code . ')', 'error');
                }
            }
            
            if ($enrolled_count > 0) {
                $this->log('✅ Successfully enrolled user in ' . $enrolled_count . ' translated memberships', 'success');
                
                // Fire custom action for other plugins to hook into
                do_action('wpml_llms_membership_enrollment_synced', $user_id, $membership_id, $translations, $enrolled_count);
            } else {
                $this->log('No new membership enrollments needed', 'info');
            }
            
        } catch (Exception $e) {
            $this->log('Error during membership enrollment sync: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Get all translations of a course
     * 
     * @param int $course_id Course ID
     * @return array Array of translations
     */
    private function get_course_translations($course_id) {
        if (!function_exists('icl_get_languages')) {
            return array();
        }
        
        $translations = array();
        $languages = icl_get_languages('skip_missing=0');
        
        foreach ($languages as $lang_code => $language) {
            $translated_id = apply_filters('wpml_object_id', $course_id, 'course', false, $lang_code);
            
            if ($translated_id && $translated_id != $course_id) {
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
     * Get all translations of a membership
     * 
     * @param int $membership_id Membership ID
     * @return array Array of translations
     */
    private function get_membership_translations($membership_id) {
        if (!function_exists('icl_get_languages')) {
            return array();
        }
        
        $translations = array();
        $languages = icl_get_languages('skip_missing=0');
        
        foreach ($languages as $lang_code => $language) {
            $translated_id = apply_filters('wpml_object_id', $membership_id, 'llms_membership', false, $lang_code);
            
            if ($translated_id && $translated_id != $membership_id) {
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
     * Check if enrollment sync is enabled
     * 
     * @return bool
     */
    private function is_sync_enabled() {
        return get_option('wpml_llms_enrollment_sync_enabled', true);
    }
    

    
    /**
     * Log messages for debugging
     * 
     * @param string $message Log message
     * @param string $level Log level (info, success, warning, error)
     */
    private function log($message, $level = 'info') {
        // Log to LifterLMS logs if available
        if (function_exists('llms_log')) {
            llms_log($message, 'wpml-enrollment-sync');
        }
        
        // Use our main logging function
        if (function_exists('wpml_llms_log')) {
            wpml_llms_log('[Enrollment-Sync] ' . $message, $level);
        }
    }
}

// Initialize the enrollment synchronizer
new WPML_LLMS_Enrollment_Sync();
