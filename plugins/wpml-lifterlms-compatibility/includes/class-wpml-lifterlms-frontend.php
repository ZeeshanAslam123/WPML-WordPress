<?php
/**
 * WPML LifterLMS Frontend Handler
 * 
 * Handles frontend elements like course catalogs, search, and student dashboards
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Handler Class
 */
class WPML_LifterLMS_Frontend {
    
    /**
     * Initialize the component
     */
    public function init() {
        add_action('wpml_loaded', array($this, 'setup_wpml_integration'));
        
        // Handle course catalog
        add_filter('llms_get_courses', array($this, 'filter_courses_by_language'), 10, 2);
        add_filter('llms_course_catalog_query_args', array($this, 'filter_catalog_query_args'));
        
        // Handle search functionality
        add_filter('llms_search_query_args', array($this, 'filter_search_query_args'));
        add_filter('get_search_query', array($this, 'translate_search_query'));
        
        // Handle navigation and URLs
        add_filter('llms_get_course_url', array($this, 'translate_course_url'), 10, 2);
        add_filter('llms_get_lesson_url', array($this, 'translate_lesson_url'), 10, 2);
        add_filter('llms_get_quiz_url', array($this, 'translate_quiz_url'), 10, 2);
        
        // Handle breadcrumbs
        add_filter('llms_get_breadcrumbs', array($this, 'translate_breadcrumbs'));
        
        // Handle AJAX requests
        add_action('wp_ajax_llms_load_more_courses', array($this, 'handle_ajax_load_more'));
        add_action('wp_ajax_nopriv_llms_load_more_courses', array($this, 'handle_ajax_load_more'));
        
        // Handle shortcodes
        add_filter('llms_shortcode_courses_query_args', array($this, 'filter_shortcode_query_args'));
        add_filter('llms_shortcode_memberships_query_args', array($this, 'filter_shortcode_query_args'));
    }
    
    /**
     * Setup WPML integration
     */
    public function setup_wpml_integration() {
        // Handle language switcher on LifterLMS pages
        add_filter('wpml_ls_language_url', array($this, 'handle_language_switcher_urls'), 10, 2);
        
        // Handle canonical URLs
        add_filter('wpml_canonical_url', array($this, 'handle_canonical_urls'), 10, 2);
        
        // Handle hreflang tags
        add_filter('wpml_hreflang_url', array($this, 'handle_hreflang_urls'), 10, 3);
    }
    
    /**
     * Filter courses by language
     * @param array $courses
     * @param array $args
     * @return array
     */
    public function filter_courses_by_language($courses, $args) {
        $current_language = apply_filters('wpml_current_language', null);
        
        if (!$current_language) {
            return $courses;
        }
        
        $filtered_courses = array();
        
        foreach ($courses as $course) {
            $course_language = apply_filters('wpml_element_language_code', null, array(
                'element_id' => $course->get('id'),
                'element_type' => 'post_course'
            ));
            
            if ($course_language === $current_language) {
                $filtered_courses[] = $course;
            }
        }
        
        return $filtered_courses;
    }
    
    /**
     * Filter catalog query arguments
     * @param array $args
     * @return array
     */
    public function filter_catalog_query_args($args) {
        // Add language filter to catalog queries
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            $args['suppress_filters'] = false;
            add_filter('posts_join', array($this, 'add_language_join'));
            add_filter('posts_where', array($this, 'add_language_where'));
        }
        
