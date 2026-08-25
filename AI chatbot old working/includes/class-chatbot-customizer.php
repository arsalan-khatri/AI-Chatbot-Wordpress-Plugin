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
    }

    public function register_settings() {
        register_setting( 'wp_ai_customizer_group', $this->option_name );
    }

public function display_page() {
        $opts = get_option( $this->option_name, array() );
        
        // Default Settings Array
        $def = array(
            'bot_name' => 'AI Assistant', 'position' => 'right', 'margin_side' => '30', 'margin_bottom' => '30',
            'header_bg' => '#0071a1', 'header_text' => '#ffffff', 'chat_bg' => '#f8f9fa',
            'bot_bubble_bg' => '#ffffff', 'bot_bubble_text' => '#333333',
            'user_bubble_bg' => '#0071a1', 'user_bubble_text' => '#ffffff',
            'send_btn_bg' => '#0071a1', 'send_btn_icon' => '', 
            'del_btn_bg' => '#d63638', 'toggle_icon' => ''
        );
        
        foreach($def as $k => $v) { if(!isset($opts[$k])) $opts[$k] = $v; }
        ?>
        <div class="wrap">
            <h1>Chatbot Appearance Customizer</h1>
            <?php settings_errors(); ?>
            <p>Customize every single visual element of your chatbot to match your theme.</p>
            <form method="post" action="options.php" id="wp-ai-customizer-form" style="background:#fff; padding:20px; border:1px solid #ccc; border-radius:8px; max-width:800px;">
                <?php settings_fields( 'wp_ai_customizer_group' ); ?>
                <table class="form-table">
                    <tr><th>Chatbot Title</th><td><input type="text" name="<?php echo $this->option_name; ?>[bot_name]" value="<?php echo esc_attr($opts['bot_name']); ?>" class="regular-text" /></td></tr>
                    <tr><th>Screen Position</th><td>
                        <select name="<?php echo $this->option_name; ?>[position]">
                            <option value="right" <?php selected($opts['position'], 'right'); ?>>Bottom Right</option>
                            <option value="left" <?php selected($opts['position'], 'left'); ?>>Bottom Left</option>
                        </select>
                    </td></tr>
                    <tr><th>Margin Side (px)</th><td><input type="number" name="<?php echo $this->option_name; ?>[margin_side]" value="<?php echo esc_attr($opts['margin_side']); ?>" style="width:100px;" /></td></tr>
                    <tr><th>Margin Bottom (px)</th><td><input type="number" name="<?php echo $this->option_name; ?>[margin_bottom]" value="<?php echo esc_attr($opts['margin_bottom']); ?>" style="width:100px;" /></td></tr>
                    
                    <tr><td colspan="2"><hr><h3>Colors & Backgrounds</h3></td></tr>
                    <tr><th>Header Background</th><td><input type="text" name="<?php echo $this->option_name; ?>[header_bg]" value="<?php echo esc_attr($opts['header_bg']); ?>" class="color-picker" /></td></tr>
                    <tr><th>Header Text Color</th><td><input type="text" name="<?php echo $this->option_name; ?>[header_text]" value="<?php echo esc_attr($opts['header_text']); ?>" class="color-picker" /></td></tr>
                    <tr><th>Main Chat Background</th><td><input type="text" name="<?php echo $this->option_name; ?>[chat_bg]" value="<?php echo esc_attr($opts['chat_bg']); ?>" class="color-picker" /></td></tr>
                    
                    <tr><td colspan="2"><hr><h3>Message Bubbles</h3></td></tr>
                    <tr><th>Bot Bubble Background</th><td><input type="text" name="<?php echo $this->option_name; ?>[bot_bubble_bg]" value="<?php echo esc_attr($opts['bot_bubble_bg']); ?>" class="color-picker" /></td></tr>
                    <tr><th>Bot Bubble Text</th><td><input type="text" name="<?php echo $this->option_name; ?>[bot_bubble_text]" value="<?php echo esc_attr($opts['bot_bubble_text']); ?>" class="color-picker" /></td></tr>
                    <tr><th>User Bubble Background</th><td><input type="text" name="<?php echo $this->option_name; ?>[user_bubble_bg]" value="<?php echo esc_attr($opts['user_bubble_bg']); ?>" class="color-picker" /></td></tr>
                    <tr><th>User Bubble Text</th><td><input type="text" name="<?php echo $this->option_name; ?>[user_bubble_text]" value="<?php echo esc_attr($opts['user_bubble_text']); ?>" class="color-picker" /></td></tr>
                    
                    <tr><td colspan="2"><hr><h3>Buttons & Icons</h3></td></tr>
                    <tr><th>Send Button Color</th><td><input type="text" name="<?php echo $this->option_name; ?>[send_btn_bg]" value="<?php echo esc_attr($opts['send_btn_bg']); ?>" class="color-picker" /></td></tr>
                    <tr><th>Delete History Button</th><td><input type="text" name="<?php echo $this->option_name; ?>[del_btn_bg]" value="<?php echo esc_attr($opts['del_btn_bg']); ?>" class="color-picker" /></td></tr>
                    <tr><th>Custom Chatbot Icon URL<br><small>(Leave empty for default SVG)</small></th><td><input type="text" name="<?php echo $this->option_name; ?>[toggle_icon]" value="<?php echo esc_attr($opts['toggle_icon']); ?>" class="large-text" placeholder="https://yourwebsite.com/icon.png" /></td></tr>
                    <tr><th>Custom Send Icon URL<br><small>(Leave empty for default SVG)</small></th><td><input type="text" name="<?php echo $this->option_name; ?>[send_btn_icon]" value="<?php echo esc_attr($opts['send_btn_icon']); ?>" class="large-text" placeholder="https://yourwebsite.com/send.png" /></td></tr>
                </table>
                
                <div style="display: flex; gap: 15px; align-items: center; margin-top: 25px; border-top: 1px solid #eee; padding-top: 15px;">
                    <?php submit_button('Save Customizations', 'primary', 'submit', false); ?>
                    <button type="button" id="wp-ai-reset-btn" class="button button-secondary" style="border-color: #d63638; color: #d63638;">Set to Default</button>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('#wp-ai-reset-btn').on('click', function(e){
                e.preventDefault();
                if(confirm('Are you sure you want to reset all appearance settings to default?')) {
                    // PHP theke default array JS e ana hocche
                    var defaults = <?php echo json_encode($def); ?>;
                    var optName = "<?php echo $this->option_name; ?>";
                    
                    // Normal Text/Number inputs reset
                    $('input[name="' + optName + '[bot_name]"]').val(defaults.bot_name);
                    $('select[name="' + optName + '[position]"]').val(defaults.position);
                    $('input[name="' + optName + '[margin_side]"]').val(defaults.margin_side);
                    $('input[name="' + optName + '[margin_bottom]"]').val(defaults.margin_bottom);
                    $('input[name="' + optName + '[toggle_icon]"]').val(defaults.toggle_icon);
                    $('input[name="' + optName + '[send_btn_icon]"]').val(defaults.send_btn_icon);
                    
                    // WP Color Pickers reset (Important step!)
                    $('input[name="' + optName + '[header_bg]"]').wpColorPicker('color', defaults.header_bg);
                    $('input[name="' + optName + '[header_text]"]').wpColorPicker('color', defaults.header_text);
                    $('input[name="' + optName + '[chat_bg]"]').wpColorPicker('color', defaults.chat_bg);
                    $('input[name="' + optName + '[bot_bubble_bg]"]').wpColorPicker('color', defaults.bot_bubble_bg);
                    $('input[name="' + optName + '[bot_bubble_text]"]').wpColorPicker('color', defaults.bot_bubble_text);
                    $('input[name="' + optName + '[user_bubble_bg]"]').wpColorPicker('color', defaults.user_bubble_bg);
                    $('input[name="' + optName + '[user_bubble_text]"]').wpColorPicker('color', defaults.user_bubble_text);
                    $('input[name="' + optName + '[send_btn_bg]"]').wpColorPicker('color', defaults.send_btn_bg);
                    $('input[name="' + optName + '[del_btn_bg]"]').wpColorPicker('color', defaults.del_btn_bg);
                    
                    // Automatic form submit korano hocche jate data save hoye jay
                    $('#wp-ai-customizer-form').submit();
                }
            });
        });
        </script>
        <?php
    }
}