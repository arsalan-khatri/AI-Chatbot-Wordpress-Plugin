<?php
/**
 * Main Analytics Dashboard (Enterprise, Dynamic Filters, & Fully Aware Data Analyst AI)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Dashboard {

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_dashboard_menu' ) ); 
        add_action( 'admin_init', array( $this, 'init_analytics_table' ) );
        add_action( 'wp_ajax_wp_ai_get_dashboard_data', array( $this, 'ajax_get_dashboard_data' ) );
        add_action( 'wp_ajax_wp_ai_sync_historical_data', array( $this, 'ajax_sync_historical_data' ) );
        add_action( 'wp_ajax_wp_ai_admin_dashboard_chat', array( $this, 'ajax_admin_dashboard_chat' ) );
    }

    public function add_dashboard_menu() {
        add_submenu_page(
            'wp-ai-chatbot',
            'Dashboard & Analytics',
            'Dashboard',
            'manage_options',
            'wp-ai-chatbot-dashboard',
            array( $this, 'display_dashboard' )
        );
    }

    public function init_analytics_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chat_analytics';
        
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                session_id varchar(100) NOT NULL,
                prompt_tokens int(11) DEFAULT 0,
                completion_tokens int(11) DEFAULT 0,
                embedding_tokens int(11) DEFAULT 0,
                response_time float DEFAULT 0,
                is_fallback tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY session_idx (session_id),
                KEY created_idx (created_at)
            ) $charset_collate;";
            
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
        }
    }

    public function ajax_admin_dashboard_chat() {
        check_ajax_referer( 'wp_ai_dashboard_nonce', 'security' );
        
        $admin_message = sanitize_text_field($_POST['message']);
        $context_data = isset($_POST['context']) ? wp_unslash($_POST['context']) : '';
        $history_data = isset($_POST['history']) ? sanitize_textarea_field(wp_unslash($_POST['history'])) : '';

        $system_prompt = "SYSTEM CONTEXT: You are a professional AI Data Analyst for this WordPress dashboard ONLY. You are completely isolated from the frontend website. You DO NOT answer questions about frontend products. You ONLY analyze the dashboard data provided below and answer the admin's queries, which includes system configurations, models used, tokens, and form conversions.\n\n";
        $system_prompt .= "STRICT BEHAVIOR RULES:\n";
        $system_prompt .= "- BE SHORT AND TO THE POINT. Use short bullet points.\n";
        $system_prompt .= "- Identify yourself simply as the 'Data Analyst'. Do not use any specific company brand names for yourself.\n";
        $system_prompt .= "- DO NOT repeat your intro. Only state your role if explicitly asked or on the first greeting.\n";
        $system_prompt .= "- ALWAYS use Markdown (boldings, lists).\n";
        $system_prompt .= "- Use the 'Hourly Activity' data in the JSON below to answer questions about 'peak hours' or 'busiest times'. Find the time with the highest numeric value.\n\n";
        $system_prompt .= "LIVE SYSTEM DATA (JSON FORMAT):\n" . $context_data . "\n\n";
        
        if (!empty($history_data)) {
            $system_prompt .= "RECENT CHAT HISTORY (MEMORY):\n" . $history_data . "\n\n";
        }

        if ($admin_message === 'INIT_GREETING') {
            $user_prompt = "TASK: The admin just opened the dashboard. Write a 2-sentence welcome message introducing yourself as the Data Analyst. Give a 1-sentence quick health check of the data provided.";
        } else {
            $user_prompt = "ADMIN QUESTION: " . $admin_message;
        }

        $chat_settings = get_option('wp_ai_chatbot_settings', []);
        $provider = $chat_settings['api_provider'] ?? 'gemini';
        $api_key = $chat_settings[$provider . '_api_key'] ?? '';
        $model = $chat_settings[$provider . '_model'] ?? '';

        if (empty($api_key)) {
            wp_send_json_error('API Key missing in plugin settings.');
        }

        $full_prompt = $system_prompt . "\n\n" . $user_prompt;
        $bot_reply = "API Error. Check keys.";

        if ($provider === 'openai') {
            $url = 'https://api.openai.com/v1/chat/completions';
            $body = json_encode([
                'model' => $model ?: 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $user_prompt]
                ],
                'temperature' => 0.7
            ]);
            $response = wp_remote_post($url, [
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key],
                'body' => $body, 'timeout' => 30
            ]);
            
            if (!is_wp_error($response)) {
                $res = json_decode(wp_remote_retrieve_body($response), true);
                $bot_reply = $res['choices'][0]['message']['content'] ?? "Error processing OpenAI response.";
            }
        } else {
            $model = $model ?: 'gemini-1.5-flash';
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
            $body = json_encode([
                'systemInstruction' => ['parts' => [['text' => $system_prompt]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $user_prompt]]]]
            ]);
            $response = wp_remote_post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $body, 'timeout' => 30
            ]);

            if (!is_wp_error($response)) {
                $res = json_decode(wp_remote_retrieve_body($response), true);
                $bot_reply = $res['candidates'][0]['content']['parts'][0]['text'] ?? "Error processing Gemini response.";
            }
        }

        $prompt_tokens = ceil(strlen($full_prompt) / 4);
        $completion_tokens = ceil(strlen($bot_reply) / 4);

        global $wpdb;
        $analytics_table = $wpdb->prefix . 'ai_chat_analytics';
        $wpdb->insert($analytics_table, array(
            'session_id' => 'admin_dashboard_bot',
            'prompt_tokens' => $prompt_tokens,
            'completion_tokens' => $completion_tokens,
            'embedding_tokens' => 0,
            'response_time' => 0, 
            'is_fallback' => 0,
            'created_at' => current_time('mysql')
        ));

        wp_send_json_success(array('reply' => $bot_reply));
    }

    public function ajax_sync_historical_data() {
        check_ajax_referer( 'wp_ai_dashboard_nonce', 'security' );
        global $wpdb;
        $logs_table = $wpdb->prefix . 'ai_chat_logs';
        $analytics_table = $wpdb->prefix . 'ai_chat_analytics';

        if ( $wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") == $analytics_table ) {
            $wpdb->query("UPDATE $analytics_table SET embedding_tokens = prompt_tokens WHERE embedding_tokens = 0");
        }

        if ( $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") == $logs_table ) {
            $existing_analytics = $wpdb->get_var("SELECT COUNT(*) FROM $analytics_table WHERE session_id != 'admin_dashboard_bot'");
            if ($existing_analytics == 0) {
                $old_logs = $wpdb->get_results("SELECT * FROM $logs_table");
                if (!empty($old_logs)) {
                    foreach ($old_logs as $log) {
                        $user_msg_len = isset($log->user_message) ? strlen($log->user_message) : 0;
                        $bot_msg_len = isset($log->bot_reply) ? strlen($log->bot_reply) : 0;
                        $wpdb->insert($analytics_table, array(
                            'session_id' => isset($log->session_id) ? $log->session_id : 'historical_user',
                            'prompt_tokens' => ceil($user_msg_len / 4),
                            'completion_tokens' => ceil($bot_msg_len / 4),
                            'embedding_tokens' => ceil($user_msg_len / 4),
                            'created_at' => isset($log->created_at) ? $log->created_at : current_time('mysql')
                        ));
                    }
                    wp_send_json_success('Historical data synchronized successfully.');
                }
            } else { wp_send_json_success('Historical data updated! Embeddings recalculated.'); }
        } else { wp_send_json_error('No historical data found.'); }
    }

    public function ajax_get_dashboard_data() {
        check_ajax_referer( 'wp_ai_dashboard_nonce', 'security' );
        
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'ai_chat_analytics';
        $vectors_table = $wpdb->prefix . 'ai_vector_embeddings';
        
        $filter = isset($_POST['date_filter']) ? sanitize_text_field($_POST['date_filter']) : '7days';
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

        $where_clause = "";
        switch ($filter) {
            case 'today': $where_clause = "WHERE DATE(created_at) = CURDATE()"; break;
            case 'yesterday': $where_clause = "WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY"; break;
            case '7days': $where_clause = "WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; break;
            case '30days': $where_clause = "WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"; break;
            case 'this_month': $where_clause = "WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"; break;
            case 'last_month': $where_clause = "WHERE YEAR(created_at) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH) AND MONTH(created_at) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)"; break;
            case 'custom': if ($start_date && $end_date) { $where_clause = $wpdb->prepare("WHERE DATE(created_at) BETWEEN %s AND %s", $start_date, $end_date); } break;
        }

        $user_where = $where_clause ? $where_clause . " AND session_id != 'admin_dashboard_bot'" : "WHERE session_id != 'admin_dashboard_bot'";
        $admin_where = $where_clause ? $where_clause . " AND session_id = 'admin_dashboard_bot'" : "WHERE session_id = 'admin_dashboard_bot'";

        $total_queries = $wpdb->get_var("SELECT COUNT(*) FROM $analytics_table $user_where") ?: 0;
        $unique_users = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM $analytics_table $user_where") ?: 0;
        $total_prompt_tokens = $wpdb->get_var("SELECT SUM(prompt_tokens) FROM $analytics_table $user_where") ?: 0;
        $total_completion_tokens = $wpdb->get_var("SELECT SUM(completion_tokens) FROM $analytics_table $user_where") ?: 0;
        $total_embedding_tokens = $wpdb->get_var("SELECT SUM(embedding_tokens) FROM $analytics_table $user_where") ?: 0;
        $avg_response_time = $wpdb->get_var("SELECT AVG(response_time) FROM $analytics_table $user_where AND response_time > 0") ?: 0;
        $fallbacks = $wpdb->get_var("SELECT COUNT(*) FROM $analytics_table $user_where AND is_fallback = 1") ?: 0;

        $admin_prompt_tokens = $wpdb->get_var("SELECT SUM(prompt_tokens) FROM $analytics_table $admin_where") ?: 0;
        $admin_comp_tokens = $wpdb->get_var("SELECT SUM(completion_tokens) FROM $analytics_table $admin_where") ?: 0;
        $total_admin_tokens = $admin_prompt_tokens + $admin_comp_tokens;

        $chart_results = $wpdb->get_results("SELECT DATE(created_at) as chat_date, COUNT(*) as query_count FROM $analytics_table $user_where GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC");
        $chart_labels = []; $chart_data = [];
        foreach ($chart_results as $row) { $chart_labels[] = date('M d', strtotime($row->chat_date)); $chart_data[] = $row->query_count; }

        $hourly_labels = [];
        for($i = 0; $i < 24; $i++) { $hourly_labels[] = date('h:i A', mktime($i, 0)); }
        
        $hourly_queries = array_fill(0, 24, 0);
        $hq_results = $wpdb->get_results("SELECT HOUR(created_at) as h, COUNT(*) as cnt FROM $analytics_table $user_where GROUP BY HOUR(created_at)");
        if(!empty($hq_results)) { foreach($hq_results as $r) $hourly_queries[(int)$r->h] = (int)$r->cnt; }

        $chat_settings = get_option('wp_ai_chatbot_settings', []);
        $rag_settings = get_option('wp_ai_rag_settings', []);
        $chat_provider = $chat_settings['api_provider'] ?? 'gemini';
        $chat_model = $chat_settings[$chat_provider . '_model'] ?? 'Default';
        $embed_provider = $rag_settings['embedding_provider'] ?? 'gemini';
        $embed_model = $rag_settings[$embed_provider . '_embedding_model'] ?? 'Default';
        $est_cost = (($total_prompt_tokens / 1000000) * 0.15) + (($total_completion_tokens / 1000000) * 0.60) + (($total_embedding_tokens / 1000000) * 0.02);

        $forms = get_option('wp_ai_chatbot_multi_forms', []);
        $total_leads = 0; $form_breakdown_html = ''; $hourly_leads = array_fill(0, 24, 0);
        
        // 🚀 FULLY AWARE CONTEXT DATA
        $raw_context_data = [
            'System Configuration' => [
                'Chat Provider' => ucfirst($chat_provider),
                'LLM Model' => $chat_model,
                'RAG Provider' => ucfirst($embed_provider),
                'Vector Model' => $embed_model
            ],
            'Dashboard Metrics' => [
                'Filter Applied' => $filter,
                'Total Conversations' => $total_queries,
                'Unique Users' => $unique_users,
                'Input Tokens' => $total_prompt_tokens,
                'Output Tokens' => $total_completion_tokens,
                'Embedding Tokens' => $total_embedding_tokens,
                'Admin Bot Tokens' => $total_admin_tokens,
                'Avg Latency' => number_format($avg_response_time, 2) . 's',
                'Estimated Cost' => '$' . number_format($est_cost, 4),
                'Fallbacks (Unanswered)' => $fallbacks
            ],
            'Forms' => []
        ];

        $hourly_activity_assoc = [];
        $hourly_leads_assoc = [];

        foreach($forms as $f) {
            $tbl = $wpdb->prefix . 'ai_leads_' . $f['id'];
            if($wpdb->get_var("SHOW TABLES LIKE '$tbl'") == $tbl) {
                $form_where = $where_clause ? $where_clause : "WHERE 1=1";
                $c = $wpdb->get_var("SELECT COUNT(*) FROM $tbl $form_where") ?: 0;
                $total_leads += $c;
                $form_breakdown_html .= '<li style="display:flex; justify-content:space-between; margin-bottom:12px; border-bottom:1px dashed #eee; padding-bottom:6px; align-items:center;"><span>' . esc_html($f['title']) . '</span> <strong style="color:#00a32a; font-size:18px;">' . intval($c) . '</strong></li>';
                
                $raw_context_data['Forms'][$f['title']] = $c;
                $hl_results = $wpdb->get_results("SELECT HOUR(created_at) as h, COUNT(*) as cnt FROM $tbl $form_where GROUP BY HOUR(created_at)");
                if(!empty($hl_results)) { foreach($hl_results as $r) { $hourly_leads[(int)$r->h] += (int)$r->cnt; } }
            }
        }
        if (empty($form_breakdown_html)) $form_breakdown_html = '<li>No submissions found.</li>';
        $raw_context_data['Total Form Leads'] = $total_leads;

        foreach($hourly_labels as $index => $label) {
            $hourly_activity_assoc[$label] = $hourly_queries[$index];
            $hourly_leads_assoc[$label] = $hourly_leads[$index];
        }
        $raw_context_data['Hourly Activity (Chat Count)'] = $hourly_activity_assoc;
        $raw_context_data['Hourly Leads (Form Count)'] = $hourly_leads_assoc;

        $outdated_vectors = 0; $outdated_list_html = '';
        if($wpdb->get_var("SHOW TABLES LIKE '$vectors_table'") == $vectors_table) {
            $outdated_vectors = $wpdb->get_var("
                SELECT COUNT(*) FROM $vectors_table v 
                INNER JOIN {$wpdb->posts} p ON v.source_id = p.ID 
                AND v.source_type IN ('wc_product', 'wp_page', 'wp_post', 'tutor_course') 
                WHERE p.post_modified > v.updated_at
            ") ?: 0;

            if ($outdated_vectors > 0) {
                $outdated_items = $wpdb->get_results("SELECT p.ID, p.post_title FROM $vectors_table v INNER JOIN {$wpdb->posts} p ON v.source_id = p.ID AND v.source_type IN ('wc_product', 'wp_page', 'wp_post', 'tutor_course') WHERE p.post_modified > v.updated_at LIMIT 5");
                $outdated_list_html .= '<ul style="margin: 8px 0 0 0; padding-left: 15px; list-style-type: circle; font-size: 11px; color: #d63638;">';
                foreach($outdated_items as $item) {
                    $title = !empty($item->post_title) ? esc_html($item->post_title) : 'ID: '.$item->ID;
                    $outdated_list_html .= '<li><a href="'.esc_url(get_edit_post_link($item->ID)).'" target="_blank" style="color:#d63638; text-decoration:underline;">'.$title.'</a></li>';
                }
                if ($outdated_vectors > 5) $outdated_list_html .= '<li>...and ' . ($outdated_vectors - 5) . ' more.</li>';
                $outdated_list_html .= '</ul>';
            }
        }
        $raw_context_data['Outdated Vectors Count'] = $outdated_vectors;

        $v_counts = $wpdb->get_results("SELECT source_type, COUNT(*) as cnt FROM $vectors_table GROUP BY source_type");
        $vec_html = ''; $raw_context_data['Vector DB Inventory'] = [];
        if(!empty($v_counts)) {
            foreach($v_counts as $v) {
                $vec_html .= '<li style="display:flex; justify-content:space-between; margin-bottom:12px; font-size:13px; color:#50575e; align-items:center;"><span>' . ucfirst(esc_html($v->source_type)) . '</span> <span style="color:#1d2327; font-size:18px; font-weight:bold;">' . intval($v->cnt) . '</span></li>';
                $raw_context_data['Vector DB Inventory'][ucfirst($v->source_type)] = intval($v->cnt);
            }
        } else { $vec_html = '<li style="font-size:13px; color:#8c8f94;">No vectors found.</li>'; }

        wp_send_json_success(array(
            'total_queries' => number_format($total_queries), 'unique_users' => number_format($unique_users),
            'total_prompt' => number_format($total_prompt_tokens), 'total_completion' => number_format($total_completion_tokens),
            'total_embeddings' => number_format($total_embedding_tokens), 'total_admin_tokens' => number_format($total_admin_tokens),
            'avg_response' => number_format($avg_response_time, 2) . 's', 'est_cost' => '$' . number_format($est_cost, 4),
            'fallbacks' => number_format($fallbacks), 'chart_labels' => $chart_labels, 'chart_data' => $chart_data,
            'hourly_labels' => $hourly_labels, 'hourly_queries' => $hourly_queries, 'hourly_leads' => $hourly_leads,
            'chat_provider' => ucfirst($chat_provider), 'chat_model' => $chat_model, 'embed_provider' => ucfirst($embed_provider),
            'embed_model' => $embed_model, 'total_leads' => number_format($total_leads), 'form_breakdown' => $form_breakdown_html,
            'outdated_vectors' => number_format($outdated_vectors), 'outdated_list' => $outdated_list_html, 'vec_inventory' => $vec_html,
            'raw_context_string' => json_encode($raw_context_data)
        ));
    }

    public function display_dashboard() {
        global $wpdb;
        $analytics_table = $wpdb->prefix . 'ai_chat_analytics';
        $earliest_date_str = current_time('Y-m-d');
        if ( $wpdb->get_var("SHOW TABLES LIKE '$analytics_table'") == $analytics_table ) {
            $earliest = $wpdb->get_var("SELECT DATE(MIN(created_at)) FROM $analytics_table");
            if ($earliest) $earliest_date_str = $earliest;
        }
        $today_str = current_time('Y-m-d');
        ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
        
        <style>
            .admin-chatbot-container { background: #fff; border-radius: 8px; border: 1px solid #ccd0d4; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
            .admin-chatbot-header { background: #007cba; color: #fff; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 14px; }
            .admin-chatbot-header button { background: none; border: none; color: #fff; cursor: pointer; opacity: 0.8; transition: 0.2s; padding: 0; outline: none; }
            .admin-chatbot-header button:hover { opacity: 1; transform: scale(1.1); }
            .admin-chat-body { flex: 1; padding: 15px; overflow-y: auto; background: #f6f7f7; min-height: 280px; max-height: 350px; display: flex; flex-direction: column; gap: 15px; scroll-behavior: smooth; }
            .chat-msg { display: flex; gap: 10px; max-width: 90%; }
            .chat-msg.admin-msg { align-self: flex-end; flex-direction: row-reverse; }
            .chat-msg.ai-msg { align-self: flex-start; }
            .chat-avatar { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff; }
            .admin-msg .chat-avatar { background: #1d2327; }
            .ai-msg .chat-avatar { background: #007cba; }
            .chat-bubble { padding: 10px 14px; border-radius: 8px; line-height: 1.5; font-size: 13px; }
            .admin-msg .chat-bubble { background: #1d2327; color: #fff; border-top-right-radius: 0; }
            .ai-msg .chat-bubble { background: #fff; color: #3c434a; border: 1px solid #e2e4e7; border-top-left-radius: 0; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
            .chat-bubble p { margin: 0 0 8px 0; } .chat-bubble p:last-child { margin: 0; }
            .chat-bubble h3, .chat-bubble h4 { margin: 12px 0 8px 0; font-size: 14px; font-weight: 600; color: #1d2327; }
            .chat-bubble ul, .chat-bubble ol { margin: 0 0 10px 20px; padding: 0; } .chat-bubble li { margin-bottom: 4px; }
            .chat-bubble strong { font-weight: 600; color: #1d2327; }
            .admin-chat-footer { padding: 12px; background: #fff; border-top: 1px solid #ccd0d4; }
            .chat-input-wrapper { display: flex; gap: 10px; background: #f0f0f1; border-radius: 20px; padding: 4px 6px 4px 15px; align-items: center; border: 1px solid #e2e4e7;}
            .chat-input-wrapper input { border: none; background: transparent; box-shadow: none; flex: 1; outline: none; font-size: 13px; }
            .chat-input-wrapper input:focus { box-shadow: none !important; outline: none !important; border: none !important; }
            .chat-send-btn { background: #007cba; color: #fff; border: none; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; }
            .chat-send-btn:hover { background: #005a87; }
            .typing-indicator { display: none; align-items: center; gap: 4px; padding: 10px 15px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; width: fit-content; border-top-left-radius: 0; box-shadow: 0 1px 2px rgba(0,0,0,0.05);}
            .typing-dot { width: 6px; height: 6px; background: #007cba; border-radius: 50%; animation: typing 1.4s infinite ease-in-out both; }
            .typing-dot:nth-child(1) { animation-delay: -0.32s; } .typing-dot:nth-child(2) { animation-delay: -0.16s; }
            @keyframes typing { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
        </style>

        <div class="wrap">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; background:#fff; padding:15px 20px; border:1px solid #ccd0d4; border-radius:4px;">
                <h1 style="margin:0; font-size:20px; font-weight:600; color:#1d2327;">Advanced System Analytics</h1>
                
                <div style="display:flex; align-items:center; gap:15px;">
                    <button id="sync-history-btn" class="button">Sync Historical Data</button>
                    <span class="spinner" id="dashboard-spinner" style="float:none; margin:0;"></span>
                    
                    <div id="custom-date-range" style="display:none; align-items:center; gap:5px;">
                        <input type="date" id="dash-start-date" min="<?php echo esc_attr($earliest_date_str); ?>" max="<?php echo esc_attr($today_str); ?>" style="padding: 2px 8px; font-size: 13px;">
                        <span>to</span>
                        <input type="date" id="dash-end-date" min="<?php echo esc_attr($earliest_date_str); ?>" max="<?php echo esc_attr($today_str); ?>" style="padding: 2px 8px; font-size: 13px;">
                        <button id="apply-custom-date" class="button button-secondary button-small">Apply</button>
                    </div>

                    <select id="dashboard-date-filter" style="padding: 4px 10px; font-size: 13px; border-radius: 4px; border: 1px solid #8c8f94;">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="7days">Last 7 Days</option>
                        <option value="30days">Last 30 Days</option>
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="custom">Custom Date Range</option>
                        <option value="all">All Time</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap: 15px; margin-bottom: 20px; align-items: stretch;">
                <div class="admin-chatbot-container" style="flex: 1.5;">
                    <div class="admin-chatbot-header">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="dashicons dashicons-chart-pie"></span> Data Analyst
                        </div>
                        <button id="clear-admin-chat" title="Clear Chat History"><span class="dashicons dashicons-trash"></span></button>
                    </div>
                    <div id="admin-chat-box" class="admin-chat-body">
                        <div id="chat-typing" class="chat-msg ai-msg" style="display:none;">
                            <div class="chat-avatar"><span class="dashicons dashicons-lightbulb"></span></div>
                            <div class="typing-indicator" style="display:flex;"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>
                        </div>
                    </div>
                    <div class="admin-chat-footer">
                        <div class="chat-input-wrapper">
                            <input type="text" id="admin-chat-input" placeholder="Ask your Data Analyst...">
                            <button id="admin-chat-send" class="chat-send-btn"><span class="dashicons dashicons-arrow-right-alt2" style="font-size:16px; line-height:1; padding-top:2px;"></span></button>
                        </div>
                    </div>
                </div>

                <div style="flex: 2.5; display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div style="background:#fff; padding:20px; border-radius:4px; border:1px solid #ccd0d4;">
                        <h3 style="margin-top:0; font-size:15px; color:#1d2327;">Current AI Engine</h3>
                        <ul style="margin:0; font-size:13px; color:#50575e; line-height:2.0;">
                            <li><strong>Chat Provider:</strong> <span id="sys-chat-provider">...</span></li>
                            <li><strong>LLM Model:</strong> <span id="sys-chat-model" style="color:#2271b1; font-weight:bold;">...</span></li>
                            <li><strong>RAG Provider:</strong> <span id="sys-embed-provider">...</span></li>
                            <li><strong>Vector Model:</strong> <span id="sys-embed-model" style="color:#00a32a; font-weight:bold;">...</span></li>
                        </ul>
                    </div>
                    <div style="background:#fff; padding:20px; border-radius:4px; border:1px solid #ccd0d4;">
                        <h3 style="margin-top:0; font-size:15px; color:#1d2327; margin-bottom:15px;">Vector Inventory</h3>
                        <ul id="sys-vector-inventory" style="margin:0; padding:0; list-style:none;"></ul>
                    </div>
                    <div style="background:#fff; padding:20px; border-radius:4px; border:1px solid #ccd0d4; border-top: 3px solid #00a32a;">
                        <h3 style="margin-top:0; font-size:15px; color:#1d2327;">Form Conversions</h3>
                        <p style="font-size:11px; color:#8c8f94; margin:0 0 10px 0;">Breakdown by selected date.</p>
                        <ul id="sys-form-breakdown" style="margin:0; font-size:13px; color:#50575e; line-height:1.8; padding:0; list-style:none;"></ul>
                    </div>
                    <div style="background:#fff; padding:20px; border-radius:4px; border:1px solid #ccd0d4; border-left: 4px solid #d63638;">
                        <h3 style="margin-top:0; font-size:15px; color:#1d2327;">System Alerts</h3>
                        <div style="margin-bottom:15px;">
                            <span style="display:flex; justify-content:space-between; font-size:12px; font-weight:600; color:#646970; align-items:center;">
                                UNANSWERED (FALLBACKS) <span id="stat-fallbacks" style="font-size:24px; font-weight:bold; color:#d63638;">0</span>
                            </span>
                        </div>
                        <div style="border-top:1px solid #eee; padding-top:10px;">
                            <span style="display:flex; justify-content:space-between; font-size:12px; font-weight:600; color:#646970; align-items:center;">
                                OUTDATED RAG VECTORS <span id="stat-outdated-vectors" style="font-size:24px; font-weight:bold; color:#f56e28;">0</span>
                            </span>
                            <p style="font-size:11px; color:#8c8f94; margin:4px 0 0 0;">Pages/Products updated on website but not synced to Vector DB.</p>
                            <div id="sys-outdated-list"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px;">
                <div style="background:#fff; padding:20px; border-radius:4px; border:1px solid #ccd0d4;">
                    <h4 style="margin:0 0 8px 0; color:#50575e; font-size:13px; text-transform:uppercase;">Total Conversations</h4>
                    <div style="font-size:28px; font-weight:600; color:#1d2327;" id="stat-queries">0</div>
                </div>
                <div style="background:#fff; padding:20px; border-radius:4px; border:1px solid #ccd0d4;">
                    <h4 style="margin:0 0 8px 0; color:#50575e; font-size:13px; text-transform:uppercase;">Unique Users</h4>
                    <div style="font-size:28px; font-weight:600; color:#1d2327;" id="stat-users">0</div>
                </div>
                <div style="background:#fff; padding:20px; border-radius:4px; border:1px solid #ccd0d4;">
                    <h4 style="margin:0 0 8px 0; color:#50575e; font-size:13px; text-transform:uppercase;">Total Leads (Forms)</h4>
                    <div style="font-size:28px; font-weight:600; color:#00a32a;" id="stat-total-leads">0</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: repeat(6, 1fr); gap: 15px; margin-bottom: 20px;">
                <div style="background:#fff; padding:15px; border-radius:4px; border:1px solid #ccd0d4;"><h4 style="margin:0 0 5px 0; color:#50575e; font-size:12px; text-transform:uppercase;">Input Tokens</h4><div style="font-size:22px; font-weight:600; color:#2271b1;" id="stat-prompt-tokens">0</div></div>
                <div style="background:#fff; padding:15px; border-radius:4px; border:1px solid #ccd0d4;"><h4 style="margin:0 0 5px 0; color:#50575e; font-size:12px; text-transform:uppercase;">Output Tokens</h4><div style="font-size:22px; font-weight:600; color:#00a32a;" id="stat-comp-tokens">0</div></div>
                <div style="background:#fff; padding:15px; border-radius:4px; border:1px solid #ccd0d4; border-bottom: 3px solid #8c8f94;"><h4 style="margin:0 0 5px 0; color:#50575e; font-size:12px; text-transform:uppercase;">Embedding Tokens</h4><div style="font-size:22px; font-weight:600; color:#1d2327;" id="stat-embed-tokens">0</div></div>
                <div style="background:#f6f7f7; padding:15px; border-radius:4px; border:1px solid #007cba;"><h4 style="margin:0 0 5px 0; color:#007cba; font-size:12px; text-transform:uppercase;">Admin Bot Tokens</h4><div style="font-size:22px; font-weight:600; color:#007cba;" id="stat-admin-tokens">0</div></div>
                <div style="background:#fff; padding:15px; border-radius:4px; border:1px solid #ccd0d4;"><h4 style="margin:0 0 5px 0; color:#50575e; font-size:12px; text-transform:uppercase;">Avg Latency</h4><div style="font-size:22px; font-weight:600; color:#1d2327;" id="stat-latency">0.00s</div></div>
                <div style="background:#fff; padding:15px; border-radius:4px; border:1px solid #ccd0d4;"><h4 style="margin:0 0 5px 0; color:#50575e; font-size:12px; text-transform:uppercase;">Estimated Cost</h4><div style="font-size:22px; font-weight:600; color:#d63638;" id="stat-cost">$0.0000</div></div>
            </div>

            <div style="display:flex; flex-direction:column; gap:20px; margin-bottom: 20px;">
                <div style="background:#fff; padding:20px; border-radius:4px; border:1px solid #ccd0d4;"><h3 style="margin-top:0; font-size:15px; color:#1d2327;">Daily Interaction Trend</h3><canvas id="dailyChart" height="70"></canvas></div>
                <div style="background:#fff; padding:20px; border-radius:4px; border:1px solid #ccd0d4;"><h3 style="margin-top:0; font-size:15px; color:#1d2327;">Hourly Peak Times (Activity vs Conversions)</h3><canvas id="hourlyChart" height="70"></canvas></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var savedFilter = localStorage.getItem('wp_ai_dashboard_filter') || '7days';
            $('#dashboard-date-filter').val(savedFilter);
            window.dashboardContextData = '{}'; 
            
            var adminChatHistory = JSON.parse(localStorage.getItem('wp_ai_admin_chat_history')) || [];
            
            function renderChatHistory() {
                var chatBox = $('#admin-chat-box');
                chatBox.find('.chat-msg:not(#chat-typing)').remove();
                
                adminChatHistory.forEach(function(msg) {
                    if (msg.role === 'ai') {
                        var formatted = marked.parse(msg.text);
                        $('#chat-typing').before('<div class="chat-msg ai-msg"><div class="chat-avatar"><span class="dashicons dashicons-lightbulb"></span></div><div class="chat-bubble">' + formatted + '</div></div>');
                    } else {
                        $('#chat-typing').before('<div class="chat-msg admin-msg"><div class="chat-avatar"><span class="dashicons dashicons-admin-users"></span></div><div class="chat-bubble">' + msg.text + '</div></div>');
                    }
                });
                chatBox.scrollTop(chatBox[0].scrollHeight);
            }

            if(savedFilter === 'custom') { $('#custom-date-range').css('display', 'flex'); }

            function fetchInitialGreeting() {
                var typingIndicator = $('#chat-typing');
                typingIndicator.show();
                $.post(ajaxurl, {
                    action: 'wp_ai_admin_dashboard_chat',
                    security: '<?php echo wp_create_nonce("wp_ai_dashboard_nonce"); ?>',
                    message: 'INIT_GREETING',
                    context: window.dashboardContextData,
                    history: '' 
                }, function(response) {
                    typingIndicator.hide();
                    if(response.success) {
                        adminChatHistory.push({role: 'ai', text: response.data.reply});
                        localStorage.setItem('wp_ai_admin_chat_history', JSON.stringify(adminChatHistory));
                        renderChatHistory();
                    }
                });
            }

            $('#clear-admin-chat').on('click', function(e){
                e.preventDefault();
                if(confirm("Are you sure you want to clear the AI Chat history?")) {
                    localStorage.removeItem('wp_ai_admin_chat_history');
                    adminChatHistory = [];
                    renderChatHistory();
                    fetchInitialGreeting();
                }
            });

            function loadDashboardData() {
                $('#dashboard-spinner').addClass('is-active');
                $.post(ajaxurl, {
                    action: 'wp_ai_get_dashboard_data',
                    security: '<?php echo wp_create_nonce("wp_ai_dashboard_nonce"); ?>',
                    date_filter: $('#dashboard-date-filter').val(),
                    start_date: $('#dash-start-date').val(),
                    end_date: $('#dash-end-date').val()
                }, function(response) {
                    $('#dashboard-spinner').removeClass('is-active');
                    if (response.success) {
                        var data = response.data;
                        $('#stat-queries').text(data.total_queries); $('#stat-users').text(data.unique_users);
                        $('#stat-prompt-tokens').text(data.total_prompt); $('#stat-comp-tokens').text(data.total_completion);
                        $('#stat-embed-tokens').text(data.total_embeddings); $('#stat-admin-tokens').text(data.total_admin_tokens);
                        $('#stat-latency').text(data.avg_response); $('#stat-cost').text(data.est_cost); $('#stat-fallbacks').text(data.fallbacks);
                        $('#stat-total-leads').text(data.total_leads); $('#stat-outdated-vectors').text(data.outdated_vectors);
                        $('#sys-outdated-list').html(data.outdated_list); $('#sys-chat-provider').text(data.chat_provider);
                        $('#sys-chat-model').text(data.chat_model); $('#sys-embed-provider').text(data.embed_provider);
                        $('#sys-embed-model').text(data.embed_model); $('#sys-form-breakdown').html(data.form_breakdown);
                        $('#sys-vector-inventory').html(data.vec_inventory);

                        window.dashboardContextData = data.raw_context_string;
                        updateCharts(data.chart_labels, data.chart_data, data.hourly_labels, data.hourly_queries, data.hourly_leads);

                        if (adminChatHistory.length === 0) { fetchInitialGreeting(); } else { renderChatHistory(); }
                    }
                });
            }

            function updateCharts(labels, data, h_labels, h_queries, h_leads) {
                var ctxDaily = document.getElementById('dailyChart').getContext('2d');
                if (window.dailyChartInst) window.dailyChartInst.destroy(); 
                window.dailyChartInst = new Chart(ctxDaily, { type: 'bar', data: { labels: labels, datasets: [{ label: 'Queries Initiated', data: data, backgroundColor: '#2271b1', borderRadius: 2 }] }, options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } } });

                var ctxHourly = document.getElementById('hourlyChart').getContext('2d');
                if (window.hourlyChartInst) window.hourlyChartInst.destroy(); 
                window.hourlyChartInst = new Chart(ctxHourly, { type: 'line', data: { labels: h_labels, datasets: [{ label: 'Conversations', data: h_queries, borderColor: '#2271b1', backgroundColor: 'rgba(34, 113, 177, 0.1)', fill: true, tension: 0.3 }, { label: 'Conversions', data: h_leads, borderColor: '#00a32a', backgroundColor: 'rgba(0, 163, 42, 0.1)', fill: true, tension: 0.3 }] }, options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: true, position: 'top' } } } });
            }

            loadDashboardData();

            $('#admin-chat-send').on('click', function(e) {
                e.preventDefault(); var msgInput = $('#admin-chat-input'); var msg = msgInput.val().trim();
                if (msg === '') return;
                
                adminChatHistory.push({role: 'admin', text: msg});
                localStorage.setItem('wp_ai_admin_chat_history', JSON.stringify(adminChatHistory));
                renderChatHistory();
                
                msgInput.val(''); var btn = $(this); btn.prop('disabled', true);
                $('#chat-typing').show(); $('#admin-chat-box').scrollTop($('#admin-chat-box')[0].scrollHeight);

                var historyForApi = adminChatHistory.slice(-24).map(function(item){ return item.role.toUpperCase() + ": " + item.text; }).join("\n");

                $.post(ajaxurl, {
                    action: 'wp_ai_admin_dashboard_chat',
                    security: '<?php echo wp_create_nonce("wp_ai_dashboard_nonce"); ?>',
                    message: msg,
                    context: window.dashboardContextData,
                    history: historyForApi
                }, function(response) {
                    $('#chat-typing').hide(); 
                    if(response.success) {
                        adminChatHistory.push({role: 'ai', text: response.data.reply});
                        localStorage.setItem('wp_ai_admin_chat_history', JSON.stringify(adminChatHistory));
                        renderChatHistory(); loadDashboardData(); 
                    } else {
                        $('#chat-typing').before('<div class="chat-msg ai-msg"><div class="chat-avatar" style="background:#d63638;">!</div><div class="chat-bubble" style="color:#d63638;">Error connecting to API.</div></div>');
                    }
                    btn.prop('disabled', false); $('#admin-chat-box').scrollTop($('#admin-chat-box')[0].scrollHeight);
                });
            });

            $('#admin-chat-input').keypress(function(e) { if (e.which == 13) { $('#admin-chat-send').click(); return false; } });
            $('#dashboard-date-filter').on('change', function() { var val = $(this).val(); localStorage.setItem('wp_ai_dashboard_filter', val); if (val === 'custom') { $('#custom-date-range').css('display', 'flex'); } else { $('#custom-date-range').hide(); loadDashboardData(); } });
            $('#apply-custom-date').on('click', function() { var sDate = $('#dash-start-date').val(); var eDate = $('#dash-end-date').val(); if(!sDate || !eDate) { alert('Please select both dates.'); return; } if(new Date(sDate) > new Date(eDate)) { alert('Start date cannot be greater than end date.'); return; } localStorage.setItem('wp_ai_dash_start', sDate); localStorage.setItem('wp_ai_dash_end', eDate); loadDashboardData(); });
            $('#sync-history-btn').on('click', function(e) { e.preventDefault(); var btn = $(this); btn.text('Syncing...').prop('disabled', true); $.post(ajaxurl, { action: 'wp_ai_sync_historical_data', security: '<?php echo wp_create_nonce("wp_ai_dashboard_nonce"); ?>' }, function(response) { alert(response.data); loadDashboardData(); btn.text('Sync Historical Data').prop('disabled', false); }); });
        });
        </script>
        <?php
    }
}