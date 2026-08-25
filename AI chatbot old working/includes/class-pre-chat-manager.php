<?php
/**
 * Pre-Chat Form Manager
 * Handles the forced lead capture form before starting the chat.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Pre_Chat_Manager {

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_submenu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_submenu() {
        add_submenu_page(
            'wp-ai-chatbot',
            'Pre-Chat Form',
            'Pre-Chat Form',
            'manage_options',
            'wp-ai-chatbot-pre-chat',
            array( $this, 'display_page' )
        );
    }

    public function register_settings() {
        // Iska option name bilkul alag hai taake API settings se takrao na ho
        register_setting( 'wp_ai_pre_chat_group', 'wp_ai_pre_chat_form_id', 'sanitize_text_field' );
        add_settings_section( 'wp_ai_pre_chat_sec', 'Force Lead Capture Before Chat', array($this, 'section_cb'), 'wp-ai-chatbot-pre-chat' );
        add_settings_field( 'pre_chat_form_id', 'Select Form to Force', array( $this, 'render_dropdown' ), 'wp-ai-chatbot-pre-chat', 'wp_ai_pre_chat_sec' );
    }

    public function section_cb() {
        echo '<p>Select a form here. New users will not be able to chat until they fill out this form.</p>';
    }

    public function render_dropdown() {
        $selected_form = get_option( 'wp_ai_pre_chat_form_id', '' );
        $all_forms = get_option( 'wp_ai_chatbot_multi_forms', array() );

        echo '<select name="wp_ai_pre_chat_form_id" class="regular-text">';
        echo '<option value="">-- None (Normal Chat) --</option>';
        
        if ( is_array( $all_forms ) && ! empty( $all_forms ) ) {
            foreach ( $all_forms as $form ) {
                echo '<option value="' . esc_attr( $form['id'] ) . '" ' . selected( $selected_form, $form['id'], false ) . '>' . esc_html( $form['title'] ) . '</option>';
            }
        }
        echo '</select>';
    }

    public function display_page() {
        ?>
        <div class="wrap">
            <h1>Pre-Chat Form Settings</h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wp_ai_pre_chat_group' );
                do_settings_sections( 'wp-ai-chatbot-pre-chat' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}