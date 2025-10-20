/**
 * WPML LifterLMS Compatibility Admin JavaScript
 * 
 * @package WPML_LifterLMS_Compatibility
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    var WpmlLlmsAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Sync translations button
            $('#sync-translations').on('click', this.syncTranslations);
            
            // Export configuration button
            $('#export-config').on('click', this.exportConfig);
            
            // Import configuration button
            $('#import-config').on('click', function() {
                $('#import-config-file').click();
            });
            
            // Handle file selection for import
            $('#import-config-file').on('change', this.importConfig);
        },
        
        /**
         * Initialize admin tabs
         */
        initTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var target = $(this).attr('href');
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show target content
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });
        },
        
        /**
         * Sync translations
         */
        syncTranslations: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $progress = $('#sync-progress');
            var $progressBar = $('.progress-fill');
            var $progressText = $('.progress-text');
            
            // Disable button and show progress
            $button.prop('disabled', true).text(wpmlLlmsAdmin.strings.syncing);
            $progress.show();
            $progressText.text('Starting sync...');
            
            // Simulate progress (in real implementation, this would be actual progress)
            var progress = 0;
            var progressInterval = setInterval(function() {
                progress += Math.random() * 20;
                if (progress > 90) progress = 90;
                
                $progressBar.css('width', progress + '%');
                $progressText.text('Syncing translations... ' + Math.round(progress) + '%');
            }, 500);
            
            // Make AJAX request
            $.ajax({
                url: wpmlLlmsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpml_llms_sync_translations',
                    nonce: wpmlLlmsAdmin.nonce
                },
                success: function(response) {
                    clearInterval(progressInterval);
                    
                    if (response.success) {
                        $progressBar.css('width', '100%');
                        $progressText.text(wpmlLlmsAdmin.strings.syncComplete);
                        
                        setTimeout(function() {
                            $progress.hide();
                            $button.prop('disabled', false).text('Sync Now');
                        }, 2000);
                    } else {
                        WpmlLlmsAdmin.showError(wpmlLlmsAdmin.strings.syncError);
                        $progress.hide();
                        $button.prop('disabled', false).text('Sync Now');
                    }
                },
                error: function() {
                    clearInterval(progressInterval);
                    WpmlLlmsAdmin.showError(wpmlLlmsAdmin.strings.syncError);
                    $progress.hide();
                    $button.prop('disabled', false).text('Sync Now');
                }
            });
        },
        
        /**
         * Export configuration
         */
        exportConfig: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: wpmlLlmsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpml_llms_export_config',
                    nonce: wpmlLlmsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link
                        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(response.data, null, 2));
                        var downloadAnchorNode = document.createElement('a');
                        downloadAnchorNode.setAttribute("href", dataStr);
                        downloadAnchorNode.setAttribute("download", "wpml-lifterlms-config.json");
                        document.body.appendChild(downloadAnchorNode);
                        downloadAnchorNode.click();
                        downloadAnchorNode.remove();
                        
                        WpmlLlmsAdmin.showSuccess('Configuration exported successfully!');
                    } else {
                        WpmlLlmsAdmin.showError('Export failed. Please try again.');
                    }
                },
                error: function() {
                    WpmlLlmsAdmin.showError('Export failed. Please try again.');
                }
            });
        },
        
        /**
         * Import configuration
         */
        importConfig: function(e) {
            var file = e.target.files[0];
            
            if (!file) {
                return;
            }
            
            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var config = JSON.parse(e.target.result);
                    
                    $.ajax({
                        url: wpmlLlmsAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wpml_llms_import_config',
                            nonce: wpmlLlmsAdmin.nonce,
                            config: JSON.stringify(config)
                        },
                        success: function(response) {
                            if (response.success) {
                                WpmlLlmsAdmin.showSuccess('Configuration imported successfully!');
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                WpmlLlmsAdmin.showError('Import failed. Please check the file format.');
                            }
                        },
                        error: function() {
                            WpmlLlmsAdmin.showError('Import failed. Please try again.');
                        }
                    });
                } catch (error) {
                    WpmlLlmsAdmin.showError('Invalid configuration file format.');
                }
            };
            
            reader.readAsText(file);
        },
        
        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
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
                $notice.fadeOut();
            }, 5000);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        WpmlLlmsAdmin.init();
    });
    
})(jQuery);

