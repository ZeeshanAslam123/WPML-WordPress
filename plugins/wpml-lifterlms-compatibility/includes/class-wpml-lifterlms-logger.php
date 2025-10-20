<?php
/**
 * WPML LifterLMS Logger
 * 
 * Comprehensive error handling, logging system, and debugging tools
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger Class
 */
class WPML_LifterLMS_Logger {
    
    /**
     * Log levels
     */
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';
    
    /**
     * Log file path
     * @var string
     */
    private $log_file;
    
    /**
     * Maximum log file size (in bytes)
     * @var int
     */
    private $max_file_size = 10485760; // 10MB
    
    /**
     * Whether logging is enabled
     * @var bool
     */
    private $logging_enabled = true;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->log_file = WP_CONTENT_DIR . '/uploads/wpml-lifterlms-compatibility.log';
        $this->logging_enabled = defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * Initialize the component
     */
    public function init() {
        // Set up error handling
        add_action('wpml_lifterlms_log', array($this, 'log'), 10, 3);
        
        // Handle WordPress errors
        add_action('wp_die_handler', array($this, 'handle_wp_die'));
        
        // Handle PHP errors if debug mode is enabled
        if ($this->logging_enabled) {
            set_error_handler(array($this, 'handle_php_error'));
            set_exception_handler(array($this, 'handle_exception'));
        }
        
        // Schedule log cleanup
        $this->schedule_log_cleanup();
    }
    
