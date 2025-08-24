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
            $limit = min($request->get_param('limit') ?: 1000, 5000);
            
            // Build WC_Order_Query arguments similar to get_enhanced_orders
            $args = array(
                'limit' => $limit,
                'orderby' => 'date',
                'order' => 'DESC',
                'status' => 'any' // Get all statuses, filter later if needed
            );
            
            // Apply filters from request
            if ($request->get_param('status')) {
                $args['status'] = $request->get_param('status');
            }
            
            if ($request->get_param('search')) {
                $args['search'] = $request->get_param('search');
            }
            
            // Handle account manager filter
            if ($request->get_param('accountManager')) {
                $manager_id = $request->get_param('accountManager');
                $customers = get_users(array(
                    'meta_key' => 'account_manager_id',
                    'meta_value' => $manager_id,
                    'fields' => 'ID'
                ));
                
                if (!empty($customers)) {
                    $args['customer'] = $customers;
                } else {
                    return new WP_Error('no_customers', 'هیچ مشتری برای این مدیر حساب یافت نشد', array('status' => 400));
                }
            }
            
            // Handle date range filters
            if ($request->get_param('dateRange')) {
                $date_range = $request->get_param('dateRange');
                $date_args = $this->get_date_range_args($date_range);
                if ($date_args) {
                    $args = array_merge($args, $date_args);
                }
            } elseif ($request->get_param('date_from') || $request->get_param('date_to')) {
                // Handle custom date range
                if ($request->get_param('date_from')) {
                    $args['date_created'] = '>=' . $request->get_param('date_from');
                }
                if ($request->get_param('date_to')) {
                    $end_date = $request->get_param('date_to') . ' 23:59:59';
                    $args['date_created'] = isset($args['date_created']) 
                        ? $args['date_created'] . '...' . $end_date
                        : '<=' . $end_date;
                }
            }
            
            // Execute query
            $order_query = new WC_Order_Query($args);
            $orders = $order_query->get_orders();
            
            if (empty($orders)) {
                return new WP_Error('no_orders', 'هیچ سفارشی برای اکسپورت یافت نشد', array('status' => 400));
            }
            
            $export_data = array();
            
            foreach ($orders as $order) {
                // Apply amount filtering if needed
                $min_amount = $request->get_param('min_amount');
                $max_amount = $request->get_param('max_amount');
                
                if ($min_amount !== null && $min_amount !== '') {
                    if (floatval($order->get_total()) < floatval($min_amount)) {
                        continue;
                    }
                }
                
                if ($max_amount !== null && $max_amount !== '') {
                    if (floatval($order->get_total()) > floatval($max_amount)) {
                        continue;
                    }
                }
                
                $export_data[] = array(
                    'شماره سفارش' => $order->get_order_number(),
                    'وضعیت' => wc_get_order_status_name($order->get_status()),
                    'تاریخ' => $order->get_date_created()->date('Y-m-d H:i:s'),
                    'مشتری' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'ایمیل' => $order->get_billing_email(),
                    'تلفن' => $order->get_billing_phone(),
                    'مبلغ کل' => $order->get_total(),
                    'ارز' => $order->get_currency(),
                    'روش پرداخت' => $order->get_payment_method_title(),
                    'آدرس' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
                    'شهر' => $order->get_billing_city(),
                    'استان' => $order->get_billing_state(),
                    'کد پستی' => $order->get_billing_postcode()
                );
            }
            
            if (empty($export_data)) {
                return new WP_Error('no_orders', 'هیچ سفارشی برای اکسپورت یافت نشد', array('status' => 400));
            }
            
            // Generate CSV or Excel file
            if ($format === 'csv') {
                return $this->generate_csv($export_data, 'orders');
            } else {
                return $this->generate_excel($export_data, 'orders');
            }
            
        } catch (Exception $e) {
            error_log('Export Error: ' . $e->getMessage());
            return new WP_Error('export_error', 'خطا در ایجاد فایل خروجی: ' . $e->getMessage(), array('status' => 500));
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
            $limit = min($request->get_param('limit') ?: 1000, 5000);
            
            // Build WP_User_Query arguments
            $args = array(
                'number' => $limit,
                'role' => 'customer'
            );
            
            // Apply filters from request
            if ($request->get_param('search')) {
                $args['search'] = '*' . esc_attr($request->get_param('search')) . '*';
            }
            
            // Handle account manager filter
            if ($request->get_param('accountManager')) {
                $manager_id = $request->get_param('accountManager');
                $args['meta_key'] = 'account_manager_id';
                $args['meta_value'] = $manager_id;
            }
            
            // Execute query
            $customer_query = new WP_User_Query($args);
            $users = $customer_query->get_results();
            
            if (empty($users)) {
                return new WP_Error('no_customers', 'هیچ مشتری برای اکسپورت یافت نشد', array('status' => 400));
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
                    'شناسه مشتری' => $customer->get_id(),
                    'نام' => $customer->get_first_name(),
                    'نام خانوادگی' => $customer->get_last_name(),
                    'ایمیل' => $customer->get_email(),
                    'تلفن' => $customer->get_billing_phone(),
                    'شرکت' => $customer->get_billing_company(),
                    'آدرس' => $customer->get_billing_address_1() . ' ' . $customer->get_billing_address_2(),
                    'شهر' => $customer->get_billing_city(),
                    'کد پستی' => $customer->get_billing_postcode(),
                    'تعداد سفارشات' => $order_count,
                    'مجموع خرید' => $total_spent,
                    'تاریخ عضویت' => $customer->get_date_created() ? $customer->get_date_created()->date('Y-m-d H:i:s') : '',
                    'مدیر حساب' => $manager_name
                );
            }
            
            if (empty($export_data)) {
                return new WP_Error('no_customers', 'هیچ مشتری برای اکسپورت یافت نشد', array('status' => 400));
            }
            
            // Generate CSV or Excel file
            if ($format === 'csv') {
                return $this->generate_csv($export_data, 'customers');
            } else {
                return $this->generate_excel($export_data, 'customers');
            }
            
        } catch (Exception $e) {
            error_log('Export Customers Error: ' . $e->getMessage());
            return new WP_Error('export_error', 'خطا در ایجاد فایل خروجی: ' . $e->getMessage(), array('status' => 500));
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
            return new WP_Error('no_data', 'هیچ داده‌ای برای اکسپورت یافت نشد', array('status' => 400));
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
