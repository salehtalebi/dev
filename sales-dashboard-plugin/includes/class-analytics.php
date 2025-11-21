<?php

/**
 * Analytics Class
 */
class Sales_Dashboard_Analytics {
    
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
     * Get JWT Auth instance for permission checks
     */
    private function get_jwt_auth() {
        if (!$this->jwt_auth) {
            $this->jwt_auth = new Sales_Dashboard_JWT_Auth();
        }
        return $this->jwt_auth;
    }
    
    /**
     * Get current user permissions
     * Returns array with is_super_admin and account_manager_id
     */
    private function get_user_permissions() {
        return $this->get_jwt_auth()->get_current_user_permissions();
    }
    
    /**
     * Filter order IDs based on account manager permissions
     * For account managers, only return orders assigned to them
     * 
     * @param array $order_ids Array of order IDs to filter
     * @param string $manager_id Account manager ID (if null, uses current user's ID)
     * @return array Filtered order IDs
     */
    private function filter_orders_by_manager($order_ids, $manager_id = null) {
        $permissions = $this->get_user_permissions();
        
        // Super admins see everything
        if ($permissions['is_super_admin']) {
            return $order_ids;
        }
        
        // Use provided manager_id or current user's manager_id
        $filter_manager_id = $manager_id ?: $permissions['account_manager_id'];
        
        // If no manager ID, return empty (shouldn't happen for account managers)
        if (!$filter_manager_id) {
            return array();
        }
        
        $filtered_ids = array();
        
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            // Check order-specific manager first
            $order_manager_id = get_post_meta($order_id, '_order_account_manager_id', true);
            
            if ($order_manager_id) {
                // Skip 'house' for account managers
                if ($order_manager_id === 'house') {
                    continue;
                }
                
                // Order has specific manager assigned
                if ($order_manager_id === $filter_manager_id) {
                    $filtered_ids[] = $order_id;
                }
            } else {
                // Fall back to customer's account manager
                $customer_id = $order->get_customer_id();
                if ($customer_id) {
                    $customer_manager_id = get_user_meta($customer_id, '_account_manager_id', true);
                    
                    // Skip 'house' for account managers
                    if ($customer_manager_id === 'house') {
                        continue;
                    }
                    
                    if ($customer_manager_id === $filter_manager_id) {
                        $filtered_ids[] = $order_id;
                    }
                }
            }
        }
        
