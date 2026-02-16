<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Sales Dashboard', 'sales-dashboard'); ?></h1>
    
    <div class="sales-dashboard-overview">
        <div class="postbox-container" style="width: 100%;">
            <div class="meta-box-sortables">
                
                <!-- Stats Overview -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('خلاصه آمار', 'sales-dashboard'); ?></h2>
                    <div class="inside">
                        <div class="sales-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                            <div class="stat-box" style="text-align: center; padding: 20px; background: #f0f6ff; border-radius: 8px;">
                                <div style="font-size: 32px; font-weight: bold; color: #1e40af;">
                                    <?php echo number_format($stats['total_orders']); ?>
                                </div>
                                <div style="color: #6b7280; margin-top: 5px;">
                                    <?php _e('کل سفارشات', 'sales-dashboard'); ?>
                                </div>
                            </div>
                            
                            <div class="stat-box" style="text-align: center; padding: 20px; background: #f0fdf4; border-radius: 8px;">
                                <div style="font-size: 32px; font-weight: bold; color: #16a34a;">
                                    <?php echo number_format($stats['total_customers']); ?>
                                </div>
                                <div style="color: #6b7280; margin-top: 5px;">
                                    <?php _e('کل مشتریان', 'sales-dashboard'); ?>
                                </div>
                            </div>
                            
                            <div class="stat-box" style="text-align: center; padding: 20px; background: #fdf2f8; border-radius: 8px;">
                                <div style="font-size: 32px; font-weight: bold; color: #e11d48;">
                                    <?php echo number_format($stats['account_managers']); ?>
                                </div>
                                <div style="color: #6b7280; margin-top: 5px;">
                                    <?php _e('مدیران حساب', 'sales-dashboard'); ?>
                                </div>
                            </div>
                            
                            <div class="stat-box" style="text-align: center; padding: 20px; background: #fefce8; border-radius: 8px;">
                                <div style="font-size: 32px; font-weight: bold; color: #ca8a04;">
                                    <?php echo number_format($stats['assigned_customers']); ?>
                                </div>
                                <div style="color: #6b7280; margin-top: 5px;">
                                    <?php _e('مشتریان تخصیص یافته', 'sales-dashboard'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('دسترسی سریع', 'sales-dashboard'); ?></h2>
                    <div class="inside">
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                            <a href="<?php echo admin_url('admin.php?page=sales-dashboard-settings'); ?>" class="button button-primary button-large" style="text-decoration: none; text-align: center; padding: 20px;">
                                <span class="dashicons dashicons-admin-settings" style="margin-top: 4px;"></span><br>
                                <?php _e('تنظیمات', 'sales-dashboard'); ?>
                            </a>
                            
                            <a href="<?php echo admin_url('admin.php?page=sales-dashboard-managers'); ?>" class="button button-primary button-large" style="text-decoration: none; text-align: center; padding: 20px;">
                                <span class="dashicons dashicons-businessperson" style="margin-top: 4px;"></span><br>
                                <?php _e('مدیران حساب', 'sales-dashboard'); ?>
                            </a>
                            
                            <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="button button-primary button-large" style="text-decoration: none; text-align: center; padding: 20px;">
                                <span class="dashicons dashicons-cart" style="margin-top: 4px;"></span><br>
                                <?php _e('مدیریت سفارشات', 'sales-dashboard'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- API Information -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('اطلاعات API', 'sales-dashboard'); ?></h2>
                    <div class="inside">
                        <table class="wp-list-table widefat fixed striped">
                            <tbody>
                                <tr>
                                    <td><strong><?php _e('Base URL', 'sales-dashboard'); ?></strong></td>
                                    <td><code><?php echo esc_url(rest_url('sales-dashboard/v1/')); ?></code></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('Authentication', 'sales-dashboard'); ?></strong></td>
                                    <td>JWT Token</td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('نسخه پلاگین', 'sales-dashboard'); ?></strong></td>
                                    <td><?php echo SALES_DASHBOARD_VERSION; ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('وضعیت WooCommerce', 'sales-dashboard'); ?></strong></td>
                                    <td>
                                        <?php if (class_exists('WooCommerce')): ?>
                                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span> فعال (نسخه <?php echo WC()->version; ?>)
                                        <?php else: ?>
                                            <span class="dashicons dashicons-dismiss" style="color: red;"></span> غیرفعال
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('فعالیت‌های اخیر', 'sales-dashboard'); ?></h2>
                    <div class="inside">
                        <?php
                        // Get recent orders
                        $recent_orders = wc_get_orders(array(
                            'limit' => 5,
                            'orderby' => 'date',
                            'order' => 'DESC'
                        ));
                        
                        if (!empty($recent_orders)):
                        ?>
                        <ul style="margin: 0;">
                            <?php foreach ($recent_orders as $order): ?>
                            <li style="padding: 10px 0; border-bottom: 1px solid #eee;">
                                <strong>#<?php echo $order->get_order_number(); ?></strong>
                                - <?php echo wc_price($order->get_total()); ?>
                                - <?php echo $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); ?>
                                <span style="float: left; color: #6b7280;">
                                    <?php echo $order->get_date_created()->date('Y-m-d H:i'); ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p><?php _e('هیچ سفارشی یافت نشد.', 'sales-dashboard'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>
