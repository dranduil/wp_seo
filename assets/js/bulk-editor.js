jQuery(document).ready(function($) {
    // Tab Navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show selected tab content
        $('.tab-content').removeClass('active');
        $('#' + tabId).addClass('active');
    });

    // Load Posts for Bulk Editor
    $('#load-posts').on('click', function() {
        var postType = $('#post-type-filter').val();
        var button = $(this);
        button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpsmd_get_posts',
                post_type: postType,
                nonce: wpsmdBulkEditor.nonce
            },
            success: function(response) {
                if (response.success) {
                    var tbody = $('#bulk-seo-rows');
                    tbody.empty();

                    response.data.forEach(function(post) {
                        tbody.append(`
                            <tr data-post-id="${post.ID}">
                                <td class="column-title">
                                    <strong>${post.post_title}</strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="${post.edit_link}" target="_blank">${wpsmdBulkEditor.editText}</a>
                                        </span>
                                        <span class="view"> | 
                                            <a href="${post.permalink}" target="_blank">${wpsmdBulkEditor.viewText}</a>
                                        </span>
                                    </div>
                                </td>
                                <td class="column-seo-title">
                                    <input type="text" class="widefat seo-title" 
                                           value="${post.seo_title || ''}" 
                                           placeholder="${wpsmdBulkEditor.seoTitlePlaceholder}">
                                </td>
                                <td class="column-meta-description">
                                    <textarea class="widefat meta-description" rows="2" 
                                              placeholder="${wpsmdBulkEditor.metaDescPlaceholder}">${post.meta_description || ''}</textarea>
                                </td>
                                <td class="column-canonical">
                                    <input type="url" class="widefat canonical-url" 
                                           value="${post.canonical_url || ''}" 
                                           placeholder="${wpsmdBulkEditor.canonicalPlaceholder}">
                                </td>
                            </tr>
                        `);
                    });
                } else {
                    alert(wpsmdBulkEditor.errorLoading);
                }
            },
            error: function() {
                alert(wpsmdBulkEditor.errorLoading);
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });

    // Save Bulk SEO Changes
    $('#save-bulk-seo').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);

        var updates = [];
        $('#bulk-seo-rows tr').each(function() {
            var row = $(this);
            updates.push({
                post_id: row.data('post-id'),
                seo_title: row.find('.seo-title').val(),
                meta_description: row.find('.meta-description').val(),
                canonical_url: row.find('.canonical-url').val()
            });
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpsmd_save_bulk_meta',
                updates: updates,
                nonce: wpsmdBulkEditor.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(wpsmdBulkEditor.saveSuccess);
                } else {
                    alert(wpsmdBulkEditor.saveError);
                }
            },
            error: function() {
                alert(wpsmdBulkEditor.saveError);
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });

    // Save CPT Templates
    $('#cpt-templates-form').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var submitButton = form.find('button[type="submit"]');
        submitButton.prop('disabled', true);

        var templates = {};
        $('.cpt-template-section').each(function() {
            var section = $(this);
            var postType = section.find('input[type="text"]').attr('id').replace('_title_template', '');
            templates[postType] = {
                title_template: section.find('input[type="text"]').val(),
                description_template: section.find('textarea').val()
            };
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpsmd_save_cpt_templates',
                templates: templates,
                nonce: wpsmdBulkEditor.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(wpsmdBulkEditor.templateSaveSuccess);
                } else {
                    alert(wpsmdBulkEditor.templateSaveError);
                }
            },
            error: function() {
                alert(wpsmdBulkEditor.templateSaveError);
            },
            complete: function() {
                submitButton.prop('disabled', false);
            }
        });
    });

    // Export Settings
    $('#export-settings').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpsmd_export_settings',
                nonce: wpsmdBulkEditor.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create and trigger download
                    var dataStr = JSON.stringify(response.data);
                    var dataUri = 'data:application/json;charset=utf-8,' + encodeURIComponent(dataStr);
                    var exportName = 'wpsmd-settings-' + new Date().toISOString().slice(0,10) + '.json';

                    var linkElement = document.createElement('a');
                    linkElement.setAttribute('href', dataUri);
                    linkElement.setAttribute('download', exportName);
                    linkElement.click();
                } else {
                    alert(wpsmdBulkEditor.exportError);
                }
            },
            error: function() {
                alert(wpsmdBulkEditor.exportError);
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });

    // Import Settings
    $('#import-settings').on('click', function() {
        var fileInput = $('#import-file')[0];
        if (!fileInput.files.length) {
            alert(wpsmdBulkEditor.selectFileError);
            return;
        }

        var file = fileInput.files[0];
        var reader = new FileReader();
        var button = $(this);
        button.prop('disabled', true);

        reader.onload = function(e) {
            try {
                var settings = JSON.parse(e.target.result);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpsmd_import_settings',
                        settings: settings,
                        nonce: wpsmdBulkEditor.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(wpsmdBulkEditor.importSuccess);
                            location.reload();
                        } else {
                            alert(wpsmdBulkEditor.importError);
                        }
                    },
                    error: function() {
                        alert(wpsmdBulkEditor.importError);
                    },
                    complete: function() {
                        button.prop('disabled', false);
                    }
                });
            } catch(e) {
                alert(wpsmdBulkEditor.invalidFileError);
                button.prop('disabled', false);
            }
        };

        reader.readAsText(file);
    });
});