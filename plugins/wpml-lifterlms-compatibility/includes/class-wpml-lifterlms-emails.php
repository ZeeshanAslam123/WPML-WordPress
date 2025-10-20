<?php
/**
 * WPML LifterLMS Emails Handler
 * 
 * Handles translation of LifterLMS email templates and notifications
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Emails Handler Class
 */
class WPML_LifterLMS_Emails {
    
    /**
     * Initialize the component
     */
    public function init() {
        add_action('wpml_loaded', array($this, 'setup_wpml_integration'));
        
        // Handle email translation
        add_filter('llms_email_subject', array($this, 'translate_email_subject'), 10, 3);
        add_filter('llms_email_message', array($this, 'translate_email_message'), 10, 3);
        add_filter('llms_email_heading', array($this, 'translate_email_heading'), 10, 3);
        
        // Handle email templates
        add_filter('llms_load_template', array($this, 'load_translated_template'), 10, 3);
        
        // Register email strings for translation
        add_action('init', array($this, 'register_email_strings'), 20);
    }
    
    /**
     * Setup WPML integration
     */
    public function setup_wpml_integration() {
        // Handle email language detection
        add_filter('wpml_current_language', array($this, 'detect_email_language'), 10, 1);
        
        // Handle email sending in correct language
        add_action('llms_send_email', array($this, 'set_email_language'), 5, 2);
    }
    
    /**
     * Translate email subject
     * @param string $subject
     * @param LLMS_Email $email
     * @param array $data
     * @return string
     */
    public function translate_email_subject($subject, $email, $data) {
        $language = $this->get_email_language($email, $data);
        
        if ($language) {
            $email_id = $email->get('id');
            $translated_subject = apply_filters('wpml_translate_single_string', 
                $subject, 
                'LifterLMS Emails', 
                $email_id . '_subject', 
                $language
            );
            
            return $translated_subject ? $translated_subject : $subject;
        }
        
        return $subject;
    }
    
    /**
     * Translate email message
     * @param string $message
     * @param LLMS_Email $email
     * @param array $data
     * @return string
     */
    public function translate_email_message($message, $email, $data) {
        $language = $this->get_email_language($email, $data);
        
        if ($language) {
            $email_id = $email->get('id');
            $translated_message = apply_filters('wpml_translate_single_string', 
                $message, 
                'LifterLMS Emails', 
                $email_id . '_message', 
                $language
            );
            
            return $translated_message ? $translated_message : $message;
        }
        
        return $message;
    }
    
    /**
     * Translate email heading
     * @param string $heading
     * @param LLMS_Email $email
     * @param array $data
     * @return string
     */
    public function translate_email_heading($heading, $email, $data) {
        $language = $this->get_email_language($email, $data);
        
        if ($language) {
            $email_id = $email->get('id');
            $translated_heading = apply_filters('wpml_translate_single_string', 
                $heading, 
                'LifterLMS Emails', 
                $email_id . '_heading', 
                $language
            );
            
            return $translated_heading ? $translated_heading : $heading;
        }
        
        return $heading;
    }
    
    /**
     * Load translated template
     * @param string $template
     * @param string $template_name
     * @param array $args
     * @return string
     */
    public function load_translated_template($template, $template_name, $args) {
        // Check if this is an email template
        if (strpos($template_name, 'emails/') === 0) {
            $current_language = apply_filters('wpml_current_language', null);
            
            if ($current_language) {
                // Look for language-specific template
                $translated_template = $this->get_translated_template_path($template, $current_language);
                
                if ($translated_template && file_exists($translated_template)) {
                    return $translated_template;
                }
            }
        }
        
        return $template;
    }
    
    /**
     * Register email strings for translation
     */
    public function register_email_strings() {
        // Get all LifterLMS email posts
        $emails = get_posts(array(
            'post_type' => 'llms_email',
            'post_status' => 'publish',
            'numberposts' => -1,
            'suppress_filters' => true
        ));
        
        foreach ($emails as $email_post) {
            $email = new LLMS_Email($email_post->ID);
            
            // Register subject
            $subject = $email->get('subject');
            if ($subject) {
                do_action('wpml_register_single_string', 
                    'LifterLMS Emails', 
                    $email_post->post_name . '_subject', 
                    $subject
                );
            }
            
            // Register heading
            $heading = $email->get('heading');
            if ($heading) {
                do_action('wpml_register_single_string', 
                    'LifterLMS Emails', 
                    $email_post->post_name . '_heading', 
                    $heading
                );
            }
            
            // Register message
            $message = $email->get('message');
            if ($message) {
                do_action('wpml_register_single_string', 
                    'LifterLMS Emails', 
                    $email_post->post_name . '_message', 
                    $message
                );
            }
        }
        
        // Register default email strings
        $this->register_default_email_strings();
    }
    
