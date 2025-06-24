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
                // Convert URL-safe base64 to standard base64
                try {
                    // Replace URL-safe characters and restore padding if needed
                    let standardBase64 = state.replace(/-/g, '+').replace(/_/g, '/');
                    const padding = standardBase64.length % 4;
                    if (padding) {
                        standardBase64 += '='.repeat(4 - padding);
                    }
                    console.log('WPSMD: Converted to standard base64:', standardBase64);

                    // Attempt to decode
                    const decoded = atob(standardBase64);
                    console.log('WPSMD: Base64 decoded result length:', decoded.length);

                    // Parse JSON
                    stateData = JSON.parse(decoded);
                    console.log('WPSMD: Successfully parsed state data:', {
                        nonce: stateData.nonce ? 'present' : 'missing',
                        action: stateData.action,
                        timestamp: stateData.timestamp,
                        site_url: stateData.site_url ? 'present' : 'missing'
                    });
                } catch (e) {
                    console.error('WPSMD: State parameter processing failed:', {
                        error: e.message,
                        type: e.name,
                        decodedLength: decoded ? decoded.length : 0
                    });
                    throw new Error(`Failed to process state parameter: ${e.message}`);
                }

                // Validate required state properties
                const requiredFields = ['nonce', 'action', 'timestamp', 'site_url'];
                const missingFields = requiredFields.filter(field => !stateData[field]);
                
                if (missingFields.length > 0) {
                    console.error('WPSMD: Missing required state parameters:', {
                        missingFields: missingFields,
                        stateData: stateData
                    });
                    throw new Error(`Missing required state parameters: ${missingFields.join(', ')}`);
                }

                // Validate timestamp format and value
                const timestamp = parseInt(stateData.timestamp, 10);
                if (isNaN(timestamp)) {
                    console.error('WPSMD: Invalid timestamp format:', stateData.timestamp);
                    throw new Error('Invalid timestamp format in state');
                }

                // Check if state has expired (30 minutes)
                const stateTime = timestamp * 1000; // Convert to milliseconds
                const currentTime = Date.now();
                const timeLimit = 30 * 60 * 1000; // 30 minutes in milliseconds

                if (currentTime - stateTime > timeLimit) {
                    console.error('WPSMD: State parameter expired:', {
                        stateTime: new Date(stateTime).toISOString(),
                        currentTime: new Date(currentTime).toISOString(),
                        timeDiff: Math.floor((currentTime - stateTime) / 1000) + ' seconds',
                        timeLimit: timeLimit / 1000 + ' seconds'
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
            if (!state) {
                console.error('WPSMD: Empty state parameter');
                return '';
            }
            
            // Log raw state for debugging
            console.log('WPSMD: Raw state parameter:', {
                state: state,
                length: state.length,
                containsBase64Chars: /^[A-Za-z0-9+/=_-]*$/.test(state)
            });
            
            try {
                // First URL decode the state parameter
                let urlDecodedState;
                try {
                    urlDecodedState = decodeURIComponent(state);
                    console.log('WPSMD: URL decoded state:', {
                        state: urlDecodedState,
                        length: urlDecodedState.length
                    });
                } catch (urlError) {
                    console.error('WPSMD: URL decode failed:', {
                        error: urlError,
                        state: state
                    });
                    throw new Error('Invalid URL encoding in state parameter');
                }
                
                // Verify the decoded state contains only valid base64url characters
                if (!/^[A-Za-z0-9_-]*$/.test(urlDecodedState)) {
                    console.error('WPSMD: Invalid characters in decoded state');
                    throw new Error('Invalid characters in state parameter');
                }
                
                // Convert URL-safe base64 to standard base64
                let standardBase64 = urlDecodedState.replace(/-/g, '+').replace(/_/g, '/');
                
                // Add padding if needed
                const padding = standardBase64.length % 4;
                if (padding) {
                    standardBase64 += '='.repeat(4 - padding);
                }
                
                console.log('WPSMD: Converted to standard base64:', {
                    base64: standardBase64,
                    length: standardBase64.length,
                    padding: padding
                });

                // Attempt base64 decode
                let decoded;
                try {
                    decoded = atob(standardBase64);
                    console.log('WPSMD: Base64 decode successful, length:', decoded.length);
                } catch (decodeError) {
                    console.error('WPSMD: Base64 decode failed:', decodeError);
                    throw new Error('Invalid base64 encoding');
                }

                // Attempt JSON parse
                try {
                    const stateData = JSON.parse(decoded);
                    console.log('WPSMD: JSON parse successful:', stateData);
                    
                    // Verify required fields
                    const requiredFields = ['nonce', 'action', 'timestamp', 'site_url'];
                    for (const field of requiredFields) {
                        if (!stateData[field]) {
                            throw new Error(`Missing required field: ${field}`);
                        }
                    }
                    
                    // Validate timestamp
                    const timestamp = parseInt(stateData.timestamp, 10);
                    if (isNaN(timestamp) || timestamp <= 0) {
                        throw new Error('Invalid timestamp format');
                    }
                    
                    const currentTime = Math.floor(Date.now() / 1000);
                    const timeDiff = Math.abs(currentTime - timestamp);
                    const maxAge = 30 * 60; // 30 minutes
                    
                    if (timeDiff > maxAge) {
                        throw new Error(`State expired. Time difference: ${timeDiff} seconds`);
                    }
                    
                    // Return the URL-decoded state if all validation passes
                    return urlDecodedState;
                } catch (jsonError) {
                    console.error('WPSMD: JSON parse failed:', {
                        error: jsonError,
                        decodedString: decoded
                    });
                    throw new Error('Invalid JSON structure');
                }
            } catch (e) {
                console.error('WPSMD: State parameter validation failed:', {
                    error: e.message,
                    originalState: state,
                    stack: e.stack
                });
                throw e; // Propagate the error instead of returning invalid state
            }
        }

        // Log request parameters for debugging
        let cleanedState;
        try {
            cleanedState = cleanStateParameter(state);
            if (!cleanedState) {
                throw new Error('State parameter validation failed');
            }
            console.log('WPSMD: Successfully validated state parameter');
        } catch (e) {
            console.error('WPSMD: State validation failed:', {
                error: e.message,
                originalState: state
            });
            showNotice(wpsmdAnalytics.i18n.verifyError + ': Invalid state parameter - Please try again', 'error');
            $button.prop('disabled', false).text(originalText);
            return;
        }

        // Prepare request data
        // Send AJAX request
        $.ajax({
            url: wpsmdAnalytics.ajaxurl,
            type: 'POST',
            data: requestData,
            beforeSend: function() {
                console.log('WPSMD: Sending AJAX request with data:', requestData);
            },
            success: function(response) {
                if (response.success) {
                    console.log('WPSMD: Analytics verification successful');
                    showNotice(wpsmdAnalytics.i18n.verifySuccess, 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    console.error('WPSMD: Analytics verification failed:', response);
                    const errorMessage = response.data && response.data.message
                        ? response.data.message
                        : wpsmdAnalytics.i18n.verifyError;
                    showNotice(errorMessage, 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('WPSMD: AJAX request failed:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                showNotice(wpsmdAnalytics.i18n.verifyError + ': ' + error, 'error');
                $button.prop('disabled', false).text(originalText);
            }
        });

        const requestData = {
            action: 'wpsmd_verify_gsc',
            nonce: wpsmdAnalytics.nonce,
            code: authCode,
            state: cleanedState,
            debug_info: {
                original_state: state,
                cleaned_state: cleanedState,
                url: window.location.href,
                timestamp: Math.floor(Date.now() / 1000)
            }
        };

        console.log('WPSMD: Sending request with data:', {
            action: requestData.action,
            has_code: !!requestData.code,
            has_state: !!requestData.state,
            debug_info: requestData.debug_info
        });

        // Log complete request configuration
        console.log('WPSMD: Complete request configuration:', {
            url: wpsmdAnalytics.ajax_url,
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            data_length: JSON.stringify(requestData).length
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
                    requestData: {
                        action: requestData.action,
                        has_code: !!requestData.code,
                        has_state: !!requestData.state,
                        debug_info: requestData.debug_info
                    }
                };

                console.error('WPSMD: Verification error details:', errorDetails);

                let errorMessage = wpsmdAnalytics.i18n.verifyError;
                let details = '';

                try {
                    // Try to parse the response as JSON
                    const jsonResponse = JSON.parse(jqXHR.responseText);
                    if (jsonResponse.data && jsonResponse.data.message) {
                        details = jsonResponse.data.message;
                    }
                } catch (e) {
                    // If response is not JSON, check for specific error types
                    if (jqXHR.status === 400) {
                        if (jqXHR.responseText.includes('state parameter')) {
                            details = 'Invalid state parameter. Please clear your browser cache and try the verification process again.';
                        } else if (jqXHR.responseText.includes('redirect_uri_mismatch')) {
                            details = 'Redirect URI mismatch. Please check the OAuth configuration in Google Cloud Console.';
                        } else {
                            details = jqXHR.responseText || 'Bad Request - Please try again';
                        }
                        console.log('WPSMD: Bad Request details:', {
                            responseText: jqXHR.responseText,
                            state: requestData.state,
                            code: !!requestData.code
                        });
                    } else if (jqXHR.status === 401) {
                        details = 'Authentication failed. Please try the verification process again.';
                    } else if (jqXHR.status === 0 && textStatus === 'error') {
                        details = 'Network error. Please check your internet connection and try again.';
                    } else {
                        details = `Error (${jqXHR.status}): ${jqXHR.statusText || 'Unknown error'}`;
                    }
                }

                // Construct a helpful error message with troubleshooting steps
                let finalErrorMessage = details ? `${errorMessage}: ${details}` : errorMessage;
                let troubleshootingSteps = '';

                if (jqXHR.status === 400) {
                    troubleshootingSteps = '\n\nTroubleshooting steps:\n' +
                        '1. Clear your browser cache and cookies\n' +
                        '2. Close all browser windows and reopen\n' +
                        '3. Try the verification process again\n' +
                        '4. If the error persists, check the Google Cloud Console OAuth configuration';
                } else if (jqXHR.status === 401) {
                    troubleshootingSteps = '\n\nTroubleshooting steps:\n' +
                        '1. Ensure you are logged into the correct Google account\n' +
                        '2. Try the verification process again\n' +
                        '3. If the error persists, check if you have the necessary permissions';
                } else if (jqXHR.status === 0) {
                    troubleshootingSteps = '\n\nTroubleshooting steps:\n' +
                        '1. Check your internet connection\n' +
                        '2. Ensure the site is accessible\n' +
                        '3. Try disabling any VPN or proxy services';
                }

                finalErrorMessage += troubleshootingSteps;
                console.log('WPSMD: Displaying error message with troubleshooting steps:', {
                    message: finalErrorMessage,
                    status: jqXHR.status,
                    responseText: jqXHR.responseText
                });

                // Show error message to user
                showNotice(finalErrorMessage, 'error');
                $button.prop('disabled', false).text(originalText);

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