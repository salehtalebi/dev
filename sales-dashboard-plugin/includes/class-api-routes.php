<?php

/**
 * API Routes Class
 */
class Sales_Dashboard_API_Routes {
    
    private $managers;
    
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
    
    public function register_routes() {
        // Orders routes
        register_rest_route('sales-dashboard/v1', '/orders/by-manager/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_orders_by_manager'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
        
        register_rest_route('sales-dashboard/v1', '/orders/statistics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_statistics'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Customers routes
        register_rest_route('sales-dashboard/v1', '/customers/by-manager/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customers_by_manager'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('sales-dashboard/v1', '/customers/(?P<id>\d+)/statistics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customer_statistics'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Enhanced WooCommerce integration
        register_rest_route('sales-dashboard/v1', '/wc/orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_enhanced_orders'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('sales-dashboard/v1', '/wc/customers', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_enhanced_customers'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('sales-dashboard/v1', '/wc/orders/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_details'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('sales-dashboard/v1', '/wc/orders/(?P<id>\d+)/status', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_order_status'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'status' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));
    }
    
    /**
     * Get orders by account manager
     */
    public function get_orders_by_manager($request) {
        global $wpdb;
        
        $manager_id = $request['id'];
        $page = $request->get_param('page') ?: 1;
        $per_page = min($request->get_param('per_page') ?: 20, 100);
        $offset = ($page - 1) * $per_page;
        $status = $request->get_param('status');
        
        // Get customer IDs assigned to this manager
        $customer_ids = $wpdb->get_col($wpdb->prepare("
            SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
            WHERE manager_id = %d
        ", $manager_id));
        
        if (empty($customer_ids)) {
            return array(
                'data' => array(),
                'total' => 0,
                'pages' => 0
            );
        }
        
        // Build query args
        $query_args = array(
            'customer' => $customer_ids,
            'limit' => $per_page,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects'
        );
        
        if ($status) {
            $query_args['status'] = $status;
        }
        
        // Get orders
        $order_query = new WC_Order_Query($query_args);
        $orders = $order_query->get_orders();
        
        // Get total count
        $total_query = new WC_Order_Query(array(
            'customer' => $customer_ids,
            'status' => $status ?: 'any',
            'return' => 'ids',
            'limit' => -1
        ));
        $total = count($total_query->get_orders());
        
        $formatted_orders = array();
        foreach ($orders as $order) {
            $formatted_orders[] = $this->format_order_data($order);
        }
        
        return array(
            'data' => $formatted_orders,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * Get customers by account manager
     */
    public function get_customers_by_manager($request) {
        global $wpdb;
        
        $manager_id = $request['id'];
        $search = $request->get_param('search');
        
        $customer_ids = $wpdb->get_col($wpdb->prepare("
            SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
            WHERE manager_id = %d
        ", $manager_id));
        
        if (empty($customer_ids)) {
            return array();
        }
        
        $customers = array();
        foreach ($customer_ids as $customer_id) {
            $customer = new WC_Customer($customer_id);
            if ($customer->get_id()) {
                $customer_data = $this->format_customer_data($customer);
                
                // Apply search filter if provided
                if ($search) {
                    $search_fields = array(
                        $customer_data['first_name'],
                        $customer_data['last_name'],
                        $customer_data['email'],
                        $customer_data['display_name']
                    );
                    
                    $match_found = false;
                    foreach ($search_fields as $field) {
                        if (stripos($field, $search) !== false) {
                            $match_found = true;
                            break;
                        }
                    }
                    
                    if (!$match_found) {
                        continue;
                    }
                }
                
                $customers[] = $customer_data;
            }
        }
        
        return $customers;
    }
    
    /**
     * Get order statistics
     */
    public function get_order_statistics($request) {
        global $wpdb;
        
        $period = $request->get_param('period') ?: 'month';
        $manager_id = $request->get_param('manager_id');
        
        $where_conditions = array("status IN ('wc-completed', 'wc-processing')");
        
        if ($manager_id) {
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE manager_id = %d
            ", $manager_id));
            
            if (!empty($customer_ids)) {
                $customer_ids_str = implode(',', array_map('intval', $customer_ids));
                $where_conditions[] = "customer_id IN ($customer_ids_str)";
            } else {
                return array('orders_count' => 0, 'revenue' => 0);
            }
        }
        
        // Date range based on period
        switch ($period) {
            case 'week':
                $where_conditions[] = "date_created_gmt >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $where_conditions[] = "date_created_gmt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'year':
                $where_conditions[] = "date_created_gmt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }
        
        $where_clause = "WHERE " . implode(' AND ', $where_conditions);
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as orders_count,
                COALESCE(SUM(total_amount), 0) as revenue
            FROM {$wpdb->prefix}wc_orders 
            $where_clause
        ");
        
        return array(
            'orders_count' => (int)$stats->orders_count,
            'revenue' => (float)$stats->revenue,
            'period' => $period
        );
    }
    
    /**
     * Get customer statistics
     */
    public function get_customer_statistics($request) {
        global $wpdb;
        
        $customer_id = $request['id'];
        $customer = new WC_Customer($customer_id);
        
        if (!$customer->get_id()) {
            return new WP_Error('customer_not_found', 'مشتری یافت نشد', array('status' => 404));
        }
        
        // Get monthly order data for the last 12 months
        $monthly_orders = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE_FORMAT(date_created_gmt, '%%Y-%%m') as month,
                COUNT(*) as orders_count,
                COALESCE(SUM(total_amount), 0) as total_spent
            FROM {$wpdb->prefix}wc_orders 
            WHERE customer_id = %d 
            AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            AND status IN ('wc-completed', 'wc-processing')
            GROUP BY DATE_FORMAT(date_created_gmt, '%%Y-%%m')
            ORDER BY month DESC
        ", $customer_id));
        
        $current_month = date('Y-m');
        $last_month = date('Y-m', strtotime('-1 month'));
        
        $current_month_data = array_filter($monthly_orders, function($order) use ($current_month) {
            return $order->month === $current_month;
        });
        
        $last_month_data = array_filter($monthly_orders, function($order) use ($last_month) {
            return $order->month === $last_month;
        });
        
        $current_month_total = !empty($current_month_data) ? reset($current_month_data)->total_spent : 0;
        $last_month_total = !empty($last_month_data) ? reset($last_month_data)->total_spent : 0;
        
        $growth_percentage = $last_month_total > 0 ? 
            (($current_month_total - $last_month_total) / $last_month_total) * 100 : 0;
        
        return array(
            'customer' => $this->format_customer_data($customer),
            'total_orders' => $customer->get_order_count(),
            'total_spent' => $customer->get_total_spent(),
            'current_month_total' => (float)$current_month_total,
            'last_month_total' => (float)$last_month_total,
            'growth_percentage' => round($growth_percentage, 2),
            'average_order_value' => $customer->get_order_count() > 0 ? 
                $customer->get_total_spent() / $customer->get_order_count() : 0,
            'monthly_data' => $monthly_orders
        );
    }
    
    /**
     * Get enhanced orders (with additional data)
     */
    public function get_enhanced_orders($request) {
        $args = array(
            'limit' => min($request->get_param('per_page') ?: 20, 100),
            'page' => $request->get_param('page') ?: 1,
            'orderby' => $request->get_param('orderby') ?: 'date',
            'order' => $request->get_param('order') ?: 'DESC'
        );
        
        if ($request->get_param('status')) {
            $args['status'] = $request->get_param('status');
        }
        
        if ($request->get_param('customer')) {
            $args['customer'] = $request->get_param('customer');
        }
        
        if ($request->get_param('date_created_gmt')) {
            $args['date_created_gmt'] = $request->get_param('date_created_gmt');
        }
        
        if ($request->get_param('search')) {
            $args['search'] = $request->get_param('search');
        }
        
        $order_query = new WC_Order_Query($args);
        $orders = $order_query->get_orders();
        
        $formatted_orders = array();
        foreach ($orders as $order) {
            $order_data = $this->format_order_data($order);
            
            // Add account manager info
            $customer_id = $order->get_customer_id();
            if ($customer_id) {
                $order_data['account_manager'] = $this->get_customer_account_manager($customer_id);
            }
            
            $formatted_orders[] = $order_data;
        }
        
        return array(
            'data' => $formatted_orders,
            'total' => count($orders)
        );
    }
    
    /**
     * Get enhanced customers (with additional data)
     */
    public function get_enhanced_customers($request) {
        $args = array(
            'number' => min($request->get_param('per_page') ?: 20, 100),
            'offset' => ($request->get_param('page') - 1) * ($request->get_param('per_page') ?: 20)
        );
        
        if ($request->get_param('search')) {
            $args['search'] = '*' . esc_attr($request->get_param('search')) . '*';
        }
        
        if ($request->get_param('role')) {
            $args['role'] = $request->get_param('role');
        }
        
        $customer_query = new WP_User_Query($args);
        $customers = $customer_query->get_results();
        
        $formatted_customers = array();
        foreach ($customers as $user) {
            $customer = new WC_Customer($user->ID);
            $customer_data = $this->format_customer_data($customer);
            
            // Add account manager info
            $customer_data['account_manager'] = $this->get_customer_account_manager($customer->get_id());
            
            $formatted_customers[] = $customer_data;
        }
        
        return array(
            'data' => $formatted_customers,
            'total' => $customer_query->get_total()
        );
    }
    
    /**
     * Get order details
     */
    public function get_order_details($request) {
        $order_id = $request['id'];
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('order_not_found', 'سفارش یافت نشد', array('status' => 404));
        }
        
        $order_data = $this->format_order_data($order);
        
        // Add detailed line items
        $order_data['line_items'] = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $order_data['line_items'][] = array(
                'id' => $item->get_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'product_id' => $product ? $product->get_id() : 0,
                'sku' => $product ? $product->get_sku() : '',
                'price' => $product ? $product->get_price() : 0,
                'meta_data' => $item->get_formatted_meta_data()
            );
        }
        
        // Add shipping items
        $order_data['shipping_lines'] = array();
        foreach ($order->get_shipping_methods() as $shipping) {
            $order_data['shipping_lines'][] = array(
                'id' => $shipping->get_id(),
                'method_title' => $shipping->get_method_title(),
                'method_id' => $shipping->get_method_id(),
                'total' => $shipping->get_total()
            );
        }
        
        // Add customer account manager
        if ($order->get_customer_id()) {
            $order_data['account_manager'] = $this->get_customer_account_manager($order->get_customer_id());
        }
        
        return $order_data;
    }
    
    /**
     * Update order status
     */
    public function update_order_status($request) {
        $order_id = $request['id'];
        $new_status = sanitize_text_field($request->get_param('status'));
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('order_not_found', 'سفارش یافت نشد', array('status' => 404));
        }
        
        // Validate status
        $valid_statuses = array_keys(wc_get_order_statuses());
        if (!in_array('wc-' . $new_status, $valid_statuses) && !in_array($new_status, $valid_statuses)) {
            return new WP_Error('invalid_status', 'وضعیت نامعتبر است', array('status' => 400));
        }
        
        $order->update_status($new_status, 'وضعیت توسط پنل فروش تغییر یافت');
        
        return array(
            'success' => true,
            'message' => 'وضعیت سفارش با موفقیت بروزرسانی شد',
            'order' => $this->format_order_data($order)
        );
    }
    
    /**
     * Format order data
     */
    private function format_order_data($order) {
        $data = array(
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'currency' => $order->get_currency(),
            'total' => $order->get_total(),
            'subtotal' => $order->get_subtotal(),
            'tax_total' => $order->get_total_tax(),
            'shipping_total' => $order->get_shipping_total(),
            'date_created' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
            'date_modified' => $order->get_date_modified() ? $order->get_date_modified()->date('Y-m-d H:i:s') : '',
            'customer_id' => $order->get_customer_id(),
            'billing' => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'company' => $order->get_billing_company(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country()
            ),
            'shipping' => array(
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country()
            ),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'line_items' => array()
        );
        
        // Add basic line items (detailed version in get_order_details)
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $data['line_items'][] = array(
                'id' => $item->get_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'product_id' => $product ? $product->get_id() : 0
            );
        }
        
        return $data;
    }
    
    /**
     * Format customer data
     */
    private function format_customer_data($customer) {
        return array(
            'id' => $customer->get_id(),
            'email' => $customer->get_email(),
            'first_name' => $customer->get_first_name(),
            'last_name' => $customer->get_last_name(),
            'display_name' => $customer->get_display_name(),
            'username' => $customer->get_username(),
            'date_created' => $customer->get_date_created() ? $customer->get_date_created()->date('Y-m-d H:i:s') : '',
            'date_modified' => $customer->get_date_modified() ? $customer->get_date_modified()->date('Y-m-d H:i:s') : '',
            'total_spent' => $customer->get_total_spent(),
            'orders_count' => $customer->get_order_count(),
            'avatar_url' => get_avatar_url($customer->get_id()),
            'billing' => array(
                'first_name' => $customer->get_billing_first_name(),
                'last_name' => $customer->get_billing_last_name(),
                'company' => $customer->get_billing_company(),
                'phone' => $customer->get_billing_phone(),
                'country' => $customer->get_billing_country(),
                'state' => $customer->get_billing_state(),
                'city' => $customer->get_billing_city(),
                'postcode' => $customer->get_billing_postcode(),
                'address_1' => $customer->get_billing_address_1(),
                'address_2' => $customer->get_billing_address_2()
            )
        );
    }
    
    /**
     * Get customer account manager
     */
    private function get_customer_account_manager($customer_id) {
        global $wpdb;
        
        $manager_id = $wpdb->get_var($wpdb->prepare("
            SELECT manager_id FROM {$wpdb->prefix}customer_account_managers 
            WHERE customer_id = %d
        ", $customer_id));
        
        if ($manager_id) {
            $manager = get_user_by('id', $manager_id);
            if ($manager) {
                return array(
                    'id' => $manager->ID,
                    'name' => $manager->display_name,
                    'email' => $manager->user_email
                );
            }
        }
        
        return null;
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
