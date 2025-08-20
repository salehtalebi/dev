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
        global $wpdb;
        
        $period = $request->get_param('period') ?: 'month';
        $manager_id = $request->get_param('manager_id');
        
        $cache_key = 'sales_dashboard_analytics_' . $period . '_' . ($manager_id ?: 'all');
        $cached_data = wp_cache_get($cache_key, 'sales_dashboard');
        
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Base conditions
        $where_conditions = array("status IN ('wc-completed', 'wc-processing')");
        $customer_filter = '';
        
        if ($manager_id) {
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE manager_id = %d
            ", $manager_id));
            
            if (!empty($customer_ids)) {
                $customer_ids_str = implode(',', array_map('intval', $customer_ids));
                $where_conditions[] = "customer_id IN ($customer_ids_str)";
                $customer_filter = "AND customer_id IN ($customer_ids_str)";
            } else {
                $empty_result = array(
                    'total_revenue' => 0,
                    'total_orders' => 0,
                    'new_customers' => 0,
                    'average_order_value' => 0,
                    'growth_percentage' => 0,
                    'period' => $period
                );
                wp_cache_set($cache_key, $empty_result, 'sales_dashboard', 300);
                return $empty_result;
            }
        }
        
        // Date ranges
        $current_period = $this->get_date_range($period, 'current');
        $previous_period = $this->get_date_range($period, 'previous');
        
        // Current period stats
        $current_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as average_order_value
            FROM {$wpdb->prefix}wc_orders 
            WHERE " . implode(' AND ', $where_conditions) . "
            AND date_created_gmt >= %s 
            AND date_created_gmt < %s
        ", $current_period['start'], $current_period['end']));
        
        // Previous period stats for comparison
        $previous_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue
            FROM {$wpdb->prefix}wc_orders 
            WHERE " . implode(' AND ', $where_conditions) . "
            AND date_created_gmt >= %s 
            AND date_created_gmt < %s
        ", $previous_period['start'], $previous_period['end']));
        
        // New customers count
        $new_customers = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT customer_id)
            FROM {$wpdb->prefix}wc_orders 
            WHERE customer_id > 0
            AND date_created_gmt >= %s 
            AND date_created_gmt < %s
            AND customer_id NOT IN (
                SELECT DISTINCT customer_id 
                FROM {$wpdb->prefix}wc_orders 
                WHERE customer_id > 0 
                AND date_created_gmt < %s
            )
            $customer_filter
        ", $current_period['start'], $current_period['end'], $current_period['start']));
        
        // Calculate growth percentage
        $growth_percentage = 0;
        if ($previous_stats->total_revenue > 0) {
            $growth_percentage = (($current_stats->total_revenue - $previous_stats->total_revenue) / $previous_stats->total_revenue) * 100;
        }
        
        $result = array(
            'total_revenue' => (float) $current_stats->total_revenue,
            'total_orders' => (int) $current_stats->total_orders,
            'new_customers' => (int) $new_customers,
            'average_order_value' => (float) $current_stats->average_order_value,
            'growth_percentage' => round($growth_percentage, 2),
            'previous_period' => array(
                'total_revenue' => (float) $previous_stats->total_revenue,
                'total_orders' => (int) $previous_stats->total_orders
            ),
            'period' => $period
        );
        
        wp_cache_set($cache_key, $result, 'sales_dashboard', 300); // Cache for 5 minutes
        return $result;
    }
    
    /**
     * Get monthly comparison data
     */
    public function get_monthly_comparison($request) {
        global $wpdb;
        
        $manager_id = $request->get_param('manager_id');
        $months_count = min($request->get_param('months') ?: 12, 24);
        
        $customer_filter = '';
        if ($manager_id) {
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE manager_id = %d
            ", $manager_id));
            
            if (!empty($customer_ids)) {
                $customer_ids_str = implode(',', array_map('intval', $customer_ids));
                $customer_filter = "AND customer_id IN ($customer_ids_str)";
            } else {
                return array('monthly_data' => array());
            }
        }
        
        $monthly_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE_FORMAT(date_created_gmt, '%%Y-%%m') as month,
                COUNT(*) as orders_count,
                COALESCE(SUM(total_amount), 0) as revenue,
                COALESCE(AVG(total_amount), 0) as average_order_value,
                COUNT(DISTINCT customer_id) as unique_customers
            FROM {$wpdb->prefix}wc_orders 
            WHERE status IN ('wc-completed', 'wc-processing')
            AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL %d MONTH)
            $customer_filter
            GROUP BY DATE_FORMAT(date_created_gmt, '%%Y-%%m')
            ORDER BY month ASC
        ", $months_count));
        
        // Format data for charts
        $formatted_data = array();
        foreach ($monthly_data as $data) {
            $formatted_data[] = array(
                'month' => $data->month,
                'month_name' => date('M Y', strtotime($data->month . '-01')),
                'orders_count' => (int) $data->orders_count,
                'revenue' => (float) $data->revenue,
                'average_order_value' => (float) $data->average_order_value,
                'unique_customers' => (int) $data->unique_customers
            );
        }
        
        return array(
            'monthly_data' => $formatted_data
        );
    }
    
    /**
     * Get sales report by period
     */
    public function get_sales_report($request) {
        global $wpdb;
        
        $period = $request->get_param('period') ?: 'month';
        $manager_id = $request->get_param('manager_id');
        
        $customer_filter = '';
        if ($manager_id) {
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE manager_id = %d
            ", $manager_id));
            
            if (!empty($customer_ids)) {
                $customer_ids_str = implode(',', array_map('intval', $customer_ids));
                $customer_filter = "AND o.customer_id IN ($customer_ids_str)";
            }
        }
        
        $date_range = $this->get_date_range($period, 'current');
        
        // Sales by status
        $sales_by_status = $wpdb->get_results($wpdb->prepare("
            SELECT 
                status,
                COUNT(*) as orders_count,
                COALESCE(SUM(total_amount), 0) as revenue
            FROM {$wpdb->prefix}wc_orders o
            WHERE date_created_gmt >= %s 
            AND date_created_gmt < %s
            $customer_filter
            GROUP BY status
            ORDER BY revenue DESC
        ", $date_range['start'], $date_range['end']));
        
        // Top customers by revenue
        $top_customers = $wpdb->get_results($wpdb->prepare("
            SELECT 
                o.customer_id,
                COUNT(*) as orders_count,
                COALESCE(SUM(o.total_amount), 0) as total_spent
            FROM {$wpdb->prefix}wc_orders o
            WHERE o.status IN ('wc-completed', 'wc-processing')
            AND o.date_created_gmt >= %s 
            AND o.date_created_gmt < %s
            AND o.customer_id > 0
            $customer_filter
            GROUP BY o.customer_id
            ORDER BY total_spent DESC
            LIMIT 10
        ", $date_range['start'], $date_range['end']));
        
        // Format top customers with user data
        $formatted_customers = array();
        foreach ($top_customers as $customer_data) {
            $customer = new WC_Customer($customer_data->customer_id);
            if ($customer->get_id()) {
                $formatted_customers[] = array(
                    'customer_id' => $customer_data->customer_id,
                    'name' => $customer->get_display_name(),
                    'email' => $customer->get_email(),
                    'orders_count' => (int) $customer_data->orders_count,
                    'total_spent' => (float) $customer_data->total_spent
                );
            }
        }
        
        // Daily sales trend
        $daily_sales = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(date_created_gmt) as date,
                COUNT(*) as orders_count,
                COALESCE(SUM(total_amount), 0) as revenue
            FROM {$wpdb->prefix}wc_orders 
            WHERE status IN ('wc-completed', 'wc-processing')
            AND date_created_gmt >= %s 
            AND date_created_gmt < %s
            $customer_filter
            GROUP BY DATE(date_created_gmt)
            ORDER BY date ASC
        ", $date_range['start'], $date_range['end']));
        
        return array(
            'sales_by_status' => $sales_by_status,
            'top_customers' => $formatted_customers,
            'daily_sales' => $daily_sales,
            'period' => $period,
            'date_range' => $date_range
        );
    }
    
    /**
     * Get top selling products
     */
    public function get_top_products($request) {
        global $wpdb;
        
        $period = $request->get_param('period') ?: 'month';
        $limit = min($request->get_param('limit') ?: 10, 50);
        $manager_id = $request->get_param('manager_id');
        
        $customer_filter = '';
        if ($manager_id) {
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE manager_id = %d
            ", $manager_id));
            
            if (!empty($customer_ids)) {
                $customer_ids_str = implode(',', array_map('intval', $customer_ids));
                $customer_filter = "AND o.customer_id IN ($customer_ids_str)";
            }
        }
        
        $date_range = $this->get_date_range($period, 'current');
        
        $top_products = $wpdb->get_results($wpdb->prepare("
            SELECT 
                oi.product_id,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.total) as total_revenue,
                COUNT(DISTINCT o.id) as orders_count
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}wc_orders o ON oi.order_id = o.id
            WHERE o.status IN ('wc-completed', 'wc-processing')
            AND o.date_created_gmt >= %s 
            AND o.date_created_gmt < %s
            AND oi.order_item_type = 'line_item'
            $customer_filter
            GROUP BY oi.product_id
            ORDER BY total_revenue DESC
            LIMIT %d
        ", $date_range['start'], $date_range['end'], $limit));
        
        // Format with product data
        $formatted_products = array();
        foreach ($top_products as $product_data) {
            $product = wc_get_product($product_data->product_id);
            if ($product) {
                $formatted_products[] = array(
                    'product_id' => $product_data->product_id,
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price(),
                    'total_quantity' => (int) $product_data->total_quantity,
                    'total_revenue' => (float) $product_data->total_revenue,
                    'orders_count' => (int) $product_data->orders_count,
                    'image_url' => wp_get_attachment_url($product->get_image_id())
                );
            }
        }
        
        return array(
            'top_products' => $formatted_products,
            'period' => $period
        );
    }
    
    /**
     * Get account manager analytics
     */
    public function get_manager_analytics($request) {
        global $wpdb;
        
        $manager_id = $request['id'];
        $period = $request->get_param('period') ?: 'month';
        
        // Get customers assigned to this manager
        $customer_ids = $wpdb->get_col($wpdb->prepare("
            SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
            WHERE manager_id = %d
        ", $manager_id));
        
        if (empty($customer_ids)) {
            return array(
                'customers_count' => 0,
                'total_revenue' => 0,
                'total_orders' => 0,
                'average_order_value' => 0,
                'top_customers' => array(),
                'monthly_performance' => array()
            );
        }
        
        $customer_ids_str = implode(',', array_map('intval', $customer_ids));
        $date_range = $this->get_date_range($period, 'current');
        
        // Manager performance stats
        $performance_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as average_order_value
            FROM {$wpdb->prefix}wc_orders 
            WHERE status IN ('wc-completed', 'wc-processing')
            AND customer_id IN ($customer_ids_str)
            AND date_created_gmt >= %s 
            AND date_created_gmt < %s
        ", $date_range['start'], $date_range['end']));
        
        // Top customers for this manager
        $top_customers = $wpdb->get_results($wpdb->prepare("
            SELECT 
                customer_id,
                COUNT(*) as orders_count,
                COALESCE(SUM(total_amount), 0) as total_spent
            FROM {$wpdb->prefix}wc_orders 
            WHERE status IN ('wc-completed', 'wc-processing')
            AND customer_id IN ($customer_ids_str)
            AND date_created_gmt >= %s 
            AND date_created_gmt < %s
            GROUP BY customer_id
            ORDER BY total_spent DESC
            LIMIT 5
        ", $date_range['start'], $date_range['end']));
        
        // Format top customers
        $formatted_top_customers = array();
        foreach ($top_customers as $customer_data) {
            $customer = new WC_Customer($customer_data->customer_id);
            if ($customer->get_id()) {
                $formatted_top_customers[] = array(
                    'customer_id' => $customer_data->customer_id,
                    'name' => $customer->get_display_name(),
                    'email' => $customer->get_email(),
                    'orders_count' => (int) $customer_data->orders_count,
                    'total_spent' => (float) $customer_data->total_spent
                );
            }
        }
        
        // Monthly performance for the last 12 months
        $monthly_performance = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(date_created_gmt, '%Y-%m') as month,
                COUNT(*) as orders_count,
                COALESCE(SUM(total_amount), 0) as revenue
            FROM {$wpdb->prefix}wc_orders 
            WHERE status IN ('wc-completed', 'wc-processing')
            AND customer_id IN ($customer_ids_str)
            AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(date_created_gmt, '%Y-%m')
            ORDER BY month ASC
        ");
        
        return array(
            'manager_id' => (int) $manager_id,
            'customers_count' => count($customer_ids),
            'total_revenue' => (float) $performance_stats->total_revenue,
            'total_orders' => (int) $performance_stats->total_orders,
            'average_order_value' => (float) $performance_stats->average_order_value,
            'top_customers' => $formatted_top_customers,
            'monthly_performance' => $monthly_performance,
            'period' => $period
        );
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
