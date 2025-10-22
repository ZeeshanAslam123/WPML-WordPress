/**
 * WPML LifterLMS Admin JavaScript
 * 
 * Handles frontend interactions for the course fixer interface
 * 
 * @package TwentyTwentyFive_Child
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    // Main application object
    var WpmlLlmsAdmin = {
        
        // Initialize the application
        init: function() {
            this.bindEvents();
            this.initializeInterface();
        },
        
        // Bind event handlers
        bindEvents: function() {
            $('#course-selector').on('change', this.onCourseSelect);
            $('#fix-relationships-btn').on('click', this.onFixRelationships);
        },
        
        // Initialize the interface
        initializeInterface: function() {
            this.hideProgressContainer();
            this.hideLogsContainer();
        },
        
        // Handle course selection
        onCourseSelect: function() {
            var courseId = $(this).val();
            var fixButton = $('#fix-relationships-btn');
            
            if (courseId) {
                fixButton.prop('disabled', false);
                WpmlLlmsAdmin.getCourseInfo(courseId);
            } else {
                fixButton.prop('disabled', true);
            }
        },
        
        // Handle fix relationships button click
        onFixRelationships: function(e) {
            e.preventDefault();
            
            var courseId = $('#course-selector').val();
            
            if (!courseId) {
                alert(wpmlLlmsAjax.strings.selectCourse);
                return;
            }
            
            WpmlLlmsAdmin.startFixProcess(courseId);
        },
        
        // Get course information
        getCourseInfo: function(courseId) {
            $.ajax({
                url: wpmlLlmsAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpml_llms_get_course_info',
                    course_id: courseId,
                    nonce: wpmlLlmsAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WpmlLlmsAdmin.displayCourseInfo(response.data);
                    } else {
                        console.error('Error getting course info:', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        },
        
        // Display course information
        displayCourseInfo: function(courseInfo) {
            // This could be expanded to show course details
            console.log('Course info:', courseInfo);
        },
        
        // Start the fix process
        startFixProcess: function(courseId) {
            this.showProgressContainer();
            this.showLogsContainer();
            this.clearLogs();
            this.setProgress(0, wpmlLlmsAjax.strings.fixing);
            this.disableInterface();
            
            $.ajax({
                url: wpmlLlmsAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpml_llms_fix_relationships',
                    course_id: courseId,
                    nonce: wpmlLlmsAjax.nonce
                },
                success: function(response) {
                    WpmlLlmsAdmin.handleFixResponse(response);
                },
                error: function(xhr, status, error) {
                    WpmlLlmsAdmin.handleFixError(error);
                }
            });
        },
        
        // Handle fix response
        handleFixResponse: function(response) {
            if (response.success) {
                this.setProgress(100, wpmlLlmsAjax.strings.complete);
                this.displayLogs(response.data.logs);
                this.displayStats(response.data.stats);
                
                // Show success message
                this.showNotice('success', response.data.message);
            } else {
                this.setProgress(0, wpmlLlmsAjax.strings.error);
                this.showNotice('error', response.data.message);
                
                if (response.data.logs) {
                    this.displayLogs(response.data.logs);
                }
            }
            
            this.enableInterface();
        },
        
        // Handle fix error
        handleFixError: function(error) {
            this.setProgress(0, wpmlLlmsAjax.strings.error);
            this.showNotice('error', wpmlLlmsAjax.strings.error + ': ' + error);
            this.enableInterface();
        },
        
        // Show progress container
        showProgressContainer: function() {
            $('#progress-container').show();
        },
        
        // Hide progress container
        hideProgressContainer: function() {
            $('#progress-container').hide();
        },
        
        // Show logs container
        showLogsContainer: function() {
            $('#logs-container').show();
        },
        
        // Hide logs container
        hideLogsContainer: function() {
            $('#logs-container').hide();
        },
        
        // Set progress
        setProgress: function(percentage, text) {
            $('#progress-fill').css('width', percentage + '%');
            $('#progress-text').text(text);
        },
        
        // Clear logs
        clearLogs: function() {
            $('#logs-content').empty();
        },
        
        // Display logs
        displayLogs: function(logs) {
            var logsContainer = $('#logs-content');
            
            if (!logs || logs.length === 0) {
                logsContainer.append('<div class="log-entry info">No logs available</div>');
                return;
            }
            
            $.each(logs, function(index, log) {
                var logEntry = $('<div class="log-entry ' + log.type + '"></div>');
                logEntry.text('[' + log.timestamp + '] ' + log.message);
                logsContainer.append(logEntry);
            });
            
            // Scroll to bottom
            logsContainer.scrollTop(logsContainer[0].scrollHeight);
        },
        
        // Display statistics
        displayStats: function(stats) {
            if (!stats) return;
            
            var statsHtml = '<div class="stats-summary">';
            statsHtml += '<h4>Processing Summary:</h4>';
            statsHtml += '<ul>';
            statsHtml += '<li>Courses Processed: ' + stats.courses_processed + '</li>';
            statsHtml += '<li>Relationships Fixed: ' + stats.relationships_fixed + '</li>';
            statsHtml += '<li>Sections Synced: ' + stats.sections_synced + '</li>';
            statsHtml += '<li>Lessons Synced: ' + stats.lessons_synced + '</li>';
            statsHtml += '<li>Quizzes Synced: ' + stats.quizzes_synced + '</li>';
            statsHtml += '<li>Enrollments Synced: ' + stats.enrollments_synced + '</li>';
            if (stats.errors > 0) {
                statsHtml += '<li class="error">Errors: ' + stats.errors + '</li>';
            }
            statsHtml += '</ul>';
            statsHtml += '</div>';
            
            $('#logs-content').append(statsHtml);
        },
        
        // Show notice
        showNotice: function(type, message) {
            var noticeClass = 'notice notice-' + type + ' is-dismissible';
            var notice = $('<div class="' + noticeClass + '"><p>' + message + '</p></div>');
            
            $('.wpml-llms-admin-header').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut();
            }, 5000);
        },
        
        // Disable interface during processing
        disableInterface: function() {
            $('#course-selector').prop('disabled', true);
            $('#fix-relationships-btn').prop('disabled', true).text('Processing...');
        },
        
        // Enable interface after processing
        enableInterface: function() {
            $('#course-selector').prop('disabled', false);
            $('#fix-relationships-btn').prop('disabled', false).text('Fix Relationships');
        },
        
        // Utility function to escape HTML
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize on our admin page
        if ($('.wpml-llms-course-fixer').length > 0) {
            WpmlLlmsAdmin.init();
        }
    });
    
    // Make WpmlLlmsAdmin globally available for debugging
    window.WpmlLlmsAdmin = WpmlLlmsAdmin;
    
})(jQuery);