        return $args;
    }
    
    /**
     * Filter search query arguments
     * @param array $args
     * @return array
     */
    public function filter_search_query_args($args) {
        // Add language filter to search queries
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            $args['suppress_filters'] = false;
            add_filter('posts_join', array($this, 'add_language_join'));
            add_filter('posts_where', array($this, 'add_language_where'));
        }
        
        return $args;
    }
    
    /**
     * Translate search query
     * @param string $query
     * @return string
     */
    public function translate_search_query($query) {
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language && !empty($query)) {
            // Translate search terms if they match registered strings
            $translated_query = apply_filters('wpml_translate_single_string', 
                $query, 
                'LifterLMS Search', 
                'search_query', 
                $current_language
            );
            
            return $translated_query ? $translated_query : $query;
        }
        
        return $query;
    }
    
    /**
     * Translate course URL
     * @param string $url
     * @param int $course_id
     * @return string
     */
    public function translate_course_url($url, $course_id) {
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            // Get course in current language
            $translated_course_id = apply_filters('wpml_object_id', $course_id, 'course', false, $current_language);
            
            if ($translated_course_id && $translated_course_id !== $course_id) {
                return get_permalink($translated_course_id);
            }
            
            // Translate existing URL
            $translated_url = apply_filters('wpml_permalink', $url, $current_language);
            return $translated_url ? $translated_url : $url;
        }
        
        return $url;
    }
    
    /**
     * Translate lesson URL
     * @param string $url
     * @param int $lesson_id
     * @return string
     */
    public function translate_lesson_url($url, $lesson_id) {
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            // Get lesson in current language
            $translated_lesson_id = apply_filters('wpml_object_id', $lesson_id, 'lesson', false, $current_language);
            
            if ($translated_lesson_id && $translated_lesson_id !== $lesson_id) {
                return get_permalink($translated_lesson_id);
            }
            
            // Translate existing URL
            $translated_url = apply_filters('wpml_permalink', $url, $current_language);
            return $translated_url ? $translated_url : $url;
        }
        
        return $url;
    }
    
    /**
     * Translate quiz URL
     * @param string $url
     * @param int $quiz_id
     * @return string
     */
    public function translate_quiz_url($url, $quiz_id) {
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            // Get quiz in current language
            $translated_quiz_id = apply_filters('wpml_object_id', $quiz_id, 'llms_quiz', false, $current_language);
            
            if ($translated_quiz_id && $translated_quiz_id !== $quiz_id) {
                return get_permalink($translated_quiz_id);
            }
            
            // Translate existing URL
            $translated_url = apply_filters('wpml_permalink', $url, $current_language);
            return $translated_url ? $translated_url : $url;
        }
        
        return $url;
    }
    
    /**
     * Translate breadcrumbs
     * @param array $breadcrumbs
     * @return array
     */
    public function translate_breadcrumbs($breadcrumbs) {
        $current_language = apply_filters('wpml_current_language', null);
        
        if (!$current_language) {
            return $breadcrumbs;
        }
        
        foreach ($breadcrumbs as &$breadcrumb) {
            // Translate breadcrumb title
            if (isset($breadcrumb['title'])) {
                $translated_title = apply_filters('wpml_translate_single_string', 
                    $breadcrumb['title'], 
                    'LifterLMS Breadcrumbs', 
                    'breadcrumb_' . sanitize_key($breadcrumb['title']), 
                    $current_language
                );
                
                if ($translated_title) {
                    $breadcrumb['title'] = $translated_title;
                }
            }
            
            // Translate breadcrumb URL
            if (isset($breadcrumb['url'])) {
                $translated_url = apply_filters('wpml_permalink', $breadcrumb['url'], $current_language);
                if ($translated_url) {
                    $breadcrumb['url'] = $translated_url;
                }
            }
        }
        
        return $breadcrumbs;
    }
    
    /**
     * Handle AJAX load more
     */
    public function handle_ajax_load_more() {
        // Ensure AJAX requests respect current language
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            do_action('wpml_switch_language', $current_language);
        }
        
        // Let LifterLMS handle the actual AJAX request
        // This ensures language context is maintained
    }
    
    /**
     * Filter shortcode query arguments
     * @param array $args
     * @return array
     */
    public function filter_shortcode_query_args($args) {
        // Add language filter to shortcode queries
        $current_language = apply_filters('wpml_current_language', null);
        
        if ($current_language) {
            $args['suppress_filters'] = false;
            add_filter('posts_join', array($this, 'add_language_join'));
            add_filter('posts_where', array($this, 'add_language_where'));
        }
        
        return $args;
    }
    
    /**
     * Handle language switcher URLs
     * @param string $url
     * @param array $data
     * @return string
     */
    public function handle_language_switcher_urls($url, $data) {
        global $post;
        
        if (!$post) {
            return $url;
        }
        
        // Handle LifterLMS post types
        $lifterlms_post_types = array('course', 'lesson', 'llms_quiz', 'llms_membership');
        
        if (in_array($post->post_type, $lifterlms_post_types)) {
            $translated_post_id = apply_filters('wpml_object_id', $post->ID, $post->post_type, false, $data['language_code']);
            
            if ($translated_post_id) {
                return get_permalink($translated_post_id);
            }
        }
        
        return $url;
    }
    
    /**
     * Handle canonical URLs
     * @param string $url
     * @param string $language
     * @return string
     */
    public function handle_canonical_urls($url, $language) {
        global $post;
        
        if (!$post) {
            return $url;
        }
        
        // Handle LifterLMS post types
        $lifterlms_post_types = array('course', 'lesson', 'llms_quiz', 'llms_membership');
        
        if (in_array($post->post_type, $lifterlms_post_types)) {
            $translated_post_id = apply_filters('wpml_object_id', $post->ID, $post->post_type, false, $language);
            
            if ($translated_post_id) {
                return get_permalink($translated_post_id);
            }
        }
        
        return $url;
    }
    
    /**
     * Handle hreflang URLs
     * @param string $url
     * @param string $language
     * @param string $element_type
     * @return string
     */
    public function handle_hreflang_urls($url, $language, $element_type) {
        global $post;
        
        if (!$post) {
            return $url;
        }
        
        // Handle LifterLMS post types
        $lifterlms_post_types = array('post_course', 'post_lesson', 'post_llms_quiz', 'post_llms_membership');
        
        if (in_array($element_type, $lifterlms_post_types)) {
            $post_type = str_replace('post_', '', $element_type);
            $translated_post_id = apply_filters('wpml_object_id', $post->ID, $post_type, false, $language);
            
            if ($translated_post_id) {
                return get_permalink($translated_post_id);
            }
        }
        
        return $url;
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
}

