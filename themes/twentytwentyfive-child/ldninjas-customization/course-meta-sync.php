<?php
/**
 * WPML LifterLMS Course Meta Synchronization
 * 
 * Automatically synchronizes all LifterLMS course meta fields across WPML language versions
 * when the English (source) course is saved or updated.
 * 
 * @package WPML_LifterLMS
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPML LifterLMS Course Meta Sync Class
 */
class WPML_LLMS_Course_Meta_Sync {
    
    /**
     * Statistics tracking
     */
    private $stats = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_stats();
        
        // Hook into course save to automatically sync meta fields
        add_action('save_post_course', array($this, 'handle_course_meta_sync'), 20, 3);
    }
    
    /**
     * Initialize statistics
     */
    private function init_stats() {
        $this->stats = array(
            'course_meta_synced' => 0,
        );
    }
    
    /**
     * Handle course meta synchronization when a course is saved
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an existing post being updated
     */
    public function handle_course_meta_sync($post_id, $post, $update) {
        
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
        
        // Only sync if this is the English (source) course
        $default_language = apply_filters('wpml_default_language', null);
        if ($post_language_info['language_code'] !== $default_language) {
            return;
        }
        
        // Prevent infinite loops
        if (defined('WPML_LLMS_SYNCING_COURSE_META')) {
            return;
        }
        define('WPML_LLMS_SYNCING_COURSE_META', true);
        
        try {
            // Get translations and sync meta fields
            $translations = $this->get_course_translations($post_id);
            if (!empty($translations)) {
                $this->sync_course_metadata($post_id, $translations);
            }
        } catch (Exception $e) {
            // Handle errors silently to prevent breaking the save process
            error_log('WPML LLMS Course Meta Sync Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get course translations
     */
    private function get_course_translations($course_id) {
        if (!function_exists('wpml_get_language_information')) {
            return array();
        }
        
        $translations = array();
        $trid = apply_filters('wpml_element_trid', null, $course_id, 'post_course');
        
        if ($trid) {
            $translation_data = apply_filters('wpml_get_element_translations', null, $trid, 'post_course');
            
            foreach ($translation_data as $lang_code => $translation) {
                if ($translation->element_id != $course_id && $translation->element_id) {
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
     * Sync course metadata across all translations
     * 
     * @param int $course_id Source course ID
     * @param array $translations Array of translation data
     */
    private function sync_course_metadata($course_id, $translations) {

        $sync_fields = array(
            '_llms_length',
            '_llms_post_course_difficulty',
            '_llms_video_embed',
            '_llms_tile_featured_video',
            '_llms_audio_embed',
            '_llms_sales_page_content_type',
            '_llms_sales_page_content_page_id',
            '_llms_sales_page_content_url',
            '_llms_content_restricted_message',
            '_llms_enrollment_period',
            '_llms_enrollment_start_date',
            '_llms_enrollment_end_date',
            '_llms_enrollment_opens_message',
            '_llms_enrollment_closed_message',
            '_llms_time_period',
            '_llms_start_date',
            '_llms_end_date',
            '_llms_course_opens_message',
            '_llms_course_closed_message',
            '_llms_has_prerequisite',
            '_llms_prerequisite',
            '_llms_prerequisite_track',
            '_llms_drip_method',
            '_llms_days_before_available',
            '_llms_date_available',
            '_llms_catalog_visibility',
            '_llms_featured',
            '_llms_enable_capacity',
            '_llms_capacity',
            '_llms_capacity_message',
            '_llms_difficulty_id',
            '_llms_track_id',
            '_llms_course_image',
            '_llms_course_video_embed',
            '_llms_enable_completion_tracking',
            '_llms_completion_redirect',
            '_llms_completion_redirect_url',
            '_llms_certificate',
            '_llms_certificate_title',
            '_llms_achievement',
            '_llms_achievement_title',
            '_llms_enable_notifications',
            '_llms_custom_excerpt',
            '_llms_points',
            '_llms_access_plans',
            '_llms_enable_reviews',
            '_llms_reviews_enabled',
            '_llms_display_reviews',
            '_llms_num_reviews',
            '_llms_multiple_reviews_disabled',
            '_llms_enable_comments',
            '_llms_instructors',
            '_llms_course_tags',
            '_llms_course_categories',
            '_llms_course_tracks',
            '_llms_course_difficulty',
            '_llms_course_status',
            '_llms_course_privacy',
            '_llms_course_forum',
            '_llms_course_forum_enabled',
            '_llms_course_forum_id',
            '_llms_course_points',
            '_llms_course_badges',
            '_llms_course_leaderboard',
            '_llms_course_social_sharing',
            '_llms_course_retake_enabled',
            '_llms_course_retake_limit',
            '_llms_course_time_limit',
            '_llms_course_time_limit_enabled',
            '_llms_course_expiration',
            '_llms_course_expiration_enabled'
        );
        
        // Add stats tracking
        $settings_synced = 0;
        
        foreach ($translations as $lang_code => $translation) {
            foreach ($sync_fields as $field) {
                $main_value = get_post_meta($course_id, $field, true);
                $current_value = get_post_meta($translation['id'], $field, true);
                
                // Only update if values are different or if main value exists and current doesn't
                if ($main_value !== $current_value && ($main_value !== '' || $current_value !== '')) {
                    if ($main_value === '' || $main_value === false || $main_value === null) {
                        // Delete the meta if main course doesn't have it
                        delete_post_meta($translation['id'], $field);
                    } else {
                        // Update with main course value
                        update_post_meta($translation['id'], $field, $main_value);
                    }
                    $settings_synced++;
                }
            }
            
            // Also sync post excerpt (used for sales page content)
            $main_post = get_post($course_id);
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
        $this->stats['course_meta_synced'] = $settings_synced;
    }
    
    /**
     * Get processing statistics
     */
    public function get_stats() {
        return $this->stats;
    }
}

// Initialize the course meta sync
new WPML_LLMS_Course_Meta_Sync();