        return $filtered_ids;
    }
    
    /**
     * Filter customer IDs based on account manager permissions
     * 
     * @param array $customer_ids Array of customer IDs to filter
     * @param string $manager_id Account manager ID (if null, uses current user's ID)
     * @return array Filtered customer IDs
     */
    private function filter_customers_by_manager($customer_ids, $manager_id = null) {
        $permissions = $this->get_user_permissions();
        
        // Super admins see everything
        if ($permissions['is_super_admin']) {
            return $customer_ids;
        }
        
        // Use provided manager_id or current user's manager_id
        $filter_manager_id = $manager_id ?: $permissions['account_manager_id'];
        
        // If no manager ID, return empty
        if (!$filter_manager_id) {
            return array();
        }
        
        $filtered_ids = array();
        
        foreach ($customer_ids as $customer_id) {
            $customer_manager_id = get_user_meta($customer_id, '_account_manager_id', true);
            
            // Skip 'house' customers for account managers
            if ($customer_manager_id === 'house') {
                continue;
            }
            
            if ($customer_manager_id === $filter_manager_id) {
                $filtered_ids[] = $customer_id;
            }
        }
        
        return $filtered_ids;
    }
    
    public function register_routes() {
        // Dashboard analytics
        register_rest_route('sales-dashboard/v1', '/analytics/dashboard', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_dashboard_analytics'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Top selling products
        register_rest_route('sales-dashboard/v1', '/analytics/top-products', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_top_products'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Monthly revenue - NEW
        register_rest_route('sales-dashboard/v1', '/analytics/monthly-revenue', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_monthly_revenue'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Sales comparison - NEW  
        register_rest_route('sales-dashboard/v1', '/analytics/sales-comparison', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_sales_comparison'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Manager performance - NEW
        register_rest_route('sales-dashboard/v1', '/analytics/manager-performance', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_manager_performance'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Order statistics by category - NEW
        register_rest_route('sales-dashboard/v1', '/analytics/order-statistics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_statistics'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Customer statistics with filters - NEW
        register_rest_route('sales-dashboard/v1', '/analytics/customer-statistics/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customer_statistics'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Customer categories analytics - NEW
        register_rest_route('sales-dashboard/v1', '/analytics/customer-categories/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customer_categories'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    /**
     * Get dashboard analytics  
     */
    public function get_dashboard_analytics($request) {
        try {
            $period = $request->get_param('period') ?: 'month';
            $manager_id = $request->get_param('manager_id');
            
            // Get user permissions
            $permissions = $this->get_user_permissions();
            
            // If account manager and no specific manager_id requested, use their own ID
            if (!$permissions['is_super_admin'] && !$manager_id) {
                $manager_id = $permissions['account_manager_id'];
            }
            
            // Simple stats without complex queries first
            $current_month_start = date('Y-m-01 00:00:00');
            $current_month_end = date('Y-m-t 23:59:59');
            
            // Get basic stats for current month
            $current_orders = wc_get_orders(array(
                'status' => array('completed', 'processing'),
                'date_created' => $current_month_start . '...' . $current_month_end,
                'limit' => -1,
                'return' => 'ids'
            ));
            
            // Filter orders by manager if applicable
            if ($manager_id || !$permissions['is_super_admin']) {
                $current_orders = $this->filter_orders_by_manager($current_orders, $manager_id);
            }

            $total_orders = count($current_orders);
            $total_revenue = 0;
            $customer_ids = array();

            // Load full order objects only for filtered IDs
            foreach ($current_orders as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $total_revenue += $order->get_total();
                    if ($order->get_customer_id() > 0) {
                        $customer_ids[] = $order->get_customer_id();
                    }
                }
            }

            // Also compute today's orders/revenue separately so frontend 'today' metrics are accurate
            $today_start = date('Y-m-d 00:00:00');
            $today_end = date('Y-m-d 23:59:59');
            $today_order_ids = wc_get_orders(array(
                'status' => array('completed', 'processing'),
                'date_created' => $today_start . '...' . $today_end,
                'limit' => -1,
                'return' => 'ids'
            ));

            if ($manager_id || !$permissions['is_super_admin']) {
                $today_order_ids = $this->filter_orders_by_manager($today_order_ids, $manager_id);
            }

            $todayOrders = 0;
            $todayRevenue = 0;
            foreach ($today_order_ids as $oid) {
                $o = wc_get_order($oid);
                if ($o) {
                    $todayOrders++;
                    $todayRevenue += $o->get_total();
                }
            }
            
            $average_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
            $new_customers = count(array_unique($customer_ids));
            
            // Get previous month for growth calculation (also limited)
            $prev_month_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
            $prev_month_end = date('Y-m-t 23:59:59', strtotime('-1 month'));
            
            $prev_orders = wc_get_orders(array(
                'status' => array('completed', 'processing'),
                'date_created' => $prev_month_start . '...' . $prev_month_end,
                'limit' => -1,
                'return' => 'ids'
            ));
            
            // Filter previous month orders by manager if applicable
            if ($manager_id || !$permissions['is_super_admin']) {
                $prev_orders = $this->filter_orders_by_manager($prev_orders, $manager_id);
            }
            
            $prev_revenue = 0;
            foreach ($prev_orders as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $prev_revenue += $order->get_total();
                }
            }
            
            $growth_percentage = $prev_revenue > 0 ? 
                (($total_revenue - $prev_revenue) / $prev_revenue) * 100 : 0;
            
            // Clear memory
            unset($current_orders, $prev_orders);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            $result = array(
                'todayOrders' => $todayOrders, // Frontend expects this key
                'todayRevenue' => $todayRevenue, // Frontend expects this key
                'newCustomers' => $new_customers,
                'averageOrderValue' => $average_order_value,
                'ordersGrowth' => round($growth_percentage, 2),
                'revenueGrowth' => round($growth_percentage, 2),
                'total_revenue' => $total_revenue,
                'total_orders' => $total_orders,
                'average_order_value' => $average_order_value,
                'growth_percentage' => round($growth_percentage, 2),
                'period' => $period
            );
            
            return rest_ensure_response($result);
            
        } catch (Exception $e) {
            error_log('Sales Dashboard Analytics Error: ' . $e->getMessage());
            return new WP_Error('analytics_error', 'Error retrieving dashboard analytics: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get monthly comparison data
     */
    public function get_monthly_comparison($request) {
        try {
            $manager_id = $request->get_param('manager_id');
            $months_count = min($request->get_param('months') ?: 12, 24);
            
            // Get user permissions
            $permissions = $this->get_user_permissions();
            
            // If account manager and no specific manager_id requested, use their own ID
            if (!$permissions['is_super_admin'] && !$manager_id) {
                $manager_id = $permissions['account_manager_id'];
            }
            
            // Get date range
            $start_date = date('Y-m-01', strtotime("-{$months_count} months"));
            $end_date = date('Y-m-t');
            
            $orders = wc_get_orders(array(
                'status' => array('wc-completed', 'wc-processing'),
                'date_created' => $start_date . '...' . $end_date,
                'limit' => -1,
                'return' => 'ids'
            ));
            
            // Filter orders by manager if applicable
            if ($manager_id || !$permissions['is_super_admin']) {
                $orders = $this->filter_orders_by_manager($orders, $manager_id);
            }
            
            $monthly_data = array();
            
            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) continue;
                
                $month = $order->get_date_created()->format('Y-m');
                
                if (!isset($monthly_data[$month])) {
                    $monthly_data[$month] = array(
                        'month' => $month,
                        'orders_count' => 0,
                        'revenue' => 0,
                        'unique_customers' => array()
                    );
                }
                
                $monthly_data[$month]['orders_count']++;
                $monthly_data[$month]['revenue'] += $order->get_total();
                
                $customer_id = $order->get_customer_id();
                if ($customer_id) {
                    $monthly_data[$month]['unique_customers'][$customer_id] = true;
                }
            }
            
            // Format data for charts
            $formatted_data = array();
            foreach ($monthly_data as $month => $data) {
                $formatted_data[] = array(
                    'month' => $month,
                    'month_name' => date('M Y', strtotime($month . '-01')),
                    'orders_count' => (int) $data['orders_count'],
                    'revenue' => (float) $data['revenue'],
                    'average_order_value' => $data['orders_count'] > 0 ? (float) ($data['revenue'] / $data['orders_count']) : 0,
                    'unique_customers' => count($data['unique_customers'])
                );
            }
            
            // Sort by month
            usort($formatted_data, function($a, $b) {
                return strcmp($a['month'], $b['month']);
            });
            
            return array(
                'monthly_data' => $formatted_data
            );
            
        } catch (Exception $e) {
            error_log('Monthly Comparison Error: ' . $e->getMessage());
            return array('monthly_data' => array());
        }
    }
    
    /**
     * Get sales report by period
     */
    public function get_sales_report($request) {
        try {
            $period = $request->get_param('period') ?: 'month';
            $manager_id = $request->get_param('manager_id');
            
            // Get user permissions
            $permissions = $this->get_user_permissions();
            
            // If account manager and no specific manager_id requested, use their own ID
            if (!$permissions['is_super_admin'] && !$manager_id) {
                $manager_id = $permissions['account_manager_id'];
            }
            
            // Get date range
            $month_start = date('Y-m-01 00:00:00');
            $month_end = date('Y-m-t 23:59:59');
            
            $orders = wc_get_orders(array(
                'status' => 'any',
                'date_created' => $month_start . '...' . $month_end,
                'limit' => -1,
                'return' => 'ids'
            ));
            
            // Filter orders by manager if applicable
            if ($manager_id || !$permissions['is_super_admin']) {
                $orders = $this->filter_orders_by_manager($orders, $manager_id);
            }
            
            $sales_by_status = array();
            $top_customers = array();
            $daily_sales = array();
            
            foreach ($orders as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) continue;
                
                $status = $order->get_status();
                $date = $order->get_date_created()->format('Y-m-d');
                $total = $order->get_total();
                $customer_id = $order->get_customer_id();
                
                // Sales by status
                if (!isset($sales_by_status[$status])) {
                    $sales_by_status[$status] = array(
                        'status' => $status,
                        'orders_count' => 0,
                        'revenue' => 0
                    );
                }
                $sales_by_status[$status]['orders_count']++;
                $sales_by_status[$status]['revenue'] += $total;
                
                // Top customers (only for completed/processing orders)
                if (in_array($status, array('completed', 'processing')) && $customer_id) {
                    if (!isset($top_customers[$customer_id])) {
                        $top_customers[$customer_id] = array(
                            'customer_id' => $customer_id,
                            'orders_count' => 0,
                            'total_spent' => 0
                        );
                    }
                    $top_customers[$customer_id]['orders_count']++;
                    $top_customers[$customer_id]['total_spent'] += $total;
                }
                
                // Daily sales (only for completed/processing orders)
                if (in_array($status, array('completed', 'processing'))) {
                    if (!isset($daily_sales[$date])) {
                        $daily_sales[$date] = array(
                            'date' => $date,
                            'orders_count' => 0,
                            'revenue' => 0
                        );
                    }
                    $daily_sales[$date]['orders_count']++;
                    $daily_sales[$date]['revenue'] += $total;
                }
            }
            
            // Sort and format top customers
            uasort($top_customers, function($a, $b) {
                return $b['total_spent'] <=> $a['total_spent'];
            });
            $top_customers = array_slice($top_customers, 0, 10);
            
            $formatted_customers = array();
            foreach ($top_customers as $customer_data) {
                $customer = new WC_Customer($customer_data['customer_id']);
                if ($customer->get_id()) {
                    $formatted_customers[] = array(
                        'customer_id' => $customer_data['customer_id'],
                        'name' => $customer->get_display_name(),
                        'email' => $customer->get_email(),
                        'orders_count' => (int) $customer_data['orders_count'],
                        'total_spent' => (float) $customer_data['total_spent']
                    );
                }
            }
            
            // Sort daily sales
            ksort($daily_sales);
            
            return array(
                'sales_by_status' => array_values($sales_by_status),
                'top_customers' => $formatted_customers,
                'daily_sales' => array_values($daily_sales),
                'period' => $period
            );
            
        } catch (Exception $e) {
            error_log('Sales Report Error: ' . $e->getMessage());
            return array(
                'sales_by_status' => array(),
                'top_customers' => array(),
                'daily_sales' => array(),
                'period' => $period
            );
        }
    }
    
    /**
     * Get top selling products
     */
    public function get_top_products($request) {
        try {
            error_log('Top Products - Request params: ' . print_r($request->get_params(), true));
            
            // Get filter parameters (support both camelCase and snake_case)
            $filter_type = $request->get_param('filterType') ?: $request->get_param('filter_type') ?: 'all';
            $filter_value = $request->get_param('filterValue') ?: $request->get_param('filter_value');
            $limit = min($request->get_param('limit') ?: 10, 50);
            
            // Get user permissions
            $permissions = $this->get_user_permissions();
            $manager_id = null;
            
            // If account manager, use their own ID
            if (!$permissions['is_super_admin']) {
                $manager_id = $permissions['account_manager_id'];
            }
            
            error_log("Top Products - filter_type: {$filter_type}, filter_value: {$filter_value}, manager_id: {$manager_id}");
            
            // Calculate date range for period
            $date_range = $this->calculate_simple_date_range($filter_type, $filter_value);
            
            // Get products data for the period
            $products = $this->get_top_products_for_period(
                $date_range['start'],
                $date_range['end'],
                $limit,
                $manager_id
            );
            
            $response = array(
                'products' => $products,
                'filter_type' => $filter_type,
                'period_start' => $date_range['start'],
                'period_end' => $date_range['end']
            );
            
            return rest_ensure_response($response);
            
        } catch (Exception $e) {
            error_log('Top Products Error: ' . $e->getMessage());
            return rest_ensure_response(array(
                'products' => array(),
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get top products for a specific period
     */
    private function get_top_products_for_period($start_date, $end_date, $limit = 10, $manager_id = null) {
        global $wpdb;
        
        $product_stats = array();
        
        // If manager filter is active, get orders first and filter them
        if ($manager_id) {
            $orders = wc_get_orders(array(
                'status' => array('wc-completed', 'wc-processing'),
                'date_created' => $start_date . '...' . $end_date,
                'limit' => -1,
                'return' => 'ids'
            ));
            
            // Filter orders by manager
            $filtered_orders = $this->filter_orders_by_manager($orders, $manager_id);
            
            // If no orders, return empty
            if (empty($filtered_orders)) {
                return array();
            }
            
            // Get product stats from filtered orders
            $product_stats_map = array();
            foreach ($filtered_orders as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) continue;
                
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    if (!$product_id) continue;
                    
                    if (!isset($product_stats_map[$product_id])) {
                        $product_stats_map[$product_id] = array(
                            'product_id' => $product_id,
                            'total_quantity' => 0,
                            'total_revenue' => 0,
                            'orders_count' => 0
                        );
                    }
                    
                    $product_stats_map[$product_id]['total_quantity'] += $item->get_quantity();
                    $product_stats_map[$product_id]['total_revenue'] += $item->get_total();
                    $product_stats_map[$product_id]['orders_count']++;
                }
            }
            
            // Sort by revenue and limit
            uasort($product_stats_map, function($a, $b) {
                return $b['total_revenue'] - $a['total_revenue'];
            });
            
            $results = array_slice($product_stats_map, 0, $limit);
            
        } else {
            // No manager filter - use optimized SQL query
            // Check if HPOS is enabled
            $hpos_enabled = class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
                           method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled') &&
                           \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
            
            if ($hpos_enabled) {
                // HPOS Query
                $query = $wpdb->prepare("
                    SELECT 
                        oitemmeta_product.meta_value as product_id,
                        SUM(oitemmeta_qty.meta_value) as total_quantity,
                        SUM(oitemmeta_total.meta_value) as total_revenue,
                        COUNT(DISTINCT o.id) as orders_count
                    FROM {$wpdb->prefix}wc_orders o
                    INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.id = oi.order_id
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oitemmeta_product 
                        ON oi.order_item_id = oitemmeta_product.order_item_id 
                        AND oitemmeta_product.meta_key = '_product_id'
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oitemmeta_qty 
                        ON oi.order_item_id = oitemmeta_qty.order_item_id 
                        AND oitemmeta_qty.meta_key = '_qty'
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oitemmeta_total 
                        ON oi.order_item_id = oitemmeta_total.order_item_id 
                        AND oitemmeta_total.meta_key = '_line_total'
                    WHERE o.status IN ('wc-completed', 'wc-processing')
                        AND o.date_created_gmt >= %s
                        AND o.date_created_gmt < %s
                        AND oi.order_item_type = 'line_item'
                    GROUP BY product_id
                    ORDER BY total_revenue DESC
                    LIMIT %d
                ", $start_date, $end_date, $limit);
            } else {
                // Legacy Query
                $query = $wpdb->prepare("
                    SELECT 
                        oitemmeta_product.meta_value as product_id,
                        SUM(oitemmeta_qty.meta_value) as total_quantity,
                        SUM(oitemmeta_total.meta_value) as total_revenue,
                        COUNT(DISTINCT p.ID) as orders_count
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oitemmeta_product 
                        ON oi.order_item_id = oitemmeta_product.order_item_id 
                        AND oitemmeta_product.meta_key = '_product_id'
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oitemmeta_qty 
                        ON oi.order_item_id = oitemmeta_qty.order_item_id 
                        AND oitemmeta_qty.meta_key = '_qty'
                    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oitemmeta_total 
                        ON oi.order_item_id = oitemmeta_total.order_item_id 
                        AND oitemmeta_total.meta_key = '_line_total'
                    WHERE p.post_type = 'shop_order'
                        AND p.post_status IN ('wc-completed', 'wc-processing')
                        AND p.post_date >= %s
                        AND p.post_date < %s
                        AND oi.order_item_type = 'line_item'
                    GROUP BY product_id
                    ORDER BY total_revenue DESC
                    LIMIT %d
                ", $start_date, $end_date, $limit);
            }
            
            $results = $wpdb->get_results($query);
        }
        
        // Format with product data
        $formatted_products = array();
        foreach ($results as $row) {
            $product = wc_get_product($row->product_id);
            if ($product) {
                $formatted_products[] = array(
                    'product_id' => intval($row->product_id),
                    'product_name' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'price' => floatval($product->get_price()),
                    'total_sold' => intval($row->total_quantity),
                    'total_revenue' => floatval($row->total_revenue),
                    'orders_count' => intval($row->orders_count),
                    'image_url' => wp_get_attachment_url($product->get_image_id())
                );
            }
        }
        
        return $formatted_products;
    }
    
    /**
     * Get account manager analytics
     */
    public function get_manager_analytics($request) {
        try {
            $manager_id = $request['id'];
            $period = $request->get_param('period') ?: 'month';
            
            // Get customers assigned to this manager
            $customers = get_users(array(
                'meta_key' => 'account_manager_id',
                'meta_value' => $manager_id,
                'fields' => 'ID'
            ));
            
            if (empty($customers)) {
                return array(
                    'customers_count' => 0,
                    'total_revenue' => 0,
                    'total_orders' => 0,
                    'average_order_value' => 0,
                    'top_customers' => array(),
                    'monthly_performance' => array()
                );
            }
            
            // Get date range
            $month_start = date('Y-m-01 00:00:00');
            $month_end = date('Y-m-t 23:59:59');
            
            $orders = wc_get_orders(array(
                'status' => array('wc-completed', 'wc-processing'),
                'date_created' => $month_start . '...' . $month_end,
                'customer' => $customers,
                'limit' => -1,
                'return' => 'objects'
            ));
            
            $total_revenue = 0;
            $total_orders = count($orders);
            $customer_stats = array();
            $monthly_performance = array();
            
            foreach ($orders as $order) {
                $total_revenue += $order->get_total();
                $customer_id = $order->get_customer_id();
                $month = $order->get_date_created()->format('Y-m');
                
                // Customer stats
                if ($customer_id) {
                    if (!isset($customer_stats[$customer_id])) {
                        $customer_stats[$customer_id] = array(
                            'customer_id' => $customer_id,
                            'orders_count' => 0,
                            'total_spent' => 0
                        );
                    }
                    $customer_stats[$customer_id]['orders_count']++;
                    $customer_stats[$customer_id]['total_spent'] += $order->get_total();
                }
                
                // Monthly performance
                if (!isset($monthly_performance[$month])) {
                    $monthly_performance[$month] = array(
                        'month' => $month,
                        'orders_count' => 0,
                        'revenue' => 0
                    );
                }
                $monthly_performance[$month]['orders_count']++;
                $monthly_performance[$month]['revenue'] += $order->get_total();
            }
            
            // Top customers
            uasort($customer_stats, function($a, $b) {
                return $b['total_spent'] <=> $a['total_spent'];
            });
            $top_customers = array_slice($customer_stats, 0, 5);
            
            $formatted_top_customers = array();
            foreach ($top_customers as $customer_data) {
                $customer = new WC_Customer($customer_data['customer_id']);
                if ($customer->get_id()) {
                    $formatted_top_customers[] = array(
                        'customer_id' => $customer_data['customer_id'],
                        'name' => $customer->get_display_name(),
                        'email' => $customer->get_email(),
                        'orders_count' => (int) $customer_data['orders_count'],
                        'total_spent' => (float) $customer_data['total_spent']
                    );
                }
            }
            
            // Sort monthly performance
            ksort($monthly_performance);
            
            return array(
                'manager_id' => (int) $manager_id,
                'customers_count' => count($customers),
                'total_revenue' => (float) $total_revenue,
                'total_orders' => (int) $total_orders,
                'average_order_value' => $total_orders > 0 ? (float) ($total_revenue / $total_orders) : 0,
                'top_customers' => $formatted_top_customers,
                'monthly_performance' => array_values($monthly_performance),
                'period' => $period
            );
            
        } catch (Exception $e) {
            error_log('Manager Analytics Error: ' . $e->getMessage());
            return array(
                'customers_count' => 0,
                'total_revenue' => 0,
                'total_orders' => 0,
                'average_order_value' => 0,
                'top_customers' => array(),
                'monthly_performance' => array()
            );
        }
    }
    
    /**
     * Get date range based on period
     */
    private function get_date_range($period, $type = 'current') {
        $now = current_time('mysql', true);
        
        switch ($period) {
            case 'week':
                if ($type === 'current') {
                    return array(
                        'start' => date('Y-m-d 00:00:00', strtotime('monday this week')),
                        'end' => date('Y-m-d 23:59:59', strtotime('sunday this week'))
                    );
                } else {
                    return array(
                        'start' => date('Y-m-d 00:00:00', strtotime('monday last week')),
                        'end' => date('Y-m-d 23:59:59', strtotime('sunday last week'))
                    );
                }
                break;
                
            case 'month':
                if ($type === 'current') {
                    return array(
                        'start' => date('Y-m-01 00:00:00'),
                        'end' => date('Y-m-t 23:59:59')
                    );
                } else {
                    return array(
                        'start' => date('Y-m-01 00:00:00', strtotime('first day of last month')),
                        'end' => date('Y-m-t 23:59:59', strtotime('last day of last month'))
                    );
                }
                break;
                
            case 'year':
                if ($type === 'current') {
                    return array(
                        'start' => date('Y-01-01 00:00:00'),
                        'end' => date('Y-12-31 23:59:59')
                    );
                } else {
                    return array(
                        'start' => date('Y-01-01 00:00:00', strtotime('last year')),
                        'end' => date('Y-12-31 23:59:59', strtotime('last year'))
                    );
                }
                break;
                
            default:
                return array(
                    'start' => date('Y-m-01 00:00:00'),
                    'end' => date('Y-m-t 23:59:59')
                );
        }
    }
    
    /**
     * Get monthly revenue data with filtering and comparison support
     * Optimized version with better performance and flexible comparison logic
     */
    public function get_monthly_revenue($request) {
        try {
            // Get filter parameters (support both camelCase and snake_case)
            $filter_type = $request->get_param('filterType') ?: $request->get_param('filter_type') ?: 'year';
            $filter_value = $request->get_param('filterValue') ?: $request->get_param('filter_value');
            $compare = $request->get_param('compare') === 'true' || $request->get_param('compare') === true;
            
            // New flexible comparison parameters
            $compare_filter_type = $request->get_param('compareFilterType') ?: $request->get_param('compare_filter_type');
            $compare_filter_value = $request->get_param('compareFilterValue') ?: $request->get_param('compare_filter_value');
            
            // Get user permissions
            $permissions = $this->get_user_permissions();
            $manager_id = null;
            
            // If account manager, use their own ID
            if (!$permissions['is_super_admin']) {
                $manager_id = $permissions['account_manager_id'];
            }
            
            // Log for debugging
            error_log("Monthly Revenue Request - filter_type: {$filter_type}, filter_value: {$filter_value}, compare: " . ($compare ? 'true' : 'false') . ", manager_id: {$manager_id}");
            if ($compare) {
                error_log("Monthly Revenue - Compare with filter_type: {$compare_filter_type}, filter_value: {$compare_filter_value}");
            }
            
            // Calculate date range based on filter type
            $date_range = $this->calculate_simple_date_range($filter_type, $filter_value);
            
            if (!$date_range) {
                error_log("Monthly Revenue - Failed to calculate date range");
                return rest_ensure_response(array('data' => array()));
            }
            
            error_log("Monthly Revenue - Date range: " . $date_range['start'] . " to " . $date_range['end'] . ", group_by: " . $date_range['group_by']);
            
            // Get primary period data with limit to prevent crashes
            $primary_data = $this->get_revenue_for_period_optimized(
                $date_range['start'],
                $date_range['end'],
                $date_range['group_by'],
                $manager_id
            );
            
            error_log("Monthly Revenue - Primary data count: " . count($primary_data));
            
            // Generate period label
            $period_label = $this->format_period_label($filter_type, $filter_value);
            
            $response = array(
                'data' => $primary_data,
                'filter_type' => $filter_type,
                'period_start' => $date_range['start'],
                'period_end' => $date_range['end'],
                'period_label' => $period_label
            );
            
            // If comparison is enabled
            if ($compare) {
                // Use provided compare parameters or auto-calculate
                if (!$compare_filter_type) {
                    $compare_filter_type = $filter_type;
                }
                
                if (!$compare_filter_value) {
                    // Auto-calculate: default to last year
                    $compare_with = $request->get_param('compareWith') ?: $request->get_param('compare_with') ?: 'last_year';
                    $years_back = ($compare_with === 'two_years_ago') ? 2 : 1;
                    
                    if ($filter_type === 'year' && $filter_value) {
                        $compare_filter_value = (intval($filter_value) - $years_back);
                    } else if ($filter_type === 'month' && $filter_value) {
                        // format: YYYY-MM
                        list($year, $month) = explode('-', $filter_value);
                        $compare_year = intval($year) - $years_back;
                        $compare_filter_value = $compare_year . '-' . $month;
                    }
                    
                    error_log("Monthly Revenue - Auto-calculated compare_filter_value: {$compare_filter_value}");
                }
                
                if ($compare_filter_value) {
                    $compare_range = $this->calculate_simple_date_range($compare_filter_type, $compare_filter_value);
                    
                    if ($compare_range) {
                        error_log("Monthly Revenue - Compare range: " . $compare_range['start'] . " to " . $compare_range['end']);
                        
                        $compare_data = $this->get_revenue_for_period_optimized(
                            $compare_range['start'],
                            $compare_range['end'],
                            $compare_range['group_by'],
                            $manager_id
                        );
                        
                        error_log("Monthly Revenue - Compare data count: " . count($compare_data));
                        
                        // Generate compare period label
                        $compare_period_label = $this->format_period_label($compare_filter_type, $compare_filter_value);
                        
                        $response['compare_data'] = $compare_data;
                        $response['compare_start'] = $compare_range['start'];
                        $response['compare_end'] = $compare_range['end'];
                        $response['compare_period_label'] = $compare_period_label;
                        
                        // Calculate comparison metrics
                        $primary_total = array_sum(array_column($primary_data, 'revenue'));
                        $compare_total = array_sum(array_column($compare_data, 'revenue'));
                        $growth = $compare_total > 0 ? (($primary_total - $compare_total) / $compare_total) * 100 : 0;
                        
                        $response['comparison_summary'] = array(
                            'primary_total' => floatval($primary_total),
                            'compare_total' => floatval($compare_total),
                            'growth' => round($growth, 2),
                            'difference' => floatval($primary_total - $compare_total),
                            'period_label' => $period_label,
                            'compare_period_label' => $compare_period_label
                        );
                    }
                }
            }
            
            return rest_ensure_response($response);
            
        } catch (Exception $e) {
            error_log('Monthly Revenue Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return rest_ensure_response(array('data' => array(), 'error' => $e->getMessage()));
        }
    }
    
    /**
     * Calculate date range - simplified version
     */
    private function calculate_simple_date_range($filter_type, $filter_value) {
        $range = array();
        
        if (!$filter_value) {
            // Default to current year if no value provided
            $filter_value = date('Y');
            $filter_type = 'year';
        }
        
        switch ($filter_type) {
            case 'month':
                // Filter by specific month (format: YYYY-MM)
                $range['start'] = date('Y-m-01 00:00:00', strtotime($filter_value . '-01'));
                $range['end'] = date('Y-m-t 23:59:59', strtotime($filter_value . '-01'));
                $range['group_by'] = 'day';
                break;
                
            case 'year':
                // Filter by specific year
                $range['start'] = $filter_value . '-01-01 00:00:00';
                $range['end'] = $filter_value . '-12-31 23:59:59';
                $range['group_by'] = 'month';
                break;
                
            default:
                return null;
        }
        
        return $range;
    }
    
    /**
     * Format period label for display
     * Converts filter type and value to readable period name
     * 
     * @param string $filter_type Type of filter ('year' or 'month')
     * @param string $filter_value Filter value (e.g., '2024' or '2024-10')
     * @return string Formatted period label (e.g., '2024' or 'October 2024')
     */
    private function format_period_label($filter_type, $filter_value) {
        if (empty($filter_value)) {
            return '';
        }
        
        switch ($filter_type) {
            case 'month':
                // Format: 2024-10 → October 2024
                $date = DateTime::createFromFormat('Y-m', $filter_value);
                if ($date) {
                    return $date->format('F Y'); // e.g., "October 2024"
                }
                return $filter_value;
                
            case 'year':
                // Format: 2024 → 2024
                return $filter_value;
                
            default:
                return $filter_value;
        }
    }
    
    /**
     * Get revenue data for a specific period - optimized version
     * Uses direct database queries for better performance
     */
    private function get_revenue_for_period_optimized($start_date, $end_date, $group_by = 'month', $manager_id = null) {
        global $wpdb;
        
        try {
            // If manager filter is active, use simplified WC API approach
            if ($manager_id) {
                return $this->get_revenue_for_period_fallback($start_date, $end_date, $group_by, $manager_id);
            }
            
            // Use direct database query for better performance (super admin only)
            $table_orders = $wpdb->prefix . 'wc_orders';
            $table_meta = $wpdb->prefix . 'wc_orders_meta';
            
            // Check if tables exist (for WC HPOS)
            $hpos_enabled = get_option('woocommerce_custom_orders_table_enabled') === 'yes';
            
            if ($hpos_enabled && $wpdb->get_var("SHOW TABLES LIKE '{$table_orders}'") == $table_orders) {
                // Use HPOS tables
                $results = $this->get_revenue_hpos($start_date, $end_date, $group_by);
            } else {
                // Use posts table (legacy)
                $results = $this->get_revenue_posts($start_date, $end_date, $group_by);
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log('Revenue query error: ' . $e->getMessage());
            // Fallback to WC API method
            return $this->get_revenue_for_period_fallback($start_date, $end_date, $group_by, $manager_id);
        }
    }
    
    /**
     * Get revenue using HPOS tables
     */
    private function get_revenue_hpos($start_date, $end_date, $group_by) {
        global $wpdb;
        
        $table_orders = $wpdb->prefix . 'wc_orders';
        
        $date_format = $group_by === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $label_format = $group_by === 'month' ? '%b %Y' : '%b %d';
        
        $query = $wpdb->prepare("
            SELECT 
                DATE_FORMAT(date_created_gmt, %s) as period,
                DATE_FORMAT(date_created_gmt, %s) as label,
                SUM(total_amount) as revenue,
                COUNT(*) as orders_count
            FROM {$table_orders}
            WHERE status IN ('wc-completed', 'wc-processing')
                AND date_created_gmt >= %s
                AND date_created_gmt <= %s
            GROUP BY period
            ORDER BY period ASC
            LIMIT 1000
        ", $date_format, $label_format, $start_date, $end_date);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (!$results) {
            return array();
        }
        
        // Convert to expected format
        $formatted = array();
        foreach ($results as $row) {
            $formatted[] = array(
                'period' => $row['period'],
                'label' => $row['label'],
                'revenue' => floatval($row['revenue']),
                'orders_count' => intval($row['orders_count'])
            );
        }
        
        return $this->fill_missing_periods_optimized($formatted, $start_date, $end_date, $group_by);
    }
    
    /**
     * Get revenue using posts table (legacy)
     */
    private function get_revenue_posts($start_date, $end_date, $group_by) {
        global $wpdb;
        
        $date_format = $group_by === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $label_format = $group_by === 'month' ? '%b %Y' : '%b %d';
        
        $query = $wpdb->prepare("
            SELECT 
                DATE_FORMAT(p.post_date, %s) as period,
                DATE_FORMAT(p.post_date, %s) as label,
                SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as revenue,
                COUNT(*) as orders_count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
            WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                AND p.post_date >= %s
                AND p.post_date <= %s
            GROUP BY period
            ORDER BY period ASC
            LIMIT 1000
        ", $date_format, $label_format, $start_date, $end_date);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (!$results) {
            return array();
        }
        
        // Convert to expected format
        $formatted = array();
        foreach ($results as $row) {
            $formatted[] = array(
                'period' => $row['period'],
                'label' => $row['label'],
                'revenue' => floatval($row['revenue']),
                'orders_count' => intval($row['orders_count'])
            );
        }
        
        return $this->fill_missing_periods_optimized($formatted, $start_date, $end_date, $group_by);
    }
    
    /**
     * Fallback method using WC API (slower but more compatible)
     */
    private function get_revenue_for_period_fallback($start_date, $end_date, $group_by, $manager_id = null) {
        // Limit orders to prevent memory issues
        $orders = wc_get_orders(array(
            'status' => array('wc-completed', 'wc-processing'),
            'date_created' => $start_date . '...' . $end_date,
            'limit' => 1000, // Limit to prevent crashes
            'return' => 'ids'
        ));
        
        // Filter orders by manager if applicable
        if ($manager_id) {
            $orders = $this->filter_orders_by_manager($orders, $manager_id);
        }
        
        // Group orders by date
        $grouped_data = array();
        
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            $order_date = $order->get_date_created();
            
            if ($group_by === 'month') {
                $key = $order_date->format('Y-m');
                $label = $order_date->format('M Y');
            } else {
                $key = $order_date->format('Y-m-d');
                $label = $order_date->format('M d');
            }
            
            if (!isset($grouped_data[$key])) {
                $grouped_data[$key] = array(
                    'period' => $key,
                    'label' => $label,
                    'revenue' => 0,
                    'orders_count' => 0
                );
            }
            
            $grouped_data[$key]['revenue'] += floatval($order->get_total());
            $grouped_data[$key]['orders_count']++;
        }
        
        // Sort by period
        ksort($grouped_data);
        
        return $this->fill_missing_periods_optimized(array_values($grouped_data), $start_date, $end_date, $group_by);
    }
    
    /**
     * Fill missing periods - optimized version
     */
    private function fill_missing_periods_optimized($data, $start_date, $end_date, $group_by) {
        $existing_periods = array();
        foreach ($data as $item) {
            $existing_periods[$item['period']] = $item;
        }
        
        $results = array();
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        
        // Limit iterations to prevent infinite loops
        $max_iterations = $group_by === 'month' ? 120 : 366; // max 10 years or 1 year
        $iteration = 0;
        
        while ($current <= $end && $iteration < $max_iterations) {
            if ($group_by === 'month') {
                $key = date('Y-m', $current);
                $label = date('M Y', $current);
                $current = strtotime('+1 month', $current);
            } else {
                $key = date('Y-m-d', $current);
                $label = date('M d', $current);
                $current = strtotime('+1 day', $current);
            }
            
            if (isset($existing_periods[$key])) {
                $results[] = $existing_periods[$key];
            } else {
                $results[] = array(
                    'period' => $key,
                    'label' => $label,
                    'revenue' => 0,
                    'orders_count' => 0
                );
            }
            
            $iteration++;
        }
        
        return $results;
    }
    
    /**
     * Get sales comparison data  
     */
    public function get_sales_comparison($request) {
        try {
            $current_month_start = date('Y-m-01 00:00:00');
            $current_month_end = date('Y-m-t 23:59:59');
            $last_month_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
            $last_month_end = date('Y-m-t 23:59:59', strtotime('-1 month'));
            
            // Get current month orders
            $current_orders = wc_get_orders(array(
                'status' => array('wc-completed', 'wc-processing'),
                'date_created' => $current_month_start . '...' . $current_month_end,
                'limit' => -1,
                'return' => 'objects'
            ));
            
            $current_month_sales = 0;
            foreach ($current_orders as $order) {
                $current_month_sales += $order->get_total();
            }
            
            // Get last month orders
            $last_orders = wc_get_orders(array(
                'status' => array('wc-completed', 'wc-processing'),
                'date_created' => $last_month_start . '...' . $last_month_end,
                'limit' => -1,
                'return' => 'objects'
            ));
            
            $last_month_sales = 0;
            foreach ($last_orders as $order) {
                $last_month_sales += $order->get_total();
            }
            
            $growth = $last_month_sales > 0 ? 
                (($current_month_sales - $last_month_sales) / $last_month_sales) * 100 : 0;
            
            return rest_ensure_response(array(
                'current' => floatval($current_month_sales),
                'previous' => floatval($last_month_sales),
                'growth' => round($growth, 2)
            ));
            
        } catch (Exception $e) {
            error_log('Sales Comparison Error: ' . $e->getMessage());
            return rest_ensure_response(array(
                'current' => 0,
                'previous' => 0,
                'growth' => 0
            ));
        }
    }
    
    /**
     * Get manager performance data with filtering and comparison
     */
    public function get_manager_performance($request) {
        try {
            // Get filter parameters
            $filter_type = $request->get_param('filterType') ?: 'year';
            $filter_value = $request->get_param('filterValue') ?: date('Y');
            $start_date = $request->get_param('startDate');
            $end_date = $request->get_param('endDate');
            $compare = filter_var($request->get_param('compare'), FILTER_VALIDATE_BOOLEAN);
            $compare_filter_type = $request->get_param('compareFilterType');
            $compare_filter_value = $request->get_param('compareFilterValue');
            $compare_start_date = $request->get_param('compareStartDate');
            $compare_end_date = $request->get_param('compareEndDate');
            
            // Calculate main period date range
            $date_range = $this->calculate_simple_date_range($filter_type, $filter_value, $start_date, $end_date);
            $period_start = $date_range['start'];
            $period_end = $date_range['end'];
            
            // Get performance data for main period
            $results = $this->calculate_manager_performance($period_start, $period_end);
            
            // If comparison is enabled, calculate comparison period
            $comparison_data = null;
            if ($compare && $compare_filter_type) {
                $compare_range = $this->calculate_simple_date_range(
                    $compare_filter_type,
                    $compare_filter_value,
                    $compare_start_date,
                    $compare_end_date
                );
                
                $comparison_results = $this->calculate_manager_performance(
                    $compare_range['start'],
                    $compare_range['end']
                );
                
                // Add growth calculations
                foreach ($results as &$manager) {
                    $compare_manager = null;
                    foreach ($comparison_results as $cm) {
                        if ($cm['manager_id'] === $manager['manager_id']) {
                            $compare_manager = $cm;
                            break;
                        }
                    }
                    
                    if ($compare_manager) {
                        $manager['compare_orders'] = $compare_manager['orders_count'];
                        $manager['compare_revenue'] = $compare_manager['revenue'];
                        
                        // Calculate growth percentages
                        $manager['orders_growth'] = $compare_manager['orders_count'] > 0
                            ? (($manager['orders_count'] - $compare_manager['orders_count']) / $compare_manager['orders_count']) * 100
                            : 0;
                        
                        $manager['revenue_growth'] = $compare_manager['revenue'] > 0
                            ? (($manager['revenue'] - $compare_manager['revenue']) / $compare_manager['revenue']) * 100
                            : 0;
                    } else {
                        $manager['compare_orders'] = 0;
                        $manager['compare_revenue'] = 0;
                        $manager['orders_growth'] = 0;
                        $manager['revenue_growth'] = 0;
                    }
                }
                
                $comparison_data = $comparison_results;
            }
            
            // Format period labels
            $period_label = $this->format_statistics_period_label($filter_type, $filter_value, $start_date, $end_date);
            $compare_period_label = null;
            
            if ($compare && $compare_filter_type) {
                $compare_period_label = $this->format_statistics_period_label(
                    $compare_filter_type,
                    $compare_filter_value,
                    $compare_start_date,
                    $compare_end_date
                );
            }
            
            $response = array(
                'data' => $results,
                'period_label' => $period_label,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'filter_type' => $filter_type
            );
            
            if ($compare) {
                $response['comparison_data'] = $comparison_data;
                $response['compare_period_label'] = $compare_period_label;
                $response['compare_period_start'] = $compare_range['start'];
                $response['compare_period_end'] = $compare_range['end'];
            }
            
            return rest_ensure_response($response);
            
        } catch (Exception $e) {
            error_log('Manager Performance Error: ' . $e->getMessage());
            return rest_ensure_response(array(
                'data' => array(),
                'period_label' => '',
                'period_start' => '',
                'period_end' => '',
                'filter_type' => 'year'
            ));
        }
    }
    
    /**
     * Calculate manager performance for a given date range
     */
    private function calculate_manager_performance($period_start, $period_end) {
        $results = array();
        
        foreach ($this->managers as $manager_id => $manager_name) {
            // Get customers for this manager
            $customers = get_users(array(
                'meta_key' => '_account_manager_id',
                'meta_value' => $manager_id,
                'fields' => 'ID'
            ));
            
            $orders_count = 0;
            $revenue = 0;
            
            if (!empty($customers)) {
                $customer_ids = array_map('intval', $customers);
                
                $orders = wc_get_orders(array(
                    'status' => array('wc-completed', 'wc-processing'),
                    'date_created' => $period_start . '...' . $period_end,
                    'customer' => $customer_ids,
                    'limit' => -1,
                    'return' => 'objects'
                ));
                
                $orders_count = count($orders);
                foreach ($orders as $order) {
                    $revenue += $order->get_total();
                }
            }
            
            $results[] = array(
                'manager_id' => $manager_id,
                'manager_name' => $manager_name,
                'orders_count' => $orders_count,
                'revenue' => $revenue
            );
        }
        
        return $results;
    }
    
    /**
     * Get order statistics by category with filtering and comparison
     * Supports: month, year, date-range filters + comparison
     */
    public function get_order_statistics($request) {
        try {
            // Get filter parameters
            $filter_type = $request->get_param('filterType') ?: 'year';
            $filter_value = $request->get_param('filterValue');
            $start_date = $request->get_param('startDate');
            $end_date = $request->get_param('endDate');
            $compare = $request->get_param('compare') === 'true' || $request->get_param('compare') === true;
            $compare_filter_type = $request->get_param('compareFilterType');
            $compare_filter_value = $request->get_param('compareFilterValue');
            $compare_start_date = $request->get_param('compareStartDate');
            $compare_end_date = $request->get_param('compareEndDate');
            
            // Get user permissions
            $permissions = $this->get_user_permissions();
            $manager_id = null;
            
            // If account manager, use their own ID
            if (!$permissions['is_super_admin']) {
                $manager_id = $permissions['account_manager_id'];
            }
            
            // Get date range for primary period
            $range = null;
            if ($filter_type === 'date-range' && $start_date && $end_date) {
                $range = array(
                    'start' => $start_date . ' 00:00:00',
                    'end' => $end_date . ' 23:59:59'
                );
            } else {
                $range = $this->calculate_simple_date_range($filter_type, $filter_value);
            }
            
            if (!$range) {
                return new WP_Error('invalid_date_range', 'Invalid date range', array('status' => 400));
            }
            
            // Get primary period statistics
            $primary_stats = $this->get_category_statistics($range['start'], $range['end'], $manager_id);
            
            // Generate period label
            $period_label = $this->format_statistics_period_label($filter_type, $filter_value, $start_date, $end_date);
            
            $response = array(
                'total_orders' => $primary_stats['total_orders'],
                'total_revenue' => $primary_stats['total_revenue'],
                'categories' => $primary_stats['categories'],
                'period_start' => $range['start'],
                'period_end' => $range['end'],
                'period_label' => $period_label,
                'filter_type' => $filter_type
            );
            
            // Handle comparison if requested
            if ($compare) {
                $compare_range = null;
                
                if ($compare_filter_type === 'date-range' && $compare_start_date && $compare_end_date) {
                    $compare_range = array(
                        'start' => $compare_start_date . ' 00:00:00',
                        'end' => $compare_end_date . ' 23:59:59'
                    );
                } else if ($compare_filter_type && $compare_filter_value) {
                    $compare_range = $this->calculate_simple_date_range($compare_filter_type, $compare_filter_value);
                }
                
                if ($compare_range) {
                    $compare_stats = $this->get_category_statistics($compare_range['start'], $compare_range['end'], $manager_id);
                    $compare_period_label = $this->format_statistics_period_label($compare_filter_type, $compare_filter_value, $compare_start_date, $compare_end_date);
                    
                    // Calculate growth for each category
                    $categories_with_growth = array();
                    foreach ($primary_stats['categories'] as $category) {
                        $cat_name = $category['category'];
                        $compare_cat = null;
                        
                        // Find matching category in comparison data
                        foreach ($compare_stats['categories'] as $c) {
                            if ($c['category'] === $cat_name) {
                                $compare_cat = $c;
                                break;
                            }
                        }
                        
                        $cat_with_growth = $category;
                        
                        if ($compare_cat) {
                            // Calculate growth percentages
                            $orders_growth = 0;
                            if ($compare_cat['orders'] > 0) {
                                $orders_growth = (($category['orders'] - $compare_cat['orders']) / $compare_cat['orders']) * 100;
                            }
                            
                            $revenue_growth = 0;
                            if ($compare_cat['revenue'] > 0) {
                                $revenue_growth = (($category['revenue'] - $compare_cat['revenue']) / $compare_cat['revenue']) * 100;
                            }
                            
                            $cat_with_growth['compare_orders'] = $compare_cat['orders'];
                            $cat_with_growth['compare_revenue'] = $compare_cat['revenue'];
                            $cat_with_growth['orders_growth'] = round($orders_growth, 2);
                            $cat_with_growth['revenue_growth'] = round($revenue_growth, 2);
                        } else {
                            // Category doesn't exist in comparison period
                            $cat_with_growth['compare_orders'] = 0;
                            $cat_with_growth['compare_revenue'] = 0;
                            $cat_with_growth['orders_growth'] = 100; // 100% increase (from 0)
                            $cat_with_growth['revenue_growth'] = 100;
                        }
                        
                        $categories_with_growth[] = $cat_with_growth;
                    }
                    
                    // Add categories that exist in compare period but not in primary
                    foreach ($compare_stats['categories'] as $compare_cat) {
                        $exists = false;
                        foreach ($primary_stats['categories'] as $cat) {
                            if ($cat['category'] === $compare_cat['category']) {
                                $exists = true;
                                break;
                            }
                        }
                        
                        if (!$exists) {
                            $categories_with_growth[] = array(
                                'category' => $compare_cat['category'],
                                'category_id' => $compare_cat['category_id'],
                                'orders' => 0,
                                'revenue' => 0,
                                'compare_orders' => $compare_cat['orders'],
                                'compare_revenue' => $compare_cat['revenue'],
                                'orders_growth' => -100, // 100% decrease (to 0)
                                'revenue_growth' => -100
                            );
                        }
                    }
                    
                    $response['categories'] = $categories_with_growth;
                    $response['compare_total_orders'] = $compare_stats['total_orders'];
                    $response['compare_total_revenue'] = $compare_stats['total_revenue'];
                    $response['compare_period_start'] = $compare_range['start'];
                    $response['compare_period_end'] = $compare_range['end'];
                    $response['compare_period_label'] = $compare_period_label;
                    
                    // Overall growth
                    $total_orders_growth = 0;
                    if ($compare_stats['total_orders'] > 0) {
                        $total_orders_growth = (($primary_stats['total_orders'] - $compare_stats['total_orders']) / $compare_stats['total_orders']) * 100;
                    }
                    
                    $total_revenue_growth = 0;
                    if ($compare_stats['total_revenue'] > 0) {
                        $total_revenue_growth = (($primary_stats['total_revenue'] - $compare_stats['total_revenue']) / $compare_stats['total_revenue']) * 100;
                    }
                    
                    $response['comparison_summary'] = array(
                        'total_orders_growth' => round($total_orders_growth, 2),
                        'total_revenue_growth' => round($total_revenue_growth, 2),
                        'orders_difference' => $primary_stats['total_orders'] - $compare_stats['total_orders'],
                        'revenue_difference' => $primary_stats['total_revenue'] - $compare_stats['total_revenue'],
                        'period_label' => $period_label,
                        'compare_period_label' => $compare_period_label
                    );
                }
            }
            
            return rest_ensure_response($response);
            
        } catch (Exception $e) {
            error_log('Order Statistics Error: ' . $e->getMessage());
            return new WP_Error('server_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get category statistics for a given period
     * Optimized with direct database queries
     */
    private function get_category_statistics($start_date, $end_date, $manager_id = null) {
        global $wpdb;
        
        // If manager filter, use WC API approach
        if ($manager_id) {
            return $this->get_category_statistics_filtered($start_date, $end_date, $manager_id);
        }
        
        $hpos_enabled = get_option('woocommerce_custom_orders_table_enabled') === 'yes';
        
        if ($hpos_enabled) {
            return $this->get_category_statistics_hpos($start_date, $end_date);
        } else {
            return $this->get_category_statistics_posts($start_date, $end_date);
        }
    }
    
    /**
     * Get category statistics with manager filtering (using WC API)
     */
    private function get_category_statistics_filtered($start_date, $end_date, $manager_id) {
        $orders = wc_get_orders(array(
            'status' => array('wc-completed', 'wc-processing'),
            'date_created' => $start_date . '...' . $end_date,
            'limit' => -1,
            'return' => 'ids'
        ));
        
        // Filter orders by manager
        $filtered_orders = $this->filter_orders_by_manager($orders, $manager_id);
        
        $category_stats = array();
        $total_orders = 0;
        $total_revenue = 0;
        
        foreach ($filtered_orders as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            $total_orders++;
            $total_revenue += $order->get_total();
            
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) continue;
                
                $category_ids = $product->get_category_ids();
                foreach ($category_ids as $cat_id) {
                    $category = get_term($cat_id, 'product_cat');
                    if (!$category || is_wp_error($category)) continue;
                    
                    $cat_name = $category->name;
                    
                    if (!isset($category_stats[$cat_name])) {
                        $category_stats[$cat_name] = array(
                            'category' => $cat_name,
                            'orders' => 0,
                            'revenue' => 0
                        );
                    }
                    
                    $category_stats[$cat_name]['orders']++;
                    $category_stats[$cat_name]['revenue'] += $item->get_total();
                }
            }
        }
        
        // Sort by revenue descending
        uasort($category_stats, function($a, $b) {
            return $b['revenue'] - $a['revenue'];
        });
        
        return array(
            'total_orders' => $total_orders,
            'total_revenue' => $total_revenue,
            'categories' => array_values($category_stats)
        );
    }
    
    /**
     * Get category statistics using HPOS tables
     */
    private function get_category_statistics_hpos($start_date, $end_date) {
        global $wpdb;
        
        $table_orders = $wpdb->prefix . 'wc_orders';
        $table_items = $wpdb->prefix . 'woocommerce_order_items';
        $table_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';
        
        // Get order IDs in date range with completed/processing status
        $order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT id 
            FROM {$table_orders}
            WHERE date_created_gmt >= %s 
            AND date_created_gmt <= %s
            AND status IN ('wc-completed', 'wc-processing')
        ", $start_date, $end_date));
        
        if (empty($order_ids)) {
            return array(
                'total_orders' => 0,
                'total_revenue' => 0,
                'categories' => array()
            );
        }
        
        $order_ids_str = implode(',', array_map('intval', $order_ids));
        
        // Get total orders and revenue
        $totals = $wpdb->get_row("
            SELECT 
                COUNT(DISTINCT id) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue
            FROM {$table_orders}
            WHERE id IN ($order_ids_str)
        ");
        
        // Get products from order items with order info
        $products_data = $wpdb->get_results("
            SELECT 
                oi.order_id,
                oi.order_item_id,
                im1.meta_value as product_id,
                im2.meta_value as line_total,
                im3.meta_value as quantity
            FROM {$table_items} oi
            LEFT JOIN {$table_itemmeta} im1 ON oi.order_item_id = im1.order_item_id AND im1.meta_key = '_product_id'
            LEFT JOIN {$table_itemmeta} im2 ON oi.order_item_id = im2.order_item_id AND im2.meta_key = '_line_total'
            LEFT JOIN {$table_itemmeta} im3 ON oi.order_item_id = im3.order_item_id AND im3.meta_key = '_qty'
            WHERE oi.order_id IN ($order_ids_str)
            AND oi.order_item_type = 'line_item'
        ");
        
        // Group by category
        $categories_map = array();
        
        foreach ($products_data as $item) {
            $product_id = intval($item->product_id);
            $order_id = intval($item->order_id);
            $line_total = floatval($item->line_total);
            
            if ($product_id > 0) {
                // Get product categories
                $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'all'));
                
                if (!empty($terms) && !is_wp_error($terms)) {
                    // Loop through ALL categories of this product
                    foreach ($terms as $term) {
                        $cat_name = $term->name;
                        $cat_id = $term->term_id;
                        
                        if (!isset($categories_map[$cat_name])) {
                            $categories_map[$cat_name] = array(
                                'category' => $cat_name,
                                'category_id' => $cat_id,
                                'orders' => 0,
                                'revenue' => 0,
                                'order_ids' => array()
                            );
                        }
                        
                        // Count unique orders per category
                        if (!in_array($order_id, $categories_map[$cat_name]['order_ids'])) {
                            $categories_map[$cat_name]['order_ids'][] = $order_id;
                            $categories_map[$cat_name]['orders']++;
                        }
                        
                        // Add line total (revenue from this product only)
                        $categories_map[$cat_name]['revenue'] += $line_total;
                    }
                }
            }
        }
        
        // Format categories
        $categories = array();
        foreach ($categories_map as $cat_data) {
            unset($cat_data['order_ids']); // Remove internal tracking
            $categories[] = $cat_data;
        }
        
        // Sort by orders count
        usort($categories, function($a, $b) {
            return $b['orders'] - $a['orders'];
        });
        
        return array(
            'total_orders' => intval($totals->total_orders),
            'total_revenue' => floatval($totals->total_revenue),
            'categories' => $categories
        );
    }
    
    /**
     * Get category statistics using Posts table (legacy)
     */
    private function get_category_statistics_posts($start_date, $end_date) {
        global $wpdb;
        
        // Get orders in date range
        $order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT ID 
            FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order'
            AND post_status IN ('wc-completed', 'wc-processing')
            AND post_date >= %s 
            AND post_date <= %s
        ", $start_date, $end_date));
        
        if (empty($order_ids)) {
            return array(
                'total_orders' => 0,
                'total_revenue' => 0,
                'categories' => array()
            );
        }
        
        $order_ids_str = implode(',', array_map('intval', $order_ids));
        
        // Get total revenue
        $total_revenue = $wpdb->get_var("
            SELECT COALESCE(SUM(meta_value), 0)
            FROM {$wpdb->postmeta}
            WHERE post_id IN ($order_ids_str)
            AND meta_key = '_order_total'
        ");
        
        // Get products from order items with line totals
        $table_items = $wpdb->prefix . 'woocommerce_order_items';
        $table_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';
        
        $products_data = $wpdb->get_results("
            SELECT 
                oi.order_item_id,
                oi.order_id,
                im1.meta_value as product_id,
                im2.meta_value as line_total,
                im3.meta_value as quantity
            FROM {$table_items} oi
            LEFT JOIN {$table_itemmeta} im1 ON oi.order_item_id = im1.order_item_id AND im1.meta_key = '_product_id'
            LEFT JOIN {$table_itemmeta} im2 ON oi.order_item_id = im2.order_item_id AND im2.meta_key = '_line_total'
            LEFT JOIN {$table_itemmeta} im3 ON oi.order_item_id = im3.order_item_id AND im3.meta_key = '_qty'
            WHERE oi.order_id IN ($order_ids_str)
            AND oi.order_item_type = 'line_item'
        ");
        
        // Group by category
        $categories_map = array();
        
        foreach ($products_data as $item) {
            $product_id = intval($item->product_id);
            $order_id = intval($item->order_id);
            $line_total = floatval($item->line_total);
            
            if ($product_id > 0) {
                $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'all'));
                
                if (!empty($terms) && !is_wp_error($terms)) {
                    // Loop through ALL categories of this product
                    foreach ($terms as $term) {
                        $cat_name = $term->name;
                        $cat_id = $term->term_id;
                        
                        if (!isset($categories_map[$cat_name])) {
                            $categories_map[$cat_name] = array(
                                'category' => $cat_name,
                                'category_id' => $cat_id,
                                'orders' => 0,
                                'revenue' => 0,
                                'order_ids' => array()
                            );
                        }
                        
                        // Count unique orders per category
                        if (!in_array($order_id, $categories_map[$cat_name]['order_ids'])) {
                            $categories_map[$cat_name]['order_ids'][] = $order_id;
                            $categories_map[$cat_name]['orders']++;
                        }
                        
                        // Add line total (revenue from this product only)
                        $categories_map[$cat_name]['revenue'] += $line_total;
                    }
                }
            }
        }
        
        $categories = array();
        foreach ($categories_map as $cat_data) {
            unset($cat_data['order_ids']);
            $categories[] = $cat_data;
        }
        
        usort($categories, function($a, $b) {
            return $b['orders'] - $a['orders'];
        });
        
        return array(
            'total_orders' => count($order_ids),
            'total_revenue' => floatval($total_revenue),
            'categories' => $categories
        );
    }
    
    /**
     * Format period label for order statistics
     */
    private function format_statistics_period_label($filter_type, $filter_value, $start_date = null, $end_date = null) {
        if ($filter_type === 'date-range' && $start_date && $end_date) {
            $start = DateTime::createFromFormat('Y-m-d', $start_date);
            $end = DateTime::createFromFormat('Y-m-d', $end_date);
            if ($start && $end) {
                return $start->format('M d, Y') . ' - ' . $end->format('M d, Y');
            }
            return $start_date . ' - ' . $end_date;
        }
        
        return $this->format_period_label($filter_type, $filter_value);
    }
    
    /**
     * Get customer statistics with filtering and comparison
     */
    public function get_customer_statistics($request) {
        try {
            $customer_id = $request['id'];
            
            // Get filter parameters
            $filter_type = $request->get_param('filterType') ?: 'year';
            $filter_value = $request->get_param('filterValue') ?: date('Y');
            $start_date = $request->get_param('startDate');
            $end_date = $request->get_param('endDate');
            $compare = filter_var($request->get_param('compare'), FILTER_VALIDATE_BOOLEAN);
            $compare_filter_type = $request->get_param('compareFilterType');
            $compare_filter_value = $request->get_param('compareFilterValue');
            $compare_start_date = $request->get_param('compareStartDate');
            $compare_end_date = $request->get_param('compareEndDate');
            
            // Calculate main period date range
            $date_range = $this->calculate_simple_date_range($filter_type, $filter_value, $start_date, $end_date);
            $period_start = $date_range['start'];
            $period_end = $date_range['end'];
            
            // Get statistics for main period
            $stats = $this->calculate_customer_statistics($customer_id, $period_start, $period_end);
            
            // If comparison is enabled
            if ($compare && $compare_filter_type) {
                $compare_range = $this->calculate_simple_date_range(
                    $compare_filter_type,
                    $compare_filter_value,
                    $compare_start_date,
                    $compare_end_date
                );
                
                $compare_stats = $this->calculate_customer_statistics(
                    $customer_id,
                    $compare_range['start'],
                    $compare_range['end']
                );
                
                // Calculate growth percentages
                $stats['compare_total_orders'] = $compare_stats['total_orders'];
                $stats['compare_total_spent'] = $compare_stats['total_spent'];
                $stats['compare_avg_order_value'] = $compare_stats['avg_order_value'];
                
                $stats['orders_growth'] = $compare_stats['total_orders'] > 0
                    ? (($stats['total_orders'] - $compare_stats['total_orders']) / $compare_stats['total_orders']) * 100
                    : 0;
                
                $stats['revenue_growth'] = $compare_stats['total_spent'] > 0
                    ? (($stats['total_spent'] - $compare_stats['total_spent']) / $compare_stats['total_spent']) * 100
                    : 0;
                
                $stats['aov_growth'] = $compare_stats['avg_order_value'] > 0
                    ? (($stats['avg_order_value'] - $compare_stats['avg_order_value']) / $compare_stats['avg_order_value']) * 100
                    : 0;
                
                $stats['compare_period_label'] = $this->format_statistics_period_label(
                    $compare_filter_type,
                    $compare_filter_value,
                    $compare_start_date,
                    $compare_end_date
                );
            }
            
            $stats['period_label'] = $this->format_statistics_period_label($filter_type, $filter_value, $start_date, $end_date);
            $stats['period_start'] = $period_start;
            $stats['period_end'] = $period_end;
            $stats['filter_type'] = $filter_type;
            
            return rest_ensure_response($stats);
            
        } catch (Exception $e) {
            error_log('Customer Statistics Error: ' . $e->getMessage());
            return new WP_Error('stats_error', 'Failed to fetch customer statistics', array('status' => 500));
        }
    }
    
    /**
     * Calculate customer statistics for a date range
     */
    private function calculate_customer_statistics($customer_id, $period_start, $period_end) {
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'status' => array('wc-completed', 'wc-processing'),
            'date_created' => $period_start . '...' . $period_end,
            'limit' => -1,
            'return' => 'objects'
        ));
        
        $total_orders = count($orders);
        $total_spent = 0;
        $last_order_date = null;
        
        foreach ($orders as $order) {
            $total_spent += $order->get_total();
            if (!$last_order_date || $order->get_date_created() > $last_order_date) {
                $last_order_date = $order->get_date_created();
            }
        }
        
        $avg_order_value = $total_orders > 0 ? $total_spent / $total_orders : 0;
        
        return array(
            'total_orders' => $total_orders,
            'total_spent' => $total_spent,
            'avg_order_value' => $avg_order_value,
            'last_order_date' => $last_order_date ? $last_order_date->date('Y-m-d H:i:s') : null
        );
    }
    
    /**
     * Get customer categories analytics (Top/Bottom categories with comparison)
     */
    public function get_customer_categories($request) {
        global $wpdb;
        
        try {
            $customer_id = $request['id'];
            
            // Get filter parameters
            $filter_type = $request->get_param('filterType') ?: 'year';
            $filter_value = $request->get_param('filterValue') ?: date('Y');
            $start_date = $request->get_param('startDate');
            $end_date = $request->get_param('endDate');
            $compare = filter_var($request->get_param('compare'), FILTER_VALIDATE_BOOLEAN);
            $compare_filter_type = $request->get_param('compareFilterType');
            $compare_filter_value = $request->get_param('compareFilterValue');
            $compare_start_date = $request->get_param('compareStartDate');
            $compare_end_date = $request->get_param('compareEndDate');
            $limit = intval($request->get_param('limit')) ?: 10;
            
            // Calculate main period date range
            $date_range = $this->calculate_simple_date_range($filter_type, $filter_value, $start_date, $end_date);
            $period_start = $date_range['start'];
            $period_end = $date_range['end'];
            
            // Get categories for main period
            $categories = $this->calculate_customer_categories($customer_id, $period_start, $period_end);
            
            // If comparison is enabled
            if ($compare && $compare_filter_type) {
                $compare_range = $this->calculate_simple_date_range(
                    $compare_filter_type,
                    $compare_filter_value,
                    $compare_start_date,
                    $compare_end_date
                );
                
                $compare_categories = $this->calculate_customer_categories(
                    $customer_id,
                    $compare_range['start'],
                    $compare_range['end']
                );
                
                // Merge and calculate growth
                foreach ($categories as &$category) {
                    $compare_cat = null;
                    foreach ($compare_categories as $cc) {
                        if ($cc['category_id'] === $category['category_id']) {
                            $compare_cat = $cc;
                            break;
                        }
                    }
                    
                    if ($compare_cat) {
                        $category['compare_orders'] = $compare_cat['orders'];
                        $category['compare_revenue'] = $compare_cat['revenue'];
                        
                        $category['orders_growth'] = $compare_cat['orders'] > 0
                            ? (($category['orders'] - $compare_cat['orders']) / $compare_cat['orders']) * 100
                            : 0;
                        
                        $category['revenue_growth'] = $compare_cat['revenue'] > 0
                            ? (($category['revenue'] - $compare_cat['revenue']) / $compare_cat['revenue']) * 100
                            : 0;
                    } else {
                        $category['compare_orders'] = 0;
                        $category['compare_revenue'] = 0;
                        $category['orders_growth'] = 0;
                        $category['revenue_growth'] = 0;
                    }
                }
            }
            
            // Sort by orders (descending)
            usort($categories, function($a, $b) {
                return $b['orders'] - $a['orders'];
            });
            
            $top_categories = array_slice($categories, 0, $limit);
            $bottom_categories = array_slice(array_reverse($categories), 0, $limit);
            
            $response = array(
                'top_categories' => $top_categories,
                'bottom_categories' => array_reverse($bottom_categories),
                'period_label' => $this->format_statistics_period_label($filter_type, $filter_value, $start_date, $end_date),
                'period_start' => $period_start,
                'period_end' => $period_end,
                'filter_type' => $filter_type
            );
            
            if ($compare && $compare_filter_type) {
                $response['compare_period_label'] = $this->format_statistics_period_label(
                    $compare_filter_type,
                    $compare_filter_value,
                    $compare_start_date,
                    $compare_end_date
                );
            }
            
            return rest_ensure_response($response);
            
        } catch (Exception $e) {
            error_log('Customer Categories Error: ' . $e->getMessage());
            return new WP_Error('categories_error', 'Failed to fetch customer categories', array('status' => 500));
        }
    }
    
    /**
     * Calculate customer categories for a date range
     */
    private function calculate_customer_categories($customer_id, $period_start, $period_end) {
        global $wpdb;
        
        // Get customer orders
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'status' => array('wc-completed', 'wc-processing'),
            'date_created' => $period_start . '...' . $period_end,
            'limit' => -1,
            'return' => 'ids'
        ));
        
        if (empty($orders)) {
            return array();
        }
        
        $order_ids_str = implode(',', array_map('intval', $orders));
        
        // Check if using HPOS
        $using_hpos = class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil') && 
                      \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        
        if ($using_hpos) {
            return $this->get_customer_categories_hpos($order_ids_str);
        } else {
            return $this->get_customer_categories_posts($order_ids_str);
        }
    }
    
    /**
     * Get customer categories using HPOS tables
     */
    private function get_customer_categories_hpos($order_ids_str) {
        global $wpdb;
        
        $table_items = $wpdb->prefix . 'woocommerce_order_items';
        $table_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';
        
        $products_data = $wpdb->get_results("
            SELECT 
                oi.order_item_id,
                oi.order_id,
                im1.meta_value as product_id,
                im2.meta_value as line_total
            FROM {$table_items} oi
            LEFT JOIN {$table_itemmeta} im1 ON oi.order_item_id = im1.order_item_id AND im1.meta_key = '_product_id'
            LEFT JOIN {$table_itemmeta} im2 ON oi.order_item_id = im2.order_item_id AND im2.meta_key = '_line_total'
            WHERE oi.order_id IN ($order_ids_str)
            AND oi.order_item_type = 'line_item'
        ");
        
        return $this->process_categories_data($products_data);
    }
    
    /**
     * Get customer categories using legacy posts tables
     */
    private function get_customer_categories_posts($order_ids_str) {
        global $wpdb;
        
        $table_items = $wpdb->prefix . 'woocommerce_order_items';
        $table_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';
        
        $products_data = $wpdb->get_results("
            SELECT 
                oi.order_item_id,
                oi.order_id,
                im1.meta_value as product_id,
                im2.meta_value as line_total
            FROM {$table_items} oi
            LEFT JOIN {$table_itemmeta} im1 ON oi.order_item_id = im1.order_item_id AND im1.meta_key = '_product_id'
            LEFT JOIN {$table_itemmeta} im2 ON oi.order_item_id = im2.order_item_id AND im2.meta_key = '_line_total'
            WHERE oi.order_id IN ($order_ids_str)
            AND oi.order_item_type = 'line_item'
        ");
        
        return $this->process_categories_data($products_data);
    }
    
    /**
     * Process categories data from products
     */
    private function process_categories_data($products_data) {
        $categories_map = array();
        
        foreach ($products_data as $item) {
            $product_id = intval($item->product_id);
            $order_id = intval($item->order_id);
            $line_total = floatval($item->line_total);
            
            if ($product_id > 0) {
                $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'all'));
                
                if (!empty($terms) && !is_wp_error($terms)) {
                    // Count each category for this product
                    foreach ($terms as $term) {
                        $cat_name = $term->name;
                        $cat_id = $term->term_id;
                        
                        if (!isset($categories_map[$cat_name])) {
                            $categories_map[$cat_name] = array(
                                'category' => $cat_name,
                                'category_id' => $cat_id,
                                'orders' => 0,
                                'revenue' => 0,
                                'order_ids' => array()
                            );
                        }
                        
                        // Count unique orders per category
                        if (!in_array($order_id, $categories_map[$cat_name]['order_ids'])) {
                            $categories_map[$cat_name]['order_ids'][] = $order_id;
                            $categories_map[$cat_name]['orders']++;
                        }
                        
                        // Add revenue
                        $categories_map[$cat_name]['revenue'] += $line_total;
                    }
                }
            }
        }
        
        $categories = array();
        foreach ($categories_map as $cat_data) {
            unset($cat_data['order_ids']);
            $categories[] = $cat_data;
        }
        
        return $categories;
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
