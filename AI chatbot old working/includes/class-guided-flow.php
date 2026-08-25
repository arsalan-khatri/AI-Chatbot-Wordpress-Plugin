<?php
/**
 * Guided Conversational Flow (Strict Chat Queries)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Guided_Flow {

    private $option_name = 'wp_ai_guided_queries';

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_submenu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_submenu() {
        add_submenu_page(
            'wp-ai-chatbot',
            'Guided Flow',
            'Guided Flow',
            'manage_options',
            'wp-ai-chatbot-guided',
            array( $this, 'display_page' )
        );
    }

    public function register_settings() {
        register_setting( 'wp_ai_guided_group', $this->option_name );
        register_setting( 'wp_ai_guided_group', 'wp_ai_guided_flow_enabled' );
    }

    public function display_page() {
        ?>
        <div class="wrap">
            <h1>Guided Conversational Flow</h1>
            <?php 
                $queries = get_option( $this->option_name, array() );
                $is_enabled = get_option( 'wp_ai_guided_flow_enabled', '0' );
        
                if ( isset($_GET['settings-updated']) ) {
                    echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
                }
            ?>
            <p>Restrict user typing and guide them through specific predefined queries. These will appear as clickable bubbles inside the chat.</p>

            <!-- 🚀 FIX: Added ID to form for JS targeting -->
            <form method="post" action="options.php" id="guided-flow-form" style="background:#fff; padding:20px; border-radius:8px; border:1px solid #ddd; width:100%; box-sizing:border-box;">
                <?php settings_fields( 'wp_ai_guided_group' ); ?>
                
                <table class="form-table" style="margin-bottom: 20px;">
                    <tr>
                        <th scope="row" style="font-weight: 600; width: 250px;">Enable Quick Guide</th>
                        <td>
                            <input type="hidden" name="wp_ai_guided_flow_enabled" value="0" />
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" name="wp_ai_guided_flow_enabled" value="1" <?php checked('1', $is_enabled); ?> style="margin:0;" />
                                Show the predefined query options in the chat window.
                            </label>
                        </td>
                    </tr>
                </table>

                <hr style="margin:20px 0; border: 0; border-top: 1px solid #eee;">

                <h3 style="margin-top:0;">Predefined Chat Queries</h3>
                <div id="guided-queries-wrapper">
                    <?php if ( !empty($queries) && is_array($queries) ) : foreach ( $queries as $index => $query ) : ?>
                        <div class="guided-query-row" style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" name="<?php echo $this->option_name; ?>[]" value="<?php echo esc_attr( $query ); ?>" class="regular-text" placeholder="e.g., Show all products" style="flex:1;" />
                            <button type="button" class="button remove-query-btn" style="color:#d63638; border-color:#d63638;">Remove</button>
                        </div>
                    <?php endforeach; else : ?>
                        <div class="guided-query-row" style="display:flex; gap:10px; margin-bottom:10px;">
                            <input type="text" name="<?php echo $this->option_name; ?>[]" value="" class="regular-text" placeholder="e.g., Show all products" style="flex:1;" />
                            <button type="button" class="button remove-query-btn" style="color:#d63638; border-color:#d63638;">Remove</button>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="button" class="button" id="add-query-btn" style="margin-top:10px;">+ Add New Query</button>
                <hr style="margin:20px 0; border: 0; border-top: 1px solid #eee;">
                
                <!-- 🚀 FIX: Animated Save Button Section -->
                <p class="submit" style="margin:0; display:flex; align-items:center; gap:10px;">
                    <input type="submit" id="guided-save-btn" class="button button-primary button-large" value="Save Flow Settings" />
                    <span class="spinner" id="guided-save-spinner" style="float:none; margin:0;"></span>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#add-query-btn').on('click', function() {
                var html = '<div class="guided-query-row" style="display:flex; gap:10px; margin-bottom:10px;">' +
                           '<input type="text" name="<?php echo $this->option_name; ?>[]" class="regular-text" placeholder="New Query" style="flex:1;" />' +
                           '<button type="button" class="button remove-query-btn" style="color:#d63638; border-color:#d63638;">Remove</button>' +
                           '</div>';
                $('#guided-queries-wrapper').append(html);
            });
            
            $(document).on('click', '.remove-query-btn', function() {
                $(this).closest('.guided-query-row').remove();
            });

            // 🚀 FIX: Submit Button Animation Logic
            $('#guided-flow-form').on('submit', function() {
                var btn = $('#guided-save-btn');
                var spinner = $('#guided-save-spinner');
                
                btn.val('Saving...');
                btn.css('pointer-events', 'none'); // Double click ko rokne ke liye
                spinner.addClass('is-active');
            });
        });
        </script>
        <?php
    }
}