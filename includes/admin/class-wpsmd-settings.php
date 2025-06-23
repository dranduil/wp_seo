<?php
/**
 * Admin settings page for WP SEO Meta Descriptions.
 *
 * @package WP_SEO_Meta_Descriptions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WPSMD_Settings {

    private $options;

    /**
     * Initialize the settings page hooks.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add options page.
     */
    public function add_plugin_settings_page() {
        add_options_page(
            __( 'WP SEO Meta Descriptions Settings', 'wp-seo-meta-descriptions' ),
            __( 'WP SEO Meta', 'wp-seo-meta-descriptions' ),
            'manage_options',
            'wpsmd-settings',
            array( $this, 'create_admin_settings_page' )
        );
    }

    /**
     * Create the settings page.
     */
    public function create_admin_settings_page() {
        $this->options = get_option( 'wpsmd_options' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wpsmd_option_group' );
                do_settings_sections( 'wpsmd-settings-admin' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings.
     */
    public function register_settings() {
        register_setting(
            'wpsmd_option_group', // Option group
            'wpsmd_options', // Option name
            array( $this, 'sanitize_settings' ) // Sanitize callback
        );

        // Google Search Console API Settings
        add_settings_section(
            'wpsmd_gsc_settings_section',
            __('Google Search Console API Settings', 'wp-seo-meta-descriptions'),
            array($this, 'print_gsc_section_info'),
            'wpsmd-settings-admin'
        );

        add_settings_field(
            'gsc_client_id',
            __('Client ID', 'wp-seo-meta-descriptions'),
            array($this, 'gsc_client_id_callback'),
            'wpsmd-settings-admin',
            'wpsmd_gsc_settings_section'
        );

        add_settings_field(
            'gsc_client_secret',
            __('Client Secret', 'wp-seo-meta-descriptions'),
            array($this, 'gsc_client_secret_callback'),
            'wpsmd-settings-admin',
            'wpsmd_gsc_settings_section'
        );

        // OpenAI API Settings
        add_settings_section(
            'wpsmd_openai_settings_section', // ID
            __( 'OpenAI API Settings', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'print_openai_section_info' ), // Callback
            'wpsmd-settings-admin' // Page
        );

        add_settings_field(
            'openai_api_key', // ID
            __( 'OpenAI API Key', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'openai_api_key_callback' ), // Callback
            'wpsmd-settings-admin', // Page
            'wpsmd_openai_settings_section' // Section
        );

        add_settings_field(
            'openai_model', // ID
            __( 'OpenAI Model', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'openai_model_callback' ), // Callback
            'wpsmd-settings-admin', // Page
            'wpsmd_openai_settings_section' // Section
        );

        add_settings_section(
            'wpsmd_auto_generation_settings_section', // ID
            __( 'Auto Generation Settings', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'print_auto_generation_section_info' ), // Callback
            'wpsmd-settings-admin' // Page
        );

        add_settings_field(
            'enable_auto_seo_title', // ID
            __( 'Enable Auto SEO Title', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'enable_auto_seo_title_callback' ), // Callback
            'wpsmd-settings-admin', // Page
            'wpsmd_auto_generation_settings_section' // Section
        );

        add_settings_field(
            'seo_title_template', // ID
            __( 'SEO Title Template', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'seo_title_template_callback' ), // Callback
            'wpsmd-settings-admin', // Page
            'wpsmd_auto_generation_settings_section' // Section
        );

        add_settings_field(
            'enable_auto_seo_description', // ID
            __( 'Enable Auto SEO Description', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'enable_auto_seo_description_callback' ), // Callback
            'wpsmd-settings-admin', // Page
            'wpsmd_auto_generation_settings_section' // Section
        );

        add_settings_field(
            'seo_description_template', // ID
            __( 'SEO Description Template', 'wp-seo-meta-descriptions' ), // Title
            array( $this, 'seo_description_template_callback' ), // Callback
            'wpsmd-settings-admin', // Page
            'wpsmd_auto_generation_settings_section' // Section
        );
    }

    /**
     * Sanitize each setting field as needed.
     *
     * @param array $input Contains all settings fields as array keys.
     */
    /**
     * Print the Google Search Console section info.
     */
    public function print_gsc_section_info() {
        echo '<p>' . esc_html__('Enter your Google Search Console API credentials below. You can obtain these by creating a project in the Google Cloud Console.', 'wp-seo-meta-descriptions') . '</p>';
    }

    /**
     * Callback for the GSC Client ID field.
     */
    public function gsc_client_id_callback() {
        $value = isset($this->options['gsc_client_id']) ? $this->options['gsc_client_id'] : '';
        echo '<input type="text" id="gsc_client_id" name="wpsmd_options[gsc_client_id]" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    /**
     * Callback for the GSC Client Secret field.
     */
    public function gsc_client_secret_callback() {
        $value = isset($this->options['gsc_client_secret']) ? $this->options['gsc_client_secret'] : '';
        echo '<input type="password" id="gsc_client_secret" name="wpsmd_options[gsc_client_secret]" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function sanitize_settings( $input ) {
        $new_input = array();
        if ( isset( $input['openai_api_key'] ) ) {
            $new_input['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] );
        }
        if ( isset( $input['openai_model'] ) ) {
            // Basic sanitization, ensure it's one of the allowed models
            $allowed_models = array( 'gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo-preview' ); // Add more as needed
            if ( in_array( $input['openai_model'], $allowed_models, true ) ) {
                $new_input['openai_model'] = $input['openai_model'];
            } else {
                $new_input['openai_model'] = 'gpt-3.5-turbo'; // Default if invalid
            }
        }
        if ( isset( $input['gsc_client_id'] ) ) {
            $new_input['gsc_client_id'] = sanitize_text_field( $input['gsc_client_id'] );
        }
        if ( isset( $input['gsc_client_secret'] ) ) {
            $new_input['gsc_client_secret'] = sanitize_text_field( $input['gsc_client_secret'] );
        }
        $new_input['enable_auto_seo_title'] = isset( $input['enable_auto_seo_title'] ) ? 1 : 0;
        if ( isset( $input['seo_title_template'] ) ) {
            $new_input['seo_title_template'] = sanitize_text_field( $input['seo_title_template'] );
        }
        $new_input['enable_auto_seo_description'] = isset( $input['enable_auto_seo_description'] ) ? 1 : 0;
        if ( isset( $input['seo_description_template'] ) ) {
            $new_input['seo_description_template'] = sanitize_text_field( $input['seo_description_template'] );
        }
        return $new_input;
    }

    /**
     * Print the Section text.
     */
    public function print_openai_section_info() {
        print __( 'Enter your OpenAI API settings below:', 'wp-seo-meta-descriptions' );
    }

    /**
     * Get the settings option array and print one of its values.
     */
    public function openai_api_key_callback() {
        printf(
            '<input type="text" id="openai_api_key" name="wpsmd_options[openai_api_key]" value="%s" style="width: 50%%;" />',
            isset( $this->options['openai_api_key'] ) ? esc_attr( $this->options['openai_api_key'] ) : ''
        );
        echo '<p class="description">' . __( 'Enter your OpenAI API key. This key will be used for AI-powered features.', 'wp-seo-meta-descriptions' ) . '</p>';
    }

    /**
     * Get the settings option array and print one of its values for OpenAI Model.
     */
    public function openai_model_callback() {
        $current_model = isset( $this->options['openai_model'] ) ? $this->options['openai_model'] : 'gpt-3.5-turbo';
        $models = array(
            'gpt-3.5-turbo' => __('GPT-3.5 Turbo (Recommended)', 'wp-seo-meta-descriptions'),
            'gpt-4' => __('GPT-4 (Advanced)', 'wp-seo-meta-descriptions'),
            'gpt-4-turbo-preview' => __('GPT-4 Turbo Preview (Latest)', 'wp-seo-meta-descriptions'),
            // Add other models as they become available or relevant
        );

        echo '<select id="openai_model" name="wpsmd_options[openai_model]">';
        foreach ( $models as $value => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $value ),
                selected( $current_model, $value, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
        echo '<p class="description">' . __( 'Select the OpenAI model to use for content generation. Different models have varying capabilities and costs.', 'wp-seo-meta-descriptions' ) . '</p>';
    }

    /**
     * Print the Section text for Auto Generation.
     */
    public function print_auto_generation_section_info() {
        print __( 'Configure automatic generation of SEO titles and descriptions:', 'wp-seo-meta-descriptions' );
    }

    /**
     * Callback for Enable Auto SEO Title checkbox.
     */
    public function enable_auto_seo_title_callback() {
        printf(
            '<input type="checkbox" id="enable_auto_seo_title" name="wpsmd_options[enable_auto_seo_title]" value="1" %s />',
            checked( 1, isset( $this->options['enable_auto_seo_title'] ) ? $this->options['enable_auto_seo_title'] : 0, false )
        );
        echo '<label for="enable_auto_seo_title"> ' . __( 'Automatically generate SEO titles if not manually set.', 'wp-seo-meta-descriptions' ) . '</label>';
    }

    /**
     * Callback for Enable Auto SEO Description checkbox.
     */
    /**
     * Callback for SEO Title Template field.
     */
    public function seo_title_template_callback() {
        printf(
            '<input type="text" id="seo_title_template" name="wpsmd_options[seo_title_template]" value="%s" style="width: 100%%;" />',
            isset( $this->options['seo_title_template'] ) ? esc_attr( $this->options['seo_title_template'] ) : '%title% | %sitename%'
        );
        echo '<p class="description">' . __( 'Available variables: %title% (Post/Page Title), %sitename% (Site Name), %category% (Primary Category), %excerpt% (Post Excerpt), %author% (Post Author)', 'wp-seo-meta-descriptions' ) . '</p>';
    }

    /**
     * Callback for SEO Description Template field.
     */
    public function seo_description_template_callback() {
        printf(
            '<input type="text" id="seo_description_template" name="wpsmd_options[seo_description_template]" value="%s" style="width: 100%%;" />',
            isset( $this->options['seo_description_template'] ) ? esc_attr( $this->options['seo_description_template'] ) : '%excerpt%'
        );
        echo '<p class="description">' . __( 'Available variables: %title% (Post/Page Title), %excerpt% (Post Excerpt), %category% (Primary Category), %author% (Post Author)', 'wp-seo-meta-descriptions' ) . '</p>';
    }

    /**
     * Callback for Enable Auto SEO Description checkbox.
     */
    public function enable_auto_seo_description_callback() {
        printf(
            '<input type="checkbox" id="enable_auto_seo_description" name="wpsmd_options[enable_auto_seo_description]" value="1" %s />',
            checked( 1, isset( $this->options['enable_auto_seo_description'] ) ? $this->options['enable_auto_seo_description'] : 0, false )
        );
        echo '<label for="enable_auto_seo_description"> ' . __( 'Automatically generate meta descriptions if not manually set (uses OpenAI if API key is provided and field is empty, otherwise generates from content).', 'wp-seo-meta-descriptions' ) . '</label>';
    }
}

if ( is_admin() ) {
    new WPSMD_Settings();
}