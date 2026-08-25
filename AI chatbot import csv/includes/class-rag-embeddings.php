<?php
/**
 * Vector RAG Embeddings Generator
 * SMART BATCH PROCESSING & AUTO-RESUME
 * Includes Granular Multi-Select Control for WC, Pages, Posts, & Tutor LMS
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_RAG_Embeddings {

    private $batch_limit = 20; 
    private $processed_this_run = 0;
    public $has_more_items = false;

    public function register_hooks() {
        add_action( 'wp_ai_trigger_vector_sync', array( $this, 'process_all_sources' ) );
    }

    public function process_all_sources() {
        $options = get_option('wp_ai_rag_settings', []);
        $stats = ['processed' => 0];
        
        delete_transient('wp_ai_rag_api_error');

        // 1. Process WooCommerce
        if ( !empty($options['wc_products_strategy']) && $options['wc_products_strategy'] !== 'none' && class_exists('WooCommerce') ) {
            $res = $this->process_woocommerce_products($options);
            if ($res === false) return; 
            $stats['processed'] += $res['processed'];
        }
        
        // 2. Process Posts
        if ( !empty($options['wp_posts_strategy']) && $options['wp_posts_strategy'] !== 'none' && !$this->has_more_items ) {
            $res = $this->process_wp_posts('post', 'wp_post', $options, 'wp_posts_strategy', 'wp_posts_ids');
            if ($res === false) return;
            $stats['processed'] += $res['processed'];
        }
        
        // 3. Process Pages
        if ( !empty($options['wp_pages_strategy']) && $options['wp_pages_strategy'] !== 'none' && !$this->has_more_items ) {
            $res = $this->process_wp_posts('page', 'wp_page', $options, 'wp_pages_strategy', 'wp_pages_ids');
            if ($res === false) return;
            $stats['processed'] += $res['processed'];
        }

        // 4. Process Tutor LMS Courses
        if ( !empty($options['tutor_courses_strategy']) && $options['tutor_courses_strategy'] !== 'none' && !$this->has_more_items && post_type_exists('courses') ) {
            $res = $this->process_wp_posts('courses', 'tutor_course', $options, 'tutor_courses_strategy', 'tutor_courses_ids');
            if ($res === false) return;
            $stats['processed'] += $res['processed'];
        }

        // 5. Process CSV Knowledge Bases
        if ( !empty($options['custom_kbs_strategy']) && $options['custom_kbs_strategy'] !== 'none' && !$this->has_more_items ) {
            $res = $this->process_custom_kbs($options);
            if ($res === false) return;
            $stats['processed'] += $res['processed'];
        }

        $api_error = get_transient('wp_ai_rag_api_error');

        // FIX: Smart Redirect Logic (Shows exactly what happened)
        if ($api_error) {
            $message = "Sync Stopped! API Error: " . $api_error;
            $redirect_url = admin_url('admin.php?page=wp-ai-chatbot-rag&message=' . urlencode($message) . '&status=error');
        } elseif ($this->has_more_items) {
            $message = "Batch successful! Generated {$stats['processed']} new vectors. Resting API for 3 seconds then auto-resuming...";
            $redirect_url = admin_url('admin.php?page=wp-ai-chatbot-rag&message=' . urlencode($message) . '&status=success&auto_resume=1');
        } else {
            if ($stats['processed'] > 0) {
                // Agar kuch naya add hua
                $message = "Sync Complete! Successfully generated {$stats['processed']} new vectors and saved them to the Database.";
            } else {
                // Agar sab kuch pehle se mojood tha (Skipped)
                $message = "Sync Complete! 0 new items processed (All your selected data already exists in the Vector Database!).";
            }
            $redirect_url = admin_url('admin.php?page=wp-ai-chatbot-rag&message=' . urlencode($message) . '&status=success');
        }

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function process_woocommerce_products($options) {
        global $wpdb;
        $processed = 0;
        
        $strategy = $options['wc_products_strategy'] ?? 'none';
        if ($strategy === 'none') return ['processed' => 0];

        $where_clause = "";
        if ($strategy === 'specific') {
            $ids = $options['wc_products_ids'] ?? [];
            if (empty($ids)) return ['processed' => 0];
            $ids_str = implode(',', array_map('intval', $ids));
            $where_clause = " AND p.ID IN ($ids_str)";
        }

        $table_name = $wpdb->prefix . 'ai_vector_embeddings';
        $limit = $this->batch_limit - $this->processed_this_run;
        
        $query = $wpdb->prepare("SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$table_name} v ON p.ID = v.source_id AND v.source_type = 'wc_product' WHERE p.post_type = 'product' AND p.post_status = 'publish' AND v.id IS NULL {$where_clause} LIMIT %d", $limit);
        $product_ids = $wpdb->get_col($query);

        if (count($product_ids) == $limit) $this->has_more_items = true;

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            $title = $product->get_name();
            $price = $product->get_price();
            $sku = $product->get_sku();
            $desc = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
            
            $cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
            $cat_str = !is_wp_error($cats) ? implode(', ', $cats) : '';

            $text_to_embed = "Product Name: $title. ";
            if ($sku) $text_to_embed .= "SKU: $sku. ";
            if ($price) $text_to_embed .= "Price: $price. ";
            if ($cat_str) $text_to_embed .= "Category: $cat_str. ";
            $text_to_embed .= "Details: " . wp_strip_all_tags($desc);
            $text_to_embed = preg_replace('/\\s+/', ' ', $text_to_embed); 

            if (empty(trim($text_to_embed))) {
                $this->save_to_db('wc_product', $product_id, $title, 'Empty Data', '[]');
                continue;
            }

            $vector_array = $this->get_embedding($text_to_embed, $options);
            if ($vector_array === false) return false;

            $this->save_to_db('wc_product', $product_id, $title, $text_to_embed, wp_json_encode($vector_array));
            $processed++;
            $this->processed_this_run++;
            
            if ($this->processed_this_run >= $this->batch_limit) break;
        }
        return ['processed' => $processed];
    }

    private function process_wp_posts($post_type, $source_type, $options, $strategy_key, $ids_key) {
        global $wpdb;
        $processed = 0;

        $strategy = $options[$strategy_key] ?? 'none';
        if ($strategy === 'none') return ['processed' => 0];

        $where_clause = "";
        if ($strategy === 'specific') {
            $ids = $options[$ids_key] ?? [];
            if (empty($ids)) return ['processed' => 0];
            $ids_str = implode(',', array_map('intval', $ids));
            $where_clause = " AND p.ID IN ($ids_str)";
        }
        
        $table_name = $wpdb->prefix . 'ai_vector_embeddings';
        $limit = $this->batch_limit - $this->processed_this_run;

        $query = $wpdb->prepare("SELECT p.ID, p.post_title, p.post_content, p.post_excerpt FROM {$wpdb->posts} p LEFT JOIN {$table_name} v ON p.ID = v.source_id AND v.source_type = %s WHERE p.post_type = %s AND p.post_status = 'publish' AND v.id IS NULL {$where_clause} LIMIT %d", $source_type, $post_type, $limit);
        
        $posts = $wpdb->get_results($query);
        if (count($posts) == $limit) $this->has_more_items = true;

        foreach ($posts as $post) {
            
            // UNIVERSAL BUILDER EXTRACTION
            $raw_content = $post->post_content;
            
            if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->db->is_built_with_elementor( $post->ID ) ) {
                $raw_content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post->ID );
            } else {
                if ( function_exists( 'do_blocks' ) ) {
                    $raw_content = do_blocks( $raw_content );
                }
                $raw_content = do_shortcode( $raw_content );
            }
            
            $clean_content = wp_strip_all_tags( $raw_content );
            
            // Agar excerpt majood hai, toh usay bhi shamil karein
            if ( !empty($post->post_excerpt) ) {
                $clean_content = wp_strip_all_tags($post->post_excerpt) . " " . $clean_content;
            }

            $text_to_embed = wp_strip_all_tags($post->post_title) . ". " . $clean_content;
            $text_to_embed = preg_replace('/\\s+/', ' ', $text_to_embed); 
            
            if (empty(trim($text_to_embed))) {
                $this->save_to_db($source_type, $post->ID, $post->post_title, 'Empty', '[]');
                continue;
            }

            $vector_array = $this->get_embedding($text_to_embed, $options);
            if ($vector_array === false) return false;

            $this->save_to_db($source_type, $post->ID, $post->post_title, $text_to_embed, wp_json_encode($vector_array));
            $processed++;
            $this->processed_this_run++;
            
            if ($this->processed_this_run >= $this->batch_limit) break;
        }
        return ['processed' => $processed];
    }

    private function process_custom_kbs($options) {
        global $wpdb;
        $kbs = get_option('wp_ai_chatbot_knowledge_bases', []);
        $processed = 0;

        $strategy = $options['custom_kbs_strategy'] ?? 'none';
        if ($strategy === 'none' || empty($kbs)) return ['processed' => 0];

        $allowed_kbs = [];
        if ($strategy === 'specific') {
            $allowed_kbs = $options['custom_kbs_ids'] ?? [];
            if (empty($allowed_kbs)) return ['processed' => 0];
        }

        $table_name_vec = $wpdb->prefix . 'ai_vector_embeddings';

        foreach ($kbs as $kb_id => $kb_name) {
            if ($strategy === 'specific' && !in_array($kb_id, $allowed_kbs)) continue;

            if ($this->processed_this_run >= $this->batch_limit) {
                $this->has_more_items = true;
                break;
            }

            $table_name = $wpdb->prefix . 'ai_kb_' . $kb_id;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) continue;

            $cols = array_diff($wpdb->get_col("DESCRIBE $table_name", 0), ['id', 'created_at']);
            $limit = $this->batch_limit - $this->processed_this_run;
            
            $query = $wpdb->prepare("SELECT r.* FROM $table_name r LEFT JOIN $table_name_vec v ON CONCAT(%s, r.id) = v.source_id AND v.source_type = 'custom_kb' WHERE v.id IS NULL LIMIT %d", $kb_id . '_', $limit);
            $rows = $wpdb->get_results($query, ARRAY_A);

            if (count($rows) > 0 && count($rows) == $limit) $this->has_more_items = true;

            foreach ($rows as $row) {
                $text_parts = [];
                foreach ($cols as $col) {
                    if (!empty($row[$col])) {
                        $text_parts[] = ucfirst(str_replace('_', ' ', $col)) . ": " . wp_strip_all_tags($row[$col]);
                    }
                }
                
                $text_to_embed = implode(". ", $text_parts);
                $source_id = $kb_id . '_' . $row['id'];
                
                if (empty(trim($text_to_embed))) {
                    $this->save_to_db('custom_kb', $source_id, 'Empty', 'Empty', '[]');
                    continue;
                }

                $first_col = reset($cols);
                $title = !empty($row[$first_col]) ? $row[$first_col] : $kb_name . " Record " . $row['id'];

                $vector_array = $this->get_embedding($text_to_embed, $options);
                if ($vector_array === false) return false; 

                $this->save_to_db('custom_kb', $source_id, $title, $text_to_embed, wp_json_encode($vector_array));
                $processed++;
                $this->processed_this_run++;
                
                if ($this->processed_this_run >= $this->batch_limit) break;
            }
        }
        return ['processed' => $processed];
    }

    private function get_embedding($text, $options) {
        sleep(2); 
        $api_settings = get_option('wp_ai_chatbot_settings', []);
        $provider = isset($options['embedding_provider']) ? $options['embedding_provider'] : 'gemini';

        if ($provider === 'openai') {
            $model = !empty($options['openai_embedding_model']) ? $options['openai_embedding_model'] : 'text-embedding-3-small';
            return $this->get_openai_embedding($text, $model, $api_settings);
        } else {
            $model = !empty($options['gemini_embedding_model']) ? $options['gemini_embedding_model'] : 'text-embedding-004';
            return $this->get_gemini_embedding($text, $model, $api_settings);
        }
    }

    private function get_openai_embedding($text, $model, $api_settings) {
        $api_key = isset($api_settings['openai_api_key']) ? $api_settings['openai_api_key'] : '';
        if (empty($api_key)) { set_transient('wp_ai_rag_api_error', 'OpenAI API Key is missing.', 60); return false; }

        $response = wp_remote_post('https://api.openai.com/v1/embeddings', array(
            'timeout' => 20, 'sslverify' => false,
            'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key),
            'body' => wp_json_encode(array('model' => $model, 'input' => mb_substr($text, 0, 8000)))
        ));

        if (is_wp_error($response)) { set_transient('wp_ai_rag_api_error', $response->get_error_message(), 60); return false; }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) { set_transient('wp_ai_rag_api_error', $body['error']['message'], 60); return false; }
        return isset($body['data'][0]['embedding']) ? $body['data'][0]['embedding'] : false;
    }

    private function get_gemini_embedding($text, $model, $api_settings) {
        $api_key = isset($api_settings['gemini_api_key']) ? $api_settings['gemini_api_key'] : '';
        if (empty($api_key)) { set_transient('wp_ai_rag_api_error', 'Gemini API Key is missing.', 60); return false; }

        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':embedContent?key=' . $api_key;
        $body = array('model' => 'models/' . $model, 'content' => array('parts' => array(array('text' => mb_substr($text, 0, 8000)))));

        $response = wp_remote_post($endpoint, array(
            'timeout' => 20, 'sslverify' => false,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($body)
        ));

        if (is_wp_error($response)) { set_transient('wp_ai_rag_api_error', $response->get_error_message(), 60); return false; }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) { set_transient('wp_ai_rag_api_error', $body['error']['message'], 60); return false; }
        return isset($body['embedding']['values']) ? $body['embedding']['values'] : false;
    }

    private function save_to_db($source_type, $source_id, $title, $content, $vector_json) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_vector_embeddings';
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO $table_name (source_type, source_id, title, content, vector_data) VALUES (%s, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), vector_data = VALUES(vector_data), updated_at = CURRENT_TIMESTAMP",
            $source_type, $source_id, $title, $content, $vector_json
        ));
    }
}