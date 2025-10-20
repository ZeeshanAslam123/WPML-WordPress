<?php
/**
 * WPML LifterLMS User Data Handler
 * 
 * Manages user progress, enrollments, and achievements across languages
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * User Data Handler Class
 */
class WPML_LifterLMS_User_Data {
    
    /**
     * User data configuration
     * @var array
     */
    private $user_data_config = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setup_user_data_config();
    }
    
    /**
     * Initialize the component
     */
    public function init() {
        add_action('wpml_loaded', array($this, 'setup_wpml_integration'));
        
        // Handle user enrollment
        add_action('llms_user_enrolled_in_course', array($this, 'handle_user_enrollment'), 10, 2);
        add_action('llms_user_removed_from_course', array($this, 'handle_user_unenrollment'), 10, 2);
        
        // Handle progress tracking
        add_action('lifterlms_lesson_completed', array($this, 'handle_lesson_completion'), 10, 2);
        add_action('lifterlms_course_completed', array($this, 'handle_course_completion'), 10, 2);
        add_action('lifterlms_quiz_completed', array($this, 'handle_quiz_completion'), 10, 3);
        
        // Handle achievements and certificates
        add_action('llms_user_earned_achievement', array($this, 'handle_achievement_earned'), 10, 3);
        add_action('llms_user_earned_certificate', array($this, 'handle_certificate_earned'), 10, 3);
        
        // Handle user dashboard
        add_filter('llms_get_student_dashboard_url', array($this, 'get_dashboard_url'), 10, 2);
        add_filter('llms_student_dashboard_courses', array($this, 'filter_dashboard_courses'), 10, 2);
        
        // Handle user queries
        add_filter('llms_get_enrolled_students', array($this, 'filter_enrolled_students'), 10, 3);
        add_filter('llms_get_student_progress', array($this, 'get_student_progress'), 10, 3);
    }
    
    /**
     * Setup user data configuration
     */
    private function setup_user_data_config() {
        $this->user_data_config = array(
            // Progress data - shared across languages
            'progress' => array(
                'sync_across_languages' => true,
                'language_specific_content' => false,
                'track_language_preference' => true
            ),
            
            // Enrollment data - shared across languages
            'enrollments' => array(
                'sync_across_languages' => true,
                'maintain_relationships' => true,
                'track_enrollment_language' => true
            ),
            
            // Achievement data - language specific content, shared progress
            'achievements' => array(
                'sync_progress' => true,
                'translate_content' => true,
                'language_specific_templates' => true
            ),
            
            // Certificate data - language specific content, shared progress
            'certificates' => array(
                'sync_progress' => true,
                'translate_content' => true,
                'language_specific_templates' => true,
                'user_language_preference' => true
            ),
            
            // Dashboard settings
            'dashboard' => array(
                'language_specific_urls' => true,
                'translate_content' => true,
                'sync_preferences' => true
            )
        );
        
        // Allow filtering of user data configuration
        $this->user_data_config = apply_filters('wpml_lifterlms_user_data_config', $this->user_data_config);
    }
    
    /**
     * Setup WPML integration
     */
    public function setup_wpml_integration() {
        // Handle user language detection
        add_filter('wpml_current_language', array($this, 'detect_user_language'), 10, 1);
        
        // Handle user meta translation
        add_filter('get_user_metadata', array($this, 'translate_user_meta'), 10, 4);
        
        // Handle student dashboard language
        add_action('llms_student_dashboard_init', array($this, 'set_dashboard_language'));
    }
    
    /**
     * Handle user enrollment
     * @param int $user_id
     * @param int $course_id
     */
    public function handle_user_enrollment($user_id, $course_id) {
        if (!$this->user_data_config['enrollments']['sync_across_languages']) {
            return;
        }
        
        // Get user's preferred language
        $user_language = $this->get_user_language($user_id);
        
        // Store enrollment language
        if ($this->user_data_config['enrollments']['track_enrollment_language']) {
            $this->set_enrollment_language($user_id, $course_id, $user_language);
        }
        
        // Sync enrollment across course translations
        if ($this->user_data_config['enrollments']['maintain_relationships']) {
            $this->sync_enrollment_across_languages($user_id, $course_id);
        }
    }
    
    /**
     * Handle user unenrollment
     * @param int $user_id
     * @param int $course_id
     */
    public function handle_user_unenrollment($user_id, $course_id) {
        if (!$this->user_data_config['enrollments']['sync_across_languages']) {
            return;
        }
        
        // Sync unenrollment across course translations
        if ($this->user_data_config['enrollments']['maintain_relationships']) {
            $this->sync_unenrollment_across_languages($user_id, $course_id);
        }
        
        // Clean up enrollment language data
        $this->remove_enrollment_language($user_id, $course_id);
    }
    
    /**
     * Handle lesson completion
     * @param int $user_id
     * @param int $lesson_id
     */
    public function handle_lesson_completion($user_id, $lesson_id) {
        if (!$this->user_data_config['progress']['sync_across_languages']) {
            return;
        }
        
        // Get user's preferred language
        $user_language = $this->get_user_language($user_id);
        
        // Store completion language
        $this->set_completion_language($user_id, $lesson_id, 'lesson', $user_language);
        
        // Sync progress across lesson translations
        $this->sync_lesson_progress_across_languages($user_id, $lesson_id);
    }
    
    /**
     * Handle course completion
     * @param int $user_id
     * @param int $course_id
     */
    public function handle_course_completion($user_id, $course_id) {
        if (!$this->user_data_config['progress']['sync_across_languages']) {
            return;
        }
        
        // Get user's preferred language
        $user_language = $this->get_user_language($user_id);
        
        // Store completion language
        $this->set_completion_language($user_id, $course_id, 'course', $user_language);
        
        // Sync progress across course translations
        $this->sync_course_progress_across_languages($user_id, $course_id);
    }
    
    /**
     * Handle quiz completion
     * @param int $user_id
     * @param int $quiz_id
     * @param LLMS_Quiz_Attempt $attempt
     */
    public function handle_quiz_completion($user_id, $quiz_id, $attempt) {
        if (!$this->user_data_config['progress']['sync_across_languages']) {
            return;
        }
        
        // Get user's preferred language
        $user_language = $this->get_user_language($user_id);
        
        // Store completion language
        $this->set_completion_language($user_id, $quiz_id, 'quiz', $user_language);
        
        // Sync quiz progress across translations
        $this->sync_quiz_progress_across_languages($user_id, $quiz_id, $attempt);
    }
    
    /**
     * Handle achievement earned
     * @param int $user_id
     * @param int $achievement_id
     * @param int $related_post_id
     */
    public function handle_achievement_earned($user_id, $achievement_id, $related_post_id) {
        // Get user's preferred language
        $user_language = $this->get_user_language($user_id);
        
        // Store achievement language
        $this->set_achievement_language($user_id, $achievement_id, $user_language);
        
        // Sync achievement progress if configured
        if ($this->user_data_config['achievements']['sync_progress']) {
            $this->sync_achievement_across_languages($user_id, $achievement_id, $related_post_id);
        }
        
        // Generate achievement in user's language
        if ($this->user_data_config['achievements']['translate_content']) {
            $this->generate_localized_achievement($user_id, $achievement_id, $user_language);
        }
    }
    
    /**
     * Handle certificate earned
     * @param int $user_id
     * @param int $certificate_id
     * @param int $related_post_id
     */
    public function handle_certificate_earned($user_id, $certificate_id, $related_post_id) {
        // Get user's preferred language
        $user_language = $this->get_user_language($user_id);
        
        // Store certificate language
        $this->set_certificate_language($user_id, $certificate_id, $user_language);
        
        // Sync certificate progress if configured
        if ($this->user_data_config['certificates']['sync_progress']) {
            $this->sync_certificate_across_languages($user_id, $certificate_id, $related_post_id);
        }
        
        // Generate certificate in user's language
        if ($this->user_data_config['certificates']['translate_content']) {
            $this->generate_localized_certificate($user_id, $certificate_id, $user_language);
        }
    }
    
    /**
     * Get dashboard URL
     * @param string $url
     * @param string $endpoint
     * @return string
     */
    public function get_dashboard_url($url, $endpoint = '') {
        if (!$this->user_data_config['dashboard']['language_specific_urls']) {
            return $url;
        }
        
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            $translated_url = apply_filters('wpml_permalink', $url, $current_language);
            return $translated_url ? $translated_url : $url;
        }
        
        return $url;
    }
    
    /**
     * Filter dashboard courses
     * @param array $courses
     * @param int $user_id
     * @return array
     */
    public function filter_dashboard_courses($courses, $user_id) {
        $current_language = apply_filters('wpml_current_language', null);
        
        if (!$current_language) {
            return $courses;
        }
        
        $filtered_courses = array();
        
        foreach ($courses as $course) {
            // Get course in current language
            $translated_course_id = apply_filters('wpml_object_id', $course->get('id'), 'course', false, $current_language);
            
            if ($translated_course_id) {
                $translated_course = llms_get_post($translated_course_id);
                if ($translated_course) {
                    $filtered_courses[] = $translated_course;
                }
            }
        }
        
        return $filtered_courses;
    }
    
    /**
     * Filter enrolled students
     * @param array $students
     * @param int $course_id
     * @param string $status
     * @return array
     */
    public function filter_enrolled_students($students, $course_id, $status) {
        // This filter ensures we get students enrolled in any language version of the course
        $course_translations = apply_filters('wpml_get_element_translations', null, $course_id, 'post_course');
        
        if ($course_translations) {
            $all_students = array();
            
            foreach ($course_translations as $translation) {
                if ($translation->element_id != $course_id) {
                    $translation_students = llms_get_enrolled_students($translation->element_id, $status);
                    $all_students = array_merge($all_students, $translation_students);
                }
            }
            
            // Remove duplicates and merge with original students
            $students = array_unique(array_merge($students, $all_students));
        }
        
        return $students;
    }
    
    /**
     * Get student progress
     * @param array $progress
     * @param int $user_id
     * @param int $course_id
     * @return array
     */
    public function get_student_progress($progress, $user_id, $course_id) {
        if (!$this->user_data_config['progress']['sync_across_languages']) {
            return $progress;
        }
        
        // Get progress from the original course (language-agnostic)
        $original_course_id = $this->get_original_post_id($course_id, 'course');
        
        if ($original_course_id && $original_course_id != $course_id) {
            $original_progress = llms_get_student_progress($user_id, $original_course_id);
            
            // Merge progress data
            if ($original_progress) {
                $progress = array_merge($progress, $original_progress);
            }
        }
        
        return $progress;
    }
    
    /**
     * Detect user language
     * @param string $current_language
     * @return string
     */
    public function detect_user_language($current_language) {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_language = $this->get_user_language($user_id);
            
            if ($user_language) {
                return $user_language;
            }
        }
        
        return $current_language;
    }
    
    /**
     * Translate user meta
     * @param mixed $value
     * @param int $user_id
     * @param string $meta_key
     * @param bool $single
     * @return mixed
     */
    public function translate_user_meta($value, $user_id, $meta_key, $single) {
        // Handle specific LifterLMS user meta fields that need translation
        $translatable_meta = array(
            'llms_billing_address_1',
            'llms_billing_address_2',
            'llms_billing_city',
            'llms_billing_state',
            'llms_billing_country'
        );
        
        if (in_array($meta_key, $translatable_meta)) {
            $user_language = $this->get_user_language($user_id);
            
            if ($user_language) {
                $translated_value = apply_filters('wpml_translate_single_string', 
                    $value, 
                    'LifterLMS User Meta', 
                    $meta_key . '_' . $user_id, 
                    $user_language
                );
                
                return $translated_value ? $translated_value : $value;
            }
        }
        
        return $value;
    }
    
    /**
     * Set dashboard language
     */
    public function set_dashboard_language() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_language = $this->get_user_language($user_id);
            
            if ($user_language) {
                do_action('wpml_switch_language', $user_language);
            }
        }
    }
    
    /**
     * Sync enrollment across languages
     * @param int $user_id
     * @param int $course_id
     */
    private function sync_enrollment_across_languages($user_id, $course_id) {
        $course_translations = apply_filters('wpml_get_element_translations', null, $course_id, 'post_course');
        
        if ($course_translations) {
            foreach ($course_translations as $translation) {
                if ($translation->element_id != $course_id) {
                    // Check if user is already enrolled in this translation
                    if (!llms_is_user_enrolled($user_id, $translation->element_id)) {
                        llms_enroll_student($user_id, $translation->element_id, 'admin');
                    }
                }
            }
        }
    }
    
    /**
     * Sync unenrollment across languages
     * @param int $user_id
     * @param int $course_id
     */
    private function sync_unenrollment_across_languages($user_id, $course_id) {
        $course_translations = apply_filters('wpml_get_element_translations', null, $course_id, 'post_course');
        
        if ($course_translations) {
            foreach ($course_translations as $translation) {
                if ($translation->element_id != $course_id) {
                    llms_unenroll_student($user_id, $translation->element_id, 'cancelled', 'admin');
                }
            }
        }
    }
    
    /**
     * Sync lesson progress across languages
     * @param int $user_id
     * @param int $lesson_id
     */
    private function sync_lesson_progress_across_languages($user_id, $lesson_id) {
        $lesson_translations = apply_filters('wpml_get_element_translations', null, $lesson_id, 'post_lesson');
        
        if ($lesson_translations) {
            foreach ($lesson_translations as $translation) {
                if ($translation->element_id != $lesson_id) {
                    // Mark lesson as complete in all languages
                    llms_mark_complete($user_id, $translation->element_id, 'lesson');
                }
            }
        }
    }
    
    /**
     * Sync course progress across languages
     * @param int $user_id
     * @param int $course_id
     */
    private function sync_course_progress_across_languages($user_id, $course_id) {
        $course_translations = apply_filters('wpml_get_element_translations', null, $course_id, 'post_course');
        
        if ($course_translations) {
            foreach ($course_translations as $translation) {
                if ($translation->element_id != $course_id) {
                    // Mark course as complete in all languages
                    llms_mark_complete($user_id, $translation->element_id, 'course');
                }
            }
        }
    }
    
    /**
     * Sync quiz progress across languages
     * @param int $user_id
     * @param int $quiz_id
     * @param LLMS_Quiz_Attempt $attempt
     */
    private function sync_quiz_progress_across_languages($user_id, $quiz_id, $attempt) {
        $quiz_translations = apply_filters('wpml_get_element_translations', null, $quiz_id, 'post_llms_quiz');
        
        if ($quiz_translations) {
            foreach ($quiz_translations as $translation) {
                if ($translation->element_id != $quiz_id) {
                    // Create quiz attempt for translated quiz
                    $translated_attempt = LLMS_Quiz_Attempt::init($translation->element_id, $attempt->get('lesson_id'), $user_id);
                    
                    if ($translated_attempt) {
                        // Copy attempt data
                        $translated_attempt->set('status', $attempt->get('status'));
                        $translated_attempt->set('grade', $attempt->get('grade'));
                        $translated_attempt->set('passed', $attempt->get('passed'));
                        $translated_attempt->save();
                    }
                }
            }
        }
    }
    
    /**
     * Sync achievement across languages
     * @param int $user_id
     * @param int $achievement_id
     * @param int $related_post_id
     */
    private function sync_achievement_across_languages($user_id, $achievement_id, $related_post_id) {
        $achievement_translations = apply_filters('wpml_get_element_translations', null, $achievement_id, 'post_llms_achievement');
        
        if ($achievement_translations) {
            foreach ($achievement_translations as $translation) {
                if ($translation->element_id != $achievement_id) {
                    // Award achievement in all languages
                    do_action('llms_user_earned_achievement', $user_id, $translation->element_id, $related_post_id);
                }
            }
        }
    }
    
    /**
     * Sync certificate across languages
     * @param int $user_id
     * @param int $certificate_id
     * @param int $related_post_id
     */
    private function sync_certificate_across_languages($user_id, $certificate_id, $related_post_id) {
        $certificate_translations = apply_filters('wpml_get_element_translations', null, $certificate_id, 'post_llms_certificate');
        
        if ($certificate_translations) {
            foreach ($certificate_translations as $translation) {
                if ($translation->element_id != $certificate_id) {
                    // Award certificate in all languages
                    do_action('llms_user_earned_certificate', $user_id, $translation->element_id, $related_post_id);
                }
            }
        }
    }
    
    /**
     * Generate localized achievement
     * @param int $user_id
     * @param int $achievement_id
     * @param string $language
     */
    private function generate_localized_achievement($user_id, $achievement_id, $language) {
        // Get achievement in user's language
        $translated_achievement_id = apply_filters('wpml_object_id', $achievement_id, 'llms_achievement', false, $language);
        
        if ($translated_achievement_id && $translated_achievement_id != $achievement_id) {
            // Generate achievement content in user's language
            do_action('wpml_switch_language', $language);
            
            // Create user achievement record
            $user_achievement = new LLMS_User_Achievement($user_id, $translated_achievement_id);
            $user_achievement->save();
            
            do_action('wpml_switch_language', null);
        }
    }
    
    /**
     * Generate localized certificate
     * @param int $user_id
     * @param int $certificate_id
     * @param string $language
     */
    private function generate_localized_certificate($user_id, $certificate_id, $language) {
        // Get certificate in user's language
        $translated_certificate_id = apply_filters('wpml_object_id', $certificate_id, 'llms_certificate', false, $language);
        
        if ($translated_certificate_id && $translated_certificate_id != $certificate_id) {
            // Generate certificate content in user's language
            do_action('wpml_switch_language', $language);
            
            // Create user certificate record
            $user_certificate = new LLMS_User_Certificate($user_id, $translated_certificate_id);
            $user_certificate->save();
            
            do_action('wpml_switch_language', null);
        }
    }
    
    /**
     * Set enrollment language
     * @param int $user_id
     * @param int $course_id
     * @param string $language
     */
    private function set_enrollment_language($user_id, $course_id, $language) {
        update_user_meta($user_id, '_llms_enrollment_language_' . $course_id, $language);
    }
    
    /**
     * Remove enrollment language
     * @param int $user_id
     * @param int $course_id
     */
    private function remove_enrollment_language($user_id, $course_id) {
        delete_user_meta($user_id, '_llms_enrollment_language_' . $course_id);
    }
    
    /**
     * Set completion language
     * @param int $user_id
     * @param int $post_id
     * @param string $post_type
     * @param string $language
     */
    private function set_completion_language($user_id, $post_id, $post_type, $language) {
        update_user_meta($user_id, '_llms_completion_language_' . $post_type . '_' . $post_id, $language);
    }
    
    /**
     * Set achievement language
     * @param int $user_id
     * @param int $achievement_id
     * @param string $language
     */
    private function set_achievement_language($user_id, $achievement_id, $language) {
        update_user_meta($user_id, '_llms_achievement_language_' . $achievement_id, $language);
    }
    
    /**
     * Set certificate language
     * @param int $user_id
     * @param int $certificate_id
     * @param string $language
     */
    private function set_certificate_language($user_id, $certificate_id, $language) {
        update_user_meta($user_id, '_llms_certificate_language_' . $certificate_id, $language);
    }
    
    /**
     * Get user language
     * @param int $user_id
     * @return string|null
     */
    private function get_user_language($user_id) {
        // Try to get user's admin language preference
        $user_language = get_user_meta($user_id, 'icl_admin_language', true);
        
        if (!$user_language) {
            // Try to get from user locale
            $user_locale = get_user_meta($user_id, 'locale', true);
            if ($user_locale) {
                $user_language = substr($user_locale, 0, 2);
            }
        }
        
        if (!$user_language) {
            // Fallback to current language
            $user_language = apply_filters('wpml_current_language', null);
        }
        
        return $user_language;
    }
    
    /**
     * Get original post ID (default language)
     * @param int $post_id
     * @param string $post_type
     * @return int
     */
    private function get_original_post_id($post_id, $post_type) {
        $original_id = apply_filters('wpml_master_post_from_duplicate', $post_id);
        return $original_id ? $original_id : $post_id;
    }
    
    /**
     * Get configuration
     * @param string $section
     * @return array
     */
    public function get_config($section = null) {
        if ($section) {
            return isset($this->user_data_config[$section]) ? $this->user_data_config[$section] : array();
        }
        
        return $this->user_data_config;
    }
}

