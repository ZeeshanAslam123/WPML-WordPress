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
        
        add_action('save_post_lesson', [$this, 'handle_lesson_meta_sync'], 20, 3);
        add_filter('llms_builder_update_lesson', [ $this, 'hook_after_lesson_update' ], 999, 4);
    }

    /**
     * Trigger after lesson update through course builder
     */
    public function hook_after_lesson_update($result, $lesson_data, $lesson, $created) {
        // Now the lesson should have the updated data
        if ($lesson && is_a($lesson, 'LLMS_Lesson')) {
            // Trigger our lesson sync with fresh data
            $this->sync_lesson_on_save($lesson, $lesson_data);

            // Check for quiz updates
            if (!empty($lesson_data['quiz']) && is_array($lesson_data['quiz'])) {
                $quiz_id = $lesson->get('quiz');
                if ($quiz_id) {
                    $quiz = llms_get_post($quiz_id);
                    if ($quiz && is_a($quiz, 'LLMS_Quiz')) {
                        $this->sync_quiz_on_save($quiz, $lesson_data['quiz'], $lesson);
                    }
                }
            }
        }

        return $result;
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
     * Sync lesson meta on save through course builder
     */
    public function sync_lesson_on_save($lesson, $lesson_data) {

        $lesson_id = $lesson->get('id');

        $translations = $this->get_lesson_translations($lesson_id);
        if (!empty($translations)) {
            $this->sync_lesson_metadata($lesson_id, $translations);
        }
    }

    /**
     * Sync quiz meta on save through course builder
     */
    public function sync_quiz_on_save($quiz, $quiz_data, $lesson) {

        $quiz_id = $quiz->get('id');
        
        $quiz_obj = new WPML_LLMS_Quiz_Meta_Sync();
        $translations = $quiz_obj->get_quiz_translations($quiz_id);
        if (!empty($translations)) {
            $quiz_obj->sync_quiz_metadata($quiz_id, $translations);
        }
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

        // Get translations and sync meta fields
        $translations = $this->get_lesson_translations($post_id);
        if (!empty($translations)) {
            $this->sync_lesson_metadata($post_id, $translations);
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
    public function sync_lesson_metadata($lesson_id, $translations) {
        
        $sync_fields = array(
            '_llms_video_embed',
            '_llms_audio_embed',
            '_llms_free_lesson',
            '_llms_has_prerequisite',
            '_llms_prerequisite',
            '_llms_require_passing_grade',
            '_llms_drip_method',
            '_llms_days_before_available',
            '_llms_date_available',
            '_llms_time_available',
            '_llms_quiz',
            '_llms_quiz_enabled',
            '_llms_lesson_video_embed',
            '_llms_lesson_audio_embed',
            '_llms_lesson_excerpt',
            '_llms_completion_redirect',
            '_llms_completion_redirect_url',
            '_llms_points',
            '_llms_content_restricted_message',
            '_llms_lesson_restricted',
            '_llms_lesson_length',
            '_llms_lesson_difficulty',
            '_llms_lesson_tags',
            '_llms_lesson_categories',
            '_llms_enable_comments',
            '_llms_enable_reviews',
            '_llms_lesson_forum',
            '_llms_lesson_forum_enabled',
            '_llms_custom_fields',
            '_llms_lesson_custom_excerpt',
            '_video_embed',
            '_audio_embed',
            '_has_prerequisite',
            '_prerequisite',
            '_days_before_avalailable'
        );

        // Add stats tracking
        $settings_synced = 0;
        
        foreach ($translations as $lang_code => $translation) {
            foreach ($sync_fields as $field) {

                $main_value = get_post_meta($lesson_id, $field, true);
                $current_value = get_post_meta($translation['id'], $field, true);
                
                if ($main_value !== $current_value && ($main_value !== '' || $current_value !== '')) {
                    if ($main_value === '' || $main_value === false || $main_value === null) {
                        delete_post_meta($translation['id'], $field);
                    } else {
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
}

// Initialize the lesson meta sync
new WPML_LLMS_Lesson_Meta_Sync();
