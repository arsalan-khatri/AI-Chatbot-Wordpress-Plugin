<?php
/**
 * Vector RAG Search Engine (The Semantic & Hybrid Brain)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_RAG_Engine {

    public function search_relevant_knowledge($query) {
        if (empty(trim($query))) return ""; 

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_vector_embeddings';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) return "";

        $options = get_option('wp_ai_rag_settings', array());
        
        $query_vector = $this->get_query_embedding($query, $options);
        
        $scored_ids = [];
        $query_lower = strtolower($query);

        // 1. SEMANTIC VECTOR SEARCH
        if ($query_vector) {
            $records = $wpdb->get_results("SELECT id, vector_data FROM $table_name");
            if (!empty($records)) {
                $word_count = str_word_count($query);
                $dynamic_threshold = ($word_count <= 3) ? 0.35 : 0.45;
                
                if (strpos($query_lower, 'contact') !== false || strpos($query_lower, 'phone') !== false || strpos($query_lower, 'email') !== false || strpos($query_lower, 'rabta') !== false) {
                    $dynamic_threshold = 0.20; 
                }

                foreach ($records as $row) {
                    $db_vector = json_decode($row->vector_data, true);
                    if (!is_array($db_vector)) continue;

                    $similarity = $this->cosine_similarity($query_vector, $db_vector);
                    
                    if ($similarity > $dynamic_threshold) {
                        $scored_ids[$row->id] = $similarity;
                    }
                }
            }
        }

        // 2. HYBRID EXACT KEYWORD MATCH
        if (strpos($query_lower, 'contact') !== false || strpos($query_lower, 'address') !== false || strpos($query_lower, 'phone') !== false || strpos($query_lower, 'email') !== false || strpos($query_lower, 'location') !== false) {
            $kw_records = $wpdb->get_results("SELECT id FROM $table_name WHERE title LIKE '%contact%' OR title LIKE '%rabta%' OR content LIKE '%phone%' OR content LIKE '%email%' OR content LIKE '%address%' LIMIT 5");
            if (!empty($kw_records)) {
                foreach ($kw_records as $kr) {
                    if (!isset($scored_ids[$kr->id]) || $scored_ids[$kr->id] < 0.99) {
                        $scored_ids[$kr->id] = 0.99; 
                    }
                }
            }
        }

        if (empty($scored_ids)) return "";

        arsort($scored_ids);
        
        $top_ids = array_slice(array_keys($scored_ids), 0, 100);
        if (empty($top_ids)) return "";

        $ids_string = implode(',', array_map('intval', $top_ids));
        $top_records = $wpdb->get_results("SELECT id, source_type, source_id, title, content FROM $table_name WHERE id IN ($ids_string)");

        foreach ($top_records as &$res) {
            $res->sales = 0;
            if ($res->source_type === 'wc_product') {
                $clean_id = intval(preg_replace('/[^0-9]/', '', $res->source_id));
                $res->sales = (int) get_post_meta($clean_id, 'total_sales', true);
            }
        }

        $wants_all = (strpos($query_lower, 'all') !== false || strpos($query_lower, 'sare') !== false || strpos($query_lower, 'tamam') !== false || strpos($query_lower, 'list') !== false || strpos($query_lower, 'every') !== false || strpos($query_lower, 'complete') !== false);
        
        if (!$wants_all) {
            usort($top_records, function($a, $b) use ($scored_ids) {
                if ($scored_ids[$a->id] >= 0.99 && $scored_ids[$b->id] < 0.99) return -1;
                if ($scored_ids[$b->id] >= 0.99 && $scored_ids[$a->id] < 0.99) return 1;

                if ($a->source_type === 'wc_product' && $b->source_type === 'wc_product') {
                    if ($a->sales != $b->sales) {
                        return $b->sales - $a->sales; 
                    }
                }
                return $scored_ids[$b->id] <=> $scored_ids[$a->id]; 
            });
            $top_records = array_slice($top_records, 0, 8); 
        } else {
            usort($top_records, function($a, $b) use ($scored_ids) {
                return $scored_ids[$b->id] <=> $scored_ids[$a->id];
            });
        }

        $matched_data = "### RETRIEVED KNOWLEDGE ###\n";
        
        // FIX: Removed hardcoded Urdu and added dynamic language instruction
        if (!$wants_all && count($top_records) > 0) {
            $matched_data .= "\n[CRITICAL SYSTEM NOTE: You are currently receiving ONLY the Top Best-Selling products or Exact keyword matches. Speak professionally and smoothly. You MUST introduce them naturally. AT THE VERY END of your message, politely ask the user if they would like to see the full list or more details. IMPORTANT: You MUST ask this question in the EXACT SAME LANGUAGE the user is currently speaking (English or Roman Urdu).]\n\n";
        } elseif ($wants_all) {
            $matched_data .= "\n[CRITICAL SYSTEM NOTE: You are now seeing the complete list of products/data. Present them professionally and smoothly to the user. Do not ask if they want to see more products.]\n\n";
        }

        foreach ($top_records as $res) {
            $confidence = round($scored_ids[$res->id] * 100, 1);
            if ($confidence >= 99) $confidence = 100; 
            
            if ($res->source_type === 'wc_product') {
                $matched_data .= "--- Match Confidence: {$confidence}% | LIVE Total Sales: {$res->sales} ---\n";
            } else {
                $matched_data .= "--- Match Confidence: {$confidence}% ---\n";
            }
            
            $matched_data .= "Title: " . $res->title . "\n";
            $matched_data .= "Details: " . $res->content . "\n";

            $clean_id = intval(preg_replace('/[^0-9]/', '', $res->source_id));
            $url = "";
            $image_url = "";

            if ( $clean_id > 0 ) {
                $fetched_url = get_permalink( $clean_id );
                if ( $fetched_url ) {
                    $url = $fetched_url;
                }
                
                $img = get_the_post_thumbnail_url( $clean_id, 'medium' );
                if ( $img ) {
                    $image_url = $img;
                }
            }

            if (!empty($url)) {
                $matched_data .= "Official URL: " . $url . "\n";
            }
            if (!empty($image_url)) {
                $matched_data .= "Image URL: " . $image_url . "\n";
            }
            
            $matched_data .= "\n";
        }
        return $matched_data;
    }

    private function get_query_embedding($text, $options) {
        $api_settings = get_option('wp_ai_chatbot_settings', array());
        $provider = isset($options['embedding_provider']) ? $options['embedding_provider'] : 'gemini';

        if ($provider === 'openai') {
            $model = !empty($options['openai_embedding_model']) ? $options['openai_embedding_model'] : 'text-embedding-3-small';
            return $this->get_openai_query($text, $model, $api_settings);
        } else {
            $model = !empty($options['gemini_embedding_model']) ? $options['gemini_embedding_model'] : 'text-embedding-004';
            return $this->get_gemini_query($text, $model, $api_settings);
        }
    }

    private function get_openai_query($text, $model, $api_settings) {
        $api_key = isset($api_settings['openai_api_key']) ? $api_settings['openai_api_key'] : '';
        if (empty($api_key)) return false;

        $response = wp_remote_post('https://api.openai.com/v1/embeddings', array(
            'timeout' => 15, 'sslverify' => false,
            'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key),
            'body' => wp_json_encode(array('model' => $model, 'input' => mb_substr($text, 0, 1000)))
        ));

        if (is_wp_error($response)) return false;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['data'][0]['embedding']) ? $body['data'][0]['embedding'] : false;
    }

    private function get_gemini_query($text, $model, $api_settings) {
        $api_key = isset($api_settings['gemini_api_key']) ? $api_settings['gemini_api_key'] : '';
        if (empty($api_key)) return false;

        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':embedContent?key=' . $api_key;
        $response = wp_remote_post($endpoint, array(
            'timeout' => 15, 'sslverify' => false,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array('model' => 'models/' . $model, 'content' => array('parts' => array(array('text' => mb_substr($text, 0, 1000))))))
        ));

        if (is_wp_error($response)) return false;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['embedding']['values']) ? $body['embedding']['values'] : false;
    }

    private function cosine_similarity($vecA, $vecB) {
        $dotProduct = 0; $magnitudeA = 0; $magnitudeB = 0;
        $count = min(count($vecA), count($vecB));
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $magnitudeA += $vecA[$i] * $vecA[$i];
            $magnitudeB += $vecB[$i] * $vecB[$i];
        }
        $magnitudeA = sqrt($magnitudeA); $magnitudeB = sqrt($magnitudeB);
        return ($magnitudeA == 0 || $magnitudeB == 0) ? 0 : ($dotProduct / ($magnitudeA * $magnitudeB));
    }
}