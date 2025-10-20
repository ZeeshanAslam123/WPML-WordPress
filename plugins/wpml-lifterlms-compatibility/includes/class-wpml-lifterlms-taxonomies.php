<?php
/**
 * WPML LifterLMS Taxonomies Handler
 * 
 * Handles translation registration and management for all LifterLMS taxonomies
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Taxonomies Handler Class
 */
class WPML_LifterLMS_Taxonomies {
    
    /**
     * LifterLMS taxonomies configuration
     * @var array
     */
    private $taxonomies_config = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setup_taxonomies_config();
    }
    
    /**
     * Initialize the component
     */
    public function init() {
        add_action('init', array($this, 'register_taxonomies_for_translation'), 20);
        add_action('wpml_loaded', array($this, 'setup_wpml_integration'));
        
        // Handle taxonomy term synchronization
        add_action('created_term', array($this, 'handle_term_created'), 10, 3);
        add_action('edited_term', array($this, 'handle_term_edited'), 10, 3);
        add_action('delete_term', array($this, 'handle_term_deleted'), 10, 4);
        
        // Handle taxonomy queries
        add_filter('get_terms_args', array($this, 'filter_terms_args'), 10, 2);
        add_filter('terms_clauses', array($this, 'filter_terms_clauses'), 10, 3);
        
        // Handle taxonomy archives
        add_filter('wpml_ls_language_url', array($this, 'handle_taxonomy_archive_urls'), 10, 2);
    }
    
    /**
     * Setup taxonomies configuration
     */
    private function setup_taxonomies_config() {
        $this->taxonomies_config = array(
            // Course taxonomies
            'course_cat' => array(
                'mode' => 'translate',
                'sync_hierarchy' => true,
                'sync_terms' => false,
                'translate_slugs' => true,
                'show_in_menu' => true,
                'priority' => 'high'
            ),
            'course_tag' => array(
                'mode' => 'translate',
                'sync_hierarchy' => false,
                'sync_terms' => false,
                'translate_slugs' => true,
                'show_in_menu' => true,
                'priority' => 'high'
            ),
            'course_difficulty' => array(
                'mode' => 'translate',
                'sync_hierarchy' => false,
                'sync_terms' => true, // Difficulty levels should be consistent
                'translate_slugs' => true,
                'show_in_menu' => true,
                'priority' => 'medium'
            ),
            'course_track' => array(
                'mode' => 'translate',
                'sync_hierarchy' => true,
                'sync_terms' => false,
                'translate_slugs' => true,
                'show_in_menu' => true,
                'priority' => 'medium'
            ),
            
            // Membership taxonomies
            'membership_cat' => array(
                'mode' => 'translate',
                'sync_hierarchy' => true,
                'sync_terms' => false,
                'translate_slugs' => true,
                'show_in_menu' => true,
                'priority' => 'high'
            ),
            'membership_tag' => array(
                'mode' => 'translate',
                'sync_hierarchy' => false,
                'sync_terms' => false,
                'translate_slugs' => true,
                'show_in_menu' => true,
                'priority' => 'high'
            )
        );
        
        // Allow filtering of taxonomies configuration
        $this->taxonomies_config = apply_filters('wpml_lifterlms_taxonomies_config', $this->taxonomies_config);
    }
    
    /**
     * Register taxonomies for translation
     */
    public function register_taxonomies_for_translation() {
        global $sitepress;
        
        if (!$sitepress) {
            return;
        }
        
        foreach ($this->taxonomies_config as $taxonomy => $config) {
            // Register taxonomy for translation
            do_action('wpml_register_single_string', 'WordPress', 'Taxonomy: ' . $taxonomy, $taxonomy);
            
            // Set translation mode
            $sitepress->set_element_language_details(
                0,
                'tax_' . $taxonomy,
                null,
                null,
                $config['mode']
            );
            
            // Configure taxonomy settings
            $this->configure_taxonomy_settings($taxonomy, $config);
        }
        
        // Register taxonomy strings for translation
        $this->register_taxonomy_strings();
    }
    
    /**
     * Configure taxonomy settings
     * @param string $taxonomy
     * @param array $config
     */
    private function configure_taxonomy_settings($taxonomy, $config) {
        // Set taxonomy as translatable
        add_filter('wpml_sub_setting', function($value, $key) use ($taxonomy, $config) {
            if ($key === 'taxonomies_sync_option' && isset($value[$taxonomy])) {
                $value[$taxonomy] = $config['sync_terms'] ? 1 : 0;
            }
            return $value;
        }, 10, 2);
        
        // Handle slug translation
        if ($config['translate_slugs']) {
            add_filter('wpml_get_taxonomy_slug_translation', array($this, 'get_taxonomy_slug_translation'), 10, 3);
        }
    }
    
    /**
     * Setup WPML integration
     */
    public function setup_wpml_integration() {
        // Add taxonomies to WPML configuration
        add_filter('wpml_config_array', array($this, 'add_wpml_config'));
        
        // Handle taxonomy term relationships
        add_filter('wpml_element_type', array($this, 'handle_element_type'), 10, 2);
        add_filter('wpml_element_language_details', array($this, 'handle_element_language_details'), 10, 2);
        
        // Handle taxonomy queries in different languages
        add_action('pre_get_posts', array($this, 'handle_taxonomy_queries'));
        
        // Handle term meta translation
        add_filter('wpml_translate_term_meta', array($this, 'handle_term_meta_translation'), 10, 4);
    }
    
    /**
     * Register taxonomy strings for translation
     */
    private function register_taxonomy_strings() {
        // Get all LifterLMS taxonomy objects
        $taxonomies = get_taxonomies(array(), 'objects');
        
        foreach ($taxonomies as $taxonomy => $taxonomy_obj) {
            if (!$this->is_lifterlms_taxonomy($taxonomy)) {
                continue;
            }
            
            // Register labels for translation
            if (isset($taxonomy_obj->labels)) {
                foreach ($taxonomy_obj->labels as $label_key => $label_value) {
                    if (!empty($label_value)) {
                        do_action('wpml_register_single_string', 
                            'LifterLMS Taxonomies', 
                            $taxonomy . '_' . $label_key, 
                            $label_value
                        );
                    }
                }
            }
            
            // Register taxonomy terms for translation
            $this->register_taxonomy_terms($taxonomy);
        }
    }
    
    /**
     * Register taxonomy terms for translation
     * @param string $taxonomy
     */
    private function register_taxonomy_terms($taxonomy) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'suppress_filters' => true
        ));
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                // Register term name
                do_action('wpml_register_single_string', 
                    'LifterLMS Taxonomy Terms', 
                    $taxonomy . '_term_' . $term->term_id . '_name', 
                    $term->name
                );
                
                // Register term description if exists
                if (!empty($term->description)) {
                    do_action('wpml_register_single_string', 
                        'LifterLMS Taxonomy Terms', 
                        $taxonomy . '_term_' . $term->term_id . '_description', 
                        $term->description
                    );
                }
            }
        }
    }
    
    /**
     * Add WPML configuration
     * @param array $config
     * @return array
     */
    public function add_wpml_config($config) {
        // Add taxonomies configuration
        foreach ($this->taxonomies_config as $taxonomy => $tax_config) {
            $config['wpml-config']['taxonomies']['taxonomy'][] = array(
                'value' => $taxonomy,
                'translate' => $tax_config['mode'] === 'translate' ? '1' : '0'
            );
        }
        
        return $config;
    }
    
    /**
     * Get taxonomy slug translation
     * @param string $slug
     * @param string $taxonomy
     * @param string $language
     * @return string
     */
    public function get_taxonomy_slug_translation($slug, $taxonomy, $language) {
        if (!$this->is_lifterlms_taxonomy($taxonomy)) {
            return $slug;
        }
        
        // Get translated slug from string translation
        $translated_slug = apply_filters('wpml_translate_single_string', 
            $slug, 
            'LifterLMS Taxonomies', 
            $taxonomy . '_slug', 
            $language
        );
        
        return $translated_slug ? $translated_slug : $slug;
    }
    
    /**
     * Handle element type
     * @param string $element_type
     * @param object $element
     * @return string
     */
    public function handle_element_type($element_type, $element) {
        if (is_object($element) && isset($element->taxonomy)) {
            if ($this->is_lifterlms_taxonomy($element->taxonomy)) {
                return 'tax_' . $element->taxonomy;
            }
        }
        
        return $element_type;
    }
    
    /**
     * Handle element language details
     * @param array $details
     * @param array $element
     * @return array
     */
    public function handle_element_language_details($details, $element) {
        if (isset($element['element_type']) && strpos($element['element_type'], 'tax_') === 0) {
            $taxonomy = str_replace('tax_', '', $element['element_type']);
            
            if ($this->is_lifterlms_taxonomy($taxonomy)) {
                $config = $this->get_taxonomy_config($taxonomy);
                if ($config) {
                    $details['translation_mode'] = $config['mode'];
                }
            }
        }
        
        return $details;
    }
    
    /**
     * Handle taxonomy queries
     * @param WP_Query $query
     */
    public function handle_taxonomy_queries($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Handle taxonomy archive pages
        if ($query->is_tax()) {
            $queried_object = get_queried_object();
            if ($queried_object && $this->is_lifterlms_taxonomy($queried_object->taxonomy)) {
                $this->adjust_taxonomy_query($query, $queried_object->taxonomy);
            }
        }
        
        // Handle taxonomy queries in post queries
        $tax_query = $query->get('tax_query');
        if ($tax_query) {
            $this->adjust_tax_query($tax_query);
            $query->set('tax_query', $tax_query);
        }
    }
    
    /**
     * Adjust taxonomy query for current language
     * @param WP_Query $query
     * @param string $taxonomy
     */
    private function adjust_taxonomy_query($query, $taxonomy) {
        // Ensure we're getting posts in the current language
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            // Add language filter to the query
            add_filter('posts_join', array($this, 'add_language_join'));
            add_filter('posts_where', array($this, 'add_language_where'));
        }
    }
    
    /**
     * Adjust tax query for multilingual support
     * @param array $tax_query
     */
    private function adjust_tax_query(&$tax_query) {
        foreach ($tax_query as $key => &$query_part) {
            if (is_array($query_part) && isset($query_part['taxonomy'])) {
                if ($this->is_lifterlms_taxonomy($query_part['taxonomy'])) {
                    // Translate term IDs if needed
                    if (isset($query_part['terms']) && !empty($query_part['terms'])) {
                        $query_part['terms'] = $this->translate_term_ids($query_part['terms'], $query_part['taxonomy']);
                    }
                }
            }
        }
    }
    
    /**
     * Translate term IDs to current language
     * @param array $term_ids
     * @param string $taxonomy
     * @return array
     */
    private function translate_term_ids($term_ids, $taxonomy) {
        $current_language = apply_filters('wpml_current_language', null);
        $translated_ids = array();
        
        foreach ($term_ids as $term_id) {
            $translated_id = apply_filters('wpml_object_id', $term_id, $taxonomy, false, $current_language);
            if ($translated_id) {
                $translated_ids[] = $translated_id;
            }
        }
        
        return $translated_ids;
    }
    
    /**
     * Filter terms arguments
     * @param array $args
     * @param array $taxonomies
     * @return array
     */
    public function filter_terms_args($args, $taxonomies) {
        // Check if any of the taxonomies are LifterLMS taxonomies
        $has_lifterlms_taxonomy = false;
        foreach ($taxonomies as $taxonomy) {
            if ($this->is_lifterlms_taxonomy($taxonomy)) {
                $has_lifterlms_taxonomy = true;
                break;
            }
        }
        
        if ($has_lifterlms_taxonomy) {
            // Add language filter
            $current_language = apply_filters('wpml_current_language', null);
            if ($current_language && !isset($args['lang'])) {
                $args['lang'] = $current_language;
            }
        }
        
        return $args;
    }
    
    /**
     * Filter terms clauses
     * @param array $clauses
     * @param array $taxonomies
     * @param array $args
     * @return array
     */
    public function filter_terms_clauses($clauses, $taxonomies, $args) {
        global $wpdb;
        
        // Check if any of the taxonomies are LifterLMS taxonomies
        $has_lifterlms_taxonomy = false;
        foreach ($taxonomies as $taxonomy) {
            if ($this->is_lifterlms_taxonomy($taxonomy)) {
                $has_lifterlms_taxonomy = true;
                break;
            }
        }
        
        if ($has_lifterlms_taxonomy && isset($args['lang'])) {
            // Add language join and where clauses
            $clauses['join'] .= " LEFT JOIN {$wpdb->prefix}icl_translations icl_t ON icl_t.element_id = t.term_id AND icl_t.element_type = CONCAT('tax_', tt.taxonomy)";
            $clauses['where'] .= $wpdb->prepare(" AND icl_t.language_code = %s", $args['lang']);
        }
        
        return $clauses;
    }
    
    /**
     * Handle taxonomy archive URLs
     * @param string $url
     * @param array $data
     * @return string
     */
    public function handle_taxonomy_archive_urls($url, $data) {
        if (isset($data['taxonomy']) && $this->is_lifterlms_taxonomy($data['taxonomy'])) {
            // Get translated taxonomy archive URL
            $translated_url = apply_filters('wpml_permalink', $url, $data['language']);
            return $translated_url ? $translated_url : $url;
        }
        
        return $url;
    }
    
    /**
     * Handle term created
     * @param int $term_id
     * @param int $tt_id
     * @param string $taxonomy
     */
    public function handle_term_created($term_id, $tt_id, $taxonomy) {
        if (!$this->is_lifterlms_taxonomy($taxonomy)) {
            return;
        }
        
        // Register new term for translation
        $term = get_term($term_id, $taxonomy);
        if ($term && !is_wp_error($term)) {
            do_action('wpml_register_single_string', 
                'LifterLMS Taxonomy Terms', 
                $taxonomy . '_term_' . $term_id . '_name', 
                $term->name
            );
            
            if (!empty($term->description)) {
                do_action('wpml_register_single_string', 
                    'LifterLMS Taxonomy Terms', 
                    $taxonomy . '_term_' . $term_id . '_description', 
                    $term->description
                );
            }
        }
        
        // Handle term synchronization if configured
        $config = $this->get_taxonomy_config($taxonomy);
        if ($config && $config['sync_terms']) {
            $this->sync_term_across_languages($term_id, $taxonomy);
        }
    }
    
    /**
     * Handle term edited
     * @param int $term_id
     * @param int $tt_id
     * @param string $taxonomy
     */
    public function handle_term_edited($term_id, $tt_id, $taxonomy) {
        if (!$this->is_lifterlms_taxonomy($taxonomy)) {
            return;
        }
        
        // Update term strings for translation
        $term = get_term($term_id, $taxonomy);
        if ($term && !is_wp_error($term)) {
            do_action('wpml_register_single_string', 
                'LifterLMS Taxonomy Terms', 
                $taxonomy . '_term_' . $term_id . '_name', 
                $term->name
            );
            
            if (!empty($term->description)) {
                do_action('wpml_register_single_string', 
                    'LifterLMS Taxonomy Terms', 
                    $taxonomy . '_term_' . $term_id . '_description', 
                    $term->description
                );
            }
        }
    }
    
    /**
     * Handle term deleted
     * @param int $term_id
     * @param int $tt_id
     * @param string $taxonomy
     * @param object $deleted_term
     */
    public function handle_term_deleted($term_id, $tt_id, $taxonomy, $deleted_term) {
        if (!$this->is_lifterlms_taxonomy($taxonomy)) {
            return;
        }
        
        // Clean up translation strings
        do_action('wpml_unregister_single_string', 
            'LifterLMS Taxonomy Terms', 
            $taxonomy . '_term_' . $term_id . '_name'
        );
        
        do_action('wpml_unregister_single_string', 
            'LifterLMS Taxonomy Terms', 
            $taxonomy . '_term_' . $term_id . '_description'
        );
    }
    
    /**
     * Handle term meta translation
     * @param mixed $meta_value
     * @param string $meta_key
     * @param int $term_id
     * @param string $language
     * @return mixed
     */
    public function handle_term_meta_translation($meta_value, $meta_key, $term_id, $language) {
        // Handle specific LifterLMS term meta fields
        $translatable_meta = array(
            '_llms_order',
            '_llms_color',
            '_llms_description_extended'
        );
        
        if (in_array($meta_key, $translatable_meta)) {
            $translated_value = apply_filters('wpml_translate_single_string', 
                $meta_value, 
                'LifterLMS Term Meta', 
                $meta_key . '_' . $term_id, 
                $language
            );
            
            return $translated_value ? $translated_value : $meta_value;
        }
        
        return $meta_value;
    }
    
    /**
     * Sync term across languages
     * @param int $term_id
     * @param string $taxonomy
     */
    private function sync_term_across_languages($term_id, $taxonomy) {
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return;
        }
        
        $active_languages = apply_filters('wpml_active_languages', null);
        $current_language = apply_filters('wpml_current_language', null);
        
        foreach ($active_languages as $language_code => $language_data) {
            if ($language_code === $current_language) {
                continue;
            }
            
            // Check if term already exists in this language
            $existing_term_id = apply_filters('wpml_object_id', $term_id, $taxonomy, false, $language_code);
            
            if (!$existing_term_id) {
                // Create term in the target language
                $new_term = wp_insert_term(
                    $term->name,
                    $taxonomy,
                    array(
                        'description' => $term->description,
                        'slug' => $term->slug . '-' . $language_code,
                        'parent' => $term->parent
                    )
                );
                
                if (!is_wp_error($new_term)) {
                    // Set language for the new term
                    do_action('wpml_set_element_language_details', array(
                        'element_id' => $new_term['term_id'],
                        'element_type' => 'tax_' . $taxonomy,
                        'trid' => apply_filters('wpml_element_trid', null, $term_id, 'tax_' . $taxonomy),
                        'language_code' => $language_code
                    ));
                }
            }
        }
    }
    
    /**
     * Add language join to query
     * @param string $join
     * @return string
     */
    public function add_language_join($join) {
        global $wpdb;
        
        $join .= " LEFT JOIN {$wpdb->prefix}icl_translations icl_t ON {$wpdb->posts}.ID = icl_t.element_id";
        
        return $join;
    }
    
    /**
     * Add language where to query
     * @param string $where
     * @return string
     */
    public function add_language_where($where) {
        global $wpdb;
        
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            $where .= $wpdb->prepare(" AND icl_t.language_code = %s", $current_language);
        }
        
        return $where;
    }
    
    /**
     * Check if taxonomy is LifterLMS taxonomy
     * @param string $taxonomy
     * @return bool
     */
    private function is_lifterlms_taxonomy($taxonomy) {
        return isset($this->taxonomies_config[$taxonomy]) || 
               strpos($taxonomy, 'course_') === 0 || 
               strpos($taxonomy, 'membership_') === 0;
    }
    
    /**
     * Get taxonomy configuration
     * @param string $taxonomy
     * @return array|null
     */
    private function get_taxonomy_config($taxonomy) {
        return isset($this->taxonomies_config[$taxonomy]) ? $this->taxonomies_config[$taxonomy] : null;
    }
    
    /**
     * Get all configured taxonomies
     * @return array
     */
    public function get_taxonomies() {
        return array_keys($this->taxonomies_config);
    }
    
    /**
     * Get taxonomy configuration
     * @param string $taxonomy
     * @return array
     */
    public function get_config($taxonomy = null) {
        if ($taxonomy) {
            return $this->get_taxonomy_config($taxonomy);
        }
        
        return $this->taxonomies_config;
    }
}

