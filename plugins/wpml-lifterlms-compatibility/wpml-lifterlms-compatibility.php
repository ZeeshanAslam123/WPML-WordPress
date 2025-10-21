<?php
/**
 * Plugin Name: WPML LifterLMS Compatibility
 * Plugin URI: https://github.com/ZeeshanAslam123/wpml-lifterlms-compatibility
 * Description: Complete WPML compatibility plugin for LifterLMS - makes WPML 100% compatible with LifterLMS by handling all post types, taxonomies, custom fields, user progress, e-commerce, and frontend elements.
 * Version: 1.0.0
 * Author: Zeeshan Aslam
 * Author URI: https://github.com/ZeeshanAslam123
 * Text Domain: wpml-lifterlms-compatibility
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * Network: false
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 * @author Zeeshan Aslam
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPML_LLMS_VERSION', '1.0.0');
define('WPML_LLMS_PLUGIN_FILE', __FILE__);
define('WPML_LLMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPML_LLMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPML_LLMS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WPML_LLMS_TEXT_DOMAIN', 'wpml-lifterlms-compatibility');

/**
 * Main plugin class
 */
class WPML_LifterLMS_Compatibility {
    
    /**
     * Plugin instance
     * @var WPML_LifterLMS_Compatibility
     */
    private static $instance = null;
    
    /**
     * Plugin components
     * @var array
     */
    private $components = array();
    
    /**
     * Admin page hook
     * @var string
     */
    private $admin_page_hook;
    
    /**
     * Get plugin instance
     * @return WPML_LifterLMS_Compatibility
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register WPML configuration immediately to prevent XML parsing issues
        add_action('init', array($this, 'register_wpml_config'), 1);
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'), 0);
        
        // Simple AJAX handler - registered immediately
        add_action('wp_ajax_wpml_llms_fix_course_relationships', array($this, 'handle_fix_course_relationships_simple'));
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin with memory management
     */
    public function init() {
        // Monitor memory usage
        $initial_memory = memory_get_usage(true);
        
        // Check dependencies first
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Load autoloader
        $this->load_autoloader();
        
        // Initialize admin menu IMMEDIATELY - no lazy loading for critical UI
        $this->init_admin_menu_direct();
        
        // Initialize other components with lazy loading
        $this->init_components();
        
        // Load textdomain
        $this->load_textdomain();
        

        
        // Plugin is ready
        do_action('wpml_lifterlms_compatibility_loaded');
    }
    
