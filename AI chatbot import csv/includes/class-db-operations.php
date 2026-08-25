<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WP_AI_Chatbot_DB_Operations {
    private $table_name;
    private $option_fields;
    private $kb_id;

    public function __construct($kb_id) {
        global $wpdb;
        $this->kb_id = sanitize_key($kb_id);
        $this->table_name = $wpdb->prefix . 'ai_kb_' . $this->kb_id;
        $this->option_fields = 'wp_ai_kb_fields_' . $this->kb_id;
    }

    public function get_all_data() {
        global $wpdb;
        return $wpdb->get_results("SHOW TABLES LIKE '{$this->table_name}'") ? $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY id DESC", ARRAY_A) : [];
    }

    public function sync_schema() {
        global $wpdb;
        if ( empty($_POST['fields']) ) {
            $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
            delete_option($this->option_fields);
            $this->redirect("Table dropped!");
        }
        $new = array_map('sanitize_key', $_POST['fields']);
        update_option($this->option_fields, $new);
        
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$this->table_name} (id mediumint(9) NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)) ENGINE=InnoDB;");
        
        $old = array_diff($wpdb->get_col("DESCRIBE {$this->table_name}", 0), ['id']);
        foreach($new as $f) if(!in_array($f, $old)) $wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN `$f` VARCHAR(500) DEFAULT NULL");
        foreach($old as $f) if(!in_array($f, $new)) $wpdb->query("ALTER TABLE {$this->table_name} DROP COLUMN `$f`");
        
        $this->redirect("Schema synced successfully!");
    }

    public function handle_csv_upload() {
        global $wpdb;
        if (($h = fopen($_FILES['product_csv']['tmp_name'], 'r')) !== FALSE) {
            $headers = array_map('trim', fgetcsv($h));
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            
            $imported = 0; $duplicates = 0;
            while (($data = fgetcsv($h)) !== FALSE) {
                if (count($headers) !== count($data)) continue;
                
                $row = array_map('sanitize_text_field', array_combine($headers, $data));
                
                $where = []; $vals = [];
                foreach ($row as $k => $v) { $where[] = "`$k` = %s"; $vals[] = $v; }
                if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE " . implode(' AND ', $where), $vals)) > 0) {
                    $duplicates++; continue;
                }
                $wpdb->insert($this->table_name, $row);
                $imported++;
            }
            fclose($h);
            $this->redirect("Imported: $imported, Skipped Duplicates: $duplicates");
        }
        $this->redirect("Import failed!", "error");
    }

    public function update_row() {
        global $wpdb;
        $id = intval($_POST['update_row']);
        $wpdb->update($this->table_name, array_map('sanitize_text_field', $_POST['row_update'][$id]), ['id' => $id]);
        $this->redirect("Row Updated!");
    }

    public function add_new_row() {
        global $wpdb;
        if (!empty($_POST['new_row'])) {
            $data = array_map('sanitize_text_field', $_POST['new_row']);
            
            $is_empty = true;
            foreach ($data as $val) {
                if (trim($val) !== '') {
                    $is_empty = false;
                    break;
                }
            }
            
            if (!$is_empty) {
                $wpdb->insert($this->table_name, $data);
                $this->redirect("New line added successfully!");
            } else {
                $this->redirect("Please fill at least one field to add a new line.", "error");
            }
        }
        $this->redirect("Failed to add line.", "error");
    }

    // NAYA IZAFA: Single Row Delete Function
    public function delete_single_row() {
        global $wpdb;
        if ( !empty($_POST['delete_single_row']) ) {
            $id = intval($_POST['delete_single_row']);
            $wpdb->delete($this->table_name, ['id' => $id]);
            $this->redirect("Row deleted successfully!");
        }
        $this->redirect("Failed to delete row.", "error");
    }

    public function delete_rows() {
        global $wpdb;
        if ( !empty($_POST['bulk_ids']) ) {
            $ids = implode(',', array_map('intval', $_POST['bulk_ids']));
            $wpdb->query("DELETE FROM {$this->table_name} WHERE id IN ($ids)");
            $this->redirect("Selected rows deleted!");
        }
        $this->redirect("Select rows first!", "error");
    }

    private function redirect($msg, $status = 'success') {
        echo '<script>window.location.href = "' . admin_url('admin.php?page=wp-ai-chatbot-db&kb_id='.$this->kb_id.'&message='.urlencode($msg).'&status='.$status) . '";</script>';
        exit;
    }
}