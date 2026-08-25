<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Admin_Settings {

    private $option_name = 'wp_ai_chatbot_settings';

    public function add_plugin_admin_menu() {
        // Main menu
        add_menu_page( 'WP AI Chatbot Settings', 'AI Chatbot', 'manage_options', 'wp-ai-chatbot', array( $this, 'display_plugin_settings_page' ), 'dashicons-format-chat', 30 );
    }

    public function register_plugin_settings() {
        register_setting( 'wp_ai_chatbot_options_group', $this->option_name, array( $this, 'sanitize_settings' ) );

        // Sirf API aur Memory ki settings reh gayi hain
        add_settings_section( 'wp_ai_chatbot_api_section', 'API & Model Configuration', array( $this, 'api_section_callback' ), 'wp-ai-chatbot-api' );
        add_settings_field( 'api_provider', 'Select AI Provider', array( $this, 'render_provider_field' ), 'wp-ai-chatbot-api', 'wp_ai_chatbot_api_section' );
        add_settings_field( 'openai_api_key', 'OpenAI API Key', array( $this, 'render_openai_key_field' ), 'wp-ai-chatbot-api', 'wp_ai_chatbot_api_section' );
        add_settings_field( 'openai_model', 'OpenAI Model Name', array( $this, 'render_openai_model_field' ), 'wp-ai-chatbot-api', 'wp_ai_chatbot_api_section' );
        add_settings_field( 'gemini_api_key', 'Gemini API Key', array( $this, 'render_gemini_key_field' ), 'wp-ai-chatbot-api', 'wp_ai_chatbot_api_section' );
        add_settings_field( 'gemini_model', 'Gemini Model Name', array( $this, 'render_gemini_model_field' ), 'wp-ai-chatbot-api', 'wp_ai_chatbot_api_section' );
        add_settings_field( 'system_prompt', 'System Prompt (Context)', array( $this, 'render_system_prompt_field' ), 'wp-ai-chatbot-api', 'wp_ai_chatbot_api_section' );
        add_settings_field( 'history_limit', 'Context Window (Memory Limit)', array( $this, 'render_history_limit_field' ), 'wp-ai-chatbot-api', 'wp_ai_chatbot_api_section' );
    }

    public function sanitize_settings( $input ) {
        $sanitized = get_option( $this->option_name, array() );
        if ( isset( $input['api_provider'] ) ) $sanitized['api_provider'] = sanitize_text_field( $input['api_provider'] );
        if ( isset( $input['openai_api_key'] ) ) $sanitized['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] );
        if ( isset( $input['openai_model'] ) ) $sanitized['openai_model'] = sanitize_text_field( $input['openai_model'] );
        if ( isset( $input['gemini_api_key'] ) ) $sanitized['gemini_api_key'] = sanitize_text_field( $input['gemini_api_key'] );
        if ( isset( $input['gemini_model'] ) ) $sanitized['gemini_model'] = sanitize_text_field( $input['gemini_model'] );
        if ( isset( $input['system_prompt'] ) ) $sanitized['system_prompt'] = sanitize_textarea_field( $input['system_prompt'] );
        if ( isset( $input['history_limit'] ) ) $sanitized['history_limit'] = intval( $input['history_limit'] );
        return $sanitized;
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== 'toplevel_page_wp-ai-chatbot' ) return;
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_style( 'wp-ai-chatbot-admin-style', WP_AI_CHATBOT_URL . 'admin/css/admin-style.css', array(), WP_AI_CHATBOT_VERSION, 'all' );
        wp_enqueue_script( 'wp-ai-chatbot-admin-script', WP_AI_CHATBOT_URL . 'admin/js/admin-script.js', array( 'jquery', 'wp-color-picker' ), WP_AI_CHATBOT_VERSION, true );
    }

    public function api_section_callback() { echo '<p>Configure your OpenAI or Gemini API keys and set the base system prompt.</p>'; }
    public function render_provider_field() {
        $options = get_option( $this->option_name );
        $provider = isset( $options['api_provider'] ) ? $options['api_provider'] : 'gemini';
        echo '<select name="wp_ai_chatbot_settings[api_provider]">
                <option value="gemini" ' . selected( $provider, 'gemini', false ) . '>Google Gemini</option>
                <option value="openai" ' . selected( $provider, 'openai', false ) . '>OpenAI</option>
              </select>';
    }
    public function render_openai_key_field() { $options = get_option( $this->option_name ); echo '<input type="password" name="wp_ai_chatbot_settings[openai_api_key]" value="' . esc_attr( isset($options['openai_api_key']) ? $options['openai_api_key'] : '' ) . '" class="regular-text" />'; }
    public function render_openai_model_field() { $options = get_option( $this->option_name ); echo '<input type="text" name="wp_ai_chatbot_settings[openai_model]" value="' . esc_attr( isset($options['openai_model']) ? $options['openai_model'] : 'gpt-4o-mini' ) . '" class="regular-text" />'; }
    public function render_gemini_key_field() { $options = get_option( $this->option_name ); echo '<input type="password" name="wp_ai_chatbot_settings[gemini_api_key]" value="' . esc_attr( isset($options['gemini_api_key']) ? $options['gemini_api_key'] : '' ) . '" class="regular-text" />'; }
    public function render_gemini_model_field() { $options = get_option( $this->option_name ); echo '<input type="text" name="wp_ai_chatbot_settings[gemini_model]" value="' . esc_attr( isset($options['gemini_model']) ? $options['gemini_model'] : 'gemini-1.5-flash' ) . '" class="regular-text" />'; }
    public function render_system_prompt_field() { $options = get_option( $this->option_name ); echo '<textarea name="wp_ai_chatbot_settings[system_prompt]" rows="5" class="large-text code">' . esc_textarea( isset($options['system_prompt']) ? $options['system_prompt'] : '' ) . '</textarea>'; }
    public function render_history_limit_field() { $options = get_option( $this->option_name ); $limit = isset($options['history_limit']) ? esc_attr($options['history_limit']) : '6'; echo '<input type="number" name="wp_ai_chatbot_settings[history_limit]" value="' . $limit . '" class="regular-text" min="0" max="50" style="width: 100px;" />'; }

    public function display_plugin_settings_page() {
        ?>
        <div class="wrap">
            <h1>WP AI Chatbot Configuration</h1>
            
            <?php settings_errors(); ?> 

            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-ai-chatbot" class="nav-tab nav-tab-active">API & Models</a>
            </h2>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wp_ai_chatbot_options_group' );
                do_settings_sections( 'wp-ai-chatbot-api' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}