    /**
     * Register default email strings
     */
    private function register_default_email_strings() {
        $default_strings = array(
            'enrollment_subject' => __('Welcome to {site_title}!', 'lifterlms'),
            'enrollment_message' => __('Hi {student_first_name},\n\nYou have been enrolled in {course_title}.\n\nYou can access your course at {course_url}.\n\nThanks!', 'lifterlms'),
            'completion_subject' => __('Congratulations! You completed {course_title}', 'lifterlms'),
            'completion_message' => __('Hi {student_first_name},\n\nCongratulations on completing {course_title}!\n\nYou can view your certificate at {certificate_url}.\n\nThanks!', 'lifterlms'),
            'quiz_passed_subject' => __('You passed {quiz_title}!', 'lifterlms'),
            'quiz_failed_subject' => __('You did not pass {quiz_title}', 'lifterlms'),
            'achievement_earned_subject' => __('You earned an achievement!', 'lifterlms'),
            'certificate_earned_subject' => __('You earned a certificate!', 'lifterlms')
        );
        
        foreach ($default_strings as $key => $string) {
            do_action('wpml_register_single_string', 
                'LifterLMS Default Emails', 
                $key, 
                $string
            );
        }
    }
    
    /**
     * Detect email language
     * @param string $current_language
     * @return string
     */
    public function detect_email_language($current_language) {
        // Check if we're in an email context
        if ($this->is_email_context()) {
            $email_language = $this->get_context_email_language();
            return $email_language ? $email_language : $current_language;
        }
        
        return $current_language;
    }
    
    /**
     * Set email language
     * @param LLMS_Email $email
     * @param array $data
     */
    public function set_email_language($email, $data) {
        $language = $this->get_email_language($email, $data);
        
        if ($language) {
            do_action('wpml_switch_language', $language);
        }
    }
    
    /**
     * Get email language
     * @param LLMS_Email $email
     * @param array $data
     * @return string|null
     */
    private function get_email_language($email, $data) {
        // Try to get language from user
        if (isset($data['user_id'])) {
            return $this->get_user_language($data['user_id']);
        }
        
        // Try to get language from student
        if (isset($data['student']) && is_object($data['student'])) {
            return $this->get_user_language($data['student']->get('id'));
        }
        
        // Try to get language from order
        if (isset($data['order']) && is_object($data['order'])) {
            return $this->get_order_language($data['order']->get('id'));
        }
        
        // Try to get language from course
        if (isset($data['course']) && is_object($data['course'])) {
            return $this->get_post_language($data['course']->get('id'), 'course');
        }
        
        // Try to get language from lesson
        if (isset($data['lesson']) && is_object($data['lesson'])) {
            return $this->get_post_language($data['lesson']->get('id'), 'lesson');
        }
        
        // Fallback to current language
        return apply_filters('wpml_current_language', null);
    }
    
    /**
     * Get translated template path
     * @param string $template
     * @param string $language
     * @return string|null
     */
    private function get_translated_template_path($template, $language) {
        $template_dir = dirname($template);
        $template_file = basename($template);
        $template_name = pathinfo($template_file, PATHINFO_FILENAME);
        $template_ext = pathinfo($template_file, PATHINFO_EXTENSION);
        
        // Look for language-specific template
        $translated_template = $template_dir . '/' . $template_name . '-' . $language . '.' . $template_ext;
        
        if (file_exists($translated_template)) {
            return $translated_template;
        }
        
        // Look in theme directory
        $theme_template = get_template_directory() . '/lifterlms/emails/' . $template_name . '-' . $language . '.' . $template_ext;
        
        if (file_exists($theme_template)) {
            return $theme_template;
        }
        
        return null;
    }
    
    /**
     * Check if we're in an email context
     * @return bool
     */
    private function is_email_context() {
        // Check if we're sending an email
        return did_action('llms_send_email') || doing_action('llms_send_email');
    }
    
    /**
     * Get context email language
     * @return string|null
     */
    private function get_context_email_language() {
        // This would need to be implemented based on how you track email context
        // For now, return null to use other detection methods
        return null;
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
        
        return $user_language;
    }
    
    /**
     * Get order language
     * @param int $order_id
     * @return string|null
     */
    private function get_order_language($order_id) {
        return get_post_meta($order_id, '_wpml_language', true);
    }
    
    /**
     * Get post language
     * @param int $post_id
     * @param string $post_type
     * @return string|null
     */
    private function get_post_language($post_id, $post_type) {
        return apply_filters('wpml_element_language_code', null, array(
            'element_id' => $post_id,
            'element_type' => 'post_' . $post_type
        ));
    }
}

