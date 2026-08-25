<?php
/**
 * Vector RAG Search Engine (The Semantic Brain)
 * FULLY TOKEN-OPTIMIZED & BUG-FREE ENTERPRISE VERSION
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_RAG_Engine {

    public function search_relevant_knowledge($query) {
        if (empty(trim($query))) return ""; 

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_vector_embeddings';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) return "";

        $options = get_option('wp_ai_rag_settings', array());
        
        // 🚀 TOKEN SAVER 1: Embedding API sirf shuru ke 1000 characters check karega
        $query_vector = $this->get_query_embedding($query, $options);
        if (!$query_vector) return ""; 

        // OPTIMIZATION STEP 1: Sirf ID aur Vector uthao taake RAM crash na ho
        $records = $wpdb->get_results("SELECT id, vector_data FROM $table_name");
        if (empty($records)) return "";

        $scored_ids = [];
        
        // 🚀 DYNAMIC THRESHOLD (Contact details miss na hone ki guarantee)
        $word_count = str_word_count($query);
        $dynamic_threshold = ($word_count <= 3) ? 0.35 : 0.45;
        
        // OPTIMIZATION STEP 3: "Sare" vs "Best Selling" Smart Routing
        $query_lower = strtolower($query);
        
        // 🚀 NAYA IZAFA: User ki "Yes", "Han", "Sari list" wali commands add kar di hain
        $wants_all = (strpos($query_lower, 'all') !== false || strpos($query_lower, 'sare') !== false || strpos($query_lower, 'tamam') !== false || strpos($query_lower, 'list') !== false || strpos($query_lower, 'every') !== false || strpos($query_lower, 'complete') !== false || strpos($query_lower, 'yes') !== false || strpos($query_lower, 'han') !== false || strpos($query_lower, 'haan') !== false || strpos($query_lower, 'sari') !== false);
        
        $products = [];
        $pages_and_kbs = [];
        
        foreach ($top_records as $rec) {
            if ($rec->source_type === 'wc_product') {
                $products[] = $rec;
            } else {
                $pages_and_kbs[] = $rec; 
            }
        }
        
        if (!$wants_all) {
            // 🚀 NAYA IZAFA: Sirf Top 5 Best-Selling Products dikhayega
            usort($products, function($a, $b) {
                return $b->sales - $a->sales; 
            });
            $products = array_slice($products, 0, 5); 
            
            usort($pages_and_kbs, function($a, $b) use ($scored_ids) {
                return $scored_ids[$b->id] <=> $scored_ids[$a->id];
            });
            $pages_and_kbs = array_slice($pages_and_kbs, 0, 2);
            
        } else {
            // 🚀 NAYA IZAFA: "Sari list" bolne par poori 30 products ki list de dega
            usort($products, function($a, $b) use ($scored_ids) {
                return $scored_ids[$b->id] <=> $scored_ids[$a->id];
            });
            $products = array_slice($products, 0, 30);
            
            usort($pages_and_kbs, function($a, $b) use ($scored_ids) {
                return $scored_ids[$b->id] <=> $scored_ids[$a->id];
            });
            $pages_and_kbs = array_slice($pages_and_kbs, 0, 3);
        }

        $final_records = array_merge($pages_and_kbs, $products);

        // 🚀 Output Generation
        $matched_data = "### SEMANTIC RETRIEVED KNOWLEDGE ###\n";
        
        // 🚀 NAYA IZAFA: AI ke liye Exact aapki requirement wala System Note
        if (!$wants_all && count($products) > 0) {
            $matched_data .= "\n[CRITICAL SYSTEM NOTE: You are currently receiving ONLY the Top 5 Best-Selling products for this category. DO NOT act like a robot. You MUST introduce them naturally by saying EXACTLY this: 'Ye hamari is category ki best selling products hain:'. Then list the products properly. AT THE VERY END of your message, you MUST politely ask exactly this: 'Hmare pass or be products hain, kya aap is category ki sari products dekhna chate hain?']\n\n";
        } elseif ($wants_all) {
            $matched_data .= "\n[CRITICAL SYSTEM NOTE: You are now seeing the complete list of products for this category. Present ALL of them professionally and smoothly to the user. DO NOT ask if they want to see more products, because you are already showing the full list.]\n\n";
        }

        foreach ($final_records as $res) {
            $confidence = round($scored_ids[$res->id] * 100, 1);
            
            if ($res->source_type === 'wc_product') {
                $matched_data .= "--- Match Confidence: {$confidence}% | LIVE Total Sales: {$res->sales} ---\n";
            } else {
                $matched_data .= "--- Match Confidence: {$confidence}% ---\n";
            }
            
            $matched_data .= "Title: " . $res->title . "\n";
            
            $short_desc = mb_strimwidth($res->content, 0, 400, "...");
            $matched_data .= "Details: " . $short_desc . "\n";

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