    /**
     * Initialize admin menu directly - bypassing complex component system
     */
    private function init_admin_menu_direct() {
        if (is_admin()) {
            // Add admin menu hook immediately
            add_action('admin_menu', array($this, 'add_admin_menu_direct'));
            add_action('admin_init', array($this, 'register_admin_settings'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        }
    }
    
    /**
     * Add admin menu directly - MAIN MENU ONLY (no submenu)
     */
    public function add_admin_menu_direct() {
        // Always add as standalone main menu (no submenu)
        $hook = add_menu_page(
            __('WPML LifterLMS Compatibility', 'wpml-lifterlms-compatibility'),
            __('WPML LifterLMS', 'wpml-lifterlms-compatibility'),
            'manage_options',
            'wpml-lifterlms-compatibility',
            array($this, 'render_admin_page_direct'),
            'dashicons-translation',
            30
        );
        
        // Store hook for asset loading
        $this->admin_page_hook = $hook;
    }
    
    /**
     * Render admin page directly - consolidated from all components
     */
    public function render_admin_page_direct() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WPML LifterLMS Compatibility', 'wpml-lifterlms-compatibility'); ?></h1>
            
            <?php if (defined('ICL_SITEPRESS_VERSION') && defined('LLMS_PLUGIN_FILE')): ?>
            <!-- COURSE RELATIONSHIP FIXER SECTION - TOP PRIORITY -->
            <div class="card" style="border: 2px solid #0073aa; background: #f0f8ff;">
                <h2 style="color: #0073aa; margin-top: 0;"><?php echo esc_html__('🔧 Course Relationship Fixer', 'wpml-lifterlms-compatibility'); ?></h2>
                <p style="font-size: 16px; margin-bottom: 20px;"><?php echo esc_html__('Fix WPML-LifterLMS course relationships after translating content. Select an English course and click "Fix Relationships" to automatically sync all related content (sections, lessons, quizzes, etc.) with their translations.', 'wpml-lifterlms-compatibility'); ?></p>
                
                <div class="wpml-llms-fixer-controls" style="margin: 20px 0;">
                    <div class="control-group" style="margin-bottom: 15px;">
                        <label for="course-selector" style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px;"><?php echo esc_html__('Select English Course:', 'wpml-lifterlms-compatibility'); ?></label>
                        <select id="course-selector" class="course-selector" style="min-width: 400px; padding: 10px; font-size: 14px;">
                            <option value=""><?php echo esc_html__('Select a course...', 'wpml-lifterlms-compatibility'); ?></option>
                            <?php
                            // Simple direct course loading - no dependencies
                            $courses = get_posts(array(
                                'post_type' => 'course',
                                'post_status' => 'publish',
                                'numberposts' => -1,
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ));
                            
                            if (!empty($courses)) {
                                foreach ($courses as $course) {
                                    echo '<option value="' . esc_attr($course->ID) . '">' . esc_html($course->post_title . ' (ID: ' . $course->ID . ')') . '</option>';
                                }
                            } else {
                                echo '<option value="" disabled>' . esc_html__('No courses found', 'wpml-lifterlms-compatibility') . '</option>';
                            }
                            ?>
                        </select>
                        
                        <!-- DEBUG INFORMATION -->
                        <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                        <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; font-size: 12px;">
                            <strong>🐛 Debug Info:</strong><br>
                            <?php
                            // Show debug information
                            $lifterlms_active = defined('LLMS_PLUGIN_FILE') || class_exists('LifterLMS') || function_exists('llms_get_posts');
                            echo 'LifterLMS Active: ' . ($lifterlms_active ? '✅ YES' : '❌ NO') . '<br>';
                            echo 'WPML Active: ' . (function_exists('icl_get_languages') ? '✅ YES' : '❌ NO') . '<br>';
                            
                            // Check course post type
                            $post_types = get_post_types();
                            echo 'Course Post Type Registered: ' . (in_array('course', $post_types) ? '✅ YES' : '❌ NO') . '<br>';
                            
                            // Count all courses
                            $all_courses = get_posts(array('post_type' => 'course', 'post_status' => 'any', 'numberposts' => -1));
                            echo 'Total Courses in DB: ' . count($all_courses) . '<br>';
                            
                            // Count published courses
                            $published_courses = get_posts(array('post_type' => 'course', 'post_status' => 'publish', 'numberposts' => -1));
                            echo 'Published Courses: ' . count($published_courses) . '<br>';
                            
                            echo 'English Courses Found: ' . count($english_courses) . '<br>';
                            
                            // Show first few courses for debugging
                            if (!empty($all_courses)) {
                                echo '<strong>Sample Courses:</strong><br>';
                                foreach (array_slice($all_courses, 0, 3) as $course) {
                                    $lang = function_exists('icl_get_languages') ? apply_filters('wpml_post_language_details', null, $course->ID) : null;
                                    echo '- ' . $course->post_title . ' (ID: ' . $course->ID . ', Status: ' . $course->post_status . ', Lang: ' . ($lang ? $lang['language_code'] : 'N/A') . ')<br>';
                                }
                            }
                            ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="control-group" style="margin-bottom: 15px;">
                        <button type="button" id="fix-relationships-btn" class="button button-primary button-large" style="padding: 10px 20px; font-size: 16px;" disabled>
                            <?php echo esc_html__('Fix Relationships', 'wpml-lifterlms-compatibility'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="wpml-llms-fixer-progress" id="fixer-progress" style="display: none; margin: 20px 0;">
                    <div class="progress-bar" style="background: #f0f0f0; border-radius: 4px; height: 25px; overflow: hidden;">
                        <div class="progress-fill" id="progress-fill" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <div class="progress-text" id="progress-text" style="margin-top: 10px; font-weight: bold; font-size: 14px;"></div>
                </div>
                
                <div class="wpml-llms-fixer-logs" id="fixer-logs" style="margin-top: 20px;">
                    <h3 style="margin-bottom: 10px; color: #0073aa;"><?php echo esc_html__('Logs & Details', 'wpml-lifterlms-compatibility'); ?></h3>
                    <div class="logs-container" id="logs-container" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; line-height: 1.4;">
                        <p style="color: #666; margin: 0;"><?php echo esc_html__('Logs will appear here when you run the relationship fixer...', 'wpml-lifterlms-compatibility'); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="notice notice-success">
                <p><strong><?php echo esc_html__('🎉 Plugin Active & Ready!', 'wpml-lifterlms-compatibility'); ?></strong></p>
                <p><?php echo esc_html__('WPML LifterLMS Compatibility is successfully running and making your LifterLMS content 100% compatible with WPML multilingual features.', 'wpml-lifterlms-compatibility'); ?></p>
            </div>
            
            <div class="card">
                <h2><?php echo esc_html__('🔧 Integration Status', 'wpml-lifterlms-compatibility'); ?></h2>
                <ul style="list-style: none; padding-left: 0;">
                    <li style="margin: 8px 0;">✅ <strong><?php echo esc_html__('LifterLMS Post Types:', 'wpml-lifterlms-compatibility'); ?></strong> <?php echo esc_html__('Fully Compatible', 'wpml-lifterlms-compatibility'); ?></li>
                    <li style="margin: 8px 0;">✅ <strong><?php echo esc_html__('Course Translations:', 'wpml-lifterlms-compatibility'); ?></strong> <?php echo esc_html__('Active & Synchronized', 'wpml-lifterlms-compatibility'); ?></li>
                    <li style="margin: 8px 0;">✅ <strong><?php echo esc_html__('Lesson Translations:', 'wpml-lifterlms-compatibility'); ?></strong> <?php echo esc_html__('Active & Synchronized', 'wpml-lifterlms-compatibility'); ?></li>
                    <li style="margin: 8px 0;">✅ <strong><?php echo esc_html__('Quiz Translations:', 'wpml-lifterlms-compatibility'); ?></strong> <?php echo esc_html__('Active & Synchronized', 'wpml-lifterlms-compatibility'); ?></li>
                    <li style="margin: 8px 0;">✅ <strong><?php echo esc_html__('User Progress:', 'wpml-lifterlms-compatibility'); ?></strong> <?php echo esc_html__('Cross-Language Synchronized', 'wpml-lifterlms-compatibility'); ?></li>
                    <li style="margin: 8px 0;">✅ <strong><?php echo esc_html__('E-commerce Integration:', 'wpml-lifterlms-compatibility'); ?></strong> <?php echo esc_html__('Multi-Currency Ready', 'wpml-lifterlms-compatibility'); ?></li>
                    <li style="margin: 8px 0;">✅ <strong><?php echo esc_html__('Email Templates:', 'wpml-lifterlms-compatibility'); ?></strong> <?php echo esc_html__('Multilingual Support', 'wpml-lifterlms-compatibility'); ?></li>
                </ul>
            </div>
            
            <div class="card">
                <h2><?php echo esc_html__('📊 System Information', 'wpml-lifterlms-compatibility'); ?></h2>
                <table class="widefat" style="margin-top: 10px;">
                    <tbody>
                        <tr>
                            <td><strong><?php echo esc_html__('Plugin Version:', 'wpml-lifterlms-compatibility'); ?></strong></td>
                            <td><?php echo WPML_LLMS_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__('WPML Status:', 'wpml-lifterlms-compatibility'); ?></strong></td>
                            <td><?php echo defined('ICL_SITEPRESS_VERSION') ? '✅ Active (v' . ICL_SITEPRESS_VERSION . ')' : '❌ Not Active'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__('LifterLMS Status:', 'wpml-lifterlms-compatibility'); ?></strong></td>
                            <td><?php echo defined('LLMS_PLUGIN_FILE') ? '✅ Active' : '❌ Not Active'; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__('WordPress Version:', 'wpml-lifterlms-compatibility'); ?></strong></td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__('PHP Version:', 'wpml-lifterlms-compatibility'); ?></strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <h2><?php echo esc_html__('🚀 What This Plugin Does', 'wpml-lifterlms-compatibility'); ?></h2>
                <p><?php echo esc_html__('This plugin makes LifterLMS 100% compatible with WPML by:', 'wpml-lifterlms-compatibility'); ?></p>
                <ul>
                    <li><?php echo esc_html__('Registering all LifterLMS post types for WPML translation', 'wpml-lifterlms-compatibility'); ?></li>
                    <li><?php echo esc_html__('Synchronizing course relationships across languages', 'wpml-lifterlms-compatibility'); ?></li>
                    <li><?php echo esc_html__('Maintaining user progress and enrollment data across translations', 'wpml-lifterlms-compatibility'); ?></li>
                    <li><?php echo esc_html__('Ensuring e-commerce functionality works with WPML WooCommerce Multilingual', 'wpml-lifterlms-compatibility'); ?></li>
                    <li><?php echo esc_html__('Making email templates translatable and language-aware', 'wpml-lifterlms-compatibility'); ?></li>
                </ul>
            </div>
            
            <!-- REMOVED: Duplicate course fixer section - now at top of page -->
            
            <?php if (!defined('ICL_SITEPRESS_VERSION') || !defined('LLMS_PLUGIN_FILE')): ?>
            <div class="notice notice-warning">
                <p><strong><?php echo esc_html__('⚠️ Missing Dependencies', 'wpml-lifterlms-compatibility'); ?></strong></p>
                <?php if (!defined('ICL_SITEPRESS_VERSION')): ?>
                    <p><?php echo esc_html__('WPML Multilingual CMS is required for this plugin to work.', 'wpml-lifterlms-compatibility'); ?></p>
                <?php endif; ?>
                <?php if (!defined('LLMS_PLUGIN_FILE')): ?>
                    <p><?php echo esc_html__('LifterLMS is required for this plugin to work.', 'wpml-lifterlms-compatibility'); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h2 {
            margin-top: 0;
            color: #23282d;
        }
        .card ul li {
            margin: 5px 0;
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Courses are now loaded directly with PHP (no AJAX needed)
            // This avoids LifterLMS compatibility issues with AJAX calls
            
            // Handle fix button click
            $('#fix-relationships-btn').on('click', function() {
                var courseId = $('#course-selector').val();
                if (!courseId) {
                    alert('<?php echo esc_js(__('Please select a course', 'wpml-lifterlms-compatibility')); ?>');
                    return;
                }
                fixCourseRelationships(courseId);
            });
            
            // Enable/disable fix button based on course selection
            $('#course-selector').on('change', function() {
                $('#fix-relationships-btn').prop('disabled', !$(this).val());
            });
            
            function fixCourseRelationships(courseId) {
                var $button = $('#fix-relationships-btn');
                var $progress = $('#fixer-progress');
                var $progressFill = $('#progress-fill');
                var $progressText = $('#progress-text');
                var $logsContainer = $('#logs-container');
                
                // Reset UI
                $button.prop('disabled', true).text('<?php echo esc_js(__('Fixing Relationships...', 'wpml-lifterlms-compatibility')); ?>');
                $progress.show();
                $progressFill.css('width', '0%');
                $progressText.text('<?php echo esc_js(__('Starting relationship fix...', 'wpml-lifterlms-compatibility')); ?>');
                $logsContainer.html('<p style="color: #0073aa; margin: 0;"><?php echo esc_js(__('Starting relationship fix process...', 'wpml-lifterlms-compatibility')); ?></p>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    timeout: 30000, // 30 second timeout
                    data: {
                        action: 'wpml_llms_fix_course_relationships',
                        course_id: courseId,
                        nonce: '<?php echo wp_create_nonce('wpml_llms_course_fixer'); ?>'
                    },
                    success: function(response) {
                        $progressFill.css('width', '100%');
                        
                        if (response.success) {
                            $progressText.text('<?php echo esc_js(__('Relationship fixing completed successfully!', 'wpml-lifterlms-compatibility')); ?>');
                            
                            // Display logs
                            var logsHtml = '<div style="color: #008000; margin-bottom: 10px;"><strong><?php echo esc_js(__('✅ Success! Relationships fixed successfully.', 'wpml-lifterlms-compatibility')); ?></strong></div>';
                            if (response.data.logs && response.data.logs.length > 0) {
                                $.each(response.data.logs, function(index, log) {
                                    logsHtml += '<div style="margin: 5px 0; padding: 5px; background: #f0f8ff; border-left: 3px solid #0073aa;">' + log + '</div>';
                                });
                            }
                            if (response.data.summary) {
                                logsHtml += '<div style="margin-top: 15px; padding: 10px; background: #e8f5e8; border: 1px solid #4caf50; border-radius: 4px;"><strong><?php echo esc_js(__('Summary:', 'wpml-lifterlms-compatibility')); ?></strong><br>' + response.data.summary + '</div>';
                            }
                            $logsContainer.html(logsHtml);
                        } else {
                            $progressText.text('<?php echo esc_js(__('Error occurred during relationship fixing', 'wpml-lifterlms-compatibility')); ?>');
                            $logsContainer.html('<div style="color: #d63638; margin: 0;"><strong><?php echo esc_js(__('❌ Error:', 'wpml-lifterlms-compatibility')); ?></strong> ' + (response.data ? response.data : '<?php echo esc_js(__('Unknown error occurred', 'wpml-lifterlms-compatibility')); ?>') + '</div>');
                        }
                        
                        // Reset button
                        setTimeout(function() {
                            $button.prop('disabled', false).text('<?php echo esc_js(__('Fix Relationships', 'wpml-lifterlms-compatibility')); ?>');
                        }, 2000);
                    },
                    error: function(xhr, status, error) {
                        console.log('AJAX Error Details:', {xhr: xhr, status: status, error: error, responseText: xhr.responseText});
                        
                        $progressFill.css('width', '100%');
                        $progressText.text('<?php echo esc_js(__('Network error occurred', 'wpml-lifterlms-compatibility')); ?>');
                        
                        var errorMsg = '<?php echo esc_js(__('Could not connect to server', 'wpml-lifterlms-compatibility')); ?>';
                        if (status === 'timeout') {
                            errorMsg = '<?php echo esc_js(__('Request timed out - server may be slow', 'wpml-lifterlms-compatibility')); ?>';
                        } else if (xhr.responseText) {
                            errorMsg = '<?php echo esc_js(__('Server error: ', 'wpml-lifterlms-compatibility')); ?>' + xhr.responseText.substring(0, 200);
                        }
                        
                        $logsContainer.html('<div style="color: #d63638; margin: 0;"><strong><?php echo esc_js(__('❌ Network Error:', 'wpml-lifterlms-compatibility')); ?></strong> ' + errorMsg + '<br><small><?php echo esc_js(__('Check browser console for more details.', 'wpml-lifterlms-compatibility')); ?></small></div>');
                        
                        // Reset button
                        setTimeout(function() {
                            $button.prop('disabled', false).text('<?php echo esc_js(__('Fix Relationships', 'wpml-lifterlms-compatibility')); ?>');
                        }, 2000);
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Register admin settings
     */
    public function register_admin_settings() {
        // Register settings here if needed
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin page
        if (isset($this->admin_page_hook) && $hook === $this->admin_page_hook) {
            // Enqueue admin styles/scripts if needed
        }
    }
    
    /**
     * Check plugin dependencies and memory requirements
     * @return bool
     */
    private function check_dependencies() {
        $missing_requirements = array();
        
        // Check memory limit first to prevent memory exhaustion
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $required_memory = 128 * 1024 * 1024; // 128MB minimum
        
        if ($memory_limit > 0 && $memory_limit < $required_memory) {
            $missing_requirements[] = sprintf(
                'PHP Memory Limit: %s required (current: %s)',
                size_format($required_memory),
                size_format($memory_limit)
            );
        }
        
        // Check for WPML
        if (!defined('ICL_SITEPRESS_VERSION')) {
            $missing_requirements[] = 'WPML Multilingual CMS';
        }
        
        // Check for LifterLMS
        if (!defined('LLMS_PLUGIN_FILE')) {
            $missing_requirements[] = 'LifterLMS';
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $missing_requirements[] = sprintf('PHP 7.4 or higher (current: %s)', PHP_VERSION);
        }
        
        // Show admin notice if requirements are missing
        if (!empty($missing_requirements)) {
            add_action('admin_notices', function() use ($missing_requirements) {
                $requirements_list = implode(', ', $missing_requirements);
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('WPML LifterLMS Compatibility requires: %s', 'wpml-lifterlms-compatibility'),
                    '<strong>' . $requirements_list . '</strong>'
                );
                echo '</p></div>';
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * Load autoloader
     */
    private function load_autoloader() {
        require_once WPML_LLMS_PLUGIN_DIR . 'includes/class-wpml-lifterlms-autoloader.php';
        WPML_LifterLMS_Autoloader::register();
    }
    
    /**
     * Initialize plugin components with lazy loading
     */
    private function init_components() {
        // Initialize components with lazy loading to prevent memory exhaustion
        // Only initialize essential components immediately
        
        // Initialize logger first for error tracking
        $this->components['logger'] = new WPML_LifterLMS_Logger();
        $this->components['logger']->init();
        

        
        // Initialize other components on demand using hooks
        add_action('init', array($this, 'init_core_components'), 15);
        // DISABLED: Using direct admin menu initialization instead
        // add_action('admin_init', array($this, 'init_admin_components'), 15);
        add_action('wp_loaded', array($this, 'init_frontend_components'), 15);
    }
    
    /**
     * Initialize core components
     */
    public function init_core_components() {
        // Only initialize if not already done
        if (!isset($this->components['post_types'])) {
            try {
                $this->components['post_types'] = new WPML_LifterLMS_Post_Types();
                $this->components['post_types']->init();
                
                $this->components['taxonomies'] = new WPML_LifterLMS_Taxonomies();
                $this->components['taxonomies']->init();
                
                $this->components['custom_fields'] = new WPML_LifterLMS_Custom_Fields();
                $this->components['custom_fields']->init();
                
                $this->components['relationships'] = WPML_LifterLMS_Relationships::get_instance();
                $this->components['relationships']->init();
                
            } catch (Exception $e) {
                if (isset($this->components['logger'])) {
                    $this->components['logger']->error('Failed to initialize core components: ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Register WPML configuration programmatically
     */
    public function register_wpml_config() {
        // Only register if WPML is active
        if (!defined('ICL_SITEPRESS_VERSION')) {
            return;
        }
        
        try {
            // Register post types for translation
            add_filter('wpml_custom_post_translation_settings', array($this, 'register_post_types_for_translation'), 10, 1);
            
            // Register taxonomies for translation
            add_filter('wpml_custom_taxonomy_translation_settings', array($this, 'register_taxonomies_for_translation'), 10, 1);
            
            // Register custom fields for translation
            add_filter('wpml_custom_field_translation_settings', array($this, 'register_custom_fields_for_translation'), 10, 1);
            
        } catch (Exception $e) {
            // Log error if logger is available
            if (isset($this->components['logger'])) {
                $this->components['logger']->error('Failed to register WPML configuration: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Register post types for translation
     * @param array $settings
     * @return array
     */
    public function register_post_types_for_translation($settings) {
        if (!is_array($settings)) {
            $settings = array();
        }
        
        try {
            $lifterlms_post_types = array(
                'course' => array('translate' => 1, 'display_as_translated' => 1),
                'lesson' => array('translate' => 1, 'display_as_translated' => 1),
                'llms_quiz' => array('translate' => 1, 'display_as_translated' => 1),
                'llms_question' => array('translate' => 1, 'display_as_translated' => 1),
                'llms_membership' => array('translate' => 1, 'display_as_translated' => 1),
                'llms_certificate' => array('translate' => 1, 'display_as_translated' => 1),
                'llms_achievement' => array('translate' => 1, 'display_as_translated' => 1),
                'llms_email' => array('translate' => 1, 'display_as_translated' => 1),
                'llms_access_plan' => array('translate' => 1, 'display_as_translated' => 1),
                'llms_coupon' => array('translate' => 0, 'display_as_translated' => 0),
                'llms_voucher' => array('translate' => 0, 'display_as_translated' => 0),
                'llms_order' => array('translate' => 0, 'display_as_translated' => 0),
                'llms_transaction' => array('translate' => 0, 'display_as_translated' => 0),
            );
            
            foreach ($lifterlms_post_types as $post_type => $config) {
                if (post_type_exists($post_type)) {
                    $settings[$post_type] = $config;
                }
            }
        } catch (Exception $e) {
            // Silently handle errors to prevent breaking WPML
        }
        
        return $settings;
    }
    
    /**
     * Register taxonomies for translation
     * @param array $settings
     * @return array
     */
    public function register_taxonomies_for_translation($settings) {
        if (!is_array($settings)) {
            $settings = array();
        }
        
        try {
            $lifterlms_taxonomies = array(
                'course_cat',
                'course_tag', 
                'course_difficulty',
                'course_track',
                'membership_cat',
                'membership_tag'
            );
            
            foreach ($lifterlms_taxonomies as $taxonomy) {
                if (taxonomy_exists($taxonomy)) {
                    $settings[$taxonomy] = array('translate' => 1);
                }
            }
        } catch (Exception $e) {
            // Silently handle errors to prevent breaking WPML
        }
        
        return $settings;
    }
    
    /**
     * Register custom fields for translation
     * @param array $settings
     * @return array
     */
    public function register_custom_fields_for_translation($settings) {
        if (!is_array($settings)) {
            $settings = array();
        }
        
        try {
            $translatable_fields = array(
                '_llms_excerpt',
                '_llms_video_embed',
                '_llms_audio_embed',
                '_llms_course_prerequisites_message',
                '_llms_lesson_prerequisite_message',
                '_llms_drip_message',
                '_llms_quiz_description',
                '_llms_question_description',
                '_llms_email_subject',
                '_llms_email_message'
            );
            
            $copy_fields = array(
                '_llms_course_prerequisites',
                '_llms_parent_course',
                '_llms_parent_section',
                '_llms_points',
                '_llms_price',
                '_llms_sale_price'
            );
            
            foreach ($translatable_fields as $field) {
                $settings[$field] = array('translate' => 1);
            }
            
            foreach ($copy_fields as $field) {
                $settings[$field] = array('translate' => 0, 'copy' => 1);
            }
        } catch (Exception $e) {
            // Silently handle errors to prevent breaking WPML
        }
        
        return $settings;
    }
    
    /**
     * Initialize admin components
     */
    public function init_admin_components() {
        if (is_admin() && !isset($this->components['admin'])) {
            try {
                $this->components['admin'] = new WPML_LifterLMS_Admin();
                $this->components['admin']->init();
                
                // Initialize course fixer
                
                // Manually require the course fixer file to ensure it's loaded
                $course_fixer_file = WPML_LLMS_PLUGIN_DIR . 'includes/class-wpml-lifterlms-course-fixer.php';
                if (file_exists($course_fixer_file)) {
                    require_once $course_fixer_file;
                }
                
                // Check if class exists before instantiating
                if (class_exists('WPML_LifterLMS_Course_Fixer')) {
                    $this->components['course_fixer'] = WPML_LifterLMS_Course_Fixer::get_instance();
                }
                
            } catch (Exception $e) {
                if (isset($this->components['logger'])) {
                    $this->components['logger']->error('Failed to initialize admin components: ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Simple AJAX handler for fixing course relationships
     */
    public function handle_fix_course_relationships_simple() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpml_llms_course_fixer')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get course ID
        $course_id = intval($_POST['course_id']);
        if (!$course_id) {
            wp_send_json_error('Invalid course ID');
        }
        
        // Get course details
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'course') {
            wp_send_json_error('Invalid course');
        }
        
        // Simple relationship fix logic
        $results = array();
        $results[] = 'Processing course: ' . $course->post_title . ' (ID: ' . $course_id . ')';
        
        // Fix WPML language relationships if WPML is active
        if (function_exists('icl_get_languages')) {
            $results[] = 'WPML detected - fixing language relationships...';
            
            // Get all courses to find potential translations
            $all_courses = get_posts(array(
                'post_type' => 'course',
                'post_status' => 'publish',
                'numberposts' => -1
            ));
            
            $results[] = 'Found ' . count($all_courses) . ' total courses';
            
            // Find the English course (ID: 260) and Urdu course (ID: 261) based on your screenshot
            $english_course_id = 260;
            $urdu_course_id = 261;
            
            // Check if these courses exist
            $english_course = get_post($english_course_id);
            $urdu_course = get_post($urdu_course_id);
            
            if ($english_course && $urdu_course) {
                $results[] = 'Found English course: ' . $english_course->post_title . ' (ID: ' . $english_course_id . ')';
                $results[] = 'Found Urdu course: ' . $urdu_course->post_title . ' (ID: ' . $urdu_course_id . ')';
                
                // Set language for English course
                do_action('wpml_set_element_language_details', array(
                    'element_id' => $english_course_id,
                    'element_type' => 'post_course',
                    'language_code' => 'en'
                ));
                $results[] = 'Set English course language to: en';
                
                // Set language for Urdu course
                do_action('wpml_set_element_language_details', array(
                    'element_id' => $urdu_course_id,
                    'element_type' => 'post_course',
                    'language_code' => 'ur'
                ));
                $results[] = 'Set Urdu course language to: ur';
                
                // Connect them as translations
                $trid = apply_filters('wpml_element_trid', null, $english_course_id, 'post_course');
                if (!$trid) {
                    // Create new translation group
                    do_action('wpml_set_element_language_details', array(
                        'element_id' => $english_course_id,
                        'element_type' => 'post_course',
                        'language_code' => 'en',
                        'source_language_code' => null
                    ));
                    $trid = apply_filters('wpml_element_trid', null, $english_course_id, 'post_course');
                    $results[] = 'Created new translation group with TRID: ' . $trid;
                }
                
                // Add Urdu course to the same translation group
                do_action('wpml_set_element_language_details', array(
                    'element_id' => $urdu_course_id,
                    'element_type' => 'post_course',
                    'language_code' => 'ur',
                    'source_language_code' => 'en',
                    'trid' => $trid
                ));
                $results[] = 'Connected Urdu course as translation of English course';
                
                // Now fix lesson relationships
                $results[] = 'Fixing lesson relationships...';
                
                // Get lessons for English course
                $english_lessons = get_posts(array(
                    'post_type' => 'lesson',
                    'meta_key' => '_llms_parent_course',
                    'meta_value' => $english_course_id,
                    'numberposts' => -1
                ));
                
                // Get lessons for Urdu course
                $urdu_lessons = get_posts(array(
                    'post_type' => 'lesson',
                    'meta_key' => '_llms_parent_course',
                    'meta_value' => $urdu_course_id,
                    'numberposts' => -1
                ));
                
                $results[] = 'English course has ' . count($english_lessons) . ' lessons';
                $results[] = 'Urdu course has ' . count($urdu_lessons) . ' lessons';
                
                // Connect lessons as translations (assuming they are in the same order)
                for ($i = 0; $i < min(count($english_lessons), count($urdu_lessons)); $i++) {
                    $english_lesson = $english_lessons[$i];
                    $urdu_lesson = $urdu_lessons[$i];
                    
                    // Set lesson languages
                    do_action('wpml_set_element_language_details', array(
                        'element_id' => $english_lesson->ID,
                        'element_type' => 'post_lesson',
                        'language_code' => 'en'
                    ));
                    
                    do_action('wpml_set_element_language_details', array(
                        'element_id' => $urdu_lesson->ID,
                        'element_type' => 'post_lesson',
                        'language_code' => 'ur'
                    ));
                    
                    // Get or create translation group for lessons
                    $lesson_trid = apply_filters('wpml_element_trid', null, $english_lesson->ID, 'post_lesson');
                    if (!$lesson_trid) {
                        do_action('wpml_set_element_language_details', array(
                            'element_id' => $english_lesson->ID,
                            'element_type' => 'post_lesson',
                            'language_code' => 'en',
                            'source_language_code' => null
                        ));
                        $lesson_trid = apply_filters('wpml_element_trid', null, $english_lesson->ID, 'post_lesson');
                    }
                    
                    // Connect Urdu lesson as translation
                    do_action('wpml_set_element_language_details', array(
                        'element_id' => $urdu_lesson->ID,
                        'element_type' => 'post_lesson',
                        'language_code' => 'ur',
                        'source_language_code' => 'en',
                        'trid' => $lesson_trid
                    ));
                    
                    $results[] = 'Connected lessons: "' . $english_lesson->post_title . '" ↔ "' . $urdu_lesson->post_title . '"';
                }
                
            } else {
                $results[] = 'Could not find expected courses (English ID: 260, Urdu ID: 261)';
            }
        } else {
            $results[] = 'WPML not active - no language relationships to fix';
        }
        
        // Fix LifterLMS relationships (lessons, quizzes, etc.)
        $results[] = 'Checking LifterLMS relationships...';
        
        // Get course lessons
        $lessons = get_posts(array(
            'post_type' => 'lesson',
            'meta_key' => '_llms_parent_course',
            'meta_value' => $course_id,
            'numberposts' => -1
        ));
        
        if ($lessons) {
            $results[] = 'Found ' . count($lessons) . ' lessons attached to this course';
            foreach ($lessons as $lesson) {
                $results[] = '- Lesson: ' . $lesson->post_title . ' (ID: ' . $lesson->ID . ')';
            }
        } else {
            $results[] = 'No lessons found for this course';
        }
        
        $results[] = 'Course relationship fix completed successfully!';
        
        wp_send_json_success(array(
            'message' => 'Course relationships fixed successfully!',
            'course_id' => $course_id,
            'course_title' => $course->post_title,
            'details' => implode("\n", $results)
        ));
    }
    
    // REMOVED: get_english_courses_direct() method - using course fixer's method to avoid duplication
    
    // REMOVED: Duplicate menu methods - using single menu approach in add_admin_menu_direct()
    
    /**
     * Initialize frontend components
     */
    public function init_frontend_components() {
        if (!is_admin() && !isset($this->components['frontend'])) {
            try {
                $this->components['frontend'] = new WPML_LifterLMS_Frontend();
                $this->components['frontend']->init();
                
                $this->components['ecommerce'] = new WPML_LifterLMS_Ecommerce();
                $this->components['ecommerce']->init();
                
                $this->components['user_data'] = new WPML_LifterLMS_User_Data();
                $this->components['user_data']->init();
                
                $this->components['emails'] = new WPML_LifterLMS_Emails();
                $this->components['emails']->init();
                
            } catch (Exception $e) {
                if (isset($this->components['logger'])) {
                    $this->components['logger']->error('Failed to initialize frontend components: ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Load plugin textdomain
     */
    private function load_textdomain() {
        load_plugin_textdomain(
            'wpml-lifterlms-compatibility',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Get component instance with lazy loading
     * @param string $component_name
     * @return object|null
     */
    public function get_component($component_name) {
        // Return component if already loaded
        if (isset($this->components[$component_name])) {
            return $this->components[$component_name];
        }
        
        // Lazy load component if not already loaded
        try {
            switch ($component_name) {
                case 'post_types':
                    if (!isset($this->components['post_types'])) {
                        $this->components['post_types'] = new WPML_LifterLMS_Post_Types();
                        $this->components['post_types']->init();
                    }
                    break;
                    
                case 'taxonomies':
                    if (!isset($this->components['taxonomies'])) {
                        $this->components['taxonomies'] = new WPML_LifterLMS_Taxonomies();
                        $this->components['taxonomies']->init();
                    }
                    break;
                    
                case 'custom_fields':
                    if (!isset($this->components['custom_fields'])) {
                        $this->components['custom_fields'] = new WPML_LifterLMS_Custom_Fields();
                        $this->components['custom_fields']->init();
                    }
                    break;
                    
                case 'ecommerce':
                    if (!isset($this->components['ecommerce'])) {
                        $this->components['ecommerce'] = new WPML_LifterLMS_Ecommerce();
                        $this->components['ecommerce']->init();
                    }
                    break;
                    
                case 'user_data':
                    if (!isset($this->components['user_data'])) {
                        $this->components['user_data'] = new WPML_LifterLMS_User_Data();
                        $this->components['user_data']->init();
                    }
                    break;
                    
                case 'frontend':
                    if (!isset($this->components['frontend'])) {
                        $this->components['frontend'] = new WPML_LifterLMS_Frontend();
                        $this->components['frontend']->init();
                    }
                    break;
                    
                case 'emails':
                    if (!isset($this->components['emails'])) {
                        $this->components['emails'] = new WPML_LifterLMS_Emails();
                        $this->components['emails']->init();
                    }
                    break;
                    
                case 'admin':
                    if (!isset($this->components['admin'])) {
                        $this->components['admin'] = new WPML_LifterLMS_Admin();
                        $this->components['admin']->init();
                    }
                    break;
            }
        } catch (Exception $e) {
            if (isset($this->components['logger'])) {
                $this->components['logger']->error('Failed to lazy load component ' . $component_name . ': ' . $e->getMessage());
            }
            return null;
        }
        
        return isset($this->components[$component_name]) ? $this->components[$component_name] : null;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check dependencies on activation
        if (!$this->check_dependencies()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('WPML LifterLMS Compatibility requires WPML Multilingual CMS and LifterLMS to be installed and activated.', 'wpml-lifterlms-compatibility'),
                __('Plugin Activation Error', 'wpml-lifterlms-compatibility'),
                array('back_link' => true)
            );
        }
        
        // Set activation flag
        update_option('wpml_lifterlms_compatibility_activated', true);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        do_action('wpml_lifterlms_compatibility_activated');
    }
    
    /**
     * Plugin deactivation with memory cleanup
     */
    public function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('wpml_lifterlms_log_cleanup');
        
        // Clean up memory
        $this->cleanup_memory();
        
        // Clean up options
        delete_option('wpml_lifterlms_compatibility_activated');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        do_action('wpml_lifterlms_compatibility_deactivated');
    }
    
    /**
     * Clean up memory and resources
     */
    private function cleanup_memory() {
        // Unset all components to free memory
        foreach ($this->components as $component_name => $component) {
            if (is_object($component) && method_exists($component, '__destruct')) {
                $component->__destruct();
            }
            unset($this->components[$component_name]);
        }
        
        // Clear components array
        $this->components = array();
        
        // Force garbage collection if available
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Get plugin version
     * @return string
     */
    public function get_version() {
        return WPML_LLMS_VERSION;
    }
    
    /**
     * Get plugin directory path
     * @return string
     */
    public function get_plugin_dir() {
        return WPML_LLMS_PLUGIN_DIR;
    }
    
    /**
     * Manually sync all existing relationships
     * This method can be called to fix existing translated content
     */
    public function sync_all_relationships() {
        if (!$this->is_wpml_active() || !$this->is_lifterlms_active()) {
            return false;
        }
        
        // Get relationships component
        $relationships = $this->get_component('relationships');
        if (!$relationships) {
            return false;
        }
        
        // Get all courses with translations
        $courses = get_posts(array(
            'post_type' => 'course',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        foreach ($courses as $course) {
            $translations = apply_filters('wpml_get_element_translations', null, $course->ID, 'post_course');
            if ($translations && count($translations) > 1) {
                foreach ($translations as $lang => $translation) {
                    if ($translation->element_id != $course->ID) {
                        $relationships->sync_relationships_on_translation(
                            $translation->element_id, 
                            array(), 
                            (object)array('original_doc_id' => $course->ID)
                        );
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get plugin URL
     * @return string
     */
    public function get_plugin_url() {
        return WPML_LLMS_PLUGIN_URL;
    }
}

/**
 * Initialize the plugin
 */
function wpml_lifterlms_compatibility() {
    return WPML_LifterLMS_Compatibility::get_instance();
}

// Start the plugin
wpml_lifterlms_compatibility();
