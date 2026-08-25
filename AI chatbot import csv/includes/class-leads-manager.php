<?php
/**
 * Leads Manager - Flat Table Per Form
 * 
 * Logic:
 * - Table name = wp_ai_leads_{form_id}  (form_id kabhi nahi badlti)
 * - Form rename ho → sirf tab title badlta hai, table same
 * - Form delete ho → table preserved, tab RED (orphan) + Delete Table button
 * - Naya form = naya tab sirf tab ban jata hai jab table bhi ho
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Leads_Manager {

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_leads_submenu' ) );
        add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
        add_action( 'admin_init', array( $this, 'handle_delete_table' ) );
    }

    public function add_leads_submenu() {
        add_submenu_page(
            'wp-ai-chatbot', 'Chatbot Leads', 'View Leads',
            'manage_options', 'wp-ai-chatbot-leads',
            array( $this, 'display_leads_page' )
        );
    }

    /**
     * DB se saare wp_ai_leads_* tables fetch karo
     * Har table ke liye check karo: kya matching form exist karta hai?
     * Returns array of:
     *   [ table, form_id, form_title, is_orphan (bool) ]
     */
    private function get_all_lead_tables() {
        global $wpdb;
        $prefix = $wpdb->prefix . 'ai_leads_';
        $tables = $wpdb->get_col( "SHOW TABLES LIKE '{$prefix}%'" );

        // form_id → title map (saved forms se)
        $saved_forms = get_option( 'wp_ai_chatbot_multi_forms', array() );
        $form_map    = array(); // form_id => title
        foreach ( $saved_forms as $f ) {
            $form_map[ sanitize_key( $f['id'] ) ] = $f['title'];
        }

        $result = array();
        foreach ( $tables as $tbl ) {
            $form_id   = str_replace( $prefix, '', $tbl );
            $is_orphan = ! isset( $form_map[ $form_id ] );
            // Orphan hone pe table slug se title banao (readable)
            $title = $is_orphan
                ? ucwords( str_replace( '_', ' ', $form_id ) ) . ' [Deleted Form]'
                : $form_map[ $form_id ];

            $result[] = array(
                'table'      => $tbl,
                'form_id'    => $form_id,
                'form_title' => $title,
                'is_orphan'  => $is_orphan,
            );
        }
        return $result;
    }

    // Table delete karo (orphan tabs ke liye)
    public function handle_delete_table() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wp-ai-chatbot-leads' ) return;
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'delete_table' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_lead_table' ) ) return;

        $form_id = isset( $_GET['form_tab'] ) ? sanitize_key( $_GET['form_tab'] ) : '';
        if ( empty( $form_id ) ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'ai_leads_' . $form_id;
        $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

        wp_redirect( admin_url( 'admin.php?page=wp-ai-chatbot-leads&deleted=1' ) );
        exit;
    }

    // CSV Export
    public function handle_csv_export() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wp-ai-chatbot-leads' ) return;
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'export_csv' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'export_leads_csv' ) ) return;

        $form_id = isset( $_GET['form_tab'] ) ? sanitize_key( $_GET['form_tab'] ) : '';
        if ( empty( $form_id ) ) return;

        global $wpdb;
        $table = $wpdb->prefix . 'ai_leads_' . $form_id;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) return;

        $rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY created_at DESC", ARRAY_A );
        if ( empty( $rows ) ) return;

        $skip_cols  = array( 'id', 'created_at' );
        $field_cols = array_diff( array_keys( $rows[0] ), $skip_cols );

        // Title for filename
        $saved_forms = get_option( 'wp_ai_chatbot_multi_forms', array() );
        $file_title  = $form_id;
        foreach ( $saved_forms as $f ) {
            if ( sanitize_key( $f['id'] ) === $form_id ) { $file_title = $f['title']; break; }
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=leads-' . sanitize_title( $file_title ) . '-' . date('Y-m-d') . '.csv' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );
        fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) );

        fputcsv( $out, array_merge(
            array( 'ID', 'Date & Time' ),
            array_map( function($c){ return ucfirst( str_replace('_',' ',$c) ); }, $field_cols )
        ) );

        foreach ( $rows as $row ) {
            $csv = array( '#' . $row['id'], $row['created_at'] );
            foreach ( $field_cols as $c ) $csv[] = isset( $row[$c] ) ? $row[$c] : '';
            fputcsv( $out, $csv );
        }
        fclose( $out );
        exit;
    }

    public function display_leads_page() {
        global $wpdb;

        // Success notices
        if ( isset( $_GET['deleted'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Table deleted successfully.</p></div>';
        }

        $all_tables     = $this->get_all_lead_tables();
        $active_form_id = isset( $_GET['form_tab'] ) ? sanitize_key( $_GET['form_tab'] ) : '';
        if ( empty( $active_form_id ) && ! empty( $all_tables ) ) {
            $active_form_id = $all_tables[0]['form_id'];
        }

        // Active table info
        $active_info = array();
        foreach ( $all_tables as $t ) {
            if ( $t['form_id'] === $active_form_id ) { $active_info = $t; break; }
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Chatbot Leads</h1>

            <?php if ( empty( $all_tables ) ) : ?>
                <div style="margin-top:30px; padding:40px; text-align:center; background:#fff; border:1px solid #ddd; border-radius:8px;">
                    <p style="color:#888; font-size:15px; margin:0;">
                        No leads yet. Save a form in <strong>Form Builder</strong> first, then users can submit data.
                    </p>
                </div>
                </div><?php return; endif; ?>

            <!-- TABS -->
            <div style="margin-top:20px; border-bottom:1px solid #ccd0d4; display:flex; flex-wrap:wrap; gap:4px;">
                <?php foreach ( $all_tables as $t ) :
                    $is_active  = ( $active_form_id === $t['form_id'] );
                    $tab_url    = admin_url( 'admin.php?page=wp-ai-chatbot-leads&form_tab=' . $t['form_id'] );
                    $orphan     = $t['is_orphan'];

                    // Styling
                    $tab_bg     = $is_active ? '#fff' : ( $orphan ? '#fff5f5' : '#f0f0f0' );
                    $tab_border = $is_active ? '2px solid ' . ( $orphan ? '#d63638' : '#2271b1' ) . '; border-bottom:1px solid #fff' : '1px solid #ccd0d4; border-bottom:none';
                    $tab_color  = $orphan ? '#d63638' : ( $is_active ? '#2271b1' : '#444' );
                    $tab_weight = $is_active ? '700' : '400';
                ?>
                    <a href="<?php echo esc_url( $tab_url ); ?>"
                       style="display:inline-flex; align-items:center; gap:6px; padding:8px 16px; background:<?php echo $tab_bg; ?>; border:<?php echo $tab_border; ?>; color:<?php echo $tab_color; ?>; font-weight:<?php echo $tab_weight; ?>; font-size:13px; text-decoration:none; border-radius:4px 4px 0 0; position:relative; top:1px;">
                        <?php if ( $orphan ) : ?><span title="Form deleted — data still exists">⚠</span><?php endif; ?>
                        <?php echo esc_html( $t['form_title'] ); ?>
                        <?php
                        // Row count badge
                        $cnt = $wpdb->get_var( "SELECT COUNT(*) FROM `{$t['table']}`" );
                        if ( $cnt > 0 ) {
                            echo '<span style="background:' . ($orphan?'#d63638':'#2271b1') . '; color:#fff; border-radius:10px; padding:1px 7px; font-size:10px; font-weight:700;">' . $cnt . '</span>';
                        }
                        ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- CONTENT PANEL -->
            <div style="background:#fff; border:1px solid #ccd0d4; border-top:none; padding:16px 20px;">

            <?php if ( empty( $active_info ) ) : ?>
                <p style="color:#888;">Select a tab above.</p>
            <?php else :
                $tbl        = $active_info['table'];
                $is_orphan  = $active_info['is_orphan'];
                $rows       = $wpdb->get_results( "SELECT * FROM `{$tbl}` ORDER BY created_at DESC", ARRAY_A );
                $field_cols = array();
                if ( ! empty( $rows ) ) {
                    $field_cols = array_diff( array_keys( $rows[0] ), array( 'id', 'created_at' ) );
                }

                $export_url = wp_nonce_url(
                    admin_url( 'admin.php?page=wp-ai-chatbot-leads&action=export_csv&form_tab=' . $active_form_id ),
                    'export_leads_csv'
                );
                $delete_url = wp_nonce_url(
                    admin_url( 'admin.php?page=wp-ai-chatbot-leads&action=delete_table&form_tab=' . $active_form_id ),
                    'delete_lead_table'
                );
            ?>

                <!-- Orphan Warning Bar -->
                <?php if ( $is_orphan ) : ?>
                <div style="background:#fff5f5; border:1px solid #f5c6cb; border-radius:6px; padding:10px 15px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:#d63638; font-weight:600;">
                        ⚠ This form has been deleted. Data is preserved in table <code><?php echo esc_html($tbl); ?></code>
                    </span>
                    <a href="<?php echo esc_url($delete_url); ?>"
                       onclick="return confirm('Are you sure? This will permanently delete all <?php echo count($rows); ?> leads in this table. This cannot be undone!')"
                       style="background:#d63638; color:#fff; padding:5px 14px; border-radius:4px; text-decoration:none; font-size:12px; font-weight:600;">
                        🗑 Delete This Table
                    </a>
                </div>
                <?php endif; ?>

                <!-- Header row -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <p style="margin:0; color:#555; font-size:13px;">
                        Table: <code style="font-size:11px;"><?php echo esc_html($tbl); ?></code>
                        &nbsp;|&nbsp; Total: <strong><?php echo count($rows); ?></strong>
                    </p>
                    <?php if ( ! empty( $rows ) ) : ?>
                    <a href="<?php echo esc_url($export_url); ?>"
                       style="background:#2271b1; color:#fff; padding:6px 16px; text-decoration:none; border-radius:4px; font-weight:600; font-size:13px;">
                        ⬇ Export CSV
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Data table -->
                <?php if ( ! empty( $rows ) ) : ?>
                <div style="overflow-x:auto;">
                <table class="wp-list-table widefat striped" style="border-collapse:collapse;">
                    <thead style="background:#f6f7f7;">
                        <tr>
                            <th style="padding:10px 12px; border:1px solid #ddd; width:45px;">#ID</th>
                            <?php foreach ( $field_cols as $col ) : ?>
                                <th style="padding:10px 12px; border:1px solid #ddd;">
                                    <?php echo esc_html( ucfirst( str_replace('_',' ',$col) ) ); ?>
                                </th>
                            <?php endforeach; ?>
                            <th style="padding:10px 12px; border:1px solid #ddd; width:150px;">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <td style="padding:9px 12px; border:1px solid #eee; font-weight:600;">#<?php echo esc_html($row['id']); ?></td>
                            <?php foreach ( $field_cols as $col ) :
                                $val   = isset( $row[$col] ) ? $row[$col] : '';
                                $parts = ( strpos($val,',') !== false )
                                    ? array_filter( array_map('trim', explode(',', $val)) )
                                    : array();
                                echo '<td style="padding:9px 12px; border:1px solid #eee;">';
                                if ( count($parts) > 1 ) {
                                    foreach ($parts as $p) {
                                        echo '<span style="display:inline-block;background:#e8f0fe;color:#1a73e8;padding:2px 9px;border-radius:12px;font-size:11px;margin:2px 2px 2px 0;">' . esc_html($p) . '</span>';
                                    }
                                } elseif ( $val !== '' ) {
                                    echo esc_html($val);
                                } else {
                                    echo '<span style="color:#bbb;">—</span>';
                                }
                                echo '</td>';
                            endforeach; ?>
                            <td style="padding:9px 12px; border:1px solid #eee; color:#888; font-size:12px; white-space:nowrap;">
                                <?php echo esc_html( date('d M Y, h:i A', strtotime($row['created_at'])) ); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php else : ?>
                <div style="padding:30px; text-align:center; background:#f9f9f9; border-radius:6px;">
                    <p style="color:#888; margin:0;">No leads yet for <strong><?php echo esc_html($active_info['form_title']); ?></strong>.</p>
                </div>
                <?php endif; ?>

            <?php endif; ?>
            </div><!-- /panel -->
        </div><!-- /wrap -->
        <?php
    }
}
