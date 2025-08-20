<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Sales Dashboard - تنظیمات', 'sales-dashboard'); ?></h1>
    
    <?php settings_errors(); ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('sales_dashboard_settings');
        do_settings_sections('sales_dashboard_settings');
        ?>
        
        <div class="postbox">
            <h2 class="hndle"><?php _e('تنظیمات API', 'sales-dashboard'); ?></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Base URL', 'sales-dashboard'); ?></th>
                        <td>
                            <code><?php echo esc_url(rest_url('sales-dashboard/v1/')); ?></code>
                            <p class="description"><?php _e('آدرس پایه API برای استفاده در فرانت‌اند', 'sales-dashboard'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('وضعیت JWT', 'sales-dashboard'); ?></th>
                        <td>
                            <?php
                            $jwt_secret = get_option('sales_dashboard_jwt_secret');
                            if ($jwt_secret) {
                                echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ' . __('فعال', 'sales-dashboard');
                            } else {
                                echo '<span class="dashicons dashicons-dismiss" style="color: red;"></span> ' . __('غیرفعال', 'sales-dashboard');
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="postbox">
            <h2 class="hndle"><?php _e('تست اتصال', 'sales-dashboard'); ?></h2>
            <div class="inside">
                <p><?php _e('برای تست اتصال به API، از کرل زیر استفاده کنید:', 'sales-dashboard'); ?></p>
                <code style="display: block; background: #f0f0f1; padding: 10px; margin: 10px 0;">
curl -X POST <?php echo esc_url(rest_url('sales-dashboard/v1/auth/login')); ?> \<br>
  -H "Content-Type: application/json" \<br>
  -d '{"username": "admin@academy.com", "password": "your_password"}'
                </code>
                
                <button type="button" class="button" onclick="testAPIConnection()"><?php _e('تست اتصال', 'sales-dashboard'); ?></button>
                <div id="api-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

<script>
function generateJWTSecret() {
    if (confirm('<?php _e('آیا می‌خواهید کلید JWT جدید تولید کنید؟ این کار تمام توکن‌های فعلی را نامعتبر می‌کند.', 'sales-dashboard'); ?>')) {
        // Generate random secret
        const secret = btoa(Math.random().toString(36).substring(2) + Math.random().toString(36).substring(2));
        document.querySelector('input[name="sales_dashboard_jwt_secret"]').value = secret;
    }
}

function testAPIConnection() {
    const resultDiv = document.getElementById('api-test-result');
    resultDiv.innerHTML = '<span class="spinner is-active" style="float: none;"></span> <?php _e('در حال تست...', 'sales-dashboard'); ?>';
    
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
            resultDiv.innerHTML = '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php _e('API در دسترس است (خطای احراز هویت انتظار می‌رود)', 'sales-dashboard'); ?>';
        } else {
            resultDiv.innerHTML = '<span class="dashicons dashicons-dismiss" style="color: red;"></span> <?php _e('خطای غیرمنتظره', 'sales-dashboard'); ?>';
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<span class="dashicons dashicons-dismiss" style="color: red;"></span> <?php _e('خطا در اتصال به API', 'sales-dashboard'); ?>';
    });
}
</script>
