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
        
        // Convert class name to file name
        $file_name = self::get_file_name_from_class($class_name);
        
        // Build file path
        $file_path = WPML_LLMS_PLUGIN_DIR . 'includes/' . $file_name;
        
        // Load the file if it exists
        if (file_exists($file_path)) {
            require_once $file_path;
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

