<?php
/**
 * Vector RAG Search Engine (The Semantic Brain)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_RAG_Engine {

    // public function search_relevant_knowledge($query) {
    //     if (empty(trim($query))) return ""; 

    //     global $wpdb;
    //     $table_name = $wpdb->prefix . 'ai_vector_embeddings';

    //     if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) return "";

    //     $options = get_option('wp_ai_rag_settings', array());
        
    //     $query_vector = $this->get_query_embedding($query, $options);
    //     if (!$query_vector) return ""; 

    //     // Data uthate waqt source_type aur source_id laazmi chahiye
    //     $records = $wpdb->get_results("SELECT source_type, source_id, title, content, vector_data FROM $table_name");
    //     if (empty($records)) return "";

    //     $scored_results = [];
    //     foreach ($records as $row) {
    //         $db_vector = json_decode($row->vector_data, true);
    //         if (!is_array($db_vector)) continue;

    //         $similarity = $this->cosine_similarity($query_vector, $db_vector);
    //         if ($similarity > 0.45) {
    //             $scored_results[] = [
    //                 'score'       => $similarity, 
    //                 'title'       => $row->title, 
    //                 'content'     => $row->content,
    //                 'source_type' => $row->source_type,
    //                 'source_id'   => $row->source_id
    //             ];
    //         }
    //     }

    //     usort($scored_results, function($a, $b) { return $b['score'] <=> $a['score']; });
        
    //     // SMART LIMIT LOGIC: Agar user tamam products mangay toh limit barha dein
    //     $query_lower = strtolower($query);
    //     $wants_all = (strpos($query_lower, 'all') !== false || strpos($query_lower, 'sare') !== false || strpos($query_lower, 'tamam') !== false || strpos($query_lower, 'list') !== false || strpos($query_lower, 'every') !== false || strpos($query_lower, 'complete') !== false);
        
    //     // $limit = $wants_all ? 15 : 5; // Normal sawal par 5, "all products" par 15 items layega
    //     $limit = 100;
    //     $top_results = array_slice($scored_results, 0, $limit);
    //     if (empty($top_results)) return "";

    //     $matched_data = "### SEMANTIC RETRIEVED KNOWLEDGE ###\n";
    //     foreach ($top_results as $res) {
    //         $confidence = round($res['score'] * 100, 1);
    //         $matched_data .= "--- Match Confidence: {$confidence}% ---\n";
    //         $matched_data .= "Title: " . $res['title'] . "\n";
    //         $matched_data .= "Details: " . $res['content'] . "\n";

    //         $clean_id = intval(preg_replace('/[^0-9]/', '', $res['source_id']));
    //         $url = "";
    //         $image_url = "";

    //         if ( $clean_id > 0 ) {
    //             $fetched_url = get_permalink( $clean_id );
    //             if ( $fetched_url ) {
    //                 $url = $fetched_url;
    //             }
                
    //             $img = get_the_post_thumbnail_url( $clean_id, 'medium' );
    //             if ( $img ) {
    //                 $image_url = $img;
    //             }
    //         }

    //         if (!empty($url)) {
    //             $matched_data .= "Official URL: " . $url . "\n";
    //         }
    //         if (!empty($image_url)) {
    //             $matched_data .= "Image URL: " . $image_url . "\n";
    //         }
            
    //         $matched_data .= "\n";
    //     }
    //     return $matched_data;
    // }

    public function search_relevant_knowledge($query) {
        if (empty(trim($query))) return ""; 

        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_vector_embeddings';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) return "";

        $options = get_option('wp_ai_rag_settings', array());
        
        $query_vector = $this->get_query_embedding($query, $options);
        if (!$query_vector) return ""; 

        // OPTIMIZATION STEP 1: Sirf ID aur Vector uthao taake RAM crash na ho
        $records = $wpdb->get_results("SELECT id, vector_data FROM $table_name");
        if (empty($records)) return "";

        $scored_ids = [];
        
        // NAYA IZAFA: Dynamic Cosine Similarity Threshold
        // Agar query mein sirf 1-3 words hain, toh threshold 0.35 kar do
        $word_count = str_word_count($query);
        $dynamic_threshold = ($word_count <= 3) ? 0.35 : 0.45;
        
        // AI Business Logic: Contact, Phone, ya Email mangne par strictness aur kam kar do
        $query_lower = strtolower($query);
        if (strpos($query_lower, 'contact') !== false || strpos($query_lower, 'phone') !== false || strpos($query_lower, 'email') !== false || strpos($query_lower, 'rabta') !== false) {
            $dynamic_threshold = 0.25; 
        }

        foreach ($records as $row) {
            $db_vector = json_decode($row->vector_data, true);
            if (!is_array($db_vector)) continue;

            $similarity = $this->cosine_similarity($query_vector, $db_vector);
            
            // Ab static 0.45 ke bajaye dynamic threshold use hoga
            if ($similarity > $dynamic_threshold) {
                $scored_ids[$row->id] = $similarity;
            }
        }

        if (empty($scored_ids)) return "";

        // Highest similarity first
        arsort($scored_ids);
        
        // Base Limit 100 rakhi hai taake inme se hum best selling dhoond sakein
        $top_ids = array_slice(array_keys($scored_ids), 0, 100);
        if (empty($top_ids)) return "";

        // Ab sirf in top 100 IDs ka actual text database se mangwayen
        $ids_string = implode(',', array_map('intval', $top_ids));
        $top_records = $wpdb->get_results("SELECT id, source_type, source_id, title, content FROM $table_name WHERE id IN ($ids_string)");

        // OPTIMIZATION STEP 2: WooCommerce LIVE Sales Data Fetch Karna
        foreach ($top_records as &$res) {
            $res->sales = 0;
            if ($res->source_type === 'wc_product') {
                $clean_id = intval(preg_replace('/[^0-9]/', '', $res->source_id));
                // Live WooCommerce sales data utha rahe hain Vector DB ke bajaye
                $res->sales = (int) get_post_meta($clean_id, 'total_sales', true);
            }
        }

        // OPTIMIZATION STEP 3: "Sare" vs "Best Selling" Filter Logic
        $query_lower = strtolower($query);
        $wants_all = (strpos($query_lower, 'all') !== false || strpos($query_lower, 'sare') !== false || strpos($query_lower, 'tamam') !== false || strpos($query_lower, 'list') !== false || strpos($query_lower, 'every') !== false || strpos($query_lower, 'complete') !== false);
        
        if (!$wants_all) {
            // Agar "all" nahi bola, toh Best Selling (Sales) ke hisaab se sort karo
            usort($top_records, function($a, $b) use ($scored_ids) {
                if ($a->source_type === 'wc_product' && $b->source_type === 'wc_product') {
                    if ($a->sales != $b->sales) {
                        return $b->sales - $a->sales; // High sales first
                    }
                }
                return $scored_ids[$b->id] <=> $scored_ids[$a->id]; // Fallback
            });
            // Sirf Top 8 Best Selling products aagay bhejo jaisa aapne pucha
            $top_records = array_slice($top_records, 0, 8); 
        } else {
            // Agar "all" bola hai toh Similar records poore 100 de do
            usort($top_records, function($a, $b) use ($scored_ids) {
                return $scored_ids[$b->id] <=> $scored_ids[$a->id];
            });
        }

    //     Output Generation
    //     $matched_data = "### SEMANTIC RETRIEVED KNOWLEDGE ###\n";
    //     foreach ($top_records as $res) {
    //         $confidence = round($scored_ids[$res->id] * 100, 1);
            
    //         // AI ko hidden instruction di ke yeh live sales data hai
    //         if ($res->source_type === 'wc_product') {
    //             $matched_data .= "--- Match Confidence: {$confidence}% | LIVE Total Sales: {$res->sales} ---\n";
    //         } else {
    //             $matched_data .= "--- Match Confidence: {$confidence}% ---\n";
    //         }
            
    //         $matched_data .= "Title: " . $res->title . "\n";
    //         $matched_data .= "Details: " . $res->content . "\n";

    //         $clean_id = intval(preg_replace('/[^0-9]/', '', $res->source_id));
    //         $url = "";
    //         $image_url = "";

    //         if ( $clean_id > 0 ) {
    //             $fetched_url = get_permalink( $clean_id );
    //             if ( $fetched_url ) {
    //                 $url = $fetched_url;
    //             }
                
    //             $img = get_the_post_thumbnail_url( $clean_id, 'medium' );
    //             if ( $img ) {
    //                 $image_url = $img;
    //             }
    //         }

    //         if (!empty($url)) {
    //             $matched_data .= "Official URL: " . $url . "\n";
    //         }
    //         if (!empty($image_url)) {
    //             $matched_data .= "Image URL: " . $image_url . "\n";
    //         }
            
    //         $matched_data .= "\n";
    //     }
    //     return $matched_data;
    // }

        // Output Generation
    //     $matched_data = "### SEMANTIC RETRIEVED KNOWLEDGE ###\n";
        
    //     // NAYA IZAFA: AI ke liye Hidden System Note (Context Awareness)
    //     if (!$wants_all && count($top_records) > 0) {
    //         $matched_data .= "\n[SYSTEM NOTE FOR AI: You are currently receiving only the Top best-selling products from this category due to system limits. DO NOT tell the user this is the full list. Instead, show these products and explicitly ask: \"Would you like to see all other products in this category?\"]\n\n";
    //     } elseif ($wants_all) {
    //         $matched_data .= "\n[SYSTEM NOTE FOR AI: You are now seeing the complete list of products for this category. Present them nicely to the user.]\n\n";
    //     }

    //     foreach ($top_records as $res) {
    //         $confidence = round($scored_ids[$res->id] * 100, 1);
            
    //         // AI ko hidden instruction di ke yeh live sales data hai
    //         if ($res->source_type === 'wc_product') {
    //             $matched_data .= "--- Match Confidence: {$confidence}% | LIVE Total Sales: {$res->sales} ---\n";
    //         } else {
    //             $matched_data .= "--- Match Confidence: {$confidence}% ---\n";
    //         }
            
    //         $matched_data .= "Title: " . $res->title . "\n";
    //         $matched_data .= "Details: " . $res->content . "\n";

    //         $clean_id = intval(preg_replace('/[^0-9]/', '', $res->source_id));
    //         $url = "";
    //         $image_url = "";

    //         if ( $clean_id > 0 ) {
    //             $fetched_url = get_permalink( $clean_id );
    //             if ( $fetched_url ) {
    //                 $url = $fetched_url;
    //             }
                
    //             $img = get_the_post_thumbnail_url( $clean_id, 'medium' );
    //             if ( $img ) {
    //                 $image_url = $img;
    //             }
    //         }

    //         if (!empty($url)) {
    //             $matched_data .= "Official URL: " . $url . "\n";
    //         }
    //         if (!empty($image_url)) {
    //             $matched_data .= "Image URL: " . $image_url . "\n";
    //         }
            
    //         $matched_data .= "\n";
    //     }
    //     return $matched_data;
    // }

        // Output Generation
        $matched_data = "### SEMANTIC RETRIEVED KNOWLEDGE ###\n";
        
        // NAYA IZAFA: AI ke liye SMART, SMOOTH & PROFESSIONAL System Note
        $query_lower = strtolower($query);
        $wants_all = (strpos($query_lower, 'all') !== false || strpos($query_lower, 'sare') !== false || strpos($query_lower, 'tamam') !== false || strpos($query_lower, 'list') !== false || strpos($query_lower, 'every') !== false || strpos($query_lower, 'complete') !== false);
        
        if (!$wants_all && count($top_records) > 0) {
            $matched_data .= "\n[CRITICAL SYSTEM NOTE: You are currently receiving ONLY the Top Best-Selling products. DO NOT act like a robot. Speak professionally and smoothly. You MUST introduce them naturally by saying 'Ye hamari is category ki best-selling products hain:'. Then list them properly. AT THE VERY END of your message, you MUST politely ask exactly this: 'Kya main aapko in products ki saari list bhej doon?']\n\n";
        } elseif ($wants_all) {
            $matched_data .= "\n[CRITICAL SYSTEM NOTE: You are now seeing the complete list of products. Present them professionally and smoothly to the user. Do not ask if they want to see more products.]\n\n";
        }

        foreach ($top_records as $res) {
            $confidence = round($scored_ids[$res->id] * 100, 1);
            
            // AI ko hidden instruction di ke yeh live sales data hai
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