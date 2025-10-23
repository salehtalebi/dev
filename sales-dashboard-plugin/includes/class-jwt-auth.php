<?php

/**
 * JWT Authentication Class
 */
class Sales_Dashboard_JWT_Auth {
    
    private $jwt_secret;
    
    public function __construct() {
        $this->jwt_secret = get_option('sales_dashboard_jwt_secret');
        if (empty($this->jwt_secret) && defined('JWT_AUTH_SECRET_KEY')) {
            // Fallback to global JWT secret if plugin option is not set
            $this->jwt_secret = JWT_AUTH_SECRET_KEY;
        }
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_auth_routes'));
        add_filter('determine_current_user', array($this, 'determine_current_user'), 10);
    }
    
    /**
     * Register authentication routes
     */
    public function register_auth_routes() {
        register_rest_route('sales-dashboard/v1', '/auth/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'login'),
            'permission_callback' => '__return_true',
            'args' => array(
                'username' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Username or email'
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'User password'
                )
            )
        ));
        
        register_rest_route('sales-dashboard/v1', '/auth/validate', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_token'),
            'permission_callback' => array($this, 'check_jwt_auth')
        ));
        
        register_rest_route('sales-dashboard/v1', '/auth/refresh', array(
            'methods' => 'POST',
            'callback' => array($this, 'refresh_token'),
            'permission_callback' => array($this, 'check_jwt_auth')
        ));
        
        register_rest_route('sales-dashboard/v1', '/auth/me', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_current_user'),
            'permission_callback' => array($this, 'check_jwt_auth')
        ));

        // Stateless logout: blacklist current token until it expires
        register_rest_route('sales-dashboard/v1', '/auth/logout', array(
            'methods' => 'POST',
            'callback' => array($this, 'logout'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Login endpoint
     */
    public function login($request) {
        $username = sanitize_text_field($request->get_param('username'));
        $password = $request->get_param('password');
        
        // Authenticate user
        $user = wp_authenticate($username, $password);
        
        if (is_wp_error($user)) {
            return new WP_Error(
                'login_failed',
                __('Incorrect username or password.', 'sales-dashboard'),
                array('status' => 401)
            );
        }
        
        // Check user permissions
        if (!$this->user_can_access_dashboard($user)) {
            return new WP_Error(
                'insufficient_permissions',
                __('You do not have permission to access the Sales Dashboard.', 'sales-dashboard'),
                array('status' => 403)
            );
        }
        
        // Generate JWT token
        $token = $this->generate_token($user);
        
        if (!$token) {
            return new WP_Error(
                'token_generation_failed',
                __('Error generating authentication token.', 'sales-dashboard'),
                array('status' => 500)
            );
        }
        
        return array(
            'token' => $token,
            'user_email' => $user->user_email,
            'user_nicename' => $user->user_nicename,
            'user_display_name' => $user->display_name,
            'user' => $this->prepare_user_data($user),
            'expires' => time() + (7 * 24 * 60 * 60) // 7 days
        );
    }
    
    /**
     * Validate token endpoint
     */
    public function validate_token($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_Error(
                'invalid_token',
                __('Token is invalid or expired.', 'sales-dashboard'),
                array('status' => 401)
            );
        }
        
        return array(
            'code' => 'jwt_auth_valid_token',
            'data' => array(
                'status' => 200,
                'user_id' => $user_id,
                'message' => __('Token is valid.', 'sales-dashboard')
            )
        );
    }
    
    /**
     * Refresh token endpoint
     */
    public function refresh_token($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_Error(
                'invalid_token',
                __('Token is invalid or expired.', 'sales-dashboard'),
                array('status' => 401)
            );
        }
        
        $user = get_user_by('id', $user_id);
        $new_token = $this->generate_token($user);
        
        return array(
            'token' => $new_token,
            'expires' => time() + (7 * 24 * 60 * 60)
        );
    }
    
    /**
     * Get current user endpoint
     */
    public function get_current_user($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_Error(
                'not_authenticated',
                __('User is not authenticated.', 'sales-dashboard'),
                array('status' => 401)
            );
        }
        
        $user = get_user_by('id', $user_id);
        return $this->prepare_user_data($user);
    }
    
    /**
     * Generate JWT token
     */
    private function generate_token($user) {
    $issued_at = time();
    $ttl = apply_filters('sales_dashboard_jwt_ttl', 7 * 24 * 60 * 60); // default 7 days
    $expiration_time = $issued_at + (int) max(60, $ttl);
        
        $payload = array(
            'iss' => get_site_url(),
            'iat' => $issued_at,
            'exp' => $expiration_time,
            'data' => array(
                'user' => array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email
                )
            )
        );
        
        return $this->encode_jwt($payload);
    }
    
    /**
     * Encode JWT token
     */
    private function encode_jwt($payload) {
        $header = array(
            'typ' => 'JWT',
            'alg' => 'HS256'
        );
        
        $header_encoded = $this->base64url_encode(json_encode($header));
        $payload_encoded = $this->base64url_encode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $header_encoded . '.' . $payload_encoded, $this->jwt_secret, true);
        $signature_encoded = $this->base64url_encode($signature);
        
        return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
    }

    /**
     * Add token to blacklist until its expiration
     */
    private function blacklist_token($token, $exp_ts = null) {
        if (!$token) return false;
        $hash = hash('sha256', $token);
        $ttl = 7 * 24 * 60 * 60; // default 7 days
        if ($exp_ts && $exp_ts > time()) {
            $ttl = max(60, $exp_ts - time());
        }
        set_transient('sdp_jwt_blacklist_' . $hash, 1, $ttl);
        return true;
    }

    /**
     * Check if token is blacklisted
     */
    private function is_token_blacklisted($token) {
        if (!$token) return false;
        $hash = hash('sha256', $token);
        return (bool) get_transient('sdp_jwt_blacklist_' . $hash);
    }
    
    /**
     * Decode JWT token
     */
    private function decode_jwt($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($header_encoded, $payload_encoded, $signature_encoded) = $parts;
        
        // Verify signature
        $signature = $this->base64url_decode($signature_encoded);
        $expected_signature = hash_hmac('sha256', $header_encoded . '.' . $payload_encoded, $this->jwt_secret, true);
        
        if (!hash_equals($signature, $expected_signature)) {
            return false;
        }
        
        $payload = json_decode($this->base64url_decode($payload_encoded), true);
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Base64url encode
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64url decode
     */
    private function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    /**
     * Determine current user from JWT token
     */
    public function determine_current_user($user_id) {
        if ($user_id) {
            return $user_id;
        }
        
        $token = $this->get_auth_header();
        
        if (!$token) {
            return $user_id;
        }

        // Deny if token has been blacklisted via logout
        if ($this->is_token_blacklisted($token)) {
            return $user_id;
        }
        
        $payload = $this->decode_jwt($token);
        
        if (!$payload) {
            return $user_id;
        }
        
        return $payload['data']['user']['id'] ?? $user_id;
    }
    
    /**
     * Get authorization header
     */
    private function get_auth_header() {
        $auth_header = null;
        
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $auth_header = $headers['Authorization'];
            }
        }
        
        if (!$auth_header) {
            return null;
        }
        
        if (strpos($auth_header, 'Bearer ') === 0) {
            return substr($auth_header, 7);
        }
        
        return null;
    }
    
    /**
     * Check JWT authentication
     */
    public function check_jwt_auth($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        $user = get_user_by('id', $user_id);
        return $this->user_can_access_dashboard($user);
    }

    /**
     * Logout endpoint: blacklist current token
     */
    public function logout($request) {
        $token = $this->get_auth_header();
        if (!$token) {
            // Idempotent: treat missing token as logged out
            return array('success' => true);
        }

        $payload = $this->decode_jwt($token);
        $exp = is_array($payload) && isset($payload['exp']) ? intval($payload['exp']) : null;
        $this->blacklist_token($token, $exp);
        return array('success' => true);
    }
    
    /**
     * Check if user can access dashboard
     */
    private function user_can_access_dashboard($user) {
        $allowed_roles = apply_filters('sales_dashboard_allowed_roles', array(
            'administrator',
            'shop_manager'
        ));
        
        return array_intersect($allowed_roles, $user->roles);
    }
    
    /**
     * Prepare user data for response
     */
    private function prepare_user_data($user) {
        $user_data = get_userdata($user->ID);
        
        return array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'first_name' => $user_data->first_name,
            'last_name' => $user_data->last_name,
            'display_name' => $user_data->display_name,
            'roles' => $user_data->roles,
            'is_account_manager' => get_user_meta($user->ID, 'is_account_manager', true) === '1',
            'avatar_url' => get_avatar_url($user->ID)
        );
    }
}