    /**
     * Log a message
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, $context = array()) {
        if (!$this->logging_enabled) {
            return;
        }
        
        $this->write_log($level, $message, $context);
    }
    
    /**
     * Log emergency message
     * @param string $message
     * @param array $context
     */
    public function emergency($message, $context = array()) {
        $this->log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Log alert message
     * @param string $message
     * @param array $context
     */
    public function alert($message, $context = array()) {
        $this->log(self::ALERT, $message, $context);
    }
    
    /**
     * Log critical message
     * @param string $message
     * @param array $context
     */
    public function critical($message, $context = array()) {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Log error message
     * @param string $message
     * @param array $context
     */
    public function error($message, $context = array()) {
        $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Log warning message
     * @param string $message
     * @param array $context
     */
    public function warning($message, $context = array()) {
        $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Log notice message
     * @param string $message
     * @param array $context
     */
    public function notice($message, $context = array()) {
        $this->log(self::NOTICE, $message, $context);
    }
    
    /**
     * Log info message
     * @param string $message
     * @param array $context
     */
    public function info($message, $context = array()) {
        $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Log debug message
     * @param string $message
     * @param array $context
     */
    public function debug($message, $context = array()) {
        $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Write log entry
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function write_log($level, $message, $context = array()) {
        // Check file size and rotate if necessary
        $this->rotate_log_if_needed();
        
        // Format log entry
        $log_entry = $this->format_log_entry($level, $message, $context);
        
        // Write to file
        error_log($log_entry, 3, $this->log_file);
        
        // Also log to WordPress debug log if available
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[WPML-LifterLMS] ' . $log_entry);
        }
    }
    
    /**
     * Format log entry
     * @param string $level
     * @param string $message
     * @param array $context
     * @return string
     */
    private function format_log_entry($level, $message, $context = array()) {
        $timestamp = current_time('Y-m-d H:i:s');
        $level = strtoupper($level);
        
        // Add context information
        $context_info = '';
        if (!empty($context)) {
            $context_info = ' | Context: ' . json_encode($context);
        }
        
        // Add request information
        $request_info = $this->get_request_info();
        
        return sprintf(
            "[%s] %s: %s%s%s\n",
            $timestamp,
            $level,
            $message,
            $context_info,
            $request_info
        );
    }
    
    /**
     * Get request information
     * @return string
     */
    private function get_request_info() {
        $info = array();
        
        // Add current user
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $info[] = 'User: ' . $user->user_login . ' (ID: ' . $user->ID . ')';
        }
        
        // Add current URL
        if (isset($_SERVER['REQUEST_URI'])) {
            $info[] = 'URL: ' . $_SERVER['REQUEST_URI'];
        }
        
        // Add current language
        $current_language = apply_filters('wpml_current_language', null);
        if ($current_language) {
            $info[] = 'Language: ' . $current_language;
        }
        
        // Add memory usage
        $info[] = 'Memory: ' . size_format(memory_get_usage(true));
        
        return !empty($info) ? ' | ' . implode(' | ', $info) : '';
    }
    
    /**
     * Rotate log file if needed
     */
    private function rotate_log_if_needed() {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        if (filesize($this->log_file) > $this->max_file_size) {
            $backup_file = $this->log_file . '.old';
            
            // Remove old backup
            if (file_exists($backup_file)) {
                unlink($backup_file);
            }
            
            // Rename current log to backup
            rename($this->log_file, $backup_file);
        }
    }
    
    /**
     * Handle WordPress die
     * @param callable $handler
     * @return callable
     */
    public function handle_wp_die($handler) {
        // Log wp_die calls
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $this->error('wp_die called', array('backtrace' => $backtrace));
        
        return $handler;
    }
    
    /**
     * Handle PHP errors
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return bool
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline) {
        // Only log errors from our plugin
        if (strpos($errfile, 'wpml-lifterlms-compatibility') === false) {
            return false;
        }
        
        $error_types = array(
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED'
        );
        
        $error_type = isset($error_types[$errno]) ? $error_types[$errno] : 'UNKNOWN';
        
        $this->error(
            sprintf('PHP %s: %s in %s on line %d', $error_type, $errstr, $errfile, $errline)
        );
        
        return false; // Don't prevent default error handling
    }
    
    /**
     * Handle exceptions
     * @param Exception $exception
     */
    public function handle_exception($exception) {
        $this->critical(
            sprintf(
                'Uncaught exception: %s in %s on line %d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ),
            array(
                'trace' => $exception->getTraceAsString()
            )
        );
    }
    
    /**
     * Get log entries
     * @param int $lines
     * @param string $level
     * @return array
     */
    public function get_log_entries($lines = 100, $level = null) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $log_content = file_get_contents($this->log_file);
        $log_lines = explode("\n", $log_content);
        $log_lines = array_filter($log_lines); // Remove empty lines
        
        // Get last N lines
        $log_lines = array_slice($log_lines, -$lines);
        
        $entries = array();
        
        foreach ($log_lines as $line) {
            $entry = $this->parse_log_line($line);
            
            if ($entry && ($level === null || $entry['level'] === strtoupper($level))) {
                $entries[] = $entry;
            }
        }
        
        return array_reverse($entries); // Most recent first
    }
    
    /**
     * Parse log line
     * @param string $line
     * @return array|null
     */
    private function parse_log_line($line) {
        // Parse log line format: [timestamp] LEVEL: message | context | request_info
        if (preg_match('/^\[([^\]]+)\] ([A-Z]+): (.+)$/', $line, $matches)) {
            $parts = explode(' | ', $matches[3]);
            
            return array(
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'message' => $parts[0],
                'context' => isset($parts[1]) ? $parts[1] : '',
                'request_info' => isset($parts[2]) ? $parts[2] : ''
            );
        }
        
        return null;
    }
    
    /**
     * Clear log file
     */
    public function clear_log() {
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }
        
        $backup_file = $this->log_file . '.old';
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
    }
    
    /**
     * Get log file size
     * @return int
     */
    public function get_log_file_size() {
        return file_exists($this->log_file) ? filesize($this->log_file) : 0;
    }
    
    /**
     * Get log statistics
     * @return array
     */
    public function get_log_stats() {
        $entries = $this->get_log_entries(1000); // Get last 1000 entries
        
        $stats = array(
            'total_entries' => count($entries),
            'levels' => array(),
            'recent_errors' => 0,
            'file_size' => $this->get_log_file_size()
        );
        
        $recent_time = strtotime('-1 hour');
        
        foreach ($entries as $entry) {
            // Count by level
            $level = $entry['level'];
            if (!isset($stats['levels'][$level])) {
                $stats['levels'][$level] = 0;
            }
            $stats['levels'][$level]++;
            
            // Count recent errors
            $entry_time = strtotime($entry['timestamp']);
            if ($entry_time > $recent_time && in_array($level, array('ERROR', 'CRITICAL', 'EMERGENCY'))) {
                $stats['recent_errors']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Schedule log cleanup
     */
    private function schedule_log_cleanup() {
        if (!wp_next_scheduled('wpml_lifterlms_log_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'wpml_lifterlms_log_cleanup');
        }
        
        add_action('wpml_lifterlms_log_cleanup', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs() {
        $backup_file = $this->log_file . '.old';
        
        // Remove old backup files older than 30 days
        if (file_exists($backup_file) && filemtime($backup_file) < (time() - 30 * DAY_IN_SECONDS)) {
            unlink($backup_file);
        }
        
        // Rotate current log if it's too large
        $this->rotate_log_if_needed();
    }
    
    /**
     * Enable logging
     */
    public function enable_logging() {
        $this->logging_enabled = true;
    }
    
    /**
     * Disable logging
     */
    public function disable_logging() {
        $this->logging_enabled = false;
    }
    
    /**
     * Check if logging is enabled
     * @return bool
     */
    public function is_logging_enabled() {
        return $this->logging_enabled;
    }
    
    /**
     * Set log level threshold
     * @param string $level
     */
    public function set_log_level($level) {
        // Implementation for setting minimum log level
        // This would filter out messages below the threshold
    }
    
    /**
     * Export logs
     * @param string $format
     * @return string
     */
    public function export_logs($format = 'txt') {
        $entries = $this->get_log_entries(1000);
        
        switch ($format) {
            case 'json':
                return json_encode($entries, JSON_PRETTY_PRINT);
                
            case 'csv':
                $csv = "Timestamp,Level,Message,Context,Request Info\n";
                foreach ($entries as $entry) {
                    $csv .= sprintf(
                        '"%s","%s","%s","%s","%s"' . "\n",
                        $entry['timestamp'],
                        $entry['level'],
                        str_replace('"', '""', $entry['message']),
                        str_replace('"', '""', $entry['context']),
                        str_replace('"', '""', $entry['request_info'])
                    );
                }
                return $csv;
                
            default:
                $txt = '';
                foreach ($entries as $entry) {
                    $txt .= sprintf(
                        "[%s] %s: %s %s %s\n",
                        $entry['timestamp'],
                        $entry['level'],
                        $entry['message'],
                        $entry['context'],
                        $entry['request_info']
                    );
                }
                return $txt;
        }
    }
}

