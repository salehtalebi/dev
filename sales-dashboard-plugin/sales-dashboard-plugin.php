<?php
/**
 * Plugin Name: Sales Dashboard API
 * Plugin URI: https://academy.com
 * Description: Custom API endpoints for Sales Dashboard with built-in JWT Authentication
 * Version: 1.0.0
 * Author: Academy Team
 * Text Domain: sales-dashboard
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SALES_DASHBOARD_VERSION', '1.0.0');
define('SALES_DASHBOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SALES_DASHBOARD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SALES_DASHBOARD_PLUGIN_FILE', __FILE__);

/**
 * Main Sales Dashboard Plugin Class
 */
class SalesDashboardPlugin {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load Memory Manager first
        require_once SALES_DASHBOARD_PLUGIN_DIR . 'includes/class-memory-manager.php';
        
        // Load other classes
        require_once SALES_DASHBOARD_PLUGIN_DIR . 'includes/class-jwt-auth.php';
        require_once SALES_DASHBOARD_PLUGIN_DIR . 'includes/class-api-routes.php';
        require_once SALES_DASHBOARD_PLUGIN_DIR . 'includes/class-analytics.php';
        require_once SALES_DASHBOARD_PLUGIN_DIR . 'includes/class-export.php';
        require_once SALES_DASHBOARD_PLUGIN_DIR . 'includes/class-account-managers.php';
        
        // Load admin class if in admin area
        if (is_admin()) {
            require_once SALES_DASHBOARD_PLUGIN_DIR . 'admin/class-admin.php';
        }
    }    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Initialize classes
        new Sales_Dashboard_JWT_Auth();
        new Sales_Dashboard_API_Routes();
        new Sales_Dashboard_Analytics();
        new Sales_Dashboard_Account_Managers();
        new Sales_Dashboard_Export();
        
        if (is_admin()) {
            new Sales_Dashboard_Admin();
        }
        
        // Add CORS headers
        add_action('rest_api_init', array($this, 'add_cors_headers'));
        add_action('init', array($this, 'handle_preflight'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_tables();
        flush_rewrite_rules();
        
        // Add default options
        add_option('sales_dashboard_jwt_secret', $this->generate_jwt_secret());
        add_option('sales_dashboard_version', SALES_DASHBOARD_VERSION);
        add_option('sales_dashboard_cors_origins', array(
            'https://sales.academy.com',
            'http://localhost:5173',
            'http://localhost:3000',
            'http://localhost:8080',
            'http://localhost:2145',
            'http://localhost:2145/nuvior'
        ));
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Create custom database tables
     */
    private function create_tables() {
        // در فاز اول نیازی به جدول سفارشی نیست
        // چون Account Manager ها در user meta ذخیره می‌شوند
        // این فانکشن برای سازگاری باقی می‌ماند
        
        // Ensure user meta table exists (it should by default)
        global $wpdb;
        
        // Create index on usermeta for better performance
        $wpdb->query("
            CREATE INDEX IF NOT EXISTS idx_account_manager_id 
            ON {$wpdb->usermeta} (meta_key, meta_value) 
            WHERE meta_key = '_account_manager_id'
        ");
    }
    
    /**
     * Generate JWT secret key
     */
    private function generate_jwt_secret() {
        return wp_generate_password(64, true, true);
    }
    
    /**
     * Add CORS headers
     */
    public function add_cors_headers() {
        $allowed_origins = get_option('sales_dashboard_cors_origins', array(
            'https://sales.academy.com',
            'http://localhost:5173',
            'http://localhost:3000',
            'http://localhost:8080',
            'http://localhost:2145',
            'http://localhost:2145/nuvior',
        ));
        
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        
        if (in_array($origin, $allowed_origins) || $this->is_dev_environment()) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-WP-Nonce');
            header('Access-Control-Allow-Credentials: true');
            // Expose pagination headers so frontend can read totals
            header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages');
            header('Access-Control-Max-Age: 86400');
        }
    }
    
    /**
     * Handle preflight requests
     */
    public function handle_preflight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->add_cors_headers();
            status_header(200);
            exit();
        }
    }
    
    /**
     * Check if development environment
     */
    private function is_dev_environment() {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Sales Dashboard requires WooCommerce to be installed and active.', 'sales-dashboard'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('sales-dashboard', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

// Initialize the plugin
new SalesDashboardPlugin();
