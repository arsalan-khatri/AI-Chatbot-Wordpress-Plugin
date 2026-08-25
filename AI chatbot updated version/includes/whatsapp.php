<?php
/**
 * WhatsApp Integrator - Frontend Output (Shortcodes & WooCommerce)
 */

if (!defined('ABSPATH')) exit;

// ------------------ 1. WOOCOMMERCE AUTO BUTTON ------------------
add_action('woocommerce_single_product_summary', function () {
    if (!class_exists('WooCommerce')) return;

    // 🚀 GLOBAL KILL SWITCH: Check if WhatsApp is enabled in settings
    if (get_option('wp_ai_wa_enable', '1') !== '1') return;

    global $product;
    if (!$product) return;
    
    $number = get_option('wp_ai_wa_number');
    if (!$number) return;

    // Check Admin Preferences
    $inc_title = get_option('wp_ai_wa_inc_title', '1');
    $inc_price = get_option('wp_ai_wa_inc_price', '1');
    $inc_link  = get_option('wp_ai_wa_inc_link', '1');

    $message = "Hi! I want to buy this product:\r\n\r\n";
    
    if ($inc_title === '1') {
        $title = sanitize_text_field($product->get_name());
        $message .= "*" . $title . "*\r\n";
    }
    
    if ($inc_price === '1') {
        $price = $product->get_price();
        $currency = html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8');
        $message .= "Price: " . $currency . " " . number_format((float)$price, 0) . "\r\n";
    }
    
    if ($inc_link === '1') {
        $link = get_permalink($product->get_id());
        $message .= "Link: " . $link . "\r\n\r\n";
    }

    $message .= "Please tell me more about it.";

    $encoded = rawurlencode($message);
    $wa_url = "https://wa.me/" . esc_attr($number) . "?text=" . $encoded;
    
    $btn_text = get_option('wp_ai_wa_auto_btn_text', 'Chat on WhatsApp');

    echo '<a href="' . esc_url($wa_url) . '" target="_blank" rel="noopener noreferrer" class="ak_deep_knowledge-whatsapp-btn" style="display:inline-block; margin-top:10px;">';
    echo esc_html($btn_text);
    echo '</a>';
}, 35);


// ------------------ 2. SHORTCODE BUTTON ------------------
add_shortcode('ak_whatsapp_button', function ($atts) {
    // 🚀 GLOBAL KILL SWITCH: Check if WhatsApp is enabled in settings
    if (get_option('wp_ai_wa_enable', '1') !== '1') return '';

    global $product;

    $number = get_option('wp_ai_wa_number');
    if (!$number) return '';

    $atts = shortcode_atts(array(
        'text'  => '',
        'bg'    => '',
        'color' => '',
        'hover' => '',
        'icon'  => ''
    ), $atts, 'ak_deep_knowledge_whatsapp_button');

    $text       = $atts['text']  ? sanitize_text_field($atts['text'])  : sanitize_text_field(get_option('wp_ai_wa_shortcode_text', 'Message on WhatsApp'));
    $bg         = $atts['bg']    ? esc_attr($atts['bg'])               : esc_attr(get_option('wp_ai_wa_shortcode_bg_color', '#25D366'));
    $hover_bg   = $atts['hover'] ? esc_attr($atts['hover'])            : esc_attr(get_option('wp_ai_wa_shortcode_hover_color', '#128C7E'));
    $color      = $atts['color'] ? esc_attr($atts['color'])            : esc_attr(get_option('wp_ai_wa_shortcode_text_color', '#ffffff'));
    $icon       = $atts['icon']  ? esc_url($atts['icon'])              : esc_url(get_option('wp_ai_wa_shortcode_icon', ''));

    if ($product && method_exists($product, 'get_name')) {
        // Check Admin Preferences for Shortcode too
        $inc_title = get_option('wp_ai_wa_inc_title', '1');
        $inc_price = get_option('wp_ai_wa_inc_price', '1');
        $inc_link  = get_option('wp_ai_wa_inc_link', '1');

        $message = "Hi! I want to buy this product:\r\n\r\n";
        
        if ($inc_title === '1') {
            $title = sanitize_text_field($product->get_name());
            $message .= "*" . $title . "*\r\n";
        }
        
        if ($inc_price === '1') {
            $price = $product->get_price();
            $currency = html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8');
            $message .= "Price: " . $currency . " " . number_format((float)$price, 0) . "\r\n";
        }
        
        if ($inc_link === '1') {
            $link = get_permalink($product->get_id());
            $message .= "Link: " . $link . "\r\n\r\n";
        }
        
        $message .= "Please tell me more about it.";
    } else {
        $message = get_option('wp_ai_wa_message', 'Hello, I need some help!');
    }

    $encoded_message = rawurlencode($message);
    $wa_url = "https://wa.me/" . esc_attr($number) . "?text=" . $encoded_message;

    $icon_size = intval(get_option('wp_ai_wa_shortcode_icon_size', 24));
    $icon_html = '';
    if ($icon) {
        $icon_html = '<img src="' . esc_url($icon) . '" alt="" style="width:' . esc_attr($icon_size) . 'px;height:auto;margin-right:8px;display:inline-block;vertical-align:middle;">';
    }

    $btn_id = 'ak_deep_knowledge-btn-' . uniqid();
    $custom_style = '<style>
        #' . $btn_id . ':hover {
            background:' . $hover_bg . ' !important;
            color:' . $color . ' !important;
        }
    </style>';

    $style = 'background:' . $bg . ';color:' . $color . ';padding:10px 18px;border-radius:6px;display:inline-flex;align-items:center;text-decoration:none;transition:0.3s;';
    $button = '<a id="' . $btn_id . '" href="' . esc_url($wa_url) . '" target="_blank" rel="noopener noreferrer" class="ak_deep_knowledge-whatsapp-btn" style="' . esc_attr($style) . '">' . $icon_html . esc_html($text) . '</a>';

    return $custom_style . $button;
});

// ------------------ 3. WOOCOMMERCE SHORTCODE FIX ------------------
add_filter( 'woocommerce_short_description', 'do_shortcode' );
add_filter( 'the_excerpt', 'do_shortcode' );