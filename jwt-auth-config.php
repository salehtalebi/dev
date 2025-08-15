<?php
/**
 * JWT Authentication Configuration
 * Add this to your theme's functions.php or as a separate plugin
 */

// JWT Authentication settings
add_action('init', 'setup_jwt_auth');

function setup_jwt_auth() {
    // Add CORS headers for sales subdomain
    add_action('rest_api_init', 'add_cors_headers');
    
    // Customize JWT response
    add_filter('jwt_auth_token_before_dispatch', 'custom_jwt_response', 10, 2);
    
    // Validate user role for JWT
    add_filter('jwt_auth_token_before_dispatch', 'validate_user_role_for_jwt', 10, 2);
}

function add_cors_headers() {
    $allowed_origins = array(
        'https://sales.academy.com',
        'http://localhost:5173', // For development
        'http://localhost:3000'  // Alternative dev port
    );
    
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    
    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        status_header(200);
        exit();
    }
}

function custom_jwt_response($data, $user) {
    // Add user role and additional info to JWT response
    $user_data = get_userdata($user->ID);
    
    $data['user'] = array(
        'id' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'first_name' => $user_data->first_name,
        'last_name' => $user_data->last_name,
        'display_name' => $user_data->display_name,
        'roles' => $user_data->roles,
        'is_account_manager' => get_user_meta($user->ID, 'is_account_manager', true) === '1'
    );
    
    return $data;
}

function validate_user_role_for_jwt($data, $user) {
    // Only allow administrators and shop managers to get JWT token
    $allowed_roles = array('administrator', 'shop_manager');
    $user_roles = get_userdata($user->ID)->roles;
    
    $has_permission = false;
    foreach ($allowed_roles as $role) {
        if (in_array($role, $user_roles)) {
            $has_permission = true;
            break;
        }
    }
    
    if (!$has_permission) {
        return new WP_Error(
            'jwt_auth_invalid_role',
            'شما مجوز دسترسی به این پنل را ندارید',
            array('status' => 403)
        );
    }
    
    return $data;
}

// Add custom user meta for account managers
add_action('show_user_profile', 'add_account_manager_field_to_profile');
add_action('edit_user_profile', 'add_account_manager_field_to_profile');
add_action('personal_options_update', 'save_account_manager_field');
add_action('edit_user_profile_update', 'save_account_manager_field');

function add_account_manager_field_to_profile($user) {
    // Only show for administrators and shop managers
    if (!in_array('administrator', $user->roles) && !in_array('shop_manager', $user->roles)) {
        return;
    }
    
    $is_account_manager = get_user_meta($user->ID, 'is_account_manager', true);
    ?>
    <h3>Sales Dashboard Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="is_account_manager">Account Manager</label></th>
            <td>
                <input type="checkbox" name="is_account_manager" id="is_account_manager" value="1" <?php checked($is_account_manager, '1'); ?> />
                <label for="is_account_manager">This user is an Account Manager</label>
            </td>
        </tr>
    </table>
    <?php
}

function save_account_manager_field($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    
    $is_account_manager = isset($_POST['is_account_manager']) ? '1' : '0';
    update_user_meta($user_id, 'is_account_manager', $is_account_manager);
}

?>
