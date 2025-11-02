<?php
/**
 * WPML LifterLMS Question Meta Synchronization
 * 
 * Automatically synchronizes all LifterLMS question meta fields across WPML language versions
 * when the English (source) question is saved or updated.
 * 
 * @package WPML_LifterLMS
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPML LifterLMS Question Meta Sync Class
 */
class WPML_LLMS_Question_Meta_Sync {
    
    /**
     * Statistics tracking
     */
    private $stats = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_stats();
        
        // Hook into question save to automatically sync meta fields
        add_action('save_post_llms_question', array($this, 'handle_question_meta_sync'), 20, 3);
    }
    
    /**
     * Initialize statistics
     */
    private function init_stats() {
        $this->stats = array(
            'question_meta_synced' => 0,
        );
    }
    
    /**
     * Handle question meta synchronization when a question is saved
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an existing post being updated
     */
    public function handle_question_meta_sync($post_id, $post, $update) {
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
        
        // Only sync if this is the English (source) question
        $default_language = apply_filters('wpml_default_language', null);
        if ($post_language_info['language_code'] !== $default_language) {
            return;
        }
        
        // Prevent infinite loops
        if (defined('WPML_LLMS_SYNCING_QUESTION_META')) {
            return;
        }
        define('WPML_LLMS_SYNCING_QUESTION_META', true);
        
        try {
            // Get translations and sync meta fields
            $translations = $this->get_question_translations($post_id);
            if (!empty($translations)) {
                $this->sync_question_metadata($post_id, $translations);
            }
        } catch (Exception $e) {
            // Handle errors silently to prevent breaking the save process
            error_log('WPML LLMS Question Meta Sync Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get question translations
     */
    private function get_question_translations($question_id) {
        if (!function_exists('wpml_get_language_information')) {
            return array();
        }
        
        $translations = array();
        $trid = apply_filters('wpml_element_trid', null, $question_id, 'post_llms_question');
        
        if ($trid) {
            $translation_data = apply_filters('wpml_get_element_translations', null, $trid, 'post_llms_question');
            
            foreach ($translation_data as $lang_code => $translation) {
                if ($translation->element_id != $question_id && $translation->element_id) {
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
     * Sync question metadata across all translations
     * 
     * @param int $question_id Source question ID
     * @param array $translations Array of translation data
     */
    private function sync_question_metadata($question_id, $translations) {
        
        // Comprehensive list of LifterLMS question meta fields to sync
        $sync_fields = array(
            // Question Structure & Relationships
            '_llms_parent_id',                       // Parent Quiz ID
            '_llms_question_order',                  // Question Order in Quiz
            
            // Question Core Settings
            '_llms_question_type',                   // Question Type (multiple_choice, true_false, open, etc.)
            '_llms_points',                          // Points for Question
            '_llms_question_description',            // Question Description
            '_llms_question_explanation',            // Question Explanation
            '_llms_question_clarifications',         // Question Clarifications
            
            // Multiple Choice & Answer Options
            '_llms_multi_choices',                   // Multiple Choice Options
            '_llms_question_options',                // Question Options Array
            '_llms_correct_option',                  // Correct Answer Option
            '_llms_question_choices',                // Question Choices
            
            // Question Behavior & Display
            '_llms_randomize_choices',               // Randomize Answer Choices
            '_llms_show_correct_answer',             // Show Correct Answer
            '_llms_question_required',               // Question Required
            '_llms_question_image',                  // Question Image
            '_llms_question_video',                  // Question Video
            '_llms_question_audio',                  // Question Audio
            
            // Grading & Feedback
            '_llms_question_feedback',               // Question Feedback
            '_llms_correct_answer_feedback',         // Correct Answer Feedback
            '_llms_incorrect_answer_feedback',       // Incorrect Answer Feedback
            '_llms_question_hint',                   // Question Hint
            '_llms_question_explanation_enabled',    // Enable Explanation
            
            // Advanced Question Settings
            '_llms_question_weight',                 // Question Weight
            '_llms_question_difficulty',             // Question Difficulty
            '_llms_question_category',               // Question Category
            '_llms_question_tags',                   // Question Tags
            
            // Question Validation & Rules
            '_llms_question_validation',             // Question Validation Rules
            '_llms_question_min_length',             // Minimum Answer Length
            '_llms_question_max_length',             // Maximum Answer Length
            '_llms_question_pattern',                // Answer Pattern/Regex
            
            // Legacy Fields (for backward compatibility)
            '_llms_legacy_question_title',           // Legacy Question Title
            '_llms_legacy_question_options',         // Legacy Question Options
            '_question_type',                        // Legacy Question Type
            '_question_points',                      // Legacy Question Points
            '_question_options',                     // Legacy Question Options
            
            // Custom Fields
            '_llms_question_custom_fields',          // Custom Fields Data
            '_llms_question_meta',                   // Additional Question Meta
            '_llms_question_settings',               // Question Settings Array
        );
        
        // Add stats tracking
        $settings_synced = 0;
        
        foreach ($translations as $lang_code => $translation) {
            // Sync standard meta fields
            foreach ($sync_fields as $field) {
                $main_value = get_post_meta($question_id, $field, true);
                $current_value = get_post_meta($translation['id'], $field, true);
                
                // Only update if values are different or if main value exists and current doesn't
                if ($main_value !== $current_value && ($main_value !== '' || $current_value !== '')) {
                    if ($main_value === '' || $main_value === false || $main_value === null) {
                        // Delete the meta if main question doesn't have it
                        delete_post_meta($translation['id'], $field);
                    } else {
                        // Update with main question value
                        update_post_meta($translation['id'], $field, $main_value);
                    }
                    $settings_synced++;
                }
            }
            
            // Sync all choice-related meta fields (dynamic fields with _llms_choice_ prefix)
            $this->sync_question_choices($question_id, $translation['id']);
            
            // Also sync post excerpt (question description)
            $main_post = get_post($question_id);
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
        $this->stats['question_meta_synced'] = $settings_synced;
    }
    
    /**
     * Sync question choice meta fields (dynamic fields with _llms_choice_ prefix)
     * 
     * @param int $source_question_id Source question ID
     * @param int $target_question_id Target question ID
     */
    private function sync_question_choices($source_question_id, $target_question_id) {
        global $wpdb;
        
        // Get all choice-related meta fields from the source question
        $choice_meta = $wpdb->get_results($wpdb->prepare("
            SELECT meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id = %d 
            AND (meta_key LIKE '_llms_choice_%' 
                OR meta_key IN ('_llms_question_type', '_llms_points', '_llms_multi_choices', 
                               '_llms_correct_option', '_llms_question_choices'))
        ", $source_question_id));
        
        if (!empty($choice_meta)) {
            // First, remove existing choice meta from target question
            $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->postmeta} 
                WHERE post_id = %d 
                AND meta_key LIKE '_llms_choice_%'
            ", $target_question_id));
            
            // Then add all choice meta from source question
            foreach ($choice_meta as $meta) {
                if (strpos($meta->meta_key, '_llms_choice_') === 0) {
                    update_post_meta($target_question_id, $meta->meta_key, $meta->meta_value);
                }
            }
        }
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

// Initialize the question meta sync
new WPML_LLMS_Question_Meta_Sync();
