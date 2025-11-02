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
        
        // Comprehensive list of LifterLMS course meta fields to sync
        $sync_fields = array(
            // General Settings
            '_llms_length',                          // Course Length
            '_llms_post_course_difficulty',          // Course Difficulty Category
            '_llms_video_embed',                     // Featured Video
            '_llms_tile_featured_video',             // Display Featured Video on Course Tile
            '_llms_audio_embed',                     // Featured Audio
            
            // Sales Page Settings
            '_llms_sales_page_content_type',         // Sales Page Content Type
            '_llms_sales_page_content_page_id',      // Sales Page Content Page ID
            '_llms_sales_page_content_url',          // Sales Page Redirect URL
            
            // Restrictions Settings
            '_llms_content_restricted_message',      // Content Restricted Message
            '_llms_enrollment_period',               // Enable Enrollment Period
            '_llms_enrollment_start_date',           // Enrollment Start Date
            '_llms_enrollment_end_date',             // Enrollment End Date
            '_llms_enrollment_opens_message',        // Enrollment Opens Message
            '_llms_enrollment_closed_message',       // Enrollment Closed Message
            '_llms_time_period',                     // Enable Course Time Period
            '_llms_start_date',                      // Course Start Date
            '_llms_end_date',                        // Course End Date
            '_llms_course_opens_message',            // Course Opens Message
            '_llms_course_closed_message',           // Course Closed Message
            
            // Prerequisites Settings
            '_llms_has_prerequisite',                // Has Prerequisites
            '_llms_prerequisite',                    // Prerequisite Course ID
            '_llms_prerequisite_track',              // Prerequisite Track ID
            
            // Drip Settings
            '_llms_drip_method',                     // Drip Method
            '_llms_days_before_available',           // Days Before Available
            '_llms_date_available',                  // Date Available
            
            // Catalog Settings
            '_llms_catalog_visibility',              // Catalog Visibility
            '_llms_featured',                        // Featured Course
            
            // Engagement Settings
            '_llms_enable_capacity',                 // Enable Capacity
            '_llms_capacity',                        // Course Capacity
            '_llms_capacity_message',                // Capacity Reached Message
            
            // Legacy/Additional Fields
            '_llms_difficulty_id',                   // Legacy Difficulty ID
            '_llms_track_id',                        // Track ID
            '_llms_course_image',                    // Course Image
            '_llms_course_video_embed',              // Legacy Video Embed
            
            // Completion Settings
            '_llms_enable_completion_tracking',      // Enable Completion Tracking
            '_llms_completion_redirect',             // Completion Redirect
            '_llms_completion_redirect_url',         // Completion Redirect URL
            
            // Certificate & Achievement Settings
            '_llms_certificate',                     // Certificate Template
            '_llms_certificate_title',               // Certificate Title
            '_llms_achievement',                     // Achievement Template
            '_llms_achievement_title',               // Achievement Title
            
            // Notification Settings
            '_llms_enable_notifications',            // Enable Notifications
            
            // Custom Fields (if any)
            '_llms_custom_excerpt',                  // Custom Excerpt
            '_llms_points',                          // Points Awarded
            
            // Access Plan Related (if stored as course meta)
            '_llms_access_plans',                    // Access Plans
            
            // Reviews & Comments Settings (Complete)
            '_llms_enable_reviews',                  // Enable Reviews
            '_llms_reviews_enabled',                 // Reviews Enabled (primary field)
            '_llms_display_reviews',                 // Display Reviews on Course Page
            '_llms_num_reviews',                     // Number of Reviews to Display
            '_llms_multiple_reviews_disabled',       // Disable Multiple Reviews per User
            '_llms_enable_comments',                 // Enable Comments
            
            // Additional Course Meta Fields
            '_llms_instructors',                     // Course Instructors
            '_llms_course_tags',                     // Course Tags
            '_llms_course_categories',               // Course Categories
            '_llms_course_tracks',                   // Course Tracks
            '_llms_course_difficulty',               // Course Difficulty (taxonomy)
            '_llms_course_status',                   // Course Status
            '_llms_course_privacy',                  // Course Privacy Settings
            '_llms_course_forum',                    // Course Forum Settings
            '_llms_course_forum_enabled',            // Enable Course Forum
            '_llms_course_forum_id',                 // Course Forum ID
            
            // Engagement & Gamification
            '_llms_course_points',                   // Course Points
            '_llms_course_badges',                   // Course Badges
            '_llms_course_leaderboard',              // Course Leaderboard
            '_llms_course_social_sharing',           // Social Sharing Settings
            
            // Advanced Course Settings
            '_llms_course_retake_enabled',           // Allow Course Retakes
            '_llms_course_retake_limit',             // Course Retake Limit
            '_llms_course_time_limit',               // Course Time Limit
            '_llms_course_time_limit_enabled',       // Enable Course Time Limit
            '_llms_course_expiration',               // Course Expiration
            '_llms_course_expiration_enabled',       // Enable Course Expiration
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
    
    /**
     * Clear stats
     */
    public function reset() {
        $this->init_stats();
    }
}

// Initialize the course meta sync
new WPML_LLMS_Course_Meta_Sync();
