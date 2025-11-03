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
        
        // Get translations and sync meta fields
        $translations = $this->get_quiz_translations($post_id);
        if (!empty($translations)) {
            $this->sync_quiz_metadata($post_id, $translations);
        }
    }
    
    /**
     * Get quiz translations
     */
    public function get_quiz_translations($quiz_id) {
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
    public function sync_quiz_metadata($quiz_id, $translations) {
        
        // Comprehensive list of LifterLMS quiz meta fields to sync
        $sync_fields = array(
            '_llms_quiz_enabled',
            '_llms_assigned_quiz',
            '_llms_question_order',
            '_llms_randomize_questions',
            '_llms_show_correct_answer',
            '_llms_passing_percent',
            '_llms_require_passing_grade',
            '_llms_quiz_attempts',
            '_llms_unlimited_attempts',
            '_llms_quiz_time_limit',
            '_llms_quiz_time_limit_enabled',
            '_llms_show_results',
            '_llms_quiz_description',
            '_llms_quiz_instructions',
            '_llms_quiz_intro',
            '_llms_quiz_grading',
            '_llms_quiz_grade_type',
            '_llms_quiz_points',
            '_llms_quiz_feedback',
            '_llms_quiz_success_message',
            '_llms_quiz_failure_message',
            '_llms_quiz_restricted',
            '_llms_quiz_restriction_message',
            '_llms_quiz_prerequisite',
            '_llms_quiz_has_prerequisite',
            '_llms_quiz_retake_enabled',
            '_llms_disable_retake',
            '_llms_quiz_retake_delay',
            '_llms_quiz_randomize_answers',
            '_llms_quiz_show_progress',
            '_llms_quiz_navigation',
            '_llms_quiz_certificate',
            '_llms_quiz_achievement',
            '_llms_quiz_completion_redirect',
            '_llms_quiz_completion_redirect_url',
            '_llms_quiz_track_attempts',
            '_llms_quiz_analytics_enabled',
            '_llms_quiz_detailed_results',
            '_llms_can_be_resumed',
            '_quiz_enabled',
            '_quiz_lesson_id',
            '_quiz_attempts',
            '_quiz_passing_percent',
            '_llms_quiz_custom_fields',
            '_llms_quiz_meta',
            '_llms_quiz_settings',
            '_llms_time_limit',
            '_llms_random_questions',
            '_llms_limit_time',
            '_llms_limit_attempts',
            '_llms_allowed_attempts',
            'referenced_media_ids',
            'copied_media_ids',
            '_wpml_word_count'
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
}

// Initialize the quiz meta sync
new WPML_LLMS_Quiz_Meta_Sync();
