(function($) {
    'use strict';

    // Initialize the analytics dashboard
    function initAnalytics() {
        bindEvents();
        checkGSCConnection();
        loadAnalyticsData();
    }

    // Bind event handlers
    function bindEvents() {
        $('#wpsmd-verify-gsc').on('click', verifySearchConsole);
        $('#wpsmd-disconnect-gsc').on('click', disconnectSearchConsole);
    }

    // Disconnect from Google Search Console
    function disconnectSearchConsole() {
        const $button = $('#wpsmd-disconnect-gsc');
        const originalText = $button.text();

        $button.prop('disabled', true)
               .text(wpsmdAnalytics.i18n.loadingData);

        $.ajax({
            url: wpsmdAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wpsmd_disconnect_gsc',
                nonce: wpsmdAnalytics.nonce
            },
            success: function(response) {
                console.log('WPSMD: Disconnect response:', response);
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    location.reload(); // Reload to update UI
                } else {
                    showNotice(response.data.message || wpsmdAnalytics.i18n.verifyError, 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('WPSMD: AJAX error details:', {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    requestData: {
                        action: 'wpsmd_verify_gsc',
                        nonce: wpsmdAnalytics.nonce,
                        code: authCode
                    }
                });
                showNotice(wpsmdAnalytics.i18n.verifyError + ': ' + (jqXHR.responseText || textStatus), 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    // Verify Google Search Console connection
    function verifySearchConsole() {
        const $button = $('#wpsmd-verify-gsc');
        const originalText = $button.text();

        $button.prop('disabled', true)
               .text(wpsmdAnalytics.i18n.loadingData);

        // Check for authorization code in URL
        const urlParams = new URLSearchParams(window.location.search);
        const authCode = urlParams.get('code');
        const error = urlParams.get('error');

        if (error) {
            showNotice(wpsmdAnalytics.i18n.verifyError + ': ' + error, 'error');
            $button.prop('disabled', false).text(originalText);
            return;
        }

        $.ajax({
            url: wpsmdAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wpsmd_verify_gsc',
                nonce: wpsmdAnalytics.nonce,
                code: authCode
            },
            success: function(response) {
                console.log('WPSMD: Verify response:', response);
                // Handle empty or '0' response
                if (response === '0' || response === 0) {
                    console.error('WPSMD: Empty or zero response received');
                    showNotice(wpsmdAnalytics.i18n.verifyError + ': Invalid server response', 'error');
                    return;
                }
                // Validate response structure
                if (typeof response !== 'object') {
                    console.error('WPSMD: Invalid response type:', typeof response);
                    showNotice(wpsmdAnalytics.i18n.verifyError + ': Invalid response format', 'error');
                    return;
                }
                if (response.success) {
                    if (response.data.auth_url) {
                        console.log('WPSMD: Redirecting to auth URL:', response.data.auth_url);
                        // Store the current page URL before redirecting
                        sessionStorage.setItem('wpsmd_redirect_after_auth', window.location.href);
                        window.location.href = response.data.auth_url;
                        return;
                    }
                    showNotice(response.data.message, 'success');
                    if (response.data.reload || authCode) {
                        console.log('WPSMD: Reloading page to refresh state');
                        // Remove code from URL and reload to refresh the page state
                        const newUrl = window.location.href.split('?')[0];
                        window.location.href = newUrl;
                    } else {
                        console.log('WPSMD: No redirect/reload needed');
                        location.reload();
                    }
                } else {
                    showNotice(response.data.message || wpsmdAnalytics.i18n.verifyError, 'error');
                    if (authCode) {
                        // Remove failed auth code from URL
                        const newUrl = window.location.href.split('?')[0];
                        window.history.replaceState({}, document.title, newUrl);
                    }
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('WPSMD: AJAX error:', textStatus, errorThrown);
                console.error('WPSMD: Response:', jqXHR.responseText);
                showNotice(wpsmdAnalytics.i18n.verifyError + ': ' + textStatus, 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    }

    // Check GSC connection status
    function checkGSCConnection() {
        $.ajax({
            url: wpsmdAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wpsmd_verify_gsc',
                nonce: wpsmdAnalytics.nonce
            },
            success: function(response) {
                console.log('WPSMD: Connection check response:', response);
                if (!response.success && response.data && response.data.message) {
                    showNotice(response.data.message, 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('WPSMD: Connection check error details:', {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    requestData: {
                        action: 'wpsmd_verify_gsc',
                        nonce: wpsmdAnalytics.nonce
                    }
                });
                showNotice(wpsmdAnalytics.i18n.verifyError + ': ' + (jqXHR.responseText || textStatus), 'error');
            }
        });
    }

    // Load analytics data
    function loadAnalyticsData() {
        loadSearchAnalytics();
        loadCrawlErrors();
    }

    // Load search analytics data
    function loadSearchAnalytics() {
        $.ajax({
            url: wpsmdAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wpsmd_get_search_analytics',
                nonce: wpsmdAnalytics.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateMetrics(response.data);
                    updatePerformanceChart(response.data);
                    updateKeywordsTable(response.data.keywords || []);
                } else {
                    showNotice(wpsmdAnalytics.i18n.errorLoadingData, 'error');
                }
            },
            error: function() {
                showNotice(wpsmdAnalytics.i18n.errorLoadingData, 'error');
            }
        });
    }

    // Load crawl errors
    function loadCrawlErrors() {
        $.ajax({
            url: wpsmdAnalytics.ajax_url,
            type: 'POST',
            data: {
                action: 'wpsmd_get_crawl_errors',
                nonce: wpsmdAnalytics.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateErrorsTable(response.data.errors || []);
                } else {
                    showNotice(wpsmdAnalytics.i18n.errorLoadingData, 'error');
                }
            },
            error: function() {
                showNotice(wpsmdAnalytics.i18n.errorLoadingData, 'error');
            }
        });
    }

    // Update metrics display
    function updateMetrics(data) {
        $('#wpsmd-clicks').text(data.clicks || 0);
        $('#wpsmd-impressions').text(data.impressions || 0);
        $('#wpsmd-position').text(data.position ? data.position.toFixed(1) : 0);
        $('#wpsmd-ctr').text(data.ctr ? (data.ctr * 100).toFixed(1) + '%' : '0%');
    }

    // Update performance chart
    function updatePerformanceChart(data) {
        // TODO: Implement chart visualization using a charting library
    }

    // Update keywords table
    function updateKeywordsTable(keywords) {
        const $tbody = $('#wpsmd-keywords-body');
        if (!keywords.length) {
            return;
        }

        let html = '';
        keywords.forEach(function(keyword) {
            html += `
                <tr>
                    <td>${escapeHtml(keyword.term)}</td>
                    <td>${keyword.clicks}</td>
                    <td>${keyword.impressions}</td>
                    <td>${keyword.position.toFixed(1)}</td>
                </tr>
            `;
        });

        $tbody.html(html);
    }

    // Update errors table
    function updateErrorsTable(errors) {
        const $tbody = $('#wpsmd-errors-body');
        if (!errors.length) {
            return;
        }

        let html = '';
        errors.forEach(function(error) {
            html += `
                <tr>
                    <td>${escapeHtml(error.url)}</td>
                    <td>${escapeHtml(error.type)}</td>
                    <td>${escapeHtml(error.detected)}</td>
                </tr>
            `;
        });

        $tbody.html(html);
    }

    // Show admin notice
    function showNotice(message, type) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>')
            .hide()
            .insertAfter('.wrap h1')
            .slideDown();

        setTimeout(function() {
            $notice.slideUp(function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize on document ready
    $(document).ready(initAnalytics);

})(jQuery);