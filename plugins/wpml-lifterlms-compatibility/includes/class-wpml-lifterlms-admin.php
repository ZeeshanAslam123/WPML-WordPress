<?php
/**
 * WPML LifterLMS Admin Handler
 * 
 * Implements plugin configuration system and admin settings
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Handler Class
 */
class WPML_LifterLMS_Admin {
    
    /**
     * Initialize the component
     */
    public function init() {
        // REMOVED: Admin menu registration - now handled in main plugin file
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Handle AJAX requests
        add_action('wp_ajax_wpml_llms_sync_translations', array($this, 'handle_sync_translations'));
        add_action('wp_ajax_wpml_llms_debug_course', array($this, 'handle_debug_course'));
        add_action('wp_ajax_wpml_llms_export_config', array($this, 'handle_export_config'));
        add_action('wp_ajax_wpml_llms_import_config', array($this, 'handle_import_config'));
    }
    
    // REMOVED: Admin menu functionality moved to main plugin file
    // This class no longer handles menu creation to avoid conflicts
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wpml_lifterlms_settings', 'wpml_lifterlms_options');
        
        // General settings section
        add_settings_section(
            'wpml_lifterlms_general',
            __('General Settings', 'wpml-lifterlms-compatibility'),
            array($this, 'general_section_callback'),
            'wpml_lifterlms_settings'
        );
        
        // Post types settings
        add_settings_section(
            'wpml_lifterlms_post_types',
            __('Post Types', 'wpml-lifterlms-compatibility'),
            array($this, 'post_types_section_callback'),
            'wpml_lifterlms_settings'
        );
        
        // E-commerce settings
        add_settings_section(
            'wpml_lifterlms_ecommerce',
            __('E-commerce', 'wpml-lifterlms-compatibility'),
            array($this, 'ecommerce_section_callback'),
            'wpml_lifterlms_settings'
        );
        
        // User data settings
        add_settings_section(
            'wpml_lifterlms_user_data',
            __('User Data', 'wpml-lifterlms-compatibility'),
            array($this, 'user_data_section_callback'),
            'wpml_lifterlms_settings'
        );
        
        $this->add_settings_fields();
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        // General settings
        add_settings_field(
            'enable_compatibility',
            __('Enable Compatibility', 'wpml-lifterlms-compatibility'),
            array($this, 'checkbox_field_callback'),
            'wpml_lifterlms_settings',
            'wpml_lifterlms_general',
            array('field' => 'enable_compatibility', 'default' => true)
        );
        
        add_settings_field(
            'sync_user_progress',
            __('Sync User Progress', 'wpml-lifterlms-compatibility'),
            array($this, 'checkbox_field_callback'),
            'wpml_lifterlms_settings',
            'wpml_lifterlms_user_data',
            array('field' => 'sync_user_progress', 'default' => true)
        );
        
