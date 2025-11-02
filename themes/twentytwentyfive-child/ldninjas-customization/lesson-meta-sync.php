<?php
/**
 * WPML LifterLMS Lesson Meta Synchronization
 * 
 * Automatically synchronizes all LifterLMS lesson meta fields across WPML language versions
 * when the English (source) lesson is saved or updated.
 * 
 * @package WPML_LifterLMS
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPML LifterLMS Lesson Meta Sync Class
 */
class WPML_LLMS_Lesson_Meta_Sync {
    
    /**
     * Statistics tracking
     */
    private $stats = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_stats();
        
        // Hook into lesson save to automatically sync meta fields
        add_action('save_post_lesson', array($this, 'handle_lesson_meta_sync'), 20, 3);
    }
    
    /**
     * Initialize statistics
     */
    private function init_stats() {
        $this->stats = array(
            'lesson_meta_synced' => 0,
        );
    }
    
    /**
     * Handle lesson meta synchronization when a lesson is saved
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an existing post being updated
     */
    public function handle_lesson_meta_sync($post_id, $post, $update) {
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
        
        // Only sync if this is the English (source) lesson
        $default_language = apply_filters('wpml_default_language', null);
        if ($post_language_info['language_code'] !== $default_language) {
            return;
        }
        
        // Prevent infinite loops
        if (defined('WPML_LLMS_SYNCING_LESSON_META')) {
            return;
        }
        define('WPML_LLMS_SYNCING_LESSON_META', true);
        
        try {
            // Get translations and sync meta fields
            $translations = $this->get_lesson_translations($post_id);
            if (!empty($translations)) {
                $this->sync_lesson_metadata($post_id, $translations);
            }
        } catch (Exception $e) {
            // Handle errors silently to prevent breaking the save process
            error_log('WPML LLMS Lesson Meta Sync Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get lesson translations
     */
    private function get_lesson_translations($lesson_id) {
        if (!function_exists('wpml_get_language_information')) {
            return array();
        }
        
        $translations = array();
        $trid = apply_filters('wpml_element_trid', null, $lesson_id, 'post_lesson');
        
        if ($trid) {
            $translation_data = apply_filters('wpml_get_element_translations', null, $trid, 'post_lesson');
            
            foreach ($translation_data as $lang_code => $translation) {
                if ($translation->element_id != $lesson_id && $translation->element_id) {
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
     * Sync lesson metadata across all translations
     * 
     * @param int $lesson_id Source lesson ID
     * @param array $translations Array of translation data
     */
    private function sync_lesson_metadata($lesson_id, $translations) {
        
        // Comprehensive list of LifterLMS lesson meta fields to sync
        $sync_fields = array(
            // General Settings
            '_llms_video_embed',                     // Video Embed URL
            '_llms_audio_embed',                     // Audio Embed URL
            '_llms_free_lesson',                     // Free Lesson (checkbox)
            
            // Prerequisites Settings
            '_llms_has_prerequisite',                // Enable Prerequisite
            '_llms_prerequisite',                    // Prerequisite Lesson ID
            '_llms_require_passing_grade',           // Require Passing Grade on Quiz
            
            // Drip Settings
            '_llms_drip_method',                     // Drip Method (date, enrollment, start, prerequisite)
            '_llms_days_before_available',           // Days Before Available (delay)
            '_llms_date_available',                  // Date Available
            '_llms_time_available',                  // Time Available
            
            // Lesson Structure & Relationships
            '_llms_order',                           // Lesson Order/Sequence
            '_llms_parent_course',                   // Parent Course ID
            '_llms_parent_section',                  // Parent Section ID
            
            // Quiz Integration
            '_llms_quiz',                            // Associated Quiz ID
            '_llms_quiz_enabled',                    // Quiz Enabled for Lesson
            
            // Content & Media
            '_llms_lesson_video_embed',              // Legacy Video Embed
            '_llms_lesson_audio_embed',              // Legacy Audio Embed
            '_llms_lesson_excerpt',                  // Lesson Excerpt
            
            // Completion & Progress
            '_llms_completion_redirect',             // Completion Redirect
            '_llms_completion_redirect_url',         // Completion Redirect URL
            '_llms_points',                          // Points Awarded for Completion
            
            // Access & Restrictions
            '_llms_content_restricted_message',      // Content Restricted Message
            '_llms_lesson_restricted',               // Lesson Restricted
            
            // Advanced Settings
            '_llms_lesson_length',                   // Lesson Length/Duration
            '_llms_lesson_difficulty',               // Lesson Difficulty
            '_llms_lesson_tags',                     // Lesson Tags
            '_llms_lesson_categories',               // Lesson Categories
            
            // Engagement Features
            '_llms_enable_comments',                 // Enable Comments on Lesson
            '_llms_enable_reviews',                  // Enable Reviews on Lesson
            '_llms_lesson_forum',                    // Lesson Forum Settings
            '_llms_lesson_forum_enabled',            // Enable Lesson Forum
            
            // Custom Fields
            '_llms_custom_fields',                   // Custom Fields Data
            '_llms_lesson_custom_excerpt',           // Custom Excerpt
            
            // Legacy Fields (for backward compatibility)
            '_video_embed',                          // Legacy Video Embed
            '_audio_embed',                          // Legacy Audio Embed
            '_has_prerequisite',                     // Legacy Has Prerequisite
            '_prerequisite',                         // Legacy Prerequisite
            '_days_before_avalailable',              // Legacy Days Before Available (typo in original)
        );
        
        // Add stats tracking
        $settings_synced = 0;
        
        foreach ($translations as $lang_code => $translation) {
            foreach ($sync_fields as $field) {
                $main_value = get_post_meta($lesson_id, $field, true);
                $current_value = get_post_meta($translation['id'], $field, true);
                
                // Only update if values are different or if main value exists and current doesn't
                if ($main_value !== $current_value && ($main_value !== '' || $current_value !== '')) {
                    if ($main_value === '' || $main_value === false || $main_value === null) {
                        // Delete the meta if main lesson doesn't have it
                        delete_post_meta($translation['id'], $field);
                    } else {
                        // Update with main lesson value
                        update_post_meta($translation['id'], $field, $main_value);
                    }
                    $settings_synced++;
                }
            }
            
            // Also sync post excerpt (lesson description)
            $main_post = get_post($lesson_id);
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
        $this->stats['lesson_meta_synced'] = $settings_synced;
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

// Initialize the lesson meta sync
new WPML_LLMS_Lesson_Meta_Sync();
