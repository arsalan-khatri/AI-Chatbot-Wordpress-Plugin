<?php
/**
 * Quick Actions (Bubbles) Manager Class
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Quick_Actions {

    private $option_name = 'wp_ai_chatbot_quick_actions';

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_submenu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_footer', array( $this, 'print_admin_js' ) ); // Inline JS for this specific page
    }

    public function add_submenu() {
        add_submenu_page( 'wp-ai-chatbot', 'Quick Action Bubbles', 'Quick Actions', 'manage_options', 'wp-ai-chatbot-quick-actions', array( $this, 'display_page' ) );
    }

    public function register_settings() {
        register_setting( 'wp_ai_chatbot_quick_actions_group', $this->option_name, array( $this, 'sanitize_actions' ) );
        add_settings_section( 'wp_ai_quick_actions_sec', 'Quick Reply Bubbles', array($this, 'section_cb'), 'wp-ai-chatbot-quick-actions' );
        add_settings_field( 'quick_actions', 'Action Bubbles', array( $this, 'render_actions' ), 'wp-ai-chatbot-quick-actions', 'wp_ai_quick_actions_sec' );
    }

    public function sanitize_actions( $input ) {
        $clean = array();
        if ( is_array( $input ) ) {
            foreach ( $input as $action ) {
                if ( ! empty( $action['label'] ) && ! empty( $action['form_id'] ) ) {
                    $clean[] = array(
                        'label'   => sanitize_text_field( $action['label'] ),
                        'form_id' => sanitize_text_field( $action['form_id'] )
                    );
                }
            }
        }
        return $clean;
    }

    public function section_cb() {
        echo '<p>Add horizontal scrolling buttons above the chat input. When clicked, they will instantly trigger the selected form.</p>';
    }

    public function render_actions() {
        $actions = get_option( $this->option_name, array() );
        $available_forms = get_option( 'wp_ai_chatbot_multi_forms', array() ); // Fetching forms from DB
        
        echo '<div id="wp-ai-quick-actions-container">';
        echo '<table class="wp-list-table widefat fixed striped" id="wp-ai-actions-table" style="max-width: 600px; margin-bottom: 15px;">';
        echo '<thead><tr><th>Bubble Label (e.g. Get Quote)</th><th>Trigger Form</th><th>Action</th></tr></thead>';
        echo '<tbody>';
        
        if ( is_array( $actions ) && ! empty( $actions ) ) {
            foreach ( $actions as $index => $action ) {
                $label = esc_attr( $action['label'] );
                $selected_form = esc_attr( $action['form_id'] );
                
                echo '<tr>';
                echo '<td><input type="text" name="wp_ai_chatbot_quick_actions['.$index.'][label]" value="'.$label.'" style="width: 100%;" required /></td>';
                echo '<td><select name="wp_ai_chatbot_quick_actions['.$index.'][form_id]" style="width: 100%;" required>';
                echo '<option value="">-- Select Form --</option>';
                if(is_array($available_forms)) {
                    foreach($available_forms as $form) {
                        echo '<option value="'.esc_attr($form['id']).'" '.selected($selected_form, $form['id'], false).'>'.esc_html($form['title']).'</option>';
                    }
                }
                echo '</select></td>';
                echo '<td><button type="button" class="button remove-ai-action">Remove</button></td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody></table>';
        echo '<button type="button" class="button button-primary" id="add-ai-action">+ Add Bubble</button>';
        echo '</div>';
        
        // Passing forms to JS for new rows
        echo '<script>var wpAiAvailableForms = ' . json_encode($available_forms) . ';</script>';
    }

    public function display_page() {
        ?>
        <div class="wrap">
            <h1>Quick Action Bubbles</h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wp_ai_chatbot_quick_actions_group' );
                do_settings_sections( 'wp-ai-chatbot-quick-actions' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    // JS explicitly for this page to handle rows
    public function print_admin_js() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wp-ai-chatbot-quick-actions' ) return;
        ?>
        <script>
        jQuery(document).ready(function($) {
            let uniqueId = $('#wp-ai-actions-table tbody tr').length + 100;
            
            $('#add-ai-action').on('click', function(e) {
                e.preventDefault();
                uniqueId++;
                let formOptions = '<option value="">-- Select Form --</option>';
                if(wpAiAvailableForms && wpAiAvailableForms.length > 0) {
                    wpAiAvailableForms.forEach(function(f) {
                        formOptions += `<option value="${f.id}">${f.title}</option>`;
                    });
                }
                let newRow = `<tr>
                    <td><input type="text" name="wp_ai_chatbot_quick_actions[${uniqueId}][label]" placeholder="e.g. Need Support" style="width:100%;" required></td>
                    <td><select name="wp_ai_chatbot_quick_actions[${uniqueId}][form_id]" style="width:100%;" required>${formOptions}</select></td>
                    <td><button type="button" class="button remove-ai-action">Remove</button></td>
                </tr>`;
                $('#wp-ai-actions-table tbody').append(newRow);
            });

            $(document).on('click', '.remove-ai-action', function(e) {
                e.preventDefault(); $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }
}