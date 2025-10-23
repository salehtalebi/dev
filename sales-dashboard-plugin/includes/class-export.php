<?php

/**
 * Export Class
 */
class Sales_Dashboard_Export {
    
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
                'status' => 'any' // Get all statuses, filter later if needed
            );
            
            // Apply filters from request
            if ($request->get_param('status') && $request->get_param('status') !== '' && $request->get_param('status') !== 'null') {
                $args['status'] = $request->get_param('status');
            }
            
            if ($request->get_param('search') && $request->get_param('search') !== '' && $request->get_param('search') !== 'null') {
                $search = trim($request->get_param('search'));
                if ($search !== '' && $search !== 'null') {
                    $args['search'] = $search;
                }
            }
            
            // Handle account manager filter
            if ($request->get_param('accountManager') && $request->get_param('accountManager') !== '' && $request->get_param('accountManager') !== 'null') {
                $manager_id = $request->get_param('accountManager');
                $customers = get_users(array(
                    'meta_key' => 'account_manager_id',
                    'meta_value' => $manager_id,
                    'fields' => 'ID'
                ));
                
                if (!empty($customers)) {
                    $args['customer'] = $customers;
                } else {
                    return new WP_Error('no_customers', 'No customers found for this account manager', array('status' => 400));
                }
            }
            
            // Handle date range filters
            if ($request->get_param('dateRange') && $request->get_param('dateRange') !== '' && $request->get_param('dateRange') !== 'null') {
                $date_range = $request->get_param('dateRange');
                $date_args = $this->get_date_range_args($date_range);
                if ($date_args) {
                    $args = array_merge($args, $date_args);
                }
            } elseif ($request->get_param('date_from') || $request->get_param('date_to')) {
                // Handle custom date range
                $date_from = $request->get_param('date_from');
                $date_to = $request->get_param('date_to');
                
                if ($date_from && $date_from !== '' && $date_from !== 'null') {
                    $args['date_created'] = '>=' . $date_from;
                }
                if ($date_to && $date_to !== '' && $date_to !== 'null') {
                    $end_date = $date_to . ' 23:59:59';
                    $args['date_created'] = isset($args['date_created']) 
                        ? $args['date_created'] . '...' . $end_date
                        : '<=' . $end_date;
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
            
            if (empty($orders)) {
                return new WP_Error('no_orders', 'No orders found for export', array('status' => 400));
            }
            
            $export_data = array();
            
            foreach ($orders as $order) {
                // Apply amount filtering if needed
                $min_amount = $request->get_param('min_amount');
                $max_amount = $request->get_param('max_amount');
                
                if ($min_amount !== null && $min_amount !== '' && $min_amount !== 'null') {
                    if (floatval($order->get_total()) < floatval($min_amount)) {
                        continue;
                    }
                }
                
                if ($max_amount !== null && $max_amount !== '' && $max_amount !== 'null') {
                    if (floatval($order->get_total()) > floatval($max_amount)) {
                        continue;
                    }
                }
                
                $export_data[] = array(
                    'Order Number' => $order->get_order_number(),
                    'Status' => wc_get_order_status_name($order->get_status()),
                    'Date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                    'Customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'Email' => $order->get_billing_email(),
                    'Phone' => $order->get_billing_phone(),
                    'Total Amount' => $order->get_total(),
                    'Currency' => $order->get_currency(),
                    'Payment Method' => $order->get_payment_method_title(),
                    'Address' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
                    'City' => $order->get_billing_city(),
                    'Province' => $order->get_billing_state(),
                    'Postal Code' => $order->get_billing_postcode()
                );
            }
            
            if (empty($export_data)) {
                return new WP_Error('no_orders', 'No orders found for export', array('status' => 400));
            }
            
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
            $format = $request->get_param('format') ?: 'csv';
            // Get all customers for export (no limit)
            $limit = $request->get_param('limit') ?: -1;
            if ($limit > 0) {
                $limit = min($limit, 10000); // Max 10000 if specified
            }
            
            // Build WP_User_Query arguments
            $args = array(
                'number' => $limit,
                'role' => 'customer'
            );
            
            // Apply filters from request
            if ($request->get_param('search') && $request->get_param('search') !== 'null') {
                $search = trim($request->get_param('search'));
                if ($search !== '' && $search !== 'null') {
                    $args['search'] = '*' . esc_attr($search) . '*';
                }
            }
            
            // Handle account manager filter
            if ($request->get_param('accountManager') && $request->get_param('accountManager') !== '') {
                $manager_id = $request->get_param('accountManager');
                $args['meta_key'] = 'account_manager_id';
                $args['meta_value'] = $manager_id;
            }

            // Handle province filter (optimized with meta_query)
            if ($request->get_param('province') && $request->get_param('province') !== '') {
                $province = sanitize_text_field($request->get_param('province'));
                
                // If we already have a meta_key/value for account manager, convert to meta_query
                if (isset($args['meta_key'])) {
                    $args['meta_query'] = array(
                        'relation' => 'AND',
                        array(
                            'key' => $args['meta_key'],
                            'value' => $args['meta_value'],
                            'compare' => '='
                        ),
                        array(
                            'key' => 'billing_state',
                            'value' => $province,
                            'compare' => '='
                        )
                    );
                    unset($args['meta_key']);
                    unset($args['meta_value']);
                } else {
                    $args['meta_key'] = 'billing_state';
                    $args['meta_value'] = $province;
                }
            }

            // Handle date registered filters
            $date_query = array();
            
            // Priority: custom date range over dateRange
            $has_custom_dates = ($request->get_param('date_registered_from') && $request->get_param('date_registered_from') !== '' && $request->get_param('date_registered_from') !== 'null') ||
                               ($request->get_param('date_from') && $request->get_param('date_from') !== '' && $request->get_param('date_from') !== 'null') ||
                               ($request->get_param('date_registered_to') && $request->get_param('date_registered_to') !== '' && $request->get_param('date_registered_to') !== 'null') ||
                               ($request->get_param('date_to') && $request->get_param('date_to') !== '' && $request->get_param('date_to') !== 'null');
            
            if ($has_custom_dates) {
                // Use custom date range
                if ($request->get_param('date_registered_from') || $request->get_param('date_from')) {
                    $date_from = $request->get_param('date_registered_from') ?: $request->get_param('date_from');
                    if ($date_from && $date_from !== '' && $date_from !== 'null') {
                        $date_query['after'] = $date_from;
                    }
                }
                if ($request->get_param('date_registered_to') || $request->get_param('date_to')) {
                    $date_to = $request->get_param('date_registered_to') ?: $request->get_param('date_to');
                    if ($date_to && $date_to !== '' && $date_to !== 'null') {
                        $date_query['before'] = $date_to . ' 23:59:59';
                    }
                }
            } elseif ($request->get_param('dateRange') && $request->get_param('dateRange') !== '' && $request->get_param('dateRange') !== 'null') {
                // Use predefined date range
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
            
            if (!empty($date_query)) {
                $args['date_query'] = array($date_query);
            }
            
            // Execute query
            $customer_query = new WP_User_Query($args);
            $users = $customer_query->get_results();
            
            if (empty($users)) {
                return new WP_Error('no_customers', 'No customers found for export', array('status' => 400));
            }
            
            $export_data = array();
            
            foreach ($users as $user) {
                $customer = new WC_Customer($user->ID);
                
                // Get customer order stats
                $order_count = wc_get_customer_order_count($customer->get_id());
                $total_spent = wc_get_customer_total_spent($customer->get_id());
                
                // Get account manager info
                $manager_id = get_user_meta($customer->get_id(), 'account_manager_id', true);
                $manager_name = '';
                if ($manager_id && isset($this->managers[$manager_id])) {
                    $manager_name = $this->managers[$manager_id];
                }
                
                $export_data[] = array(
                    'Customer ID' => $customer->get_id(),
                    'First Name' => $customer->get_first_name(),
                    'Last Name' => $customer->get_last_name(),
                    'Email' => $customer->get_email(),
                    'Phone' => $customer->get_billing_phone(),
                    'Company' => $customer->get_billing_company(),
                    'Address' => $customer->get_billing_address_1() . ' ' . $customer->get_billing_address_2(),
                    'City' => $customer->get_billing_city(),
                    'Province' => $customer->get_billing_state(),
                    'Postal Code' => $customer->get_billing_postcode(),
                    'Total Orders' => $order_count,
                    'Total Spent' => $total_spent,
                    'Registration Date' => $customer->get_date_created() ? $customer->get_date_created()->date('Y-m-d H:i:s') : '',
                    'Account Manager' => $manager_name
                );
            }
            
            if (empty($export_data)) {
                return new WP_Error('no_customers', 'No customers found for export', array('status' => 400));
            }
            
            // Generate CSV or Excel file
            if ($format === 'csv') {
                return $this->generate_csv($export_data, 'customers');
            } else {
                return $this->generate_excel($export_data, 'customers');
            }
            
        } catch (Exception $e) {
            error_log('Export Customers Error: ' . $e->getMessage());
            return new WP_Error('export_error', 'Error creating export file: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Export analytics report
     */
    public function export_analytics($request) {
        global $wpdb;
        
        $format = $request->get_param('format') ?: 'csv';
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
