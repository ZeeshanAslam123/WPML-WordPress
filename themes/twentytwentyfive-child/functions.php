<?php
/**
 * Twenty Twenty-Five Child Theme Functions
 * 
 * WPML LifterLMS Integration
 * 
 * @package TwentyTwentyFive_Child
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('WPML_LLMS_CHILD_THEME_VERSION', rand( 54545, 99797 ) );
define('WPML_LLMS_CHILD_THEME_PATH', get_stylesheet_directory());
define('WPML_LLMS_CHILD_THEME_URL', get_stylesheet_directory_uri());

/**
 * Enqueue parent theme styles
 */
function twentytwentyfive_child_enqueue_styles() {
    // Enqueue parent theme style
    wp_enqueue_style(
        'twentytwentyfive-parent-style',
        get_template_directory_uri() . '/style.css',
        array(),
        wp_get_theme()->parent()->get('Version')
    );
    
    // Enqueue child theme style
    wp_enqueue_style(
        'twentytwentyfive-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('twentytwentyfive-parent-style'),
        WPML_LLMS_CHILD_THEME_VERSION
    );
}
add_action('wp_enqueue_scripts', 'twentytwentyfive_child_enqueue_styles');

// Load WPML LifterLMS components
require_once WPML_LLMS_CHILD_THEME_PATH . '/ldninjas-customization/admin-menu.php';
require_once WPML_LLMS_CHILD_THEME_PATH . '/ldninjas-customization/ajax-handlers.php';
require_once WPML_LLMS_CHILD_THEME_PATH . '/ldninjas-customization/course-fixer.php';
require_once WPML_LLMS_CHILD_THEME_PATH . '/ldninjas-customization/auto-course-fixer.php';
require_once WPML_LLMS_CHILD_THEME_PATH . '/ldninjas-customization/enrollment-sync.php';
require_once WPML_LLMS_CHILD_THEME_PATH . '/ldninjas-customization/progress-sync.php';

// Initialize the auto course fixer
add_action('init', function() {
    if (class_exists('WPML_LLMS_Auto_Course_Fixer')) {
        WPML_LLMS_Auto_Course_Fixer::get_instance();
    }
});

/**
 * Enqueue admin assets
 */
function wpml_llms_enqueue_admin_assets($hook) {
    // Only load on our admin page
    if (strpos($hook, 'ldninjas-customization') === false) {
        return;
    }

    wp_enqueue_style(
        'wpml-llms-admin-css',
        WPML_LLMS_CHILD_THEME_URL . '/ldninjas-customization/assets/admin.css',
        array(),
        WPML_LLMS_CHILD_THEME_VERSION
    );
    
    wp_enqueue_script(
        'wpml-llms-admin-js',
        WPML_LLMS_CHILD_THEME_URL . '/ldninjas-customization/assets/admin.js',
        array('jquery'),
        WPML_LLMS_CHILD_THEME_VERSION,
        true
    );
    
    // Localize script for AJAX
    wp_localize_script('wpml-llms-admin-js', 'wpmlLlmsAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpml_llms_nonce'),
        'strings' => array(
            'fixing' => __('Fixing relationships...', 'twentytwentyfive-child'),
            'complete' => __('Fix complete!', 'twentytwentyfive-child'),
            'error' => __('An error occurred', 'twentytwentyfive-child'),
            'selectCourse' => __('Please select a course', 'twentytwentyfive-child')
        )
    ));
}
add_action('admin_enqueue_scripts', 'wpml_llms_enqueue_admin_assets');

/**
 * Utility function to log messages
 */
function wpml_llms_log($message, $type = 'info') {
    // Only log if WordPress debug logging is enabled
    if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        $log_message = '[WPML-LLMS] ' . strtoupper($type) . ': ' . $message;
        // Use WordPress native logging
        if (function_exists('error_log')) {
            error_log($log_message);
        }
    }
}
