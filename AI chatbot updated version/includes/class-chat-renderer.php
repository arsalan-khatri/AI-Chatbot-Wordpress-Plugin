<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Chat_Renderer {

    private $settings;
    private $multi_forms;

    public function __construct() {
        $this->settings    = get_option( 'wp_ai_chatbot_settings', array() );
        $this->multi_forms = get_option( 'wp_ai_chatbot_multi_forms', array() );
    }

    public function enqueue_public_assets() {
        wp_enqueue_style( 'wp-ai-chat-widget-style', WP_AI_CHATBOT_URL . 'public/css/chat-widget.css', array(), WP_AI_CHATBOT_VERSION );
        wp_enqueue_script( 'marked-js', 'https://cdnjs.cloudflare.com/ajax/libs/marked/4.0.2/marked.min.js', array(), '4.0.2', true );
        wp_enqueue_script( 'wp-ai-chat-widget-script', WP_AI_CHATBOT_URL . 'public/js/chat-widget.js', array('jquery', 'marked-js'), WP_AI_CHATBOT_VERSION, true );

        $active_forms = array();
        $active_form_ids = array();
        
        if ( is_array( $this->multi_forms ) ) {
            foreach ( $this->multi_forms as $form ) {
                if ( !isset($form['is_enabled']) || $form['is_enabled'] === '1' ) {
                    $active_forms[] = $form;
                    $active_form_ids[] = $form['id'];
                }
            }
        }

        $raw_quick_actions = get_option( 'wp_ai_chatbot_quick_actions', array() );
        $active_quick_actions = array();
        
        if ( is_array( $raw_quick_actions ) ) {
            foreach ( $raw_quick_actions as $qa ) {
                if ( !empty($qa['form_id']) ) {
                    if ( in_array( $qa['form_id'], $active_form_ids ) ) {
                        $active_quick_actions[] = $qa;
                    }
                } else {
                    $active_quick_actions[] = $qa;
                }
            }
        }

        wp_localize_script( 'wp-ai-chat-widget-script', 'wpAiChatbotData', array(
            'rest_url'        => esc_url_raw( rest_url( 'wp-ai-chatbot/v1/chat' ) ),
            'form_submit_url' => esc_url_raw( rest_url( 'wp-ai-chatbot/v1/submit-lead' ) ),
            'nonce'           => wp_create_nonce( 'wp_rest' ),
            'bot_name'        => isset( $this->settings['bot_name'] ) ? $this->settings['bot_name'] : 'AI Assistant',
            'forms'           => $active_forms, 
            'quick_actions'   => $active_quick_actions, 
            'pre_chat_form_id'=> get_option( 'wp_ai_pre_chat_form_id', '' ),
            'guided_queries'  => array_filter(get_option( 'wp_ai_guided_queries', array() )),
            'is_guided_enabled' => get_option( 'wp_ai_guided_flow_enabled', '0' )
        ) );
    }

    public function render_chat_widget_html() {
        $opts = get_option( 'wp_ai_chatbot_custom_style', array() );
        
        $gen_border = !empty($opts['general_border_color']) ? $opts['general_border_color'] : '#e5e7eb';
        $bot_name = !empty($opts['bot_name']) ? $opts['bot_name'] : 'AI Assistant';
        $header_bg = !empty($opts['header_bg']) ? $opts['header_bg'] : '#0071a1';
        $header_text = !empty($opts['header_text']) ? $opts['header_text'] : '#ffffff';
        $chat_bg = !empty($opts['chat_bg']) ? $opts['chat_bg'] : '#f8f9fa';
        $bot_bg = !empty($opts['bot_bubble_bg']) ? $opts['bot_bubble_bg'] : '#ffffff';
        $bot_text = !empty($opts['bot_bubble_text']) ? $opts['bot_bubble_text'] : '#333333';
        $user_bg = !empty($opts['user_bubble_bg']) ? $opts['user_bubble_bg'] : '#0071a1';
        $user_text = !empty($opts['user_bubble_text']) ? $opts['user_bubble_text'] : '#ffffff';
        
        $send_bg = !empty($opts['send_btn_bg']) ? $opts['send_btn_bg'] : '#0071a1';
        $send_hover_bg = !empty($opts['send_btn_hover_bg']) ? $opts['send_btn_hover_bg'] : '#005a87';
        $send_btn_text = !empty($opts['send_btn_text']) ? $opts['send_btn_text'] : '#ffffff'; 
        $send_icon_hover = !empty($opts['send_btn_icon_hover']) ? $opts['send_btn_icon_hover'] : $send_btn_text;
        
        $del_bg = !empty($opts['del_btn_bg']) ? $opts['del_btn_bg'] : '#d63638';
        $del_hover_bg = !empty($opts['del_btn_hover_bg']) ? $opts['del_btn_hover_bg'] : '#b32d2e';

        $close_color = !empty($opts['close_btn_color']) ? $opts['close_btn_color'] : $header_text;
        $close_hover_bg = !empty($opts['close_btn_hover_bg']) ? $opts['close_btn_hover_bg'] : 'rgba(255,255,255,0.15)';
        $close_size = isset($opts['close_btn_size']) && $opts['close_btn_size'] !== '' ? intval($opts['close_btn_size']) : 32;
        $close_pad = isset($opts['close_btn_padding']) && $opts['close_btn_padding'] !== '' ? intval($opts['close_btn_padding']) : 0;
        $close_mar = isset($opts['close_btn_margin']) && $opts['close_btn_margin'] !== '' ? intval($opts['close_btn_margin']) : 0;
        
        // Sizing Variables
        $chat_width = !empty($opts['chat_width']) ? intval($opts['chat_width']) : 380;
        $chat_height = !empty($opts['chat_height']) ? intval($opts['chat_height']) : 600;
        $chat_radius = !empty($opts['chat_radius']) ? intval($opts['chat_radius']) : 16;
        $icon_size = !empty($opts['icon_size']) ? intval($opts['icon_size']) : 60;

        // Input Borders
        $input_bg_color = !empty($opts['input_bg_color']) ? $opts['input_bg_color'] : '#ffffff'; 
        $inp_b_sides = isset($opts['input_border_sides']) ? $opts['input_border_sides'] : 'all';
        $inp_b_width = isset($opts['input_border_width']) ? intval($opts['input_border_width']) : 1;
        $inp_b_color = !empty($opts['input_border_color']) ? $opts['input_border_color'] : '#e5e7eb';
        $inp_b_focus = !empty($opts['input_border_focus_color']) ? $opts['input_border_focus_color'] : '#0071a1';

        // Transparency Flags
        $is_icon_trans = isset($opts['toggle_icon_transparent']) && $opts['toggle_icon_transparent'] == '1';
        $is_chat_trans = isset($opts['chat_bg_transparent']) && $opts['chat_bg_transparent'] == '1';

        $pos = isset($opts['position']) ? $opts['position'] : 'right';
        $side = isset($opts['margin_side']) ? intval($opts['margin_side']) : 30;
        $bottom = isset($opts['margin_bottom']) ? intval($opts['margin_bottom']) : 30;
        
        $pos_css = ($pos === 'left') ? "left: {$side}px; right: auto;" : "right: {$side}px; left: auto;";
        $win_pos_css = ($pos === 'left') ? "left: {$side}px; right: auto; transform-origin: bottom left;" : "right: {$side}px; left: auto; transform-origin: bottom right;";

        $border_css = '';
        if ($inp_b_sides === 'all') {
            $border_css = "border: {$inp_b_width}px solid var(--wp-ai-input-border-color) !important; border-radius: 24px !important;";
        } elseif ($inp_b_sides === 'none') {
            $border_css = "border: none !important; border-radius: 24px !important;";
        } else {
            $border_css = "border: none !important; border-{$inp_b_sides}: {$inp_b_width}px solid var(--wp-ai-input-border-color) !important; border-radius: 0 !important; padding-left: 0 !important; padding-right: 0 !important;";
        }

        $glass_css = $is_chat_trans ? "backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);" : "";
        $icon_trans_css = $is_icon_trans ? "background: transparent !important; box-shadow: none !important;" : "";

        echo '<style>
            :root {
                --wp-ai-border-color: ' . esc_attr($gen_border) . '; /* Fixes global dark mode lines */
                
                --wp-ai-header-bg: ' . esc_attr($header_bg) . ';
                --wp-ai-header-text: ' . esc_attr($header_text) . ';
                --wp-ai-chat-bg: ' . esc_attr($chat_bg) . ';
                --wp-ai-bot-bg: ' . esc_attr($bot_bg) . ';
                --wp-ai-bot-text: ' . esc_attr($bot_text) . ';
                --wp-ai-user-bg: ' . esc_attr($user_bg) . ';
                --wp-ai-user-text: ' . esc_attr($user_text) . ';
                
                --wp-ai-send-bg: ' . esc_attr($send_bg) . ';
                --wp-ai-send-hover-bg: ' . esc_attr($send_hover_bg) . ';
                --wp-ai-send-text: ' . esc_attr($send_btn_text) . ';
                --wp-ai-send-icon-hover: ' . esc_attr($send_icon_hover) . ';
                
                --wp-ai-del-bg: ' . esc_attr($del_bg) . ';
                --wp-ai-del-hover-bg: ' . esc_attr($del_hover_bg) . ';
                
                --wp-ai-close-color: ' . esc_attr($close_color) . ';
                --wp-ai-close-hover-bg: ' . esc_attr($close_hover_bg) . ';
                --wp-ai-close-size: ' . esc_attr($close_size) . 'px;
                --wp-ai-close-padding: ' . esc_attr($close_pad) . 'px;
                --wp-ai-close-margin: ' . esc_attr($close_mar) . 'px;
                
                --wp-ai-input-bg: ' . esc_attr($input_bg_color) . '; 
                --wp-ai-input-border-color: ' . esc_attr($inp_b_color) . ';
                --wp-ai-input-focus-color: ' . esc_attr($inp_b_focus) . ';
                
                --wp-ai-primary-color: ' . esc_attr($header_bg) . ';
                
                --wp-ai-chat-width: ' . esc_attr($chat_width) . 'px;
                --wp-ai-chat-height: ' . esc_attr($chat_height) . 'px;
                --wp-ai-chat-radius: ' . esc_attr($chat_radius) . 'px;
                --wp-ai-icon-size: ' . esc_attr($icon_size) . 'px;
            }
            @media (min-width: 481px) {
                .wp-ai-fab-container { bottom: ' . $bottom . 'px; ' . $pos_css . ' }
                .wp-ai-chatbot-window { bottom: ' . ($bottom + $icon_size + 15) . 'px; ' . $win_pos_css . ' }
            }
            .wp-ai-chatbot-window { ' . $glass_css . ' }
            .wp-ai-chatbot-toggle { ' . $icon_trans_css . ' }
            .wp-ai-chat-footer input { ' . $border_css . ' }
            .wp-ai-chat-footer input:focus { border-color: var(--wp-ai-input-focus-color) !important; }
        </style>';

        // FETCH WHATSAPP FEATURES FROM DATABASE
        $is_wa_enabled = get_option('wp_ai_wa_enable', '1');
        $wa_number     = get_option('wp_ai_wa_number');
        $wa_hover_text = get_option('wp_ai_wa_hover_text', 'Chat on WhatsApp');
        $wa_message    = get_option('wp_ai_wa_message', 'Hello, I need some help!');
        $wa_bg_color   = get_option('wp_ai_wa_bg_color', '#25D366');
        $wa_text_color = get_option('wp_ai_wa_text_color', '#ffffff');
        $wa_icon       = get_option('wp_ai_wa_icon');
        
        if (empty($wa_icon)) {
            $wa_icon = 'https://cdn-icons-png.flaticon.com/512/733/733585.png';
        }

        $has_wa = ( $is_wa_enabled === '1' && !empty( $wa_number ) );
        $wa_url = "https://wa.me/" . esc_attr($wa_number) . "?text=" . urlencode($wa_message);

        ?>
        
        <div id="wp-ai-fab-container" class="wp-ai-fab-container">
            <div id="wp-ai-chatbot-toggle" class="wp-ai-chatbot-toggle" aria-label="Open Chat Menu">
                <?php if(!empty($opts['toggle_icon'])): ?>
                    <img src="<?php echo esc_url($opts['toggle_icon']); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <?php endif; ?>
            </div>
        </div>

        <div id="wp-ai-chatbot-window" class="wp-ai-chatbot-window wp-ai-hidden">
            <div class="wp-ai-chat-header">
                <div class="wp-ai-chat-title"><span class="wp-ai-status-dot"></span><?php echo esc_html( $bot_name ); ?></div>
                <div class="wp-ai-header-actions">
                    <button id="wp-ai-delete-chat" class="wp-ai-header-icon" title="Clear Chat">Delete History</button>
                    <button id="wp-ai-close-chat" class="wp-ai-close-btn">&times;</button>
                </div>
            </div>

            <div id="wp-ai-confirm-modal" class="wp-ai-modal wp-ai-hidden">
                <div class="wp-ai-modal-content">
                    <p>Are you sure you want to clear the chat history?</p>
                    <div class="wp-ai-modal-actions">
                        <button id="wp-ai-cancel-del" class="wp-ai-btn-secondary">Cancel</button>
                        <button id="wp-ai-confirm-del" class="wp-ai-btn-danger">Clear Now</button>
                    </div>
                </div>
            </div>

            <div id="wp-ai-chat-body" class="wp-ai-chat-body"></div>

            <div id="wp-ai-quick-actions-wrapper" class="wp-ai-quick-actions-wrapper" style="display:none;">
                <button type="button" id="wp-ai-scroll-left" class="wp-ai-scroll-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <div id="wp-ai-quick-actions" class="wp-ai-quick-actions"></div>
                <button type="button" id="wp-ai-scroll-right" class="wp-ai-scroll-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </div>

            <div class="wp-ai-chat-footer">
                <input type="text" id="wp-ai-chat-input" placeholder="Type your message..." autocomplete="off" />
                <button id="wp-ai-send-btn" class="wp-ai-send-btn">
                    <?php if(!empty($opts['send_btn_icon'])): ?>
                        <img src="<?php echo esc_url($opts['send_btn_icon']); ?>" style="width:18px;height:18px;object-fit:contain;">
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    <?php endif; ?>
                </button>
            </div>
        </div>
        <?php
    }

    public function register_chat_rest_route() {
        register_rest_route( 'wp-ai-chatbot/v1', '/chat', array( 
            'methods' => 'POST', 
            'callback' => array( $this, 'handle_rest_chat_request' ), 
            'permission_callback' => '__return_true' 
        ) );
        register_rest_route( 'wp-ai-chatbot/v1', '/submit-lead', array( 
            'methods' => 'POST', 
            'callback' => array( $this, 'handle_lead_submission' ), 
            'permission_callback' => '__return_true' 
        ) );
    }

    private function get_or_set_session_cookie() {
        $cookie_name = 'wp_ai_chat_session';
        if ( isset( $_COOKIE[$cookie_name] ) ) {
            return sanitize_text_field( $_COOKIE[$cookie_name] );
        } else {
            $session_id = md5( uniqid( wp_rand(), true ) );
            setcookie( $cookie_name, $session_id, time() + 2592000, '/' );
            return $session_id;
        }
    }

    public function handle_rest_chat_request( WP_REST_Request $request ) {
        $user_message = sanitize_text_field( $request->get_param( 'message' ) );
        $memory_manager = new WP_AI_Chatbot_Memory_Manager();
        $history = $memory_manager->process_history( $request->get_param( 'history' ) );
        if ( empty( $user_message ) ) {
            return new WP_Error( 'empty_message', 'Message cannot be empty.', array( 'status' => 400 ) );
        }
        $start_time = microtime(true);
        $api_handler = new WP_AI_Chatbot_API_Handler();
        $bot_reply = $api_handler->process_chat_request( $user_message, $history );
        $end_time = microtime(true);
        $response_time = round($end_time - $start_time, 2);
        $session_id = $this->get_or_set_session_cookie();
        if (class_exists('WP_AI_Chatbot_Logger')) {
            WP_AI_Chatbot_Logger::log_chat($session_id, $user_message, $bot_reply);
        }
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'ai_chat_analytics';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") == $analytics_table ) {
            $prompt_tokens = ceil(strlen($user_message) / 4);
            $completion_tokens = ceil(strlen($bot_reply) / 4);
            $embedding_tokens = $prompt_tokens;
            $reply_lower = strtolower($bot_reply);
            $is_fallback = (strpos($reply_lower, 'i don\'t know') !== false || strpos($reply_lower, 'not sure') !== false || strpos($reply_lower, 'contact our team') !== false) ? 1 : 0;
            $wpdb->insert( $analytics_table, array(
                'session_id'        => $session_id,
                'prompt_tokens'     => $prompt_tokens,
                'completion_tokens' => $completion_tokens,
                'embedding_tokens'  => $embedding_tokens, 
                'response_time'     => $response_time,
                'is_fallback'       => $is_fallback,
                'created_at'        => current_time('mysql')
            ));
        }
        return rest_ensure_response( array( 'success' => true, 'reply' => $bot_reply ) );
    }

    public function handle_lead_submission( WP_REST_Request $request ) {
        return rest_ensure_response( array( 'success' => true, 'message' => "Success" ) );
    }
}
