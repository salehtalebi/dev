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
        
        register_rest_route('sales-dashboard/v1', '/wc/customers/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_customer_details'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
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
        
        // Export routes - remove these since they exist in class-export.php
        // register_rest_route('sales-dashboard/v1', '/export/orders', array(
        //     'methods' => 'GET',
        //     'callback' => array($this, 'export_orders'),
        //     'permission_callback' => array($this, 'check_permissions')
        // ));
        
        // register_rest_route('sales-dashboard/v1', '/export/customers', array(
        //     'methods' => 'GET',
        //     'callback' => array($this, 'export_customers'),
        //     'permission_callback' => array($this, 'check_permissions')
        // ));

        // Analytics routes - remove these since they exist in class-analytics.php
        // register_rest_route('sales-dashboard/v1', '/analytics/dashboard', array(
        //     'methods' => 'GET',
        //     'callback' => array($this, 'get_dashboard_analytics'),
        //     'permission_callback' => array($this, 'check_permissions')
        // ));
        
        // register_rest_route('sales-dashboard/v1', '/analytics/top-products', array(
        //     'methods' => 'GET',
        //     'callback' => array($this, 'get_top_products'),
        //     'permission_callback' => array($this, 'check_permissions')
        // ));
        
        // register_rest_route('sales-dashboard/v1', '/analytics/monthly-revenue', array(
        //     'methods' => 'GET',
        //     'callback' => array($this, 'get_monthly_revenue'),
        //     'permission_callback' => array($this, 'check_permissions')
        // ));
        
        // register_rest_route('sales-dashboard/v1', '/analytics/sales-comparison', array(
        //     'methods' => 'GET',
        //     'callback' => array($this, 'get_sales_comparison'),
        //     'permission_callback' => array($this, 'check_permissions')
        // ));
        
        // register_rest_route('sales-dashboard/v1', '/analytics/manager-performance', array(
        //     'methods' => 'GET',
        //     'callback' => array($this, 'get_manager_performance'),
        //     'permission_callback' => array($this, 'check_permissions')
        // ));
    }
    
    /**
     * Get orders by account manager
     */
    public function get_orders_by_manager($request) {
        try {
            $manager_id = $request['id'];
            $page = $request->get_param('page') ?: 1;
            $per_page = min($request->get_param('per_page') ?: 20, 100);
            $offset = ($page - 1) * $per_page;
            $status = $request->get_param('status');
            
            // Get customer IDs assigned to this manager
            $customers = get_users(array(
                'meta_key' => '_account_manager_id',
                'meta_value' => $manager_id,
                'fields' => 'ID'
            ));
            
            if (empty($customers)) {
                return array(
                    'data' => array(),
                    'total' => 0,
                    'pages' => 0
                );
            }
            
            // Build WooCommerce query args
            $args = array(
                'customer' => $customers,
                'limit' => $per_page,
                'offset' => $offset,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'objects'
            );
            
            if ($status) {
                $args['status'] = $status;
            }
            
            // Get orders
            $orders = wc_get_orders($args);
            
            // Get total count for pagination
            $count_args = $args;
            $count_args['limit'] = -1;
            $count_args['paginate'] = true;
            $count_query = wc_get_orders($count_args);
            $total = $count_query->total;
            
            $formatted_orders = array();
            foreach ($orders as $order) {
                $formatted_orders[] = array(
                    'id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'status' => $order->get_status(),
                    'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
                    'total' => $order->get_total(),
                    'currency' => $order->get_currency(),
                    'customer_id' => $order->get_customer_id(),
                    'billing' => $order->get_address('billing'),
                    'shipping' => $order->get_address('shipping')
                );
            }
            
            return array(
                'data' => $formatted_orders,
                'total' => $total,
                'pages' => ceil($total / $per_page)
            );
            
        } catch (Exception $e) {
            error_log('Get Orders by Manager Error: ' . $e->getMessage());
            return array(
                'data' => array(),
                'total' => 0,
                'pages' => 0
            );
        }
    }
    
    /**
     * Get customers by account manager
     */
    public function get_customers_by_manager($request) {
        try {
            $manager_id = $request['id'];
            $search = $request->get_param('search');
            
            $customers = get_users(array(
                'meta_key' => '_account_manager_id',
                'meta_value' => $manager_id,
                'fields' => array('ID', 'display_name', 'user_email'),
                'search' => $search ? "*{$search}*" : ''
            ));
            
            if (empty($customers)) {
                return array();
            }
            
            $formatted_customers = array();
            foreach ($customers as $customer) {
                $wc_customer = new WC_Customer($customer->ID);
                if ($wc_customer->get_id()) {
                    $formatted_customers[] = array(
                        'id' => $customer->ID,
                        'name' => $customer->display_name,
                        'email' => $customer->user_email,
                        'total_orders' => $wc_customer->get_order_count(),
                        'total_spent' => $wc_customer->get_total_spent(),
                        'date_registered' => $wc_customer->get_date_created()
                    );
                }
            }
            
            return $formatted_customers;
            
        } catch (Exception $e) {
            error_log('Get Customers by Manager Error: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get order statistics
     */
    public function get_order_statistics($request) {
        try {
            $period = $request->get_param('period') ?: 'month';
            $manager_id = $request->get_param('manager_id');
            
            // Get date range
            $date_range = array();
            switch ($period) {
                case 'week':
                    $date_range = array(
                        'after' => date('Y-m-d', strtotime('-1 week')),
                        'before' => date('Y-m-d')
                    );
                    break;
                case 'month':
                    $date_range = array(
                        'after' => date('Y-m-d', strtotime('-1 month')),
                        'before' => date('Y-m-d')
                    );
                    break;
                case 'year':
                    $date_range = array(
                        'after' => date('Y-m-d', strtotime('-1 year')),
                        'before' => date('Y-m-d')
                    );
                    break;
                default:
                    $date_range = array(
                        'after' => date('Y-m-01'),
                        'before' => date('Y-m-d')
                    );
            }
            
            $orders_args = array(
                'status' => array('wc-completed', 'wc-processing'),
                'date_created' => $date_range['after'] . '...' . $date_range['before'],
                'limit' => -1,
                'return' => 'objects'
            );
            
            // Filter by manager if specified
            if ($manager_id) {
                $customers = get_users(array(
                    'meta_key' => '_account_manager_id',
                    'meta_value' => $manager_id,
                    'fields' => 'ID'
                ));
                
                if (empty($customers)) {
                    return array('orders_count' => 0, 'revenue' => 0);
                }
                
                $orders_args['customer'] = $customers;
            }
            
            $orders = wc_get_orders($orders_args);
            
            $orders_count = count($orders);
            $revenue = 0;
            
            foreach ($orders as $order) {
                $revenue += $order->get_total();
            }
            
            return array(
                'orders_count' => (int) $orders_count,
                'revenue' => (float) $revenue,
                'period' => $period
            );
            
        } catch (Exception $e) {
            error_log('Order Statistics Error: ' . $e->getMessage());
            return array('orders_count' => 0, 'revenue' => 0, 'period' => $period);
        }
    }
    
    /**
     * Get customer statistics
     */
    public function get_customer_statistics($request) {
        try {
            $customer_id = $request['id'];
            $customer = new WC_Customer($customer_id);
            
            if (!$customer->get_id()) {
                return new WP_Error('customer_not_found', 'مشتری یافت نشد', array('status' => 404));
            }
            
            // Get orders for the last 12 months
            $twelve_months_ago = date('Y-m-d', strtotime('-12 months'));
            $orders = wc_get_orders(array(
                'customer_id' => $customer_id,
                'status' => array('wc-completed', 'wc-processing'),
                'date_created' => $twelve_months_ago . '...' . date('Y-m-d'),
                'limit' => -1,
                'return' => 'objects'
            ));
            
            $monthly_data = array();
            
            foreach ($orders as $order) {
                $month = $order->get_date_created()->format('Y-m');
                
                if (!isset($monthly_data[$month])) {
                    $monthly_data[$month] = array(
                        'month' => $month,
                        'orders_count' => 0,
                        'total_spent' => 0
                    );
                }
                
                $monthly_data[$month]['orders_count']++;
                $monthly_data[$month]['total_spent'] += $order->get_total();
            }
            
            // Sort by month
            krsort($monthly_data);
            $monthly_orders = array_values($monthly_data);
            
            $current_month = date('Y-m');
            $last_month = date('Y-m', strtotime('-1 month'));
            
            $current_month_total = isset($monthly_data[$current_month]) ? $monthly_data[$current_month]['total_spent'] : 0;
            $last_month_total = isset($monthly_data[$last_month]) ? $monthly_data[$last_month]['total_spent'] : 0;
            
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
            
        } catch (Exception $e) {
            error_log('Customer Statistics Error: ' . $e->getMessage());
            return new WP_Error('server_error', 'Error retrieving customer statistics', array('status' => 500));
        }
    }
    
    /**
     * Get enhanced orders (with additional data)
     */
    public function get_enhanced_orders($request) {
        try {
            // Check if WooCommerce is active
            if (!class_exists('WooCommerce') || !class_exists('WC_Order_Query')) {
                return new WP_Error('woocommerce_not_active', 'WooCommerce is not active', array('status' => 500));
            }

            $args = array(
                'limit' => min($request->get_param('per_page') ?: 20, 100),
                'page' => $request->get_param('page') ?: 1,
                'orderby' => $request->get_param('orderby') ?: 'date',
                'order' => $request->get_param('order') ?: 'DESC',
                // Avoid refunds objects in results
                'type' => 'shop_order'
            );
            
            if ($request->get_param('status')) {
                $args['status'] = $request->get_param('status');
            }
            
            if ($request->get_param('customer')) {
                $args['customer'] = $request->get_param('customer');
            }
            
            // For search, we need to get more records since we filter post-query
            $has_search = $request->get_param('search');
            if ($has_search) {
                $args['limit'] = 1000; // Get more orders to search through
                $args['paginate'] = false;
            }
            
            // Handle date range filters
            if ($request->get_param('date_range')) {
                $date_range = $request->get_param('date_range');
                $date_args = $this->get_date_range_args($date_range);
                if ($date_args) {
                    $args = array_merge($args, $date_args);
                }
            } elseif ($request->get_param('date_from') || $request->get_param('date_to')) { // Custom date range (YYYY-mm-dd)
                // Handle custom date range (build one correct range expression)
                $date_from = $request->get_param('date_from');
                $date_to = $request->get_param('date_to');
                if ($date_from && $date_to) {
                    $args['date_created'] = $date_from . '...' . $date_to . ' 23:59:59';
                } elseif ($date_from) {
                    $args['date_created'] = '>=' . $date_from;
                } elseif ($date_to) {
                    $args['date_created'] = '<=' . $date_to . ' 23:59:59';
                }
            }
            
            // Handle account manager filter
            if ($request->get_param('account_manager')) {
                $manager_id = sanitize_text_field($request->get_param('account_manager'));
                
                // Get users assigned to this manager
                $customers = get_users(array(
                    'meta_key' => '_account_manager_id',
                    'meta_value' => $manager_id,
                    'fields' => 'ID'
                ));
                
                if (!empty($customers)) {
                    $args['customer'] = $customers;
                } else {
                    // No customers for this manager, return empty result
                    return array(
                        'data' => array(),
                        'total' => 0,
                        'page' => intval($request->get_param('page') ?: 1),
                        'per_page' => intval($request->get_param('per_page') ?: 20),
                        'pages' => 0
                    );
                }
            }
            
            // Province filter flag and value (we'll apply manually for consistency across HPOS/legacy)
            $province_param = $request->get_param('province');
            $has_province_filter = ($province_param !== null && $province_param !== '' && $province_param !== 'null');
            $province = $has_province_filter ? sanitize_text_field($province_param) : '';

            // Check if we have amount filters (these require post-query filtering)
            $min_amount = $request->get_param('min_amount');
            $max_amount = $request->get_param('max_amount');
            $has_amount_filter = ($min_amount !== null && $min_amount !== '') || 
                                 ($max_amount !== null && $max_amount !== '');
            
            // Get total count for pagination (optimized, but aligned with filters)
            $count_args = array(
                'return' => 'ids',
                'status' => $args['status'] ?? 'any',
                'limit' => -1,
                // Only count real orders, not refunds
                'type' => 'shop_order',
            );

            // Apply customer filter for count if exists
            if (isset($args['customer'])) {
                $count_args['customer'] = $args['customer'];
            }

            // Do NOT push province into count meta_query; we'll apply it manually to ensure HPOS compatibility

            // Apply date filters for count if present
            if ($request->get_param('date_range')) {
                $date_range = $request->get_param('date_range');
                $date_args = $this->get_date_range_args($date_range);
                if ($date_args) {
                    $count_args = array_merge($count_args, $date_args);
                }
            } elseif ($request->get_param('date_from') || $request->get_param('date_to')) { // Count query custom date range
                $date_from = $request->get_param('date_from');
                $date_to = $request->get_param('date_to');
                if ($date_from && $date_to) {
                    $count_args['date_created'] = $date_from . '...' . $date_to . ' 23:59:59';
                } elseif ($date_from) {
                    $count_args['date_created'] = '>=' . $date_from;
                } elseif ($date_to) {
                    $count_args['date_created'] = '<=' . $date_to . ' 23:59:59';
                }
            }

            // Get initial ID set using count args (status/date/customer etc.)
            $count_query = new WC_Order_Query($count_args);
            $count_ids = $count_query->get_orders();

            // Collect filters that require manual evaluation (amount, province)
            $min_amount = $request->get_param('min_amount');
            $max_amount = $request->get_param('max_amount');
            $has_amount_filter = ($min_amount !== null && $min_amount !== '') || ($max_amount !== null && $max_amount !== '');

            $needs_manual_filtering = $has_amount_filter || $has_province_filter;

            if ($needs_manual_filtering && is_array($count_ids)) {
                $manually_filtered_ids = array();
                foreach ($count_ids as $order_id) {
                    $o = wc_get_order($order_id);
                    if (!$o) continue;
                    // Safety: skip refund objects defensively
                    if (is_a($o, 'WC_Order_Refund') || is_a($o, '\\Automattic\\WooCommerce\\Admin\\Overrides\\OrderRefund')) continue;

                    // Province filter (shipping state ONLY per requirement)
                    if ($has_province_filter) {
                        $sstate = $o->get_shipping_state();
                        if (!$sstate || strtoupper($sstate) !== strtoupper($province)) continue;
                    }

                    // Amount filter
                    if ($has_amount_filter) {
                        $total_val = floatval($o->get_total());
                        if ($min_amount !== null && $min_amount !== '' && $total_val < floatval($min_amount)) continue;
                        if ($max_amount !== null && $max_amount !== '' && $total_val > floatval($max_amount)) continue;
                    }

                    $manually_filtered_ids[] = $order_id;
                }

                $count_ids = $manually_filtered_ids;
            }

            $total_orders = is_array($count_ids) ? count($count_ids) : intval($count_ids);
            
            // Adjust query limit if amount filter is present (need to get more orders to filter)
            if ($needs_manual_filtering) {
                // Get more orders than needed to account for post-filtering
                // For amount filtering, we need to get all filtered IDs and slice them
                // Use the count_ids we already have (which includes amount filter)
                if (is_array($count_ids) && count($count_ids) > 0) {
                    $requested_per_page = intval($request->get_param('per_page') ?: 20);
                    $current_page = intval($request->get_param('page') ?: 1);
                    
                    // Slice the IDs for current page
                    $start = ($current_page - 1) * $requested_per_page;
                    $page_ids = array_slice($count_ids, $start, $requested_per_page);
                    
                    // Update args to get only these specific orders
                    if (!empty($page_ids)) {
                        // Remove pagination params and use include instead
                        unset($args['limit']);
                        unset($args['page']);
                        $args['include'] = $page_ids;
                        $args['orderby'] = 'include'; // Maintain order from array
                    } else {
                        // No orders for this page
                        return array(
                            'data' => array(),
                            'total' => $total_orders,
                            'page' => $current_page,
                            'per_page' => $requested_per_page,
                            'pages' => ceil($total_orders / $requested_per_page)
                        );
                    }
                }
            } else {
                // No amount filter, use normal pagination
                $args['limit'] = min($args['limit'], 100); // Max 100 orders per page
            }

            // Check if search is present (requires post-query filtering)
            $has_search = $request->get_param('search');
            
            // If search is present and we have amount filter, we need to get all filtered IDs
            if ($has_search && $has_amount_filter && is_array($count_ids)) {
                // Get all filtered orders (not just current page) for searching
                unset($args['include']);
                unset($args['limit']);
                unset($args['page']);
                $args['include'] = $count_ids;
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
            } elseif ($has_search && !$needs_manual_filtering) {
                // Get more orders for searching when no amount filter
                $args['limit'] = 1000;
                unset($args['page']); // Don't use built-in pagination for search
            }
            
            $order_query = new WC_Order_Query($args);
            $orders = $order_query->get_orders();

            // Enhanced post-filter search (email, name, id) if search supplied
            if ($has_search) {
                $search_term = strtolower(sanitize_text_field($request->get_param('search')));
                $orders = array_filter($orders, function($order) use ($search_term) {
                    if (!$order) return false;
                    $id_match = strpos((string) $order->get_id(), $search_term) !== false;
                    $billing_email = strtolower($order->get_billing_email());
                    $email_match = $billing_email && strpos($billing_email, $search_term) !== false;
                    $name_combined = strtolower(trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));
                    $name_match = $name_combined && strpos($name_combined, $search_term) !== false;
                    return $id_match || $email_match || $name_match;
                });
            }
            
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
            
            // Clear memory
            unset($orders, $order_query, $count_query);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            // Build response
            $page = intval($request->get_param('page') ?: 1);
            $per_page = intval($request->get_param('per_page') ?: 20);
            
            // If search was applied, recalculate total and paginate
            if ($has_search) {
                $search_total = count($formatted_orders);
                $pages = $per_page > 0 ? ceil($search_total / $per_page) : 1;
                
                // Slice for current page
                $start = ($page - 1) * $per_page;
                $formatted_orders = array_slice($formatted_orders, $start, $per_page);
                
                $response_payload = array(
                    'data' => $formatted_orders,
                    'total' => $search_total,
                    'page' => $page,
                    'per_page' => $per_page,
                    'pages' => $pages
                );
            } else {
                $pages = $per_page > 0 ? ceil($total_orders / $per_page) : 1;
                
                $response_payload = array(
                    'data' => $formatted_orders,
                    'total' => $total_orders,
                    'page' => $page,
                    'per_page' => $per_page,
                    'pages' => $pages
                );
            }

            $resp = new WP_REST_Response($response_payload);
            $resp->header('X-WP-Total', $response_payload['total']);
            $resp->header('X-WP-TotalPages', $response_payload['pages']);
            return $resp;
            
        } catch (Exception $e) {
            error_log('Sales Dashboard API Error in get_enhanced_orders: ' . $e->getMessage());
            return new WP_Error('api_error', 'An error occurred: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get enhanced customers (with additional data)
     */
    public function get_enhanced_customers($request) {
        $per_page = intval($request->get_param('per_page') ?: 20);
        $page = intval($request->get_param('page') ?: 1);

        $args = array(
            'number' => min($per_page, 100),
            'offset' => ($page - 1) * $per_page,
            'fields' => 'all'
        );

        if ($request->get_param('search')) {
            $args['search'] = '*' . esc_attr($request->get_param('search')) . '*';
        }

        if ($request->get_param('role')) {
            $args['role'] = $request->get_param('role');
        }

        // Handle account manager filter for customers
        if ($request->get_param('account_manager')) {
            $manager_id = sanitize_text_field($request->get_param('account_manager'));
            $args['meta_key'] = '_account_manager_id';
            $args['meta_value'] = $manager_id;
        }

        // Handle province filter with meta_query (pre-query filtering)
        if ($request->get_param('province')) {
            $province = sanitize_text_field($request->get_param('province'));
            
            // If we already have meta_key/value for account manager, convert to meta_query
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

        // Date registered filters
        $date_query = array();
        if ($request->get_param('date_from')) {
            $date_query['after'] = $request->get_param('date_from');
        }
        if ($request->get_param('date_to')) {
            // include the full day
            $date_query['before'] = $request->get_param('date_to') . ' 23:59:59';
        }
        if (!empty($date_query)) {
            $args['date_query'] = array($date_query);
        }

        // Query
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

        // Get total from query
        $total = intval($customer_query->get_total());
        
        $pages = $per_page > 0 ? ceil($total / $per_page) : 1;

        $response_payload = array(
            'data' => $formatted_customers,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'pages' => $pages
        );

        $resp = new WP_REST_Response($response_payload);
        $resp->header('X-WP-Total', $total);
        $resp->header('X-WP-TotalPages', $pages);
        return $resp;
    }
    
    /**
     * Get customer details
     */
    public function get_customer_details($request) {
        $customer_id = $request['id'];
        $customer = new WC_Customer($customer_id);
        
        if (!$customer->get_id()) {
            return new WP_Error('customer_not_found', 'مشتری یافت نشد', array('status' => 404));
        }
        
        $customer_data = $this->format_customer_data($customer);
        
        // Add account manager info
        $customer_data['account_manager'] = $this->get_customer_account_manager($customer->get_id());
        
        return $customer_data;
    }
    
    /**
     * Get order details
     */
    public function get_order_details($request) {
        $order_id = $request['id'];
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('order_not_found', 'Order not found', array('status' => 404));
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
            return new WP_Error('invalid_status', 'Invalid status', array('status' => 400));
        }
        
    $order->update_status($new_status, 'Status changed by Sales Dashboard');
        
        return array(
            'success' => true,
            'message' => 'Order status updated successfully',
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
        $manager_id = get_user_meta($customer_id, '_account_manager_id', true);
        
        if ($manager_id && $manager_id !== '' && $manager_id !== 'house') {
            $manager = get_user_by('id', $manager_id);
            if ($manager) {
                return array(
                    'id' => $manager->ID,
                    'name' => $manager->display_name,
                    'email' => $manager->user_email
                );
            }
        }
        
        // If manager_id is 'house' or empty, return house manager info
        if ($manager_id === 'house' || empty($manager_id)) {
            return array(
                'id' => 'house',
                'name' => 'House',
                'email' => ''
            );
        }
        
        return null;
    }
    
    /*
    // Export and Analytics methods are now handled by separate classes:
    // - class-export.php handles export_orders() and export_customers()
    // - class-analytics.php handles all analytics endpoints
    // These methods have been commented out to prevent conflicts
    
    /**
     * Export orders - DEPRECATED: Use class-export.php instead
     */
    /*
    public function export_orders($request) {
        // Build same arguments as get_enhanced_orders
        $args = array(
            'limit' => -1, // Get all orders for export
            'orderby' => $request->get_param('orderby') ?: 'date',
            'order' => $request->get_param('order') ?: 'DESC'
        );
        
        if ($request->get_param('status')) {
            $args['status'] = $request->get_param('status');
        }
        
        if ($request->get_param('customer')) {
            $args['customer'] = $request->get_param('customer');
        }
        
        if ($request->get_param('search')) {
            $args['search'] = $request->get_param('search');
        }
        
        // Handle account manager filter
        if ($request->get_param('account_manager')) {
            global $wpdb;
            $manager_id = $request->get_param('account_manager');
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE manager_id = %s
            ", $manager_id));
            
            if (!empty($customer_ids)) {
                $args['customer'] = $customer_ids;
            } else {
                return array('data' => array());
            }
        }
        
        $order_query = new WC_Order_Query($args);
        $orders = $order_query->get_orders();
        
        $export_data = array();
        foreach ($orders as $order) {
            $order_data = $this->format_order_data($order);
            
            // Add account manager info
            $customer_id = $order->get_customer_id();
            if ($customer_id) {
                $order_data['account_manager'] = $this->get_customer_account_manager($customer_id);
            }
            
            $export_data[] = $order_data;
        }
        
        return array('data' => $export_data);
    }
    */
    
    /**
     * Export customers - DEPRECATED: Use class-export.php instead
     */
    /*
    public function export_customers($request) {
        $args = array(
            'number' => -1 // Get all customers for export
        );
        
        if ($request->get_param('search')) {
            $args['search'] = '*' . esc_attr($request->get_param('search')) . '*';
        }
        
        // Handle account manager filter
        if ($request->get_param('account_manager')) {
            global $wpdb;
            $manager_id = $request->get_param('account_manager');
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE manager_id = %s
            ", $manager_id));
            
            if (!empty($customer_ids)) {
                $args['include'] = $customer_ids;
            } else {
                return array('data' => array());
            }
        }
        
        $customer_query = new WP_User_Query($args);
        $customers = $customer_query->get_results();
        
        $export_data = array();
        foreach ($customers as $user) {
            $customer = new WC_Customer($user->ID);
            $customer_data = $this->format_customer_data($customer);
            
            // Add account manager info
            $customer_data['account_manager'] = $this->get_customer_account_manager($customer->get_id());
            
            $export_data[] = $customer_data;
        }
        
        return array('data' => $export_data);
    }
    */
    
    /**
     * Get dashboard analytics - DEPRECATED: Use class-analytics.php instead
     */
    /*
    public function get_dashboard_analytics($request) {
        global $wpdb;
        
        // Get basic stats
        $today_orders = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}wc_orders 
            WHERE DATE(date_created_gmt) = CURDATE()
            AND status IN ('wc-completed', 'wc-processing')
        ");
        
        $today_revenue = $wpdb->get_var("
            SELECT COALESCE(SUM(total_amount), 0) 
            FROM {$wpdb->prefix}wc_orders 
            WHERE DATE(date_created_gmt) = CURDATE()
            AND status IN ('wc-completed', 'wc-processing')
        ");
        
        $new_customers = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}users 
            WHERE DATE(user_registered) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
        ");
        
        $avg_order_value = $wpdb->get_var("
            SELECT COALESCE(AVG(total_amount), 0) 
            FROM {$wpdb->prefix}wc_orders 
            WHERE DATE(date_created_gmt) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
            AND status IN ('wc-completed', 'wc-processing')
        ");
        
        return array(
            'todayOrders' => intval($today_orders),
            'todayRevenue' => floatval($today_revenue),
            'newCustomers' => intval($new_customers),
            'averageOrderValue' => floatval($avg_order_value),
            'ordaysGrowth' => 5.2, // Mock data - you can calculate real growth
            'revenueGrowth' => 8.5  // Mock data - you can calculate real growth
        );
    }
    */
    
    /**
     * Get top products - DEPRECATED: Use class-analytics.php instead
     */
    /*
    public function get_top_products($request) {
        global $wpdb;
        
        $period = $request->get_param('period') ?: 'month';
        $date_condition = '';
        
        switch ($period) {
            case 'week':
                $date_condition = "AND o.date_created_gmt >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $date_condition = "AND o.date_created_gmt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'year':
                $date_condition = "AND o.date_created_gmt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
        }
        
        $results = $wpdb->get_results("
            SELECT 
                oi.product_id,
                p.post_title as product_name,
                SUM(oi.quantity) as total_sold,
                SUM(oi.total) as total_revenue
            FROM {$wpdb->prefix}wc_order_items oi
            LEFT JOIN {$wpdb->prefix}wc_orders o ON oi.order_id = o.id
            LEFT JOIN {$wpdb->prefix}posts p ON oi.product_id = p.ID
            WHERE oi.type = 'line_item'
            AND o.status IN ('wc-completed', 'wc-processing')
            {$date_condition}
            GROUP BY oi.product_id
            ORDER BY total_sold DESC
            LIMIT 10
        ");
        
        return array('data' => $results);
    }
    */
    
    /**
     * Get monthly revenue - DEPRECATED: Use class-analytics.php instead
     */
    /*
    public function get_monthly_revenue($request) {
        global $wpdb;
        
        $months = intval($request->get_param('months') ?: 12);
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE_FORMAT(date_created_gmt, '%%Y-%%m') as month,
                COALESCE(SUM(total_amount), 0) as revenue
            FROM {$wpdb->prefix}wc_orders
            WHERE date_created_gmt >= DATE_SUB(NOW(), INTERVAL %d MONTH)
            AND status IN ('wc-completed', 'wc-processing')
            GROUP BY DATE_FORMAT(date_created_gmt, '%%Y-%%m')
            ORDER BY month ASC
        ", $months));
        
        return array('data' => $results);
    }
    */
    
    /**
     * Get sales comparison - DEPRECATED: Use class-analytics.php instead
     */
    /*
    public function get_sales_comparison($request) {
        global $wpdb;
        
        $current_month = date('Y-m');
        $last_month = date('Y-m', strtotime('-1 month'));
        
        $current_month_sales = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM {$wpdb->prefix}wc_orders
            WHERE DATE_FORMAT(date_created_gmt, '%%Y-%%m') = %s
            AND status IN ('wc-completed', 'wc-processing')
        ", $current_month));
        
        $last_month_sales = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(total_amount), 0)
            FROM {$wpdb->prefix}wc_orders
            WHERE DATE_FORMAT(date_created_gmt, '%%Y-%%m') = %s
            AND status IN ('wc-completed', 'wc-processing')
        ", $last_month));
        
        $growth = $last_month_sales > 0 ? 
            (($current_month_sales - $last_month_sales) / $last_month_sales) * 100 : 0;
        
        return array(
            'currentMonth' => floatval($current_month_sales),
            'lastMonth' => floatval($last_month_sales),
            'growth' => round($growth, 2)
        );
    }
    */
    
    /**
     * Get date range arguments for WC_Order_Query
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
     * Get manager performance - DEPRECATED: Use class-analytics.php instead
     */
    /*
    public function get_manager_performance($request) {
        global $wpdb;
        
        $results = array();
        
        foreach ($this->managers as $manager_id => $manager_name) {
            $customer_ids = $wpdb->get_col($wpdb->prepare("
                SELECT customer_id FROM {$wpdb->prefix}customer_account_managers 
                WHERE manager_id = %s
            ", $manager_id));
            
            if (!empty($customer_ids)) {
                $customer_ids_str = implode(',', array_map('intval', $customer_ids));
                
                $stats = $wpdb->get_row("
                    SELECT 
                        COUNT(*) as orders_count,
                        COALESCE(SUM(total_amount), 0) as revenue
                    FROM {$wpdb->prefix}wc_orders
                    WHERE customer_id IN ($customer_ids_str)
                    AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
                    AND status IN ('wc-completed', 'wc-processing')
                ");
                
                $results[] = array(
                    'manager_id' => $manager_id,
                    'manager_name' => $manager_name,
                    'orders_count' => intval($stats->orders_count),
                    'revenue' => floatval($stats->revenue)
                );
            }
        }
        
        return array('data' => $results);
    }
    */

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
