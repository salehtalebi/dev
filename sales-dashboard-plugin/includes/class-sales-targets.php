<?php
/**
 * Sales Targets Management
 */
class Sales_Dashboard_Sales_Targets {

    private $table_name;
    private $jwt_auth;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sales_dashboard_targets';
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    private function get_jwt_auth() {
        if (!$this->jwt_auth) {
            $this->jwt_auth = new Sales_Dashboard_JWT_Auth();
        }
        return $this->jwt_auth;
    }

    private function get_permissions() {
        return $this->get_jwt_auth()->get_current_user_permissions();
    }

    /**
     * Create table if not exists (called from plugin activation)
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            manager_id VARCHAR(32) NULL,
            brand_slug VARCHAR(128) NULL,
            period_type VARCHAR(16) NOT NULL, -- week | month | year
            period_key VARCHAR(32) NOT NULL, -- e.g. 2025-W47 / 2025-11 / 2025
            target_amount DECIMAL(18,4) NOT NULL DEFAULT 0,
            currency VARCHAR(8) DEFAULT 'USD',
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_manager_period (manager_id, period_type, period_key),
            KEY idx_brand_period (brand_slug, period_type, period_key),
            KEY idx_period_type (period_type, period_key)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function register_routes() {
        register_rest_route('sales-dashboard/v1', '/targets', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'list_targets'),
                'permission_callback' => array($this, 'can_view_targets')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_target'),
                'permission_callback' => array($this, 'can_manage_targets')
            )
        ));

        register_rest_route('sales-dashboard/v1', '/targets/(?P<id>\d+)', array(
            array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_target'),
                'permission_callback' => array($this, 'can_manage_targets')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_target'),
                'permission_callback' => array($this, 'can_manage_targets')
            )
        ));

        register_rest_route('sales-dashboard/v1', '/targets/progress', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_progress'),
            'permission_callback' => array($this, 'can_view_targets')
        ));
    }

    public function can_view_targets() {
        $p = $this->get_permissions();
        return $p && ($p['is_super_admin'] || !empty($p['account_manager_id']));
    }

    public function can_manage_targets() {
        $p = $this->get_permissions();
        return $p && $p['is_super_admin'];
    }

    private function sanitize_period($period_type, $period_key) {
        $period_type = strtolower(sanitize_text_field($period_type));
        if (!in_array($period_type, array('week','month','year'), true)) {
            return array(null, null);
        }
        $period_key = sanitize_text_field($period_key);
        return array($period_type, $period_key);
    }

    public function create_target($request) {
        global $wpdb;
        $permissions = $this->get_permissions();
        if (!$permissions['is_super_admin']) {
            return new WP_Error('forbidden', 'Only super admin can create targets', array('status' => 403));
        }

        $manager_id = $request->get_param('manager_id'); // nullable for global
        $brand_slug = $request->get_param('brand'); // nullable
        list($period_type, $period_key) = $this->sanitize_period($request->get_param('period_type'), $request->get_param('period_key'));
        if (!$period_type || !$period_key) {
            return new WP_Error('invalid_period', 'Invalid period_type or period_key', array('status' => 400));
        }
        $target_amount = floatval($request->get_param('target_amount'));
        $currency = $request->get_param('currency') ? strtoupper(sanitize_text_field($request->get_param('currency'))) : 'USD';
        $notes = $request->get_param('notes');

        $wpdb->insert($this->table_name, array(
            'manager_id' => $manager_id ? sanitize_text_field($manager_id) : null,
            'brand_slug' => $brand_slug ? sanitize_title($brand_slug) : null,
            'period_type' => $period_type,
            'period_key' => $period_key,
            'target_amount' => $target_amount,
            'currency' => $currency,
            'notes' => $notes ? wp_kses_post($notes) : null,
            'created_by' => get_current_user_id()
        ));

        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error, array('status' => 500));
        }

        return array('id' => $wpdb->insert_id, 'success' => true);
    }

    public function update_target($request) {
        global $wpdb;
        $permissions = $this->get_permissions();
        if (!$permissions['is_super_admin']) {
            return new WP_Error('forbidden', 'Only super admin can update targets', array('status' => 403));
        }
        $id = intval($request['id']);
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id=%d", $id));
        if (!$existing) {
            return new WP_Error('not_found', 'Target not found', array('status' => 404));
        }
        $fields = array();
        if ($request->get_param('target_amount') !== null) {
            $fields['target_amount'] = floatval($request->get_param('target_amount'));
        }
        if ($request->get_param('notes') !== null) {
            $fields['notes'] = wp_kses_post($request->get_param('notes'));
        }
        if ($request->get_param('currency') !== null) {
            $fields['currency'] = strtoupper(sanitize_text_field($request->get_param('currency')));
        }
        if (empty($fields)) {
            return new WP_Error('no_changes', 'No fields to update', array('status' => 400));
        }
        $wpdb->update($this->table_name, $fields, array('id' => $id));
        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error, array('status' => 500));
        }
        return array('id' => $id, 'updated' => true);
    }

    public function delete_target($request) {
        global $wpdb;
        $permissions = $this->get_permissions();
        if (!$permissions['is_super_admin']) {
            return new WP_Error('forbidden', 'Only super admin can delete targets', array('status' => 403));
        }
        $id = intval($request['id']);
        $wpdb->delete($this->table_name, array('id' => $id));
        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error, array('status' => 500));
        }
        return array('id' => $id, 'deleted' => true);
    }

    public function list_targets($request) {
        global $wpdb;
        $permissions = $this->get_permissions();
        $manager_filter = $request->get_param('manager_id');
        $brand_filter = $request->get_param('brand');
        $period_type = $request->get_param('period_type');
        $period_key = $request->get_param('period_key');

        $where = array();
        $params = array();

        if ($manager_filter) {
            $where[] = 'manager_id = %s';
            $params[] = sanitize_text_field($manager_filter);
        } elseif (!$permissions['is_super_admin']) {
            // Force manager's own targets if not super admin
            $where[] = 'manager_id = %s';
            $params[] = $permissions['account_manager_id'];
        }
        if ($brand_filter) {
            $where[] = 'brand_slug = %s';
            $params[] = sanitize_title($brand_filter);
        }
        if ($period_type) {
            $where[] = 'period_type = %s';
            $params[] = sanitize_text_field($period_type);
        }
        if ($period_key) {
            $where[] = 'period_key = %s';
            $params[] = sanitize_text_field($period_key);
        }

        $sql = "SELECT * FROM {$this->table_name}";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC';

        if (!empty($params)) {
            $prepared = $wpdb->prepare($sql, $params);
        } else {
            $prepared = $sql;
        }

        $rows = $wpdb->get_results($prepared, ARRAY_A);
        return array('data' => $rows);
    }

    public function get_progress($request) {
        global $wpdb;
        $permissions = $this->get_permissions();
        $manager_id = $request->get_param('manager_id');
        if ($manager_id === '' || $manager_id === 'null' || $manager_id === 'undefined') {
            $manager_id = null;
        } elseif ($manager_id !== null) {
            $manager_id = sanitize_text_field($manager_id);
        }

        $brand = $request->get_param('brand');
        if ($brand === '' || $brand === 'null' || $brand === 'undefined') {
            $brand = null;
        } elseif ($brand !== null) {
            $brand = sanitize_title($brand);
        }
        $period_type = $request->get_param('period_type') ?: 'month';
        $period_key = $request->get_param('period_key');

        // Auto derive period_key if not provided
        if (!$period_key) {
            switch ($period_type) {
                case 'week':
                    $period_key = date('o-\WW'); // year-week
                    break;
                case 'month':
                    $period_key = date('Y-m');
                    break;
                case 'year':
                    $period_key = date('Y');
                    break;
            }
        }

        // If non-super admin, force manager scope
        if (!$permissions['is_super_admin']) {
            $manager_id = $permissions['account_manager_id'];
        }

        // Fetch target row (manager-specific else global aggregate row where manager_id IS NULL)
        $query_params = array($period_type, $period_key);
        $sql = "SELECT * FROM {$this->table_name} WHERE period_type=%s AND period_key=%s AND ";
        if ($manager_id) {
            $sql .= 'manager_id=%s';
            $query_params[] = $manager_id;
        } else {
            $sql .= 'manager_id IS NULL';
        }
        if ($brand) {
            $sql .= ' AND brand_slug=%s';
            $query_params[] = $brand;
        } else {
            $sql .= ' AND brand_slug IS NULL';
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';

        $target_row = $wpdb->get_row($wpdb->prepare($sql, $query_params), ARRAY_A);

        $target_amount = $target_row ? floatval($target_row['target_amount']) : 0.0;
        $currency = $target_row ? $target_row['currency'] : 'USD';

        // Compute actual revenue for given scope
        $actual_revenue = $this->compute_revenue($manager_id, $brand, $period_type, $period_key);
        $progress = $target_amount > 0 ? ($actual_revenue / $target_amount) * 100 : 0;

        return array(
            'period_type' => $period_type,
            'period_key' => $period_key,
            'manager_id' => $manager_id,
            'brand' => $brand,
            'target_amount' => $target_amount,
            'actual_revenue' => round($actual_revenue, 2),
            'currency' => $currency,
            'progress_percentage' => round($progress, 2)
        );
    }

    private function compute_revenue($manager_id, $brand, $period_type, $period_key) {
        // Build date boundaries
        $start = null; $end = null;
        if ($period_type === 'year') {
            $start = $period_key . '-01-01 00:00:00';
            $end = $period_key . '-12-31 23:59:59';
        } elseif ($period_type === 'month') {
            $start = $period_key . '-01 00:00:00';
            $end = date('Y-m-t 23:59:59', strtotime($start));
        } elseif ($period_type === 'week') {
            // period_key format expected: YYYY-Www (ISO week)
            $year = substr($period_key, 0, 4);
            $week = intval(substr($period_key, 6));
            $dto = new DateTime();
            $dto->setISODate(intval($year), $week);
            $start = $dto->format('Y-m-d') . ' 00:00:00';
            $dto->modify('+6 days');
            $end = $dto->format('Y-m-d') . ' 23:59:59';
        }

        $args = array(
            'status' => array('completed','processing'),
            'date_created' => $start . '...' . $end,
            'limit' => -1,
            'return' => 'ids'
        );

        // Potential brand filtering stub (assumes product taxonomy 'product_brand')
        // We'll filter after loading orders if brand provided.
        $order_ids = wc_get_orders($args);
        if (!is_array($order_ids) || empty($order_ids)) {
            return 0.0;
        }

        // Manager filtering reuse (similar logic as analytics)
        if ($manager_id) {
            $order_ids = $this->filter_orders_by_manager($order_ids, $manager_id);
        }

        $total = 0.0;
        foreach ($order_ids as $oid) {
            $order = wc_get_order($oid);
            if (!$order) continue;
            if ($brand) {
                if (!$this->order_has_brand($order, $brand)) {
                    continue;
                }
            }
            $total += floatval($order->get_total());
        }
        return $total;
    }

    private function filter_orders_by_manager($order_ids, $manager_id) {
        if (empty($order_ids) || empty($manager_id)) {
            return array();
        }
        $filtered = array();
        foreach ($order_ids as $order_id) {
            // Order-level override first
            $order_manager = get_post_meta($order_id, '_order_account_manager_id', true);
            if ($order_manager !== '') {
                // Skip house assignments entirely for manager targets
                if ($order_manager === 'house') {
                    continue;
                }
                if ($order_manager === $manager_id) {
                    $filtered[] = $order_id;
                }
                // If override exists and not matching, never count for customer manager
                continue;
            }
            $order = wc_get_order($order_id);
            if (!$order) continue;
            $cid = $order->get_customer_id();
            if ($cid) {
                $cust_manager = get_user_meta($cid, '_account_manager_id', true);
                if ($cust_manager && $cust_manager !== 'house' && $cust_manager === $manager_id) {
                    $filtered[] = $order_id;
                }
            }
        }
        return $filtered;
    }

    private function order_has_brand($order, $brand_slug) {
        if (!$brand_slug) return true; // no brand filter
        $brand_slug = sanitize_title($brand_slug);
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            // Use WooCommerce product categories as brand dimension
            $terms = get_the_terms($product->get_id(), 'product_cat');
            if (is_array($terms)) {
                foreach ($terms as $t) {
                    if ($t->slug === $brand_slug) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
