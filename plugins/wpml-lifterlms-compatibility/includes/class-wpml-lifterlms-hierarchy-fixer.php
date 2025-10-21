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
    
    /**
     * Fix database-level relationships based on actual database meta
     */
    private function fix_database_relationships(&$results) {
        global $wpdb;
        
        $results[] = '🔧 FIXING DATABASE-LEVEL RELATIONSHIPS';
        $results[] = 'Based on your provided database meta...';
        
        // Your specific IDs from the database meta
        $english_course_id = 260;
        $english_section_id = 264;
        $english_lesson_id = 266;
        $urdu_section_id = 269;
        $urdu_lesson_id = 274;
        
        // Find the Urdu course ID by checking which course the Urdu lesson belongs to
        $urdu_course_meta = get_post_meta($urdu_lesson_id, '_llms_parent_course', true);
        if (!$urdu_course_meta) {
            // If not found, find it by checking sections
            $urdu_course_id = 261; // Based on your screenshots
            $results[] = '🔍 Urdu course ID determined as: ' . $urdu_course_id;
        } else {
            $urdu_course_id = $urdu_course_meta;
            $results[] = '🔍 Found Urdu course ID: ' . $urdu_course_id;
        }
        
        // Fix 1: Ensure Urdu section has proper parent course relationship
        $results[] = '';
        $results[] = '🔧 FIX 1: SECTION → COURSE RELATIONSHIPS';
        
        $urdu_section_parent = get_post_meta($urdu_section_id, '_llms_parent_course', true);
        if (!$urdu_section_parent || $urdu_section_parent != $urdu_course_id) {
            update_post_meta($urdu_section_id, '_llms_parent_course', $urdu_course_id);
            $results[] = '✅ Fixed: Set Urdu section (ID: ' . $urdu_section_id . ') parent course to: ' . $urdu_course_id;
        } else {
            $results[] = '✅ Already correct: Urdu section parent course = ' . $urdu_section_parent;
        }
        
        // Fix 2: Ensure lessons have proper section and course relationships
        $results[] = '';
        $results[] = '🔧 FIX 2: LESSON → SECTION & COURSE RELATIONSHIPS';
        
        // Check English lesson relationships
        $en_lesson_section = get_post_meta($english_lesson_id, '_llms_parent_section', true);
        $en_lesson_course = get_post_meta($english_lesson_id, '_llms_parent_course', true);
        $results[] = '📝 English lesson (ID: ' . $english_lesson_id . '):';
        $results[] = '  - Parent section: ' . ($en_lesson_section ?: 'MISSING');
        $results[] = '  - Parent course: ' . ($en_lesson_course ?: 'MISSING');
        
        if (!$en_lesson_section || $en_lesson_section != $english_section_id) {
            update_post_meta($english_lesson_id, '_llms_parent_section', $english_section_id);
            $results[] = '  ✅ Fixed: Set English lesson parent section to: ' . $english_section_id;
        }
        
        if (!$en_lesson_course || $en_lesson_course != $english_course_id) {
            update_post_meta($english_lesson_id, '_llms_parent_course', $english_course_id);
            $results[] = '  ✅ Fixed: Set English lesson parent course to: ' . $english_course_id;
        }
        
        // Check Urdu lesson relationships
        $ur_lesson_section = get_post_meta($urdu_lesson_id, '_llms_parent_section', true);
        $ur_lesson_course = get_post_meta($urdu_lesson_id, '_llms_parent_course', true);
        $results[] = '📝 Urdu lesson (ID: ' . $urdu_lesson_id . '):';
        $results[] = '  - Parent section: ' . ($ur_lesson_section ?: 'MISSING');
        $results[] = '  - Parent course: ' . ($ur_lesson_course ?: 'MISSING');
        
        if (!$ur_lesson_section || $ur_lesson_section != $urdu_section_id) {
            update_post_meta($urdu_lesson_id, '_llms_parent_section', $urdu_section_id);
            $results[] = '  ✅ Fixed: Set Urdu lesson parent section to: ' . $urdu_section_id;
        }
        
        if (!$ur_lesson_course || $ur_lesson_course != $urdu_course_id) {
            update_post_meta($urdu_lesson_id, '_llms_parent_course', $urdu_course_id);
            $results[] = '  ✅ Fixed: Set Urdu lesson parent course to: ' . $urdu_course_id;
        }
        
        // Fix 3: Create proper WPML translation relationships in icl_translations table
        $results[] = '';
        $results[] = '🔧 FIX 3: WPML TRANSLATION TABLE RELATIONSHIPS';
        
        $this->fix_wpml_translations_table($english_course_id, $urdu_course_id, 'post_course', $results);
        $this->fix_wpml_translations_table($english_section_id, $urdu_section_id, 'post_section', $results);
        $this->fix_wpml_translations_table($english_lesson_id, $urdu_lesson_id, 'post_lesson', $results);
        
        // Fix 4: Handle quizzes if they exist
        $results[] = '';
        $results[] = '🔧 FIX 4: QUIZ RELATIONSHIPS';
        
        $english_quiz_id = get_post_meta($english_lesson_id, '_llms_quiz', true);
        $urdu_quiz_id = get_post_meta($urdu_lesson_id, '_llms_quiz', true);
        
        if ($english_quiz_id && $urdu_quiz_id) {
            $results[] = '📝 Found quizzes - English: ' . $english_quiz_id . ', Urdu: ' . $urdu_quiz_id;
            $this->fix_wpml_translations_table($english_quiz_id, $urdu_quiz_id, 'post_llms_quiz', $results);
        } else {
            $results[] = '📝 No quizzes found to connect';
        }
    }
    
    /**
     * Fix WPML translations table entries
     */
    private function fix_wpml_translations_table($english_id, $target_id, $element_type, &$results) {
        global $wpdb;
        
        // Check if entries exist in icl_translations
        $english_translation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = %s",
            $english_id, $element_type
        ));
        
        $target_translation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = %s",
            $target_id, $element_type
        ));
        
        $results[] = '🔍 Checking WPML translations for ' . $element_type . ':';
        $results[] = '  - English (ID: ' . $english_id . '): ' . ($english_translation ? 'EXISTS' : 'MISSING');
        $results[] = '  - Target (ID: ' . $target_id . '): ' . ($target_translation ? 'EXISTS' : 'MISSING');
        
        // Get or create TRID
        $trid = null;
        if ($english_translation && $english_translation->trid) {
            $trid = $english_translation->trid;
        } elseif ($target_translation && $target_translation->trid) {
            $trid = $target_translation->trid;
        } else {
            // Create new TRID
            $trid = $wpdb->get_var("SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations") + 1;
            $results[] = '  ✅ Created new TRID: ' . $trid;
        }
        
        // Insert/Update English entry
        if (!$english_translation) {
            $wpdb->insert(
                $wpdb->prefix . 'icl_translations',
                array(
                    'element_type' => $element_type,
                    'element_id' => $english_id,
                    'trid' => $trid,
                    'language_code' => 'en',
                    'source_language_code' => null
                )
            );
            $results[] = '  ✅ Created English translation entry';
        } else {
            $wpdb->update(
                $wpdb->prefix . 'icl_translations',
                array('trid' => $trid, 'language_code' => 'en'),
                array('element_id' => $english_id, 'element_type' => $element_type)
            );
            $results[] = '  ✅ Updated English translation entry';
        }
        
        // Insert/Update target entry
        if (!$target_translation) {
            $wpdb->insert(
                $wpdb->prefix . 'icl_translations',
                array(
                    'element_type' => $element_type,
                    'element_id' => $target_id,
                    'trid' => $trid,
                    'language_code' => 'ur', // Assuming Urdu based on your data
                    'source_language_code' => 'en'
                )
            );
            $results[] = '  ✅ Created target translation entry';
        } else {
            $wpdb->update(
                $wpdb->prefix . 'icl_translations',
                array('trid' => $trid, 'language_code' => 'ur', 'source_language_code' => 'en'),
                array('element_id' => $target_id, 'element_type' => $element_type)
            );
            $results[] = '  ✅ Updated target translation entry';
        }
    }
    
    /**
     * Verify that all relationships are properly fixed
     */
    private function verify_relationships(&$results) {
        global $wpdb;
        
        $results[] = '🔍 VERIFYING ALL RELATIONSHIPS...';
        
        // Check specific IDs from your database
        $english_course_id = 260;
        $english_section_id = 264;
        $english_lesson_id = 266;
        $urdu_section_id = 269;
        $urdu_lesson_id = 274;
        $urdu_course_id = 261;
        
        // Verify LifterLMS relationships
        $results[] = '';
        $results[] = '📋 LIFTERLMS RELATIONSHIPS VERIFICATION:';
        
        $en_section_course = get_post_meta($english_section_id, '_llms_parent_course', true);
        $ur_section_course = get_post_meta($urdu_section_id, '_llms_parent_course', true);
        $results[] = '📚 Section → Course:';
        $results[] = '  - English section ' . $english_section_id . ' → Course ' . $en_section_course . ' (' . ($en_section_course == $english_course_id ? '✅' : '❌') . ')';
        $results[] = '  - Urdu section ' . $urdu_section_id . ' → Course ' . $ur_section_course . ' (' . ($ur_section_course == $urdu_course_id ? '✅' : '❌') . ')';
        
        $en_lesson_section = get_post_meta($english_lesson_id, '_llms_parent_section', true);
        $ur_lesson_section = get_post_meta($urdu_lesson_id, '_llms_parent_section', true);
        $results[] = '📝 Lesson → Section:';
        $results[] = '  - English lesson ' . $english_lesson_id . ' → Section ' . $en_lesson_section . ' (' . ($en_lesson_section == $english_section_id ? '✅' : '❌') . ')';
        $results[] = '  - Urdu lesson ' . $urdu_lesson_id . ' → Section ' . $ur_lesson_section . ' (' . ($ur_lesson_section == $urdu_section_id ? '✅' : '❌') . ')';
        
        // Verify WPML relationships
        $results[] = '';
        $results[] = '📋 WPML RELATIONSHIPS VERIFICATION:';
        
        $course_translations = $wpdb->get_results($wpdb->prepare(
            "SELECT element_id, language_code, trid FROM {$wpdb->prefix}icl_translations WHERE element_id IN (%d, %d) AND element_type = 'post_course'",
            $english_course_id, $urdu_course_id
        ));
        
        $results[] = '📚 Course translations:';
        foreach ($course_translations as $trans) {
            $results[] = '  - Course ' . $trans->element_id . ' → Language: ' . $trans->language_code . ', TRID: ' . $trans->trid;
        }
        
        $section_translations = $wpdb->get_results($wpdb->prepare(
            "SELECT element_id, language_code, trid FROM {$wpdb->prefix}icl_translations WHERE element_id IN (%d, %d) AND element_type = 'post_section'",
            $english_section_id, $urdu_section_id
        ));
        
        $results[] = '📖 Section translations:';
        foreach ($section_translations as $trans) {
            $results[] = '  - Section ' . $trans->element_id . ' → Language: ' . $trans->language_code . ', TRID: ' . $trans->trid;
        }
        
        $lesson_translations = $wpdb->get_results($wpdb->prepare(
            "SELECT element_id, language_code, trid FROM {$wpdb->prefix}icl_translations WHERE element_id IN (%d, %d) AND element_type = 'post_lesson'",
            $english_lesson_id, $urdu_lesson_id
        ));
        
        $results[] = '📝 Lesson translations:';
        foreach ($lesson_translations as $trans) {
            $results[] = '  - Lesson ' . $trans->element_id . ' → Language: ' . $trans->language_code . ', TRID: ' . $trans->trid;
        }
        
        $results[] = '';
        $results[] = '🎉 DATABASE-LEVEL VERIFICATION COMPLETED!';
    }
}
