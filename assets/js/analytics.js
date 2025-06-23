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
                // Enhanced error logging
                const errorDetails = {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    requestData: requestData,
                    url: wpsmdAnalytics.ajax_url
                };

                // Try to parse response JSON if available
                try {
                    errorDetails.parsedResponse = JSON.parse(jqXHR.responseText);
                } catch (e) {
                    errorDetails.parseError = e.message;
                }

                console.error('WPSMD: Verification error details:', errorDetails);

                // Check for specific error conditions
                if (jqXHR.status === 400) {
                    console.warn('WPSMD: Received 400 Bad Request - This might indicate an issue with the OAuth state parameter or invalid request format');
                }

                console.error('WPSMD: Disconnect error details:', {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    requestData: {
                        action: 'wpsmd_disconnect_gsc',
                        nonce: wpsmdAnalytics.nonce
                    }
                });
                
                // Log additional request details for debugging
                console.log('WPSMD: Request URL:', wpsmdAnalytics.ajax_url);
                console.log('WPSMD: Request Headers:', jqXHR.getAllResponseHeaders());

                let errorMessage = wpsmdAnalytics.i18n.verifyError;
                
                try {
                    const errorResponse = JSON.parse(jqXHR.responseText);
                    if (errorResponse.data && errorResponse.data.message) {
                        errorMessage = errorResponse.data.message;
                    }
                } catch (e) {
                    errorMessage += textStatus ? ': ' + textStatus : '';
                }

                showNotice(errorMessage, 'error');
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

        // Check for authorization code and state in URL
        const urlParams = new URLSearchParams(window.location.search);
        const authCode = urlParams.get('code');
        const state = urlParams.get('state');
        const error = urlParams.get('error');

        if (error) {
            const errorDescription = urlParams.get('error_description') || error;
            console.error('WPSMD: OAuth error:', {
                error: error,
                error_description: errorDescription,
                state: state
            });
            showNotice(wpsmdAnalytics.i18n.verifyError + ': ' + errorDescription, 'error');
            $button.prop('disabled', false).text(originalText);
            return;
        }

        // If we have both code and state, we're handling the OAuth callback
        if (authCode && state) {
            console.log('WPSMD: Handling OAuth callback with:', {
                code: authCode,
                state: state,
                decodedState: decodeURIComponent(state)
            });

            // Log the complete URL and query parameters
            console.log('WPSMD: Complete callback URL:', window.location.href);
            console.log('WPSMD: All URL parameters:', Object.fromEntries(urlParams.entries()));
            
            // Validate state parameter format
            try {
                // First try to decode the state parameter directly
                let stateData;
                let decodedState = state;
                
                try {
                    // Try direct base64 decode first
                    const directDecoded = atob(state);
                    try {
                        stateData = JSON.parse(directDecoded);
                        console.log('WPSMD: Successfully decoded state directly:', stateData);
                    } catch (e) {
                        console.log('WPSMD: Direct JSON parse failed, trying URL decode:', e);
                        decodedState = cleanStateParameter(state);
                        stateData = JSON.parse(atob(decodedState));
                    }
                } catch (e) {
                    console.log('WPSMD: Direct base64 decode failed, trying URL decode first:', e);
                    decodedState = cleanStateParameter(state);
                    stateData = JSON.parse(atob(decodedState));
                }
                
                console.log('WPSMD: Final parsed state data:', stateData);

                // Validate required state properties
                if (!stateData.nonce || !stateData.action || !stateData.timestamp) {
                    console.error('WPSMD: Missing required state parameters:', {
                        hasNonce: !!stateData.nonce,
                        hasAction: !!stateData.action,
                        hasTimestamp: !!stateData.timestamp
                    });
                    throw new Error('Missing required state parameters');
                }

                // Check if state has expired (30 minutes)
                const stateTime = new Date(stateData.timestamp).getTime();
                const currentTime = new Date().getTime();
                const timeLimit = 30 * 60 * 1000; // 30 minutes in milliseconds

                if (currentTime - stateTime > timeLimit) {
                    console.error('WPSMD: State parameter expired:', {
                        stateTime: new Date(stateTime).toISOString(),
                        currentTime: new Date(currentTime).toISOString(),
                        timeDiff: Math.floor((currentTime - stateTime) / 1000) + ' seconds'
                    });
                    throw new Error('State parameter has expired');
                }
            } catch (e) {
                console.error('WPSMD: State validation error:', e);
                showNotice(wpsmdAnalytics.i18n.verifyError + ': Invalid or expired state parameter', 'error');
                $button.prop('disabled', false).text(originalText);
                return;
            }
        }

        // Clean and validate state parameter
        function cleanStateParameter(state) {
            if (!state) return '';
            
            // Log raw state for debugging
            console.log('WPSMD: Raw state parameter:', state);
            
            // First try direct base64 decode
            try {
                const directDecoded = atob(state);
                try {
                    JSON.parse(directDecoded);
                    console.log('WPSMD: Successfully decoded state directly:', directDecoded);
                    return state; // Return original if it's already valid base64
                } catch (e) {
                    console.log('WPSMD: Direct decode succeeded but invalid JSON:', e);
                }
            } catch (e) {
                console.log('WPSMD: Direct base64 decode failed, trying URL decode:', e);
            }
            
            // Try URL decoding
            let cleanedState = state;
            try {
                // Try to decode until we can't anymore (handles multiple encodings)
                let iterations = 0;
                const maxIterations = 3; // Prevent infinite loops
                
                while (iterations < maxIterations) {
                    const decoded = decodeURIComponent(cleanedState);
                    console.log(`WPSMD: Decode iteration ${iterations + 1}:`, decoded);
                    
                    if (decoded === cleanedState) break;
                    cleanedState = decoded;
                    iterations++;
                }
                
                // Try to validate the final decoded state
                try {
                    const stateData = JSON.parse(atob(cleanedState));
                    console.log('WPSMD: Final decoded state data:', stateData);
                } catch (e) {
                    console.warn('WPSMD: Final state validation failed:', e);
                }
                
            } catch (e) {
                console.warn('WPSMD: State parameter decoding error:', e);
                return state; // Return original if decoding fails
            }
            
            return cleanedState;
        }

        // Log request parameters for debugging
        let cleanedState;
        try {
            cleanedState = cleanStateParameter(state);
            // Validate the cleaned state can be properly decoded
            const testDecode = JSON.parse(atob(cleanedState));
            console.log('WPSMD: Validated cleaned state:', testDecode);
        } catch (e) {
            console.error('WPSMD: Failed to validate cleaned state, using original:', e);
            cleanedState = state; // Fall back to original state if cleaning fails
        }

        const requestData = {
            action: 'wpsmd_verify_gsc',
            nonce: wpsmdAnalytics.nonce,
            code: authCode,
            state: cleanedState,
            debug_info: {
                original_state: state,
                cleaned_state: cleanedState,
                url: window.location.href
            }
        };
        console.log('WPSMD: Sending request with data:', requestData);

        // Log complete request configuration
        console.log('WPSMD: Complete request configuration:', {
            url: wpsmdAnalytics.ajax_url,
            type: 'POST',
            data: requestData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        $.ajax({
            url: wpsmdAnalytics.ajax_url,
            type: 'POST',
            data: requestData,
            beforeSend: function(xhr) {
                console.log('WPSMD: Request headers being sent:', xhr.getAllRequestHeaders?.() || 'Headers not available');
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Enhanced error logging
                const errorDetails = {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    requestData: requestData,
                    url: wpsmdAnalytics.ajax_url
                };

                console.error('WPSMD: Verification error details:', errorDetails);

                let errorMessage = wpsmdAnalytics.i18n.verifyError;
                let details = '';

                // Try to parse response JSON if available
                try {
                    const errorResponse = JSON.parse(jqXHR.responseText);
                    if (errorResponse.data && errorResponse.data.message) {
                        errorMessage = errorResponse.data.message;
                    }
                    
                    // Add detailed error information
                    if (errorResponse.data && errorResponse.data.debug_info) {
                        details = errorResponse.data.debug_info;
                    }
                } catch (e) {
                    console.warn('WPSMD: Could not parse error response:', e);
                }

                // Add specific guidance for 400 Bad Request
                if (jqXHR.status === 400) {
                    console.log('WPSMD: Analyzing 400 error response:', {
                        responseText: jqXHR.responseText,
                        state: state,
                        authCode: authCode
                    });

                    // Check for specific error conditions
                    if (jqXHR.responseText.includes('state parameter')) {
                        details = 'The OAuth state parameter appears to be invalid or corrupted. This could be due to:\
' +
                                 '1. Browser encoding issues with special characters\
' +
                                 '2. URL truncation or modification during redirect\
' +
                                 '3. Session expiration\
\n' +
                                 'Please try clearing your browser cache and cookies, then authenticate again.';
                    } else if (jqXHR.responseText.includes('redirect_uri_mismatch')) {
                        details = 'The redirect URI does not match the one configured in your Google Cloud Console. \
' +
                                 'Please ensure the redirect URI https://unlockthemove.com/wp-admin/admin-ajax.php is properly configured.';
                    } else {
                        details = 'This might be due to an invalid OAuth state parameter or expired authorization. Please try authenticating again.';
                    }
                }

                showNotice(errorMessage, 'error', details);
                $button.prop('disabled', false).text(originalText);
            },
            success: function(response) {
                console.log('WPSMD: Verify response:', response);
                
                // Handle empty or invalid response
                if (!response || response === '0' || response === 0) {
                    console.error('WPSMD: Empty or zero response received');
                    showNotice(wpsmdAnalytics.i18n.verifyError + ': Invalid server response', 'error');
                    $button.prop('disabled', false).text(originalText);
                    return;
                }
                
                // Validate and parse response if needed
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                        console.log('WPSMD: Parsed JSON response:', response);
                    } catch (e) {
                        console.error('WPSMD: Failed to parse response:', e);
                        showNotice(wpsmdAnalytics.i18n.verifyError + ': Invalid response format', 'error');
                        $button.prop('disabled', false).text(originalText);
                        return;
                    }
                }

                // Ensure response is an object
                if (!response || typeof response !== 'object') {
                    console.error('WPSMD: Invalid response type:', typeof response);
                    showNotice(wpsmdAnalytics.i18n.verifyError + ': Invalid response format', 'error');
                    $button.prop('disabled', false).text(originalText);
                    return;
                }
                
                // Log full response for debugging
                console.log('WPSMD: Full response:', response);
                
                if (response.success) {
                    // Handle successful response with auth_url (initial auth)
                    if (response.data && response.data.auth_url) {
                        console.log('WPSMD: Redirecting to auth URL:', response.data.auth_url);
                        // Store the current page URL before redirecting
                        sessionStorage.setItem('wpsmd_redirect_after_auth', window.location.href);
                        window.location.href = response.data.auth_url;
                        return;
                    }
                    
                    // Handle successful response with message
                    if (response.data && response.data.message) {
                        showNotice(response.data.message, 'success');
                    }
                    
                    // Handle successful response with redirect_url
                    if (response.data && response.data.redirect_url) {
                        console.log('WPSMD: Redirecting to:', response.data.redirect_url);
                        window.location.href = response.data.redirect_url;
                        return;
                    }
                    
                    // Handle successful response requiring reload
                    if (response.data && response.data.reload) {
                        console.log('WPSMD: Reloading page to refresh state');
                        if (authCode) {
                            // Remove code from URL and reload
                            const newUrl = window.location.href.split('?')[0];
                            window.location.href = newUrl;
                        } else {
                            window.location.reload();
                        }
                        return;
                    }
                } else {
                    // Handle error response
                    console.error('WPSMD: Error response:', response);
                    const errorMessage = response.data && response.data.message
                        ? response.data.message
                        : wpsmdAnalytics.i18n.verifyError;
                    showNotice(errorMessage, 'error');
                    if (authCode) {
                        // Remove failed auth code from URL
                        const newUrl = window.location.href.split('?')[0];
                        window.history.replaceState({}, document.title, newUrl);
                    }
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('WPSMD: AJAX error details:', {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText,
                    textStatus: textStatus,
                    errorThrown: errorThrown
                });

                let errorMessage = wpsmdAnalytics.i18n.verifyError;
                
                // Try to parse response text for more details
                try {
                    const errorResponse = JSON.parse(jqXHR.responseText);
                    if (errorResponse.data && errorResponse.data.message) {
                        errorMessage = errorResponse.data.message;
                    }
                } catch (e) {
                    // Handle specific error cases
                    if (jqXHR.status === 400) {
                        errorMessage = 'OAuth Configuration Error: Please verify the following in Google Cloud Console:\n' +
                            '1. The authorized redirect URI matches exactly: ' + window.location.origin + '/wp-admin/admin-ajax.php\n' +
                            '2. Client ID and Client Secret are correctly configured\n' +
                            '3. OAuth consent screen is properly set up\n' +
                            '4. The application is not in testing mode, or your account is added as a test user';
                    } else {
                        errorMessage += textStatus ? ': ' + textStatus : '';
                    }
                }

                showNotice(errorMessage, 'error');
                
                // If this was an OAuth callback, clean up the URL
                if (authCode) {
                    const newUrl = window.location.href.split('?')[0];
                    window.history.replaceState({}, document.title, newUrl);
                }
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
    function showNotice(message, type, details = '') {
        // Convert newlines to <br> tags for proper HTML display
        const formattedMessage = message.replace(/\n/g, '<br>');
        let displayMessage = formattedMessage;
        if (details) {
            displayMessage += '<br><small class="notice-details">' + details + '</small>';
        }
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + displayMessage + '</p></div>')
            .hide()
            .insertAfter('.wrap h1')
            .slideDown();

        // Ensure the notice is visible by scrolling to it
        $('html, body').animate({
            scrollTop: $notice.offset().top - 50
        }, 500);

        setTimeout(function() {
            $notice.slideUp(function() {
                $(this).remove();
            });
        }, 30000);
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