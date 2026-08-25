<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Form_Builder {

    private $option_name = 'wp_ai_chatbot_multi_forms';

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_form_builder_submenu' ) );
        add_action( 'admin_init', array( $this, 'register_form_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_form_assets' ) );
    }

    public function add_form_builder_submenu() {
        add_submenu_page( 'wp-ai-chatbot', 'Form Builder', 'Form Builder', 'manage_options', 'wp-ai-chatbot-form', array( $this, 'display_page' ) );
    }

    public function register_form_settings() {
        // 🚀 FIX: Removed add_settings_section and add_settings_field. 
        // Is se WordPress ka left side wala "Your Forms" ka blank column khatam ho jayega aur design 100% full width ho jayega.
        register_setting( 'wp_ai_chatbot_form_group', $this->option_name, array( $this, 'sanitize_forms' ) );
    }

    public function sanitize_forms( $input ) {
        $clean = array();
        if ( is_array( $input ) ) {
            foreach ( $input as $form ) {
                if ( empty( $form['title'] ) || empty( $form['intent'] ) ) continue;
                
                $form_id = isset($form['id']) && !empty($form['id']) ? $form['id'] : 'form_' . substr(md5(rand()), 0, 8);
                
                // Save Enable/Disable State
                $is_enabled = (isset($form['is_enabled']) && $form['is_enabled'] === '1') ? '1' : '0';
                
                $clean_form = array(
                    'id'         => sanitize_text_field( $form_id ),
                    'is_enabled' => sanitize_text_field( $is_enabled ),
                    'title'      => sanitize_text_field( $form['title'] ),
                    'intent'     => sanitize_text_field( $form['intent'] ),
                    'fields'     => array()
                );

                if ( isset( $form['fields'] ) && is_array( $form['fields'] ) ) {
                    foreach ( $form['fields'] as $field ) {
                        if ( empty( $field['label'] ) ) continue;
                        $clean_form['fields'][] = array(
                            'label'    => sanitize_text_field( $field['label'] ),
                            'type'     => sanitize_text_field( $field['type'] ),
                            'options'  => isset( $field['options'] ) ? sanitize_text_field( $field['options'] ) : '',
                            'required' => isset( $field['required'] ) ? 'yes' : 'no'
                        );
                    }
                }

                // Har form ka dedicated flat table create/sync karo
                $this->sync_form_table( $clean_form );

                $clean[] = $clean_form;
            }
        }
        return $clean;
    }

    private function sync_form_table( $form ) {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'ai_leads_' . sanitize_key( $form['id'] );
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta( $sql );

        $existing_cols = $wpdb->get_col( "DESCRIBE {$table_name}", 0 );

        foreach ( $form['fields'] as $field ) {
            $col = sanitize_key( str_replace( ' ', '_', strtolower( $field['label'] ) ) );
            if ( empty( $col ) || in_array( $col, $existing_cols ) ) continue;

            $col_type = in_array( $field['type'], array( 'multiselect', 'textarea' ) ) ? 'TEXT' : 'VARCHAR(500)';
            $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN `{$col}` {$col_type} DEFAULT NULL" );
        }
    }

    public function enqueue_form_assets($hook) {
        if ( isset($_GET['page']) && $_GET['page'] === 'wp-ai-chatbot-form' ) {
            wp_enqueue_style( 'wp-ai-chatbot-admin-style', WP_AI_CHATBOT_URL . 'admin/css/admin-style.css', array(), WP_AI_CHATBOT_VERSION, 'all' );
            wp_enqueue_script( 'wp-ai-chatbot-admin-script', WP_AI_CHATBOT_URL . 'admin/js/admin-script.js', array( 'jquery' ), WP_AI_CHATBOT_VERSION, true );
        }
    }

    public function render_forms() {
        $forms = get_option( $this->option_name, array() );
        echo '<div id="wp-ai-forms-container">';
        
        if ( is_array( $forms ) && ! empty( $forms ) ) {
            foreach ( $forms as $f_idx => $form ) {
                $id = esc_attr($form['id']);
                $title = esc_attr($form['title']);
                $intent = esc_attr($form['intent']);
                
                $is_enabled = isset($form['is_enabled']) ? $form['is_enabled'] : '1';
                
                echo '<div class="postbox wp-ai-form-block" style="margin-bottom:20px; border:1px solid #ccd0d4; border-radius:8px; overflow:hidden;">';
                
                // Form Header with Checkbox
                echo '<div class="postbox-header" style="display:flex; justify-content:space-between; align-items:center; padding:15px; background:#f8f9fa; border-bottom:1px solid #e2e4e7;">';
                echo '<div style="display:flex; align-items:center; width:50%; gap:10px;">';
                echo '<input type="hidden" name="wp_ai_chatbot_multi_forms['.$f_idx.'][id]" value="'.$id.'">';
                echo '<input type="text" name="wp_ai_chatbot_multi_forms['.$f_idx.'][title]" value="'.$title.'" placeholder="Form Title" style="font-size:16px; font-weight:bold; width:100%; border:1px solid #ccc; padding:5px 10px;" required />';
                echo '</div>';
                
                echo '<div style="display:flex; align-items:center; gap:20px;">';
                echo '<label style="cursor:pointer; font-weight:600; color:#2271b1; display:flex; align-items:center; gap:5px;">';
                echo '<input type="hidden" name="wp_ai_chatbot_multi_forms['.$f_idx.'][is_enabled]" value="0">';
                echo '<input type="checkbox" name="wp_ai_chatbot_multi_forms['.$f_idx.'][is_enabled]" value="1" '.checked('1', $is_enabled, false).'> Enable Form';
                echo '</label>';
                echo '<button type="button" class="button remove-ai-form" style="color:#d63638; border-color:#d63638; display:flex; align-items:center; gap:5px;">Delete Form</button>';
                echo '</div>';
                echo '</div>';
                // End Header
                
                echo '<div class="inside" style="padding:20px;">';
                
                echo '<div style="background: #e5f5fa; padding: 12px 15px; border-left: 4px solid #00a0d2; margin-bottom: 20px; border-radius: 4px;">';
                echo '<span style="font-size: 14px;"><strong>👉 Shortcode for this form:</strong></span> ';
                echo '<code style="font-size: 15px; font-weight: bold; user-select: all; cursor: pointer; background: #fff; padding: 4px 8px; border: 1px solid #ccc; display: inline-block; margin-left: 10px; border-radius:4px;">[SHOW_FORM_' . $id . ']</code>';
                echo '<p style="margin: 5px 0 0 0; color: #555; font-size: 13px;"><em>Copy this shortcode and paste it in your Admin Panel Prompt (e.g., in the FINAL rule) to trigger this specific form.</em></p>';
                echo '</div>';

                echo '<p style="margin-bottom:5px;"><strong>AI Trigger Intent (Instruction for AI):</strong></p>';
                echo '<input type="text" name="wp_ai_chatbot_multi_forms['.$f_idx.'][intent]" value="'.$intent.'" placeholder="e.g. If user asks for pricing or sales" style="width:100%; margin-bottom: 20px; padding:6px 10px;" required />';
                
                echo '<table class="wp-list-table widefat fixed striped wp-ai-fields-table" style="margin-bottom: 15px; border:1px solid #e2e4e7;">';
                echo '<thead><tr><th style="width:25%;">Field Label</th><th style="width:20%;">Type</th><th style="width:35%;">Options (Comma separated)</th><th style="width:10%;">Required</th><th style="width:10%;">Action</th></tr></thead>';
                echo '<tbody>';
                
                if ( !empty($form['fields']) ) {
                    foreach ( $form['fields'] as $fld_idx => $field ) {
                        $f_label = esc_attr($field['label']);
                        $f_type = $field['type'];
                        $f_opts = esc_attr($field['options']);
                        $f_req = ($field['required'] === 'yes') ? 'checked' : '';
                        $display_opts = ($f_type === 'select' || $f_type === 'multiselect') ? 'block' : 'none';
                        
                        echo '<tr>';
                        echo '<td><input type="text" name="wp_ai_chatbot_multi_forms['.$f_idx.'][fields]['.$fld_idx.'][label]" value="'.$f_label.'" style="width:100%;" required></td>';
                        
                        echo '<td><select class="ai-field-type-select" name="wp_ai_chatbot_multi_forms['.$f_idx.'][fields]['.$fld_idx.'][type]" style="width:100%;">';
                        echo '<option value="text" '.selected($f_type,'text',false).'>Text</option>';
                        echo '<option value="email" '.selected($f_type,'email',false).'>Email</option>';
                        echo '<option value="tel" '.selected($f_type,'tel',false).'>Phone</option>';
                        echo '<option value="textarea" '.selected($f_type,'textarea',false).'>Textarea</option>';
                        echo '<option value="select" '.selected($f_type,'select',false).'>Dropdown</option>';
                        echo '<option value="multiselect" '.selected($f_type,'multiselect',false).'>Multi-Select</option>';
                        echo '</select></td>'; 
                        
                        echo '<td><input type="text" class="ai-field-options" name="wp_ai_chatbot_multi_forms['.$f_idx.'][fields]['.$fld_idx.'][options]" value="'.$f_opts.'" placeholder="opt1, opt2, opt3" style="width:100%; display:'.$display_opts.';"></td>';
                        
                        echo '<td><input type="checkbox" name="wp_ai_chatbot_multi_forms['.$f_idx.'][fields]['.$fld_idx.'][required]" value="yes" '.$f_req.'></td>';
                        echo '<td><button type="button" class="button remove-ai-field">X</button></td>';
                        echo '</tr>';
                    }
                }
                
                echo '</tbody></table>';
                echo '<button type="button" class="button add-ai-field" data-form-id="'.$f_idx.'" style="border-color:#2271b1; color:#2271b1;">+ Add Field to this Form</button>';
                echo '</div></div>';
            }
        }
        
        echo '</div>';
        echo '<button type="button" class="button button-primary button-large" id="add-ai-multi-form" style="padding: 5px 20px; font-size: 15px;">+ Create New Form</button>';
    }

    public function display_page() {
        ?>
        <div class="wrap">
            <h1>Multiple Dynamic Forms Builder</h1>
            
            <?php settings_errors(); ?>
            <p style="font-size: 14px; margin-bottom: 20px; color: #555;">Create multiple forms. Give each form a specific "Intent" (e.g., "If user wants to book appointment") so the AI knows which form to trigger.</p>

            <!-- 🚀 FIX: Ab yahan forms ek full-width white box mein display honge -->
            <form method="post" action="options.php" style="background:#fff; padding:20px; border-radius:8px; border:1px solid #ddd; width:100%; box-sizing:border-box;">
                <?php
                settings_fields( 'wp_ai_chatbot_form_group' );
                $this->render_forms();
                echo '<hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">';
                submit_button('Save All Forms', 'primary large', 'submit', false);
                ?>
            </form>
        </div>
        
        <script>
        jQuery(document).on('click', '#add-ai-multi-form', function() {
            setTimeout(function() {
                var newBlock = jQuery('.wp-ai-form-block').last();
                if (newBlock.find('input[name*="[is_enabled]"]').length === 0) {
                    var titleInput = newBlock.find('input[name*="[title]"]');
                    var match = titleInput.attr('name').match(/\[(\d+)\]/);
                    if (match) {
                        var idx = match[1];
                        var cbHTML = '<label style="cursor:pointer; font-weight:600; color:#2271b1; display:flex; align-items:center; gap:5px; margin-right:20px;"><input type="hidden" name="wp_ai_chatbot_multi_forms['+idx+'][is_enabled]" value="0"><input type="checkbox" name="wp_ai_chatbot_multi_forms['+idx+'][is_enabled]" value="1" checked> Enable Form</label>';
                        newBlock.find('.remove-ai-form').before(cbHTML);
                        newBlock.find('.postbox-header').css({'display':'flex', 'justify-content':'space-between', 'align-items':'center'});
                    }
                }
            }, 100);
        });
        </script>
        <?php
    }
}