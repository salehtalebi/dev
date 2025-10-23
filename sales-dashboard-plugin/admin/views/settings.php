<?php
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['settings_nonce'], 'sales_dashboard_settings')) {
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

$jwt_secret = get_option('sales_dashboard_jwt_secret', '');
$cors_origins = get_option('sales_dashboard_cors_origins', "https://sales.academy.com\nhttp://localhost:5173\nhttp://localhost:3000");
$token_expiry = get_option('sales_dashboard_token_expiry', 7);
$rate_limit = get_option('sales_dashboard_rate_limit', 100);
?>

<div class="wrap">
    <h1><?php _e('Sales Dashboard - Settings', 'sales-dashboard'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('sales_dashboard_settings', 'settings_nonce'); ?>
        
        <div class="postbox">
            <h2 class="hndle"><?php _e('JWT Settings', 'sales-dashboard'); ?></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="jwt_secret"><?php _e('JWT Secret Key', 'sales-dashboard'); ?></label>
                        </th>
                        <td>
                            <textarea name="jwt_secret" id="jwt_secret" rows="3" cols="60" class="large-text"><?php echo esc_textarea($jwt_secret); ?></textarea>
                            <p class="description">
                                <?php _e('Secret key for signing JWT tokens. Keep this key secure!', 'sales-dashboard'); ?>
                                <br>
                                <button type="button" class="button button-secondary" onclick="generateJWTSecret()">
                                    <?php _e('Generate new key', 'sales-dashboard'); ?>
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
                                <?php _e('Allowed CORS origins, one per line. Add your frontend domain.', 'sales-dashboard'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="token_expiry"><?php _e('Token validity (days)', 'sales-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="token_expiry" id="token_expiry" value="<?php echo esc_attr($token_expiry); ?>" min="1" max="30" class="small-text">
                            <p class="description">
                                <?php _e('Number of days the JWT tokens remain valid.', 'sales-dashboard'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="rate_limit"><?php _e('Rate limit (requests/hour)', 'sales-dashboard'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="rate_limit" id="rate_limit" value="<?php echo esc_attr($rate_limit); ?>" min="10" max="1000" class="small-text">
                            <p class="description">
                                <?php _e('Maximum number of API requests per hour per user.', 'sales-dashboard'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle"><?php _e('API Tests', 'sales-dashboard'); ?></h2>
            <div class="inside">
                <h3><?php _e('Authentication Test', 'sales-dashboard'); ?></h3>
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
                
                <button type="button" class="button button-primary" onclick="testLogin()">
                    <?php _e('Test login and generate token', 'sales-dashboard'); ?>
                </button>
                
                <div id="login-test-result" style="margin-top: 20px;"></div>
                
                <h3><?php _e('Test API Endpoint', 'sales-dashboard'); ?></h3>
                <button type="button" class="button" onclick="testAPIConnection()">
                    <?php _e('Test API connectivity', 'sales-dashboard'); ?>
                </button>
                <div id="api-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

<script>
function generateJWTSecret() {
    if (confirm('<?php _e('Generate a new JWT secret? This will invalidate all existing tokens.', 'sales-dashboard'); ?>')) {
        // Generate a strong random secret (64 characters)
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        let secret = '';
        for (let i = 0; i < 64; i++) {
            secret += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('jwt_secret').value = secret;
    }
}

function testLogin() {
    const username = document.getElementById('test-username').value;
    const password = document.getElementById('test-password').value;
    const resultDiv = document.getElementById('login-test-result');
    
    if (!username || !password) {
        resultDiv.innerHTML = '<div class="notice notice-error"><p>Please enter username and password.</p></div>';
        return;
    }
    
    resultDiv.innerHTML = '<span class="spinner is-active" style="float: none;"></span> Testing...';
    
    fetch('<?php echo esc_url(rest_url('sales-dashboard/v1/auth/login')); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            username: username,
            password: password
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.token) {
            resultDiv.innerHTML = `
                <div class="notice notice-success">
                    <p><strong>✅ Login successful!</strong></p>
                    <p><strong>User:</strong> ${data.user.display_name} (${data.user.email})</p>
                    <p><strong>Roles:</strong> ${data.user.roles.join(', ')}</p>
                    <p><strong>Token:</strong></p>
                    <textarea rows="3" cols="80" readonly>${data.token}</textarea>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="notice notice-error">
                    <p><strong>❌ Login error:</strong> ${data.message || 'Incorrect username or password'}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = `
            <div class="notice notice-error">
                <p><strong>❌ Connection error:</strong> ${error.message}</p>
            </div>
        `;
    });
}

function testAPIConnection() {
    const resultDiv = document.getElementById('api-test-result');
    resultDiv.innerHTML = '<span class="spinner is-active" style="float: none;"></span> Testing...';
    
    fetch('<?php echo esc_url(rest_url('sales-dashboard/v1/auth/login')); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            username: 'test',
            password: 'test'
        })
    })
    .then(response => {
        if (response.status === 401) {
            resultDiv.innerHTML = '<div class="notice notice-success"><p>✅ API is reachable (authentication error expected)</p></div>';
        } else if (response.status === 200) {
            resultDiv.innerHTML = '<div class="notice notice-success"><p>✅ API is fully operational</p></div>';
        } else {
            resultDiv.innerHTML = '<div class="notice notice-warning"><p>⚠️ API returned an unexpected response</p></div>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="notice notice-error"><p>❌ Error connecting to API</p></div>';
    });
}
</script>

<style>
.notice {
    border-left: 4px solid #00a0d2;
    padding: 12px;
    margin: 15px 0;
    background: #fff;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.notice-success { border-left-color: #46b450; }
.notice-error { border-left-color: #dc3232; }
.notice-warning { border-left-color: #ffb900; }
</style>
