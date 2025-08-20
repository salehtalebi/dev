<?php

/**
 * Account Managers Class
 */
class Sales_Dashboard_Account_Managers {
    
    private $managers;
    
    public function __construct() {
        // Define account managers list
        $this->managers = array(
            'house' => 'House',
            '1465'  => 'Ina Istok',
            '845'   => 'Pina Lee',
            '1886'  => 'Vidika Shenton',
            '2532'  => 'Sarah Hearn',
            '2533'  => 'Jonathon Regan',
        );
        
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('show_user_profile', array($this, 'add_account_manager_field'));
        add_action('edit_user_profile', array($this, 'add_account_manager_field'));
        add_action('personal_options_update', array($this, 'save_account_manager_field'));
        add_action('edit_user_profile_update', array($this, 'save_account_manager_field'));
    }
    
    public function register_routes() {
        // Get all account managers
        register_rest_route('sales-dashboard/v1', '/account-managers', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_account_managers'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Get customers by account manager
        register_rest_route('sales-dashboard/v1', '/account-managers/(?P<id>[a-zA-Z0-9_-]+)/customers', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customers_by_manager'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Get orders by account manager
        register_rest_route('sales-dashboard/v1', '/account-managers/(?P<id>[a-zA-Z0-9_-]+)/orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_orders_by_manager'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Assign customer to account manager
        register_rest_route('sales-dashboard/v1', '/account-managers/assign', array(
            'methods' => 'POST',
            'callback' => array($this, 'assign_customer'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'customer_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Customer ID to assign'
                ),
                'manager_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Account Manager ID'
                )
            )
        ));
        
        // Remove customer from account manager
        register_rest_route('sales-dashboard/v1', '/account-managers/unassign', array(
            'methods' => 'POST',
            'callback' => array($this, 'unassign_customer'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'customer_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Customer ID to unassign'
                )
            )
        ));
        
        // Get account manager statistics
        register_rest_route('sales-dashboard/v1', '/account-managers/(?P<id>[a-zA-Z0-9_-]+)/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_manager_statistics'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Bulk assign customers
        register_rest_route('sales-dashboard/v1', '/account-managers/bulk-assign', array(
            'methods' => 'POST',
            'callback' => array($this, 'bulk_assign_customers'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'customer_ids' => array(
                    'required' => true,
                    'type' => 'array',
                    'description' => 'Array of customer IDs to assign'
                ),
                'manager_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Account Manager ID'
                )
            )
        ));
    }
    
    /**
     * Get all account managers
     */
    public function get_account_managers($request) {
        $managers_with_stats = array();
        
        foreach ($this->managers as $manager_id => $manager_name) {
            $customers_count = $this->get_customers_count_by_manager($manager_id);
            
            $managers_with_stats[] = array(
                'id' => $manager_id,
                'name' => $manager_name,
                'customers_count' => $customers_count,
                'is_active' => $customers_count > 0
            );
        }
        
        return $managers_with_stats;
    }
    
    /**
     * Get customers by account manager
     */
    public function get_customers_by_manager($request) {
        $manager_id = $request['id'];
        $page = $request->get_param('page') ?: 1;
        $per_page = min($request->get_param('per_page') ?: 20, 100);
        $search = $request->get_param('search');
        
        if (!isset($this->managers[$manager_id])) {
            return new WP_Error('invalid_manager', 'Invalid account manager ID', array('status' => 400));
        }
        
        // Get customer IDs assigned to this manager
        $args = array(
            'meta_key' => '_account_manager_id',
            'meta_value' => $manager_id,
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'fields' => 'all'
        );
        
        if ($search) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }
        
        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total = $user_query->get_total();
        
        $customers = array();
        foreach ($users as $user) {
            $customer = new WC_Customer($user->ID);
            if ($customer->get_id()) {
                $customer_data = $this->format_customer_data($customer);
                
                // Add province if available (BuddyPress)
                if (function_exists('xprofile_get_field_data')) {
                    $province = xprofile_get_field_data('Business Province', $user->ID);
                    $customer_data['province'] = !empty($province) ? $province : 'Not Set';
                }
                
                $customers[] = $customer_data;
            }
        }
        
