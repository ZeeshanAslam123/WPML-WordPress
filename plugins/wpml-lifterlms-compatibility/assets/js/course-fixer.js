/**
 * WPML LifterLMS Course Fixer JavaScript
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    var WpmlLlmsCourseFixer = {
        
        /**
         * Initialize course fixer functionality
         */
        init: function() {
            this.bindEvents();
            this.loadEnglishCourses();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Course selection change
            $('#course-selector').on('change', this.onCourseSelectionChange);
            
            // Fix relationships button
            $('#fix-relationships-btn').on('click', this.fixRelationships);
            
            // Log controls
            $('#clear-logs-btn').on('click', this.clearLogs);
            $('#copy-logs-btn').on('click', this.copyLogs);
        },
        
        /**
         * Load English courses into dropdown
         */
        loadEnglishCourses: function() {
            var $selector = $('#course-selector');
            
            $.ajax({
                url: wpmlLlmsCourseFixer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpml_llms_get_english_courses',
                    nonce: wpmlLlmsCourseFixer.nonce
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        $selector.empty();
                        $selector.append('<option value="">' + wpmlLlmsCourseFixer.strings.selectCourse + '</option>');
                        
                        $.each(response.data, function(index, course) {
                            $selector.append('<option value="' + course.id + '">' + course.display_title + '</option>');
                        });
                    } else {
                        $selector.empty();
                        $selector.append('<option value="">' + wpmlLlmsCourseFixer.strings.noCoursesFound + '</option>');
                    }
                },
                error: function() {
                    $selector.empty();
                    $selector.append('<option value="">Error loading courses</option>');
                    WpmlLlmsCourseFixer.addLog('error', 'Failed to load English courses');
                }
            });
        },
        
        /**
         * Handle course selection change
         */
        onCourseSelectionChange: function() {
            var courseId = $(this).val();
            var $fixBtn = $('#fix-relationships-btn');
            
            if (courseId) {
                $fixBtn.prop('disabled', false);
            } else {
                $fixBtn.prop('disabled', true);
            }
        },
        
        /**
         * Fix relationships for selected course
         */
        fixRelationships: function(e) {
            e.preventDefault();
            
            var courseId = $('#course-selector').val();
            if (!courseId) {
                WpmlLlmsCourseFixer.showNotice(wpmlLlmsCourseFixer.strings.selectCourse, 'error');
                return;
            }
            
            var $button = $(this);
            var $progress = $('#fixer-progress');
            var $progressFill = $('#progress-fill');
            var $progressText = $('#progress-text');
            
            // Clear previous logs
            WpmlLlmsCourseFixer.clearLogs();
            
            // Disable button and show progress
            $button.prop('disabled', true).text(wpmlLlmsCourseFixer.strings.fixing);
            $progress.show();
            $progressText.text('Initializing...');
            
            // Simulate progress animation
            var progress = 0;
            var progressInterval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                
                $progressFill.css('width', progress + '%');
            }, 800);
            
            // Make AJAX request
            $.ajax({
                url: wpmlLlmsCourseFixer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpml_llms_fix_course_relationships',
                    nonce: wpmlLlmsCourseFixer.nonce,
                    course_id: courseId
                },
                success: function(response) {
                    clearInterval(progressInterval);
                    
                    if (response.success) {
                        $progressFill.css('width', '100%');
                        $progressText.text(wpmlLlmsCourseFixer.strings.fixComplete);
                        
                        // Display logs
                        if (response.data.logs && response.data.logs.length > 0) {
                            $.each(response.data.logs, function(index, log) {
                                WpmlLlmsCourseFixer.addLog(log.type, log.message, log.timestamp);
                            });
                        }
                        
                        WpmlLlmsCourseFixer.showNotice(wpmlLlmsCourseFixer.strings.fixComplete, 'success');
                        
                        setTimeout(function() {
                            $progress.hide();
                            $button.prop('disabled', false).text(wpmlLlmsCourseFixer.strings.fixButton);
                        }, 3000);
                    } else {
                        WpmlLlmsCourseFixer.handleError(response.data || wpmlLlmsCourseFixer.strings.fixError);
                        $progress.hide();
                        $button.prop('disabled', false).text(wpmlLlmsCourseFixer.strings.fixButton);
                    }
                },
                error: function(xhr, status, error) {
                    clearInterval(progressInterval);
                    WpmlLlmsCourseFixer.handleError(wpmlLlmsCourseFixer.strings.fixError + ': ' + error);
                    $progress.hide();
                    $button.prop('disabled', false).text(wpmlLlmsCourseFixer.strings.fixButton);
                }
            });
        },
        
        /**
         * Clear logs
         */
        clearLogs: function(e) {
            if (e) e.preventDefault();
            
            var $container = $('#log-container');
            $container.empty();
            $container.append('<div class="log-placeholder">Logs will appear here when you start fixing relationships...</div>');
        },
        
        /**
         * Copy logs to clipboard
         */
        copyLogs: function(e) {
            e.preventDefault();
            
            var $container = $('#log-container');
            var $logEntries = $container.find('.log-entry');
            
            if ($logEntries.length === 0) {
                WpmlLlmsCourseFixer.showNotice('No logs to copy', 'warning');
                return;
            }
            
            var logText = '';
            $logEntries.each(function() {
                var $entry = $(this);
                var timestamp = $entry.find('.log-timestamp').text();
                var type = $entry.find('.log-type').text();
                var message = $entry.find('.log-message').text();
                
                logText += '[' + timestamp + '] ' + type.toUpperCase() + ': ' + message + '\n';
            });
            
            // Copy to clipboard
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(logText).then(function() {
                    WpmlLlmsCourseFixer.showNotice('Logs copied to clipboard', 'success');
                }).catch(function() {
                    WpmlLlmsCourseFixer.fallbackCopyToClipboard(logText);
                });
            } else {
                WpmlLlmsCourseFixer.fallbackCopyToClipboard(logText);
            }
        },
        
        /**
         * Fallback copy to clipboard method
         */
        fallbackCopyToClipboard: function(text) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                WpmlLlmsCourseFixer.showNotice('Logs copied to clipboard', 'success');
            } catch (err) {
                WpmlLlmsCourseFixer.showNotice('Failed to copy logs', 'error');
            }
            
            document.body.removeChild(textArea);
        },
        
        /**
         * Add log entry
         */
        addLog: function(type, message, timestamp) {
            var $container = $('#log-container');
            var $placeholder = $container.find('.log-placeholder');
            
            // Remove placeholder if it exists
            if ($placeholder.length > 0) {
                $placeholder.remove();
            }
            
            // Format timestamp
            if (!timestamp) {
                timestamp = new Date().toLocaleString();
            }
            
            // Create log entry
            var $logEntry = $('<div class="log-entry log-' + type + '"></div>');
            $logEntry.append('<span class="log-timestamp">' + timestamp + '</span>');
            $logEntry.append('<span class="log-type">' + type + '</span>');
            $logEntry.append('<span class="log-message">' + message + '</span>');
            
            $container.append($logEntry);
            
            // Auto-scroll to bottom
            $container.scrollTop($container[0].scrollHeight);
        },
        
        /**
         * Handle error
         */
        handleError: function(message) {
            this.addLog('error', message);
            this.showNotice(message, 'error');
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
            
            // Handle dismiss button
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.wpml-llms-course-fixer').length > 0) {
            WpmlLlmsCourseFixer.init();
        }
    });
    
})(jQuery);

