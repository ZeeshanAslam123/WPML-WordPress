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
        add_action('plugins_loaded', array($this, 'init'), 0);
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
        
        // Initialize components with lazy loading
        $this->init_components();
        
        // Load textdomain
        $this->load_textdomain();
        
        // Log memory usage if debug mode is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $final_memory = memory_get_usage(true);
            $memory_used = $final_memory - $initial_memory;
            error_log(sprintf(
                'WPML LifterLMS Compatibility: Memory used during initialization: %s (Peak: %s)',
                size_format($memory_used),
                size_format(memory_get_peak_usage(true))
            ));
        }
        
        // Plugin is ready
        do_action('wpml_lifterlms_compatibility_loaded');
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
        
        // Initialize cache system early
        $this->components['cache'] = new WPML_LifterLMS_Cache();
        $this->components['cache']->init();
        
        // Initialize other components on demand using hooks
        add_action('init', array($this, 'init_core_components'), 15);
        add_action('admin_init', array($this, 'init_admin_components'), 15);
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
                
            } catch (Exception $e) {
                if (isset($this->components['logger'])) {
                    $this->components['logger']->error('Failed to initialize core components: ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Initialize admin components
     */
    public function init_admin_components() {
        if (is_admin() && !isset($this->components['admin'])) {
            try {
                $this->components['admin'] = new WPML_LifterLMS_Admin();
                $this->components['admin']->init();
                
            } catch (Exception $e) {
                if (isset($this->components['logger'])) {
                    $this->components['logger']->error('Failed to initialize admin components: ' . $e->getMessage());
                }
            }
        }
    }
    
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
        wp_clear_scheduled_hook('wpml_lifterlms_cache_cleanup');
        wp_clear_scheduled_hook('wpml_lifterlms_log_cleanup');
        
        // Clear cache if available
        if (isset($this->components['cache'])) {
            $this->components['cache']->clear_all_cache();
        }
        
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
