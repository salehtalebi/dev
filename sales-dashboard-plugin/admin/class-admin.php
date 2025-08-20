<?php

/**
 * Admin Panel Class
 */
class Sales_Dashboard_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Sales Dashboard', 'sales-dashboard'),
            __('Sales Dashboard', 'sales-dashboard'),
            'manage_woocommerce',
            'sales-dashboard',
            array($this, 'admin_page'),
            'dashicons-chart-line',
            30
        );
        
        add_submenu_page(
            'sales-dashboard',
            __('Settings', 'sales-dashboard'),
            __('Settings', 'sales-dashboard'),
            'manage_options',
            'sales-dashboard-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'sales-dashboard',
            __('JWT Tokens', 'sales-dashboard'),
            __('JWT Tokens', 'sales-dashboard'),
            'manage_options',
            'sales-dashboard-tokens',
            array($this, 'tokens_page')
        );
        
        add_submenu_page(
            'sales-dashboard',
            __('API Documentation', 'sales-dashboard'),
            __('API Docs', 'sales-dashboard'),
            'manage_woocommerce',
            'sales-dashboard-docs',
            array($this, 'docs_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'sales-dashboard') === false) {
            return;
        }
        
        wp_enqueue_style(
            'sales-dashboard-admin',
            SALES_DASHBOARD_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            SALES_DASHBOARD_VERSION
        );
        
        wp_enqueue_script(
            'sales-dashboard-admin',
            SALES_DASHBOARD_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            SALES_DASHBOARD_VERSION,
            true
        );
        
        wp_localize_script('sales-dashboard-admin', 'salesDashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sales_dashboard_nonce'),
            'api_base' => rest_url('sales-dashboard/v1/')
        ));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('sales_dashboard_settings', 'sales_dashboard_jwt_secret');
        register_setting('sales_dashboard_settings', 'sales_dashboard_cors_origins');
        register_setting('sales_dashboard_settings', 'sales_dashboard_token_expiry');
        register_setting('sales_dashboard_settings', 'sales_dashboard_rate_limit');
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Sales Dashboard', 'sales-dashboard'); ?></h1>
            
            <div class="sales-dashboard-admin">
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3><?php _e('Total Orders', 'sales-dashboard'); ?></h3>
                        <div class="stat-number" id="total-orders">-</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e('Total Revenue', 'sales-dashboard'); ?></h3>
                        <div class="stat-number" id="total-revenue">-</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e('Active Customers', 'sales-dashboard'); ?></h3>
                        <div class="stat-number" id="active-customers">-</div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e('Account Managers', 'sales-dashboard'); ?></h3>
                        <div class="stat-number" id="account-managers">6</div>
                    </div>
                </div>
                
                <div class="dashboard-actions">
                    <h2><?php _e('Quick Actions', 'sales-dashboard'); ?></h2>
                    
                    <div class="action-buttons">
                        <a href="<?php echo admin_url('admin.php?page=sales-dashboard-settings'); ?>" class="button button-primary">
                            <?php _e('Plugin Settings', 'sales-dashboard'); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=sales-dashboard-tokens'); ?>" class="button">
                            <?php _e('Manage JWT Tokens', 'sales-dashboard'); ?>
                        </a>
                        
                        <a href="<?php echo admin_url('admin.php?page=sales-dashboard-docs'); ?>" class="button">
                            <?php _e('API Documentation', 'sales-dashboard'); ?>
                        </a>
                        
                        <button type="button" class="button" id="test-api-connection">
                            <?php _e('Test API Connection', 'sales-dashboard'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="dashboard-info">
                    <h2><?php _e('System Information', 'sales-dashboard'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Plugin Version', 'sales-dashboard'); ?></th>
                            <td><?php echo SALES_DASHBOARD_VERSION; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('WordPress Version', 'sales-dashboard'); ?></th>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('WooCommerce Version', 'sales-dashboard'); ?></th>
                            <td><?php echo class_exists('WooCommerce') ? WC()->version : __('Not installed', 'sales-dashboard'); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('API Endpoint', 'sales-dashboard'); ?></th>
                            <td><code><?php echo rest_url('sales-dashboard/v1/'); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php _e('JWT Secret Status', 'sales-dashboard'); ?></th>
                            <td>
                                <?php if (get_option('sales_dashboard_jwt_secret')): ?>
                                    <span style="color: green;">✓ <?php _e('Configured', 'sales-dashboard'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php _e('Not configured', 'sales-dashboard'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $jwt_secret = get_option('sales_dashboard_jwt_secret', '');
        $cors_origins = get_option('sales_dashboard_cors_origins', "https://sales.academy.com\nhttp://localhost:5173");
        $token_expiry = get_option('sales_dashboard_token_expiry', 7);
        $rate_limit = get_option('sales_dashboard_rate_limit', 100);
        ?>
        <div class="wrap">
            <h1><?php _e('Sales Dashboard Settings', 'sales-dashboard'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('sales_dashboard_settings', 'settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="jwt_secret"><?php _e('JWT Secret Key', 'sales-dashboard'); ?></label>
                        </th>
                        <td>
                            <textarea name="jwt_secret" id="jwt_secret" rows="3" cols="60" class="large-text"><?php echo esc_textarea($jwt_secret); ?></textarea>
                            <p class="description">
                                <?php _e('Secret key used to sign JWT tokens. Keep this secure!', 'sales-dashboard'); ?>
                                <button type="button" class="button button-secondary" id="generate-jwt-secret">
                                    <?php _e('Generate New Secret', 'sales-dashboard'); ?>
                                </button>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cors_origins"><?php _e('CORS Origins', 'sales-dashboard'); ?></label>
                        </th>
                        <td>
                            <textarea name="cors_origins" id="cors_origins" rows="5" cols="60" class="large-text"><?php echo esc_textarea($cors_origins); ?></textarea>
                            <p class="description">
                                <?php _e('Allowed origins for CORS requests, one per line. Include your frontend domain.', 'sales-dashboard'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="token_expiry"><?php _e('Token Expiry (Days)', 'sales-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="token_expiry" id="token_expiry" value="<?php echo esc_attr($token_expiry); ?>" min="1" max="30" class="small-text">
                            <p class="description">
                                <?php _e('Number of days before JWT tokens expire.', 'sales-dashboard'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="rate_limit"><?php _e('Rate Limit (Requests/Hour)', 'sales-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="rate_limit" id="rate_limit" value="<?php echo esc_attr($rate_limit); ?>" min="10" max="1000" class="small-text">
                            <p class="description">
                                <?php _e('Maximum number of API requests per hour per user.', 'sales-dashboard'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * JWT Tokens management page
     */
    public function tokens_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('JWT Tokens Management', 'sales-dashboard'); ?></h1>
            
            <div class="sales-dashboard-tokens">
                <div class="token-generator">
                    <h2><?php _e('Generate Test Token', 'sales-dashboard'); ?></h2>
                    <p><?php _e('Generate a JWT token for testing purposes.', 'sales-dashboard'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="test-username"><?php _e('Username', 'sales-dashboard'); ?></label></th>
                            <td><input type="text" id="test-username" class="regular-text" placeholder="admin"></td>
                        </tr>
                        <tr>
                            <th><label for="test-password"><?php _e('Password', 'sales-dashboard'); ?></label></th>
                            <td><input type="password" id="test-password" class="regular-text"></td>
                        </tr>
                    </table>
                    
                    <button type="button" class="button button-primary" id="generate-test-token">
                        <?php _e('Generate Token', 'sales-dashboard'); ?>
                    </button>
                    
                    <div id="test-token-result" style="margin-top: 20px;"></div>
                </div>
                
                <div class="token-validator">
                    <h2><?php _e('Validate Token', 'sales-dashboard'); ?></h2>
                    <p><?php _e('Validate an existing JWT token.', 'sales-dashboard'); ?></p>
                    
                    <textarea id="validate-token" rows="5" cols="80" class="large-text" placeholder="<?php _e('Paste JWT token here...', 'sales-dashboard'); ?>"></textarea>
                    
                    <p>
                        <button type="button" class="button" id="validate-jwt-token">
                            <?php _e('Validate Token', 'sales-dashboard'); ?>
                        </button>
                    </p>
                    
                    <div id="token-validation-result"></div>
                </div>
                
                <div class="api-test">
                    <h2><?php _e('Test API Endpoint', 'sales-dashboard'); ?></h2>
                    <p><?php _e('Test API endpoints with authentication.', 'sales-dashboard'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="api-endpoint"><?php _e('Endpoint', 'sales-dashboard'); ?></label></th>
                            <td>
                                <select id="api-endpoint" class="regular-text">
                                    <option value="analytics/dashboard"><?php _e('Dashboard Analytics', 'sales-dashboard'); ?></option>
                                    <option value="account-managers"><?php _e('Account Managers', 'sales-dashboard'); ?></option>
                                    <option value="auth/me"><?php _e('Current User', 'sales-dashboard'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="api-token"><?php _e('JWT Token', 'sales-dashboard'); ?></label></th>
                            <td><input type="password" id="api-token" class="large-text"></td>
                        </tr>
                    </table>
                    
                    <button type="button" class="button" id="test-api-endpoint">
                        <?php _e('Test Endpoint', 'sales-dashboard'); ?>
                    </button>
                    
                    <div id="api-test-result"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * API Documentation page
     */
    public function docs_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('API Documentation', 'sales-dashboard'); ?></h1>
            
            <div class="sales-dashboard-docs">
                <div class="doc-section">
                    <h2><?php _e('Authentication', 'sales-dashboard'); ?></h2>
                    <p><?php _e('All API endpoints require JWT authentication except the login endpoint.', 'sales-dashboard'); ?></p>
                    
                    <h3><?php _e('Login', 'sales-dashboard'); ?></h3>
                    <code>POST /wp-json/sales-dashboard/v1/auth/login</code>
                    <pre>{
  "username": "your_username",
  "password": "your_password"
}</pre>
                    
                    <h3><?php _e('Using JWT Token', 'sales-dashboard'); ?></h3>
                    <pre>Authorization: Bearer YOUR_JWT_TOKEN</pre>
                </div>
                
                <div class="doc-section">
                    <h2><?php _e('Analytics Endpoints', 'sales-dashboard'); ?></h2>
                    
                    <h3>GET /analytics/dashboard</h3>
                    <p><?php _e('Get dashboard analytics data', 'sales-dashboard'); ?></p>
                    <strong><?php _e('Parameters:', 'sales-dashboard'); ?></strong>
                    <ul>
                        <li><code>period</code> - week, month, quarter, year</li>
                        <li><code>manager_id</code> - Filter by account manager</li>
                    </ul>
                    
                    <h3>GET /analytics/monthly-comparison</h3>
                    <p><?php _e('Get monthly comparison data', 'sales-dashboard'); ?></p>
                    
                    <h3>GET /reports/sales/{period}</h3>
                    <p><?php _e('Get detailed sales report', 'sales-dashboard'); ?></p>
                </div>
                
                <div class="doc-section">
                    <h2><?php _e('Account Manager Endpoints', 'sales-dashboard'); ?></h2>
                    
                    <h3>GET /account-managers</h3>
                    <p><?php _e('Get list of all account managers', 'sales-dashboard'); ?></p>
                    
                    <h3>GET /account-managers/{id}/customers</h3>
                    <p><?php _e('Get customers assigned to an account manager', 'sales-dashboard'); ?></p>
                    
                    <h3>POST /account-managers/assign</h3>
                    <p><?php _e('Assign a customer to an account manager', 'sales-dashboard'); ?></p>
                    <pre>{
  "customer_id": 123,
  "manager_id": "1465"
}</pre>
                </div>
                
                <div class="doc-section">
                    <h2><?php _e('Export Endpoints', 'sales-dashboard'); ?></h2>
                    
                    <h3>GET /export/orders</h3>
                    <p><?php _e('Export orders data', 'sales-dashboard'); ?></p>
                    
                    <h3>GET /export/customers</h3>
                    <p><?php _e('Export customers data', 'sales-dashboard'); ?></p>
                    
                    <h3>GET /export/reports/{type}</h3>
                    <p><?php _e('Export reports (sales, products, customers, managers)', 'sales-dashboard'); ?></p>
                    
                    <strong><?php _e('Common Parameters:', 'sales-dashboard'); ?></strong>
                    <ul>
                        <li><code>format</code> - csv, json, xml</li>
                        <li><code>manager_id</code> - Filter by account manager</li>
                        <li><code>date_from</code> - Start date (Y-m-d)</li>
                        <li><code>date_to</code> - End date (Y-m-d)</li>
                    </ul>
                </div>
                
                <div class="doc-section">
                    <h2><?php _e('Base URL', 'sales-dashboard'); ?></h2>
                    <code><?php echo rest_url('sales-dashboard/v1/'); ?></code>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['settings_nonce'], 'sales_dashboard_settings')) {
            wp_die(__('Security check failed', 'sales-dashboard'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page', 'sales-dashboard'));
        }
        
        // Sanitize and save settings
        $jwt_secret = sanitize_textarea_field($_POST['jwt_secret']);
        $cors_origins = sanitize_textarea_field($_POST['cors_origins']);
        $token_expiry = intval($_POST['token_expiry']);
        $rate_limit = intval($_POST['rate_limit']);
        
        update_option('sales_dashboard_jwt_secret', $jwt_secret);
        update_option('sales_dashboard_cors_origins', $cors_origins);
        update_option('sales_dashboard_token_expiry', max(1, min(30, $token_expiry)));
        update_option('sales_dashboard_rate_limit', max(10, min(1000, $rate_limit)));
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'sales-dashboard') . '</p></div>';
    }
}
