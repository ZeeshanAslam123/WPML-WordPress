<?php
/**
 * Test Auto-Fixer Functionality
 * 
 * This file tests if our auto-fixer is working properly
 */

// Load WordPress
require_once('../../../wp-load.php');

echo "=== WPML Auto-Fixer Test ===\n";

// Test 1: Check if auto-fixer class exists
if (class_exists('WPML_LLMS_Auto_Course_Fixer')) {
    echo "✅ WPML_LLMS_Auto_Course_Fixer class exists\n";
    
    // Get instance
    $auto_fixer = WPML_LLMS_Auto_Course_Fixer::get_instance();
    
    // Test health check
    $health = $auto_fixer->health_check();
    echo "Health Check Results:\n";
    foreach ($health as $check => $result) {
        echo "  " . ($result ? "✅" : "❌") . " $check\n";
    }
    
    // Test stats
    $stats = $auto_fixer->get_stats();
    echo "Stats:\n";
    print_r($stats);
    
} else {
    echo "❌ WPML_LLMS_Auto_Course_Fixer class not found\n";
}

// Test 2: Check if course fixer class exists
if (class_exists('WPML_LLMS_Course_Fixer')) {
    echo "✅ WPML_LLMS_Course_Fixer class exists\n";
} else {
    echo "❌ WPML_LLMS_Course_Fixer class not found\n";
}

// Test 3: Find problematic lesson
echo "\n=== Finding Problematic Lesson ===\n";

// Look for Urdu lessons
$urdu_lessons = get_posts(array(
    'post_type' => 'lesson',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'meta_query' => array(
        array(
            'key' => 'wpml_language',
            'value' => 'ur',
            'compare' => '='
        )
    )
));

echo "Found " . count($urdu_lessons) . " Urdu lessons:\n";

foreach ($urdu_lessons as $lesson) {
    $parent_course = get_post_meta($lesson->ID, '_llms_parent_course', true);
    $parent_section = get_post_meta($lesson->ID, '_llms_parent_section', true);
    
    echo "Lesson: {$lesson->post_title} (ID: {$lesson->ID})\n";
    echo "  Parent Course: " . ($parent_course ? $parent_course : "MISSING") . "\n";
    echo "  Parent Section: " . ($parent_section ? $parent_section : "MISSING") . "\n";
    
    if (!$parent_course || !$parent_section) {
        echo "  ❌ This lesson has missing relationships!\n";
        
        // Try to find the English version
        $english_lesson_id = apply_filters('wpml_object_id', $lesson->ID, 'lesson', false, 'en');
        if ($english_lesson_id && $english_lesson_id !== $lesson->ID) {
            echo "  English lesson ID: $english_lesson_id\n";
            
            $english_parent_course = get_post_meta($english_lesson_id, '_llms_parent_course', true);
            $english_parent_section = get_post_meta($english_lesson_id, '_llms_parent_section', true);
            
            echo "  English Parent Course: " . ($english_parent_course ? $english_parent_course : "MISSING") . "\n";
            echo "  English Parent Section: " . ($english_parent_section ? $english_parent_section : "MISSING") . "\n";
            
            if ($english_parent_course) {
                // Try to manually fix this lesson
                echo "  Attempting manual fix...\n";
                
                // Find translated course
                $translated_course_id = apply_filters('wpml_object_id', $english_parent_course, 'course', false, 'ur');
                if ($translated_course_id) {
                    update_post_meta($lesson->ID, '_llms_parent_course', $translated_course_id);
                    echo "  ✅ Fixed parent course: $translated_course_id\n";
                }
                
                // Find translated section
                if ($english_parent_section) {
                    $translated_section_id = apply_filters('wpml_object_id', $english_parent_section, 'section', false, 'ur');
                    if ($translated_section_id) {
                        update_post_meta($lesson->ID, '_llms_parent_section', $translated_section_id);
                        echo "  ✅ Fixed parent section: $translated_section_id\n";
                    }
                }
            }
        }
    } else {
        echo "  ✅ This lesson has correct relationships\n";
    }
    echo "\n";
}

echo "=== Test Complete ===\n";
