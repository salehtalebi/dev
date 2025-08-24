<?php

/**
 * Analytics Class
 */
class Sales_Dashboard_Analytics {
    
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
        // Dashboard analytics
        register_rest_route('sales-dashboard/v1', '/analytics/dashboard', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_dashboard_analytics'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Monthly comparison
        register_rest_route('sales-dashboard/v1', '/analytics/monthly-comparison', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_monthly_comparison'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Sales report by period
        register_rest_route('sales-dashboard/v1', '/analytics/sales-report', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_sales_report'),
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
        
        // Account manager analytics
        register_rest_route('sales-dashboard/v1', '/analytics/manager/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_manager_analytics'),
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
            
            // Simple stats without complex queries first
            $current_month_start = date('Y-m-01 00:00:00');
            $current_month_end = date('Y-m-t 23:59:59');
            
            // Get basic stats using limited queries to avoid memory issues
            $current_orders = wc_get_orders(array(
                'status' => array('wc-completed', 'wc-processing'),
                'date_created' => $current_month_start . '...' . $current_month_end,
                'limit' => 1000, // Limit to avoid memory exhaustion
                'return' => 'objects'
            ));
            
            $total_orders = count($current_orders);
            $total_revenue = 0;
            $customer_ids = array();
            
            foreach ($current_orders as $order) {
                $total_revenue += $order->get_total();
                if ($order->get_customer_id() > 0) {
                    $customer_ids[] = $order->get_customer_id();
                }
            }
            
            $average_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
            $new_customers = count(array_unique($customer_ids));
            
            // Get previous month for growth calculation (also limited)
            $prev_month_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
            $prev_month_end = date('Y-m-t 23:59:59', strtotime('-1 month'));
            
            $prev_orders = wc_get_orders(array(
                'status' => array('wc-completed', 'wc-processing'),
                'date_created' => $prev_month_start . '...' . $prev_month_end,
                'limit' => 1000, // Also limit previous month
                'return' => 'objects'
            ));
            
            $prev_revenue = 0;
            foreach ($prev_orders as $order) {
                $prev_revenue += $order->get_total();
            }
            
            $growth_percentage = $prev_revenue > 0 ? 
                (($total_revenue - $prev_revenue) / $prev_revenue) * 100 : 0;
            
            // Clear memory
            unset($current_orders, $prev_orders);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            $result = array(
                'todayOrders' => $total_orders, // Frontend expects this key
                'todayRevenue' => $total_revenue, // Frontend expects this key
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
            return new WP_Error('analytics_error', 'خطا در دریافت آمار داشبورد: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get monthly comparison data
     */
    public function get_monthly_comparison($request) {
        try {
            $manager_id = $request->get_param('manager_id');
            $months_count = min($request->get_param('months') ?: 12, 24);
            
            // Get date range
            $start_date = date('Y-m-01', strtotime("-{$months_count} months"));
            $end_date = date('Y-m-t');
            
            $orders = wc_get_orders(array(
                'status' => array('wc-completed', 'wc-processing'),
                'date_created' => $start_date . '...' . $end_date,
                'limit' => -1,
                'return' => 'objects'
            ));
            
            $monthly_data = array();
            
            foreach ($orders as $order) {
                $month = $order->get_date_created()->format('Y-m');
                
                // Skip if manager filter is set and customer is not assigned
                if ($manager_id) {
                    $customer_id = $order->get_customer_id();
                    if (!$customer_id) continue;
                    
                    $customer_manager = get_user_meta($customer_id, 'account_manager_id', true);
                    if ($customer_manager != $manager_id) continue;
                }
                
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
            
            // Get date range
            $month_start = date('Y-m-01 00:00:00');
            $month_end = date('Y-m-t 23:59:59');
            
            $orders = wc_get_orders(array(
                'status' => 'any',
                'date_created' => $month_start . '...' . $month_end,
                'limit' => -1,
                'return' => 'objects'
            ));
            
            $sales_by_status = array();
            $top_customers = array();
            $daily_sales = array();
            
            foreach ($orders as $order) {
                // Skip if manager filter is set and customer is not assigned
                if ($manager_id) {
                    $customer_id = $order->get_customer_id();
                    if ($customer_id) {
                        $customer_manager = get_user_meta($customer_id, 'account_manager_id', true);
                        if ($customer_manager != $manager_id) continue;
                    }
                }
                
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
            $period = $request->get_param('period') ?: 'month';
            $limit = min($request->get_param('limit') ?: 10, 10); // Limit to max 10 products
            
            // Get orders for current month only (less memory usage)
            $month_start = date('Y-m-01 00:00:00');
            $month_end = date('Y-m-t 23:59:59');
            
            // Use paginated approach to avoid memory exhaustion
            $orders = wc_get_orders(array(
                'status' => array('wc-completed', 'wc-processing'),
                'date_created' => $month_start . '...' . $month_end,
                'limit' => 500, // Process in batches of 500
                'return' => 'objects',
                'paginate' => true
            ));
            
            $product_stats = array();
            $page = 1;
            
            do {
                $orders = wc_get_orders(array(
                    'status' => array('wc-completed', 'wc-processing'),
                    'date_created' => $month_start . '...' . $month_end,
                    'limit' => 500,
                    'page' => $page,
                    'return' => 'objects'
                ));
                
                foreach ($orders as $order) {
                    foreach ($order->get_items() as $item) {
                        $product_id = $item->get_product_id();
                        $quantity = $item->get_quantity();
                        $total = $item->get_total();
                        
                        if (!isset($product_stats[$product_id])) {
                            $product_stats[$product_id] = array(
                                'product_id' => $product_id,
                                'total_quantity' => 0,
                                'total_revenue' => 0,
                                'orders_count' => 0
                            );
                        }
                        
                        $product_stats[$product_id]['total_quantity'] += $quantity;
                        $product_stats[$product_id]['total_revenue'] += $total;
                        $product_stats[$product_id]['orders_count']++;
                    }
                }
                
                $page++;
                
                // Clear memory after each batch
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                
            } while (count($orders) === 500 && $page <= 5); // Max 5 pages (2500 orders)
            
            // Sort by revenue and limit
            uasort($product_stats, function($a, $b) {
                return $b['total_revenue'] <=> $a['total_revenue'];
            });
            
            $product_stats = array_slice($product_stats, 0, $limit);
            
            // Format with product data
            $formatted_products = array();
            foreach ($product_stats as $product_data) {
                $product = wc_get_product($product_data['product_id']);
                if ($product) {
                    $formatted_products[] = array(
                        'product_id' => $product_data['product_id'],
                        'name' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'price' => $product->get_price(),
                        'total_quantity' => (int) $product_data['total_quantity'],
                        'total_revenue' => (float) $product_data['total_revenue'],
                        'orders_count' => (int) $product_data['orders_count'],
                        'image_url' => wp_get_attachment_url($product->get_image_id())
                    );
                }
            }
            
            return array(
                'top_products' => $formatted_products,
                'period' => $period
            );
            
        } catch (Exception $e) {
            error_log('Top Products Error: ' . $e->getMessage());
            return array(
                'top_products' => array(),
                'period' => $period
            );
        }
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
     * Get monthly revenue data
     */
    public function get_monthly_revenue($request) {
        try {
            $months = intval($request->get_param('months') ?: 12);
            
            // Use WC_Order_Query for better compatibility
            $results = array();
            
            for ($i = 0; $i < $months; $i++) {
                $month_start = date('Y-m-01 00:00:00', strtotime("-$i months"));
                $month_end = date('Y-m-t 23:59:59', strtotime("-$i months"));
                $month_key = date('Y-m', strtotime("-$i months"));
                
                $orders = wc_get_orders(array(
                    'status' => array('wc-completed', 'wc-processing'),
                    'date_created' => $month_start . '...' . $month_end,
                    'limit' => -1,
                    'return' => 'objects'
                ));
                
                $revenue = 0;
                foreach ($orders as $order) {
                    $revenue += $order->get_total();
                }
                
                $results[] = array(
                    'month' => $month_key,
                    'revenue' => $revenue
                );
            }
            
            // Reverse to get chronological order
            $results = array_reverse($results);
            
            return rest_ensure_response(array('data' => $results));
            
        } catch (Exception $e) {
            error_log('Monthly Revenue Error: ' . $e->getMessage());
            return rest_ensure_response(array('data' => array()));
        }
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
     * Get manager performance data
     */
    public function get_manager_performance($request) {
        try {
            $results = array();
            
            // Date range for last month
            $month_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
            $month_end = date('Y-m-t 23:59:59', strtotime('-1 month'));
            
            foreach ($this->managers as $manager_id => $manager_name) {
                // Get customers for this manager (simplified - assuming user meta)
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
                        'date_created' => $month_start . '...' . $month_end,
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
            
            return rest_ensure_response(array('data' => $results));
            
        } catch (Exception $e) {
            error_log('Manager Performance Error: ' . $e->getMessage());
            return rest_ensure_response(array('data' => array()));
        }
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
