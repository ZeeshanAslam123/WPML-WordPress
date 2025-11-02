<?php
/**
 * WPML LifterLMS Quiz Meta Synchronization
 * 
 * Automatically synchronizes all LifterLMS quiz meta fields across WPML language versions
 * when the English (source) quiz is saved or updated.
 * 
 * @package WPML_LifterLMS
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPML LifterLMS Quiz Meta Sync Class
 */
class WPML_LLMS_Quiz_Meta_Sync {
    
    /**
     * Statistics tracking
     */
    private $stats = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_stats();
        
        // Hook into quiz save to automatically sync meta fields
        add_action('save_post_llms_quiz', array($this, 'handle_quiz_meta_sync'), 20, 3);
    }
    
    /**
     * Initialize statistics
     */
    private function init_stats() {
        $this->stats = array(
            'quiz_meta_synced' => 0,
        );
    }
    
    /**
     * Handle quiz meta synchronization when a quiz is saved
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an existing post being updated
     */
    public function handle_quiz_meta_sync($post_id, $post, $update) {
        // Skip if this is an autosave, revision, or bulk edit
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) || (defined('DOING_BULK_EDIT') && DOING_BULK_EDIT)) {
            return;
        }
        
        // Skip if WPML functions are not available
        if (!function_exists('wpml_get_language_information')) {
            return;
        }
        
        // Get the language of the current post
        $post_language_info = wpml_get_language_information($post_id);
        if (is_wp_error($post_language_info)) {
            return;
        }
        
        // Only sync if this is the English (source) quiz
        $default_language = apply_filters('wpml_default_language', null);
        if ($post_language_info['language_code'] !== $default_language) {
            return;
        }
        
        // Prevent infinite loops
        if (defined('WPML_LLMS_SYNCING_QUIZ_META')) {
            return;
        }
        define('WPML_LLMS_SYNCING_QUIZ_META', true);
        
        try {
            // Get translations and sync meta fields
            $translations = $this->get_quiz_translations($post_id);
            if (!empty($translations)) {
                $this->sync_quiz_metadata($post_id, $translations);
            }
        } catch (Exception $e) {
            // Handle errors silently to prevent breaking the save process
            error_log('WPML LLMS Quiz Meta Sync Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get quiz translations
     */
    private function get_quiz_translations($quiz_id) {
        if (!function_exists('wpml_get_language_information')) {
            return array();
        }
        
        $translations = array();
        $trid = apply_filters('wpml_element_trid', null, $quiz_id, 'post_llms_quiz');
        
        if ($trid) {
            $translation_data = apply_filters('wpml_get_element_translations', null, $trid, 'post_llms_quiz');
            
            foreach ($translation_data as $lang_code => $translation) {
                if ($translation->element_id != $quiz_id && $translation->element_id) {
                    $translations[$lang_code] = array(
                        'id' => $translation->element_id,
                        'lang' => $lang_code
                    );
                }
            }
        }
        
        return $translations;
    }
    
    /**
     * Sync quiz metadata across all translations
     * 
     * @param int $quiz_id Source quiz ID
     * @param array $translations Array of translation data
     */
    private function sync_quiz_metadata($quiz_id, $translations) {
        
        // Comprehensive list of LifterLMS quiz meta fields to sync
        $sync_fields = array(
            // Quiz Relationship & Structure
            '_llms_lesson_id',                       // Parent Lesson ID
            '_llms_quiz',                            // Quiz ID (self-reference)
            '_llms_quiz_enabled',                    // Quiz Enabled
            '_llms_assigned_quiz',                   // Assigned Quiz
            
            // Quiz Questions & Content
            '_llms_questions',                       // Quiz Questions Array
            '_llms_question_order',                  // Question Order
            '_llms_randomize_questions',             // Randomize Questions
            '_llms_show_correct_answer',             // Show Correct Answers
            
            // Quiz Settings & Configuration
            '_llms_passing_percent',                 // Passing Percentage
            '_llms_require_passing_grade',           // Require Passing Grade
            '_llms_quiz_attempts',                   // Number of Attempts Allowed
            '_llms_unlimited_attempts',              // Unlimited Attempts
            '_llms_quiz_time_limit',                 // Quiz Time Limit (minutes)
            '_llms_quiz_time_limit_enabled',         // Enable Time Limit
            
            // Quiz Behavior & Display
            '_llms_show_results',                    // Show Results to Student
            '_llms_show_correct_answer',             // Show Correct Answers
            '_llms_quiz_description',                // Quiz Description
            '_llms_quiz_instructions',               // Quiz Instructions
            '_llms_quiz_intro',                      // Quiz Introduction
            
            // Grading & Feedback
            '_llms_quiz_grading',                    // Grading Method
            '_llms_quiz_grade_type',                 // Grade Type (points, percentage)
            '_llms_quiz_points',                     // Total Points
            '_llms_quiz_feedback',                   // Quiz Feedback
            '_llms_quiz_success_message',            // Success Message
            '_llms_quiz_failure_message',            // Failure Message
            
            // Quiz Restrictions & Access
            '_llms_quiz_restricted',                 // Quiz Restricted
            '_llms_quiz_restriction_message',        // Restriction Message
            '_llms_quiz_prerequisite',               // Quiz Prerequisite
            '_llms_quiz_has_prerequisite',           // Has Prerequisite
            
            // Advanced Quiz Settings
            '_llms_quiz_retake_enabled',             // Allow Retakes
            '_llms_quiz_retake_delay',               // Retake Delay
            '_llms_quiz_randomize_answers',          // Randomize Answer Choices
            '_llms_quiz_show_progress',              // Show Progress Bar
            '_llms_quiz_navigation',                 // Quiz Navigation (linear, free)
            
            // Quiz Completion & Certificates
            '_llms_quiz_certificate',               // Quiz Certificate
            '_llms_quiz_achievement',               // Quiz Achievement
            '_llms_quiz_completion_redirect',       // Completion Redirect
            '_llms_quiz_completion_redirect_url',   // Completion Redirect URL
            
            // Quiz Analytics & Tracking
            '_llms_quiz_track_attempts',            // Track Attempts
            '_llms_quiz_analytics_enabled',         // Enable Analytics
            '_llms_quiz_detailed_results',          // Detailed Results
            
            // Legacy Fields (for backward compatibility)
            '_quiz_enabled',                        // Legacy Quiz Enabled
            '_quiz_lesson_id',                      // Legacy Lesson ID
            '_quiz_attempts',                       // Legacy Attempts
            '_quiz_passing_percent',                // Legacy Passing Percent
            
            // Custom Fields
            '_llms_quiz_custom_fields',             // Custom Fields Data
            '_llms_quiz_meta',                      // Additional Quiz Meta
            '_llms_quiz_settings',                  // Quiz Settings Array
        );
        
        // Add stats tracking
        $settings_synced = 0;
        
        foreach ($translations as $lang_code => $translation) {
            foreach ($sync_fields as $field) {
                $main_value = get_post_meta($quiz_id, $field, true);
                $current_value = get_post_meta($translation['id'], $field, true);
                
                // Only update if values are different or if main value exists and current doesn't
                if ($main_value !== $current_value && ($main_value !== '' || $current_value !== '')) {
                    if ($main_value === '' || $main_value === false || $main_value === null) {
                        // Delete the meta if main quiz doesn't have it
                        delete_post_meta($translation['id'], $field);
                    } else {
                        // Update with main quiz value
                        update_post_meta($translation['id'], $field, $main_value);
                    }
                    $settings_synced++;
                }
            }
            
            // Also sync post excerpt (quiz description)
            $main_post = get_post($quiz_id);
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
        $this->stats['quiz_meta_synced'] = $settings_synced;
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

// Initialize the quiz meta sync
new WPML_LLMS_Quiz_Meta_Sync();
