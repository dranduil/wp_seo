<?php
/**
 * Analytics functionality for the WP SEO Meta Descriptions plugin.
 *
 * @package WP_SEO_Meta_Descriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Check if composer autoload exists and load it
if (file_exists(WPSMD_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once WPSMD_PLUGIN_PATH . 'vendor/autoload.php';
}

class WPSMD_Analytics {
    /**
     * Initialize the analytics hooks.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_analytics_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_scripts'));
        add_action('wp_ajax_wpsmd_verify_gsc', array($this, 'verify_search_console'));
        add_action('wp_ajax_wpsmd_disconnect_gsc', array($this, 'disconnect_search_console'));
        add_action('wp_ajax_wpsmd_get_search_analytics', array($this, 'get_search_analytics'));
        add_action('wp_ajax_wpsmd_get_crawl_errors', array($this, 'get_crawl_errors'));
        add_action('admin_notices', array($this, 'check_dependencies'));
    }

    /**
     * Disconnect from Google Search Console.
     */
    public function disconnect_search_console() {
        // Verify nonce from state parameter first
        if (!isset($_POST['state'])) {
            wp_send_json_error(array('message' => __('Missing state parameter', 'wp-seo-meta-descriptions')));
            return;
        }

        try {
            $state_data = json_decode(base64_decode(strtr($_POST['state'], '-_', '+/')), true);
            if (!$state_data || !isset($state_data['nonce'])) {
                wp_send_json_error(array('message' => __('Invalid state parameter', 'wp-seo-meta-descriptions')));
                return;
            }

            // Verify the nonce from state parameter
            if (!wp_verify_nonce($state_data['nonce'], 'wpsmd_gsc_auth')) {
                wp_send_json_error(array('message' => __('Invalid nonce in state parameter', 'wp-seo-meta-descriptions')));
                return;
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error processing state parameter', 'wp-seo-meta-descriptions')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-seo-meta-descriptions')));
            return;
        }

        delete_option('wpsmd_gsc_token');
        wp_send_json_success(array('message' => __('Successfully disconnected from Google Search Console', 'wp-seo-meta-descriptions')));
    }

    /**
     * Check if required dependencies are installed.
     */
    public function check_dependencies() {
        if (!file_exists(WPSMD_PLUGIN_PATH . 'vendor/autoload.php')) {
            echo '<div class="notice notice-error"><p>';
            echo __('WP SEO Meta Descriptions plugin requires Composer dependencies to be installed. Please run <code>composer install</code> in the plugin directory.', 'wp-seo-meta-descriptions');
            echo '</p></div>';
        }
    }

    /**
     * Add analytics menu item.
     */
    public function add_analytics_menu() {
        add_submenu_page(
            'tools.php',
            __('SEO Analytics', 'wp-seo-meta-descriptions'),
            __('SEO Analytics', 'wp-seo-meta-descriptions'),
            'manage_options',
            'wpsmd-analytics',
            array($this, 'render_analytics_page')
        );
    }

    /**
     * Enqueue analytics scripts and styles.
     */
    public function enqueue_analytics_scripts($hook) {
        if ('tools_page_wpsmd-analytics' !== $hook) {
            return;
        }

        wp_enqueue_script('wpsmd-analytics-js', 
            plugin_dir_url(__FILE__) . '../../assets/js/analytics.js',
            array('jquery', 'wp-api'), 
            WPSMD_VERSION,
            true
        );

        wp_enqueue_style('wpsmd-analytics-css',
            plugin_dir_url(__FILE__) . '../../assets/css/analytics.css',
            array(),
            WPSMD_VERSION
        );

        $nonce = wp_create_nonce('wpsmd_analytics_nonce');
        error_log('WPSMD: Generated nonce: ' . $nonce);

        wp_localize_script('wpsmd-analytics-js', 'wpsmdAnalytics', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => $nonce,
            'i18n' => array(
                'verifySuccess' => __('Successfully connected to Google Search Console', 'wp-seo-meta-descriptions'),
                'verifyError' => __('Error connecting to Google Search Console', 'wp-seo-meta-descriptions'),
                'loadingData' => __('Loading data...', 'wp-seo-meta-descriptions'),
                'errorLoadingData' => __('Error loading data', 'wp-seo-meta-descriptions'),
                'authRequired' => __('Please authorize access to Google Search Console', 'wp-seo-meta-descriptions'),
                'tokenExpired' => __('Authentication expired. Please reconnect to Google Search Console.', 'wp-seo-meta-descriptions'),
                'alreadyConnected' => __('Already connected to Google Search Console', 'wp-seo-meta-descriptions'),
                'connectionRefreshed' => __('Successfully refreshed Google Search Console connection', 'wp-seo-meta-descriptions'),
                'disconnected' => __('Successfully disconnected from Google Search Console', 'wp-seo-meta-descriptions')
            )
        ));
    }

    /**
     * Render the analytics dashboard page.
     */
    public function render_analytics_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Display the last used redirect URI if available
        $last_redirect_uri = get_transient('wpsmd_last_redirect_uri');
        if ($last_redirect_uri) {
            echo '<div class="notice notice-info is-dismissible"><p>';
            echo sprintf(
                /* translators: %s: Redirect URI */
                esc_html__('Last used redirect URI: %s', 'wp-seo-meta-descriptions'),
                '<code>' . esc_url($last_redirect_uri) . '</code>'
            );
            echo '<br/><strong>' . esc_html__('Important:', 'wp-seo-meta-descriptions') . '</strong> ';
            echo esc_html__('Make sure this exact URL is added to your Google Cloud Console\'s Authorized redirect URIs.', 'wp-seo-meta-descriptions');
            echo '</p></div>';
        }

        // Show success message if just connected and state is valid
        if (isset($_GET['connection']) && $_GET['connection'] === 'success' &&
            isset($_GET['state']) && wp_verify_nonce($_GET['state'], 'wpsmd_gsc_success')) {
            add_settings_error(
                'wpsmd_messages',
                'wpsmd_connection_success',
                __('Successfully connected to Google Search Console', 'wp-seo-meta-descriptions'),
                'updated'
            );
        }

        // Display any settings messages
        settings_errors('wpsmd_messages');

        require_once WPSMD_PLUGIN_PATH . 'includes/admin/views/analytics-dashboard.php';
    }

    /**
     * Verify Google Search Console connection.
     */
    public function verify_search_console() {
        error_log('WPSMD: Starting verify_search_console');
        
        if (!check_ajax_referer('wpsmd_analytics_nonce', 'nonce', false)) {
            error_log('WPSMD: Nonce verification failed');
            wp_send_json_error(array('message' => __('Invalid nonce', 'wp-seo-meta-descriptions')));
            return;
        }

        if (!current_user_can('manage_options')) {
            error_log('WPSMD: Permission check failed');
            wp_send_json_error(array('message' => __('Permission denied', 'wp-seo-meta-descriptions')));
            return;
        }

        error_log('WPSMD: Permission check passed');
        $options = get_option('wpsmd_options');
        error_log('WPSMD: Retrieved options: ' . print_r($options, true));

        $client_id = isset($options['gsc_client_id']) ? $options['gsc_client_id'] : '';
        $client_secret = isset($options['gsc_client_secret']) ? $options['gsc_client_secret'] : '';

        if (empty($client_id) || empty($client_secret)) {
            error_log('WPSMD: Missing GSC credentials - Client ID: ' . (empty($client_id) ? 'empty' : 'set') . ', Client Secret: ' . (empty($client_secret) ? 'empty' : 'set'));
            wp_send_json_error(array('message' => __('Please configure Google Search Console API credentials in settings.', 'wp-seo-meta-descriptions')));
            return;
        }

        error_log('WPSMD: GSC credentials validation passed');

        error_log('WPSMD: Starting Google Client initialization');

        // Check if Google API client is available
        if (!class_exists('Google_Client')) {
            error_log('WPSMD: Google_Client class not found. Checking autoloader...');
            if (!file_exists(WPSMD_PLUGIN_PATH . 'vendor/autoload.php')) {
                error_log('WPSMD: Composer autoload.php not found. Please run composer install');
                wp_send_json_error(array('message' => __('Google API Client not installed. Please contact the administrator.', 'wp-seo-meta-descriptions')));
                return;
            }
            require_once WPSMD_PLUGIN_PATH . 'vendor/autoload.php';
            if (!class_exists('Google_Client')) {
                error_log('WPSMD: Google_Client class still not found after loading autoloader');
                wp_send_json_error(array('message' => __('Google API Client not properly installed. Please contact the administrator.', 'wp-seo-meta-descriptions')));
                return;
            }
        }

        error_log('WPSMD: Google_Client class found, proceeding with initialization');

        try {
            $client = new Google_Client();
            $client->setClientId($client_id);
            $client->setClientSecret($client_secret);
            $client->setScopes(array(
                'https://www.googleapis.com/auth/webmasters.readonly'
            ));
            $client->setAccessType('offline');
            $client->setPrompt('consent');
            
            // Set redirect URI to admin-ajax.php endpoint
            // IMPORTANT: This exact URL must be added to authorized redirect URIs in Google Cloud Console
            $redirect_uri = 'https://unlockthemove.com/wp-admin/admin-ajax.php';
            error_log('WPSMD: Setting redirect URI: ' . $redirect_uri);
            
            // Verify we're using HTTPS in production
            if (strpos($redirect_uri, 'https://') !== 0) {
                error_log('WPSMD: Error - Redirect URI must use HTTPS for unlockthemove.com');
                wp_send_json_error(array('message' => 'Configuration error: HTTPS required'));
                return;
            }
            
            // Log server environment for debugging
            error_log('WPSMD: Server environment:');
            error_log('WPSMD: - HTTP_HOST: ' . $_SERVER['HTTP_HOST']);
            error_log('WPSMD: - REQUEST_SCHEME: ' . $_SERVER['REQUEST_SCHEME']);
            error_log('WPSMD: - HTTP_X_FORWARDED_PROTO: ' . (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : 'not set'));
            error_log('WPSMD: - is_ssl(): ' . (is_ssl() ? 'true' : 'false'));
            
            // Double-check the constructed URI matches Google's requirements
            $expected_uri = 'https://unlockthemove.com/wp-admin/admin-ajax.php';
            if ($redirect_uri !== $expected_uri) {
                error_log('WPSMD: Warning - Redirect URI mismatch:');
                error_log('WPSMD: Constructed URI: ' . $redirect_uri);
                error_log('WPSMD: Expected URI: ' . $expected_uri);
                error_log('WPSMD: Server variables: ' . print_r($_SERVER, true));
            }
            error_log('WPSMD: Setting redirect URI: ' . $redirect_uri);
            error_log('WPSMD: Current SSL status: ' . (is_ssl() ? 'true' : 'false'));
            error_log('WPSMD: Server protocol: ' . (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : $_SERVER['REQUEST_SCHEME']));
            
            $client->setRedirectUri($redirect_uri);
            
            // Add state parameter to track the original request
            $state = array(
                'nonce' => wp_create_nonce('wpsmd_gsc_auth'),
                'action' => 'wpsmd_verify_gsc',
                'page' => 'wpsmd-analytics',
                'timestamp' => time(),
                'site_url' => site_url()
            );
            
            // Verify all values are properly encoded strings
            foreach ($state as $key => $value) {
                if (!is_string($value) && !is_int($value)) {
                    error_log(sprintf('WPSMD: Invalid state value type for %s: %s', $key, gettype($value)));
                    wp_send_json_error(array('message' => 'Internal server error: Invalid state value type'));
                    return;
                }
            }
            
            // Encode with error checking
            $state_json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
            if ($state_json === false) {
                error_log('WPSMD: Error encoding state JSON - ' . json_last_error_msg());
                error_log('WPSMD: State array contents: ' . print_r($state, true));
                wp_send_json_error(array('message' => 'Internal server error: Could not encode state'));
                return;
            }
            
            // Verify the encoded JSON can be decoded
            $test_decode = json_decode($state_json, true);
            if ($test_decode === null) {
                error_log('WPSMD: Error - Generated JSON is invalid: ' . json_last_error_msg());
                error_log('WPSMD: Generated JSON: ' . $state_json);
                wp_send_json_error(array('message' => 'Internal server error: Generated invalid JSON'));
                return;
            }
            // Generate base64 encoded state with proper padding
            $state_base64 = rtrim(base64_encode($state_json), '=');
            error_log('WPSMD: Initial base64 state (without padding): ' . $state_base64);
            error_log('WPSMD: Initial state JSON: ' . $state_json);
            
            // Convert to URL-safe base64 without padding
            $state_base64_urlsafe = strtr($state_base64, '+/', '-_');
            error_log('WPSMD: URL-safe state parameter (without padding): ' . $state_base64_urlsafe);
            
            // URL encode the entire state parameter
            $state_urlencoded = rawurlencode($state_base64_urlsafe);
            error_log('WPSMD: URL-encoded state parameter: ' . $state_urlencoded);
            
            // Validate the state parameter can be decoded correctly
            try {
                // URL decode
                $test_urldecode = rawurldecode($state_urlencoded);
                error_log('WPSMD: Test URL decode: ' . $test_urldecode);
                
                // Convert URL-safe to standard base64 and add padding
                $test_base64 = strtr($test_urldecode, '-_', '+/');
                $padding = strlen($test_base64) % 4;
                if ($padding) {
                    $test_base64 .= str_repeat('=', 4 - $padding);
                }
                error_log('WPSMD: Test base64 (with padding): ' . $test_base64);
                error_log('WPSMD: Validation - converted back to standard base64: ' . $test_base64);
                
                // Attempt decode
                $test_decode = base64_decode($test_base64, true);
                if ($test_decode === false) {
                    throw new Exception('Base64 decode failed');
                }
                error_log('WPSMD: Validation - base64 decoded: ' . $test_decode);
                
                // Verify JSON structure
                $test_json = json_decode($test_decode, true);
                if ($test_json === null) {
                    throw new Exception('JSON decode failed: ' . json_last_error_msg());
                }
                error_log('WPSMD: Validation - JSON decoded: ' . print_r($test_json, true));
                
                // Verify all required fields are present
                foreach (['nonce', 'action', 'timestamp', 'site_url'] as $required_field) {
                    if (!isset($test_json[$required_field])) {
                        throw new Exception('Missing required field: ' . $required_field);
                    }
                }
                
                error_log('WPSMD: State parameter validation successful');
            } catch (Exception $e) {
                error_log('WPSMD: State parameter validation failed: ' . $e->getMessage());
                error_log('WPSMD: Original state JSON: ' . $state_json);
                wp_send_json_error(array('message' => 'Internal server error: Invalid state parameter generation'));
                return;
            }
            
            // Set the validated state parameter
            $client->setState($state_base64_urlsafe);
            error_log('WPSMD: State parameter set on client: ' . $state_base64_urlsafe);
            error_log('WPSMD: Original state data: ' . print_r($state, true));
            
            // Log the authorization URL for debugging
            $auth_url = $client->createAuthUrl();
            error_log('WPSMD: Authorization URL: ' . $auth_url);
            
            // If this is the initial authorization request, return the auth URL
            if (!isset($_GET['code'])) {
                wp_send_json_success(array('auth_url' => $auth_url));
                return;
            }
            
            // Log the exact redirect URI and its components for debugging
            error_log('WPSMD: ====== Redirect URI Debug Info ======');
            error_log('WPSMD: Exact redirect URI to add in Google Console: ' . $redirect_uri);
            error_log('WPSMD: Current site URL: ' . site_url());
            error_log('WPSMD: Current home URL: ' . home_url());
            error_log('WPSMD: Current admin URL: ' . admin_url());
            error_log('WPSMD: WordPress address (URL): ' . get_option('siteurl'));
            error_log('WPSMD: Site address (URL): ' . get_option('home'));
            error_log('WPSMD: Is SSL: ' . (is_ssl() ? 'Yes' : 'No'));
            error_log('WPSMD: SERVER_NAME: ' . $_SERVER['SERVER_NAME']);
            error_log('WPSMD: HTTP_HOST: ' . $_SERVER['HTTP_HOST']);
            error_log('WPSMD: REQUEST_SCHEME: ' . $_SERVER['REQUEST_SCHEME']);
            error_log('WPSMD: ================================');
            
            // Store the redirect URI in a transient for reference
            set_transient('wpsmd_last_redirect_uri', $redirect_uri, HOUR_IN_SECONDS);
            error_log('WPSMD: Parsed redirect URI components: ' . print_r(parse_url($redirect_uri), true));
            
            // Verify the protocol matches what's configured in Google Cloud Console
            if (!is_ssl() && strpos($redirect_uri, 'http://') === 0) {
                error_log('WPSMD: Warning - Using non-HTTPS URL. Make sure this matches Google Cloud Console settings.');
            }
            error_log('WPSMD: Current request URI: ' . $_SERVER['REQUEST_URI']);
            error_log('WPSMD: Current request scheme: ' . $_SERVER['REQUEST_SCHEME']);
            error_log('WPSMD: Current HTTP host: ' . $_SERVER['HTTP_HOST']);
            error_log('WPSMD: Current request method: ' . $_SERVER['REQUEST_METHOD']);
            if (isset($_GET['code'])) {
                error_log('WPSMD: Received code parameter: ' . $_GET['code']);
            }
            if (isset($_GET['error'])) {
                error_log('WPSMD: Received error: ' . $_GET['error']);
                if (isset($_GET['error_description'])) {
                    error_log('WPSMD: Error description: ' . $_GET['error_description']);
                }
            }
            
            // OAuth parameters already set above

            // Check if we already have a token
            $existing_token = get_option('wpsmd_gsc_token');
            if (!empty($existing_token)) {
                $client->setAccessToken($existing_token);
                
                if ($client->isAccessTokenExpired()) {
                    if ($client->getRefreshToken()) {
                        try {
                            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                            update_option('wpsmd_gsc_token', $client->getAccessToken());
                            wp_send_json_success(array('message' => __('Successfully refreshed Google Search Console connection', 'wp-seo-meta-descriptions')));
                            return;
                        } catch (Exception $e) {
                            error_log('WPSMD: Token refresh failed: ' . $e->getMessage());
                            delete_option('wpsmd_gsc_token');
                        }
                    } else {
                        error_log('WPSMD: Token expired and no refresh token available');
                        delete_option('wpsmd_gsc_token');
                    }
                } else {
                    wp_send_json_success(array('message' => __('Already connected to Google Search Console', 'wp-seo-meta-descriptions')));
                    return;
                }
            }

            // Handle the OAuth 2.0 flow
            if (isset($_POST['code']) || isset($_GET['code'])) {
                $auth_code = isset($_POST['code']) ? $_POST['code'] : $_GET['code'];
                $state = isset($_POST['state']) ? $_POST['state'] : (isset($_GET['state']) ? $_GET['state'] : null);
                
                error_log('WPSMD: Processing OAuth callback');
                error_log('WPSMD: Auth code: ' . $auth_code);
                error_log('WPSMD: State: ' . ($state ?? 'null'));
                
                // Verify state parameter to prevent CSRF
                if (!$state) {
                    error_log('WPSMD: Missing state parameter');
                    wp_send_json_error(array('message' => __('Missing state parameter. Please try again.', 'wp-seo-meta-descriptions')));
                    return;
                }

                try {
                    error_log('WPSMD: Raw state parameter received: ' . $state);
                    error_log('WPSMD: Full request details:');
                    error_log('WPSMD: REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
                    
                    // First URL decode the state parameter
                    $url_decoded_state = rawurldecode($state);
                    error_log('WPSMD: URL-decoded state: ' . $url_decoded_state);
                    
                    // Convert URL-safe base64 to standard base64 and add padding
                    $standard_base64 = strtr($url_decoded_state, '-_', '+/');
                    $padding = strlen($standard_base64) % 4;
                    if ($padding) {
                        $standard_base64 .= str_repeat('=', 4 - $padding);
                    }
                    error_log('WPSMD: Standard base64 with padding: ' . $standard_base64);
                    
                    // Attempt base64 decode
                    $decoded_data = base64_decode($standard_base64, true);
                    if ($decoded_data === false) {
                        wp_send_json_error(array(
                            'message' => __('Invalid state parameter format. Please try the authentication process again.', 'wp-seo-meta-descriptions'),
                            'details' => 'Base64 decode failed'
                        ));
                        return;
                    }
                    error_log('WPSMD: Base64 decode successful, length: ' . strlen($decoded_data));
                    
                    // Attempt JSON decode
                    $state_data = json_decode($decoded_data, true);
                    if ($state_data === null) {
                        wp_send_json_error(array(
                            'message' => __('Invalid state parameter structure. Please try the authentication process again.', 'wp-seo-meta-descriptions'),
                            'details' => 'JSON decode failed: ' . json_last_error_msg()
                        ));
                        return;
                    }
                    error_log('WPSMD: JSON decode successful: ' . print_r($state_data, true));
                    
                    // Validate required fields in state data
                    $required_fields = ['nonce', 'action', 'timestamp', 'site_url'];
                    foreach ($required_fields as $field) {
                        if (!isset($state_data[$field])) {
                            wp_send_json_error(array(
                                'message' => __('Invalid state parameter content. Please try the authentication process again.', 'wp-seo-meta-descriptions'),
                                'details' => "Missing required field: {$field}"
                            ));
                            return;
                        }
                    }

                    // Verify the nonce from state parameter
                    if (!wp_verify_nonce($state_data['nonce'], 'wpsmd_gsc_auth')) {
                        error_log('WPSMD: Invalid nonce in state parameter');
                        wp_send_json_error(array(
                            'message' => __('Invalid security token. Please try the authentication process again.', 'wp-seo-meta-descriptions'),
                            'details' => 'Invalid nonce'
                        ));
                        return;
                    }
                    error_log('WPSMD: Nonce verification successful');
                    }
                    error_log('WPSMD: All required fields present in state data');
                    
                    // Validate timestamp format and expiration
                    $timestamp = intval($state_data['timestamp']);
                    if ($timestamp <= 0) {
                        wp_send_json_error(array(
                            'message' => __('Invalid state parameter timestamp. Please try the authentication process again.', 'wp-seo-meta-descriptions'),
                            'details' => 'Invalid timestamp format'
                        ));
                        return;
                    }
                    
                    $current_time = time();
                    $time_diff = abs($current_time - $timestamp);
                    $max_age = 30 * 60; // 30 minutes
                    
                    if ($time_diff > $max_age) {
                        error_log(sprintf('WPSMD: State expired. Current time: %d, State time: %d, Diff: %d seconds', 
                            $current_time, $timestamp, $time_diff));
                        wp_send_json_error(array(
                            'message' => __('Your authentication session has expired. Please try the authentication process again.', 'wp-seo-meta-descriptions'),
                            'details' => sprintf('State expired. Time difference: %d seconds', $time_diff)
                        ));
                        return;
                    }
                    
                    error_log('WPSMD: State timestamp validation passed');
                    error_log(sprintf('WPSMD: Time difference: %d seconds (max allowed: %d)', $time_diff, $max_age));
                    
                    // Validate site URL matches
                    $site_url = rtrim($state_data['site_url'], '/');
                    $current_site_url = rtrim(get_site_url(), '/');
                    
                    if ($site_url !== $current_site_url) {
                        error_log(sprintf('WPSMD: Site URL mismatch. Expected: %s, Got: %s', 
                            $current_site_url, $site_url));
                        wp_send_json_error(array(
                            'message' => __('Invalid site URL in state parameter. Please ensure you are on the correct site.', 'wp-seo-meta-descriptions'),
                            'details' => 'Site URL mismatch'
                        ));
                        return;
                    }
                    
                    error_log('WPSMD: Site URL validation passed');
                    error_log('WPSMD: State validation completed successfully');
                    
                    // Store validated state data for further processing
                    $state_json = json_encode($state_data);
                    }
                    
                    error_log('WPSMD: State JSON string: ' . $state_json);
                    $state = json_decode($state_json, true);
                    
                    if (!$state || !is_array($state)) {
                        error_log('WPSMD: Failed to decode state JSON: ' . json_last_error_msg());
                        wp_send_json_error(array('message' => __('Invalid state format. Please try again.', 'wp-seo-meta-descriptions')));
                        return;
                    }
                    error_log('WPSMD: Parsed state: ' . print_r($state, true));
                    
                    if (!isset($state['nonce']) || !wp_verify_nonce($state['nonce'], 'wpsmd_gsc_auth')) {
                        error_log('WPSMD: Invalid state nonce');
                        wp_send_json_error(array('message' => __('Invalid OAuth state. Please try again.', 'wp-seo-meta-descriptions')));
                        return;
                    }
                    
                    // Verify site URL matches
                    if (!isset($state['site_url']) || $state['site_url'] !== site_url()) {
                        error_log('WPSMD: Site URL mismatch. Expected: ' . site_url() . ', Got: ' . ($state['site_url'] ?? 'not set'));
                        wp_send_json_error(array('message' => __('Invalid site URL in state. Please try again.', 'wp-seo-meta-descriptions')));
                        return;
                    }
                    
                    // Check timestamp (optional: expire after 1 hour)
                    if (!isset($state['timestamp']) || (time() - $state['timestamp']) > 3600) {
                        error_log('WPSMD: State timestamp expired or invalid');
                        wp_send_json_error(array('message' => __('Authorization request expired. Please try again.', 'wp-seo-meta-descriptions')));
                        return;
                    }

                    // Set the action and page parameters from the state
                    $_GET['action'] = $state['action'];
                    $_GET['page'] = $state['page'];
                } catch (Exception $e) {
                    error_log('WPSMD: Error decoding state: ' . $e->getMessage());
                    wp_send_json_error(array('message' => __('Invalid state format. Please try again.', 'wp-seo-meta-descriptions')));
                    return;
                }

                try {
                    error_log('WPSMD: Received authorization code, attempting to fetch token');
                    error_log('WPSMD: Authorization code: ' . $auth_code);
                    error_log('WPSMD: Redirect URI set to: ' . $client->getRedirectUri());
                    
                    try {
                        // Log the complete request details
                        error_log('WPSMD: Complete request details:');
                        error_log('WPSMD: Auth code: ' . $auth_code);
                        error_log('WPSMD: Redirect URI: ' . $client->getRedirectUri());
                        error_log('WPSMD: Client ID: ' . substr($client->getClientId(), 0, 8) . '...');
                        
                        // Use dynamic site URL for redirect URI
                        $redirect_uri = admin_url('admin-ajax.php');
                        $client->setRedirectUri($redirect_uri);
                        
                        // Verify the redirect URI matches exactly
                        if ($client->getRedirectUri() !== $redirect_uri) {
                            error_log('WPSMD: Critical - Redirect URI mismatch after setting:');
                            error_log('WPSMD: Expected: ' . $redirect_uri);
                            error_log('WPSMD: Actual: ' . $client->getRedirectUri());
                            throw new Exception('Redirect URI configuration error');
                        }
                        
                        error_log('WPSMD: Token exchange configuration:');
                        error_log('WPSMD: - Redirect URI: ' . $redirect_uri);
                        error_log('WPSMD: - Auth Code Length: ' . strlen($auth_code));
                        error_log('WPSMD: - Auth Code Format Valid: ' . (preg_match('/^[\w\/\-]+$/', $auth_code) ? 'yes' : 'no'));
                        
                        // Verify the authorization code format
                        if (!preg_match('/^[\w\/\-]+$/', $auth_code)) {
                            error_log('WPSMD: Invalid authorization code format');
                            throw new Exception('Invalid authorization code format');
                        }
                        
                        // Log the complete exchange request
                        error_log('WPSMD: Token exchange request:');
                        error_log('WPSMD: - Grant Type: authorization_code');
                        error_log('WPSMD: - Client ID: ' . substr($client->getClientId(), 0, 8) . '...');
                        error_log('WPSMD: - Redirect URI: ' . $redirect_uri);
                        error_log('WPSMD: - Scopes: ' . implode(', ', $client->getScopes()));
                        error_log('WPSMD: - REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
                        
                        // Verify client configuration before token fetch
                        error_log('WPSMD: Client configuration before token fetch:');
                        error_log('WPSMD: - Client ID: ' . substr($client->getClientId(), 0, 8) . '...');
                        error_log('WPSMD: - Redirect URI: ' . $client->getRedirectUri());
                        error_log('WPSMD: - Auth Code: ' . substr($auth_code, 0, 8) . '...');
                        
                        // Fetch the token
                        $token = $client->fetchAccessTokenWithAuthCode($auth_code);
                        error_log('WPSMD: Token fetch attempt completed');
                        error_log('WPSMD: Raw token response: ' . print_r($token, true));
                    } catch (Google_Service_Exception $e) {
                        error_log('WPSMD: Google Service Exception while fetching token:');
                        error_log('WPSMD: - Message: ' . $e->getMessage());
                        error_log('WPSMD: - Code: ' . $e->getCode());
                        
                        // Get the error details from the response
                        $error_details = $e->getErrors();
                        error_log('WPSMD: Error details: ' . print_r($error_details, true));
                        
                        if ($e->getCode() === 400) {
                            error_log('WPSMD: 400 Bad Request - Checking specific error conditions');
                            
                            // Check for redirect_uri_mismatch
                            if (strpos($e->getMessage(), 'redirect_uri_mismatch') !== false) {
                                error_log('WPSMD: Detected redirect_uri_mismatch error');
                                $error_message = 'Redirect URI mismatch. Please ensure the following URI is configured in Google Cloud Console:\n' .
                                                'https://unlockthemove.com/wp-admin/admin-ajax.php\n\n' .
                                                'Troubleshooting steps:\n' .
                                                '1. Go to Google Cloud Console\n' .
                                                '2. Navigate to APIs & Services > Credentials\n' .
                                                '3. Edit your OAuth 2.0 Client ID\n' .
                                                '4. Add or update the authorized redirect URI\n' .
                                                '5. Ensure it matches exactly (including https:// and no trailing slash)';
                            } else {
                                $error_message = 'Invalid request. Please check the following:\n' .
                                                '1. Authorization code is valid and not expired\n' .
                                                '2. The request hasn\'t been used before\n' .
                                                '3. All required parameters are present';
                            }
                        } else {
                            $error_message = $e->getMessage();
                        }
                    } catch (Exception $e) {
                        error_log('WPSMD: General Exception while fetching token:');
                        error_log('WPSMD: - Message: ' . $e->getMessage());
                        error_log('WPSMD: - Trace: ' . $e->getTraceAsString());
                        error_log('WPSMD: - Request headers: ' . print_r(getallheaders(), true));
                        
                        $error_message = $e->getMessage();
                        error_log('WPSMD: Error message: ' . $error_message);
                        
                        if ($e instanceof Google_Service_Exception) {
                            $error_data = json_decode($error_message, true);
                            error_log('WPSMD: Google Service Exception details: ' . print_r($error_data, true));
                            
                            if (isset($error_data['error'])) {
                                if (is_string($error_data['error'])) {
                                    $error_message = $error_data['error'];
                                } else if (isset($error_data['error']['message'])) {
                                    $error_message = $error_data['error']['message'];
                                }
                            }
                        } else if (strpos($error_message, '400') !== false || strpos($error_message, 'redirect_uri_mismatch') !== false) {
                            error_log('WPSMD: 400 Bad Request or redirect_uri_mismatch detected');
                            error_log('WPSMD: Current redirect URI: ' . $redirect_uri);
                            error_log('WPSMD: Request scheme: ' . $_SERVER['REQUEST_SCHEME']);
                            error_log('WPSMD: HTTP_X_FORWARDED_PROTO: ' . (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : 'not set'));
                            
                            $protocol = is_ssl() ? 'https://' : 'http://';
                            $expected_uri = $protocol . $_SERVER['HTTP_HOST'] . '/wp-admin/admin-ajax.php';
                            
                            $error_message = sprintf(
                                'Redirect URI mismatch. Please configure the following URI exactly in your Google Cloud Console (APIs & Services > Credentials > OAuth 2.0 Client IDs > Authorized redirect URIs):\n\n%s\n\nCurrent configuration issues:\n1. Your redirect URI must match exactly (check for HTTP vs HTTPS)\n2. Remove any trailing slashes or extra parameters\n3. Verify no typos or missing characters\n\nExpected URI format: %s\nCurrent URI: %s',
                                $expected_uri,
                                $expected_uri,
                                $redirect_uri
                            );
                        }
                        
                        wp_send_json_error(array(
                            'message' => sprintf(
                                __('Error connecting to Google Search Console: %s. Please verify your Google Cloud Console configuration.', 'wp-seo-meta-descriptions'),
                                $error_message
                            )
                        ));
                        return;
                    }
                    
                    if (!is_array($token)) {
                        error_log('WPSMD: Invalid token response type: ' . gettype($token));
                        wp_send_json_error(array('message' => __('Invalid token response from Google. Please try again.', 'wp-seo-meta-descriptions')));
                        return;
                    }

                    if (isset($token['error'])) {
                        error_log('WPSMD: Token fetch error: ' . $token['error']);
                        $error_message = isset($token['error_description']) ? $token['error_description'] : $token['error'];
                        wp_send_json_error(array('message' => sprintf(__('Error fetching access token: %s', 'wp-seo-meta-descriptions'), $error_message)));
                        return;
                    }

                    if (!isset($token['access_token'])) {
                        error_log('WPSMD: Missing access_token in response: ' . print_r($token, true));
                        wp_send_json_error(array('message' => __('Invalid token response: missing access token', 'wp-seo-meta-descriptions')));
                        return;
                    }
                    
                    error_log('WPSMD: Token fetched successfully: ' . print_r($token, true));
                    update_option('wpsmd_gsc_token', $token);
                    
                    // Set the access token on the client for immediate use
                    $client->setAccessToken($token);
                    
                    // Verify the token works by making a test API call
                    $searchConsole = new Google_Service_SearchConsole($client);
                    $siteUrl = get_site_url();
                    
                    try {
                        // First check if the site is already verified
                        $site = $searchConsole->sites->get($siteUrl);
                        error_log('WPSMD: Site verification status: ' . print_r($site->permissionLevel, true));
                        
                        if ($site->permissionLevel === 'siteUnverifiedUser') {
                            error_log('WPSMD: Site is not verified');
                            delete_option('wpsmd_gsc_token');
                            wp_send_json_error(array('message' => __('Please verify your site in Google Search Console first.', 'wp-seo-meta-descriptions')));
                            return;
                        }
                    } catch (Google_Service_Exception $e) {
                        error_log('WPSMD: Site verification check failed: ' . $e->getMessage());
                        if ($e->getCode() === 404) {
                            delete_option('wpsmd_gsc_token');
                            wp_send_json_error(array('message' => __('Your site is not found in Google Search Console. Please add and verify it first.', 'wp-seo-meta-descriptions')));
                            return;
                        }
                        throw $e;
                    }
                    
                    error_log('WPSMD: Test API call successful');
                    
                    // Return success response with redirect URL
                    $redirect_url = add_query_arg(
                        array(
                            'page' => 'wpsmd-analytics',
                            'connection' => 'success',
                            'timestamp' => time()
                        ),
                        admin_url('tools.php')
                    );
                    
                    wp_send_json_success(array(
                        'message' => __('Successfully connected to Google Search Console', 'wp-seo-meta-descriptions'),
                        'redirect_url' => $redirect_url
                    ));
                    return;
                    
                    wp_send_json_success(array(
                        'message' => __('Successfully connected to Google Search Console', 'wp-seo-meta-descriptions'),
                        'reload' => true
                    ));
                } catch (Exception $e) {
                    error_log('WPSMD: Token fetch/verification exception: ' . $e->getMessage());
                    delete_option('wpsmd_gsc_token'); // Clean up failed token
                    wp_send_json_error(array('message' => __('Error connecting to Google Search Console. Please try again.', 'wp-seo-meta-descriptions')));
                }
            } else {
                $auth_url = $client->createAuthUrl();
                error_log('WPSMD: Generated auth URL: ' . $auth_url);
                wp_send_json_success(array(
                    'auth_url' => $auth_url,
                    'message' => __('Please authorize access to Google Search Console', 'wp-seo-meta-descriptions')
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Get search analytics data from Google Search Console.
     */
    public function get_search_analytics() {
        check_ajax_referer('wpsmd_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-seo-meta-descriptions')));
            return;
        }

        $token = get_option('wpsmd_gsc_token');
        if (empty($token)) {
            wp_send_json_error(array('message' => __('Please connect to Google Search Console first.', 'wp-seo-meta-descriptions')));
            return;
        }

        require_once WPSMD_PLUGIN_PATH . 'vendor/autoload.php';

        try {
            $client = new Google_Client();
            $client->setAccessToken($token);

            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    update_option('wpsmd_gsc_token', $client->getAccessToken());
                } else {
                    wp_send_json_error(array('message' => __('Authentication expired. Please reconnect to Google Search Console.', 'wp-seo-meta-descriptions')));
                    return;
                }
            }

            $searchConsole = new Google_Service_SearchConsole($client);
            $siteUrl = get_site_url();

            // Get search analytics data for the last 30 days
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-30 days'));

            $request = new Google_Service_SearchConsole_SearchAnalyticsQueryRequest();
            $request->setStartDate($startDate);
            $request->setEndDate($endDate);
            $request->setDimensions(['query']);
            $request->setRowLimit(10);

            $response = $searchConsole->searchanalytics->query($siteUrl, $request);
            $rows = $response->getRows();

            $total_clicks = 0;
            $total_impressions = 0;
            $total_position = 0;
            $keywords = array();

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $total_clicks += $row->getClicks();
                    $total_impressions += $row->getImpressions();
                    $total_position += $row->getPosition();

                    $keywords[] = array(
                        'term' => $row->getKeys()[0],
                        'clicks' => $row->getClicks(),
                        'impressions' => $row->getImpressions(),
                        'position' => $row->getPosition(),
                        'ctr' => $row->getCtr()
                    );
                }
            }

            $avg_position = count($rows) > 0 ? $total_position / count($rows) : 0;
            $ctr = $total_impressions > 0 ? $total_clicks / $total_impressions : 0;

            wp_send_json_success(array(
                'clicks' => $total_clicks,
                'impressions' => $total_impressions,
                'position' => $avg_position,
                'ctr' => $ctr,
                'keywords' => $keywords
            ));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Get crawl errors from Google Search Console.
     */
    public function get_crawl_errors() {
        check_ajax_referer('wpsmd_analytics_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wp-seo-meta-descriptions')));
            return;
        }

        $token = get_option('wpsmd_gsc_token');
        if (empty($token)) {
            wp_send_json_error(array('message' => __('Please connect to Google Search Console first.', 'wp-seo-meta-descriptions')));
            return;
        }

        require_once WPSMD_PLUGIN_PATH . 'vendor/autoload.php';

        try {
            $client = new Google_Client();
            $client->setAccessToken($token);

            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    update_option('wpsmd_gsc_token', $client->getAccessToken());
                } else {
                    wp_send_json_error(array('message' => __('Authentication expired. Please reconnect to Google Search Console.', 'wp-seo-meta-descriptions')));
                    return;
                }
            }

            $searchConsole = new Google_Service_SearchConsole($client);
            $siteUrl = get_site_url();

            // Get indexing issues
            $request = new Google_Service_SearchConsole_InspectUrlIndexRequest();
            $request->setInspectionUrl($siteUrl);
            $response = $searchConsole->urlInspection_index->inspect($request);

            $errors = array();
            $inspectionResult = $response->getInspectionResult();

            if ($inspectionResult) {
                $indexingState = $inspectionResult->getIndexingState();
                if ($indexingState && $indexingState->getPageFetchState() !== 'SUCCESSFUL') {
                    $errors[] = array(
                        'url' => $siteUrl,
                        'type' => 'FETCH_ERROR',
                        'detected' => current_time('mysql')
                    );
                }

                if ($indexingState && !$indexingState->getIsIndexed()) {
                    $errors[] = array(
                        'url' => $siteUrl,
                        'type' => 'NOT_INDEXED',
                        'detected' => current_time('mysql')
                    );
                }

                $mobileFriendliness = $inspectionResult->getMobileFriendliness();
                if ($mobileFriendliness && $mobileFriendliness->getVerdict() !== 'MOBILE_FRIENDLY') {
                    $errors[] = array(
                        'url' => $siteUrl,
                        'type' => 'NOT_MOBILE_FRIENDLY',
                        'detected' => current_time('mysql')
                    );
                }
            }

            wp_send_json_success(array('errors' => $errors));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}