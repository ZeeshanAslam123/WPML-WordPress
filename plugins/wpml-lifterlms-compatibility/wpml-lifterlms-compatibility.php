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
     * Initialize plugin
     */
    public function init() {
        // Check dependencies
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Load autoloader
        $this->load_autoloader();
        
        // Initialize components
        $this->init_components();
        
        // Load textdomain
        $this->load_textdomain();
        
        // Plugin is ready
        do_action('wpml_lifterlms_compatibility_loaded');
    }
    
    /**
     * Check plugin dependencies
     * @return bool
     */
    private function check_dependencies() {
        $missing_plugins = array();
        
        // Check for WPML
        if (!defined('ICL_SITEPRESS_VERSION')) {
            $missing_plugins[] = 'WPML Multilingual CMS';
        }
        
        // Check for LifterLMS
        if (!defined('LLMS_PLUGIN_FILE')) {
            $missing_plugins[] = 'LifterLMS';
        }
        
        // Show admin notice if dependencies are missing
        if (!empty($missing_plugins)) {
            add_action('admin_notices', function() use ($missing_plugins) {
                $plugin_names = implode(', ', $missing_plugins);
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('WPML LifterLMS Compatibility requires the following plugins to be installed and activated: %s', 'wpml-lifterlms-compatibility'),
                    '<strong>' . $plugin_names . '</strong>'
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
     * Initialize plugin components
     */
    private function init_components() {
        // Core components
        $this->components['post_types'] = new WPML_LifterLMS_Post_Types();
        $this->components['taxonomies'] = new WPML_LifterLMS_Taxonomies();
        $this->components['custom_fields'] = new WPML_LifterLMS_Custom_Fields();
        $this->components['ecommerce'] = new WPML_LifterLMS_Ecommerce();
        $this->components['user_data'] = new WPML_LifterLMS_User_Data();
        $this->components['frontend'] = new WPML_LifterLMS_Frontend();
        $this->components['emails'] = new WPML_LifterLMS_Emails();
        $this->components['admin'] = new WPML_LifterLMS_Admin();
        $this->components['cache'] = new WPML_LifterLMS_Cache();
        $this->components['logger'] = new WPML_LifterLMS_Logger();
        
        // Initialize all components
        foreach ($this->components as $component) {
            if (method_exists($component, 'init')) {
                $component->init();
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
     * Get component instance
     * @param string $component_name
     * @return object|null
     */
    public function get_component($component_name) {
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
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up
        delete_option('wpml_lifterlms_compatibility_activated');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        do_action('wpml_lifterlms_compatibility_deactivated');
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

