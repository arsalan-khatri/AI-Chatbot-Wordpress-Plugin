<?php
/**
 * Vector RAG Control Room (Admin UI & Settings)
 * Advanced Multi-Select & Granular Control (With LIVE Counter, Outdated Detection & Filters)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_RAG_Manager {

    private $option_name = 'wp_ai_rag_settings';

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_submenu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_wp_ai_get_vector_count', array( $this, 'ajax_get_vector_count' ) );
    }

    public function add_submenu() {
        add_submenu_page(
            'wp-ai-chatbot',
            'Vector RAG System',
            'Vector RAG DB',
            'manage_options',
            'wp-ai-chatbot-rag',
            array( $this, 'display_page' )
        );
    }

    public function register_settings() {
        register_setting( 'wp_ai_rag_group', $this->option_name );
    }

    public function ajax_get_vector_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_vector_embeddings';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        wp_send_json_success(array('count' => number_format($count)));
    }

    private function get_post_options($post_type) {
        $posts = get_posts(['post_type' => $post_type, 'numberposts' => -1, 'post_status' => 'publish']);
        $options = [];
        foreach($posts as $p) {
            $options[$p->ID] = $p->post_title;
        }
        return $options;
    }

    private function update_single_vector($vector_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_vector_embeddings';
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $vector_id));
        if (!$record) return "Record not found in Vector Database.";

        $text_to_embed = "";
        $title = "";

        // 🚀 FIX 1: Bypass Cache - Force Fresh Data
        clean_post_cache($record->source_id); 
        
        if ($record->source_type === 'wc_product' && class_exists('WooCommerce')) {
            wc_delete_product_transients($record->source_id); // 🚀 Purana WooCommerce data flush karo
            
            $product = wc_get_product($record->source_id);
            if (!$product) return "Product not found in WooCommerce.";
            
            $title = $product->get_name();
            $price = $product->get_price();
            $sku = $product->get_sku();
            $desc = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
            
            $cats = wp_get_post_terms($record->source_id, 'product_cat', ['fields' => 'names']);
            $cat_str = !is_wp_error($cats) ? implode(', ', $cats) : '';

            $text_to_embed = "Product Name: $title. ";
            if ($sku) $text_to_embed .= "SKU: $sku. ";
            if ($price) $text_to_embed .= "Price: $price. ";
            if ($cat_str) $text_to_embed .= "Category: $cat_str. ";
            $text_to_embed .= "Details: " . wp_strip_all_tags($desc);
            
} elseif (in_array($record->source_type, ['wp_post', 'wp_page', 'tutor_course'])) {
            $post = get_post($record->source_id);
            if (!$post) return "Post/Page not found in WordPress.";
            
            $title = $post->post_title;
            
            // UNIVERSAL BUILDER EXTRACTION (Elementor, Gutenberg, etc.)
            $raw_content = $post->post_content;
            
            // 1. Agar Elementor use ho raha hai, toh uska properly rendered frontend data uthao
            if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->db->is_built_with_elementor( $post->ID ) ) {
                $raw_content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post->ID );
            } else {
                // 2. Gutenberg Blocks ya dusre Shortcodes (WPBakery/Divi) ke liye
                if ( function_exists( 'do_blocks' ) ) {
                    $raw_content = do_blocks( $raw_content );
                }
                $raw_content = do_shortcode( $raw_content );
            }
            
            // 3. HTML hata kar saaf text banao
            $clean_content = wp_strip_all_tags( $raw_content );
            $text_to_embed = wp_strip_all_tags($title) . ". " . $clean_content;
            
        } elseif ($record->source_type === 'custom_kb') {
            $parts = explode('_', $record->source_id);
            $row_id = array_pop($parts);
            $kb_id = implode('_', $parts);
            
            $kb_table = $wpdb->prefix . 'ai_kb_' . $kb_id;
            if ($wpdb->get_var("SHOW TABLES LIKE '$kb_table'") != $kb_table) return "Knowledge Base table not found.";
            
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $kb_table WHERE id = %d", $row_id), ARRAY_A);
            if (!$row) return "Record not found in Knowledge Base.";
            
            $cols = array_diff(array_keys($row), ['id', 'created_at']);
            $text_parts = [];
            foreach ($cols as $col) {
                if (!empty($row[$col])) {
                    $text_parts[] = ucfirst(str_replace('_', ' ', $col)) . ": " . wp_strip_all_tags($row[$col]);
                }
            }
            $text_to_embed = implode(". ", $text_parts);
            
            $first_col = reset($cols);
            $title = !empty($row[$first_col]) ? $row[$first_col] : "KB Record " . $row_id;
        }

        $text_to_embed = preg_replace('/\\s+/', ' ', $text_to_embed); 
        if (empty(trim($text_to_embed))) return "Extracted data is empty, cannot generate vector.";

        $options = get_option('wp_ai_rag_settings', []);
        $api_settings = get_option('wp_ai_chatbot_settings', []);
        $provider = isset($options['embedding_provider']) ? $options['embedding_provider'] : 'gemini';
        
        $vector_data = false;
        
        if ($provider === 'openai') {
            $model = !empty($options['openai_embedding_model']) ? $options['openai_embedding_model'] : 'text-embedding-3-small';
            $api_key = $api_settings['openai_api_key'] ?? '';
            if (empty($api_key)) return "OpenAI API Key is missing.";
            
            $response = wp_remote_post('https://api.openai.com/v1/embeddings', [
                'timeout' => 15, 'sslverify' => false,
                'headers' => ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key],
                'body' => wp_json_encode(['model' => $model, 'input' => mb_substr($text_to_embed, 0, 8000)])
            ]);
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['data'][0]['embedding'])) $vector_data = $body['data'][0]['embedding'];
            } else {
                return $response->get_error_message();
            }
        } else {
            $model = !empty($options['gemini_embedding_model']) ? $options['gemini_embedding_model'] : 'text-embedding-004';
            $api_key = $api_settings['gemini_api_key'] ?? '';
            if (empty($api_key)) return "Gemini API Key is missing.";
            
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':embedContent?key=' . $api_key;
            $response = wp_remote_post($endpoint, [
                'timeout' => 15, 'sslverify' => false,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode(['model' => 'models/' . $model, 'content' => ['parts' => [['text' => mb_substr($text_to_embed, 0, 8000)]]]])
            ]);
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['embedding']['values'])) $vector_data = $body['embedding']['values'];
            } else {
                return $response->get_error_message();
            }
        }

        if (!$vector_data) return "API returned an empty vector. Please check API limits.";

        // 🚀 FIX 2: Force Database to change updated_at Timestamp
        $wpdb->update($table_name, [
            'title' => $title,
            'content' => $text_to_embed,
            'vector_data' => wp_json_encode($vector_data),
            'updated_at' => current_time('mysql') // Automatically set the latest time
        ], ['id' => $vector_id]);

        return true;
    }

    public function display_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_vector_embeddings';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $wpdb->query("CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                source_type varchar(50) NOT NULL,
                source_id varchar(100) NOT NULL,
                title text NOT NULL,
                content longtext NOT NULL,
                vector_data longtext NOT NULL,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY source_unique (source_type, source_id)
            ) $charset_collate;");
        }

        if (isset($_POST['clear_vector_db'])) {
            check_admin_referer('wp_ai_rag_action');
            $wpdb->query("TRUNCATE TABLE $table_name");
            echo '<div class="notice notice-warning is-dismissible"><p>Vector Database Emptied Successfully!</p></div>';
        }

        if (isset($_POST['delete_single_vector']) && !empty($_POST['vector_id'])) {
            check_admin_referer('wp_ai_rag_action');
            $del_id = intval($_POST['vector_id']);
            $wpdb->delete($table_name, ['id' => $del_id]);
            echo '<div class="notice notice-success is-dismissible"><p>Single Vector record deleted successfully!</p></div>';
        }

        if (isset($_POST['update_single_vector']) && !empty($_POST['update_vector_id'])) {
            check_admin_referer('wp_ai_rag_action');
            $upd_id = intval($_POST['update_vector_id']);
            $result = $this->update_single_vector($upd_id);
            if ($result === true) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Success:</strong> Vector updated instantly with fresh data! Chatbot is now synced.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p><strong>Update Failed:</strong> ' . esc_html($result) . '</p></div>';
            }
        }

        if (isset($_POST['sync_vectors_now'])) {
            check_admin_referer('wp_ai_rag_action');
            do_action('wp_ai_trigger_vector_sync');
        }

        $opts = get_option($this->option_name, []);
        $total_vectors = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        if ( isset($_GET['message']) ) {
            $status_class = (isset($_GET['status']) && $_GET['status'] === 'error') ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . $status_class . ' is-dismissible"><p><strong>' . esc_html($_GET['message']) . '</strong></p></div>';
        }

        $auto_resume = isset($_GET['auto_resume']) && $_GET['auto_resume'] == '1';

        $wc_products = class_exists('WooCommerce') ? $this->get_post_options('product') : [];
        $wp_pages = $this->get_post_options('page');
        $wp_posts = $this->get_post_options('post');
        $tutor_courses = post_type_exists('courses') ? $this->get_post_options('courses') : [];
        $kbs = get_option('wp_ai_chatbot_knowledge_bases', []);

        $query = "
            SELECT 
                v.id, v.source_type, v.title, v.content, v.updated_at,
                (CASE WHEN v.source_type IN ('wc_product', 'wp_page', 'wp_post', 'tutor_course') THEN p.post_modified ELSE NULL END) as source_modified
            FROM $table_name v
            LEFT JOIN {$wpdb->posts} p ON v.source_id = p.ID AND v.source_type IN ('wc_product', 'wp_page', 'wp_post', 'tutor_course')
            ORDER BY v.updated_at DESC 
            LIMIT 300
        ";
        $vector_records = $wpdb->get_results($query);
        
        $source_types_available = [];
        foreach($vector_records as $rec) {
            if(!in_array($rec->source_type, $source_types_available)) $source_types_available[] = $rec->source_type;
        }

        ?>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <div class="wrap">
            <h1>Vector RAG Control Room (Semantic Brain)</h1>
            <?php settings_errors(); ?>
            <p>Smart Batch Processing: Select exactly which products, pages, or courses AI should memorize.</p>

            <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; margin-bottom:20px; display:flex; gap:30px; align-items:center; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <div>
                    <h2 style="margin:0; font-size:36px; color:#2271b1; font-weight:bold;" id="live-vector-count"><?php echo number_format($total_vectors); ?></h2>
                    <span style="color:#666; font-size:13px; font-weight:600;">Total Vectors</span>
                </div>
                <div style="border-left:1px solid #ddd; padding-left:30px;" id="wp-ai-action-area">
                    <form method="post" id="wp-ai-sync-form" <?php if($auto_resume) echo 'style="display:none;"'; ?>>
                        <?php wp_nonce_field('wp_ai_rag_action'); ?>
                        <button type="submit" name="sync_vectors_now" id="wp-ai-sync-btn" class="button button-primary button-large">⚡ Process & Index Selected Sources</button>
                        <button type="submit" name="clear_vector_db" class="button button-secondary" onclick="return confirm('Delete all stored vectors?');">Empty Vector DB</button>
                    </form>
                    
                    <div id="wp-ai-processing-status" style="<?php echo $auto_resume ? 'display:flex;' : 'display:none;'; ?> align-items:center; gap:12px;">
                        <div class="wp-ai-spinner"></div>
                        <span style="font-size:14px; font-weight:600; color:#2271b1;" id="wp-ai-processing-text">
                            <?php echo $auto_resume ? "Cooling down API... Auto-starting next batch in 3 seconds!" : "AI is generating vectors... Please wait..."; ?>
                        </span>
                    </div>
                </div>
            </div>

            <h2 class="nav-tab-wrapper" style="border-bottom:none; margin-bottom:15px;">
                <a href="#settings-tab" class="nav-tab nav-tab-active" id="tab-settings">Settings & Sync</a>
                <a href="#explorer-tab" class="nav-tab" id="tab-explorer">Vector Explorer (Filters & Status)</a>
            </h2>

            <div id="settings-tab" class="wp-ai-tab-content">
                <form method="post" action="options.php" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px; max-width:800px;">
                    <?php settings_fields( 'wp_ai_rag_group' ); ?>
                    
                    <h3 style="margin-top:0;">1. Dynamic Embedding Configuration</h3>
                    <hr>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Active Provider</th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[embedding_provider]" id="wp-ai-provider-select">
                                    <option value="gemini" <?php selected($opts['embedding_provider'] ?? 'gemini', 'gemini'); ?>>Google Gemini</option>
                                    <option value="openai" <?php selected($opts['embedding_provider'] ?? 'gemini', 'openai'); ?>>OpenAI</option>
                                </select>
                            </td>
                        </tr>
                        <tr class="provider-field openai-meta" <?php echo (($opts['embedding_provider'] ?? 'gemini') === 'openai') ? '' : 'style="display:none;"'; ?>>
                            <th scope="row">OpenAI Embedding Model</th>
                            <td>
                                <input type="text" name="<?php echo $this->option_name; ?>[openai_embedding_model]" value="<?php echo esc_attr($opts['openai_embedding_model'] ?? 'text-embedding-3-small'); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr class="provider-field gemini-meta" <?php echo (($opts['embedding_provider'] ?? 'gemini') === 'gemini') ? '' : 'style="display:none;"'; ?>>
                            <th scope="row">Gemini Embedding Model</th>
                            <td>
                                <input type="text" name="<?php echo $this->option_name; ?>[gemini_embedding_model]" value="<?php echo esc_attr($opts['gemini_embedding_model'] ?? 'text-embedding-004'); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>

                    <br>
                    <h3>2. Active Knowledge Sources (Granular Control)</h3>
                    <hr>
                    <table class="form-table">
                        <?php if (class_exists('WooCommerce')): ?>
                        <tr>
                            <th scope="row">WooCommerce Products</th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[wc_products_strategy]" class="wp-ai-strategy-select" data-target=".wc-products-ids">
                                    <option value="none" <?php selected($opts['wc_products_strategy'] ?? 'none', 'none'); ?>>Do Not Sync</option>
                                    <option value="all" <?php selected($opts['wc_products_strategy'] ?? 'none', 'all'); ?>>Sync All Products</option>
                                    <option value="specific" <?php selected($opts['wc_products_strategy'] ?? 'none', 'specific'); ?>>Sync Specific Products</option>
                                </select>
                                <div class="wc-products-ids wp-ai-multi-wrapper" style="<?php echo (($opts['wc_products_strategy'] ?? 'none') === 'specific') ? '' : 'display:none;'; ?> margin-top:10px;">
                                    <select name="<?php echo $this->option_name; ?>[wc_products_ids][]" multiple="multiple" class="wp-ai-select2" style="width:100%;">
                                        <?php 
                                        $saved_wc = $opts['wc_products_ids'] ?? [];
                                        foreach($wc_products as $id => $title) {
                                            $sel = in_array($id, $saved_wc) ? 'selected' : '';
                                            echo "<option value='{$id}' {$sel}>{$title}</option>";
                                        } 
                                        ?>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php if (post_type_exists('courses')): ?>
                        <tr>
                            <th scope="row">Tutor LMS Courses</th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[tutor_courses_strategy]" class="wp-ai-strategy-select" data-target=".tutor-courses-ids">
                                    <option value="none" <?php selected($opts['tutor_courses_strategy'] ?? 'none', 'none'); ?>>Do Not Sync</option>
                                    <option value="all" <?php selected($opts['tutor_courses_strategy'] ?? 'none', 'all'); ?>>Sync All Courses</option>
                                    <option value="specific" <?php selected($opts['tutor_courses_strategy'] ?? 'none', 'specific'); ?>>Sync Specific Courses</option>
                                </select>
                                <div class="tutor-courses-ids wp-ai-multi-wrapper" style="<?php echo (($opts['tutor_courses_strategy'] ?? 'none') === 'specific') ? '' : 'display:none;'; ?> margin-top:10px;">
                                    <select name="<?php echo $this->option_name; ?>[tutor_courses_ids][]" multiple="multiple" class="wp-ai-select2" style="width:100%;">
                                        <?php 
                                        $saved_tutor = $opts['tutor_courses_ids'] ?? [];
                                        foreach($tutor_courses as $id => $title) {
                                            $sel = in_array($id, $saved_tutor) ? 'selected' : '';
                                            echo "<option value='{$id}' {$sel}>{$title}</option>";
                                        } 
                                        ?>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>

                        <tr>
                            <th scope="row">WordPress Pages</th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[wp_pages_strategy]" class="wp-ai-strategy-select" data-target=".wp-pages-ids">
                                    <option value="none" <?php selected($opts['wp_pages_strategy'] ?? 'none', 'none'); ?>>Do Not Sync</option>
                                    <option value="all" <?php selected($opts['wp_pages_strategy'] ?? 'none', 'all'); ?>>Sync All Pages</option>
                                    <option value="specific" <?php selected($opts['wp_pages_strategy'] ?? 'none', 'specific'); ?>>Sync Specific Pages</option>
                                </select>
                                <div class="wp-pages-ids wp-ai-multi-wrapper" style="<?php echo (($opts['wp_pages_strategy'] ?? 'none') === 'specific') ? '' : 'display:none;'; ?> margin-top:10px;">
                                    <select name="<?php echo $this->option_name; ?>[wp_pages_ids][]" multiple="multiple" class="wp-ai-select2" style="width:100%;">
                                        <?php 
                                        $saved_pages = $opts['wp_pages_ids'] ?? [];
                                        foreach($wp_pages as $id => $title) {
                                            $sel = in_array($id, $saved_pages) ? 'selected' : '';
                                            echo "<option value='{$id}' {$sel}>{$title}</option>";
                                        } 
                                        ?>
                                    </select>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">WordPress Blog Posts</th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[wp_posts_strategy]" class="wp-ai-strategy-select" data-target=".wp-posts-ids">
                                    <option value="none" <?php selected($opts['wp_posts_strategy'] ?? 'none', 'none'); ?>>Do Not Sync</option>
                                    <option value="all" <?php selected($opts['wp_posts_strategy'] ?? 'none', 'all'); ?>>Sync All Posts</option>
                                    <option value="specific" <?php selected($opts['wp_posts_strategy'] ?? 'none', 'specific'); ?>>Sync Specific Posts</option>
                                </select>
                                <div class="wp-posts-ids wp-ai-multi-wrapper" style="<?php echo (($opts['wp_posts_strategy'] ?? 'none') === 'specific') ? '' : 'display:none;'; ?> margin-top:10px;">
                                    <select name="<?php echo $this->option_name; ?>[wp_posts_ids][]" multiple="multiple" class="wp-ai-select2" style="width:100%;">
                                        <?php 
                                        $saved_posts = $opts['wp_posts_ids'] ?? [];
                                        foreach($wp_posts as $id => $title) {
                                            $sel = in_array($id, $saved_posts) ? 'selected' : '';
                                            echo "<option value='{$id}' {$sel}>{$title}</option>";
                                        } 
                                        ?>
                                    </select>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Custom CSV Tables</th>
                            <td>
                                <select name="<?php echo $this->option_name; ?>[custom_kbs_strategy]" class="wp-ai-strategy-select" data-target=".custom-kbs-ids">
                                    <option value="none" <?php selected($opts['custom_kbs_strategy'] ?? 'none', 'none'); ?>>Do Not Sync</option>
                                    <option value="all" <?php selected($opts['custom_kbs_strategy'] ?? 'none', 'all'); ?>>Sync All KBs</option>
                                    <option value="specific" <?php selected($opts['custom_kbs_strategy'] ?? 'none', 'specific'); ?>>Sync Specific KBs</option>
                                </select>
                                <div class="custom-kbs-ids wp-ai-multi-wrapper" style="<?php echo (($opts['custom_kbs_strategy'] ?? 'none') === 'specific') ? '' : 'display:none;'; ?> margin-top:10px;">
                                    <select name="<?php echo $this->option_name; ?>[custom_kbs_ids][]" multiple="multiple" class="wp-ai-select2" style="width:100%;">
                                        <?php 
                                        $saved_kbs = $opts['custom_kbs_ids'] ?? [];
                                        foreach($kbs as $id => $title) {
                                            $sel = in_array($id, $saved_kbs) ? 'selected' : '';
                                            echo "<option value='{$id}' {$sel}>{$title}</option>";
                                        } 
                                        ?>
                                    </select>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <p class="submit" style="margin-bottom:0;"><input type="submit" class="button button-primary" value="Save RAG Settings" /></p>
                </form>
            </div>

            <div id="explorer-tab" class="wp-ai-tab-content" style="display:none;">
                <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3 style="margin:0;">Vector Database Explorer</h3>
                        <span style="font-size:12px; color:#888;">(Showing latest 300 records for performance)</span>
                    </div>

                    <div style="display:flex; gap:15px; margin-bottom:15px; background:#f9f9f9; padding:10px; border-radius:6px; border:1px solid #eee;">
                        <div>
                            <strong>Source Type:</strong><br>
                            <select id="filter-source">
                                <option value="all">All Sources</option>
                                <?php foreach($source_types_available as $src): ?>
                                    <option value="<?php echo esc_attr($src); ?>"><?php echo esc_html($src); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <strong>Search:</strong><br>
                            <input type="text" id="filter-search" placeholder="Type to search..." style="width:200px;">
                        </div>
                        <div>
                            <strong>Date Filter:</strong><br>
                            <input type="date" id="filter-date">
                        </div>
                    </div>

                    <?php if (empty($vector_records)): ?>
                        <p style="color:#666;">The Vector Database is currently empty. Run the sync process from the settings tab.</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat striped" id="vector-table">
                            <thead>
                                <tr>
                                    <th class="sortable" data-type="number" style="width:5%; cursor:pointer;">ID ↕</th>
                                    <th class="sortable" data-type="string" style="width:10%; cursor:pointer;">Type ↕</th>
                                    <th class="sortable" data-type="string" style="width:25%; cursor:pointer;">Title ↕</th>
                                    <th style="width:35%;">Content Preview</th>
                                    <th class="sortable" data-type="date" style="width:10%; cursor:pointer;">Last Synced ↕</th>
                                    <th style="width:15%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach($vector_records as $rec): 
                                    $is_outdated = false;
                                    if (!empty($rec->source_modified)) {
                                        $mod_time = strtotime($rec->source_modified);
                                        $sync_time = strtotime($rec->updated_at);
                                        if ($mod_time > $sync_time) {
                                            $is_outdated = true;
                                        }
                                    }
                                    $row_style = $is_outdated ? "background-color: #fff0f0;" : "";
                                ?>
                                    <tr style="<?php echo $row_style; ?>" data-source="<?php echo esc_attr($rec->source_type); ?>" data-date="<?php echo esc_attr(date('Y-m-d', strtotime($rec->updated_at))); ?>">
                                        <td class="col-id">#<?php echo esc_html($rec->id); ?></td>
                                        <td class="col-type"><span style="background:#eef3f8; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600; color:#2271b1;"><?php echo esc_html($rec->source_type); ?></span></td>
                                        <td class="col-title">
                                            <strong><?php echo esc_html($rec->title); ?></strong>
                                            <?php if($is_outdated): ?>
                                                <span style="color:#d63638; font-size:11px; font-weight:bold; margin-left:5px;">(Outdated Data!)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:12px; color:#555;"><?php echo esc_html(mb_strimwidth($rec->content, 0, 80, "...")); ?></td>
                                        <td class="col-date" style="font-size:12px;"><?php echo esc_html(date('Y-m-d', strtotime($rec->updated_at))); ?></td>
                                        <td style="white-space: nowrap;">
                                            <form method="post" style="display:inline-block;" onsubmit="return confirm('Fetch latest data and update this vector?');">
                                                <?php wp_nonce_field('wp_ai_rag_action'); ?>
                                                <input type="hidden" name="update_vector_id" value="<?php echo esc_attr($rec->id); ?>">
                                                <?php if($is_outdated): ?>
                                                    <button type="submit" name="update_single_vector" class="button" style="background:#d63638; color:#fff; border-color:#d63638; padding:0 8px; font-size:12px; margin-right:4px;">Update Now ⚠️</button>
                                                <?php else: ?>
                                                    <button type="submit" name="update_single_vector" class="button" style="color:#2271b1; border-color:#2271b1; padding:0 8px; font-size:12px; margin-right:4px;">Update</button>
                                                <?php endif; ?>
                                            </form>
                                            <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete this vector permanently?');">
                                                <?php wp_nonce_field('wp_ai_rag_action'); ?>
                                                <input type="hidden" name="vector_id" value="<?php echo esc_attr($rec->id); ?>">
                                                <button type="submit" name="delete_single_vector" class="button" style="color:#d63638; border-color:#d63638; padding:0 8px; font-size:12px;">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <style>
            .wp-ai-spinner { width: 24px; height: 24px; border: 3px solid #f3f3f3; border-top: 3px solid #2271b1; border-radius: 50%; animation: wpAiSpin 1s linear infinite; }
            @keyframes wpAiSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .select2-container--default .select2-selection--multiple { border-color: #8c8f94; border-radius: 4px; min-height: 35px; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.wp-ai-select2').select2({ placeholder: "Select items to sync..." });

            $('.wp-ai-strategy-select').on('change', function() {
                var target = $(this).data('target');
                if($(this).val() === 'specific') { $(target).show(); } else { $(target).hide(); }
            });

            $('#wp-ai-provider-select').on('change', function() {
                var val = $(this).val();
                $('.provider-field').hide();
                if(val === 'openai') { $('.openai-meta').show(); } else { $('.gemini-meta').show(); }
            });

            var pollInterval;
            function startLivePolling() {
                pollInterval = setInterval(function() {
                    $.post(ajaxurl, { action: 'wp_ai_get_vector_count' }, function(response) {
                        if (response.success) {
                            $('#live-vector-count').text(response.data.count);
                        }
                    });
                }, 2000);
            }

            $('#wp-ai-sync-form').on('submit', function() {
                $('#wp-ai-sync-form').hide();
                $('#wp-ai-processing-text').text('AI is generating vectors... Please wait...');
                $('#wp-ai-processing-status').css('display', 'flex');
                startLivePolling();
            });

            <?php if($auto_resume): ?>
                startLivePolling();
                setTimeout(function() { 
                    $('#wp-ai-processing-text').text('AI is generating vectors... Please wait...');
                    $('#wp-ai-sync-btn').click(); 
                }, 3000);
            <?php endif; ?>

            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.wp-ai-tab-content').hide();
                $($(this).attr('href')).show();
            });

            function filterTable() {
                var sourceVal = $('#filter-source').val();
                var searchVal = $('#filter-search').val().toLowerCase();
                var dateVal = $('#filter-date').val();

                $('#vector-table tbody tr').each(function() {
                    var $row = $(this);
                    var rowSource = $row.data('source');
                    var rowDate = $row.data('date');
                    var rowText = $row.text().toLowerCase();

                    var show = true;
                    if (sourceVal !== 'all' && rowSource !== sourceVal) show = false;
                    if (searchVal !== '' && rowText.indexOf(searchVal) === -1) show = false;
                    if (dateVal !== '' && rowDate !== dateVal) show = false;

                    if (show) $row.show(); else $row.hide();
                });
            }

            $('#filter-source, #filter-date').on('change', filterTable);
            $('#filter-search').on('keyup', filterTable);

            $('.sortable').on('click', function() {
                var type = $(this).data('type');
                var idx = $(this).index();
                var isAsc = $(this).hasClass('asc');
                
                $('.sortable').removeClass('asc desc');
                $(this).addClass(isAsc ? 'desc' : 'asc');

                var rows = $('#vector-table tbody tr').get();
                rows.sort(function(a, b) {
                    var valA = $(a).children('td').eq(idx).text().toUpperCase().replace('#', '');
                    var valB = $(b).children('td').eq(idx).text().toUpperCase().replace('#', '');

                    if (type === 'number') {
                        valA = parseInt(valA); valB = parseInt(valB);
                    }
                    if (valA < valB) return isAsc ? 1 : -1;
                    if (valA > valB) return isAsc ? -1 : 1;
                    return 0;
                });
                $.each(rows, function(index, row) { $('#vector-table tbody').append(row); });
            });
        });
        </script>
        <?php
    }
}
