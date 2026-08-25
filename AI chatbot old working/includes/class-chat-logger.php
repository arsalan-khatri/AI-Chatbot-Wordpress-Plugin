<?php
/**
 * Chat Logger & History Viewer (Messenger UI with WhatsApp Sticky Dates & Range Filter)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Logger {

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_submenu' ) );
    }

    public function add_submenu() {
        add_submenu_page(
            'wp-ai-chatbot',
            'Chat History',
            'Chat History',
            'manage_options',
            'wp-ai-chatbot-logs',
            array( $this, 'display_logs_page' )
        );
    }

    public static function log_chat($session_id, $user_message, $bot_reply) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chat_logs';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $wpdb->query("CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                session_id varchar(100) NOT NULL,
                user_message text NOT NULL,
                bot_reply text NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;");
        } else {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'session_id'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $table_name ADD session_id varchar(100) NOT NULL DEFAULT 'unknown'");
            }
        }

        $wpdb->insert(
            $table_name,
            array(
                'session_id'   => sanitize_text_field($session_id),
                'user_message' => sanitize_textarea_field($user_message),
                'bot_reply'    => sanitize_textarea_field($bot_reply),
                'created_at'   => current_time('mysql') 
            )
        );
    }

    // WhatsApp Style Date Format Logic
    private function get_whatsapp_date_label($timestamp_str) {
        $log_time = strtotime($timestamp_str);
        $current_time = current_time('timestamp');
        
        $today_start = strtotime(date('Y-m-d', $current_time));
        $yesterday_start = strtotime('-1 day', $today_start);
        $week_ago_start = strtotime('-6 days', $today_start);
        
        $log_date = strtotime(date('Y-m-d', $log_time));

        if ($log_date == $today_start) {
            return 'Today';
        } elseif ($log_date == $yesterday_start) {
            return 'Yesterday';
        } elseif ($log_date >= $week_ago_start) {
            return date('l', $log_time); 
        } else {
            return date('n/j/Y', $log_time); 
        }
    }

    public function display_logs_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chat_logs';
        
        if (isset($_POST['clear_logs'])) {
            $wpdb->query("TRUNCATE TABLE $table_name");
            echo '<div class="notice notice-success is-dismissible"><p>All Chat Logs Cleared Successfully!</p></div>';
        }

        $sessions = [];
        $logs_by_session = [];
        
        // 🚀 NAYA IZAFA: Date Range Filter (Start Date & End Date) Logic
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date   = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $where_clause = "";
        
        if (!empty($start_date) && !empty($end_date)) {
            $where_clause = $wpdb->prepare(" WHERE DATE(created_at) BETWEEN %s AND %s ", $start_date, $end_date);
        } elseif (!empty($start_date)) {
            $where_clause = $wpdb->prepare(" WHERE DATE(created_at) >= %s ", $start_date);
        } elseif (!empty($end_date)) {
            $where_clause = $wpdb->prepare(" WHERE DATE(created_at) <= %s ", $end_date);
        }

        $min_date = '';
        $max_date = date('Y-m-d'); 

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            
            $date_range = $wpdb->get_row("SELECT MIN(DATE(created_at)) as first_chat, MAX(DATE(created_at)) as last_chat FROM $table_name");
            if ($date_range && $date_range->first_chat) {
                $min_date = $date_range->first_chat;
                $max_date = $date_range->last_chat;
            }

            $sessions = $wpdb->get_results("SELECT session_id, MAX(created_at) as latest_interaction FROM $table_name $where_clause GROUP BY session_id ORDER BY latest_interaction DESC LIMIT 100");
            
            if (!empty($sessions)) {
                $session_ids = array_map(function($s) { return $s->session_id; }, $sessions);
                $placeholders = implode(',', array_fill(0, count($session_ids), '%s'));
                
                $query = "SELECT * FROM $table_name WHERE session_id IN ($placeholders) ORDER BY created_at ASC";
                $all_logs = $wpdb->get_results($wpdb->prepare($query, $session_ids));
                
                foreach ($all_logs as $log) {
                    $logs_by_session[$log->session_id][] = $log;
                }
            }
        }

        $multi_forms = get_option( 'wp_ai_chatbot_multi_forms', array() );

        ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.0.2/marked.min.js"></script>

        <div class="wrap">
            <h1>Live User Conversations</h1>
            <?php settings_errors(); ?>
            <p>Monitor individual user chats, forms submitted, and bot responses in a professional layout.</p>
            
            <div style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:15px 20px; border:1px solid #ccd0d4; border-radius:8px; margin-bottom:15px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                
                <!-- 🚀 NAYA IZAFA: Start Date aur End Date Fields -->
                <form method="get" style="display:flex; gap:10px; align-items:center; margin:0; flex-wrap:wrap;">
                    <input type="hidden" name="page" value="wp-ai-chatbot-logs">
                    
                    <div style="display:flex; align-items:center; font-weight:600; color:#1d2327; font-size:14px; margin-right:5px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px; color:#2271b1;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        Filter by Date:
                    </div>

                    <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" min="<?php echo esc_attr($min_date); ?>" max="<?php echo esc_attr($max_date); ?>" style="border-radius:4px; border:1px solid #8c8f94; padding:0 10px; height:36px; line-height:36px; cursor:pointer; color:#3c434a;" title="From Date">
                    
                    <span style="color:#8c8f94; font-weight:bold; font-size:13px; margin:0 2px;">to</span>
                    
                    <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" min="<?php echo esc_attr($min_date); ?>" max="<?php echo esc_attr($max_date); ?>" style="border-radius:4px; border:1px solid #8c8f94; padding:0 10px; height:36px; line-height:36px; cursor:pointer; color:#3c434a;" title="To Date">
                    
                    <button type="submit" class="button button-primary" style="height:36px; display:flex; align-items:center; padding:0 16px;">Filter</button>
                    
                    <?php if(!empty($start_date) || !empty($end_date)): ?>
                        <a href="?page=wp-ai-chatbot-logs" class="button button-secondary" style="height:36px; display:flex; align-items:center; padding:0 16px;">Clear Filter</a>
                    <?php endif; ?>
                </form>

                <form method="post" style="margin:0;">
                    <button type="submit" name="clear_logs" class="button" style="color:#d63638; border-color:#d63638; height:36px; display:flex; align-items:center; padding:0 16px;" onclick="return confirm('Are you sure you want to delete all chat history permanently?');">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                        Clear All Logs
                    </button>
                </form>
            </div>

            <div class="wp-ai-messenger">
                <div class="wp-ai-sidebar">
                    <?php if (empty($sessions)): ?>
                        <div style="padding: 20px; text-align: center; color: #666;">No chats found for this date range.</div>
                    <?php else: ?>
                        <?php foreach($sessions as $session): 
                            $short_id = strtoupper(substr($session->session_id, 0, 5));
                            $latest_log = end($logs_by_session[$session->session_id]);
                        ?>
                            <div class="wp-ai-sidebar-item" data-session="<?php echo esc_attr($session->session_id); ?>">
                                <h4>👤 User-<?php echo $short_id; ?></h4>
                                <p><?php echo esc_html(mb_strimwidth($latest_log->user_message, 0, 30, '...')); ?></p>
                                <span style="font-size:11px; color:#777; font-weight:600;"><?php echo date('l, d M Y - h:i A', strtotime($session->latest_interaction)); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="wp-ai-chat-area">
                    <div class="wp-ai-chat-header" id="wp-ai-active-user-title">
                        Select a user to view chat history
                    </div>
                    
                    <div class="wp-ai-chat-container-wrapper">
                        <?php if (!empty($sessions)): foreach($sessions as $session): ?>
                            <div class="wp-ai-chat-messages" id="chat-<?php echo esc_attr($session->session_id); ?>">
                                <?php 
                                    $last_date = ''; 
                                    
                                    foreach($logs_by_session[$session->session_id] as $log): 
                                        $current_date = date('Y-m-d', strtotime($log->created_at));
                                        
                                        if ($current_date !== $last_date) {
                                            $date_label = $this->get_whatsapp_date_label($log->created_at);
                                            echo '<div class="chat-date-divider"><span>' . esc_html($date_label) . '</span></div>';
                                            $last_date = $current_date;
                                        }

                                        $is_system = strpos($log->user_message, '[SYSTEM_ACTION]:') === 0;
                                        $formatted_time = date('h:i A', strtotime($log->created_at));
                                ?>
                                    
                                    <?php if($is_system): ?>
                                        <div class="chat-bubble chat-system">
                                            ✅ <?php echo esc_html(str_replace('[SYSTEM_ACTION]: ', '', $log->user_message)); ?>
                                            <span class="chat-time"><?php echo $formatted_time; ?></span>
                                        </div>
                                        <div class="chat-bubble chat-bot">
                                            <div class="wp-ai-raw-markdown" style="display:none;"><?php echo esc_html($log->bot_reply); ?></div>
                                            <div class="wp-ai-rendered-content"></div>
                                            <span class="chat-time"><?php echo $formatted_time; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="chat-bubble chat-user">
                                            <?php echo esc_html($log->user_message); ?>
                                            <span class="chat-time" style="color: rgba(255,255,255,0.8);"><?php echo $formatted_time; ?></span>
                                        </div>
                                        
                                        <div class="chat-bubble chat-bot">
                                            <div class="wp-ai-raw-markdown" style="display:none;"><?php echo esc_html($log->bot_reply); ?></div>
                                            <div class="wp-ai-rendered-content"></div>
                                            <span class="chat-time"><?php echo $formatted_time; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .wp-ai-messenger { display: flex; height: 600px; background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .wp-ai-sidebar { width: 300px; border-right: 1px solid #ddd; background: #f8f9fa; overflow-y: auto; }
            .wp-ai-sidebar-item { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: 0.2s; }
            .wp-ai-sidebar-item:hover, .wp-ai-sidebar-item.active { background: #e0f0fa; border-left: 4px solid #2271b1; }
            .wp-ai-sidebar-item h4 { margin: 0 0 5px 0; color: #2271b1; font-size: 14px; font-weight: bold; }
            .wp-ai-sidebar-item p { margin: 0 0 5px 0; font-size: 13px; color: #444; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .wp-ai-chat-area { flex-grow: 1; display: flex; flex-direction: column; background: #e5ddd5; position: relative; }
            .wp-ai-chat-header { padding: 15px 20px; background: #fff; border-bottom: 1px solid #ddd; font-weight: bold; font-size: 16px; color: #333; z-index: 10; }
            .wp-ai-chat-container-wrapper { flex-grow: 1; position: relative; overflow: hidden; }
            .wp-ai-chat-messages { height: 100%; padding: 20px; overflow-y: auto; display: none; flex-direction: column; position: absolute; width: 100%; box-sizing: border-box; }
            .wp-ai-chat-messages.active { display: flex; }
            
            .chat-date-divider { 
                text-align: center; 
                margin: 10px 0 15px 0; 
                position: sticky; 
                top: 5px; 
                z-index: 100; 
                display: flex;
                justify-content: center;
                width: 100%;
                pointer-events: none; 
            }
            .chat-date-divider span { 
                background-color: rgba(24, 34, 41, 0.85); 
                color: #f1f1f2; 
                font-size: 11.5px; 
                font-weight: 500; 
                padding: 5px 14px; 
                border-radius: 8px; 
                display: inline-block; 
                box-shadow: 0 1px 2px rgba(0,0,0,0.15); 
                backdrop-filter: blur(4px); 
                letter-spacing: 0.3px;
            }

            .chat-bubble { max-width: 75%; padding: 10px 15px; border-radius: 8px; margin-bottom: 12px; font-size: 14px; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.1); line-height: 1.5; }
            .chat-user { background: #2271b1; align-self: flex-end; border-bottom-right-radius: 0; color: #fff; }
            .chat-bot { background: #fff; align-self: flex-start; border-bottom-left-radius: 0; color: #333; }
            .chat-system { background: #fff3cd; color: #856404; align-self: center; font-size: 13px; font-weight: bold; border-radius: 12px; text-align: center; border: 1px solid #ffeeba; max-width: 90%; }
            .chat-time { display: block; font-size: 10px; margin-top: 5px; text-align: right; }
            .chat-bot .chat-time { color: #888; }

            .wp-ai-rendered-content img { max-width: 100%; height: auto; border-radius: 6px; margin: 5px 0; display: block; }
            .wp-ai-rendered-content p { margin: 0 0 10px 0; }
            .wp-ai-rendered-content p:last-child { margin-bottom: 0; }
            .wp-ai-rendered-content a { color: #2271b1; text-decoration: none; font-weight: 500; }
            .wp-ai-rendered-content a:hover { text-decoration: underline; }
            .wp-ai-rendered-content h1, .wp-ai-rendered-content h2, .wp-ai-rendered-content h3 { margin: 10px 0 5px 0; color: #2271b1; font-size: 16px; }
            .wp-ai-rendered-content ul, .wp-ai-rendered-content ol { margin-top: 0; padding-left: 20px; }
        </style>

        <script>
        var wpAiAdminForms = <?php echo wp_json_encode($multi_forms); ?>;

        function generateDisabledFormHTML(formId) {
            if (!wpAiAdminForms || wpAiAdminForms.length === 0) return '<div style="color:red; font-size:12px;">[Form data unavailable]</div>';
            
            var form = wpAiAdminForms.find(f => f.id == formId);
            if (!form) return '<div style="color:red; font-size:12px;">[Requested form not found in settings]</div>';

            var html = '<div style="background:#f9f9f9; padding:15px; border-radius:8px; border:1px solid #ddd; margin:10px 0; text-align:left; opacity: 0.9;">';
            html += '<h4 style="margin-top:0; margin-bottom:10px; font-size:14px; color:#333; display:flex; justify-content:space-between; align-items:center;">' + 
                        form.title + 
                        '<span style="font-size:10px; background:#eef2f5; color:#2271b1; padding:2px 6px; border-radius:4px; border:1px solid #b5c3d0; font-weight:normal;">Preview Mode (Disabled)</span>' +
                    '</h4>';
            
            if (form.fields && Array.isArray(form.fields)) {
                form.fields.forEach((field) => {
                    var req = field.required === 'yes' ? '<span style="color:red;">*</span>' : '';
                    html += '<div style="margin-bottom:10px;">';
                    html += '<label style="display:block; font-size:12px; margin-bottom:4px; color:#555; font-weight:600;">' + field.label + ' ' + req + '</label>';
                    
                    if (field.type === 'textarea') {
                        html += '<textarea disabled style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px; background:#e9ecef; color:#666; resize:none;"></textarea>';
                    } else if (field.type === 'select' || field.type === 'multiselect') {
                        html += '<select disabled style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px; background:#e9ecef; color:#666;"><option>Select an option...</option></select>';
                    } else {
                        html += '<input type="' + field.type + '" disabled style="width:100%; padding:6px; border:1px solid #ccc; border-radius:4px; background:#e9ecef; color:#666;" />';
                    }
                    html += '</div>';
                });
            }
            
            html += '<button disabled style="width:100%; background:#b5c3d0; color:#fff; border:none; padding:10px; border-radius:4px; font-weight:bold; cursor:not-allowed;">Submit</button>';
            html += '</div>';
            
            return html;
        }

        jQuery(document).ready(function($) {
            
            if (typeof marked !== 'undefined') {
                marked.setOptions({ gfm: true, breaks: true });
                
                $('.chat-bot').each(function() {
                    var rawText = $(this).find('.wp-ai-raw-markdown').text();
                    
                    rawText = rawText.replace(/\[SHOW_FORM_([a-zA-Z0-9_]+)\]/g, function(match, formId) {
                        return generateDisabledFormHTML(formId);
                    });

                    var htmlContent = marked.parse(rawText);
                    
                    htmlContent = htmlContent.replace(/(?<!src=['"])(https?:\/\/[^\s]+?\.(?:jpg|jpeg|png|gif))/gi, function(match) {
                        return '<img src="' + match + '" style="max-width:250px; height:auto; border-radius:6px; display:block; margin:5px 0;">';
                    });
                    
                    $(this).find('.wp-ai-rendered-content').html(htmlContent);
                });
            } else {
                $('.chat-bot').each(function() {
                    var rawText = $(this).find('.wp-ai-raw-markdown').text();
                    rawText = rawText.replace(/\[SHOW_FORM_([a-zA-Z0-9_]+)\]/g, function(match, formId) {
                        return generateDisabledFormHTML(formId);
                    });
                    $(this).find('.wp-ai-rendered-content').html(rawText.replace(/\n/g, '<br>'));
                });
            }

            $('.wp-ai-sidebar-item').on('click', function() {
                var sessionId = $(this).data('session');
                var userName = $(this).find('h4').text();
                
                $('.wp-ai-sidebar-item').removeClass('active');
                $(this).addClass('active');
                
                $('#wp-ai-active-user-title').text('Chatting with: ' + userName);
                
                $('.wp-ai-chat-messages').removeClass('active');
                var activeChat = $('#chat-' + sessionId);
                activeChat.addClass('active');
                
                activeChat.scrollTop(activeChat[0].scrollHeight);
            });

            if($('.wp-ai-sidebar-item').length > 0) {
                $('.wp-ai-sidebar-item').first().click();
            }
        });
        </script>
        <?php
    }
}