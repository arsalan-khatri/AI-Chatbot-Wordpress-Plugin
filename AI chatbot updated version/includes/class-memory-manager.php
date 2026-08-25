<?php
/**
 * Context Window & Memory Manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Memory_Manager {
    private $limit;

    public function __construct() {
        $settings = get_option( 'wp_ai_chatbot_settings', array() );
        // Limit uthayein admin se, agar na ho toh default 6 rakhein
        $this->limit = isset( $settings['history_limit'] ) ? intval( $settings['history_limit'] ) : 6;
    }

    public function process_history( $raw_history ) {
        if ( ! is_array( $raw_history ) ) {
            return array();
        }

        // 1. Data Sanitization
        $clean_history = array();
        foreach ( $raw_history as $msg ) {
            if ( isset( $msg['text'] ) && isset( $msg['sender'] ) ) {
                $clean_history[] = array(
                    'text'   => sanitize_text_field( $msg['text'] ),
                    'sender' => sanitize_text_field( $msg['sender'] )
                );
            }
        }

        // 2. Sliding Window (Context Limit)
        if ( $this->limit > 0 && count( $clean_history ) > $this->limit ) {
            $clean_history = array_slice( $clean_history, -($this->limit) );
        } elseif ( $this->limit === 0 ) {
            return array(); // Agar admin 0 set kare, toh memory disable ho jayegi
        }

        return $clean_history;
    }
}