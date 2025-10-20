<?php
/**
 * WPML LifterLMS Course Relationship Fixer
 * 
 * Provides a dedicated admin interface for fixing WPML-LifterLMS course relationships
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPML_LifterLMS_Course_Fixer {
    
    /**
     * Singleton instance
     * @var WPML_LifterLMS_Course_Fixer
     */
    private static $instance = null;
    
    /**
     * Relationships handler
     * @var WPML_LifterLMS_Relationships
     */
    private $relationships;
    
    /**
     * Get singleton instance
     * 
     * @return WPML_LifterLMS_Course_Fixer
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Debug: Show that the course fixer is being constructed
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<pre>WPML LifterLMS Course Fixer: Constructor called</pre>';
            var_dump('Course Fixer Constructor - Starting initialization');
        }
        
        $this->relationships = WPML_LifterLMS_Relationships::get_instance();
        $this->init_hooks();
        
        // Debug: Show that initialization is complete
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<pre>WPML LifterLMS Course Fixer: Initialization complete</pre>';
            var_dump('Course Fixer Constructor - Initialization complete');
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_wpml_llms_get_english_courses', array($this, 'handle_get_english_courses'));
        add_action('wp_ajax_wpml_llms_fix_course_relationships', array($this, 'handle_fix_course_relationships'));
        
        // Debug: Add admin notice to verify plugin is loading
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('admin_notices', array($this, 'debug_admin_notice'));
        }
    }
    
    /**
     * Debug admin notice
     */
    public function debug_admin_notice() {
        echo '<div class="notice notice-info"><p>WPML LifterLMS Course Fixer is loaded and active!</p></div>';
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        // Debug: Show that this method is being called
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<pre>WPML LifterLMS Course Fixer: Adding admin menu</pre>';
            var_dump('add_admin_menu() method called');
        }
        
        $hook = add_menu_page(
            __('WPML LifterLMS Fix', 'wpml-lifterlms-compatibility'),
            __('WPML LifterLMS Fix', 'wpml-lifterlms-compatibility'),
            'manage_options',
            'wpml-lifterlms-course-fixer',
            array($this, 'render_admin_page'),
            'dashicons-admin-tools',
            30
        );
        
        // Debug: Show the hook result
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<pre>WPML LifterLMS Course Fixer: Menu hook created</pre>';
            var_dump('Menu hook result', $hook);
        }
    }
    
    /**
     * Enqueue admin assets
     * 
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_wpml-lifterlms-course-fixer') {
            return;
        }
        
        wp_enqueue_script(
            'wpml-lifterlms-course-fixer',
            WPML_LLMS_PLUGIN_URL . 'assets/js/course-fixer.js',
            array('jquery'),
            WPML_LLMS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wpml-lifterlms-course-fixer',
            WPML_LLMS_PLUGIN_URL . 'assets/css/course-fixer.css',
            array(),
            WPML_LLMS_VERSION
        );
        
        wp_localize_script('wpml-lifterlms-course-fixer', 'wpmlLlmsCourseFixer', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpml_llms_course_fixer'),
            'strings' => array(
                'selectCourse' => __('Please select a course', 'wpml-lifterlms-compatibility'),
                'fixing' => __('Fixing Relationships...', 'wpml-lifterlms-compatibility'),
                'fixButton' => __('Fix Relationships', 'wpml-lifterlms-compatibility'),
                'loadingCourses' => __('Loading courses...', 'wpml-lifterlms-compatibility'),
                'noCoursesFound' => __('No English courses found', 'wpml-lifterlms-compatibility'),
                'fixComplete' => __('Relationship fixing completed!', 'wpml-lifterlms-compatibility'),
                'fixError' => __('Error occurred while fixing relationships', 'wpml-lifterlms-compatibility')
            )
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap wpml-llms-course-fixer">
            <h1><?php _e('WPML LifterLMS Course Relationship Fixer', 'wpml-lifterlms-compatibility'); ?></h1>
            
            <div class="wpml-llms-fixer-description">
                <p><?php _e('This tool helps you fix WPML-LifterLMS course relationships after translating content. Select an English course and click "Fix Relationships" to automatically sync all related content (sections, lessons, quizzes, etc.) with their translations.', 'wpml-lifterlms-compatibility'); ?></p>
            </div>
            
            <div class="wpml-llms-fixer-controls">
                <div class="control-group">
                    <label for="course-selector"><?php _e('Select English Course:', 'wpml-lifterlms-compatibility'); ?></label>
                    <select id="course-selector" class="course-selector">
                        <option value=""><?php _e('Loading courses...', 'wpml-lifterlms-compatibility'); ?></option>
                    </select>
                </div>
                
                <div class="control-group">
                    <button type="button" id="fix-relationships-btn" class="button button-primary button-large" disabled>
                        <?php _e('Fix Relationships', 'wpml-lifterlms-compatibility'); ?>
                    </button>
                </div>
            </div>
            
            <div class="wpml-llms-fixer-progress" id="fixer-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div class="progress-text" id="progress-text"></div>
            </div>
            
            <div class="wpml-llms-fixer-logs">
                <h3><?php _e('Process Logs', 'wpml-lifterlms-compatibility'); ?></h3>
                <div class="log-controls">
                    <button type="button" id="clear-logs-btn" class="button"><?php _e('Clear Logs', 'wpml-lifterlms-compatibility'); ?></button>
                    <button type="button" id="copy-logs-btn" class="button"><?php _e('Copy Logs', 'wpml-lifterlms-compatibility'); ?></button>
                </div>
                <div class="log-container" id="log-container">
                    <div class="log-placeholder"><?php _e('Logs will appear here when you start fixing relationships...', 'wpml-lifterlms-compatibility'); ?></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle get English courses AJAX request
     */
    public function handle_get_english_courses() {
        check_ajax_referer('wpml_llms_course_fixer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wpml-lifterlms-compatibility'));
        }
        
        $courses = $this->get_english_courses();
        wp_send_json_success($courses);
    }
    
    /**
     * Handle fix course relationships AJAX request
     */
    public function handle_fix_course_relationships() {
        check_ajax_referer('wpml_llms_course_fixer', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'wpml-lifterlms-compatibility'));
        }
        
        $course_id = intval($_POST['course_id']);
        if (!$course_id) {
            wp_send_json_error(__('Invalid course ID', 'wpml-lifterlms-compatibility'));
        }
        
        $result = $this->fix_course_relationships($course_id);
        wp_send_json_success($result);
    }
    
    /**
     * Get all English courses
     * 
     * @return array
     */
    private function get_english_courses() {
        $courses = array();
        
        // Get default language (should be English)
        $default_language = apply_filters('wpml_default_language', null);
        
        // Query courses in default language
        $course_posts = get_posts(array(
            'post_type' => 'course',
            'post_status' => array('publish', 'draft'),
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        foreach ($course_posts as $course_post) {
            // Check if this course is in the default language
            $course_language = apply_filters('wpml_element_language_code', null, array(
                'element_id' => $course_post->ID,
                'element_type' => 'post_course'
            ));
            
            if ($course_language === $default_language) {
                $courses[] = array(
                    'id' => $course_post->ID,
                    'title' => $course_post->post_title,
                    'status' => $course_post->post_status,
                    'display_title' => sprintf('%s (ID: %d) - %s', 
                        $course_post->post_title, 
                        $course_post->ID, 
                        ucfirst($course_post->post_status)
                    )
                );
            }
        }
        
        return $courses;
    }
    
    /**
     * Fix course relationships
     * 
     * @param int $course_id
     * @return array
     */
    private function fix_course_relationships($course_id) {
        $logs = array();
        $success = true;
        
        try {
            $logs[] = array(
                'type' => 'info',
                'message' => sprintf(__('Starting relationship fix for course ID: %d', 'wpml-lifterlms-compatibility'), $course_id),
                'timestamp' => current_time('mysql')
            );
            
            // Verify course exists and is in default language
            $course_post = get_post($course_id);
            if (!$course_post || $course_post->post_type !== 'course') {
                throw new Exception(__('Invalid course ID or course not found', 'wpml-lifterlms-compatibility'));
            }
            
            $course_language = apply_filters('wpml_element_language_code', null, array(
                'element_id' => $course_id,
                'element_type' => 'post_course'
            ));
            
            $default_language = apply_filters('wpml_default_language', null);
            
            if ($course_language !== $default_language) {
                throw new Exception(__('Selected course is not in the default language', 'wpml-lifterlms-compatibility'));
            }
            
            $logs[] = array(
                'type' => 'success',
                'message' => sprintf(__('Course verified: "%s" (Language: %s)', 'wpml-lifterlms-compatibility'), $course_post->post_title, $course_language),
                'timestamp' => current_time('mysql')
            );
            
            // Get all available languages
            $languages = apply_filters('wpml_active_languages', null);
            $logs[] = array(
                'type' => 'info',
                'message' => sprintf(__('Found %d active languages: %s', 'wpml-lifterlms-compatibility'), count($languages), implode(', ', array_keys($languages))),
                'timestamp' => current_time('mysql')
            );
            
            // Fix relationships for each language
            foreach ($languages as $lang_code => $language) {
                if ($lang_code === $default_language) {
                    continue; // Skip default language
                }
                
                $logs[] = array(
                    'type' => 'info',
                    'message' => sprintf(__('Processing language: %s (%s)', 'wpml-lifterlms-compatibility'), $language['native_name'], $lang_code),
                    'timestamp' => current_time('mysql')
                );
                
                $result = $this->fix_course_relationships_for_language($course_id, $lang_code);
                $logs = array_merge($logs, $result['logs']);
                
                if (!$result['success']) {
                    $success = false;
                }
            }
            
            $logs[] = array(
                'type' => $success ? 'success' : 'warning',
                'message' => $success ? __('All relationships fixed successfully!', 'wpml-lifterlms-compatibility') : __('Relationship fixing completed with some issues. Check logs above.', 'wpml-lifterlms-compatibility'),
                'timestamp' => current_time('mysql')
            );
            
        } catch (Exception $e) {
            $success = false;
            $logs[] = array(
                'type' => 'error',
                'message' => sprintf(__('Error: %s', 'wpml-lifterlms-compatibility'), $e->getMessage()),
                'timestamp' => current_time('mysql')
            );
        }
        
        return array(
            'success' => $success,
            'logs' => $logs
        );
    }
    
    /**
     * Fix course relationships for a specific language
     * 
     * @param int $course_id
     * @param string $language_code
     * @return array
     */
    private function fix_course_relationships_for_language($course_id, $language_code) {
        $logs = array();
        $success = true;
        
        try {
            // Get translated course
            $translated_course_id = apply_filters('wpml_object_id', $course_id, 'course', false, $language_code);
            
            if (!$translated_course_id) {
                $logs[] = array(
                    'type' => 'warning',
                    'message' => sprintf(__('No translation found for course in %s language', 'wpml-lifterlms-compatibility'), $language_code),
                    'timestamp' => current_time('mysql')
                );
                return array('success' => true, 'logs' => $logs);
            }
            
            $translated_course = get_post($translated_course_id);
            $logs[] = array(
                'type' => 'success',
                'message' => sprintf(__('Found translated course: "%s" (ID: %d)', 'wpml-lifterlms-compatibility'), $translated_course->post_title, $translated_course_id),
                'timestamp' => current_time('mysql')
            );
            
            // Fix sections
            $section_result = $this->fix_sections_for_course($course_id, $translated_course_id, $language_code);
            $logs = array_merge($logs, $section_result['logs']);
            if (!$section_result['success']) $success = false;
            
            // Fix lessons
            $lesson_result = $this->fix_lessons_for_course($course_id, $translated_course_id, $language_code);
            $logs = array_merge($logs, $lesson_result['logs']);
            if (!$lesson_result['success']) $success = false;
            
            // Fix quizzes
            $quiz_result = $this->fix_quizzes_for_course($course_id, $translated_course_id, $language_code);
            $logs = array_merge($logs, $quiz_result['logs']);
            if (!$quiz_result['success']) $success = false;
            
            // Sync custom fields
            $this->relationships->sync_course_custom_fields($course_id, $translated_course_id);
            $logs[] = array(
                'type' => 'success',
                'message' => __('Custom fields synchronized', 'wpml-lifterlms-compatibility'),
                'timestamp' => current_time('mysql')
            );
            
        } catch (Exception $e) {
            $success = false;
            $logs[] = array(
                'type' => 'error',
                'message' => sprintf(__('Error fixing relationships for %s: %s', 'wpml-lifterlms-compatibility'), $language_code, $e->getMessage()),
                'timestamp' => current_time('mysql')
            );
        }
        
        return array(
            'success' => $success,
            'logs' => $logs
        );
    }
    
    /**
     * Fix sections for course
     * 
     * @param int $original_course_id
     * @param int $translated_course_id
     * @param string $language_code
     * @return array
     */
    private function fix_sections_for_course($original_course_id, $translated_course_id, $language_code) {
        $logs = array();
        $success = true;
        
        // Get original sections
        $original_sections = get_posts(array(
            'post_type' => 'section',
            'meta_key' => '_llms_parent_course',
            'meta_value' => $original_course_id,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $logs[] = array(
            'type' => 'info',
            'message' => sprintf(__('Found %d sections in original course', 'wpml-lifterlms-compatibility'), count($original_sections)),
            'timestamp' => current_time('mysql')
        );
        
        foreach ($original_sections as $original_section) {
            try {
                // Get translated section
                $translated_section_id = apply_filters('wpml_object_id', $original_section->ID, 'section', false, $language_code);
                
                if ($translated_section_id) {
                    // Update parent course relationship
                    update_post_meta($translated_section_id, '_llms_parent_course', $translated_course_id);
                    
                    $translated_section = get_post($translated_section_id);
                    $logs[] = array(
                        'type' => 'success',
                        'message' => sprintf(__('Fixed section: "%s" → "%s"', 'wpml-lifterlms-compatibility'), $original_section->post_title, $translated_section->post_title),
                        'timestamp' => current_time('mysql')
                    );
                } else {
                    $logs[] = array(
                        'type' => 'warning',
                        'message' => sprintf(__('No translation found for section: "%s"', 'wpml-lifterlms-compatibility'), $original_section->post_title),
                        'timestamp' => current_time('mysql')
                    );
                }
            } catch (Exception $e) {
                $success = false;
                $logs[] = array(
                    'type' => 'error',
                    'message' => sprintf(__('Error fixing section "%s": %s', 'wpml-lifterlms-compatibility'), $original_section->post_title, $e->getMessage()),
                    'timestamp' => current_time('mysql')
                );
            }
        }
        
        return array('success' => $success, 'logs' => $logs);
    }
    
    /**
     * Fix lessons for course
     * 
     * @param int $original_course_id
     * @param int $translated_course_id
     * @param string $language_code
     * @return array
     */
    private function fix_lessons_for_course($original_course_id, $translated_course_id, $language_code) {
        $logs = array();
        $success = true;
        
        // Get original lessons
        $original_lessons = get_posts(array(
            'post_type' => 'lesson',
            'meta_key' => '_llms_parent_course',
            'meta_value' => $original_course_id,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $logs[] = array(
            'type' => 'info',
            'message' => sprintf(__('Found %d lessons in original course', 'wpml-lifterlms-compatibility'), count($original_lessons)),
            'timestamp' => current_time('mysql')
        );
        
        foreach ($original_lessons as $original_lesson) {
            try {
                // Get translated lesson
                $translated_lesson_id = apply_filters('wpml_object_id', $original_lesson->ID, 'lesson', false, $language_code);
                
                if ($translated_lesson_id) {
                    // Update parent course relationship
                    update_post_meta($translated_lesson_id, '_llms_parent_course', $translated_course_id);
                    
                    // Fix parent section relationship
                    $original_section_id = get_post_meta($original_lesson->ID, '_llms_parent_section', true);
                    if ($original_section_id) {
                        $translated_section_id = apply_filters('wpml_object_id', $original_section_id, 'section', false, $language_code);
                        if ($translated_section_id) {
                            update_post_meta($translated_lesson_id, '_llms_parent_section', $translated_section_id);
                        }
                    }
                    
                    $translated_lesson = get_post($translated_lesson_id);
                    $logs[] = array(
                        'type' => 'success',
                        'message' => sprintf(__('Fixed lesson: "%s" → "%s"', 'wpml-lifterlms-compatibility'), $original_lesson->post_title, $translated_lesson->post_title),
                        'timestamp' => current_time('mysql')
                    );
                } else {
                    $logs[] = array(
                        'type' => 'warning',
                        'message' => sprintf(__('No translation found for lesson: "%s"', 'wpml-lifterlms-compatibility'), $original_lesson->post_title),
                        'timestamp' => current_time('mysql')
                    );
                }
            } catch (Exception $e) {
                $success = false;
                $logs[] = array(
                    'type' => 'error',
                    'message' => sprintf(__('Error fixing lesson "%s": %s', 'wpml-lifterlms-compatibility'), $original_lesson->post_title, $e->getMessage()),
                    'timestamp' => current_time('mysql')
                );
            }
        }
        
        return array('success' => $success, 'logs' => $logs);
    }
    
    /**
     * Fix quizzes for course
     * 
     * @param int $original_course_id
     * @param int $translated_course_id
     * @param string $language_code
     * @return array
     */
    private function fix_quizzes_for_course($original_course_id, $translated_course_id, $language_code) {
        $logs = array();
        $success = true;
        
        // Get original quizzes associated with course lessons
        $original_lessons = get_posts(array(
            'post_type' => 'lesson',
            'meta_key' => '_llms_parent_course',
            'meta_value' => $original_course_id,
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $quiz_count = 0;
        
        foreach ($original_lessons as $original_lesson) {
            $quiz_id = get_post_meta($original_lesson->ID, '_llms_assigned_quiz', true);
            if ($quiz_id) {
                $quiz_count++;
                
                try {
                    // Get translated lesson and quiz
                    $translated_lesson_id = apply_filters('wpml_object_id', $original_lesson->ID, 'lesson', false, $language_code);
                    $translated_quiz_id = apply_filters('wpml_object_id', $quiz_id, 'llms_quiz', false, $language_code);
                    
                    if ($translated_lesson_id && $translated_quiz_id) {
                        // Update quiz assignment
                        update_post_meta($translated_lesson_id, '_llms_assigned_quiz', $translated_quiz_id);
                        
                        $original_quiz = get_post($quiz_id);
                        $translated_quiz = get_post($translated_quiz_id);
                        
                        $logs[] = array(
                            'type' => 'success',
                            'message' => sprintf(__('Fixed quiz assignment: "%s" → "%s"', 'wpml-lifterlms-compatibility'), $original_quiz->post_title, $translated_quiz->post_title),
                            'timestamp' => current_time('mysql')
                        );
                    } else {
                        $logs[] = array(
                            'type' => 'warning',
                            'message' => sprintf(__('Missing translation for quiz or lesson (Original Quiz ID: %d)', 'wpml-lifterlms-compatibility'), $quiz_id),
                            'timestamp' => current_time('mysql')
                        );
                    }
                } catch (Exception $e) {
                    $success = false;
                    $logs[] = array(
                        'type' => 'error',
                        'message' => sprintf(__('Error fixing quiz (ID: %d): %s', 'wpml-lifterlms-compatibility'), $quiz_id, $e->getMessage()),
                        'timestamp' => current_time('mysql')
                    );
                }
            }
        }
        
        $logs[] = array(
            'type' => 'info',
            'message' => sprintf(__('Processed %d quizzes for course', 'wpml-lifterlms-compatibility'), $quiz_count),
            'timestamp' => current_time('mysql')
        );
        
        return array('success' => $success, 'logs' => $logs);
    }
}
