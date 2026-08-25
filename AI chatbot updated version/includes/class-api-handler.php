<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_API_Handler {

    private $settings;
    private $multi_forms;
    private $provider;

    public function __construct() {
        $this->settings    = get_option( 'wp_ai_chatbot_settings', array() );
        $this->multi_forms = get_option( 'wp_ai_chatbot_multi_forms', array() ); 
        $this->provider    = isset( $this->settings['api_provider'] ) ? $this->settings['api_provider'] : 'gemini';
    }

    public function process_chat_request( $user_message, $history = array() ) {
        
        // 1. SMART ROUTER (Saves API Limit on Greetings)
        $clean_msg = strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $user_message)));
        $generic_greetings = array('hi', 'hello', 'hey', 'salam', 'assalam o alaikum', 'assalamualaikum', 'ok', 'thanks', 'thank you', 'yes', 'no', 'acha', 'theek');
        
        $relevant_data = "";

        if ( str_word_count($clean_msg) <= 3 && in_array($clean_msg, $generic_greetings) ) {
            // RAG Engine bypassed for generic greetings. API Token Saved!
        } else {
            // 2. CONTEXT-AWARE SEARCH (Chatbot ki Memory)
            $search_query = $user_message;
            
            if ( !empty($history) && is_array($history) && str_word_count($user_message) <= 6 ) {
                $last_user_msg = '';
                $rev_history = array_reverse($history);
                foreach($rev_history as $msg) {
                    if ($msg['sender'] === 'user') {
                        $last_user_msg = $msg['text'];
                        break;
                    }
                }
                
                if (!empty($last_user_msg)) {
                    $search_query = $last_user_msg . " - " . $user_message;
                }
            }

            // RAG Engine call karein
            $engine = new WP_AI_Chatbot_RAG_Engine(); 
            $relevant_data = $engine->search_relevant_knowledge($search_query);
        }
        
        $admin_prompt = isset( $this->settings['system_prompt'] ) ? $this->settings['system_prompt'] : "You are a helpful assistant.";
        $system_prompt = $admin_prompt;

        // 3. AI INSTRUCTIONS (Text Flow + Rich Cards)
        if (!empty($relevant_data)) {
            $system_prompt .= "\n\n### RELEVANT DATABASE RECORDS ###\n" . $relevant_data . "\n\n### END OF RECORDS ###\n\n";
            $system_prompt .= "INSTRUCTIONS FOR ANSWERING:\n";
            $system_prompt .= "1. Use ONLY the above records to answer the user's query accurately in a natural, conversational tone.\n";
            $system_prompt .= "2. Do not invent any details, prices, or info that is not in the records.\n";
            $system_prompt .= "3. RICH LINK PREVIEW (CARDS): Whenever you recommend a product, course, page, article, or blog post, you MUST display it as a beautiful visual card using Markdown.\n";
            $system_prompt .= "4. If an 'Image URL' and 'Official URL' are provided, format it EXACTLY like this below your text explanation to make BOTH the image and the text clickable:\n\n";
            $system_prompt .= "[![Item Name](Image URL)](Official URL)\n";
            $system_prompt .= "**[Click here to view: Item Name](Official URL)**\n\n";
            $system_prompt .= "5. If NO 'Image URL' is provided but an 'Official URL' or a Link inside 'Details' is available, just provide the clickable link normally in your text.\n";
            
            // 🚀 UNIVERSAL TECHNICAL RULE (Safe for all websites)
            $system_prompt .= "6. STRICT LIST FORMATTING: Whenever you mention multiple items or products, you MUST NEVER write them in a single line or paragraph separated by commas. You MUST ALWAYS display them vertically using strict HTML list tags (<ul> and <li>). Example:\n<ul>\n<li>Item 1</li>\n<li>Item 2</li>\n</ul>\n";
        }


        // if ( ! empty( $this->multi_forms ) && is_array( $this->multi_forms ) ) {
        //     $system_prompt .= "\n\n### CRITICAL FORM RULES (ASK BEFORE SENDING) ###\n";
        //     $system_prompt .= "Never send a form directly to the user. Follow this strict 3-step rule:\n";
        //     $system_prompt .= "STEP 1: If the user asks for something that matches a form intent (e.g., 'i want contact details' or 'sales team'), just send a VERY SHORT message asking for permission (e.g., 'Can I send you our contact form?'). Do NOT send the form shortcode yet.\n";
        //     $system_prompt .= "STEP 2: Only when the user says 'yes', 'ok', or explicitly agrees, then output ONLY the exact form shortcode and nothing else.\n";
        //     $system_prompt .= "STEP 3 (ANTI-SPAM): If the conversation history shows that you ALREADY provided the form shortcode, DO NOT ask again and DO NOT send the shortcode again. Politely say: 'I have already provided the form above. Please fill it out.'\n\n";
            
        //     foreach ( $this->multi_forms as $form ) {
        //         $system_prompt .= "- For Intent: \"" . $form['intent'] . "\" => Ask first. If they agree, output exactly: [SHOW_FORM_" . $form['id'] . "]\n";
        //     }
        // }

        // if ( ! empty( $this->multi_forms ) && is_array( $this->multi_forms ) ) {
        //     $system_prompt .= "\n\n### CRITICAL FORM RULES ###\n";
        //     $system_prompt .= "Follow these smart rules for sending forms:\n";
        //     $system_prompt .= "RULE 1 (EXPLICIT REQUEST): If the user EXPLICITLY asks for a form (e.g., 'give me contact form', 'contact form do mujhe', 'send form') OR if they agree to your offer (e.g., 'yes', 'ok'), IMMEDIATELY output ONLY the exact form shortcode. DO NOT ask for permission.\n";
        //     $system_prompt .= "RULE 2 (IMPLIED REQUEST): If the user only hints at needing help (e.g., 'I want to talk to sales', 'how to contact'), politely ask: 'May I send you our contact form?'. Do NOT send the shortcode yet.\n";
        //     $system_prompt .= "RULE 3 (ANTI-SPAM): If the conversation history shows that you ALREADY provided the form shortcode, DO NOT send the shortcode again. Politely say: 'I have already provided the form above. Please fill it out.'\n\n";
            
        //     foreach ( $this->multi_forms as $form ) {
        //         $system_prompt .= "- For Intent: \"" . $form['intent'] . "\" => Form Shortcode: [SHOW_FORM_" . $form['id'] . "]\n";
        //     }
        // }


        // Detect already sent forms to prevent spam
        $sent_forms = array();
        if ( !empty( $history ) && is_array( $history ) ) {
            foreach ( $history as $msg ) {
                if ( $msg['sender'] !== 'user' && preg_match('/\[SHOW_FORM_([a-zA-Z0-9_]+)\]/', $msg['text'], $matches) ) {
                    $sent_forms[] = $matches[1];
                }
            }
        }

        if ( ! empty( $this->multi_forms ) && is_array( $this->multi_forms ) ) {
            $system_prompt .= "\n\n### CRITICAL FORM RULES ###\n";
            $system_prompt .= "Follow these smart rules for sending forms:\n";
            $system_prompt .= "RULE 1: If the user explicitly asks for a form or says 'yes/ok' to your offer, output ONLY the exact form shortcode.\n";
            $system_prompt .= "RULE 2: If they just hint at it, politely ask for permission first.\n";
            
            // $system_prompt .= "\nAVAILABLE FORMS:\n";
            // foreach ( $this->multi_forms as $form ) {
            //     if ( in_array( $form['id'], $sent_forms ) ) {
            //         // AGAR FORM PEHLE JA CHUKA HAI TOH SHORTCODE CHUPA DO
            //         $system_prompt .= "- Intent: \"" . $form['intent'] . "\" => STATUS: ALREADY SENT. DO NOT send this form again. If user says 'ok', reply: 'I have already provided the form above. Please fill it out.'\n";
            //     } else {
            //         // Normal behavior
            //         $system_prompt .= "- Intent: \"" . $form['intent'] . "\" => Form Shortcode: [SHOW_FORM_" . $form['id'] . "]\n";
            //     }
            // }

            // $system_prompt .= "\nAVAILABLE FORMS:\n";
            // foreach ( $this->multi_forms as $form ) {
            //     if ( in_array( $form['id'], $sent_forms ) ) {
            //         // AGAR FORM PEHLE JA CHUKA HAI TOH STRICT RULE LAGA DO
            //         $system_prompt .= "- Intent: \"" . $form['intent'] . "\" => STATUS: ALREADY SENT. If the user just says generic words like 'ok' or 'yes', say 'I have already provided the form above.' BUT IF the user EXPLICITLY asks you to resend it (e.g., 'send again', 'dobara do', 'resend form'), ONLY THEN output: [SHOW_FORM_" . $form['id'] . "]\n";
            //     } else {
            //         // Pehli dafa ke liye normal behavior
            //         $system_prompt .= "- Intent: \"" . $form['intent'] . "\" => Form Shortcode: [SHOW_FORM_" . $form['id'] . "]\n";
            //     }
            // }

            $system_prompt .= "\nAVAILABLE FORMS:\n";
            foreach ( $this->multi_forms as $form ) {
                if ( in_array( $form['id'], $sent_forms ) ) {
                    // 🚀 AGAR FORM PEHLE JA CHUKA HAI TOH NAGGING KHATAM KAR DO
                    $system_prompt .= "- Intent: \"" . $form['intent'] . "\" => STATUS: ALREADY SENT. CRITICAL RULE: If the user just says 'ok', 'yes', or 'thanks', DO NOT mention the form at all. Just ignore the form and move on to the next step naturally. ONLY if the user explicitly asks for the form AGAIN (e.g., 'send form again', 'form do'), you must ask: 'I have already provided the form above. Would you like me to send it again?'. ONLY if they say yes to this specific question, output: [SHOW_FORM_" . $form['id'] . "]\n";
                } else {
                    // Pehli dafa ke liye normal behavior
                    $system_prompt .= "- Intent: \"" . $form['intent'] . "\" => Form Shortcode: [SHOW_FORM_" . $form['id'] . "]\n";
                }
            }
        }

        // 5. CALL AI PROVIDER
        if ( $this->provider === 'openai' ) {
            return $this->call_openai( $user_message, $system_prompt, $history );
        } else {
            return $this->call_gemini( $user_message, $system_prompt, $history );
        }
    }

    private function call_openai( $message, $system_prompt, $history ) {
        $api_key = isset( $this->settings['openai_api_key'] ) ? $this->settings['openai_api_key'] : '';
        if ( empty( $api_key ) ) return 'Error: OpenAI API key is missing.';
        $model = isset( $this->settings['openai_model'] ) ? $this->settings['openai_model'] : 'gpt-4o-mini';

        $messages_array = array(
            array( 'role' => 'system', 'content' => $system_prompt )
        );

        if ( !empty( $history ) && is_array( $history ) ) {
            foreach ( $history as $msg ) {
                $text = sanitize_text_field( $msg['text'] );
                
                // AI ko yaad dilana ke form pehle ja chuka hai
                // if ( strpos( $msg['text'], '[SHOW_FORM' ) !== false ) {
                //     $text = "[SYSTEM NOTE: You have already provided the form shortcode to the user here. Do not send it again.]";
                // }
                
                // AI ko yaad dilana ke form pehle ja chuka hai
                if ( strpos( $msg['text'], '[SHOW_FORM' ) !== false && $msg['sender'] !== 'user' ) {
                    $text = $msg['text'] . "\n[SYSTEM NOTE: Form already sent. Do NOT mention this form again if the user just says 'ok' or 'thanks'.]";
                }
                
                $role = ( $msg['sender'] === 'user' ) ? 'user' : 'assistant';
                $messages_array[] = array( 'role' => $role, 'content' => $text );
            }
        }

        $messages_array[] = array( 'role' => 'user', 'content' => $message );

        $body = array(
            'model' => $model,
            'messages' => $messages_array,
            'temperature' => 0.7,
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30, 'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key ),
            'body' => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) return 'Error: ' . $response->get_error_message();
        $body_decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body_decoded['choices'][0]['message']['content'] ) ) return $body_decoded['choices'][0]['message']['content'];
        return 'Error: Unexpected response from OpenAI.';
    }

    private function call_gemini( $message, $system_prompt, $history ) {
        $api_key = isset( $this->settings['gemini_api_key'] ) ? $this->settings['gemini_api_key'] : '';
        if ( empty( $api_key ) ) return 'Error: Gemini API key is missing.';
        $model = isset( $this->settings['gemini_model'] ) ? $this->settings['gemini_model'] : 'gemini-1.5-flash';
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;

        $contents_array = array();

        if ( empty( $history ) || !is_array( $history ) ) {
            $contents_array[] = array( 'role' => 'user', 'parts' => array( array( 'text' => "System Instructions:\n" . $system_prompt . "\n\nUser: " . $message ) ) );
        } else {
            $first = true;
            foreach ( $history as $msg ) {
                $role = ( $msg['sender'] === 'user' ) ? 'user' : 'model';
                $text = sanitize_text_field( $msg['text'] );
                
                // AI ko yaad dilana ke form pehle ja chuka hai
                // if ( strpos( $msg['text'], '[SHOW_FORM' ) !== false ) {
                //     $text = "[SYSTEM NOTE: You have already provided the form shortcode to the user here. Do not send it again.]";
                // }

                // AI ko yaad dilana ke form pehle ja chuka hai
                if ( strpos( $msg['text'], '[SHOW_FORM' ) !== false && $msg['sender'] !== 'user' ) {
                    $text = $msg['text'] . "\n[SYSTEM NOTE: Form already sent. Do NOT mention this form again if the user just says 'ok' or 'thanks'.]";
                }
                
                if ( $first && $role === 'user' ) {
                    $text = "System Instructions:\n" . $system_prompt . "\n\nUser: " . $text;
                    $first = false;
                }
                $contents_array[] = array( 'role' => $role, 'parts' => array( array( 'text' => $text ) ) );
            }
            $contents_array[] = array( 'role' => 'user', 'parts' => array( array( 'text' => $message ) ) );
        }

        $body = array(
            'contents' => $contents_array,
            'generationConfig' => array( 'temperature' => 0.7 )
        );

        $response = wp_remote_post( $endpoint, array(
            'timeout' => 30, 'headers' => array( 'Content-Type' => 'application/json' ),
            'body' => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) return 'Error: ' . $response->get_error_message();
        $body_decoded = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body_decoded['candidates'][0]['content']['parts'][0]['text'] ) ) return $body_decoded['candidates'][0]['content']['parts'][0]['text'];
        return 'Error: Unexpected response from Gemini API.';
    }
}