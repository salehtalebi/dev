# راهنمای نصب و تنظیم WordPress برای Sales Dashboard

## مرحله 1: نصب پلاگین‌های ضروری

### 1. JWT Authentication for WP REST API
```bash
# دانلود و نصب پلاگین JWT
wget https://github.com/Tmeister/wp-api-jwt-auth/archive/develop.zip
# یا از WordPress Admin Panel نصب کنید
```

### 2. WooCommerce (اگر نصب نیست)
```bash
# از WordPress Admin: Plugins > Add New > جستجو "WooCommerce"
```

## مرحله 2: تنظیمات wp-config.php

در فایل `wp-config.php` سایت اصلی خود موارد زیر را اضافه کنید:

```php
// JWT Authentication Secret Key
define('JWT_AUTH_SECRET_KEY', 'your-top-secret-key-here-make-it-very-long-and-random');

// CORS Support
define('JWT_AUTH_CORS_ENABLE', true);

// Allow custom tables
define('WP_ALLOW_REPAIR', true);
```

## مرحله 3: نصب پلاگین‌های سفارشی

### 1. پلاگین Sales Dashboard API
```bash
# کپی فایل sales-dashboard-plugin.php به:
/wp-content/plugins/sales-dashboard-api/sales-dashboard-plugin.php

# یا zip کرده و از WordPress Admin نصب کنید
```

### 2. تنظیمات JWT
```bash
# کد موجود در jwt-auth-config.php را به functions.php theme اضافه کنید
# یا به عنوان پلاگین جداگانه نصب کنید
```

## مرحله 4: فعال‌سازی پلاگین‌ها

1. وارد WordPress Admin Panel شوید
2. به Plugins بروید
3. پلاگین‌های زیر را فعال کنید:
   - JWT Authentication for WP REST API
   - Sales Dashboard API
   - WooCommerce (اگر قبلاً فعال نیست)

## مرحله 5: تنظیمات WooCommerce API

### ایجاد API Keys:
1. WooCommerce > Settings > Advanced > REST API
2. Add Key
3. Description: "Sales Dashboard"
4. User: یک کاربر Administrator
5. Permissions: Read/Write
6. Generate API Key

### تنظیم Webhooks (اختیاری):
1. WooCommerce > Settings > Advanced > Webhooks
2. برای اطلاع از تغییرات real-time

## مرحله 6: تست API ها

### تست JWT Authentication:
```bash
curl -X POST https://academy.com/wp-json/jwt-auth/v1/token \
  -H "Content-Type: application/json" \
  -d '{
    "username": "your_username",
    "password": "your_password"
  }'
```

### تست WooCommerce API:
```bash
curl -X GET https://academy.com/wp-json/wc/v3/orders \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### تست Custom API:
```bash
curl -X GET https://academy.com/wp-json/sales-dashboard/v1/analytics/dashboard \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## مرحله 7: تنظیمات کاربران

### 1. تعیین Account Manager:
1. Users > All Users
2. Edit کاربر مورد نظر
3. در بخش "Sales Dashboard Settings" گزینه "Account Manager" را فعال کنید

### 2. تخصیص مشتریان به Account Manager:
```php
// از Admin Panel یا API:
POST /wp-json/sales-dashboard/v1/account-managers/assign
{
  "customer_id": 123,
  "manager_id": 456
}
```

## مرحله 8: تنظیمات امنیتی

### 1. محدود کردن دسترسی API:
```php
// در .htaccess
<Files "wp-config.php">
Order Allow,Deny
Deny from all
</Files>

# محدود کردن API به ساب دامنه
RewriteCond %{HTTP_ORIGIN} !^https://sales\.academy\.com$
RewriteRule ^wp-json/ - [F,L]
```

### 2. SSL Certificate:
- اطمینان حاصل کنید که هم دامنه اصلی و هم ساب دامنه SSL دارند

## مرحله 9: بک‌آپ و مانیتورینگ

### 1. بک‌آپ دیتابیس:
```bash
# بک‌آپ جداول سفارشی
mysqldump -u username -p database_name wp_customer_account_managers > backup.sql
```

### 2. مانیتورینگ API:
- استفاده از WordPress logs
- نصب پلاگین Query Monitor برای debug

## خطاهای متداول و راه‌حل

### 1. CORS Error:
```php
// اضافه کردن هدرهای CORS بیشتر
header('Access-Control-Allow-Origin: https://sales.academy.com');
header('Access-Control-Allow-Credentials: true');
```

### 2. JWT Token Invalid:
- بررسی JWT_AUTH_SECRET_KEY در wp-config.php
- اطمینان از فعال بودن پلاگین JWT

### 3. Permission Denied:
- بررسی نقش کاربر (Administrator یا Shop Manager)
- تست با کاربر Administrator

### 4. API Endpoint Not Found:
```bash
# Flush rewrite rules
wp rewrite flush --hard
```

## تنظیمات Environment Variables

در فایل `.env` پروژه Vue.js:

```env
VITE_API_BASE_URL=https://academy.com
VITE_WP_API_ENDPOINT=/wp-json/wp/v2
VITE_WC_API_ENDPOINT=/wp-json/wc/v3
VITE_CUSTOM_API_ENDPOINT=/wp-json/sales-dashboard/v1
VITE_JWT_ENDPOINT=/wp-json/jwt-auth/v1
```

## آمادگی برای Production

### 1. بررسی نهایی:
- [ ] تمام پلاگین‌ها فعال هستند
- [ ] JWT Authentication کار می‌کند
- [ ] WooCommerce API در دسترس است
- [ ] Custom API endpoints پاسخ می‌دهند
- [ ] CORS به درستی تنظیم شده
- [ ] SSL فعال است
- [ ] کاربران Account Manager تعیین شده‌اند

### 2. Performance:
```php
// کش کردن API responses
wp_cache_set('dashboard_analytics', $data, 'sales_dashboard', 300); // 5 minutes
```

### 3. Security:
- Rate limiting برای API endpoints
- Validation و sanitization ورودی‌ها
- استفاده از nonce برای forms

این راهنما شامل تمام مراحل لازم برای راه‌اندازی کامل backend است.
