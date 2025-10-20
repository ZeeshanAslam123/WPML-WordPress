<?php
/**
 * WPML LifterLMS Custom Fields Handler
 * 
 * Handles translation of LifterLMS custom fields and meta data
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Custom Fields Handler Class
 */
class WPML_LifterLMS_Custom_Fields {
    
    /**
     * Custom fields configuration
     * @var array
     */
    private $fields_config = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setup_fields_config();
    }
    
    /**
     * Initialize the component
     */
    public function init() {
        add_action('wpml_loaded', array($this, 'setup_wpml_integration'));
        
        // Handle custom field translation
        add_filter('wpml_custom_field_values_for_post_signature', array($this, 'filter_custom_field_values'), 10, 3);
        add_filter('wpml_duplicate_generic_string', array($this, 'handle_field_duplication'), 10, 3);
        
        // Handle meta field synchronization
        add_action('updated_post_meta', array($this, 'handle_meta_updated'), 10, 4);
        add_action('added_post_meta', array($this, 'handle_meta_added'), 10, 4);
        add_action('deleted_post_meta', array($this, 'handle_meta_deleted'), 10, 4);
        
        // Handle specific LifterLMS field types
        add_filter('wpml_translate_custom_field', array($this, 'translate_custom_field'), 10, 4);
        add_filter('wpml_custom_field_translate_value', array($this, 'translate_field_value'), 10, 4);
    }
    
    /**
     * Setup custom fields configuration
     */
    private function setup_fields_config() {
        $this->fields_config = array(
            // Course fields
            'course' => array(
                // Translatable content fields
                'translate' => array(
                    '_llms_excerpt',
                    '_llms_video_embed',
                    '_llms_audio_embed',
                    '_llms_course_outline',
                    '_llms_course_prerequisites_message',
                    '_llms_course_capacity_message',
                    '_llms_sales_page_content_type',
                    '_llms_sales_page_content_url',
                    '_llms_sales_page_content_page_id'
                ),
                // Copy/sync fields (same across languages)
                'copy' => array(
                    '_llms_length',
                    '_llms_difficulty',
                    '_llms_track',
                    '_llms_course_prerequisites',
                    '_llms_course_capacity',
                    '_llms_enable_capacity',
                    '_llms_start_date',
                    '_llms_end_date',
                    '_llms_time_period',
                    '_llms_course_closed_message',
                    '_llms_enable_course_closed_message'
                ),
                // Don't translate (system fields)
                'ignore' => array(
                    '_llms_order',
                    '_llms_lessons',
                    '_llms_sections',
                    '_llms_course_image',
                    '_llms_course_video',
                    '_llms_course_audio'
                )
            ),
            
            // Lesson fields
            'lesson' => array(
                'translate' => array(
                    '_llms_video_embed',
                    '_llms_audio_embed',
                    '_llms_lesson_prerequisite_message',
                    '_llms_drip_message'
                ),
                'copy' => array(
                    '_llms_parent_course',
                    '_llms_parent_section',
                    '_llms_order',
                    '_llms_lesson_prerequisite',
                    '_llms_require_passing_grade',
                    '_llms_require_assignment_passing_grade',
                    '_llms_points',
                    '_llms_drip_method',
                    '_llms_drip_days',
                    '_llms_drip_date',
                    '_llms_drip_time'
                ),
                'ignore' => array(
                    '_llms_quiz',
                    '_llms_assignment'
                )
            ),
            
            // Quiz fields
            'llms_quiz' => array(
                'translate' => array(
                    '_llms_quiz_description',
                    '_llms_quiz_success_message',
                    '_llms_quiz_failure_message'
                ),
                'copy' => array(
                    '_llms_attempts_allowed',
                    '_llms_limit_attempts',
                    '_llms_time_limit',
                    '_llms_limit_time',
                    '_llms_passing_percent',
                    '_llms_show_correct_answer',
                    '_llms_random_questions',
                    '_llms_show_results',
                    '_llms_show_points'
                ),
                'ignore' => array(
                    '_llms_questions',
                    '_llms_quiz_lesson'
                )
            ),
            
            // Question fields
            'llms_question' => array(
                'translate' => array(
                    '_llms_question_description',
                    '_llms_question_explanation',
                    '_llms_choices',
                    '_llms_correct_answer'
                ),
                'copy' => array(
                    '_llms_question_type',
                    '_llms_points',
                    '_llms_question_order'
                ),
                'ignore' => array(
                    '_llms_parent_quiz'
                )
            ),
            
            // Membership fields
            'llms_membership' => array(
                'translate' => array(
                    '_llms_membership_redirect_page_id',
                    '_llms_restriction_redirect_type',
                    '_llms_restriction_redirect_url',
                    '_llms_restriction_redirect_page_id',
                    '_llms_sales_page_content_type',
                    '_llms_sales_page_content_url',
                    '_llms_sales_page_content_page_id'
                ),
                'copy' => array(
                    '_llms_restriction_add_notice',
                    '_llms_restriction_notice',
                    '_llms_auto_enroll',
                    '_llms_membership_redirect_forced'
                ),
                'ignore' => array(
                    '_llms_instructors',
                    '_llms_product_courses'
                )
            ),
            
            // Certificate fields
            'llms_certificate' => array(
                'translate' => array(
                    '_llms_certificate_title',
                    '_llms_certificate_content',
                    '_llms_certificate_background'
                ),
                'copy' => array(
                    '_llms_certificate_sequential_id',
                    '_llms_certificate_template_id'
                ),
                'ignore' => array()
            ),
            
            // Achievement fields
            'llms_achievement' => array(
                'translate' => array(
                    '_llms_achievement_title',
                    '_llms_achievement_content',
                    '_llms_achievement_image'
                ),
                'copy' => array(
                    '_llms_achievement_template_id'
                ),
                'ignore' => array()
            ),
            
            // Email fields
            'llms_email' => array(
                'translate' => array(
                    '_llms_email_subject',
                    '_llms_email_heading',
                    '_llms_email_message'
                ),
                'copy' => array(
                    '_llms_email_type',
                    '_llms_email_trigger_event'
                ),
                'ignore' => array()
            ),
            
            // Access Plan fields
            'llms_access_plan' => array(
                'translate' => array(
                    '_llms_access_plan_title',
                    '_llms_access_plan_description',
                    '_llms_checkout_redirect_type',
                    '_llms_checkout_redirect_url',
                    '_llms_checkout_redirect_page_id'
                ),
                'copy' => array(
                    '_llms_access_expiration',
                    '_llms_access_expires',
                    '_llms_access_length',
                    '_llms_access_period',
                    '_llms_billing_cycle_number',
                    '_llms_billing_frequency',
                    '_llms_billing_period',
                    '_llms_price',
                    '_llms_sale_price',
                    '_llms_sale_price_dates_from',
                    '_llms_sale_price_dates_to',
                    '_llms_trial_offer',
                    '_llms_trial_length',
                    '_llms_trial_period',
                    '_llms_trial_price',
                    '_llms_availability',
                    '_llms_availability_restrictions',
                    '_llms_enroll_text',
                    '_llms_featured'
                ),
                'ignore' => array(
                    '_llms_product_id',
                    '_llms_order'
                )
            ),
            
            // Coupon fields
            'llms_coupon' => array(
                'translate' => array(
                    '_llms_description',
                    '_llms_usage_limit_message'
                ),
                'copy' => array(
                    '_llms_coupon_amount',
                    '_llms_discount_type',
                    '_llms_expiration_date',
                    '_llms_usage_limit',
                    '_llms_enable_usage_limit'
                ),
                'ignore' => array(
                    '_llms_usage_count',
                    '_llms_product_restrictions'
                )
            ),
            
            // Voucher fields
            'llms_voucher' => array(
                'translate' => array(
                    '_llms_voucher_title',
                    '_llms_voucher_description'
                ),
                'copy' => array(
                    '_llms_voucher_codes',
                    '_llms_voucher_redemption_count'
                ),
                'ignore' => array()
            )
        );
        
        // Allow filtering of fields configuration
        $this->fields_config = apply_filters('wpml_lifterlms_custom_fields_config', $this->fields_config);
    }
    
    /**
     * Setup WPML integration
     */
    public function setup_wpml_integration() {
        // Register custom fields for translation
        $this->register_custom_fields();
        
        // Add custom fields to WPML configuration
        add_filter('wpml_config_array', array($this, 'add_wpml_config'));
        
        // Handle field translation during post duplication
        add_action('wpml_pro_translation_completed', array($this, 'handle_translation_completed'), 10, 3);
    }
    
    /**
     * Register custom fields for translation
     */
    private function register_custom_fields() {
        foreach ($this->fields_config as $post_type => $field_groups) {
            foreach ($field_groups as $action => $fields) {
                foreach ($fields as $field_key) {
                    $this->register_field_for_translation($field_key, $post_type, $action);
                }
            }
        }
    }
    
    /**
     * Register individual field for translation
     * @param string $field_key
     * @param string $post_type
     * @param string $action
     */
    private function register_field_for_translation($field_key, $post_type, $action) {
        switch ($action) {
            case 'translate':
                // Register field for translation
                do_action('wpml_register_single_string', 
                    'LifterLMS Custom Fields', 
                    $post_type . '_' . $field_key, 
                    $field_key
                );
                break;
                
            case 'copy':
                // Register field for copying
                add_filter('wpml_duplicate_generic_string', function($value, $target_lang, $meta_data) use ($field_key) {
                    if (isset($meta_data['key']) && $meta_data['key'] === $field_key) {
                        return $meta_data['value'];
                    }
                    return $value;
                }, 10, 3);
                break;
                
            case 'ignore':
                // Add to ignore list
                add_filter('wpml_copy_from_original_custom_fields', function($fields) use ($field_key) {
                    if (!in_array($field_key, $fields)) {
                        $fields[] = $field_key;
                    }
                    return $fields;
                });
                break;
        }
    }
    
    /**
     * Add WPML configuration
     * @param array $config
     * @return array
     */
    public function add_wpml_config($config) {
        // Add custom fields configuration
        foreach ($this->fields_config as $post_type => $field_groups) {
            foreach ($field_groups['translate'] as $field_key) {
                $config['wpml-config']['custom-fields']['custom-field'][] = array(
                    'value' => $field_key,
                    'translate' => '1'
                );
            }
            
            foreach ($field_groups['copy'] as $field_key) {
                $config['wpml-config']['custom-fields']['custom-field'][] = array(
                    'value' => $field_key,
                    'translate' => '0',
                    'copy' => '1'
                );
            }
            
            foreach ($field_groups['ignore'] as $field_key) {
                $config['wpml-config']['custom-fields']['custom-field'][] = array(
                    'value' => $field_key,
                    'translate' => '0'
                );
            }
        }
        
        return $config;
    }
    
    /**
     * Filter custom field values for post signature
     * @param array $values
     * @param int $post_id
     * @param string $post_type
     * @return array
     */
    public function filter_custom_field_values($values, $post_id, $post_type) {
        if (!isset($this->fields_config[$post_type])) {
            return $values;
        }
        
        $config = $this->fields_config[$post_type];
        
        // Only include translatable fields in signature
        foreach ($values as $key => $value) {
            if (!in_array($key, $config['translate'])) {
                unset($values[$key]);
            }
        }
        
        return $values;
    }
    
    /**
     * Handle field duplication
     * @param mixed $value
     * @param string $target_lang
     * @param array $meta_data
     * @return mixed
     */
    public function handle_field_duplication($value, $target_lang, $meta_data) {
        if (!isset($meta_data['key'])) {
            return $value;
        }
        
        $field_key = $meta_data['key'];
        $post_type = get_post_type($meta_data['post_id']);
        
        if (!isset($this->fields_config[$post_type])) {
            return $value;
        }
        
        $config = $this->fields_config[$post_type];
        
        // Handle different field types
        if (in_array($field_key, $config['translate'])) {
            return $this->translate_field_content($value, $field_key, $target_lang);
        } elseif (in_array($field_key, $config['copy'])) {
            return $meta_data['value'];
        }
        
        return $value;
    }
    
    /**
     * Translate field content
     * @param mixed $value
     * @param string $field_key
     * @param string $target_lang
     * @return mixed
     */
    private function translate_field_content($value, $field_key, $target_lang) {
        // Handle different field types
        if (is_array($value)) {
            return $this->translate_array_field($value, $field_key, $target_lang);
        } elseif (is_string($value)) {
            return $this->translate_string_field($value, $field_key, $target_lang);
        }
        
        return $value;
    }
    
    /**
     * Translate array field
     * @param array $value
     * @param string $field_key
     * @param string $target_lang
     * @return array
     */
    private function translate_array_field($value, $field_key, $target_lang) {
        $translated_value = array();
        
        foreach ($value as $key => $item) {
            if (is_string($item)) {
                $translated_value[$key] = apply_filters('wpml_translate_single_string', 
                    $item, 
                    'LifterLMS Custom Fields', 
                    $field_key . '_' . $key, 
                    $target_lang
                );
            } elseif (is_array($item)) {
                $translated_value[$key] = $this->translate_array_field($item, $field_key . '_' . $key, $target_lang);
            } else {
                $translated_value[$key] = $item;
            }
        }
        
        return $translated_value;
    }
    
    /**
     * Translate string field
     * @param string $value
     * @param string $field_key
     * @param string $target_lang
     * @return string
     */
    private function translate_string_field($value, $field_key, $target_lang) {
        // Handle special field types
        if ($this->is_page_id_field($field_key)) {
            return $this->translate_page_id($value, $target_lang);
        } elseif ($this->is_url_field($field_key)) {
            return $this->translate_url($value, $target_lang);
        } else {
            return apply_filters('wpml_translate_single_string', 
                $value, 
                'LifterLMS Custom Fields', 
                $field_key, 
                $target_lang
            );
        }
    }
    
    /**
     * Check if field is a page ID field
     * @param string $field_key
     * @return bool
     */
    private function is_page_id_field($field_key) {
        $page_id_fields = array(
            '_llms_sales_page_content_page_id',
            '_llms_membership_redirect_page_id',
            '_llms_restriction_redirect_page_id',
            '_llms_checkout_redirect_page_id'
        );
        
        return in_array($field_key, $page_id_fields);
    }
    
    /**
     * Check if field is a URL field
     * @param string $field_key
     * @return bool
     */
    private function is_url_field($field_key) {
        $url_fields = array(
            '_llms_sales_page_content_url',
            '_llms_restriction_redirect_url',
            '_llms_checkout_redirect_url'
        );
        
        return in_array($field_key, $url_fields);
    }
    
    /**
     * Translate page ID
     * @param int $page_id
     * @param string $target_lang
     * @return int
     */
    private function translate_page_id($page_id, $target_lang) {
        if (empty($page_id)) {
            return $page_id;
        }
        
        $translated_id = apply_filters('wpml_object_id', $page_id, 'page', false, $target_lang);
        return $translated_id ? $translated_id : $page_id;
    }
    
    /**
     * Translate URL
     * @param string $url
     * @param string $target_lang
     * @return string
     */
    private function translate_url($url, $target_lang) {
        if (empty($url)) {
            return $url;
        }
        
        // Check if it's an internal URL
        if (strpos($url, home_url()) === 0) {
            return apply_filters('wpml_permalink', $url, $target_lang);
        }
        
        return $url;
    }
    
    /**
     * Handle meta updated
     * @param int $meta_id
     * @param int $post_id
     * @param string $meta_key
     * @param mixed $meta_value
     */
    public function handle_meta_updated($meta_id, $post_id, $meta_key, $meta_value) {
        $post_type = get_post_type($post_id);
        
        if (!isset($this->fields_config[$post_type])) {
            return;
        }
        
        $config = $this->fields_config[$post_type];
        
        // Handle translatable fields
        if (in_array($meta_key, $config['translate'])) {
            $this->sync_translatable_field($post_id, $meta_key, $meta_value);
        }
        
        // Handle copy fields
        if (in_array($meta_key, $config['copy'])) {
            $this->sync_copy_field($post_id, $meta_key, $meta_value);
        }
    }
    
    /**
     * Handle meta added
     * @param int $meta_id
     * @param int $post_id
     * @param string $meta_key
     * @param mixed $meta_value
     */
    public function handle_meta_added($meta_id, $post_id, $meta_key, $meta_value) {
        $this->handle_meta_updated($meta_id, $post_id, $meta_key, $meta_value);
    }
    
    /**
     * Handle meta deleted
     * @param array $meta_ids
     * @param int $post_id
     * @param string $meta_key
     * @param mixed $meta_value
     */
    public function handle_meta_deleted($meta_ids, $post_id, $meta_key, $meta_value) {
        $post_type = get_post_type($post_id);
        
        if (!isset($this->fields_config[$post_type])) {
            return;
        }
        
        $config = $this->fields_config[$post_type];
        
        // Handle copy fields - delete from translations too
        if (in_array($meta_key, $config['copy'])) {
            $this->delete_from_translations($post_id, $meta_key);
        }
    }
    
    /**
     * Sync translatable field
     * @param int $post_id
     * @param string $meta_key
     * @param mixed $meta_value
     */
    private function sync_translatable_field($post_id, $meta_key, $meta_value) {
        // Register/update string for translation
        do_action('wpml_register_single_string', 
            'LifterLMS Custom Fields', 
            $meta_key . '_' . $post_id, 
            $meta_value
        );
    }
    
    /**
     * Sync copy field
     * @param int $post_id
     * @param string $meta_key
     * @param mixed $meta_value
     */
    private function sync_copy_field($post_id, $meta_key, $meta_value) {
        // Get all translations of this post
        $translations = apply_filters('wpml_get_element_translations', null, $post_id, 'post_' . get_post_type($post_id));
        
        if ($translations) {
            foreach ($translations as $translation) {
                if ($translation->element_id != $post_id) {
                    update_post_meta($translation->element_id, $meta_key, $meta_value);
                }
            }
        }
    }
    
    /**
     * Delete from translations
     * @param int $post_id
     * @param string $meta_key
     */
    private function delete_from_translations($post_id, $meta_key) {
        // Get all translations of this post
        $translations = apply_filters('wpml_get_element_translations', null, $post_id, 'post_' . get_post_type($post_id));
        
        if ($translations) {
            foreach ($translations as $translation) {
                if ($translation->element_id != $post_id) {
                    delete_post_meta($translation->element_id, $meta_key);
                }
            }
        }
    }
    
    /**
     * Translate custom field
     * @param mixed $value
     * @param string $meta_key
     * @param int $post_id
     * @param string $language
     * @return mixed
     */
    public function translate_custom_field($value, $meta_key, $post_id, $language) {
        $post_type = get_post_type($post_id);
        
        if (!isset($this->fields_config[$post_type])) {
            return $value;
        }
        
        $config = $this->fields_config[$post_type];
        
        if (in_array($meta_key, $config['translate'])) {
            return $this->translate_field_content($value, $meta_key, $language);
        }
        
        return $value;
    }
    
    /**
     * Translate field value
     * @param mixed $value
     * @param string $meta_key
     * @param int $post_id
     * @param string $language
     * @return mixed
     */
    public function translate_field_value($value, $meta_key, $post_id, $language) {
        return $this->translate_custom_field($value, $meta_key, $post_id, $language);
    }
    
    /**
     * Handle translation completed
     * @param int $new_post_id
     * @param array $fields
     * @param object $job
     */
    public function handle_translation_completed($new_post_id, $fields, $job) {
        $original_post_id = $job->original_doc_id;
        $post_type = get_post_type($original_post_id);
        
        if (!isset($this->fields_config[$post_type])) {
            return;
        }
        
        // Sync copy fields
        $this->sync_copy_fields_on_translation($original_post_id, $new_post_id, $post_type);
        
        // Handle special field relationships
        $this->handle_field_relationships($original_post_id, $new_post_id, $post_type);
    }
    
    /**
     * Sync copy fields on translation
     * @param int $original_post_id
     * @param int $new_post_id
     * @param string $post_type
     */
    private function sync_copy_fields_on_translation($original_post_id, $new_post_id, $post_type) {
        $config = $this->fields_config[$post_type];
        
        foreach ($config['copy'] as $field_key) {
            $value = get_post_meta($original_post_id, $field_key, true);
            if ($value !== '') {
                update_post_meta($new_post_id, $field_key, $value);
            }
        }
    }
    
    /**
     * Handle field relationships
     * @param int $original_post_id
     * @param int $new_post_id
     * @param string $post_type
     */
    private function handle_field_relationships($original_post_id, $new_post_id, $post_type) {
        // Handle post relationships in custom fields
        $relationship_fields = $this->get_relationship_fields($post_type);
        
        foreach ($relationship_fields as $field_key) {
            $this->translate_relationship_field($original_post_id, $new_post_id, $field_key);
        }
    }
    
    /**
     * Get relationship fields for post type
     * @param string $post_type
     * @return array
     */
    private function get_relationship_fields($post_type) {
        $relationship_fields = array();
        
        switch ($post_type) {
            case 'course':
                $relationship_fields = array('_llms_lessons', '_llms_sections');
                break;
            case 'lesson':
                $relationship_fields = array('_llms_parent_course', '_llms_parent_section', '_llms_quiz');
                break;
            case 'llms_quiz':
                $relationship_fields = array('_llms_questions');
                break;
        }
        
        return $relationship_fields;
    }
    
    /**
     * Translate relationship field
     * @param int $original_post_id
     * @param int $new_post_id
     * @param string $field_key
     */
    private function translate_relationship_field($original_post_id, $new_post_id, $field_key) {
        $value = get_post_meta($original_post_id, $field_key, true);
        
        if (empty($value)) {
            return;
        }
        
        $target_language = apply_filters('wpml_element_language_code', null, array('element_id' => $new_post_id, 'element_type' => 'post_' . get_post_type($new_post_id)));
        
        if (is_array($value)) {
            $translated_value = array();
            foreach ($value as $item) {
                if (is_numeric($item)) {
                    $translated_id = apply_filters('wpml_object_id', $item, get_post_type($item), false, $target_language);
                    if ($translated_id) {
                        $translated_value[] = $translated_id;
                    }
                } else {
                    $translated_value[] = $item;
                }
            }
            update_post_meta($new_post_id, $field_key, $translated_value);
        } elseif (is_numeric($value)) {
            $translated_id = apply_filters('wpml_object_id', $value, get_post_type($value), false, $target_language);
            if ($translated_id) {
                update_post_meta($new_post_id, $field_key, $translated_id);
            }
        }
    }
    
    /**
     * Get fields configuration
     * @param string $post_type
     * @return array
     */
    public function get_config($post_type = null) {
        if ($post_type) {
            return isset($this->fields_config[$post_type]) ? $this->fields_config[$post_type] : array();
        }
        
        return $this->fields_config;
    }
}

