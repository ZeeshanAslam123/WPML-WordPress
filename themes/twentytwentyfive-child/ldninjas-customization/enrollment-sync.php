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

        add_action('llms_user_enrolled_in_course', array($this, 'sync_course_enrollment'), 10, 2);
        add_action('llms_user_added_to_membership_level', array($this, 'sync_membership_enrollment'), 10, 2);
    }
    
    /**
     * Sync course enrollment across all language versions
     * 
     * @param int $user_id User ID who was enrolled
     * @param int $course_id Course ID they were enrolled in
     */
    public function sync_course_enrollment($user_id, $course_id) {

        if (doing_action('llms_user_enrolled_in_course') > 1) {
            return;
        }
        
        if (!$this->is_sync_enabled()) {
            return;
        }
        
        $translations = $this->get_course_translations($course_id);
        
        if (empty($translations)) {
            return;
        }
        
        $enrolled_count = 0;
        
        foreach ($translations as $lang_code => $translation_data) {
            $translated_course_id = $translation_data['id'];
            
            // Skip if it's the same course (original)
            if ($translated_course_id == $course_id) {
                continue;
            }
            
            if (llms_is_user_enrolled($user_id, $translated_course_id)) {
                continue;
            }
            
            $enrollment_result = llms_enroll_student($user_id, $translated_course_id, 'wpml_sync');
            
            if ($enrollment_result) {
                $enrolled_count++;
            }
        }
        
        if ($enrolled_count > 0) {
            
            do_action('wpml_llms_enrollment_synced', $user_id, $course_id, $translations, $enrolled_count);
        }
    }
    
    /**
     * Sync membership enrollment across all language versions
     * 
     * @param int $user_id User ID who was enrolled
     * @param int $membership_id Membership ID they were enrolled in
     */
    public function sync_membership_enrollment($user_id, $membership_id) {

        if (doing_action('llms_user_added_to_membership_level') > 1) {
            return;
        }
        
        if (!$this->is_sync_enabled()) {
            return;
        }
        
        $translations = $this->get_membership_translations($membership_id);
        
        if (empty($translations)) {
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
                continue;
            }
            
            // Enroll user in translated membership
            $enrollment_result = llms_enroll_student($user_id, $translated_membership_id, 'wpml_sync');
            
            if ($enrollment_result) {
                $enrolled_count++;
            }
        }
        
        if ($enrolled_count > 0) {
            
            do_action('wpml_llms_membership_enrollment_synced', $user_id, $membership_id, $translations, $enrolled_count);
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
}

new WPML_LLMS_Enrollment_Sync();
