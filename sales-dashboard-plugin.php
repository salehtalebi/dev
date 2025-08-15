<?php
/**
 * Plugin Name: Sales Dashboard API
 * Description: Custom API endpoints for Sales Dashboard
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin activation hook
register_activation_hook(__FILE__, 'sales_dashboard_activate');
register_deactivation_hook(__FILE__, 'sales_dashboard_deactivate');

function sales_dashboard_activate() {
    // Create custom tables if needed
    sales_dashboard_create_tables();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

function sales_dashboard_deactivate() {
    // Clean up if needed
    flush_rewrite_rules();
}

// Initialize the plugin
add_action('init', 'sales_dashboard_init');

function sales_dashboard_init() {
    // Add custom user meta for account manager
    add_action('rest_api_init', 'sales_dashboard_register_api_routes');
    add_action('woocommerce_customer_save_address', 'sales_dashboard_save_account_manager', 10, 2);
    
    // Add account manager field to customer registration
    add_action('woocommerce_edit_account_form', 'sales_dashboard_add_account_manager_field');
    add_action('woocommerce_save_account_details', 'sales_dashboard_save_account_manager_field');
}

// Create custom database tables
function sales_dashboard_create_tables() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'customer_account_managers';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        customer_id bigint(20) NOT NULL,
        manager_id bigint(20) NOT NULL,
        assigned_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY customer_manager (customer_id, manager_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Register custom REST API routes
function sales_dashboard_register_api_routes() {
    // Analytics endpoints
    register_rest_route('sales-dashboard/v1', '/analytics/dashboard', array(
        'methods' => 'GET',
        'callback' => 'sales_dashboard_get_analytics',
        'permission_callback' => 'sales_dashboard_check_permission'
    ));
    
    register_rest_route('sales-dashboard/v1', '/analytics/monthly-comparison', array(
        'methods' => 'GET',
        'callback' => 'sales_dashboard_get_monthly_comparison',
        'permission_callback' => 'sales_dashboard_check_permission'
    ));
    
    register_rest_route('sales-dashboard/v1', '/analytics/manager/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'sales_dashboard_get_manager_performance',
        'permission_callback' => 'sales_dashboard_check_permission'
    ));
    
    // Account manager endpoints
    register_rest_route('sales-dashboard/v1', '/account-managers', array(
        'methods' => 'GET',
        'callback' => 'sales_dashboard_get_account_managers',
        'permission_callback' => 'sales_dashboard_check_permission'
    ));
    
    register_rest_route('sales-dashboard/v1', '/account-managers/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'sales_dashboard_get_account_manager',
        'permission_callback' => 'sales_dashboard_check_permission'
    ));
    
    register_rest_route('sales-dashboard/v1', '/account-managers/assign', array(
        'methods' => 'POST',
        'callback' => 'sales_dashboard_assign_customer',
        'permission_callback' => 'sales_dashboard_check_permission'
    ));
    
    // Orders by manager
    register_rest_route('sales-dashboard/v1', '/orders/by-manager/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'sales_dashboard_get_orders_by_manager',
        'permission_callback' => 'sales_dashboard_check_permission'
    ));
    
    // Customers by manager
    register_rest_route('sales-dashboard/v1', '/customers/by-manager/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'sales_dashboard_get_customers_by_manager',
        'permission_callback' => 'sales_dashboard_check_permission'
    ));
    
    // Customer statistics
    register_rest_route('sales-dashboard/v1', '/customers/(?P<id>\d+)/statistics', array(
        'methods' => 'GET',
        'callback' => 'sales_dashboard_get_customer_statistics',
        'permission_callback' => 'sales_dashboard_check_permission'
    ));
    
    // Export endpoints
    register_rest_route('sales-dashboard/v1', '/export/orders', array(
        'methods' => 'GET',
        'callback' => 'sales_dashboard_export_orders',
        'permission_callback' => 'sales_dashboard_check_permission'
    ));
    
    register_rest_route('sales-dashboard/v1', '/export/customers', array(
        'methods' => 'GET',
        'callback' => 'sales_dashboard_export_customers',
        'permission_callback' => 'sales_dashboard_check_permission'
    ));
}

// Permission check function
function sales_dashboard_check_permission($request) {
    // Check if user is logged in and has administrator or shop_manager role
    if (!is_user_logged_in()) {
        return false;
    }
    
    $current_user = wp_get_current_user();
    
    // Allow administrators and shop managers
    if (in_array('administrator', $current_user->roles) || 
        in_array('shop_manager', $current_user->roles)) {
        return true;
    }
    
    return false;
}

// Get dashboard analytics
function sales_dashboard_get_analytics($request) {
    global $wpdb;
    
    $current_month = date('Y-m');
    $last_month = date('Y-m', strtotime('-1 month'));
    
    // Get current month orders
    $current_month_orders = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
        WHERE DATE_FORMAT(date_created_gmt, '%%Y-%%m') = %s
        AND status IN ('wc-completed', 'wc-processing')
    ", $current_month));
    
    // Get last month orders for comparison
    $last_month_orders = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
        WHERE DATE_FORMAT(date_created_gmt, '%%Y-%%m') = %s
        AND status IN ('wc-completed', 'wc-processing')
    ", $last_month));
    
    // Get current month revenue
    $current_month_revenue = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(total_amount) FROM {$wpdb->prefix}wc_orders 
        WHERE DATE_FORMAT(date_created_gmt, '%%Y-%%m') = %s
        AND status IN ('wc-completed', 'wc-processing')
    ", $current_month));
    
    // Get last month revenue
    $last_month_revenue = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(total_amount) FROM {$wpdb->prefix}wc_orders 
        WHERE DATE_FORMAT(date_created_gmt, '%%Y-%%m') = %s
        AND status IN ('wc-completed', 'wc-processing')
    ", $last_month));
    
    // Calculate growth percentages
    $orders_growth = $last_month_orders > 0 ? 
        (($current_month_orders - $last_month_orders) / $last_month_orders) * 100 : 0;
    
    $revenue_growth = $last_month_revenue > 0 ? 
        (($current_month_revenue - $last_month_revenue) / $last_month_revenue) * 100 : 0;
    
    return array(
        'current_month' => array(
            'orders' => (int)$current_month_orders,
            'revenue' => (float)$current_month_revenue
        ),
        'last_month' => array(
            'orders' => (int)$last_month_orders,
            'revenue' => (float)$last_month_revenue
        ),
        'growth' => array(
            'orders' => round($orders_growth, 2),
            'revenue' => round($revenue_growth, 2)
        )
    );
}

// Get monthly comparison data
function sales_dashboard_get_monthly_comparison($request) {
    global $wpdb;
    
    $months = array();
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $months[] = $month;
    }
    
    $data = array();
    foreach ($months as $month) {
        $orders_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
            WHERE DATE_FORMAT(date_created_gmt, '%%Y-%%m') = %s
            AND status IN ('wc-completed', 'wc-processing')
        ", $month));
        
        $revenue = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(total_amount) FROM {$wpdb->prefix}wc_orders 
            WHERE DATE_FORMAT(date_created_gmt, '%%Y-%%m') = %s
            AND status IN ('wc-completed', 'wc-processing')
        ", $month));
        
        $data[] = array(
            'month' => $month,
            'orders' => (int)$orders_count,
            'revenue' => (float)$revenue
        );
    }
    
    return $data;
}

// Get account managers
function sales_dashboard_get_account_managers($request) {
    $users = get_users(array(
        'role__in' => array('administrator', 'shop_manager'),
        'meta_key' => 'is_account_manager',
        'meta_value' => '1'
    ));
    
    $managers = array();
    foreach ($users as $user) {
        $managers[] = array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email
        );
    }
    
    return $managers;
}

// Get orders by account manager
function sales_dashboard_get_orders_by_manager($request) {
    global $wpdb;
    
    $manager_id = $request['id'];
    $page = $request->get_param('page') ?: 1;
    $per_page = $request->get_param('per_page') ?: 20;
    $offset = ($page - 1) * $per_page;
    
    // Get customer IDs assigned to this manager
    $customer_ids = $wpdb->get_col($wpdb->prepare("
        SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
        WHERE manager_id = %d
    ", $manager_id));
    
    if (empty($customer_ids)) {
        return array('data' => array(), 'total' => 0);
    }
    
    $customer_ids_str = implode(',', array_map('intval', $customer_ids));
    
    // Get orders for these customers
    $orders = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}wc_orders 
        WHERE customer_id IN ($customer_ids_str)
        ORDER BY date_created_gmt DESC
        LIMIT %d OFFSET %d
    ", $per_page, $offset));
    
    $total = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
        WHERE customer_id IN ($customer_ids_str)
    "));
    
    return array(
        'data' => $orders,
        'total' => (int)$total,
        'pages' => ceil($total / $per_page)
    );
}

// Get customers by account manager
function sales_dashboard_get_customers_by_manager($request) {
    global $wpdb;
    
    $manager_id = $request['id'];
    
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
            $customers[] = array(
                'id' => $customer->get_id(),
                'email' => $customer->get_email(),
                'first_name' => $customer->get_first_name(),
                'last_name' => $customer->get_last_name(),
                'date_created' => $customer->get_date_created()->date('Y-m-d H:i:s'),
                'total_spent' => $customer->get_total_spent(),
                'orders_count' => $customer->get_order_count()
            );
        }
    }
    
    return $customers;
}

// Get customer statistics
function sales_dashboard_get_customer_statistics($request) {
    $customer_id = $request['id'];
    $customer = new WC_Customer($customer_id);
    
    if (!$customer->get_id()) {
        return new WP_Error('customer_not_found', 'Customer not found', array('status' => 404));
    }
    
    global $wpdb;
    
    // Get orders for the last 12 months
    $orders = $wpdb->get_results($wpdb->prepare("
        SELECT 
            DATE_FORMAT(date_created_gmt, '%%Y-%%m') as month,
            COUNT(*) as orders_count,
            SUM(total_amount) as total_spent
        FROM {$wpdb->prefix}wc_orders 
        WHERE customer_id = %d 
        AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        AND status IN ('wc-completed', 'wc-processing')
        GROUP BY DATE_FORMAT(date_created_gmt, '%%Y-%%m')
        ORDER BY month DESC
    ", $customer_id));
    
    $current_month = date('Y-m');
    $last_month = date('Y-m', strtotime('-1 month'));
    
    $current_month_data = array_filter($orders, function($order) use ($current_month) {
        return $order->month === $current_month;
    });
    
    $last_month_data = array_filter($orders, function($order) use ($last_month) {
        return $order->month === $last_month;
    });
    
    $current_month_total = !empty($current_month_data) ? reset($current_month_data)->total_spent : 0;
    $last_month_total = !empty($last_month_data) ? reset($last_month_data)->total_spent : 0;
    
    $growth_percentage = $last_month_total > 0 ? 
        (($current_month_total - $last_month_total) / $last_month_total) * 100 : 0;
    
    return array(
        'total_orders' => $customer->get_order_count(),
        'total_spent' => $customer->get_total_spent(),
        'current_month_total' => (float)$current_month_total,
        'last_month_total' => (float)$last_month_total,
        'growth_percentage' => round($growth_percentage, 2),
        'average_order_value' => $customer->get_order_count() > 0 ? 
            $customer->get_total_spent() / $customer->get_order_count() : 0,
        'monthly_data' => $orders
    );
}

// Export orders
function sales_dashboard_export_orders($request) {
    // Implementation for CSV/Excel export
    // This would generate and return a downloadable file
    // For now, return success message
    return array('message' => 'Export functionality to be implemented');
}

// Export customers
function sales_dashboard_export_customers($request) {
    // Implementation for CSV/Excel export
    // This would generate and return a downloadable file
    // For now, return success message
    return array('message' => 'Export functionality to be implemented');
}

// Add account manager field to customer account page
function sales_dashboard_add_account_manager_field() {
    $user_id = get_current_user_id();
    $account_manager = get_user_meta($user_id, 'account_manager', true);
    
    $managers = get_users(array(
        'role__in' => array('administrator', 'shop_manager'),
        'meta_key' => 'is_account_manager',
        'meta_value' => '1'
    ));
    
    $options = array('' => 'Select Account Manager');
    foreach ($managers as $manager) {
        $options[$manager->ID] = $manager->display_name;
    }
    
    woocommerce_form_field('account_manager', array(
        'type' => 'select',
        'label' => __('Account Manager'),
        'required' => false,
        'options' => $options
    ), $account_manager);
}

// Save account manager field
function sales_dashboard_save_account_manager_field($user_id) {
    if (isset($_POST['account_manager'])) {
        update_user_meta($user_id, 'account_manager', sanitize_text_field($_POST['account_manager']));
        
        // Also save in custom table
        global $wpdb;
        $manager_id = intval($_POST['account_manager']);
        
        if ($manager_id > 0) {
            $wpdb->replace(
                $wpdb->prefix . 'customer_account_managers',
                array(
                    'customer_id' => $user_id,
                    'manager_id' => $manager_id,
                    'assigned_date' => current_time('mysql')
                ),
                array('%d', '%d', '%s')
            );
        }
    }
}

// Assign customer to account manager
function sales_dashboard_assign_customer($request) {
    global $wpdb;
    
    $customer_id = $request->get_param('customer_id');
    $manager_id = $request->get_param('manager_id');
    
    if (!$customer_id || !$manager_id) {
        return new WP_Error('missing_params', 'Customer ID and Manager ID required', array('status' => 400));
    }
    
    $result = $wpdb->replace(
        $wpdb->prefix . 'customer_account_managers',
        array(
            'customer_id' => $customer_id,
            'manager_id' => $manager_id,
            'assigned_date' => current_time('mysql')
        ),
        array('%d', '%d', '%s')
    );
    
    if ($result !== false) {
        // Also update user meta
        update_user_meta($customer_id, 'account_manager', $manager_id);
        
        return array('success' => true, 'message' => 'Customer assigned successfully');
    }
    
    return new WP_Error('assignment_failed', 'Failed to assign customer', array('status' => 500));
}

?>