        return array(
            'data' => $customers,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'manager' => array(
                'id' => $manager_id,
                'name' => $this->managers[$manager_id]
            )
        );
    }
    
    /**
     * Get orders by account manager
     */
    public function get_orders_by_manager($request) {
        global $wpdb;
        
        $manager_id = $request['id'];
        $page = $request->get_param('page') ?: 1;
        $per_page = min($request->get_param('per_page') ?: 20, 100);
        $status = $request->get_param('status');
        $date_from = $request->get_param('date_from');
        $date_to = $request->get_param('date_to');
        
        if (!isset($this->managers[$manager_id])) {
            return new WP_Error('invalid_manager', 'Invalid account manager ID', array('status' => 400));
        }
        
        // Get customer IDs assigned to this manager
        $customer_ids = $wpdb->get_col($wpdb->prepare("
            SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = '_account_manager_id' 
            AND meta_value = %s
        ", $manager_id));
        
        if (empty($customer_ids)) {
            return array(
                'data' => array(),
                'total' => 0,
                'pages' => 0,
                'manager' => array(
                    'id' => $manager_id,
                    'name' => $this->managers[$manager_id]
                )
            );
        }
        
        // Build WC_Order_Query arguments
        $args = array(
            'customer' => $customer_ids,
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects'
        );
        
        if ($status) {
            $args['status'] = $status;
        }
        
        if ($date_from) {
            $args['date_created'] = '>=' . $date_from;
        }
        
        if ($date_to) {
            if (isset($args['date_created'])) {
                $args['date_created'] = array(
                    'after' => $date_from,
                    'before' => $date_to . ' 23:59:59',
                    'inclusive' => true
                );
            } else {
                $args['date_created'] = '<=' . $date_to . ' 23:59:59';
            }
        }
        
        $order_query = new WC_Order_Query($args);
        $orders = $order_query->get_orders();
        
        // Get total count
        $total_args = $args;
        unset($total_args['limit'], $total_args['offset']);
        $total_args['return'] = 'ids';
        $total_query = new WC_Order_Query($total_args);
        $total = count($total_query->get_orders());
        
        $formatted_orders = array();
        foreach ($orders as $order) {
            $order_data = $this->format_order_data($order);
            
            // Add customer account manager info
            $customer_id = $order->get_customer_id();
            if ($customer_id) {
                $customer_manager_id = get_user_meta($customer_id, '_account_manager_id', true);
                $order_data['customer_manager'] = array(
                    'id' => $customer_manager_id,
                    'name' => isset($this->managers[$customer_manager_id]) ? $this->managers[$customer_manager_id] : 'No Manager'
                );
            }
            
            $formatted_orders[] = $order_data;
        }
        
        return array(
            'data' => $formatted_orders,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'manager' => array(
                'id' => $manager_id,
                'name' => $this->managers[$manager_id]
            )
        );
    }
    
    /**
     * Assign customer to account manager
     */
    public function assign_customer($request) {
        $customer_id = $request->get_param('customer_id');
        $manager_id = $request->get_param('manager_id');
        
        if (!isset($this->managers[$manager_id])) {
            return new WP_Error('invalid_manager', 'Invalid account manager ID', array('status' => 400));
        }
        
        // Check if customer exists
        $customer = new WC_Customer($customer_id);
        if (!$customer->get_id()) {
            return new WP_Error('customer_not_found', 'Customer not found', array('status' => 404));
        }
        
        // Update user meta
        $updated = update_user_meta($customer_id, '_account_manager_id', $manager_id);
        
        if ($updated !== false) {
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Customer %s has been assigned to %s', 'sales-dashboard'),
                    $customer->get_display_name(),
                    $this->managers[$manager_id]
                ),
                'customer' => $this->format_customer_data($customer),
                'manager' => array(
                    'id' => $manager_id,
                    'name' => $this->managers[$manager_id]
                )
            );
        } else {
            return new WP_Error('assignment_failed', 'Failed to assign customer', array('status' => 500));
        }
    }
    
    /**
     * Unassign customer from account manager
     */
    public function unassign_customer($request) {
        $customer_id = $request->get_param('customer_id');
        
        // Check if customer exists
        $customer = new WC_Customer($customer_id);
        if (!$customer->get_id()) {
            return new WP_Error('customer_not_found', 'Customer not found', array('status' => 404));
        }
        
        // Remove user meta
        $deleted = delete_user_meta($customer_id, '_account_manager_id');
        
        if ($deleted) {
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Customer %s has been unassigned from account manager', 'sales-dashboard'),
                    $customer->get_display_name()
                ),
                'customer' => $this->format_customer_data($customer)
            );
        } else {
            return new WP_Error('unassignment_failed', 'Failed to unassign customer', array('status' => 500));
        }
    }
    
    /**
     * Get account manager statistics
     */
    public function get_manager_statistics($request) {
        global $wpdb;
        
        $manager_id = $request['id'];
        $period = $request->get_param('period') ?: 'month';
        
        if (!isset($this->managers[$manager_id])) {
            return new WP_Error('invalid_manager', 'Invalid account manager ID', array('status' => 400));
        }
        
        // Get customer IDs
        $customer_ids = $wpdb->get_col($wpdb->prepare("
            SELECT user_id FROM {$wpdb->usermeta} 
            WHERE meta_key = '_account_manager_id' 
            AND meta_value = %s
        ", $manager_id));
        
        if (empty($customer_ids)) {
            return array(
                'manager' => array('id' => $manager_id, 'name' => $this->managers[$manager_id]),
                'customers_count' => 0,
                'total_orders' => 0,
                'total_revenue' => 0,
                'avg_order_value' => 0,
                'monthly_performance' => array()
            );
        }
        
        $customer_ids_str = implode(',', array_map('intval', $customer_ids));
        
        // Get date condition
        $date_condition = $this->get_date_condition($period);
        
        // Get statistics
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                AVG(total_amount) as avg_order_value
            FROM {$wpdb->prefix}wc_orders 
            WHERE status IN ('wc-completed', 'wc-processing')
            AND customer_id IN ($customer_ids_str)
            AND $date_condition
        ");
        
        // Get monthly performance (last 6 months)
        $monthly_performance = $wpdb->get_results("
            SELECT 
                DATE_FORMAT(date_created_gmt, '%Y-%m') as month,
                DATE_FORMAT(date_created_gmt, '%M %Y') as month_name,
                COUNT(*) as orders_count,
                COALESCE(SUM(total_amount), 0) as revenue
            FROM {$wpdb->prefix}wc_orders 
            WHERE status IN ('wc-completed', 'wc-processing')
            AND customer_id IN ($customer_ids_str)
            AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(date_created_gmt, '%Y-%m')
            ORDER BY month DESC
        ");
        
        return array(
            'manager' => array(
                'id' => $manager_id, 
                'name' => $this->managers[$manager_id]
            ),
            'customers_count' => count($customer_ids),
            'total_orders' => (int)$stats->total_orders,
            'total_revenue' => (float)$stats->total_revenue,
            'avg_order_value' => (float)$stats->avg_order_value,
            'monthly_performance' => array_reverse($monthly_performance),
            'period' => $period
        );
    }
    
    /**
     * Bulk assign customers
     */
    public function bulk_assign_customers($request) {
        $customer_ids = $request->get_param('customer_ids');
        $manager_id = $request->get_param('manager_id');
        
        if (!isset($this->managers[$manager_id])) {
            return new WP_Error('invalid_manager', 'Invalid account manager ID', array('status' => 400));
        }
        
        $success_count = 0;
        $failed_customers = array();
        
        foreach ($customer_ids as $customer_id) {
            $customer = new WC_Customer($customer_id);
            
            if (!$customer->get_id()) {
                $failed_customers[] = array(
                    'id' => $customer_id,
                    'error' => 'Customer not found'
                );
                continue;
            }
            
            $updated = update_user_meta($customer_id, '_account_manager_id', $manager_id);
            
            if ($updated !== false) {
                $success_count++;
            } else {
                $failed_customers[] = array(
                    'id' => $customer_id,
                    'name' => $customer->get_display_name(),
                    'error' => 'Failed to update'
                );
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf(
                __('%d customers assigned to %s', 'sales-dashboard'),
                $success_count,
                $this->managers[$manager_id]
            ),
            'success_count' => $success_count,
            'failed_count' => count($failed_customers),
            'failed_customers' => $failed_customers,
            'manager' => array(
                'id' => $manager_id,
                'name' => $this->managers[$manager_id]
            )
        );
    }
    
    /**
     * Add account manager field to user profile
     */
    public function add_account_manager_field($user) {
        if (!current_user_can('edit_users')) {
            return;
        }
        
        $current_manager = get_user_meta($user->ID, '_account_manager_id', true);
        ?>
        <h3><?php _e('Sales Dashboard Settings', 'sales-dashboard'); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="account_manager_id"><?php _e('Account Manager', 'sales-dashboard'); ?></label>
                </th>
                <td>
                    <select name="account_manager_id" id="account_manager_id" class="regular-text">
                        <option value=""><?php _e('No Account Manager', 'sales-dashboard'); ?></option>
                        <?php foreach ($this->managers as $manager_id => $manager_name) : ?>
                            <option value="<?php echo esc_attr($manager_id); ?>" <?php selected($current_manager, $manager_id); ?>>
                                <?php echo esc_html($manager_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Select the account manager responsible for this customer.', 'sales-dashboard'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save account manager field
     */
    public function save_account_manager_field($user_id) {
        if (!current_user_can('edit_users')) {
            return;
        }
        
        $manager_id = isset($_POST['account_manager_id']) ? sanitize_text_field($_POST['account_manager_id']) : '';
        
        if (empty($manager_id)) {
            delete_user_meta($user_id, '_account_manager_id');
        } else {
            if (isset($this->managers[$manager_id])) {
                update_user_meta($user_id, '_account_manager_id', $manager_id);
            }
        }
    }
    
    /**
     * Get customers count by manager
     */
    private function get_customers_count_by_manager($manager_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->usermeta} 
            WHERE meta_key = '_account_manager_id' 
            AND meta_value = %s
        ", $manager_id));
        
        return (int)$count;
    }
    
    /**
     * Get date condition based on period
     */
    private function get_date_condition($period) {
        switch ($period) {
            case 'week':
                return "date_created_gmt >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            case 'month':
                return "date_created_gmt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            case 'quarter':
                return "date_created_gmt >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            case 'year':
                return "date_created_gmt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "date_created_gmt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
    }
    
    /**
     * Format customer data
     */
    private function format_customer_data($customer) {
        $manager_id = get_user_meta($customer->get_id(), '_account_manager_id', true);
        
        return array(
            'id' => $customer->get_id(),
            'email' => $customer->get_email(),
            'first_name' => $customer->get_first_name(),
            'last_name' => $customer->get_last_name(),
            'display_name' => $customer->get_display_name(),
            'username' => $customer->get_username(),
            'date_created' => $customer->get_date_created() ? $customer->get_date_created()->date('Y-m-d H:i:s') : '',
            'total_spent' => $customer->get_total_spent(),
            'orders_count' => $customer->get_order_count(),
            'avatar_url' => get_avatar_url($customer->get_id()),
            'account_manager' => array(
                'id' => $manager_id,
                'name' => isset($this->managers[$manager_id]) ? $this->managers[$manager_id] : 'No Manager'
            ),
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
     * Format order data
     */
    private function format_order_data($order) {
        return array(
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'currency' => $order->get_currency(),
            'total' => $order->get_total(),
            'subtotal' => $order->get_subtotal(),
            'tax_total' => $order->get_total_tax(),
            'shipping_total' => $order->get_shipping_total(),
            'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'date_modified' => $order->get_date_modified()->date('Y-m-d H:i:s'),
            'customer_id' => $order->get_customer_id(),
            'billing' => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title()
        );
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
