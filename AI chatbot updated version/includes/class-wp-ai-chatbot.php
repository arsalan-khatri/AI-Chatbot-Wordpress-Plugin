<?php
/**
 * The core plugin class.
 * Path: includes/class-wp-ai-chatbot.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'Direct access is not allowed.' );
}

class WP_AI_Chatbot {

    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once WP_AI_CHATBOT_PATH . 'includes/class-admin-settings.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-api-handler.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-chat-renderer.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-form-builder.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-leads-manager.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-quick-actions.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-dynamic-db-manager.php';
        // require_once WP_AI_CHATBOT_PATH . 'includes/class-chatbot-engine.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-memory-manager.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-pre-chat-manager.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-chatbot-customizer.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-rag-manager.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-rag-embeddings.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-rag-engine.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-chat-logger.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-whatsapp-settings.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-guided-flow.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/whatsapp.php';
        require_once WP_AI_CHATBOT_PATH . 'includes/class-dashboard.php';

    }

    private function define_admin_hooks() {
        $plugin_admin = new WP_AI_Chatbot_Admin_Settings();
        add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
        add_action( 'admin_init', array( $plugin_admin, 'register_plugin_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_admin_assets' ) );

        $form_builder = new WP_AI_Chatbot_Form_Builder();
        $form_builder->register_hooks();

        $leads_manager = new WP_AI_Chatbot_Leads_Manager();
        $leads_manager->register_hooks();

        $quick_actions = new WP_AI_Chatbot_Quick_Actions();
        $quick_actions->register_hooks();

        $db_manager = new WP_AI_Chatbot_Dynamic_DB();
        $db_manager->register_hooks();

        $pre_chat_manager = new WP_AI_Chatbot_Pre_Chat_Manager();
        $pre_chat_manager->register_hooks();

        $customizer = new WP_AI_Chatbot_Customizer(); 
        $customizer->register_hooks();

        $rag_manager = new WP_AI_Chatbot_RAG_Manager();
        $rag_manager->register_hooks();

        $rag_embeddings = new WP_AI_Chatbot_RAG_Embeddings();
        $rag_embeddings->register_hooks();

        // FIX: Yahan se Engine aur $user_message wali ghalat lines hata di gayi hain

        $chat_logger = new WP_AI_Chatbot_Logger();
        $chat_logger->register_hooks();

        $wa_settings = new WP_AI_Chatbot_WhatsApp_Settings();
        $wa_settings->register_hooks();

        // quick guide
        $guided_flow = new WP_AI_Chatbot_Guided_Flow();
        $guided_flow->register_hooks();

        // Hooks wale method mein add karein
        $dashboard = new WP_AI_Chatbot_Dashboard();
        $dashboard->register_hooks();
    }

    private function define_public_hooks() {
        $plugin_public = new WP_AI_Chatbot_Chat_Renderer();
        add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_public_assets' ) );
        add_action( 'wp_footer', array( $plugin_public, 'render_chat_widget_html' ) );
        add_action( 'rest_api_init', array( $plugin_public, 'register_chat_rest_route' ) );
    }

    public function run() {
    }
}