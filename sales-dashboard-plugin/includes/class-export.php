<?php

/**
 * Export Class
 */
class Sales_Dashboard_Export
{

    private $managers;

    public function __construct()
    {
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

    public function register_routes()
    {
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
    public function export_orders($request)
    {
        global $wpdb;

        $format = $request->get_param('format') ?: 'csv';
        $status = $request->get_param('status');
        $manager_id = $request->get_param('manager_id');
        $date_from = $request->get_param('date_from');
        $date_to = $request->get_param('date_to');
        $limit = min($request->get_param('limit') ?: 1000, 5000);

        // Build query conditions
        $where_conditions = array("1=1");

        if ($status) {
            $where_conditions[] = $wpdb->prepare("status = %s", $status);
        }

        if ($manager_id) {
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE manager_id = %d
            ", $manager_id));

            if (!empty($customer_ids)) {
                $customer_ids_str = implode(',', array_map('intval', $customer_ids));
                $where_conditions[] = "customer_id IN ($customer_ids_str)";
            } else {
                return new WP_Error('no_customers', 'هیچ مشتری برای این مدیر حساب یافت نشد', array('status' => 400));
            }
        }

        if ($date_from) {
            $where_conditions[] = $wpdb->prepare("date_created_gmt >= %s", $date_from . ' 00:00:00');
        }

        if ($date_to) {
            $where_conditions[] = $wpdb->prepare("date_created_gmt <= %s", $date_to . ' 23:59:59');
        }

        $where_clause = "WHERE " . implode(' AND ', $where_conditions);

        // Get orders
        $orders = $wpdb->get_results("
            SELECT 
                id,
                number,
                status,
                currency,
                total_amount,
                customer_id,
                date_created_gmt,
                billing_first_name,
                billing_last_name,
                billing_email,
                billing_phone,
                payment_method,
                payment_method_title
            FROM {$wpdb->prefix}wc_orders 
            $where_clause
            ORDER BY date_created_gmt DESC
            LIMIT $limit
        ");

        if (empty($orders)) {
            return new WP_Error('no_orders', 'هیچ سفارشی برای اکسپورت یافت نشد', array('status' => 400));
        }

        // Generate export data
        $export_data = array();
        $headers = array(
            'شناسه سفارش',
            'شماره سفارش',
            'وضعیت',
            'مبلغ کل',
            'واحد پول',
            'نام مشتری',
            'ایمیل مشتری',
            'تلفن مشتری',
            'روش پرداخت',
            'تاریخ سفارش',
            'مدیر حساب'
        );

        $export_data[] = $headers;

        foreach ($orders as $order) {
            $customer_name = trim($order->billing_first_name . ' ' . $order->billing_last_name);

            // Get account manager
            $manager_name = '';
            if ($order->customer_id) {
                $manager_id = $wpdb->get_var($wpdb->prepare("
                    SELECT manager_id FROM {$wpdb->prefix}customer_account_managers 
                    WHERE customer_id = %d
                ", $order->customer_id));

                if ($manager_id) {
                    $manager = get_user_by('id', $manager_id);
                    if ($manager) {
                        $manager_name = $manager->display_name;
                    }
                }
            }

            $export_data[] = array(
                $order->id,
                $order->number,
                $this->translate_order_status($order->status),
                $order->total_amount,
                $order->currency,
                $customer_name,
                $order->billing_email,
                $order->billing_phone,
                $order->payment_method_title,
                date('Y-m-d H:i:s', strtotime($order->date_created_gmt)),
                $manager_name
            );
        }

        // Generate file
        if ($format === 'csv') {
            return $this->generate_csv_response($export_data, 'orders_export_' . date('Y-m-d'));
        } else {
            return $this->generate_excel_response($export_data, 'orders_export_' . date('Y-m-d'));
        }
    }

    /**
     * Export customers to CSV
     */
    public function export_customers($request)
    {
        global $wpdb;

        $format = $request->get_param('format') ?: 'csv';
        $manager_id = $request->get_param('manager_id');
        $limit = min($request->get_param('limit') ?: 1000, 5000);

        // Build customer query
        $customer_ids = array();

        if ($manager_id) {
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE manager_id = %d
                LIMIT %d
            ", $manager_id, $limit));

            if (empty($customer_ids)) {
                return new WP_Error('no_customers', 'هیچ مشتری برای این مدیر حساب یافت نشد', array('status' => 400));
            }
        } else {
            // Get all customers with orders
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT customer_id 
                FROM {$wpdb->prefix}wc_orders 
                WHERE customer_id > 0 
                LIMIT %d
            ", $limit));
        }

        if (empty($customer_ids)) {
            return new WP_Error('no_customers', 'هیچ مشتری برای اکسپورت یافت نشد', array('status' => 400));
        }

        // Generate export data
        $export_data = array();
        $headers = array(
            'شناسه مشتری',
            'نام',
            'نام خانوادگی',
            'ایمیل',
            'تلفن',
            'شرکت',
            'آدرس',
            'شهر',
            'کد پستی',
            'تعداد سفارشات',
            'مجموع خرید',
            'تاریخ عضویت',
            'مدیر حساب'
        );

        $export_data[] = $headers;

        foreach ($customer_ids as $customer_id) {
            $customer = new WC_Customer($customer_id);

            if (!$customer->get_id()) {
                continue;
            }

            // Get customer stats
            $stats = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as orders_count,
                    COALESCE(SUM(total_amount), 0) as total_spent
                FROM {$wpdb->prefix}wc_orders 
                WHERE customer_id = %d 
                AND status IN ('wc-completed', 'wc-processing')
            ", $customer_id));

            // Get account manager
            $manager_name = '';
            $manager_id = $wpdb->get_var($wpdb->prepare("
                SELECT manager_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE customer_id = %d
            ", $customer_id));

            if ($manager_id) {
                $manager = get_user_by('id', $manager_id);
                if ($manager) {
                    $manager_name = $manager->display_name;
                }
            }

            $export_data[] = array(
                $customer->get_id(),
                $customer->get_first_name(),
                $customer->get_last_name(),
                $customer->get_email(),
                $customer->get_billing_phone(),
                $customer->get_billing_company(),
                $customer->get_billing_address_1() . ' ' . $customer->get_billing_address_2(),
                $customer->get_billing_city(),
                $customer->get_billing_postcode(),
                $stats->orders_count,
                $stats->total_spent,
                $customer->get_date_created() ? $customer->get_date_created()->date('Y-m-d H:i:s') : '',
                $manager_name
            );
        }

        // Generate file
        if ($format === 'csv') {
            return $this->generate_csv_response($export_data, 'customers_export_' . date('Y-m-d'));
        } else {
            return $this->generate_excel_response($export_data, 'customers_export_' . date('Y-m-d'));
        }
    }

    /**
     * Export analytics report
     */
    public function export_analytics($request)
    {
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
    private function generate_csv_response($data, $filename)
    {
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
    private function generate_excel_response($data, $filename)
    {
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
     * Translate order status to Persian
     */
    private function translate_order_status($status)
    {
        $status_translations = array(
            'pending' => 'Pending payment',
            'processing' => 'Processing',
            'on-hold' => 'On hold',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'failed' => 'Failed'
        );

        $clean_status = str_replace('wc-', '', $status);
        return $status_translations[$clean_status] ?? $status;
    }

    /**
     * Check permissions
     */
    public function check_permissions($request)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $current_user = wp_get_current_user();

        return in_array('administrator', $current_user->roles) ||
            in_array('shop_manager', $current_user->roles);
    }
}
