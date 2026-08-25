<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_Dynamic_DB {
    private $kb_option = 'wp_ai_chatbot_knowledge_bases';

    public function __construct() {}

    public function register_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }

    public function add_admin_menu() {
        add_submenu_page( 'wp-ai-chatbot', 'DB Manager', 'Knowledge Bases', 'manage_options', 'wp-ai-chatbot-db', array( $this, 'display_page' ) );
    }

    public function admin_notices() {
        if ( isset( $_GET['message'] ) ) {
            $class = ( isset( $_GET['status'] ) && $_GET['status'] == 'error' ) ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . sanitize_text_field($_GET['message']) . '</p></div>';
        }
    }

    public function display_page() {
        if ( isset($_POST['create_new_kb']) && !empty($_POST['kb_name']) ) {
            $kbs = get_option($this->kb_option, array());
            $kb_name = sanitize_text_field($_POST['kb_name']);
            $kb_id = sanitize_key(strtolower(str_replace(' ', '_', $kb_name))) . '_' . substr(md5(time()), 0, 4);
            
            $kbs[$kb_id] = $kb_name;
            update_option($this->kb_option, $kbs);
            echo '<script>window.location.href = "' . admin_url('admin.php?page=wp-ai-chatbot-db&kb_id='.$kb_id.'&message=Knowledge Base Created!') . '";</script>';
            exit;
        }

        if ( isset($_POST['delete_kb']) && !empty($_POST['delete_kb_id']) ) {
            $kbs = get_option($this->kb_option, array());
            $del_id = sanitize_key($_POST['delete_kb_id']);
            if (isset($kbs[$del_id])) {
                unset($kbs[$del_id]);
                update_option($this->kb_option, $kbs);
                global $wpdb;
                $wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "ai_kb_" . $del_id);
                delete_option('wp_ai_kb_fields_' . $del_id);
                echo '<script>window.location.href = "' . admin_url('admin.php?page=wp-ai-chatbot-db&message=Knowledge Base Deleted!') . '";</script>';
                exit;
            }
        }

        $all_kbs = get_option($this->kb_option, array());
        $active_kb_id = isset($_GET['kb_id']) ? sanitize_key($_GET['kb_id']) : (empty($all_kbs) ? '' : array_key_first($all_kbs));

        ?>
        <div class="wrap">
            <h1>Multi-Knowledge Base Manager</h1>
            <?php settings_errors(); ?>
            <div style="background:#fff; padding:15px; border:1px solid #ccd0d4; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <strong>Select Knowledge Base: </strong>
                    <?php if (!empty($all_kbs)): ?>
                        <select onchange="window.location.href='?page=wp-ai-chatbot-db&kb_id='+this.value">
                            <?php foreach($all_kbs as $id => $name): ?>
                                <option value="<?php echo esc_attr($id); ?>" <?php selected($active_kb_id, $id); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <span style="color:red;">No Knowledge Base found. Create one first!</span>
                    <?php endif; ?>
                </div>

                <form method="post" style="display:inline-block;">
                    <input type="text" name="kb_name" placeholder="e.g. Shipping Policies" required />
                    <button type="submit" name="create_new_kb" class="button button-primary">+ Create New KB</button>
                </form>
            </div>

            <?php 
            if (!empty($active_kb_id)): 
                require_once plugin_dir_path(__FILE__) . 'class-db-operations.php';
                $db_ops = new WP_AI_Chatbot_DB_Operations($active_kb_id);
                
                if ( isset( $_POST['save_schema'] ) ) $db_ops->sync_schema();
                if ( isset( $_POST['upload_csv'] ) ) $db_ops->handle_csv_upload();
                if ( isset( $_POST['update_row'] ) ) $db_ops->update_row(); 
                if ( isset( $_POST['delete_action'] ) ) $db_ops->delete_rows();
                if ( isset( $_POST['add_new_row'] ) ) $db_ops->add_new_row();
                // NAYA IZAFA: Trigger single row delete logic
                if ( isset( $_POST['delete_single_row'] ) ) $db_ops->delete_single_row();

                $fields = get_option('wp_ai_kb_fields_' . $active_kb_id, []);
                $data = $db_ops->get_all_data();
            ?>
            
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2>Managing: <span style="color:#2271b1;"><?php echo esc_html($all_kbs[$active_kb_id]); ?></span></h2>
                <form method="post" onsubmit="return confirm('WARNING: This will permanently delete this Knowledge Base and all its data. Are you sure?');">
                    <input type="hidden" name="delete_kb_id" value="<?php echo esc_attr($active_kb_id); ?>">
                    <button type="submit" name="delete_kb" class="button" style="color:red; border-color:red;">Delete This KB</button>
                </form>
            </div>
            <hr>

            <form method="post">
                <h3>1. Manage Table Fields (Schema)</h3>
                <table class="widefat" style="width: 500px;">
                    <tbody id="fields-body">
                        <?php foreach($fields as $f) : ?>
                        <tr><td><input type="text" name="fields[]" value="<?php echo esc_attr($f); ?>" required /></td><td><button type="button" class="button remove-field">Delete</button></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="button" id="add-field" class="button">Add Field</button> <input type="submit" name="save_schema" class="button button-primary" value="Save Schema" /></p>
            </form>
            <hr>

            <?php if (!empty($fields)): ?>
            <form method="post" enctype="multipart/form-data">
                <h3>2. Upload CSV Data</h3>
                <input type="file" name="product_csv" accept=".csv" required />
                <input type="submit" name="upload_csv" class="button" value="Upload & Import" />
            </form>
            <hr>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h3 style="margin:0;">3. Manage Data</h3>
                <input type="text" id="kb-search" placeholder="Search records..." style="width:300px; border-radius:4px; border:1px solid #8c8f94; padding:0 10px; height:32px;">
            </div>
            
            <form method="post">
                <table class="wp-list-table widefat striped" id="kb-data-table" style="overflow-x:auto; display:block;">
                    <thead><tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <?php foreach($fields as $index => $f) echo "<th class='kb-sortable' data-col='{$index}' style='cursor:pointer; user-select:none; white-space:nowrap;' title='Click to sort'>".ucfirst(str_replace('_', ' ', $f))." ↕</th>"; ?>
                        <th>Actions</th>
                    </tr></thead>
                    <tbody>
                        <tr style="background:#e5f5fa; border-bottom: 2px solid #00a0d2;">
                            <td style="text-align:center; font-weight:bold; color:#00a0d2; font-size:18px;">+</td>
                            <?php foreach($fields as $f) : ?>
                                <td><input type="text" name="new_row[<?php echo $f; ?>]" placeholder="Add <?php echo esc_attr($f); ?>..." style="border:1px solid #00a0d2; width:100%;" /></td>
                            <?php endforeach; ?>
                            <td><button type="submit" name="add_new_row" value="1" class="button button-primary" style="width:100%;">+ Add Line</button></td>
                        </tr>

                        <?php foreach($data as $row) : ?>
                        <tr>
                            <td><input type="checkbox" name="bulk_ids[]" value="<?php echo $row['id']; ?>"></td>
                            <?php foreach($fields as $f) : ?>
                                <td><input type="text" name="row_update[<?php echo $row['id']; ?>][<?php echo $f; ?>]" value="<?php echo esc_attr($row[$f] ?? ''); ?>" /></td>
                            <?php endforeach; ?>
                            <!-- 🚀 NAYA IZAFA: Har row mein Update ke sath red Delete button laga diya gaya hai -->
                            <td style="white-space: nowrap;">
                                <button type="submit" name="update_row" value="<?php echo $row['id']; ?>" class="button">Update</button>
                                <button type="submit" name="delete_single_row" value="<?php echo $row['id']; ?>" class="button" style="color:#d63638; border-color:#d63638;" onclick="return confirm('Are you sure you want to delete this specific row?');">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <button type="submit" name="delete_action" class="button button-danger">Delete Selected (Bulk)</button>
                </p>
            </form>
            <?php else: ?>
                <p style="color:#d63638;"><strong>Notice:</strong> Please add fields and save the schema first before uploading CSV or adding data.</p>
            <?php endif; ?>

            <?php endif; ?> </div>

        <script>
            document.getElementById('add-field').onclick = () => { const tr = document.createElement('tr'); tr.innerHTML = '<td><input type="text" name="fields[]" placeholder="e.g. product_name" required /></td><td><button type="button" class="button remove-field">Delete</button></td>'; document.getElementById('fields-body').appendChild(tr); };
            document.addEventListener('click', e => { if(e.target.classList.contains('remove-field')) e.target.closest('tr').remove(); });
            const selectAllBtn = document.getElementById('select-all');
            if(selectAllBtn) selectAllBtn.onclick = function() { document.querySelectorAll('input[name="bulk_ids[]"]').forEach(cb => cb.checked = this.checked); };

            const searchInput = document.getElementById('kb-search');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    let filter = this.value.toLowerCase();
                    let rows = document.querySelectorAll('#kb-data-table tbody tr');
                    
                    rows.forEach((row, index) => {
                        if(index === 0) return; 
                        
                        let inputs = row.querySelectorAll('input[type="text"]');
                        let rowText = '';
                        inputs.forEach(input => rowText += input.value.toLowerCase() + ' ');
                        
                        if (rowText.includes(filter)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            document.querySelectorAll('.kb-sortable').forEach(th => {
                th.addEventListener('click', function() {
                    const table = document.getElementById('kb-data-table');
                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    
                    const addRow = rows.shift(); 
                    
                    const colIndex = parseInt(this.getAttribute('data-col')) + 1; 
                    const isAsc = this.classList.contains('asc');
                    
                    document.querySelectorAll('.kb-sortable').forEach(header => header.classList.remove('asc', 'desc'));
                    this.classList.add(isAsc ? 'desc' : 'asc');
                    
                    rows.sort((a, b) => {
                        let aInput = a.querySelectorAll('td')[colIndex].querySelector('input');
                        let bInput = b.querySelectorAll('td')[colIndex].querySelector('input');
                        
                        let aVal = aInput ? aInput.value.toLowerCase() : '';
                        let bVal = bInput ? bInput.value.toLowerCase() : '';
                        
                        if (!isNaN(aVal) && !isNaN(bVal) && aVal !== '' && bVal !== '') {
                            return isAsc ? bVal - aVal : aVal - bVal;
                        }
                        
                        if (aVal < bVal) return isAsc ? 1 : -1;
                        if (aVal > bVal) return isAsc ? -1 : 1;
                        return 0;
                    });
                    
                    tbody.innerHTML = '';
                    tbody.appendChild(addRow); 
                    rows.forEach(row => tbody.appendChild(row));
                });
            });
        </script>
        <?php
    }
}