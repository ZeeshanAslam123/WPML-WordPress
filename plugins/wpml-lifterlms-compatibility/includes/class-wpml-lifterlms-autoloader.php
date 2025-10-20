<?php
/**
 * WPML LifterLMS Compatibility Autoloader
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autoloader class
 */
class WPML_LifterLMS_Autoloader {
    
    /**
     * Register autoloader
     */
    public static function register() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }
    
    /**
     * Autoload classes
     * @param string $class_name
     */
    public static function autoload($class_name) {
        // Only autoload our classes
        if (strpos($class_name, 'WPML_LifterLMS_') !== 0) {
            return;
        }
        
        // Debug: Show autoload attempts
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<pre>WPML LifterLMS Autoloader: Attempting to load class</pre>';
            var_dump('Autoloader called for class', $class_name);
        }
        
        // Convert class name to file name
        $file_name = self::get_file_name_from_class($class_name);
        
        // Build file path
        $file_path = WPML_LLMS_PLUGIN_DIR . 'includes/' . $file_name;
        
        // Debug: Show file path
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<pre>WPML LifterLMS Autoloader: Looking for file</pre>';
            var_dump('Generated filename', $file_name);
            var_dump('Full file path', $file_path);
            var_dump('File exists?', file_exists($file_path));
        }
        
        // Load the file if it exists
        if (file_exists($file_path)) {
            require_once $file_path;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<pre>WPML LifterLMS Autoloader: Successfully loaded file</pre>';
                var_dump('File loaded successfully', $file_path);
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<pre>WPML LifterLMS Autoloader: File not found</pre>';
                var_dump('File not found', $file_path);
            }
        }
    }
    
    /**
     * Convert class name to file name
     * @param string $class_name
     * @return string
     */
    private static function get_file_name_from_class($class_name) {
        // Convert from PascalCase to kebab-case
        $file_name = strtolower(str_replace('_', '-', $class_name));
        
        // Add class prefix
        $file_name = 'class-' . $file_name . '.php';
        
        return $file_name;
    }
}
