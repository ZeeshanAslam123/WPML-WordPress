<?php
/**
 * WPML LifterLMS Admin Menu
 * 
 * Creates the admin interface for WPML LifterLMS course relationship fixing
 * 
 * @package TwentyTwentyFive_Child
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPML_LLMS_Admin_Menu {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Add admin menu under WPML
     */
    public function add_admin_menu() {
        
        add_submenu_page(
            'edit.php?post_type=course', // Parent slug (WPML main menu)
            __('LifterLMS', 'twentytwentyfive-child'),        // Page title
            __('LifterLMS', 'twentytwentyfive-child'),        // Menu title
            'manage_options',                                  // Capability
            'wpml-lifterlms',                                 // Menu slug
            array($this, 'render_admin_page')                 // Callback
        );
    }
    
    /**
     * Render the admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap wpml-llms-course-fixer">
            <div class="wpml-llms-admin-header">
                <h2><?php _e('WPML LifterLMS Course Relationship Fixer', 'twentytwentyfive-child'); ?></h2>
                <p><?php _e('Fix relationships between WPML translations and LifterLMS courses to ensure proper multilingual functionality.', 'twentytwentyfive-child'); ?></p>
            </div>
            
            <div class="wpml-llms-fixer-description">
                <p><strong><?php _e('What this tool does:', 'twentytwentyfive-child'); ?></strong></p>
                <p><?php _e('This tool identifies and repairs broken relationships between course translations in WPML and LifterLMS. It ensures that course progress, enrollments, and other data are properly synchronized across all language versions.', 'twentytwentyfive-child'); ?></p>
            </div>
            
            <div class="wpml-llms-fixer-controls">
                <div class="control-group">
                    <label for="course-selector"><?php _e('Select English Course:', 'twentytwentyfive-child'); ?></label>
                    <select id="course-selector" class="course-selector">
                        <option value=""><?php _e('-- Select a course --', 'twentytwentyfive-child'); ?></option>
                        <?php $this->render_course_options(); ?>
                    </select>
                </div>
                
                <div class="control-group">
                    <button id="fix-relationships-btn" class="fix-button" disabled>
                        <?php _e('Fix Relationships', 'twentytwentyfive-child'); ?>
                    </button>
                </div>
                
                <div class="progress-container" id="progress-container">
                    <div class="progress-text" id="progress-text"><?php _e('Initializing...', 'twentytwentyfive-child'); ?></div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                </div>
            </div>
            
            <div class="logs-container" id="logs-container">
                <div class="logs-header">
                    <?php _e('Operation Logs', 'twentytwentyfive-child'); ?>
                </div>
                <div class="logs-content" id="logs-content">
                    <!-- Logs will be populated here -->
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render course options for the selector
     */
    private function render_course_options() {
        $courses = $this->get_english_courses();
        
        if (empty($courses)) {
            echo '<option value="" disabled>' . __('No English courses found', 'twentytwentyfive-child') . '</option>';
            return;
        }
        
        foreach ($courses as $course) {
            printf(
                '<option value="%d">%s (ID: %d)</option>',
                esc_attr($course['id']),
                esc_html($course['title']),
                esc_html($course['id'])
            );
        }
    }
    
    /**
     * Get English courses
     */
    private function get_english_courses() {
        if (!class_exists('LLMS_Course')) {
            return array();
        }
        
        $courses = array();
        
        // Get all published courses
        $args = array(
            'post_type' => 'course',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $course_posts = get_posts($args);
        
        foreach ($course_posts as $course_post) {
            // Check if this is an English course (default language or explicitly English)
            $language = $this->get_post_language($course_post->ID);
            
            if ($language === 'en' || $language === null) { // null means default language
                $courses[] = array(
                    'id' => $course_post->ID,
                    'title' => $course_post->post_title
                );
            }
        }
        
        return $courses;
    }
    
    /**
     * Get post language using WPML
     */
    private function get_post_language($post_id) {
        if (function_exists('wpml_get_language_information')) {
            $lang_info = wpml_get_language_information(null, $post_id);
            return isset($lang_info['language_code']) ? $lang_info['language_code'] : null;
        }
        
        // Fallback method
        if (function_exists('icl_get_languages')) {
            global $sitepress;
            if ($sitepress) {
                return $sitepress->get_language_for_element($post_id, 'post_course');
            }
        }
        
        return null;
    }
    
    /**
     * Get system status for debugging
     */
    public function get_system_status() {
        return array(
            'wpml_active' => class_exists('SitePress'),
            'lifterlms_active' => class_exists('LifterLMS'),
            'wpml_version' => defined('ICL_SITEPRESS_VERSION') ? ICL_SITEPRESS_VERSION : 'Unknown',
            'lifterlms_version' => defined('LLMS_VERSION') ? LLMS_VERSION : 'Unknown',
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'theme_version' => WPML_LLMS_CHILD_THEME_VERSION
        );
    }
}

new WPML_LLMS_Admin_Menu();