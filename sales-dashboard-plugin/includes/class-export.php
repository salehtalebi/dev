<?php

/**
 * Export Class
 */
class Sales_Dashboard_Export {
    
    private $managers;
    private $jwt_auth;
    
    public function __construct() {
        $this->managers = array(
            'house' => 'House',
            '1465'  => 'Ina Istok',
            '845'   => 'Pina Lee',
            '1886'  => 'Vidika Shenton',
            '2532'  => 'Sarah Hearn',
            '2533'  => 'Jonathon Regan',
        );
        
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Get JWT auth instance
     */
    private function get_jwt_auth() {
        if (!$this->jwt_auth) {
            $this->jwt_auth = new Sales_Dashboard_JWT_Auth();
        }
        return $this->jwt_auth;
    }
    
    /**
     * Get current user permissions
     */
    private function get_user_permissions() {
        $jwt_auth = $this->get_jwt_auth();
        return $jwt_auth->get_current_user_permissions();
    }
    
    /**
     * Filter orders by manager
     * Excludes 'house' customers for non-super-admins
     */
    private function filter_orders_by_manager($order_ids, $manager_id) {
        if (empty($order_ids) || empty($manager_id)) {
            return $order_ids;
        }
        
        $filtered = array();
        
        foreach ($order_ids as $order_id) {
            // Check order-level manager first
            $order_manager = get_post_meta($order_id, '_order_account_manager_id', true);
            
            if ($order_manager) {
                // Skip 'house' customers
                if ($order_manager === 'house') {
                    continue;
                }
                
                if ($order_manager === $manager_id) {
                    $filtered[] = $order_id;
                }
                continue;
            }
            
            // Check customer's manager
            $order = wc_get_order($order_id);
            if ($order) {
                // Skip refund objects to avoid calling customer helpers on them
                if (is_a($order, 'WC_Order_Refund') || is_a($order, '\\Automattic\\WooCommerce\\Admin\\Overrides\\OrderRefund')) {
                    continue;
                }
                $customer_id = $order->get_customer_id();
                if ($customer_id) {
                    $customer_manager = get_user_meta($customer_id, '_account_manager_id', true);
                    
                    // Skip 'house' customers
                    if ($customer_manager === 'house') {
                        continue;
                    }
                    
                    if ($customer_manager === $manager_id) {
                        $filtered[] = $order_id;
                    }
                }
            }
        }
        
        return $filtered;
    }

    /**
     * Get order IDs explicitly tagged with order-level manager assignments
     */
    private function get_orders_with_explicit_manager($manager_id, $base_args = array()) {
        if (empty($manager_id)) {
            return array();
        }

        $query_args = $base_args;
        $query_args['limit'] = -1;
        $query_args['return'] = 'ids';
        $query_args['type'] = 'shop_order';

        if (isset($query_args['customer'])) {
            unset($query_args['customer']);
        }

        $clause = array(
            'key' => '_order_account_manager_id',
            'value' => $manager_id,
            'compare' => '='
        );

        if (isset($query_args['meta_query'])) {
            $meta_query = $query_args['meta_query'];
            if (!isset($meta_query['relation'])) {
                $meta_query = array_merge(array('relation' => 'AND'), $meta_query);
            }
            $meta_query[] = $clause;
            $query_args['meta_query'] = $meta_query;
        } else {
            $query_args['meta_query'] = array($clause);
        }

        $order_query = new WC_Order_Query($query_args);
        $order_ids = $order_query->get_orders();

        return is_array($order_ids) ? array_map('intval', $order_ids) : array();
    }
    
    public function register_routes() {
        // Export orders
        register_rest_route('sales-dashboard/v1', '/export/orders', array(
            'methods' => 'POST',
            'callback' => array($this, 'export_orders'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Export customers
        register_rest_route('sales-dashboard/v1', '/export/customers', array(
            'methods' => 'POST',
            'callback' => array($this, 'export_customers'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Export analytics report
        register_rest_route('sales-dashboard/v1', '/export/analytics', array(
            'methods' => 'POST',
            'callback' => array($this, 'export_analytics'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    /**
     * Export orders to CSV
     */
    public function export_orders($request) {
        try {
            // Check if WooCommerce is active
            if (!class_exists('WooCommerce') || !class_exists('WC_Order_Query')) {
                return new WP_Error('woocommerce_not_active', 'WooCommerce is not active', array('status' => 500));
            }
            
            // Get user permissions
            $permissions = $this->get_user_permissions();
            $user_manager_id = null;
            
            // If account manager, restrict to their own data
            if (!$permissions['is_super_admin'] && $permissions['account_manager_id']) {
                $user_manager_id = $permissions['account_manager_id'];
            }

            $requested_manager_id = null;
            $format = $request->get_param('format') ?: 'csv';
            // Get all orders for export (no limit)
            $limit = $request->get_param('limit') ?: -1;
            if ($limit > 0) {
                $limit = min($limit, 10000); // Max 10000 if specified
            }
            
            // Build WC_Order_Query arguments similar to get_enhanced_orders
            $args = array(
                'limit' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'status' => 'any', // Get all statuses, filter later if needed
                'return' => 'ids',
                'type' => 'shop_order'
            );
            
            // Apply filters from request
            if ($request->get_param('status') && $request->get_param('status') !== '' && $request->get_param('status') !== 'null') {
                $args['status'] = $request->get_param('status');
            }
            
            $search_term = null;
            if ($request->get_param('search') && $request->get_param('search') !== '' && $request->get_param('search') !== 'null') {
                $search = trim($request->get_param('search'));
                if ($search !== '' && $search !== 'null') {
                    $args['search'] = $search;
                    $search_term = strtolower(sanitize_text_field($search));
                }
            }
            
            // Handle account manager filter
            if ($request->get_param('accountManager') && $request->get_param('accountManager') !== '' && $request->get_param('accountManager') !== 'null') {
                $requested_manager_id = sanitize_text_field($request->get_param('accountManager'));
                $customers = get_users(array(
                    'meta_key' => '_account_manager_id',
                    'meta_value' => $requested_manager_id,
                    'fields' => 'ID',
                    'number' => -1
                ));
                
                if (!empty($customers)) {
                    $args['customer'] = $customers;
                }
                // If no customers are linked we still continue, order-level assignments may exist
            }
            
            $date_from_param = $request->get_param('date_from');
            $date_to_param = $request->get_param('date_to');
            $has_custom_dates = ($date_from_param && $date_from_param !== '' && $date_from_param !== 'null') ||
                                ($date_to_param && $date_to_param !== '' && $date_to_param !== 'null');
            $date_range_param = $request->get_param('dateRange');

            if ($has_custom_dates) {
                $date_from_valid = $date_from_param && $date_from_param !== '' && $date_from_param !== 'null';
                $date_to_valid = $date_to_param && $date_to_param !== '' && $date_to_param !== 'null';

                if ($date_from_valid && $date_to_valid) {
                    $args['date_created'] = $date_from_param . '...' . $date_to_param . ' 23:59:59';
                } elseif ($date_from_valid) {
                    $args['date_created'] = '>=' . $date_from_param;
                } elseif ($date_to_valid) {
                    $args['date_created'] = '<=' . $date_to_param . ' 23:59:59';
                }
            } elseif ($date_range_param && $date_range_param !== '' && $date_range_param !== 'null' && $date_range_param !== 'custom') {
                $date_args = $this->get_date_range_args($date_range_param);
                if ($date_args) {
                    $args = array_merge($args, $date_args);
                }
            }

            // Handle explicit customer filter
            $customer_ids_filter = null;
            if ($request->get_param('customer') && $request->get_param('customer') !== '' && $request->get_param('customer') !== 'null') {
                $raw_customer = $request->get_param('customer');
                $customer_values = is_array($raw_customer) ? $raw_customer : explode(',', $raw_customer);
                $customer_ids_filter = array_filter(array_map('intval', $customer_values));
                if (!empty($customer_ids_filter)) {
                    $args['customer'] = $customer_ids_filter;
                }
            }

            // Handle province filter (optimized with meta_query)
            if ($request->get_param('province') && $request->get_param('province') !== '' && $request->get_param('province') !== 'null') {
                $province = sanitize_text_field($request->get_param('province'));
                if (!isset($args['meta_query'])) {
                    $args['meta_query'] = array();
                }
                $args['meta_query'][] = array(
                    'key' => '_billing_state',
                    'value' => $province,
                    'compare' => '='
                );
            }
            
            // Execute query
            $order_query = new WC_Order_Query($args);
            $orders = $order_query->get_orders();
            $order_ids = is_array($orders) ? array_map('intval', $orders) : array();
            
            if ($requested_manager_id) {
                $manager_query_args = $args;
                if (isset($manager_query_args['customer'])) {
                    unset($manager_query_args['customer']);
                }
                $explicit_manager_orders = $this->get_orders_with_explicit_manager($requested_manager_id, $manager_query_args);
                if (!empty($explicit_manager_orders)) {
                    $order_ids = array_values(array_unique(array_merge($order_ids, $explicit_manager_orders)));
                }
            }

            $active_manager_filter = $user_manager_id ?: $requested_manager_id;
            
            // Apply account manager filtering if user is not super admin
            if ($active_manager_filter && !empty($order_ids)) {
                $order_ids = $this->filter_orders_by_manager($order_ids, $active_manager_filter);
            }
            
            if (empty($order_ids)) {
                return new WP_Error('no_orders', 'No orders found for export', array('status' => 400));
            }
            
            $export_data = array();
            $min_amount = $this->parse_money_param($request->get_param('min_amount'));
            $max_amount = $this->parse_money_param($request->get_param('max_amount'));
            $filtered_customer_ids = $customer_ids_filter ?? null;
            
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) continue;
                
                // Enforce customer filter (in case WC query included guest orders etc.)
                if (!empty($filtered_customer_ids)) {
                    $order_customer_id = $order->get_customer_id();
                    if (!$order_customer_id || !in_array(intval($order_customer_id), $filtered_customer_ids, true)) {
                        continue;
                    }
                }
                
                // Apply amount filtering if needed
                $order_total = floatval($order->get_total());
                if ($min_amount !== null && $order_total < $min_amount) {
                    continue;
                }
                if ($max_amount !== null && $order_total > $max_amount) {
                    continue;
                }

                if ($search_term !== null && !$this->order_matches_search_term($order, $search_term)) {
                    continue;
                }

                $export_data[] = array(
                    'Order Number' => $order->get_order_number(),
                    'Status' => wc_get_order_status_name($order->get_status()),
                    'Date' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                    'Customer' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                    'Email' => $order->get_billing_email(),
                    'Phone' => $order->get_billing_phone(),
                    'Total Amount' => $order_total,
                    'Currency' => $order->get_currency(),
                    'Payment Method' => $order->get_payment_method_title(),
                    'Address' => trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2()),
                    'City' => $order->get_billing_city(),
                    'Province' => $order->get_billing_state(),
                    'Postal Code' => $order->get_billing_postcode()
                );
            }
            
            if (empty($export_data)) {
                return new WP_Error('no_orders', 'No orders found for export', array('status' => 400));
            }
            $export_data = $this->prepend_header_row($export_data);
            
            // Generate CSV or Excel file
            if ($format === 'csv') {
                return $this->generate_csv($export_data, 'orders');
            } else {
                return $this->generate_excel($export_data, 'orders');
            }
            
        } catch (Exception $e) {
            error_log('Export Error: ' . $e->getMessage());
            return new WP_Error('export_error', 'Error creating export file: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get date range arguments for export
     */
    private function get_date_range_args($date_range) {
        $args = array();
        
        switch ($date_range) {
            case 'this_week':
                $args['date_created'] = '>=' . date('Y-m-d', strtotime('monday this week'));
                break;
                
            case 'this_month':
                $args['date_created'] = '>=' . date('Y-m-01');
                break;
                
            case 'last_month':
                $start_of_last_month = date('Y-m-01', strtotime('-1 month'));
                $end_of_last_month = date('Y-m-t', strtotime('-1 month'));
                $args['date_created'] = $start_of_last_month . '...' . $end_of_last_month . ' 23:59:59';
                break;
                
            case 'last_year':
                $start_of_last_year = date('Y-01-01', strtotime('-1 year'));
                $end_of_last_year = date('Y-12-31', strtotime('-1 year'));
                $args['date_created'] = $start_of_last_year . '...' . $end_of_last_year . ' 23:59:59';
                break;
                
            case 'last_7_days':
                $args['date_created'] = '>=' . date('Y-m-d', strtotime('-7 days'));
                break;
                
            case 'last_30_days':
                $args['date_created'] = '>=' . date('Y-m-d', strtotime('-30 days'));
                break;
        }
        
        return $args;
    }
    
    /**
     * Export customers to CSV
     */
    public function export_customers($request) {
        try {
            // Get user permissions
            $permissions = $this->get_user_permissions();
            $user_manager_id = null;
            
            // If account manager, restrict to their own customers
            if (!$permissions['is_super_admin'] && $permissions['account_manager_id']) {
                $user_manager_id = $permissions['account_manager_id'];
            }
            
            $format = $request->get_param('format') ?: 'csv';
            // Get all customers for export (no limit)
            $limit_raw = $request->get_param('limit');
            $limit = ($limit_raw && intval($limit_raw) > 0) ? min(intval($limit_raw), 10000) : -1;
            
            // Build WP_User_Query arguments mirroring dashboard filters
            $args = array(
                'number' => $limit,
                'fields' => 'ID',
                'orderby' => 'registered',
                'order' => 'DESC'
            );
            
            // Apply search filter
            $search_param = $request->get_param('search');
            if ($search_param && $search_param !== 'null') {
                $search = trim($search_param);
                if ($search !== '' && $search !== 'null') {
                    $args['search'] = '*' . esc_attr($search) . '*';
                    $args['search_columns'] = array('user_login', 'user_email', 'user_nicename', 'display_name');
                }
            }
            
            // Handle account manager + province filters via meta_query
            $meta_query = array();
            $requested_manager = $request->get_param('accountManager') ?: $request->get_param('account_manager');
            if ($user_manager_id) {
                $meta_query[] = array(
                    'key' => '_account_manager_id',
                    'value' => $user_manager_id,
                    'compare' => '='
                );
            } elseif ($requested_manager && $requested_manager !== '' && $requested_manager !== 'null') {
                $meta_query[] = array(
                    'key' => '_account_manager_id',
                    'value' => sanitize_text_field($requested_manager),
                    'compare' => '='
                );
            }

            $province_param = $request->get_param('province');
            if ($province_param && $province_param !== '' && $province_param !== 'null') {
                $meta_query[] = array(
                    'key' => 'billing_state',
                    'value' => sanitize_text_field($province_param),
                    'compare' => '='
                );
            }

            if (!empty($meta_query)) {
                $args['meta_query'] = count($meta_query) > 1
                    ? array_merge(array('relation' => 'AND'), $meta_query)
                    : $meta_query;
            }

            // Handle date registered filters (custom dates prioritized over presets)
            $date_query = array('column' => 'user_registered', 'inclusive' => true);
            $custom_from = $request->get_param('date_registered_from') ?: $request->get_param('date_from');
            $custom_to = $request->get_param('date_registered_to') ?: $request->get_param('date_to');
            $has_custom_dates = ($custom_from && $custom_from !== '' && $custom_from !== 'null') || ($custom_to && $custom_to !== '' && $custom_to !== 'null');
            
            if ($has_custom_dates) {
                if ($custom_from && $custom_from !== '' && $custom_from !== 'null') {
                    $date_query['after'] = $custom_from;
                }
                if ($custom_to && $custom_to !== '' && $custom_to !== 'null') {
                    $date_query['before'] = $custom_to . ' 23:59:59';
                }
            } elseif ($request->get_param('dateRange') && $request->get_param('dateRange') !== '' && $request->get_param('dateRange') !== 'null') {
                $date_range = $request->get_param('dateRange');
                switch ($date_range) {
                    case 'last_year':
                        $date_query['after'] = date('Y-01-01', strtotime('-1 year'));
                        $date_query['before'] = date('Y-12-31', strtotime('-1 year')) . ' 23:59:59';
                        break;
                    case 'this_year':
                        $date_query['after'] = date('Y-01-01');
                        break;
                    case 'last_month':
                        $date_query['after'] = date('Y-m-01', strtotime('-1 month'));
                        $date_query['before'] = date('Y-m-t', strtotime('-1 month')) . ' 23:59:59';
                        break;
                    case 'this_month':
                        $date_query['after'] = date('Y-m-01');
                        break;
                    case 'last_7_days':
                        $date_query['after'] = date('Y-m-d', strtotime('-7 days'));
                        break;
                    case 'last_30_days':
                        $date_query['after'] = date('Y-m-d', strtotime('-30 days'));
                        break;
                }
            }

            if (!empty($date_query['after']) || !empty($date_query['before'])) {
                $args['date_query'] = array($date_query);
            }
            
            // Execute query
            $customer_query = new WP_User_Query($args);
            $user_ids = $customer_query->get_results();
            
            if (empty($user_ids)) {
                return new WP_Error('no_customers', 'No customers found for export', array('status' => 400));
            }
            
            $min_total_spent = $this->parse_money_param($request->get_param('total_spent_min'));
            $max_total_spent = $this->parse_money_param($request->get_param('total_spent_max'));
            $export_data = array();
            
            foreach ($user_ids as $user_id) {
                $customer = new WC_Customer($user_id);
                if (!$customer->get_id()) {
                    continue;
                }
                
                // Get stats once per customer
                $order_count = wc_get_customer_order_count($customer->get_id());
                $total_spent = floatval(wc_get_customer_total_spent($customer->get_id()));

                if ($min_total_spent !== null && $total_spent < $min_total_spent) {
                    continue;
                }
                if ($max_total_spent !== null && $total_spent > $max_total_spent) {
                    continue;
                }

                // Ensure account managers never see house customers even if meta query misses
                if ($user_manager_id) {
                    $customer_manager = get_user_meta($customer->get_id(), '_account_manager_id', true);
                    if ($customer_manager === 'house') {
                        continue;
                    }
                }

                $manager_label = $this->get_customer_manager_label($customer->get_id());
                
                $export_data[] = array(
                    'Customer ID' => $customer->get_id(),
                    'First Name' => $customer->get_first_name(),
                    'Last Name' => $customer->get_last_name(),
                    'Email' => $customer->get_email(),
                    'Phone' => $customer->get_billing_phone(),
                    'Company' => $customer->get_billing_company(),
                    'Address' => trim($customer->get_billing_address_1() . ' ' . $customer->get_billing_address_2()),
                    'City' => $customer->get_billing_city(),
                    'Province' => $customer->get_billing_state(),
                    'Postal Code' => $customer->get_billing_postcode(),
                    'Total Orders' => $order_count,
                    'Total Spent' => $total_spent,
                    'Registration Date' => $customer->get_date_created() ? $customer->get_date_created()->date('Y-m-d H:i:s') : '',
                    'Account Manager' => $manager_label
                );
            }
            
            if (empty($export_data)) {
                return new WP_Error('no_customers', 'No customers found for export', array('status' => 400));
            }
            $export_data = $this->prepend_header_row($export_data);
            
            // Generate CSV or Excel file
            if ($format === 'csv') {
                return $this->generate_csv($export_data, 'customers');
            }
            
            return $this->generate_excel($export_data, 'customers');
            
        } catch (Exception $e) {
            error_log('Export Customers Error: ' . $e->getMessage());
            return new WP_Error('export_error', 'Error creating export file: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Resolve customer account manager label with sane fallbacks
     */
    private function get_customer_manager_label($customer_id) {
        $manager_id = get_user_meta($customer_id, '_account_manager_id', true);
        if (!$manager_id) {
            return '';
        }
        if ($manager_id === 'house') {
            return 'House';
        }
        if (isset($this->managers[$manager_id])) {
            return $this->managers[$manager_id];
        }
        $user = get_user_by('id', $manager_id);
        if ($user) {
            return $user->display_name ?: $user->user_email;
        }
        return '';
    }

    /**
     * Normalize numeric filters coming from request payload
     */
    private function parse_money_param($value) {
        if ($value === null) {
            return null;
        }
        $string_value = trim((string) $value);
        if ($string_value === '' || strtolower($string_value) === 'null') {
            return null;
        }
        return floatval($string_value);
    }

    /**
     * Match exports to the same search logic as the dashboard table
     */
    private function order_matches_search_term($order, $search_term) {
        if ($search_term === null || $search_term === '') {
            return true;
        }
        $needle = strtolower($search_term);
        $id_match = strpos((string) $order->get_id(), $needle) !== false || strpos((string) $order->get_order_number(), $needle) !== false;
        $billing_email = strtolower((string) $order->get_billing_email());
        $email_match = $billing_email && strpos($billing_email, $needle) !== false;
        $name_combined = strtolower(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));
        $name_match = $name_combined && strpos($name_combined, $needle) !== false;
        return $id_match || $email_match || $name_match;
    }

    /**
     * Prepend header row (column titles) to export data arrays
     */
    private function prepend_header_row($rows) {
        if (empty($rows) || !is_array($rows)) {
            return $rows;
        }
        $first_row = $rows[0];
        if (!is_array($first_row)) {
            return $rows;
        }
        $headers = array_keys($first_row);
        array_unshift($rows, $headers);
        return $rows;
    }
    
    /**
     * Export analytics report
     */
    public function export_analytics($request) {
        global $wpdb;
        
        // Get user permissions
        $permissions = $this->get_user_permissions();
        $user_manager_id = null;
        
        // If account manager, restrict to their own data
        if (!$permissions['is_super_admin'] && $permissions['account_manager_id']) {
            $user_manager_id = $permissions['account_manager_id'];
        }
        
        $format = $request->get_param('format') ?: 'csv';
        $period = $request->get_param('period') ?: 'month';
        $requested_manager = $request->get_param('manager_id');
        
        // Determine which manager to filter by
        $manager_id = $user_manager_id ?: $requested_manager;
        
        $customer_filter = '';
        if ($manager_id) {
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE manager_id = %d
            ", $manager_id));
            
            if (!empty($customer_ids)) {
                $customer_ids_str = implode(',', array_map('intval', $customer_ids));
                $customer_filter = "AND customer_id IN ($customer_ids_str)";
            }
        }
        
        // Get analytics data based on period
        $analytics_data = array();
        
        if ($period === 'daily') {
            $analytics_data = $wpdb->get_results("
                SELECT 
                    DATE(date_created_gmt) as period_date,
                    COUNT(*) as orders_count,
                    COALESCE(SUM(total_amount), 0) as revenue,
                    COALESCE(AVG(total_amount), 0) as avg_order_value,
                    COUNT(DISTINCT customer_id) as unique_customers
                FROM {$wpdb->prefix}wc_orders 
                WHERE status IN ('wc-completed', 'wc-processing')
                AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                $customer_filter
                GROUP BY DATE(date_created_gmt)
                ORDER BY period_date DESC
            ");
        } elseif ($period === 'monthly') {
            $analytics_data = $wpdb->get_results("
                SELECT 
                    DATE_FORMAT(date_created_gmt, '%Y-%m') as period_date,
                    COUNT(*) as orders_count,
                    COALESCE(SUM(total_amount), 0) as revenue,
                    COALESCE(AVG(total_amount), 0) as avg_order_value,
                    COUNT(DISTINCT customer_id) as unique_customers
                FROM {$wpdb->prefix}wc_orders 
                WHERE status IN ('wc-completed', 'wc-processing')
                AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                $customer_filter
                GROUP BY DATE_FORMAT(date_created_gmt, '%Y-%m')
                ORDER BY period_date DESC
            ");
        }
        
        if (empty($analytics_data)) {
            return new WP_Error('no_data', 'No data found for export', array('status' => 400));
        }
        
        // Generate export data
        $export_data = array();
        $headers = array(
            'تاریخ/دوره',
            'تعداد سفارشات',
            'درآمد کل',
            'میانگین ارزش سفارش',
            'مشتریان منحصربه‌فرد'
        );
        
        $export_data[] = $headers;
        
        foreach ($analytics_data as $data) {
            $period_label = $period === 'daily' 
                ? date('Y-m-d', strtotime($data->period_date))
                : date('Y-m', strtotime($data->period_date . '-01'));
                
            $export_data[] = array(
                $period_label,
                $data->orders_count,
                number_format($data->revenue, 2),
                number_format($data->avg_order_value, 2),
                $data->unique_customers
            );
        }
        
        // Generate file
        if ($format === 'csv') {
            return $this->generate_csv_response($export_data, 'analytics_export_' . date('Y-m-d'));
        } else {
            return $this->generate_excel_response($export_data, 'analytics_export_' . date('Y-m-d'));
        }
    }
    
    /**
     * Generate CSV response
     */
    private function generate_csv_response($data, $filename) {
        // Create temporary file
        $temp_file = wp_tempnam($filename . '.csv');
        $file_handle = fopen($temp_file, 'w');
        
        // Add BOM for UTF-8
        fwrite($file_handle, "\xEF\xBB\xBF");
        
        // Write data
        foreach ($data as $row) {
            fputcsv($file_handle, $row);
        }
        
        fclose($file_handle);
        
        // Read file content
        $content = file_get_contents($temp_file);
        unlink($temp_file);
        
        // Return base64 encoded content
        return array(
            'success' => true,
            'filename' => $filename . '.csv',
            'content' => base64_encode($content),
            'mime_type' => 'text/csv',
            'size' => strlen($content)
        );
    }
    
    /**
     * Generate Excel response (CSV format for simplicity)
     */
    private function generate_excel_response($data, $filename) {
        // For now, we'll use CSV format with .xls extension
        // In a full implementation, you might want to use a library like PhpSpreadsheet
        
        $temp_file = wp_tempnam($filename . '.xls');
        $file_handle = fopen($temp_file, 'w');
        
        // Add BOM for UTF-8
        fwrite($file_handle, "\xEF\xBB\xBF");
        
        // Write data as tab-separated values for better Excel compatibility
        foreach ($data as $row) {
            fwrite($file_handle, implode("\t", $row) . "\n");
        }
        
        fclose($file_handle);
        
        // Read file content
        $content = file_get_contents($temp_file);
        unlink($temp_file);
        
        // Return base64 encoded content
        return array(
            'success' => true,
            'filename' => $filename . '.xls',
            'content' => base64_encode($content),
            'mime_type' => 'application/vnd.ms-excel',
            'size' => strlen($content)
        );
    }
    
    /**
     * Generate CSV file
     */
    private function generate_csv($data, $filename) {
        $csv_content = '';
        
        foreach ($data as $row) {
            $csv_row = array();
            foreach ($row as $value) {
                // Escape quotes and wrap in quotes if contains comma
                $escaped_value = str_replace('"', '""', $value);
                if (strpos($escaped_value, ',') !== false || strpos($escaped_value, '"') !== false) {
                    $csv_row[] = '"' . $escaped_value . '"';
                } else {
                    $csv_row[] = $escaped_value;
                }
            }
            $csv_content .= implode(',', $csv_row) . "\n";
        }
        
        return array(
            'success' => true,
            'filename' => $filename . '_' . date('Y-m-d') . '.csv',
            'content' => base64_encode("\xEF\xBB\xBF" . $csv_content), // Add BOM for UTF-8
            'mime_type' => 'text/csv',
            'size' => strlen($csv_content)
        );
    }
    
    /**
     * Generate Excel file
     */
    private function generate_excel($data, $filename) {
        $content = '<table border="1">';
        
        foreach ($data as $row) {
            $content .= '<tr>';
            foreach ($row as $value) {
                $content .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $content .= '</tr>';
        }
        
        $content .= '</table>';
        
        return array(
            'success' => true,
            'filename' => $filename . '_' . date('Y-m-d') . '.xls',
            'content' => base64_encode($content),
            'mime_type' => 'application/vnd.ms-excel',
            'size' => strlen($content)
        );
    }
    
    /**
     * Translate order status to Persian
     */
    private function translate_order_status($status) {
        $status_translations = array(
            'pending' => 'در انتظار پرداخت',
            'processing' => 'در حال پردازش',
            'on-hold' => 'در انتظار',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            'refunded' => 'بازپرداخت شده',
            'failed' => 'ناموفق'
        );
        
        $clean_status = str_replace('wc-', '', $status);
        return $status_translations[$clean_status] ?? $status;
    }
    
    /**
     * Check permissions
     */
    public function check_permissions($request) {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $current_user = wp_get_current_user();
        
        return in_array('administrator', $current_user->roles) || 
               in_array('shop_manager', $current_user->roles);
    }
}