        add_settings_field(
            'enable_multi_currency',
            __('Enable Multi-Currency', 'wpml-lifterlms-compatibility'),
            array($this, 'checkbox_field_callback'),
            'wpml_lifterlms_settings',
            'wpml_lifterlms_ecommerce',
            array('field' => 'enable_multi_currency', 'default' => false)
        );
    }
    
    /**
     * Enqueue admin scripts
     * @param string $hook
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wpml-lifterlms-compatibility') === false) {
            return;
        }
        
        wp_enqueue_script(
            'wpml-lifterlms-admin',
            WPML_LLMS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WPML_LLMS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wpml-lifterlms-admin',
            WPML_LLMS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPML_LLMS_VERSION
        );
        
        wp_localize_script('wpml-lifterlms-admin', 'wpmlLlmsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpml_llms_admin'),
            'strings' => array(
                'syncing' => __('Syncing translations...', 'wpml-lifterlms-compatibility'),
                'syncComplete' => __('Sync completed successfully!', 'wpml-lifterlms-compatibility'),
                'syncError' => __('Sync failed. Please try again.', 'wpml-lifterlms-compatibility')
            )
        ));
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if WPML and LifterLMS are active
        if (!defined('ICL_SITEPRESS_VERSION')) {
            echo '<div class="notice notice-error"><p>';
            echo __('WPML LifterLMS Compatibility requires WPML Multilingual CMS to be installed and activated.', 'wpml-lifterlms-compatibility');
            echo '</p></div>';
        }
        
        if (!defined('LLMS_PLUGIN_FILE')) {
            echo '<div class="notice notice-error"><p>';
            echo __('WPML LifterLMS Compatibility requires LifterLMS to be installed and activated.', 'wpml-lifterlms-compatibility');
            echo '</p></div>';
        }
        
        // Show success message after sync
        if (isset($_GET['synced']) && $_GET['synced'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo __('Translations synced successfully!', 'wpml-lifterlms-compatibility');
            echo '</p></div>';
        }
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wpml-llms-admin-header">
                <h2><?php _e('WPML LifterLMS Compatibility Settings', 'wpml-lifterlms-compatibility'); ?></h2>
                <p><?php _e('Configure how WPML integrates with LifterLMS for complete multilingual support.', 'wpml-lifterlms-compatibility'); ?></p>
            </div>
            
            <div class="wpml-llms-admin-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'wpml-lifterlms-compatibility'); ?></a>
                    <a href="#post-types" class="nav-tab"><?php _e('Post Types', 'wpml-lifterlms-compatibility'); ?></a>
                    <a href="#ecommerce" class="nav-tab"><?php _e('E-commerce', 'wpml-lifterlms-compatibility'); ?></a>
                    <a href="#user-data" class="nav-tab"><?php _e('User Data', 'wpml-lifterlms-compatibility'); ?></a>
                    <a href="#tools" class="nav-tab"><?php _e('Tools', 'wpml-lifterlms-compatibility'); ?></a>
                </nav>
                
                <div id="general" class="tab-content active">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('wpml_lifterlms_settings');
                        do_settings_sections('wpml_lifterlms_settings');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <div id="post-types" class="tab-content">
                    <?php $this->render_post_types_tab(); ?>
                </div>
                
                <div id="ecommerce" class="tab-content">
                    <?php $this->render_ecommerce_tab(); ?>
                </div>
                
                <div id="user-data" class="tab-content">
                    <?php $this->render_user_data_tab(); ?>
                </div>
                
                <div id="tools" class="tab-content">
                    <?php $this->render_tools_tab(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render post types tab
     */
    private function render_post_types_tab() {
        $post_types_handler = wpml_lifterlms_compatibility()->get_component('post_types');
        $post_types_config = $post_types_handler ? $post_types_handler->get_config() : array();
        
        ?>
        <h3><?php _e('Post Types Translation Settings', 'wpml-lifterlms-compatibility'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Post Type', 'wpml-lifterlms-compatibility'); ?></th>
                    <th><?php _e('Translation Mode', 'wpml-lifterlms-compatibility'); ?></th>
                    <th><?php _e('Display as Translated', 'wpml-lifterlms-compatibility'); ?></th>
                    <th><?php _e('Priority', 'wpml-lifterlms-compatibility'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($post_types_config as $post_type => $config): ?>
                <tr>
                    <td><strong><?php echo esc_html($post_type); ?></strong></td>
                    <td>
                        <span class="mode-<?php echo esc_attr($config['mode']); ?>">
                            <?php echo esc_html(ucfirst($config['mode'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo $config['display_as_translated'] ? '✓' : '✗'; ?>
                    </td>
                    <td>
                        <span class="priority-<?php echo esc_attr($config['priority']); ?>">
                            <?php echo esc_html(ucfirst($config['priority'])); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render e-commerce tab
     */
    private function render_ecommerce_tab() {
        ?>
        <h3><?php _e('E-commerce Integration Status', 'wpml-lifterlms-compatibility'); ?></h3>
        <div class="wpml-llms-status-grid">
            <div class="status-item">
                <h4><?php _e('Multi-Currency', 'wpml-lifterlms-compatibility'); ?></h4>
                <p class="status <?php echo class_exists('WCML_Multi_Currency') ? 'enabled' : 'disabled'; ?>">
                    <?php echo class_exists('WCML_Multi_Currency') ? __('Enabled', 'wpml-lifterlms-compatibility') : __('Disabled', 'wpml-lifterlms-compatibility'); ?>
                </p>
            </div>
            <div class="status-item">
                <h4><?php _e('Order Synchronization', 'wpml-lifterlms-compatibility'); ?></h4>
                <p class="status enabled"><?php _e('Active', 'wpml-lifterlms-compatibility'); ?></p>
            </div>
            <div class="status-item">
                <h4><?php _e('Coupon Translation', 'wpml-lifterlms-compatibility'); ?></h4>
                <p class="status enabled"><?php _e('Active', 'wpml-lifterlms-compatibility'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render user data tab
     */
    private function render_user_data_tab() {
        ?>
        <h3><?php _e('User Data Synchronization', 'wpml-lifterlms-compatibility'); ?></h3>
        <p><?php _e('Configure how user progress, enrollments, and achievements are handled across languages.', 'wpml-lifterlms-compatibility'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Progress Synchronization', 'wpml-lifterlms-compatibility'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" checked disabled>
                        <?php _e('Sync user progress across all language versions', 'wpml-lifterlms-compatibility'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Enrollment Synchronization', 'wpml-lifterlms-compatibility'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" checked disabled>
                        <?php _e('Sync enrollments across course translations', 'wpml-lifterlms-compatibility'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Achievement Translation', 'wpml-lifterlms-compatibility'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" checked disabled>
                        <?php _e('Generate achievements in user\'s preferred language', 'wpml-lifterlms-compatibility'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render tools tab
     */
    private function render_tools_tab() {
        ?>
        <h3><?php _e('Compatibility Tools', 'wpml-lifterlms-compatibility'); ?></h3>
        
        <div class="wpml-llms-tools">
            <div class="tool-section">
                <h4><?php _e('Sync Translations', 'wpml-lifterlms-compatibility'); ?></h4>
                <p><?php _e('Synchronize existing LifterLMS content with WPML translations.', 'wpml-lifterlms-compatibility'); ?></p>
                <button type="button" class="button button-primary" id="sync-translations">
                    <?php _e('Sync Now', 'wpml-lifterlms-compatibility'); ?>
                </button>
                <div id="sync-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <p class="progress-text"></p>
                </div>
            </div>
            
            <div class="tool-section">
                <h4><?php _e('Debug Course Relationships', 'wpml-lifterlms-compatibility'); ?></h4>
                <p><?php _e('Debug relationship issues for a specific course ID.', 'wpml-lifterlms-compatibility'); ?></p>
                <input type="number" id="debug-course-id" placeholder="<?php _e('Enter Course ID', 'wpml-lifterlms-compatibility'); ?>" style="width: 150px; margin-right: 10px;">
                <button type="button" class="button" id="debug-relationships">
                    <?php _e('Debug Course', 'wpml-lifterlms-compatibility'); ?>
                </button>
                <div id="debug-results" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa; display: none;">
                    <p><strong><?php _e('Debug results will appear here. Check your error logs for detailed information.', 'wpml-lifterlms-compatibility'); ?></strong></p>
                </div>
            </div>
            
            <div class="tool-section">
                <h4><?php _e('Configuration Export/Import', 'wpml-lifterlms-compatibility'); ?></h4>
                <p><?php _e('Export or import compatibility settings.', 'wpml-lifterlms-compatibility'); ?></p>
                <button type="button" class="button" id="export-config">
                    <?php _e('Export Configuration', 'wpml-lifterlms-compatibility'); ?>
                </button>
                <input type="file" id="import-config-file" accept=".json" style="display: none;">
                <button type="button" class="button" id="import-config">
                    <?php _e('Import Configuration', 'wpml-lifterlms-compatibility'); ?>
                </button>
            </div>
            
            <div class="tool-section">
                <h4><?php _e('System Status', 'wpml-lifterlms-compatibility'); ?></h4>
                <p><?php _e('Check the compatibility status and system requirements.', 'wpml-lifterlms-compatibility'); ?></p>
                <?php $this->render_system_status(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render system status
     */
    private function render_system_status() {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Component', 'wpml-lifterlms-compatibility'); ?></th>
                    <th><?php _e('Status', 'wpml-lifterlms-compatibility'); ?></th>
                    <th><?php _e('Version', 'wpml-lifterlms-compatibility'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('WPML Multilingual CMS', 'wpml-lifterlms-compatibility'); ?></td>
                    <td>
                        <?php if (defined('ICL_SITEPRESS_VERSION')): ?>
                            <span class="status-enabled">✓ <?php _e('Active', 'wpml-lifterlms-compatibility'); ?></span>
                        <?php else: ?>
                            <span class="status-disabled">✗ <?php _e('Not Found', 'wpml-lifterlms-compatibility'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo defined('ICL_SITEPRESS_VERSION') ? ICL_SITEPRESS_VERSION : 'N/A'; ?></td>
                </tr>
                <tr>
                    <td><?php _e('LifterLMS', 'wpml-lifterlms-compatibility'); ?></td>
                    <td>
                        <?php if (defined('LLMS_VERSION')): ?>
                            <span class="status-enabled">✓ <?php _e('Active', 'wpml-lifterlms-compatibility'); ?></span>
                        <?php else: ?>
                            <span class="status-disabled">✗ <?php _e('Not Found', 'wpml-lifterlms-compatibility'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo defined('LLMS_VERSION') ? LLMS_VERSION : 'N/A'; ?></td>
                </tr>
                <tr>
                    <td><?php _e('WPML String Translation', 'wpml-lifterlms-compatibility'); ?></td>
                    <td>
                        <?php if (defined('WPML_ST_VERSION')): ?>
                            <span class="status-enabled">✓ <?php _e('Active', 'wpml-lifterlms-compatibility'); ?></span>
                        <?php else: ?>
                            <span class="status-warning">⚠ <?php _e('Recommended', 'wpml-lifterlms-compatibility'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo defined('WPML_ST_VERSION') ? WPML_ST_VERSION : 'N/A'; ?></td>
                </tr>
                <tr>
                    <td><?php _e('WPML Multi-Currency', 'wpml-lifterlms-compatibility'); ?></td>
                    <td>
                        <?php if (class_exists('WCML_Multi_Currency')): ?>
                            <span class="status-enabled">✓ <?php _e('Active', 'wpml-lifterlms-compatibility'); ?></span>
                        <?php else: ?>
                            <span class="status-optional">○ <?php _e('Optional', 'wpml-lifterlms-compatibility'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo class_exists('WCML_Multi_Currency') ? 'Available' : 'N/A'; ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure general compatibility settings.', 'wpml-lifterlms-compatibility') . '</p>';
    }
    
    /**
     * Post types section callback
     */
    public function post_types_section_callback() {
        echo '<p>' . __('Configure how LifterLMS post types are handled for translation.', 'wpml-lifterlms-compatibility') . '</p>';
    }
    
    /**
     * E-commerce section callback
     */
    public function ecommerce_section_callback() {
        echo '<p>' . __('Configure e-commerce integration settings.', 'wpml-lifterlms-compatibility') . '</p>';
    }
    
    /**
     * User data section callback
     */
    public function user_data_section_callback() {
        echo '<p>' . __('Configure user data synchronization settings.', 'wpml-lifterlms-compatibility') . '</p>';
    }
    
    /**
     * Checkbox field callback
     * @param array $args
     */
    public function checkbox_field_callback($args) {
        $options = get_option('wpml_lifterlms_options', array());
        $value = isset($options[$args['field']]) ? $options[$args['field']] : $args['default'];
        
        echo '<label>';
        echo '<input type="checkbox" name="wpml_lifterlms_options[' . esc_attr($args['field']) . ']" value="1" ' . checked(1, $value, false) . '>';
        echo isset($args['label']) ? esc_html($args['label']) : '';
        echo '</label>';
    }
    
    /**
     * Handle sync translations AJAX
     */
    public function handle_sync_translations() {
        check_ajax_referer('wpml_llms_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wpml-lifterlms-compatibility'));
        }
        
        // Perform sync operations
        $result = $this->sync_translations();
        
        wp_send_json_success($result);
    }
    
    /**
     * Handle debug course AJAX
     */
    public function handle_debug_course() {
        check_ajax_referer('wpml_llms_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wpml-lifterlms-compatibility'));
        }
        
        $course_id = intval($_POST['course_id']);
        if (!$course_id) {
            wp_send_json_error(__('Invalid course ID', 'wpml-lifterlms-compatibility'));
        }
        
        // Get the relationships instance and run debug
        $relationships = WPML_LifterLMS_Relationships::get_instance();
        $relationships->debug_course_relationships($course_id);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Debug completed for course ID %d. Check your error logs for detailed information.', 'wpml-lifterlms-compatibility'), $course_id)
        ));
    }
    
    /**
     * Handle export config AJAX
     */
    public function handle_export_config() {
        check_ajax_referer('wpml_llms_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wpml-lifterlms-compatibility'));
        }
        
        $config = $this->export_configuration();
        
        wp_send_json_success($config);
    }
    
    /**
     * Handle import config AJAX
     */
    public function handle_import_config() {
        check_ajax_referer('wpml_llms_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wpml-lifterlms-compatibility'));
        }
        
        $config = json_decode(stripslashes($_POST['config']), true);
        $result = $this->import_configuration($config);
        
        wp_send_json_success($result);
    }
    
    /**
     * Sync translations
     * @return array
     */
    private function sync_translations() {
        // Get main plugin instance
        $plugin = WPML_LifterLMS_Compatibility::get_instance();
        
        // Sync all relationships
        $success = $plugin->sync_all_relationships();
        
        if ($success) {
            return array(
                'message' => __('All LifterLMS relationships synced successfully! Sections, lessons, quizzes, and access plans should now appear in translated courses.', 'wpml-lifterlms-compatibility'),
                'synced_items' => 1
            );
        } else {
            return array(
                'message' => __('Failed to sync relationships. Please ensure WPML and LifterLMS are both active.', 'wpml-lifterlms-compatibility'),
                'synced_items' => 0
            );
        }
    }
    
    /**
     * Export configuration
     * @return array
     */
    private function export_configuration() {
        return array(
            'version' => WPML_LLMS_VERSION,
            'settings' => get_option('wpml_lifterlms_options', array()),
            'timestamp' => current_time('timestamp')
        );
    }
    
    /**
     * Import configuration
     * @param array $config
     * @return array
     */
    private function import_configuration($config) {
        if (isset($config['settings'])) {
            update_option('wpml_lifterlms_options', $config['settings']);
        }
        
        return array(
            'message' => __('Configuration imported successfully!', 'wpml-lifterlms-compatibility')
        );
    }
}
