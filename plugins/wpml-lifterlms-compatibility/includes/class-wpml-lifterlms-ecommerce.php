<?php
/**
 * WPML LifterLMS E-commerce Handler
 * 
 * Handles translation and synchronization of LifterLMS e-commerce elements
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * E-commerce Handler Class
 */
class WPML_LifterLMS_Ecommerce {
    
    /**
     * E-commerce configuration
     * @var array
     */
    private $ecommerce_config = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setup_ecommerce_config();
    }
    
    /**
     * Initialize the component
     */
    public function init() {
        add_action('wpml_loaded', array($this, 'setup_wpml_integration'));
        
        // Handle order processing
        add_action('lifterlms_order_status_changed', array($this, 'handle_order_status_change'), 10, 3);
        add_action('llms_user_enrolled_in_course', array($this, 'handle_course_enrollment'), 10, 2);
        add_action('llms_user_removed_from_course', array($this, 'handle_course_unenrollment'), 10, 2);
        
        // Handle pricing and currency
        add_filter('llms_get_product_price', array($this, 'handle_product_pricing'), 10, 3);
        add_filter('llms_get_access_plan_price', array($this, 'handle_access_plan_pricing'), 10, 2);
        
        // Handle checkout process
        add_filter('llms_checkout_redirect_url', array($this, 'handle_checkout_redirect'), 10, 2);
        add_filter('llms_get_checkout_url', array($this, 'handle_checkout_url'), 10, 2);
        
        // Handle coupon functionality
        add_filter('llms_coupon_get_discount_amount', array($this, 'handle_coupon_discount'), 10, 3);
        add_action('llms_coupon_used', array($this, 'handle_coupon_usage'), 10, 3);
        
        // Handle payment gateways
        add_filter('llms_payment_gateways', array($this, 'handle_payment_gateways'));
        add_filter('llms_gateway_title', array($this, 'translate_gateway_title'), 10, 2);
        add_filter('llms_gateway_description', array($this, 'translate_gateway_description'), 10, 2);
        
        // Handle order emails
        add_filter('llms_email_subject', array($this, 'translate_email_subject'), 10, 3);
        add_filter('llms_email_message', array($this, 'translate_email_message'), 10, 3);
    }
    
    /**
     * Setup e-commerce configuration
     */
    private function setup_ecommerce_config() {
        $this->ecommerce_config = array(
            // Currency settings
            'currency' => array(
                'multi_currency' => true,
                'sync_prices' => false,
                'currency_switcher' => true
            ),
            
            // Order settings
            'orders' => array(
                'language_specific' => false,
                'sync_status' => true,
                'translate_notes' => true
            ),
            
            // Access plan settings
            'access_plans' => array(
                'translate_content' => true,
                'sync_pricing' => false,
                'sync_restrictions' => true
            ),
            
            // Coupon settings
            'coupons' => array(
                'language_specific' => true,
                'sync_usage' => true,
                'translate_messages' => true
            ),
            
            // Payment gateway settings
            'gateways' => array(
                'translate_titles' => true,
                'translate_descriptions' => true,
                'sync_settings' => true
            )
        );
        
        // Allow filtering of e-commerce configuration
        $this->ecommerce_config = apply_filters('wpml_lifterlms_ecommerce_config', $this->ecommerce_config);
    }
    
    /**
     * Setup WPML integration
     */
    public function setup_wpml_integration() {
        // Handle multi-currency if enabled
        if ($this->ecommerce_config['currency']['multi_currency']) {
            $this->setup_multi_currency();
        }
        
        // Handle order language detection
        add_filter('wpml_current_language', array($this, 'detect_order_language'), 10, 1);
        
        // Handle checkout language
        add_action('llms_checkout_init', array($this, 'set_checkout_language'));
        
        // Register e-commerce strings for translation
        $this->register_ecommerce_strings();
    }
    
    /**
     * Setup multi-currency support
     */
    private function setup_multi_currency() {
        // Check if WPML Multi-Currency is active
        if (class_exists('WCML_Multi_Currency')) {
            add_filter('llms_format_price', array($this, 'format_price_with_currency'), 10, 2);
            add_filter('llms_get_currency_symbol', array($this, 'get_currency_symbol'));
        }
        
        // Handle currency conversion for prices
        add_filter('llms_price_raw', array($this, 'convert_price_currency'), 10, 2);
    }
    
    /**
     * Register e-commerce strings for translation
     */
    private function register_ecommerce_strings() {
        // Register payment gateway strings
        $gateways = LLMS()->payment_gateways()->get_payment_gateways();
        
        foreach ($gateways as $gateway_id => $gateway) {
            if (method_exists($gateway, 'get_title')) {
                do_action('wpml_register_single_string', 
                    'LifterLMS Payment Gateways', 
                    $gateway_id . '_title', 
                    $gateway->get_title()
                );
            }
            
            if (method_exists($gateway, 'get_description')) {
                do_action('wpml_register_single_string', 
                    'LifterLMS Payment Gateways', 
                    $gateway_id . '_description', 
                    $gateway->get_description()
                );
            }
        }
        
        // Register checkout strings
        $checkout_strings = array(
            'checkout_title' => __('Checkout', 'lifterlms'),
            'order_summary' => __('Order Summary', 'lifterlms'),
            'billing_information' => __('Billing Information', 'lifterlms'),
            'payment_method' => __('Payment Method', 'lifterlms'),
            'place_order' => __('Place Order', 'lifterlms')
        );
        
        foreach ($checkout_strings as $key => $string) {
            do_action('wpml_register_single_string', 
                'LifterLMS Checkout', 
                $key, 
                $string
            );
        }
    }
    
    /**
     * Handle order status change
     * @param LLMS_Order $order
     * @param string $old_status
     * @param string $new_status
     */
    public function handle_order_status_change($order, $old_status, $new_status) {
        if (!$this->ecommerce_config['orders']['sync_status']) {
            return;
        }
        
        // Get order language
        $order_language = $this->get_order_language($order->get('id'));
        
        // Switch to order language for processing
        if ($order_language) {
            do_action('wpml_switch_language', $order_language);
        }
        
        // Handle status-specific actions
        switch ($new_status) {
            case 'llms-completed':
                $this->handle_order_completion($order);
                break;
            case 'llms-cancelled':
                $this->handle_order_cancellation($order);
                break;
            case 'llms-refunded':
                $this->handle_order_refund($order);
                break;
        }
        
        // Switch back to original language
        if ($order_language) {
            do_action('wpml_switch_language', null);
        }
    }
    
    /**
     * Handle course enrollment
     * @param int $user_id
     * @param int $course_id
     */
    public function handle_course_enrollment($user_id, $course_id) {
        // Get user's preferred language
        $user_language = $this->get_user_language($user_id);
        
        // Get course in user's language
        $translated_course_id = apply_filters('wpml_object_id', $course_id, 'course', false, $user_language);
        
        if ($translated_course_id && $translated_course_id !== $course_id) {
            // Ensure enrollment is recorded for the translated course as well
            llms_enroll_student($user_id, $translated_course_id, 'admin');
        }
        
        // Send enrollment email in user's language
        $this->send_enrollment_email($user_id, $course_id, $user_language);
    }
    
    /**
     * Handle course unenrollment
     * @param int $user_id
     * @param int $course_id
     */
    public function handle_course_unenrollment($user_id, $course_id) {
        // Get user's preferred language
        $user_language = $this->get_user_language($user_id);
        
        // Get course in user's language
        $translated_course_id = apply_filters('wpml_object_id', $course_id, 'course', false, $user_language);
        
        if ($translated_course_id && $translated_course_id !== $course_id) {
            // Remove enrollment from translated course as well
            llms_unenroll_student($user_id, $translated_course_id, 'cancelled', 'admin');
        }
    }
    
    /**
     * Handle product pricing
     * @param float $price
     * @param LLMS_Product $product
     * @param string $key
     * @return float
     */
    public function handle_product_pricing($price, $product, $key) {
        if (!$this->ecommerce_config['currency']['multi_currency']) {
            return $price;
        }
        
        // Convert price to current currency
        return $this->convert_price_currency($price, $key);
    }
    
    /**
     * Handle access plan pricing
     * @param float $price
     * @param LLMS_Access_Plan $plan
     * @return float
     */
    public function handle_access_plan_pricing($price, $plan) {
        if (!$this->ecommerce_config['currency']['multi_currency']) {
            return $price;
        }
        
        // Convert price to current currency
        return $this->convert_price_currency($price, 'access_plan');
    }
    
    /**
     * Handle checkout redirect
     * @param string $url
     * @param LLMS_Order $order
     * @return string
     */
    public function handle_checkout_redirect($url, $order) {
        // Get order language
        $order_language = $this->get_order_language($order->get('id'));
        
        if ($order_language) {
            // Translate redirect URL to order language
            $translated_url = apply_filters('wpml_permalink', $url, $order_language);
            return $translated_url ? $translated_url : $url;
        }
        
        return $url;
    }
    
    /**
     * Handle checkout URL
     * @param string $url
     * @param LLMS_Access_Plan $plan
     * @return string
     */
    public function handle_checkout_url($url, $plan) {
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            // Translate checkout URL to current language
            $translated_url = apply_filters('wpml_permalink', $url, $current_language);
            return $translated_url ? $translated_url : $url;
        }
        
        return $url;
    }
    
    /**
     * Handle coupon discount
     * @param float $discount
     * @param LLMS_Coupon $coupon
     * @param float $amount
     * @return float
     */
    public function handle_coupon_discount($discount, $coupon, $amount) {
        if (!$this->ecommerce_config['currency']['multi_currency']) {
            return $discount;
        }
        
        // Convert discount amount to current currency if it's a fixed amount
        if ($coupon->get('discount_type') === 'dollar') {
            return $this->convert_price_currency($discount, 'coupon_discount');
        }
        
        return $discount;
    }
    
    /**
     * Handle coupon usage
     * @param LLMS_Coupon $coupon
     * @param LLMS_Order $order
     * @param int $user_id
     */
    public function handle_coupon_usage($coupon, $order, $user_id) {
        if (!$this->ecommerce_config['coupons']['sync_usage']) {
            return;
        }
        
        // Sync usage count across all language versions of the coupon
        $this->sync_coupon_usage($coupon->get('id'));
    }
    
    /**
     * Handle payment gateways
     * @param array $gateways
     * @return array
     */
    public function handle_payment_gateways($gateways) {
        // Ensure gateways are available in all languages
        foreach ($gateways as $gateway_id => $gateway) {
            if (method_exists($gateway, 'set_title')) {
                $translated_title = $this->translate_gateway_title($gateway->get_title(), $gateway_id);
                $gateway->set_title($translated_title);
            }
            
            if (method_exists($gateway, 'set_description')) {
                $translated_description = $this->translate_gateway_description($gateway->get_description(), $gateway_id);
                $gateway->set_description($translated_description);
            }
        }
        
        return $gateways;
    }
    
    /**
     * Translate gateway title
     * @param string $title
     * @param string $gateway_id
     * @return string
     */
    public function translate_gateway_title($title, $gateway_id) {
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            $translated_title = apply_filters('wpml_translate_single_string', 
                $title, 
                'LifterLMS Payment Gateways', 
                $gateway_id . '_title', 
                $current_language
            );
            
            return $translated_title ? $translated_title : $title;
        }
        
        return $title;
    }
    
    /**
     * Translate gateway description
     * @param string $description
     * @param string $gateway_id
     * @return string
     */
    public function translate_gateway_description($description, $gateway_id) {
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            $translated_description = apply_filters('wpml_translate_single_string', 
                $description, 
                'LifterLMS Payment Gateways', 
                $gateway_id . '_description', 
                $current_language
            );
            
            return $translated_description ? $translated_description : $description;
        }
        
        return $description;
    }
    
    /**
     * Translate email subject
     * @param string $subject
     * @param LLMS_Email $email
     * @param array $data
     * @return string
     */
    public function translate_email_subject($subject, $email, $data) {
        $language = $this->get_email_language($email, $data);
        
        if ($language) {
            $translated_subject = apply_filters('wpml_translate_single_string', 
                $subject, 
                'LifterLMS Emails', 
                $email->get('id') . '_subject', 
                $language
            );
            
            return $translated_subject ? $translated_subject : $subject;
        }
        
        return $subject;
    }
    
    /**
     * Translate email message
     * @param string $message
     * @param LLMS_Email $email
     * @param array $data
     * @return string
     */
    public function translate_email_message($message, $email, $data) {
        $language = $this->get_email_language($email, $data);
        
        if ($language) {
            $translated_message = apply_filters('wpml_translate_single_string', 
                $message, 
                'LifterLMS Emails', 
                $email->get('id') . '_message', 
                $language
            );
            
            return $translated_message ? $translated_message : $message;
        }
        
        return $message;
    }
    
    /**
     * Format price with currency
     * @param string $formatted_price
     * @param float $price
     * @return string
     */
    public function format_price_with_currency($formatted_price, $price) {
        $current_currency = $this->get_current_currency();
        
        if ($current_currency) {
            $symbol = $this->get_currency_symbol($current_currency);
            return $symbol . number_format($price, 2);
        }
        
        return $formatted_price;
    }
    
    /**
     * Get currency symbol
     * @param string $currency
     * @return string
     */
    public function get_currency_symbol($currency = null) {
        if (!$currency) {
            $currency = $this->get_current_currency();
        }
        
        // Use WPML Multi-Currency if available
        if (class_exists('WCML_Multi_Currency')) {
            global $woocommerce_wpml;
            if ($woocommerce_wpml && $woocommerce_wpml->multi_currency) {
                return $woocommerce_wpml->multi_currency->get_currency_symbol($currency);
            }
        }
        
        // Fallback to LifterLMS currency symbol
        return llms_get_currency_symbol($currency);
    }
    
    /**
     * Convert price currency
     * @param float $price
     * @param string $context
     * @return float
     */
    public function convert_price_currency($price, $context = '') {
        $current_currency = $this->get_current_currency();
        $base_currency = get_lifterlms_currency();
        
        if ($current_currency && $current_currency !== $base_currency) {
            // Use WPML Multi-Currency if available
            if (class_exists('WCML_Multi_Currency')) {
                global $woocommerce_wpml;
                if ($woocommerce_wpml && $woocommerce_wpml->multi_currency) {
                    return $woocommerce_wpml->multi_currency->prices->convert_price_amount($price, $current_currency);
                }
            }
            
            // Fallback conversion (you might want to integrate with exchange rate API)
            $conversion_rate = $this->get_currency_conversion_rate($base_currency, $current_currency);
            return $price * $conversion_rate;
        }
        
        return $price;
    }
    
    /**
     * Detect order language
     * @param string $current_language
     * @return string
     */
    public function detect_order_language($current_language) {
        // Check if we're processing an order
        if (isset($_POST['llms_order_id'])) {
            $order_language = $this->get_order_language($_POST['llms_order_id']);
            return $order_language ? $order_language : $current_language;
        }
        
        return $current_language;
    }
    
    /**
     * Set checkout language
     */
    public function set_checkout_language() {
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            // Store checkout language in session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['llms_checkout_language'] = $current_language;
        }
    }
    
    /**
     * Handle order completion
     * @param LLMS_Order $order
     */
    private function handle_order_completion($order) {
        // Send completion email in order language
        $order_language = $this->get_order_language($order->get('id'));
        $user_id = $order->get('user_id');
        
        if ($order_language) {
            do_action('wpml_switch_language', $order_language);
            
            // Trigger completion actions
            do_action('llms_order_completed_multilingual', $order, $order_language);
            
            do_action('wpml_switch_language', null);
        }
    }
    
    /**
     * Handle order cancellation
     * @param LLMS_Order $order
     */
    private function handle_order_cancellation($order) {
        // Handle cancellation in order language
        $order_language = $this->get_order_language($order->get('id'));
        
        if ($order_language) {
            do_action('wpml_switch_language', $order_language);
            
            // Trigger cancellation actions
            do_action('llms_order_cancelled_multilingual', $order, $order_language);
            
            do_action('wpml_switch_language', null);
        }
    }
    
    /**
     * Handle order refund
     * @param LLMS_Order $order
     */
    private function handle_order_refund($order) {
        // Handle refund in order language
        $order_language = $this->get_order_language($order->get('id'));
        
        if ($order_language) {
            do_action('wpml_switch_language', $order_language);
            
            // Trigger refund actions
            do_action('llms_order_refunded_multilingual', $order, $order_language);
            
            do_action('wpml_switch_language', null);
        }
    }
    
    /**
     * Send enrollment email
     * @param int $user_id
     * @param int $course_id
     * @param string $language
     */
    private function send_enrollment_email($user_id, $course_id, $language) {
        if ($language) {
            do_action('wpml_switch_language', $language);
            
            // Send enrollment email
            do_action('llms_user_enrolled_in_course_multilingual', $user_id, $course_id, $language);
            
            do_action('wpml_switch_language', null);
        }
    }
    
    /**
     * Sync coupon usage across languages
     * @param int $coupon_id
     */
    private function sync_coupon_usage($coupon_id) {
        $coupon = new LLMS_Coupon($coupon_id);
        $usage_count = $coupon->get('usage_count');
        
        // Get all translations of the coupon
        $translations = apply_filters('wpml_get_element_translations', null, $coupon_id, 'post_llms_coupon');
        
        if ($translations) {
            foreach ($translations as $translation) {
                if ($translation->element_id != $coupon_id) {
                    update_post_meta($translation->element_id, '_llms_usage_count', $usage_count);
                }
            }
        }
    }
    
    /**
     * Get order language
     * @param int $order_id
     * @return string|null
     */
    private function get_order_language($order_id) {
        return get_post_meta($order_id, '_wpml_language', true);
    }
    
    /**
     * Get user language
     * @param int $user_id
     * @return string|null
     */
    private function get_user_language($user_id) {
        $user_language = get_user_meta($user_id, 'icl_admin_language', true);
        return $user_language ? $user_language : apply_filters('wpml_current_language', null);
    }
    
    /**
     * Get email language
     * @param LLMS_Email $email
     * @param array $data
     * @return string|null
     */
    private function get_email_language($email, $data) {
        // Try to get language from user
        if (isset($data['user_id'])) {
            return $this->get_user_language($data['user_id']);
        }
        
        // Try to get language from order
        if (isset($data['order_id'])) {
            return $this->get_order_language($data['order_id']);
        }
        
        // Fallback to current language
        return apply_filters('wpml_current_language', null);
    }
    
    /**
     * Get current currency
     * @return string
     */
    private function get_current_currency() {
        // Use WPML Multi-Currency if available
        if (class_exists('WCML_Multi_Currency')) {
            global $woocommerce_wpml;
            if ($woocommerce_wpml && $woocommerce_wpml->multi_currency) {
                return $woocommerce_wpml->multi_currency->get_client_currency();
            }
        }
        
        // Fallback to LifterLMS currency
        return get_lifterlms_currency();
    }
    
    /**
     * Get currency conversion rate
     * @param string $from_currency
     * @param string $to_currency
     * @return float
     */
    private function get_currency_conversion_rate($from_currency, $to_currency) {
        // This is a placeholder - you should integrate with a real exchange rate service
        // or use WPML Multi-Currency rates
        
        if (class_exists('WCML_Multi_Currency')) {
            global $woocommerce_wpml;
            if ($woocommerce_wpml && $woocommerce_wpml->multi_currency) {
                $rates = $woocommerce_wpml->multi_currency->get_exchange_rates();
                if (isset($rates[$to_currency])) {
                    return $rates[$to_currency];
                }
            }
        }
        
        // Fallback to 1:1 conversion
        return 1.0;
    }
    
    /**
     * Get configuration
     * @param string $section
     * @return array
     */
    public function get_config($section = null) {
        if ($section) {
            return isset($this->ecommerce_config[$section]) ? $this->ecommerce_config[$section] : array();
        }
        
        return $this->ecommerce_config;
    }
}

