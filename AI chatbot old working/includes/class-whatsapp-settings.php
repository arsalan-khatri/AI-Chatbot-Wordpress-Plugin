<?php
/**
 * WhatsApp Integrator Settings (Full-Width UI with Seamless Tabs & Animated Save Button)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_WhatsApp_Settings {

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_submenu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media' ) );
    }

    public function add_submenu() {
        add_submenu_page(
            'wp-ai-chatbot',
            'WhatsApp Settings',
            'WhatsApp Settings',
            'manage_options',
            'wp-ai-chatbot-whatsapp',
            array( $this, 'display_page' )
        );
    }

    public function enqueue_media($hook) {
        if ( isset($_GET['page']) && $_GET['page'] === 'wp-ai-chatbot-whatsapp' ) {
            wp_enqueue_media();
        }
    }

    public function register_settings() {
        $fields = [
            'wp_ai_wa_enable',
            'wp_ai_wa_number',
            'wp_ai_wa_hover_text',
            'wp_ai_wa_message',
            'wp_ai_wa_bg_color',
            'wp_ai_wa_text_color',
            'wp_ai_wa_size',
            'wp_ai_wa_icon',
            'wp_ai_wa_right',
            'wp_ai_wa_bottom',
            'wp_ai_wa_auto_btn_text',
            'wp_ai_wa_inc_title',
            'wp_ai_wa_inc_price',
            'wp_ai_wa_inc_link',
            'wp_ai_wa_shortcode_text',
            'wp_ai_wa_shortcode_bg_color',
            'wp_ai_wa_shortcode_hover_color',
            'wp_ai_wa_shortcode_text_color',
            'wp_ai_wa_shortcode_icon_size',
            'wp_ai_wa_shortcode_icon'
        ];
        foreach ($fields as $f) {
            register_setting('wp_ai_whatsapp_group', $f);
        }
    }

    public function display_page() {
        ?>
        <div class="wrap">
            <h1>WhatsApp Integrator Settings</h1>
            <?php 
                if ( isset($_GET['settings-updated']) ) {
                    echo '<div class="notice notice-success is-dismissible"><p>WhatsApp settings saved successfully!</p></div>';
                }
            ?>
            <p>Customize your floating action button (FAB), WooCommerce auto buttons, and Shortcodes from here.</p>

            <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="#general" class="nav-tab" id="tab-general">General Settings</a>
                <a href="#styling" class="nav-tab" id="tab-styling">Styling & Icons</a>
            </h2>

            <form method="post" action="options.php" id="wa-settings-form" style="background:#fff; padding:20px; border-radius:8px; border:1px solid #ddd; width:100%; box-sizing:border-box;">
                <?php settings_fields( 'wp_ai_whatsapp_group' ); ?>

                <!-- ==============================
                     TAB 1: GENERAL SETTINGS 
                     ============================== -->
                <div id="content-general" class="wa-tab-content" style="display:none;">
                    
                    <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px;">⚙️ Core Configurations</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 250px;">Enable Floating Button</th>
                            <td>
                                <input type="hidden" name="wp_ai_wa_enable" value="0" />
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                    <input type="checkbox" name="wp_ai_wa_enable" value="1" <?php checked('1', get_option('wp_ai_wa_enable', '1')); ?> style="margin:0;" />
                                    Show the WhatsApp option in the floating menu.
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">WhatsApp Number</th>
                            <td>
                                <input type="text" name="wp_ai_wa_number" class="regular-text" value="<?php echo esc_attr(get_option('wp_ai_wa_number')); ?>" placeholder="923456789012">
                                <p class="description">Include your country code <strong>without the '+' or '00' prefix</strong>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Default Pre-filled Message</th>
                            <td>
                                <textarea name="wp_ai_wa_message" rows="3" class="large-text" style="width: 100%; max-width: 500px;"><?php echo esc_textarea(get_option('wp_ai_wa_message', 'Hello, I need some help!')); ?></textarea>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top:30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">📝 Text & Labels</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 250px;">Hover Text (FAB Typing)</th>
                            <td><input type="text" name="wp_ai_wa_hover_text" class="regular-text" value="<?php echo esc_attr(get_option('wp_ai_wa_hover_text', 'Chat on WhatsApp')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">WooCommerce Auto Button Text</th>
                            <td><input type="text" name="wp_ai_wa_auto_btn_text" class="regular-text" value="<?php echo esc_attr(get_option('wp_ai_wa_auto_btn_text', 'Chat on WhatsApp')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Shortcode Button Text</th>
                            <td><input type="text" name="wp_ai_wa_shortcode_text" class="regular-text" value="<?php echo esc_attr(get_option('wp_ai_wa_shortcode_text', 'Message on WhatsApp')); ?>"></td>
                        </tr>
                    </table>

                    <h3 style="margin-top:30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">🧩 WooCommerce Dynamic Details</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 250px;">Include in Message</th>
                            <td>
                                <input type="hidden" name="wp_ai_wa_inc_title" value="0" />
                                <label style="display:block; margin-bottom:5px;">
                                    <input type="checkbox" name="wp_ai_wa_inc_title" value="1" <?php checked('1', get_option('wp_ai_wa_inc_title', '1')); ?> /> Product Title
                                </label>
                                
                                <input type="hidden" name="wp_ai_wa_inc_price" value="0" />
                                <label style="display:block; margin-bottom:5px;">
                                    <input type="checkbox" name="wp_ai_wa_inc_price" value="1" <?php checked('1', get_option('wp_ai_wa_inc_price', '1')); ?> /> Product Price
                                </label>

                                <input type="hidden" name="wp_ai_wa_inc_link" value="0" />
                                <label style="display:block; margin-bottom:5px;">
                                    <input type="checkbox" name="wp_ai_wa_inc_link" value="1" <?php checked('1', get_option('wp_ai_wa_inc_link', '1')); ?> /> Product Link
                                </label>
                                <p class="description">Select what details to include when a user clicks the WhatsApp button on a product page.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ==============================
                     TAB 2: STYLING & ICONS 
                     ============================== -->
                <div id="content-styling" class="wa-tab-content" style="display:none;">
                    
                    <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px;">🎨 Floating Action Button (FAB) Styling</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 250px;">Background Color</th>
                            <td><input type="color" name="wp_ai_wa_bg_color" value="<?php echo esc_attr(get_option('wp_ai_wa_bg_color', '#25D366')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Text Color</th>
                            <td><input type="color" name="wp_ai_wa_text_color" value="<?php echo esc_attr(get_option('wp_ai_wa_text_color', '#ffffff')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Button Size (px)</th>
                            <td><input type="number" name="wp_ai_wa_size" value="<?php echo esc_attr(get_option('wp_ai_wa_size', 60)); ?>" min="40" max="120"></td>
                        </tr>
                        <tr>
                            <th scope="row">Position (Right px)</th>
                            <td><input type="number" name="wp_ai_wa_right" value="<?php echo esc_attr(get_option('wp_ai_wa_right', 25)); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Position (Bottom px)</th>
                            <td><input type="number" name="wp_ai_wa_bottom" value="<?php echo esc_attr(get_option('wp_ai_wa_bottom', 25)); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Floating Icon URL (optional)</th>
                            <td>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="text" id="wp_ai_wa_icon" name="wp_ai_wa_icon" class="regular-text" value="<?php echo esc_attr(get_option('wp_ai_wa_icon', 'https://cdn-icons-png.flaticon.com/512/733/733585.png')); ?>" style="width: 100%; max-width: 400px;">
                                    <button type="button" class="button" id="upload_wa_icon_btn">Upload Icon</button>
                                </div>
                                <p class="description">Recommended: 40x40 PNG or SVG (transparent) icon.</p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top:30px; border-bottom: 1px solid #eee; padding-bottom: 10px;">🔗 Shortcode Button Styling</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 250px;">Background Color</th>
                            <td><input type="color" name="wp_ai_wa_shortcode_bg_color" value="<?php echo esc_attr(get_option('wp_ai_wa_shortcode_bg_color', '#25D366')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Hover Background Color</th>
                            <td><input type="color" name="wp_ai_wa_shortcode_hover_color" value="<?php echo esc_attr(get_option('wp_ai_wa_shortcode_hover_color', '#128C7E')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Text Color</th>
                            <td><input type="color" name="wp_ai_wa_shortcode_text_color" value="<?php echo esc_attr(get_option('wp_ai_wa_shortcode_text_color', '#ffffff')); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row">Icon Size (px)</th>
                            <td><input type="number" name="wp_ai_wa_shortcode_icon_size" value="<?php echo esc_attr(get_option('wp_ai_wa_shortcode_icon_size', 24)); ?>" min="12" max="100"></td>
                        </tr>
                        <tr>
                            <th scope="row">Shortcode Icon URL (optional)</th>
                            <td>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="text" id="wp_ai_wa_shortcode_icon" name="wp_ai_wa_shortcode_icon" class="regular-text" value="<?php echo esc_attr(get_option('wp_ai_wa_shortcode_icon')); ?>" style="width: 100%; max-width: 400px;">
                                    <button type="button" class="button" id="upload_shortcode_icon_btn">Upload Icon</button>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <hr style="margin:20px 0; border: 0; border-top: 1px solid #eee;">
                
                <!-- 🚀 FIX: Animated Save Button Section -->
                <p class="submit" style="margin:0; display:flex; align-items:center; gap:10px;">
                    <input type="submit" id="wa-save-btn" class="button button-primary button-large" value="Save WhatsApp Settings" />
                    <span class="spinner" id="wa-save-spinner" style="float:none; margin:0;"></span>
                </p>
            </form>
        </div>

        <script>
            (function() {
                var activeTab = localStorage.getItem('wp_ai_wa_active_tab') || 'tab-general';
                var tabElement = document.getElementById(activeTab);
                
                if (tabElement) {
                    tabElement.classList.add('nav-tab-active');
                }
                
                if (activeTab === 'tab-general') {
                    document.getElementById('content-general').style.display = 'block';
                } else if (activeTab === 'tab-styling') {
                    document.getElementById('content-styling').style.display = 'block';
                }
            })();
        </script>

        <script>
        jQuery(document).ready(function($) {
            
            // Tab Switching Logic
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var clickedTabId = $(this).attr('id');

                localStorage.setItem('wp_ai_wa_active_tab', clickedTabId);

                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                $('.wa-tab-content').hide();
                if (clickedTabId === 'tab-general') {
                    $('#content-general').show();
                } else if (clickedTabId === 'tab-styling') {
                    $('#content-styling').show();
                }
            });

            // 🚀 FIX: Submit Button Animation Logic
            $('#wa-settings-form').on('submit', function() {
                var btn = $('#wa-save-btn');
                var spinner = $('#wa-save-spinner');
                
                btn.val('Saving...');
                btn.css('pointer-events', 'none'); // Double click rokne ke liye
                spinner.addClass('is-active');
            });

            // Media Uploader Logic
            var mediaUploaderFrame;

            function openMediaUploader(targetInput) {
                if (mediaUploaderFrame) {
                    mediaUploaderFrame.open();
                    mediaUploaderFrame.off('select'); 
                    mediaUploaderFrame.on('select', function() {
                        var attachment = mediaUploaderFrame.state().get('selection').first().toJSON();
                        $(targetInput).val(attachment.url);
                    });
                    return;
                }

                mediaUploaderFrame = wp.media({
                    title: 'Select or Upload Icon',
                    library: { type: ['image'] },
                    button: { text: 'Use this Icon' },
                    multiple: false
                });

                mediaUploaderFrame.on('select', function() {
                    var attachment = mediaUploaderFrame.state().get('selection').first().toJSON();
                    $(targetInput).val(attachment.url);
                });

                mediaUploaderFrame.open();
            }

            // Button Clicks for Media Upload
            $('#upload_wa_icon_btn').on('click', function(e) { 
                e.preventDefault(); 
                openMediaUploader('#wp_ai_wa_icon'); 
            });
            
            $('#upload_shortcode_icon_btn').on('click', function(e) { 
                e.preventDefault(); 
                openMediaUploader('#wp_ai_wa_shortcode_icon'); 
            });
            
        });
        </script>
        <?php
    }
}