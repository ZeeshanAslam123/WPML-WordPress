<?php
/**
 * WPML LifterLMS Course Fixer
 * 
 * Core logic for fixing relationships between WPML and LifterLMS
 * 
 * @package TwentyTwentyFive_Child
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPML_LLMS_Course_Fixer {
    
    private $logs = array();
    private $stats = array(
        'courses_processed' => 0,
        'relationships_fixed' => 0,
        'sections_synced' => 0,
        'lessons_synced' => 0,
        'quizzes_synced' => 0,
        'questions_synced' => 0,
        'errors' => 0
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_stats();
    }
    
    /**
     * Initialize statistics
     */
    private function init_stats() {
        $this->stats = array(
            'courses_processed' => 0,
            'relationships_fixed' => 0,
            'sections_synced' => 0,
            'lessons_synced' => 0,
            'quizzes_synced' => 0,
            'questions_synced' => 0,
            'enrollments_synced' => 0,
            'errors' => 0,
            'start_time' => current_time('mysql'),
            'end_time' => null
        );
    }
    
    /**
     * Fix course relationships
     */
    public function fix_course_relationships($course_id) {
        $this->log('Starting relationship fix for course ID: ' . $course_id, 'info');
        
        try {
            // Validate course exists
            $course = get_post($course_id);
            if (!$course || $course->post_type !== 'course') {
                throw new Exception('Invalid course ID: ' . $course_id);
            }
            
            $this->log('Course found: ' . $course->post_title, 'success');
            
            // Get course translations
            $translations = $this->get_course_translations($course_id);
            $this->log('Found ' . count($translations) . ' translations', 'info');
            
            // Fix WPML relationships
            $this->fix_wpml_relationships($course_id, $translations);
            
            // Sync course sections
            $this->sync_course_sections($course_id, $translations);
            
            // Sync course lessons
            $this->sync_course_lessons($course_id, $translations);
            
            // Sync course quizzes
            $this->sync_course_quizzes($course_id, $translations);
            
            // Sync quiz questions
            $this->sync_quiz_questions($course_id, $translations);
            
            // Sync enrollments
            $this->sync_course_enrollments($course_id, $translations);
            
            // Update course metadata
            $this->sync_course_metadata($course_id, $translations);
            
            $this->stats['courses_processed']++;
            $this->stats['end_time'] = current_time('mysql');
            
            $this->log('Course relationship fix completed successfully', 'success');
            
            return array(
                'success' => true,
                'logs' => $this->logs,
                'stats' => $this->stats
            );
            
        } catch (Exception $e) {
            $this->stats['errors']++;
            $this->log('Error: ' . $e->getMessage(), 'error');
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'logs' => $this->logs,
                'stats' => $this->stats
            );
        }
    }
    
    /**
     * Get course translations
     */
    private function get_course_translations($course_id) {
        $translations = array();
        
        if (!function_exists('wpml_get_language_information')) {
            $this->log('WPML functions not available', 'warning');
            return $translations;
        }
        
        // Get all active languages
        $languages = apply_filters('wpml_active_languages', null);
        
        foreach ($languages as $lang_code => $language) {
            $translated_id = apply_filters('wpml_object_id', $course_id, 'course', false, $lang_code);
            
            if ($translated_id && $translated_id !== $course_id) {
                $translated_post = get_post($translated_id);
                if ($translated_post) {
                    $translations[$lang_code] = array(
                        'id' => $translated_id,
                        'title' => $translated_post->post_title,
                        'language' => $lang_code,
                        'language_name' => $language['native_name']
                    );
                    
                    $this->log('Found translation: ' . $translated_post->post_title . ' (' . $lang_code . ')', 'info');
                }
            }
        }
        
        return $translations;
    }
    
    /**
     * Fix WPML relationships
     */
    private function fix_wpml_relationships($course_id, $translations) {
        $this->log('Fixing WPML relationships...', 'info');
        
        global $wpdb;
        
        foreach ($translations as $lang_code => $translation) {
            // Check if relationship exists in icl_translations table
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}icl_translations 
                WHERE element_id = %d AND element_type = 'post_course'",
                $translation['id']
            ));
            
            if (!$existing) {
                // Create missing translation relationship
                $trid = $wpdb->get_var($wpdb->prepare(
                    "SELECT trid FROM {$wpdb->prefix}icl_translations 
                    WHERE element_id = %d AND element_type = 'post_course'",
                    $course_id
                ));
                
                if ($trid) {
                    $wpdb->insert(
                        $wpdb->prefix . 'icl_translations',
                        array(
                            'element_type' => 'post_course',
                            'element_id' => $translation['id'],
                            'trid' => $trid,
                            'language_code' => $lang_code,
                            'source_language_code' => 'en'
                        ),
                        array('%s', '%d', '%d', '%s', '%s')
                    );
                    
                    $this->stats['relationships_fixed']++;
                    $this->log('Fixed WPML relationship for ' . $translation['title'], 'success');
                }
            } else {
                $this->log('WPML relationship already exists for ' . $translation['title'], 'info');
            }
        }
    }
    
    /**
     * Sync course sections
     */
    private function sync_course_sections($course_id, $translations) {
        $this->log('Syncing course sections...', 'info');
        
        if (!class_exists('LLMS_Course')) {
            $this->log('LifterLMS not available', 'warning');
            return;
        }
        
        $main_course = new LLMS_Course($course_id);
        $main_sections = $main_course->get_sections('ids');
        
        if (empty($main_sections)) {
            $this->log('No sections found in main course', 'info');
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            $this->log('Processing sections for ' . $translation['title'] . ' (' . $lang_code . ')', 'info');
            
            // Sync section relationships
            foreach ($main_sections as $section_id) {
                $translated_section_id = apply_filters('wpml_object_id', $section_id, 'section', false, $lang_code);
                
                if ($translated_section_id && $translated_section_id !== $section_id) {
                    // Fix section → course relationship
                    $current_parent_course = get_post_meta($translated_section_id, '_llms_parent_course', true);
                    if ($current_parent_course != $translation['id']) {
                        update_post_meta($translated_section_id, '_llms_parent_course', $translation['id']);
                        $this->log('Fixed section ' . $translated_section_id . ' parent course to ' . $translation['id'], 'success');
                        $this->stats['sections_synced']++;
                    } else {
                        $this->log('Section ' . $translated_section_id . ' already has correct parent course', 'info');
                    }
                    
                    // Fix section order meta key (critical for LifterLMS to find sections)
                    $main_section_order = get_post_meta($section_id, '_llms_order', true);
                    if ($main_section_order) {
                        $current_section_order = get_post_meta($translated_section_id, '_llms_order', true);
                        if ($current_section_order != $main_section_order) {
                            update_post_meta($translated_section_id, '_llms_order', $main_section_order);
                            $this->log('Fixed section ' . $translated_section_id . ' order meta to ' . $main_section_order, 'success');
                        } else {
                            $this->log('Section ' . $translated_section_id . ' already has correct order meta', 'info');
                        }
                    } else {
                        $this->log('Main section ' . $section_id . ' has no order meta', 'warning');
                    }
                } else {
                    $this->log('No translation found for section ' . $section_id . ' in ' . $lang_code, 'warning');
                }
            }
            
            $this->log('Completed section sync for ' . $translation['title'], 'success');
        }
    }
    
    /**
     * Sync course lessons
     */
    private function sync_course_lessons($course_id, $translations) {
        $this->log('Syncing course lessons...', 'info');
        
        if (!class_exists('LLMS_Course')) {
            $this->log('LifterLMS not available', 'warning');
            return;
        }
        
        $main_course = new LLMS_Course($course_id);
        $main_lessons = $main_course->get_lessons('ids');
        
        if (empty($main_lessons)) {
            $this->log('No lessons found in main course', 'info');
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            $this->log('Processing lessons for ' . $translation['title'] . ' (' . $lang_code . ')', 'info');
            
            // Sync lesson relationships
            foreach ($main_lessons as $lesson_id) {
                $translated_lesson_id = apply_filters('wpml_object_id', $lesson_id, 'lesson', false, $lang_code);
                
                if ($translated_lesson_id && $translated_lesson_id !== $lesson_id) {
                    // Fix lesson → course relationship
                    $current_parent_course = get_post_meta($translated_lesson_id, '_llms_parent_course', true);
                    if ($current_parent_course != $translation['id']) {
                        update_post_meta($translated_lesson_id, '_llms_parent_course', $translation['id']);
                        $this->log('Fixed lesson ' . $translated_lesson_id . ' parent course to ' . $translation['id'], 'success');
                    }
                    
                    // Fix lesson → section relationship
                    $main_lesson_section = get_post_meta($lesson_id, '_llms_parent_section', true);
                    if ($main_lesson_section) {
                        // Find the translated section
                        $translated_section_id = apply_filters('wpml_object_id', $main_lesson_section, 'section', false, $lang_code);
                        
                        if ($translated_section_id && $translated_section_id !== $main_lesson_section) {
                            $current_parent_section = get_post_meta($translated_lesson_id, '_llms_parent_section', true);
                            if ($current_parent_section != $translated_section_id) {
                                update_post_meta($translated_lesson_id, '_llms_parent_section', $translated_section_id);
                                $this->log('Fixed lesson ' . $translated_lesson_id . ' parent section to ' . $translated_section_id, 'success');
                            } else {
                                $this->log('Lesson ' . $translated_lesson_id . ' already has correct parent section', 'info');
                            }
                        } else {
                            $this->log('No translated section found for lesson ' . $translated_lesson_id, 'warning');
                        }
                    } else {
                        $this->log('Main lesson ' . $lesson_id . ' has no parent section', 'info');
                    }
                    
                    // Fix lesson order meta key (critical for LifterLMS ordering)
                    $main_lesson_order = get_post_meta($lesson_id, '_llms_order', true);
                    if ($main_lesson_order) {
                        $current_lesson_order = get_post_meta($translated_lesson_id, '_llms_order', true);
                        if ($current_lesson_order != $main_lesson_order) {
                            update_post_meta($translated_lesson_id, '_llms_order', $main_lesson_order);
                            $this->log('Fixed lesson ' . $translated_lesson_id . ' order meta to ' . $main_lesson_order, 'success');
                        } else {
                            $this->log('Lesson ' . $translated_lesson_id . ' already has correct order meta', 'info');
                        }
                    } else {
                        $this->log('Main lesson ' . $lesson_id . ' has no order meta', 'warning');
                    }
                    
                    $this->stats['lessons_synced']++;
                } else {
                    $this->log('No translation found for lesson ' . $lesson_id . ' in ' . $lang_code, 'warning');
                }
            }
            
            $this->log('Completed lesson sync for ' . $translation['title'], 'success');
        }
    }
    
    /**
     * Sync course quizzes
     */
    private function sync_course_quizzes($course_id, $translations) {
        $this->log('Syncing course quizzes...', 'info');
        
        if (!class_exists('LLMS_Course')) {
            $this->log('LifterLMS not available', 'warning');
            return;
        }
        
        $main_course = new LLMS_Course($course_id);
        $main_lessons = $main_course->get_lessons('lessons');
        
        if (empty($main_lessons)) {
            $this->log('No lessons found in main course', 'info');
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            $this->log('Processing quizzes for ' . $translation['title'] . ' (' . $lang_code . ')', 'info');
            
            // Process each lesson to find and sync quizzes
            foreach ($main_lessons as $main_lesson) {
                if (!$main_lesson->has_quiz()) {
                    continue; // Skip lessons without quizzes
                }
                
                $main_quiz = $main_lesson->get_quiz();
                if (!$main_quiz) {
                    continue; // Skip if quiz object not found
                }
                
                $main_lesson_id = $main_lesson->get('id');
                $main_quiz_id = $main_quiz->get('id');
                
                // Find translated lesson and quiz
                $translated_lesson_id = apply_filters('wpml_object_id', $main_lesson_id, 'lesson', false, $lang_code);
                $translated_quiz_id = apply_filters('wpml_object_id', $main_quiz_id, 'llms_quiz', false, $lang_code);
                
                if ($translated_lesson_id && $translated_quiz_id && 
                    $translated_lesson_id !== $main_lesson_id && 
                    $translated_quiz_id !== $main_quiz_id) {
                    
                    // Fix lesson → quiz relationship
                    $translated_lesson = llms_get_post($translated_lesson_id);
                    if ($translated_lesson) {
                        $current_lesson_quiz = $translated_lesson->get('quiz');
                        if ($current_lesson_quiz != $translated_quiz_id) {
                            $translated_lesson->set('quiz', $translated_quiz_id);
                            $this->log('Fixed lesson ' . $translated_lesson_id . ' quiz reference to ' . $translated_quiz_id, 'success');
                        } else {
                            $this->log('Lesson ' . $translated_lesson_id . ' already has correct quiz reference', 'info');
                        }
                    }
                    
                    // Fix quiz → lesson relationship
                    $translated_quiz = llms_get_post($translated_quiz_id);
                    if ($translated_quiz) {
                        $current_quiz_lesson = $translated_quiz->get('lesson_id');
                        if ($current_quiz_lesson != $translated_lesson_id) {
                            $translated_quiz->set('lesson_id', $translated_lesson_id);
                            $this->log('Fixed quiz ' . $translated_quiz_id . ' lesson reference to ' . $translated_lesson_id, 'success');
                        } else {
                            $this->log('Quiz ' . $translated_quiz_id . ' already has correct lesson reference', 'info');
                        }
                    }
                    
                    $this->stats['quizzes_synced']++;
                    
                } else {
                    if (!$translated_lesson_id) {
                        $this->log('No translated lesson found for lesson ' . $main_lesson_id . ' in ' . $lang_code, 'warning');
                    }
                    if (!$translated_quiz_id) {
                        $this->log('No translated quiz found for quiz ' . $main_quiz_id . ' in ' . $lang_code, 'warning');
                    }
                }
            }
            
            $this->log('Completed quiz sync for ' . $translation['title'], 'success');
        }
    }
    
    /**
     * Sync quiz questions
     */
    private function sync_quiz_questions($course_id, $translations) {
        $this->log('Syncing quiz questions...', 'info');
        
        if (!class_exists('LLMS_Course')) {
            $this->log('LifterLMS not available', 'warning');
            return;
        }
        
        $main_course = new LLMS_Course($course_id);
        $main_lessons = $main_course->get_lessons('lessons');
        
        if (empty($main_lessons)) {
            $this->log('No lessons found in main course', 'info');
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            $this->log('Processing quiz questions for ' . $translation['title'] . ' (' . $lang_code . ')', 'info');
            
            // Process each lesson to find quizzes and their questions
            foreach ($main_lessons as $main_lesson) {
                if (!$main_lesson->has_quiz()) {
                    continue; // Skip lessons without quizzes
                }
                
                $main_quiz = $main_lesson->get_quiz();
                if (!$main_quiz) {
                    continue; // Skip if quiz object not found
                }
                
                $main_quiz_id = $main_quiz->get('id');
                $translated_quiz_id = apply_filters('wpml_object_id', $main_quiz_id, 'llms_quiz', false, $lang_code);
                
                if (!$translated_quiz_id || $translated_quiz_id === $main_quiz_id) {
                    continue; // Skip if no translation or same quiz
                }
                
                // Get questions from main quiz
                $main_questions = $main_quiz->get_questions('ids');
                
                if (empty($main_questions)) {
                    $this->log('No questions found in main quiz ' . $main_quiz_id, 'info');
                    continue;
                }
                
                $this->log('Found ' . count($main_questions) . ' questions in main quiz ' . $main_quiz_id, 'info');
                
                // Process each question
                foreach ($main_questions as $main_question_id) {
                    $translated_question_id = apply_filters('wpml_object_id', $main_question_id, 'llms_question', false, $lang_code);
                    
                    if ($translated_question_id && $translated_question_id !== $main_question_id) {
                        $question_updated = false;
                        
                        // Fix question → quiz relationship using post_parent
                        $current_parent = wp_get_post_parent_id($translated_question_id);
                        if ($current_parent != $translated_quiz_id) {
                            wp_update_post(array(
                                'ID' => $translated_question_id,
                                'post_parent' => $translated_quiz_id
                            ));
                            $this->log('Fixed question ' . $translated_question_id . ' parent to quiz ' . $translated_quiz_id, 'success');
                            $question_updated = true;
                        }
                        
                        // Sync question meta data
                        if ($this->sync_question_meta($main_question_id, $translated_question_id)) {
                            $question_updated = true;
                        }
                        
                        // Ensure question has correct menu_order (LifterLMS orders by this)
                        if ($this->sync_question_menu_order($main_question_id, $translated_question_id)) {
                            $question_updated = true;
                        }
                        
                        // Ensure question has correct post_status (LifterLMS requires 'publish')
                        if ($this->sync_question_post_status($main_question_id, $translated_question_id)) {
                            $question_updated = true;
                        }
                        
                        if ($question_updated) {
                            $this->stats['questions_synced']++;
                        } else {
                            $this->log('Question ' . $translated_question_id . ' already properly configured', 'info');
                        }
                    } else {
                        $this->log('No translated question found for question ' . $main_question_id . ' in ' . $lang_code, 'warning');
                    }
                }
                
                // Sync quiz questions using your proven working pattern
                if ($translated_quiz_id) {
                    $this->sync_quiz_questions_simple($main_quiz_id, $translated_quiz_id, $lang_code);
                    $this->verify_quiz_questions($translated_quiz_id, $lang_code);
                }
            }
            
            $this->log('Completed question sync for ' . $translation['title'], 'success');
        }
    }
    
    /**
     * Sync question meta data from source to translated question
     */
    private function sync_question_meta($source_question_id, $translated_question_id) {
        $meta_synced = false;
        
        // Get all meta for source question
        $source_meta = get_post_meta($source_question_id);
        
        if (empty($source_meta)) {
            $this->log('No meta found for source question ' . $source_question_id, 'warning');
            return false;
        }
        
        // Define LifterLMS question meta keys that should be synced
        $llms_meta_keys = array(
            '_llms_question_type',
            '_llms_points',
            '_llms_parent_id',
            '_llms_multi_choices',
            '_llms_description_enabled',
            '_llms_video_enabled',
            '_llms_video_src',
            '_llms_clarifications_enabled',
            '_llms_clarifications',
            '_llms_question_order',
            '_llms_question_bank_id',
            '_llms_question_bank_order'
        );
        
        // Get existing translated meta to compare
        $translated_meta = get_post_meta($translated_question_id);
        
        // Sync basic LifterLMS meta keys
        foreach ($llms_meta_keys as $meta_key) {
            if (isset($source_meta[$meta_key])) {
                $source_value = $source_meta[$meta_key][0];
                $current_value = isset($translated_meta[$meta_key]) ? $translated_meta[$meta_key][0] : '';
                
                if ($current_value !== $source_value) {
                    update_post_meta($translated_question_id, $meta_key, $source_value);
                    $this->log('Synced meta ' . $meta_key . ' for question ' . $translated_question_id, 'success');
                    $meta_synced = true;
                }
            }
        }
        
        // Sync choice meta keys using your working pattern (preserve translated choice text)
        if ($this->sync_llms_question_choices($source_question_id, $translated_question_id)) {
            $meta_synced = true;
        }
        
        // Update parent ID to point to translated quiz (not source quiz)
        $translated_quiz_id = wp_get_post_parent_id($translated_question_id);
        if ($translated_quiz_id) {
            $current_parent_meta = get_post_meta($translated_question_id, '_llms_parent_id', true);
            if ($current_parent_meta != $translated_quiz_id) {
                update_post_meta($translated_question_id, '_llms_parent_id', $translated_quiz_id);
                $this->log('Updated _llms_parent_id to ' . $translated_quiz_id . ' for question ' . $translated_question_id, 'success');
                $meta_synced = true;
            }
        }
        
        return $meta_synced;
    }
    
    /**
     * Sync LifterLMS question choices (based on your working function)
     * Preserves translated choice text while copying other metadata
     */
    private function sync_llms_question_choices($english_question_id, $target_question_id) {
        global $wpdb;

        $english_meta = $wpdb->get_results( $wpdb->prepare( "
            SELECT meta_key, meta_value
            FROM {$wpdb->postmeta}
            WHERE post_id = %d AND meta_key LIKE '_llms_choice_%%'
        ", $english_question_id ) );

        if ( empty( $english_meta ) ) {
            return false;
        }

        $choices_synced = false;

        foreach ( $english_meta as $row ) {
            $choice_key = $row->meta_key;
            $english_choice_data = maybe_unserialize( $row->meta_value );

            // Get translated version of same choice
            $target_meta = $wpdb->get_row( $wpdb->prepare( "
                SELECT meta_value
                FROM {$wpdb->postmeta}
                WHERE post_id = %d AND meta_key = %s
            ", $target_question_id, $choice_key ) );

            if ( $target_meta ) {
                $target_choice_data = maybe_unserialize( $target_meta->meta_value );

                // Preserve translated 'choice' text only, copy other meta from source
                if ( is_array( $english_choice_data ) ) {
                    $updated = false;
                    foreach ( $english_choice_data as $key => $value ) {
                        if ( $key === 'choice' ) {
                            continue; // Preserve translated choice text
                        }
                        if ( !isset($target_choice_data[$key]) || $target_choice_data[$key] !== $value ) {
                            $target_choice_data[ $key ] = $value;
                            $updated = true;
                        }
                    }
                    if ( $updated ) {
                        update_post_meta( $target_question_id, $choice_key, $target_choice_data );
                        $this->log('Synced choice meta ' . $choice_key . ' for question ' . $target_question_id . ' (preserved translated text)', 'success');
                        $choices_synced = true;
                    }
                }
            } else {
                // No translated choice exists, copy entire choice data
                update_post_meta( $target_question_id, $choice_key, maybe_unserialize( $row->meta_value ) );
                $this->log('Created choice meta ' . $choice_key . ' for question ' . $target_question_id, 'success');
                $choices_synced = true;
            }
        }
        
        return $choices_synced;
    }
    
    /**
     * Sync question menu_order from source to translated question
     */
    private function sync_question_menu_order($source_question_id, $translated_question_id) {
        $source_question = get_post($source_question_id);
        $translated_question = get_post($translated_question_id);
        
        if (!$source_question || !$translated_question) {
            return false;
        }
        
        if ($source_question->menu_order != $translated_question->menu_order) {
            wp_update_post(array(
                'ID' => $translated_question_id,
                'menu_order' => $source_question->menu_order
            ));
            $this->log('Updated menu_order to ' . $source_question->menu_order . ' for question ' . $translated_question_id, 'success');
            return true;
        }
        
        return false;
    }
    
    /**
     * Sync question post_status from source to translated question
     */
    private function sync_question_post_status($source_question_id, $translated_question_id) {
        $source_question = get_post($source_question_id);
        $translated_question = get_post($translated_question_id);
        
        if (!$source_question || !$translated_question) {
            return false;
        }
        
        if ($source_question->post_status != $translated_question->post_status) {
            wp_update_post(array(
                'ID' => $translated_question_id,
                'post_status' => $source_question->post_status
            ));
            $this->log('Updated post_status to ' . $source_question->post_status . ' for question ' . $translated_question_id, 'success');
            return true;
        }
        
        return false;
    }
    
    /**
     * Simple quiz-question relationship sync based on your working code
     */
    private function sync_quiz_questions_simple($main_quiz_id, $translated_quiz_id, $target_lang) {
        $default_lang = apply_filters('wpml_default_language', null) ?: 'en';
        $questions_fixed = 0;
        
        // Get questions for this specific quiz using your pattern
        $questions = get_posts([
            'post_type' => 'llms_question',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'suppress_filters' => false,
            'lang' => $target_lang,
            'fields' => 'ids',
        ]);
        
        foreach ($questions as $post_id) {
            // Get original question ID (your exact pattern)
            $orig_id = apply_filters('wpml_object_id', $post_id, 'llms_question', true, $default_lang);
            if (!$orig_id || $orig_id == $post_id) {
                continue;
            }
            
            // Check if this question belongs to our main quiz
            $orig_quiz = get_post_meta($orig_id, '_llms_parent_id', true);
            if ($orig_quiz == $main_quiz_id) {
                // Fix the relationship (your exact logic)
                update_post_meta($post_id, '_llms_parent_id', $translated_quiz_id);
                $this->log('Question ' . $post_id . ' fixed: _llms_parent_id => ' . $translated_quiz_id, 'success');
                $questions_fixed++;
                
                // Sync choices using your working function
                $this->sync_llms_question_choices($orig_id, $post_id);
            }
        }
        
        if ($questions_fixed > 0) {
            $this->log('Fixed ' . $questions_fixed . ' questions for quiz ' . $translated_quiz_id, 'success');
        }
        
        return $questions_fixed;
    }
    
    /**
     * Sync quiz questions meta data - the key that LifterLMS uses to store question lists
     */
    private function sync_quiz_questions_meta($main_quiz_id, $translated_quiz_id, $main_questions) {
        // Get the main quiz's question data
        $main_quiz_questions_meta = get_post_meta($main_quiz_id, '_llms_questions', true);
        
        if (empty($main_quiz_questions_meta)) {
            $this->log('No _llms_questions meta found for main quiz ' . $main_quiz_id, 'warning');
            return false;
        }
        
        // Create translated question list by mapping question IDs
        $translated_questions_meta = array();
        
        if (is_array($main_quiz_questions_meta)) {
            foreach ($main_quiz_questions_meta as $question_data) {
                if (isset($question_data['id'])) {
                    $main_question_id = $question_data['id'];
                    $translated_question_id = apply_filters('wpml_object_id', $main_question_id, 'llms_question', false, 
                        get_post_meta($translated_quiz_id, '_wpml_language_code', true));
                    
                    if ($translated_question_id && $translated_question_id !== $main_question_id) {
                        // Copy question data but update the ID to translated version
                        $translated_question_data = $question_data;
                        $translated_question_data['id'] = $translated_question_id;
                        $translated_questions_meta[] = $translated_question_data;
                        
                        $this->log('Mapped question ' . $main_question_id . ' to ' . $translated_question_id . ' in quiz meta', 'success');
                    } else {
                        $this->log('No translated question found for ' . $main_question_id . ' in quiz meta mapping', 'warning');
                    }
                }
            }
        }
        
        // Update the translated quiz's question meta
        if (!empty($translated_questions_meta)) {
            update_post_meta($translated_quiz_id, '_llms_questions', $translated_questions_meta);
            $this->log('Updated _llms_questions meta for translated quiz ' . $translated_quiz_id . ' with ' . count($translated_questions_meta) . ' questions', 'success');
            return true;
        } else {
            $this->log('No translated questions to add to quiz meta for quiz ' . $translated_quiz_id, 'error');
            return false;
        }
    }
    
    /**
     * Simple verification that quiz has questions
     */
    private function verify_quiz_questions($quiz_id, $lang_code) {
        if (!class_exists('LLMS_Quiz')) {
            return;
        }
        
        $quiz = new LLMS_Quiz($quiz_id);
        $questions = $quiz->get_questions('ids');
        
        if (empty($questions)) {
            $this->log('Quiz ' . $quiz_id . ' (' . $lang_code . ') still has no questions after sync', 'warning');
        } else {
            $this->log('Quiz ' . $quiz_id . ' (' . $lang_code . ') has ' . count($questions) . ' questions', 'success');
        }
    }
    
    /**
     * Sync course enrollments
     */
    private function sync_course_enrollments($course_id, $translations) {
        $this->log('Syncing course enrollments...', 'info');
        
        if (!class_exists('LLMS_Student')) {
            $this->log('LifterLMS Student class not available', 'warning');
            return;
        }
        
        // Get students enrolled in main course
        $main_course = new LLMS_Course($course_id);
        $enrolled_students = $main_course->get_students();
        
        if (empty($enrolled_students)) {
            $this->log('No enrolled students found', 'info');
            return;
        }
        
        foreach ($translations as $lang_code => $translation) {
            foreach ($enrolled_students as $student_id) {
                $student = new LLMS_Student($student_id);
                
                // Check if student is already enrolled in translated course
                if (!$student->is_enrolled($translation['id'])) {
                    // Enroll student in translated course
                    $student->enroll($translation['id']);
                    $this->stats['enrollments_synced']++;
                    
                    $this->log('Enrolled student ' . $student_id . ' in ' . $translation['title'], 'success');
                }
            }
        }
    }
    
    /**
     * Sync course metadata
     */
    private function sync_course_metadata($course_id, $translations) {
        $this->log('Syncing course metadata...', 'info');
        
        // Define metadata fields that should be synced
        $sync_fields = array(
            '_llms_length',
            '_llms_difficulty_id',
            '_llms_track_id',
            '_llms_course_image',
            '_llms_course_video_embed',
            '_llms_audio_embed',
            '_llms_prerequisite',
            '_llms_has_prerequisite'
        );
        
        foreach ($translations as $lang_code => $translation) {
            foreach ($sync_fields as $field) {
                $main_value = get_post_meta($course_id, $field, true);
                
                if (!empty($main_value)) {
                    update_post_meta($translation['id'], $field, $main_value);
                }
            }
            
            $this->log('Synced metadata for ' . $translation['title'], 'success');
        }
    }
    
    /**
     * Log a message
     */
    private function log($message, $type = 'info') {
        $timestamp = current_time('H:i:s');
        
        $this->logs[] = array(
            'timestamp' => $timestamp,
            'message' => $message,
            'type' => $type
        );
        
        // Also log to WordPress debug log if enabled
        wpml_llms_log($message, $type);
    }
    
    /**
     * Get processing statistics
     */
    public function get_stats() {
        return $this->stats;
    }
    
    /**
     * Get processing logs
     */
    public function get_logs() {
        return $this->logs;
    }
    
    /**
     * Clear logs and stats
     */
    public function reset() {
        $this->logs = array();
        $this->init_stats();
    }
}

new WPML_LLMS_Course_Fixer();
