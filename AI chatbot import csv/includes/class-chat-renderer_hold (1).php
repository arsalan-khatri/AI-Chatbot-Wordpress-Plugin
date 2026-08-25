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

        // 🚀 FIX 1: Filter out disabled forms before sending to JS
        $active_forms = array();
        $active_form_ids = array();
        
        if ( is_array( $this->multi_forms ) ) {
            foreach ( $this->multi_forms as $form ) {
                // Check if is_enabled is set to '1' or if it's an old form without this setting
                if ( !isset($form['is_enabled']) || $form['is_enabled'] === '1' ) {
                    $active_forms[] = $form;
                    $active_form_ids[] = $form['id'];
                }
            }
        }

        // 🚀 FIX 2: Filter out Quick Actions that are linked to disabled forms
        $raw_quick_actions = get_option( 'wp_ai_chatbot_quick_actions', array() );
        $active_quick_actions = array();
        
        if ( is_array( $raw_quick_actions ) ) {
            foreach ( $raw_quick_actions as $qa ) {
                if ( !empty($qa['form_id']) ) {
                    // Agar form active list mein hai, tabhi quick action show karo
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
            'forms'           => $active_forms, // 👈 Sirf active forms jayenge
            'quick_actions'   => $active_quick_actions, // 👈 Sirf active quick actions jayenge
            'pre_chat_form_id'=> get_option( 'wp_ai_pre_chat_form_id', '' ),
            'guided_queries'  => array_filter(get_option( 'wp_ai_guided_queries', array() )),
            'is_guided_enabled' => get_option( 'wp_ai_guided_flow_enabled', '0' )
        ) );
    }

    public function render_chat_widget_html() {
        $opts = get_option( 'wp_ai_chatbot_custom_style', array() );
        
        $bot_name = !empty($opts['bot_name']) ? $opts['bot_name'] : 'AI Assistant';
        $header_bg = !empty($opts['header_bg']) ? $opts['header_bg'] : '#0071a1';
        $header_text = !empty($opts['header_text']) ? $opts['header_text'] : '#ffffff';
        $chat_bg = !empty($opts['chat_bg']) ? $opts['chat_bg'] : '#f8f9fa';
        $bot_bg = !empty($opts['bot_bubble_bg']) ? $opts['bot_bubble_bg'] : '#ffffff';
        $bot_text = !empty($opts['bot_bubble_text']) ? $opts['bot_bubble_text'] : '#333333';
        $user_bg = !empty($opts['user_bubble_bg']) ? $opts['user_bubble_bg'] : '#0071a1';
        $user_text = !empty($opts['user_bubble_text']) ? $opts['user_bubble_text'] : '#ffffff';
        $send_bg = !empty($opts['send_btn_bg']) ? $opts['send_btn_bg'] : '#0071a1';
        $del_bg = !empty($opts['del_btn_bg']) ? $opts['del_btn_bg'] : '#d63638';
        
        $pos = isset($opts['position']) ? $opts['position'] : 'right';
        $side = isset($opts['margin_side']) ? intval($opts['margin_side']) : 30;
        $bottom = isset($opts['margin_bottom']) ? intval($opts['margin_bottom']) : 30;
        
        $pos_css = ($pos === 'left') ? "left: {$side}px; right: auto;" : "right: {$side}px; left: auto;";
        $win_pos_css = ($pos === 'left') ? "left: {$side}px; right: auto; transform-origin: bottom left;" : "right: {$side}px; left: auto; transform-origin: bottom right;";

        // FETCH WHATSAPP FEATURES FROM DATABASE
        $is_wa_enabled = get_option('wp_ai_wa_enable', '1');
        $wa_number     = get_option('wp_ai_wa_number');
        $wa_hover_text = get_option('wp_ai_wa_hover_text', 'Chat on WhatsApp');
        $wa_message    = get_option('wp_ai_wa_message', 'Hello, I need some help!');
        $wa_bg_color   = get_option('wp_ai_wa_bg_color', '#25D366');
        $wa_text_color = get_option('wp_ai_wa_text_color', '#ffffff');
        $wa_size       = intval(get_option('wp_ai_wa_size', 60));
        $wa_icon       = get_option('wp_ai_wa_icon');
        
        if (empty($wa_icon)) {
            $wa_icon = 'https://cdn-icons-png.flaticon.com/512/733/733585.png';
        }

        $has_wa = ( $is_wa_enabled === '1' && !empty( $wa_number ) );
        $wa_url = "https://wa.me/" . esc_attr($wa_number) . "?text=" . urlencode($wa_message);

        echo '<style>
            :root {
                --wp-ai-header-bg: ' . esc_attr($header_bg) . ';
                --wp-ai-header-text: ' . esc_attr($header_text) . ';
                --wp-ai-chat-bg: ' . esc_attr($chat_bg) . ';
                --wp-ai-bot-bg: ' . esc_attr($bot_bg) . ';
                --wp-ai-bot-text: ' . esc_attr($bot_text) . ';
                --wp-ai-user-bg: ' . esc_attr($user_bg) . ';
                --wp-ai-user-text: ' . esc_attr($user_text) . ';
                --wp-ai-send-bg: ' . esc_attr($send_bg) . ';
                --wp-ai-del-bg: ' . esc_attr($del_bg) . ';
                --wp-ai-primary-color: ' . esc_attr($header_bg) . ';
            }
            @media (min-width: 481px) {
                .wp-ai-fab-container { bottom: ' . $bottom . 'px; ' . $pos_css . ' }
                .wp-ai-chatbot-window { bottom: ' . ($bottom + 75) . 'px; ' . $win_pos_css . ' }
            }
            .wp-ai-whatsapp-custom-btn {
                background: ' . esc_attr($wa_bg_color) . ' !important;
                color: ' . esc_attr($wa_text_color) . ' !important;
            }
        </style>';
        ?>
        
        <div id="wp-ai-fab-container" class="wp-ai-fab-container <?php echo $has_wa ? 'has-wa-menu' : ''; ?>">
            
            <div class="wp-ai-fab-menu">
                
                <?php if ( $has_wa ) : ?>
                <a href="<?php echo esc_url( $wa_url ); ?>" target="_blank" rel="noopener noreferrer" class="wp-ai-fab-item wp-ai-whatsapp-btn wp-ai-whatsapp-custom-btn" title="<?php echo esc_attr($wa_hover_text); ?>" data-hover-text="<?php echo esc_attr($wa_hover_text); ?>">
                    <img src="<?php echo esc_url($wa_icon); ?>" alt="WhatsApp" style="width: 24px; height: 24px; object-fit: contain;" />
                    <span class="wp-ai-fab-label"><?php echo esc_html($wa_hover_text); ?></span>
                </a>
                <?php endif; ?>
                
                <div class="wp-ai-fab-item wp-ai-chat-btn" id="wp-ai-open-chat-btn" title="AI Assistant" data-hover-text="AI Chat">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="white" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    <span class="wp-ai-fab-label">AI Chat</span>
                </div>
            </div>

            <div id="wp-ai-chatbot-toggle" class="wp-ai-chatbot-toggle" aria-label="Open Chat Menu">
                <?php if(!empty($opts['toggle_icon'])): ?>
                    <img src="<?php echo esc_url($opts['toggle_icon']); ?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <?php endif; ?>
            </div>
        </div>

        <div id="wp-ai-chatbot-window" class="wp-ai-chatbot-window wp-ai-hidden">
            <div class="wp-ai-chat-header">
                <div class="wp-ai-chat-title"><span class="wp-ai-status-dot"></span><?php echo esc_html( $bot_name ); ?></div>
                <button id="wp-ai-delete-chat" class="wp-ai-header-icon" title="Clear Chat">Delete History</button>
                <button id="wp-ai-close-chat" class="wp-ai-close-btn">&times;</button>
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
        
        <!-- 🚀 UNIVERSAL HOVER TYPING ANIMATION -->
        <script>
        (function($) {
            $(document).ready(function() {
                $('.wp-ai-fab-item').each(function() {
                    var btn = $(this);
                    var text = btn.attr('data-hover-text') || btn.find('.wp-ai-fab-label').text().trim();
                    var labelSpan = btn.find('.wp-ai-fab-label');
                    var typingInterval = null;
                    var idx = 0;

                    function startTypingEffect() {
                        if (!labelSpan.length) return;
                        labelSpan.text('');
                        idx = 0;
                        clearInterval(typingInterval);
                        typingInterval = setInterval(function() {
                            if (idx <= text.length) {
                                labelSpan.text(text.slice(0, idx));
                                idx++;
                            } else {
                                clearInterval(typingInterval);
                            }
                        }, 30);
                    }

                    function clearTypingEffect() {
                        clearInterval(typingInterval);
                        labelSpan.text(text);
                    }

                    btn.on('mouseenter focus', startTypingEffect);
                    btn.on('mouseleave blur', clearTypingEffect);
                });
            });
        })(jQuery);
        </script>
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
    
        $api_handler = new WP_AI_Chatbot_API_Handler();
        $bot_reply = $api_handler->process_chat_request( $user_message, $history );
        
        $session_id = $this->get_or_set_session_cookie();
        
        if (class_exists('WP_AI_Chatbot_Logger')) {
            WP_AI_Chatbot_Logger::log_chat($session_id, $user_message, $bot_reply);
        }

        return rest_ensure_response( array( 
            'success' => true, 
            'reply' => $bot_reply 
        ) );
    }

    public function handle_lead_submission( WP_REST_Request $request ) {
        $form_data = $request->get_param( 'form_data' );
        if ( empty( $form_data ) || ! is_array( $form_data ) ) {
            return new WP_Error( 'empty_form', 'Form data missing.', array( 'status' => 400 ) );
        }

        $form_id = isset( $form_data['wp_ai_form_id'] ) ? sanitize_key( $form_data['wp_ai_form_id'] ) : '';
        $form_title = isset( $form_data['wp_ai_form_title'] ) ? sanitize_text_field( $form_data['wp_ai_form_title'] ) : 'form';
        unset( $form_data['wp_ai_form_title'], $form_data['wp_ai_form_id'] );

        if ( empty( $form_id ) ) {
            return new WP_Error( 'missing_form_id', 'Form ID missing.', array( 'status' => 400 ) );
        }

        global $wpdb;
        $table_name      = $wpdb->prefix . 'ai_leads_' . $form_id;
        $charset_collate = $wpdb->get_charset_collate();

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) !== $table_name ) {
            $wpdb->query(
                "CREATE TABLE IF NOT EXISTS {$table_name} (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                    PRIMARY KEY (id)
                ) {$charset_collate};"
            );
        }

        $existing_cols = $wpdb->get_col( "DESCRIBE {$table_name}", 0 );
        $row_data   = array();
        $row_format = array();
        $user_name  = ''; 

        foreach ( $form_data as $key => $value ) {
            $col = sanitize_key( $key );
            if ( empty( $col ) ) continue;

            if ( is_array( $value ) ) {
                $clean_val = implode( ', ', array_map( 'sanitize_text_field', $value ) );
                $col_type  = 'TEXT';
            } else {
                $clean_val = sanitize_textarea_field( $value );
                $col_type  = 'VARCHAR(500)';
            }

            if ( empty( $user_name ) && ( strpos( $col, 'name' ) !== false || strpos( $col, 'naam' ) !== false ) ) {
                $user_name = ucfirst( strtolower( trim( $clean_val ) ) ); 
            }

            if ( ! in_array( $col, $existing_cols ) ) {
                $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN `{$col}` {$col_type} DEFAULT NULL" );
            }

            $row_data[ $col ] = $clean_val;
            $row_format[]     = '%s';
        }

        if ( empty( $row_data ) ) {
            return new WP_Error( 'empty_data', 'No field data received.', array( 'status' => 400 ) );
        }

        $inserted = $wpdb->insert( $table_name, $row_data, $row_format );

        if ( $inserted ) {
            $api_handler = new WP_AI_Chatbot_API_Handler();
            $site_name = get_bloginfo('name'); 
            
            if ( ! empty( $user_name ) ) {
                $ai_prompt = "System Instruction: The user named '{$user_name}' has just successfully submitted the '{$form_title}'. Generate a short, polite, 1-2 sentence response thanking '{$user_name}' by name for filling out the '{$form_title}', and letting them know that the {$site_name} team will contact them shortly. Do not ask any further questions.";
            } else {
                $ai_prompt = "System Instruction: The user has just successfully submitted the '{$form_title}'. Generate a short, polite, 1-2 sentence response thanking them for filling out the '{$form_title}', and letting them know that the {$site_name} team will contact them shortly. Do not ask any further questions.";
            }
            
            $ai_message = $api_handler->process_chat_request( $ai_prompt, array() );
            
            if ( empty( $ai_message ) || strpos( $ai_message, 'Error:' ) !== false ) {
                $ai_message = ! empty( $user_name ) ? "Thank you, **{$user_name}**! Your details for the **{$form_title}** have been submitted successfully. Our team from {$site_name} will contact you shortly." : "Thank you! Your details for the **{$form_title}** have been submitted successfully. Our team will contact you shortly.";
            }

            if (class_exists('WP_AI_Chatbot_Logger')) {
                $session_id = $this->get_or_set_session_cookie();
                WP_AI_Chatbot_Logger::log_chat($session_id, "[SYSTEM_ACTION]: User filled the form: " . $form_title, $ai_message);
            }

            return rest_ensure_response( array( 'success' => true, 'message' => $ai_message ) );
        } else {
            return new WP_Error( 'db_error', 'Could not save details.', array( 'status' => 500 ) );
        }
    }
}