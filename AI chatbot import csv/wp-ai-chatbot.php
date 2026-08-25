<?php
/**
 * Plugin Name:       WP AI Chatbot (Pro)
 * Plugin URI:        https://github.com/devarsalankhatri/wp-ai-chatbot
 * Description:       An enterprise-grade AI Chatbot supporting OpenAI, Gemini, and custom appearance settings. Ready for RAG integration.
 * Version:           1.0.0
 * Author:            Arsalan Khatri
 * Author URI:        https://github.com/devarsalankhatri
 * License:           GPL-2.0+
 * Text Domain:       wp-ai-chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'Direct access is not allowed.' );
}

define( 'WP_AI_CHATBOT_VERSION', '1.0.0' );
define( 'WP_AI_CHATBOT_FILE', __FILE__ );
define( 'WP_AI_CHATBOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_AI_CHATBOT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Activation Hook
 */
function activate_wp_ai_chatbot() {
    $default_settings = array(
        'api_provider'  => 'gemini',
        'gemini_model'  => 'gemini-1.5-flash',
        'openai_model'  => 'gpt-4o-mini',
        'system_prompt' => 'You are a helpful customer support assistant.',
        'primary_color' => '#0071a1',
        'bot_name'      => 'AI Assistant',
        'bot_icon'      => ''
    );

    if ( false === get_option( 'wp_ai_chatbot_settings' ) ) {
        add_option( 'wp_ai_chatbot_settings', $default_settings );
    }

    wp_ai_chatbot_create_table();
    flush_rewrite_rules();
}
register_activation_hook( WP_AI_CHATBOT_FILE, 'activate_wp_ai_chatbot' );

/**
 * Creates the database table for leads
 */
function wp_ai_chatbot_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_chatbot_leads';
    $charset_collate = $wpdb->get_charset_collate();

    // form_name column ab alag hai — hybrid approach
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        form_name varchar(255) NOT NULL DEFAULT '',
        form_data text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Migration: agar purani table hai toh form_name column add karo
    $columns = $wpdb->get_col( "DESCRIBE {$table_name}", 0 );
    if ( ! in_array( 'form_name', $columns ) ) {
        $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN form_name VARCHAR(255) NOT NULL DEFAULT '' AFTER id" );
        // Purani rows mein form_data se Form Purpose nikal ke form_name mein daalo
        $rows = $wpdb->get_results( "SELECT id, form_data FROM {$table_name}" );
        foreach ( $rows as $row ) {
            $data = json_decode( $row->form_data, true );
            if ( isset( $data['Form Purpose'] ) ) {
                $form_name = $data['Form Purpose'];
                unset( $data['Form Purpose'] );
                $wpdb->update(
                    $table_name,
                    array( 'form_name' => $form_name, 'form_data' => wp_json_encode( $data ) ),
                    array( 'id' => $row->id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
            }
        }
    }
}

/**
 * BULLETPROOF FIX: Auto-create table if it doesn't exist.
 * Yeh function is baat ki guarantee dega ke agar aap plugin deactivate/activate karna 
 * bhool bhi jayen, toh admin panel khulte hi table khud ba khud ban jayegi.
 */
add_action('admin_init', function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_chatbot_leads';
    if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {
        wp_ai_chatbot_create_table();
    }
});

function deactivate_wp_ai_chatbot() {
    flush_rewrite_rules();
}
register_deactivation_hook( WP_AI_CHATBOT_FILE, 'deactivate_wp_ai_chatbot' );

function run_wp_ai_chatbot() {
    if ( file_exists( WP_AI_CHATBOT_PATH . 'includes/class-wp-ai-chatbot.php' ) ) {
        require_once WP_AI_CHATBOT_PATH . 'includes/class-wp-ai-chatbot.php';
        $plugin = new WP_AI_Chatbot();
        $plugin->run();
    }
}
add_action( 'plugins_loaded', 'run_wp_ai_chatbot' );