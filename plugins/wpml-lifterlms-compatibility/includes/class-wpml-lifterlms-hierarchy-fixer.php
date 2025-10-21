<?php
/**
 * WPML LifterLMS Hierarchy Fixer Helper Methods
 * 
 * @package WPML_LifterLMS_Compatibility
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper methods for fixing course hierarchy relationships
 */
trait WPML_LifterLMS_Hierarchy_Fixer {
    
    /**
     * Fix course hierarchy (sections and lessons) with deep logging
     */
    private function fix_course_hierarchy($english_course_id, $target_course_id, $target_lang, &$results) {
        $results[] = '';
        $results[] = '🏗️ FIXING COURSE HIERARCHY';
        $results[] = '=========================';
        
        // Get sections for both courses
        $english_sections = get_posts(array(
            'post_type' => 'section',
            'meta_key' => '_llms_parent_course',
            'meta_value' => $english_course_id,
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
        
        $target_sections = get_posts(array(
            'post_type' => 'section',
            'meta_key' => '_llms_parent_course',
            'meta_value' => $target_course_id,
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
        
        $results[] = '📚 English course sections: ' . count($english_sections);
        $results[] = '📚 ' . strtoupper($target_lang) . ' course sections: ' . count($target_sections);
        
        if (empty($english_sections) && empty($target_sections)) {
            $results[] = '⚠️ No sections found - checking lessons directly attached to courses';
            $this->fix_direct_lessons($english_course_id, $target_course_id, $target_lang, $results);
            return;
        }
        
        // Connect sections as translations
        for ($i = 0; $i < min(count($english_sections), count($target_sections)); $i++) {
            $english_section = $english_sections[$i];
            $target_section = $target_sections[$i];
            
            $results[] = '';
            $results[] = '🔗 SECTION PAIR ' . ($i + 1) . ':';
            $results[] = '  📖 English: "' . $english_section->post_title . '" (ID: ' . $english_section->ID . ')';
            $results[] = '  📖 ' . strtoupper($target_lang) . ': "' . $target_section->post_title . '" (ID: ' . $target_section->ID . ')';
            
            // Set section languages
            do_action('wpml_set_element_language_details', array(
                'element_id' => $english_section->ID,
                'element_type' => 'post_section',
                'language_code' => 'en'
            ));
            
            do_action('wpml_set_element_language_details', array(
                'element_id' => $target_section->ID,
                'element_type' => 'post_section',
                'language_code' => $target_lang
            ));
            
            // Create translation group for sections
            $section_trid = apply_filters('wpml_element_trid', null, $english_section->ID, 'post_section');
            if (!$section_trid) {
                do_action('wpml_set_element_language_details', array(
                    'element_id' => $english_section->ID,
                    'element_type' => 'post_section',
                    'language_code' => 'en',
                    'source_language_code' => null
                ));
                $section_trid = apply_filters('wpml_element_trid', null, $english_section->ID, 'post_section');
            }
            
            do_action('wpml_set_element_language_details', array(
                'element_id' => $target_section->ID,
                'element_type' => 'post_section',
                'language_code' => $target_lang,
                'source_language_code' => 'en',
                'trid' => $section_trid
            ));
            
            $results[] = '  ✅ Connected sections (TRID: ' . $section_trid . ')';
            
            // Now fix lessons within this section
            $this->fix_section_lessons($english_section->ID, $target_section->ID, $target_lang, $results);
        }
    }
    
    /**
     * Fix lessons within a section
     */
    private function fix_section_lessons($english_section_id, $target_section_id, $target_lang, &$results) {
        $results[] = '  🔍 Checking lessons in this section...';
        
        // Get lessons for both sections
        $english_lessons = get_posts(array(
            'post_type' => 'lesson',
            'meta_key' => '_llms_parent_section',
            'meta_value' => $english_section_id,
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
        
        $target_lessons = get_posts(array(
            'post_type' => 'lesson',
            'meta_key' => '_llms_parent_section',
            'meta_value' => $target_section_id,
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
        
        $results[] = '    📝 English lessons: ' . count($english_lessons);
        $results[] = '    📝 ' . strtoupper($target_lang) . ' lessons: ' . count($target_lessons);
        
        if (empty($english_lessons) || empty($target_lessons)) {
            $results[] = '    ⚠️ No lessons to connect in this section';
            return;
        }
        
        // Connect lessons as translations
        for ($j = 0; $j < min(count($english_lessons), count($target_lessons)); $j++) {
            $english_lesson = $english_lessons[$j];
            $target_lesson = $target_lessons[$j];
            
            $results[] = '    🔗 Lesson pair ' . ($j + 1) . ':';
            $results[] = '      📄 English: "' . $english_lesson->post_title . '" (ID: ' . $english_lesson->ID . ')';
            $results[] = '      📄 ' . strtoupper($target_lang) . ': "' . $target_lesson->post_title . '" (ID: ' . $target_lesson->ID . ')';
            
            // Set lesson languages
            do_action('wpml_set_element_language_details', array(
                'element_id' => $english_lesson->ID,
                'element_type' => 'post_lesson',
                'language_code' => 'en'
            ));
            
            do_action('wpml_set_element_language_details', array(
                'element_id' => $target_lesson->ID,
                'element_type' => 'post_lesson',
                'language_code' => $target_lang
            ));
            
            // Create translation group for lessons
            $lesson_trid = apply_filters('wpml_element_trid', null, $english_lesson->ID, 'post_lesson');
            if (!$lesson_trid) {
                do_action('wpml_set_element_language_details', array(
                    'element_id' => $english_lesson->ID,
                    'element_type' => 'post_lesson',
                    'language_code' => 'en',
                    'source_language_code' => null
                ));
                $lesson_trid = apply_filters('wpml_element_trid', null, $english_lesson->ID, 'post_lesson');
            }
            
            do_action('wpml_set_element_language_details', array(
                'element_id' => $target_lesson->ID,
                'element_type' => 'post_lesson',
                'language_code' => $target_lang,
                'source_language_code' => 'en',
                'trid' => $lesson_trid
            ));
            
            $results[] = '      ✅ Connected lessons (TRID: ' . $lesson_trid . ')';
        }
    }
    
    /**
     * Fix lessons directly attached to courses (no sections)
     */
    private function fix_direct_lessons($english_course_id, $target_course_id, $target_lang, &$results) {
        $results[] = '🔍 Checking lessons directly attached to courses...';
        
        // Get lessons directly attached to courses
        $english_lessons = get_posts(array(
            'post_type' => 'lesson',
            'meta_key' => '_llms_parent_course',
            'meta_value' => $english_course_id,
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
        
        $target_lessons = get_posts(array(
            'post_type' => 'lesson',
            'meta_key' => '_llms_parent_course',
            'meta_value' => $target_course_id,
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
        
        $results[] = '📝 English direct lessons: ' . count($english_lessons);
        $results[] = '📝 ' . strtoupper($target_lang) . ' direct lessons: ' . count($target_lessons);
        
        if (empty($english_lessons) || empty($target_lessons)) {
            $results[] = '⚠️ No direct lessons to connect';
            return;
        }
        
        // Connect direct lessons
        for ($i = 0; $i < min(count($english_lessons), count($target_lessons)); $i++) {
            $english_lesson = $english_lessons[$i];
            $target_lesson = $target_lessons[$i];
            
            $results[] = '🔗 Direct lesson pair ' . ($i + 1) . ':';
            $results[] = '  📄 English: "' . $english_lesson->post_title . '" (ID: ' . $english_lesson->ID . ')';
            $results[] = '  📄 ' . strtoupper($target_lang) . ': "' . $target_lesson->post_title . '" (ID: ' . $target_lesson->ID . ')';
            
            // Set lesson languages and connect
            do_action('wpml_set_element_language_details', array(
                'element_id' => $english_lesson->ID,
                'element_type' => 'post_lesson',
                'language_code' => 'en'
            ));
            
            do_action('wpml_set_element_language_details', array(
                'element_id' => $target_lesson->ID,
                'element_type' => 'post_lesson',
                'language_code' => $target_lang
            ));
            
            $lesson_trid = apply_filters('wpml_element_trid', null, $english_lesson->ID, 'post_lesson');
            if (!$lesson_trid) {
                do_action('wpml_set_element_language_details', array(
                    'element_id' => $english_lesson->ID,
                    'element_type' => 'post_lesson',
                    'language_code' => 'en',
                    'source_language_code' => null
                ));
                $lesson_trid = apply_filters('wpml_element_trid', null, $english_lesson->ID, 'post_lesson');
            }
            
            do_action('wpml_set_element_language_details', array(
                'element_id' => $target_lesson->ID,
                'element_type' => 'post_lesson',
                'language_code' => $target_lang,
                'source_language_code' => 'en',
                'trid' => $lesson_trid
            ));
            
            $results[] = '  ✅ Connected direct lessons (TRID: ' . $lesson_trid . ')';
        }
    }
}

