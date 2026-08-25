<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Customizer {

    private $option_name = 'wp_ai_chatbot_custom_style';

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_submenu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function add_submenu() {
        add_submenu_page( 'wp-ai-chatbot', 'Chatbot Customizer', 'Appearance', 'manage_options', 'wp-ai-chatbot-customizer', array( $this, 'display_page' ) );
    }

    public function enqueue_assets($hook) {
        if ( $hook !== 'ai-chatbot_page_wp-ai-chatbot-customizer' ) return;
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_add_inline_script( 'wp-color-picker', 'jQuery(document).ready(function($){ $(".color-picker").wpColorPicker(); });' );
        
        wp_enqueue_script( 'wp-ai-chatbot-templates', WP_AI_CHATBOT_URL . 'admin/js/chatbot-templates.js', array('jquery', 'wp-color-picker'), WP_AI_CHATBOT_VERSION, true );
    }

    public function register_settings() {
        register_setting( 'wp_ai_customizer_group', $this->option_name );
    }

    public function display_page() {
        $opts = get_option( $this->option_name, array() );
        
        $def = array(
            'bot_name' => 'AI Assistant', 
            'position' => 'right', 
            'margin_side' => '30', 
            'margin_bottom' => '30',
            'chat_width' => '380',
            'chat_height' => '600',
            'chat_radius' => '16',
            'icon_size' => '60',
            // Colors
            'general_border_color' => '#e5e7eb', // NAYA IZAFA (Fixes Dark Mode Lines)
            'header_bg' => '#0071a1', 
            'header_text' => '#ffffff', 
            'chat_bg' => '#f8f9fa',
            'bot_bubble_bg' => '#ffffff', 
            'bot_bubble_text' => '#333333',
            'user_bubble_bg' => '#0071a1', 
            'user_bubble_text' => '#ffffff',
            'send_btn_bg' => '#0071a1', 
            'send_btn_hover_bg' => '#005a87',
            'send_btn_text' => '#ffffff',
            'send_btn_icon_hover' => '#ffffff', // NAYA IZAFA
            'del_btn_bg' => '#d63638', 
            'del_btn_hover_bg' => '#b32d2e',
            'close_btn_color' => '#ffffff', 
            'close_btn_hover_bg' => 'rgba(255,255,255,0.15)',
            'close_btn_size' => '32', // NAYA IZAFA
            'close_btn_padding' => '0', // NAYA IZAFA
            'close_btn_margin' => '0', // NAYA IZAFA
            'input_bg_color' => '#ffffff', 
            'input_border_sides' => 'all',    
            'input_border_width' => '1',      
            'input_border_color' => '#e5e7eb',
            'input_border_focus_color' => '#0071a1',
            'toggle_icon_transparent' => '0', 
            'chat_bg_transparent' => '0',     
            'send_btn_icon' => '', 
            'toggle_icon' => ''
        );
        
        foreach($def as $k => $v) { if(!isset($opts[$k])) $opts[$k] = $v; }
        ?>
        <div class="wrap">
            <h1>Chatbot Appearance Customizer</h1>
            <?php settings_errors(); ?>
            <p>A to Z Customization for your AI Chatbot. Make it look exactly like your favorite apps!</p>

            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-bottom:20px; max-width:900px; max-height:400px; overflow-y:auto;">
                <h3 style="margin-top:0;">💎 70 Premium Social & Modern Templates</h3>
                <p style="color:#666; font-size:13px; margin-bottom:15px;">Click a template to instantly apply a full UI overhaul.</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px;">
                    <!-- Original 20 -->
                    <button type="button" class="button apply-template-btn" data-template="chatgpt_light">ChatGPT Light</button>
                    <button type="button" class="button apply-template-btn" data-template="chatgpt_dark" style="background:#212121; color:#fff; border:none;">ChatGPT Dark</button>
                    <button type="button" class="button apply-template-btn" data-template="claude_light" style="background:#f3f0e8; color:#d97757; border:1px solid #d97757;">Claude UI</button>
                    <button type="button" class="button apply-template-btn" data-template="whatsapp" style="background:#25D366; color:#fff; border:none;">WhatsApp</button>
                    <button type="button" class="button apply-template-btn" data-template="messenger" style="background:#0084ff; color:#fff; border:none;">Messenger</button>
                    <button type="button" class="button apply-template-btn" data-template="instagram" style="background:linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); color:#fff; border:none;">Instagram</button>
                    <button type="button" class="button apply-template-btn" data-template="twitter" style="background:#1DA1F2; color:#fff; border:none;">Twitter</button>
                    <button type="button" class="button apply-template-btn" data-template="telegram" style="background:#0088cc; color:#fff; border:none;">Telegram</button>
                    <button type="button" class="button apply-template-btn" data-template="discord" style="background:#5865F2; color:#fff; border:none;">Discord</button>
                    <button type="button" class="button apply-template-btn" data-template="discord_dark" style="background:#36393f; color:#fff; border:none;">Discord Dark</button>
                    <button type="button" class="button apply-template-btn" data-template="linkedin" style="background:#0077b5; color:#fff; border:none;">LinkedIn</button>
                    <button type="button" class="button apply-template-btn" data-template="slack" style="background:#4A154B; color:#fff; border:none;">Slack</button>
                    <button type="button" class="button apply-template-btn" data-template="imessage" style="background:#34C759; color:#fff; border:none;">iMessage</button>
                    <button type="button" class="button apply-template-btn" data-template="android_sms" style="background:#1a73e8; color:#fff; border:none;">Android SMS</button>
                    <button type="button" class="button apply-template-btn" data-template="teams" style="background:#6264A7; color:#fff; border:none;">MS Teams</button>
                    <button type="button" class="button apply-template-btn" data-template="material_ui" style="border:2px solid #4285F4; color:#4285F4;">Material UI</button>
                    <button type="button" class="button apply-template-btn" data-template="fluent_design" style="background:#f3f2f1; color:#000;">Fluent Design</button>
                    <button type="button" class="button apply-template-btn" data-template="cyberpunk" style="background:#fcee0a; color:#00f0ff; border:2px solid #00f0ff;">Cyberpunk</button>
                    <button type="button" class="button apply-template-btn" data-template="monochrome" style="background:#000; color:#fff; border:none;">Monochrome</button>
                    <button type="button" class="button apply-template-btn" data-template="glassmorphism" style="background:linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0)); border:1px solid #ccc; backdrop-filter:blur(10px);">Glassmorphism</button>
                    
                    <!-- NEW 50 -->
                    <button type="button" class="button apply-template-btn" data-template="spotify" style="background:#1DB954; color:#fff; border:none;">Spotify</button>
                    <button type="button" class="button apply-template-btn" data-template="netflix" style="background:#E50914; color:#fff; border:none;">Netflix</button>
                    <button type="button" class="button apply-template-btn" data-template="twitch" style="background:#9146FF; color:#fff; border:none;">Twitch</button>
                    <button type="button" class="button apply-template-btn" data-template="github" style="background:#24292e; color:#fff; border:none;">GitHub</button>
                    <button type="button" class="button apply-template-btn" data-template="github_dark" style="background:#0d1117; color:#c9d1d9; border:1px solid #30363d;">GitHub Dark</button>
                    <button type="button" class="button apply-template-btn" data-template="notion" style="border:1px solid #37352f;">Notion</button>
                    <button type="button" class="button apply-template-btn" data-template="pinterest" style="background:#e60023; color:#fff; border:none;">Pinterest</button>
                    <button type="button" class="button apply-template-btn" data-template="snapchat" style="background:#FFFC00; color:#000; border:none;">Snapchat</button>
                    <button type="button" class="button apply-template-btn" data-template="reddit" style="background:#FF4500; color:#fff; border:none;">Reddit</button>
                    <button type="button" class="button apply-template-btn" data-template="tiktok" style="background:#010101; color:#fff; border:2px solid #25F4EE;">TikTok</button>
                    <button type="button" class="button apply-template-btn" data-template="dracula" style="background:#282a36; color:#ff79c6; border:none;">Dracula</button>
                    <button type="button" class="button apply-template-btn" data-template="monokai" style="background:#272822; color:#a6e22e; border:none;">Monokai</button>
                    <button type="button" class="button apply-template-btn" data-template="nord" style="background:#2e3440; color:#88c0d0; border:none;">Nord</button>
                    <button type="button" class="button apply-template-btn" data-template="synthwave" style="background:#262335; color:#ff7edb; border:none;">Synthwave</button>
                    <button type="button" class="button apply-template-btn" data-template="solarized_dark" style="background:#002b36; color:#2aa198; border:none;">Solarized Dk</button>
                    <button type="button" class="button apply-template-btn" data-template="solarized_light" style="background:#fdf6e3; color:#268bd2; border:none;">Solarized Lt</button>
                    <button type="button" class="button apply-template-btn" data-template="windows_11" style="background:#005fb8; color:#fff; border:none;">Windows 11</button>
                    <button type="button" class="button apply-template-btn" data-template="macos" style="background:#e8e8e8; color:#000; border:1px solid #ccc;">macOS</button>
                    <button type="button" class="button apply-template-btn" data-template="neon_glow" style="background:#000; color:#39ff14; border:1px solid #39ff14;">Neon Glow</button>
                    <button type="button" class="button apply-template-btn" data-template="pastel_dream" style="background:#ffb6c1; color:#fff; border:none;">Pastel Dream</button>
                    <button type="button" class="button apply-template-btn" data-template="ocean_breeze" style="background:#006994; color:#fff; border:none;">Ocean Breeze</button>
                    <button type="button" class="button apply-template-btn" data-template="forest_green" style="background:#2d5a27; color:#fff; border:none;">Forest Green</button>
                    <button type="button" class="button apply-template-btn" data-template="sunset_orange" style="background:#e65100; color:#fff; border:none;">Sunset Orange</button>
                    <button type="button" class="button apply-template-btn" data-template="luxury_gold" style="background:#1a1a1a; color:#d4af37; border:none;">Luxury Gold</button>
                    <button type="button" class="button apply-template-btn" data-template="matte_black" style="background:#1e1e1e; color:#fff; border:none;">Matte Black</button>
                    <button type="button" class="button apply-template-btn" data-template="hacker" style="background:#000; color:#0f0; border:1px solid #0f0;">Hacker</button>
                    <button type="button" class="button apply-template-btn" data-template="deep_space" style="background:#0b0c10; color:#58a6ff; border:none;">Deep Space</button>
                    <button type="button" class="button apply-template-btn" data-template="coral" style="background:#ff7f50; color:#fff; border:none;">Coral</button>
                    <button type="button" class="button apply-template-btn" data-template="mocha" style="background:#4e342e; color:#fff; border:none;">Mocha</button>
                    <button type="button" class="button apply-template-btn" data-template="mint" style="background:#00bfa5; color:#fff; border:none;">Mint</button>
                    <button type="button" class="button apply-template-btn" data-template="lavender" style="background:#7e57c2; color:#fff; border:none;">Lavender</button>
                    <button type="button" class="button apply-template-btn" data-template="peach" style="background:#ffab91; color:#fff; border:none;">Peach</button>
                    <button type="button" class="button apply-template-btn" data-template="teal" style="background:#00695c; color:#fff; border:none;">Teal</button>
                    <button type="button" class="button apply-template-btn" data-template="slate" style="background:#455a64; color:#fff; border:none;">Slate</button>
                    <button type="button" class="button apply-template-btn" data-template="crimson" style="background:#b71c1c; color:#fff; border:none;">Crimson</button>
                    <button type="button" class="button apply-template-btn" data-template="azure" style="background:#0277bd; color:#fff; border:none;">Azure</button>
                    <button type="button" class="button apply-template-btn" data-template="indigo" style="background:#283593; color:#fff; border:none;">Indigo</button>
                    <button type="button" class="button apply-template-btn" data-template="amber" style="background:#ff8f00; color:#fff; border:none;">Amber</button>
                    <button type="button" class="button apply-template-btn" data-template="emerald" style="background:#2e7d32; color:#fff; border:none;">Emerald</button>
                    <button type="button" class="button apply-template-btn" data-template="fuchsia" style="background:#c2185b; color:#fff; border:none;">Fuchsia</button>
                    <button type="button" class="button apply-template-btn" data-template="rose" style="background:#e91e63; color:#fff; border:none;">Rose</button>
                    <button type="button" class="button apply-template-btn" data-template="violet" style="background:#673ab7; color:#fff; border:none;">Violet</button>
                    <button type="button" class="button apply-template-btn" data-template="cyan" style="background:#0097a7; color:#fff; border:none;">Cyan</button>
                    <button type="button" class="button apply-template-btn" data-template="lime" style="background:#afb42b; color:#fff; border:none;">Lime</button>
                    <button type="button" class="button apply-template-btn" data-template="emerald_dark" style="background:#1b5e20; color:#fff; border:none;">Emerald Dark</button>
                    <button type="button" class="button apply-template-btn" data-template="midnight" style="background:#1a237e; color:#fff; border:none;">Midnight</button>
                    <button type="button" class="button apply-template-btn" data-template="valentine" style="background:#e91e63; color:#fff; border:none;">Valentine</button>
                    <button type="button" class="button apply-template-btn" data-template="halloween" style="background:#e65100; color:#000; border:none;">Halloween</button>
                    <button type="button" class="button apply-template-btn" data-template="royal_purple" style="background:#4a148c; color:#fff; border:none;">Royal Purple</button>
                    <button type="button" class="button apply-template-btn" data-template="golden_hour" style="background:#ffb300; color:#000; border:none;">Golden Hour</button>
                    <button type="button" class="button apply-template-btn" data-template="frost_glass" style="background:rgba(255,255,255,0.2); color:#333; border:1px solid #ccc; backdrop-filter:blur(5px);">Frost Glass</button>
                </div>
            </div>

            <form method="post" action="options.php" id="wp-ai-customizer-form" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; max-width:900px;">
                <?php settings_fields( 'wp_ai_customizer_group' ); ?>
                <table class="form-table">
                    <tr><th>Chatbot Title</th><td><input type="text" name="<?php echo $this->option_name; ?>[bot_name]" value="<?php echo esc_attr($opts['bot_name']); ?>" class="regular-text" /></td></tr>
                    
                    <tr><td colspan="2"><hr><h3>1. True Transparency Options</h3></td></tr>
                    <tr>
                        <th>Icon Background</th>
                        <td>
                            <label>
                                <input type="hidden" name="<?php echo $this->option_name; ?>[toggle_icon_transparent]" value="0" />
                                <input type="checkbox" name="<?php echo $this->option_name; ?>[toggle_icon_transparent]" value="1" <?php checked('1', $opts['toggle_icon_transparent']); ?> />
                                Make Chat Icon Background Transparent
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Chat Window Glass Effect</th>
                        <td>
                            <label>
                                <input type="hidden" name="<?php echo $this->option_name; ?>[chat_bg_transparent]" value="0" />
                                <input type="checkbox" name="<?php echo $this->option_name; ?>[chat_bg_transparent]" value="1" <?php checked('1', $opts['chat_bg_transparent']); ?> />
                                Enable Glassmorphism
                            </label>
                        </td>
                    </tr>

                    <tr><td colspan="2"><hr><h3>2. Layout & Sizing (px)</h3></td></tr>
                    <tr><th>Screen Position</th><td>
                        <select name="<?php echo $this->option_name; ?>[position]">
                            <option value="right" <?php selected($opts['position'], 'right'); ?>>Bottom Right</option>
                            <option value="left" <?php selected($opts['position'], 'left'); ?>>Bottom Left</option>
                        </select>
                    </td></tr>
                    <tr><th>Margin Side & Bottom</th>
                        <td>
                            <input type="number" name="<?php echo $this->option_name; ?>[margin_side]" value="<?php echo esc_attr($opts['margin_side']); ?>" style="width:70px;" /> px (Side)
                            &nbsp;&nbsp;&nbsp;
                            <input type="number" name="<?php echo $this->option_name; ?>[margin_bottom]" value="<?php echo esc_attr($opts['margin_bottom']); ?>" style="width:70px;" /> px (Bottom)
                        </td>
                    </tr>
                    <tr><th>Chat Window Size</th>
                        <td>
                            <input type="number" name="<?php echo $this->option_name; ?>[chat_width]" value="<?php echo esc_attr($opts['chat_width']); ?>" style="width:70px;" /> px (Width)
                            &nbsp;&nbsp;&nbsp;
                            <input type="number" name="<?php echo $this->option_name; ?>[chat_height]" value="<?php echo esc_attr($opts['chat_height']); ?>" style="width:70px;" /> px (Height)
                        </td>
                    </tr>
                    <tr><th>Border Radius & Icon</th>
                        <td>
                            <input type="number" name="<?php echo $this->option_name; ?>[chat_radius]" value="<?php echo esc_attr($opts['chat_radius']); ?>" style="width:70px;" /> px (Corners)
                            &nbsp;&nbsp;&nbsp;
                            <input type="number" name="<?php echo $this->option_name; ?>[icon_size]" value="<?php echo esc_attr($opts['icon_size']); ?>" style="width:70px;" /> px (Icon Size)
                        </td>
                    </tr>
                    
                    <tr><td colspan="2"><hr><h3>3. Core Colors & Backgrounds</h3></td></tr>
                    <tr><th>Global Border Color</th><td><input type="text" name="<?php echo $this->option_name; ?>[general_border_color]" value="<?php echo esc_attr($opts['general_border_color']); ?>" class="color-picker" data-alpha-enabled="true" /> <small>(Fixes unwanted lines in dark mode)</small></td></tr>
                    <tr><th>Header Color</th>
                        <td>
                            Bg: <input type="text" name="<?php echo $this->option_name; ?>[header_bg]" value="<?php echo esc_attr($opts['header_bg']); ?>" class="color-picker" data-alpha-enabled="true" />
                            &nbsp;&nbsp;
                            Text: <input type="text" name="<?php echo $this->option_name; ?>[header_text]" value="<?php echo esc_attr($opts['header_text']); ?>" class="color-picker" />
                        </td>
                    </tr>
                    <tr><th>Main Chat Background</th><td><input type="text" name="<?php echo $this->option_name; ?>[chat_bg]" value="<?php echo esc_attr($opts['chat_bg']); ?>" class="color-picker" data-alpha-enabled="true" /></td></tr>
                    
                    <tr><td colspan="2"><hr><h3>4. Message Bubbles</h3></td></tr>
                    <tr><th>Bot Bubble</th>
                        <td>
                            Bg: <input type="text" name="<?php echo $this->option_name; ?>[bot_bubble_bg]" value="<?php echo esc_attr($opts['bot_bubble_bg']); ?>" class="color-picker" data-alpha-enabled="true" />
                            &nbsp;&nbsp;
                            Text: <input type="text" name="<?php echo $this->option_name; ?>[bot_bubble_text]" value="<?php echo esc_attr($opts['bot_bubble_text']); ?>" class="color-picker" />
                        </td>
                    </tr>
                    <tr><th>User Bubble</th>
                        <td>
                            Bg: <input type="text" name="<?php echo $this->option_name; ?>[user_bubble_bg]" value="<?php echo esc_attr($opts['user_bubble_bg']); ?>" class="color-picker" data-alpha-enabled="true" />
                            &nbsp;&nbsp;
                            Text: <input type="text" name="<?php echo $this->option_name; ?>[user_bubble_text]" value="<?php echo esc_attr($opts['user_bubble_text']); ?>" class="color-picker" />
                        </td>
                    </tr>

                    <tr><td colspan="2"><hr><h3>5. Input Box Border Settings</h3></td></tr>
                    <tr><th>Input Box Background</th><td><input type="text" name="<?php echo $this->option_name; ?>[input_bg_color]" value="<?php echo esc_attr($opts['input_bg_color']); ?>" class="color-picker" data-alpha-enabled="true" /></td></tr>
                    <tr><th>Border Side</th><td>
                        <select name="<?php echo $this->option_name; ?>[input_border_sides]">
                            <option value="all" <?php selected($opts['input_border_sides'], 'all'); ?>>All Sides (Full Box)</option>
                            <option value="top" <?php selected($opts['input_border_sides'], 'top'); ?>>Top Only (Line above input)</option>
                            <option value="bottom" <?php selected($opts['input_border_sides'], 'bottom'); ?>>Bottom Only (Underline)</option>
                            <option value="none" <?php selected($opts['input_border_sides'], 'none'); ?>>No Border</option>
                        </select>
                    </td></tr>
                    <tr><th>Border Width (px)</th><td><input type="number" name="<?php echo $this->option_name; ?>[input_border_width]" value="<?php echo esc_attr($opts['input_border_width']); ?>" style="width:100px;" /></td></tr>
                    <tr><th>Border Colors</th>
                        <td>
                            Default: <input type="text" name="<?php echo $this->option_name; ?>[input_border_color]" value="<?php echo esc_attr($opts['input_border_color']); ?>" class="color-picker" data-alpha-enabled="true" />
                            &nbsp;&nbsp;
                            On Typing: <input type="text" name="<?php echo $this->option_name; ?>[input_border_focus_color]" value="<?php echo esc_attr($opts['input_border_focus_color']); ?>" class="color-picker" data-alpha-enabled="true" />
                        </td>
                    </tr>
                    
                    <tr><td colspan="2"><hr><h3>6. Buttons, Hover Colors & Spacing</h3></td></tr>
                    <tr><th>Close (X) Button Spacing (px)</th>
                        <td>
                            Size: <input type="number" name="<?php echo $this->option_name; ?>[close_btn_size]" value="<?php echo esc_attr($opts['close_btn_size']); ?>" style="width:70px;" />
                            &nbsp;&nbsp;
                            Padding: <input type="number" name="<?php echo $this->option_name; ?>[close_btn_padding]" value="<?php echo esc_attr($opts['close_btn_padding']); ?>" style="width:70px;" />
                            &nbsp;&nbsp;
                            Margin: <input type="number" name="<?php echo $this->option_name; ?>[close_btn_margin]" value="<?php echo esc_attr($opts['close_btn_margin']); ?>" style="width:70px;" />
                        </td>
                    </tr>
                    <tr><th>Close (X) Button Colors</th>
                        <td>
                            Icon Color: <input type="text" name="<?php echo $this->option_name; ?>[close_btn_color]" value="<?php echo esc_attr($opts['close_btn_color']); ?>" class="color-picker" data-alpha-enabled="true" />
                            &nbsp;&nbsp;
                            Hover Bg: <input type="text" name="<?php echo $this->option_name; ?>[close_btn_hover_bg]" value="<?php echo esc_attr($opts['close_btn_hover_bg']); ?>" class="color-picker" data-alpha-enabled="true" />
                        </td>
                    </tr>
                    <tr><th>Send Button</th>
                        <td>
                            Bg: <input type="text" name="<?php echo $this->option_name; ?>[send_btn_bg]" value="<?php echo esc_attr($opts['send_btn_bg']); ?>" class="color-picker" data-alpha-enabled="true" />
                            &nbsp;&nbsp;
                            Hover Bg: <input type="text" name="<?php echo $this->option_name; ?>[send_btn_hover_bg]" value="<?php echo esc_attr($opts['send_btn_hover_bg']); ?>" class="color-picker" data-alpha-enabled="true" />
                            <br><br>
                            Icon Color: <input type="text" name="<?php echo $this->option_name; ?>[send_btn_text]" value="<?php echo esc_attr($opts['send_btn_text']); ?>" class="color-picker" />
                            &nbsp;&nbsp;
                            Hover Icon Color: <input type="text" name="<?php echo $this->option_name; ?>[send_btn_icon_hover]" value="<?php echo esc_attr($opts['send_btn_icon_hover']); ?>" class="color-picker" />
                        </td>
                    </tr>
                    <tr><th>Delete History Button</th>
                        <td>
                            Bg: <input type="text" name="<?php echo $this->option_name; ?>[del_btn_bg]" value="<?php echo esc_attr($opts['del_btn_bg']); ?>" class="color-picker" data-alpha-enabled="true" />
                            &nbsp;&nbsp;
                            Hover: <input type="text" name="<?php echo $this->option_name; ?>[del_btn_hover_bg]" value="<?php echo esc_attr($opts['del_btn_hover_bg']); ?>" class="color-picker" data-alpha-enabled="true" />
                        </td>
                    </tr>

                    <tr><th>Custom Icon URLs (Optional)</th>
                        <td>
                            Toggle Icon: <input type="text" name="<?php echo $this->option_name; ?>[toggle_icon]" value="<?php echo esc_attr($opts['toggle_icon']); ?>" class="regular-text" placeholder="https://..." /><br><br>
                            Send Icon: <input type="text" name="<?php echo $this->option_name; ?>[send_btn_icon]" value="<?php echo esc_attr($opts['send_btn_icon']); ?>" class="regular-text" placeholder="https://..." />
                        </td>
                    </tr>
                </table>
                
                <div style="display: flex; gap: 15px; align-items: center; margin-top: 25px; border-top: 1px solid #eee; padding-top: 15px;">
                    <?php submit_button('Save Customizations', 'primary', 'submit', false); ?>
                    <button type="button" id="wp-ai-reset-btn" class="button button-secondary" style="border-color: #d63638; color: #d63638;">Set to Default</button>
                </div>
            </form>
            
            <script>var defaultSettings = <?php echo json_encode($def); ?>;</script>
        </div>
        <?php
    }
}