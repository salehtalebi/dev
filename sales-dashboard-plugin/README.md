# Sales Dashboard WordPress Plugin

یک پلاگین وردپرس کامل برای مدیریت داشبورد فروش با قابلیت JWT Authentication و API های RESTful.

## ویژگی‌ها

### فاز اول (نمایش دیتا) ✅
- نمایش آمار کلی فروش
- نمایش لیست سفارشات با فیلتر
- نمایش لیست مشتریان 
- نمایش سفارشات هر مشتری
- فیلتر بر اساس Account Manager
- فیلتر بر اساس تاریخ
- فیلتر بر اساس وضعیت سفارش
- نمایش آمار ماهانه
- نمایش محصولات پرفروش
- نمایش عملکرد Account Manager ها
- امکان Export داده‌ها (CSV, JSON, XML)

### فاز بعدی (مدیریت) 🔄
- اساین کردن مشتری به Account Manager
- ویرایش اطلاعات مشتری
- تغییر وضعیت سفارشات
- افزودن یادداشت به سفارشات

## نصب و راه‌اندازی

### 1. نصب پلاگین
```bash
# کپی فولدر sales-dashboard-plugin به wp-content/plugins/
cp -r sales-dashboard-plugin /path/to/wordpress/wp-content/plugins/
```

### 2. فعال‌سازی
- به WordPress Admin بروید
- Plugins > Installed Plugins
- پلاگین "Sales Dashboard API" را فعال کنید

### 3. تنظیمات اولیه
- به Sales Dashboard > Settings بروید
- JWT Secret Key را تولید کنید
- CORS Origins را تنظیم کنید (دامنه فرانت‌اند خود)
- تنظیمات را ذخیره کنید

## Account Managers

Account Manager ها در user meta ذخیره می‌شوند:
- Meta Key: `_account_manager_id`
- Meta Value: ID مدیر (مثل 'house', '1465', '845', ...)

### لیست Account Managers:
```php
$managers = array(
    'house' => 'House',
    '1465'  => 'Ina Istok',
    '845'   => 'Pina Lee',
    '1886'  => 'Vidika Shenton',
    '2532'  => 'Sarah Hearn',
    '2533'  => 'Jonathon Regan',
);
```

## API Endpoints

Base URL: `/wp-json/sales-dashboard/v1/`

### Authentication

#### Login
```http
POST /auth/login
Content-Type: application/json

{
  "username": "your_username",
  "password": "your_password"
}
```

#### استفاده از Token
```http
Authorization: Bearer YOUR_JWT_TOKEN
```

### Dashboard Analytics

#### آمار کلی داشبورد
```http
GET /analytics/dashboard?period=month&manager_id=1465
```

#### مقایسه ماهانه
```http
GET /analytics/monthly-comparison?months=6&manager_id=house
```

#### گزارش فروش
```http
GET /reports/sales/month?manager_id=845
```

#### محصولات پرفروش
```http
GET /reports/top-sellers/month?limit=10&manager_id=1886
```

#### عملکرد Account Manager ها
```http
GET /analytics/managers-performance?period=month
```

### Orders (سفارشات)

#### لیست سفارشات
```http
GET /wc/orders?page=1&per_page=20&status=completed&manager_id=1465
```

#### سفارشات بر اساس Account Manager
```http
GET /account-managers/1465/orders?page=1&per_page=20&date_from=2025-01-01
```

#### آمار سفارشات
```http
GET /orders/statistics?period=month&manager_id=house
```

### Customers (مشتریان)

#### لیست مشتریان
```http
GET /wc/customers?page=1&per_page=20&search=احمد
```

#### مشتریان بر اساس Account Manager
```http
GET /account-managers/845/customers?page=1&per_page=20
```

#### آمار مشتری
```http
GET /customers/123/statistics
```

### Account Managers

#### لیست Account Manager ها
```http
GET /account-managers
```

#### آمار Account Manager
```http
GET /account-managers/1465/stats?period=month
```

### Export

#### Export سفارشات
```http
GET /export/orders?format=csv&manager_id=1465&date_from=2025-01-01&date_to=2025-01-31
```

#### Export مشتریان
```http
GET /export/customers?format=json&manager_id=house
```

#### Export گزارشات
```http
GET /export/reports/sales?format=csv&period=month&manager_id=845
```

**فرمت‌های پشتیبانی شده:** `csv`, `json`, `xml`

## فیلترها

### فیلتر بر اساس Account Manager
تمام endpoint ها از پارامتر `manager_id` پشتیبانی می‌کنند:
- `house` - House
- `1465` - Ina Istok  
- `845` - Pina Lee
- `1886` - Vidika Shenton
- `2532` - Sarah Hearn
- `2533` - Jonathon Regan

### فیلتر بر اساس تاریخ
```http
?date_from=2025-01-01&date_to=2025-01-31
```

### فیلتر بر اساس دوره زمانی
```http
?period=week|month|quarter|year
```

### فیلتر بر اساس وضعیت
```http
?status=pending|processing|completed|cancelled
```

### Pagination
```http
?page=1&per_page=20
```

### جستجو
```http
?search=احمد
```

## نمونه Response

### Dashboard Analytics
```json
{
  "total_orders": 1250,
  "total_revenue": 45000.00,
  "total_customers": 320,
  "avg_order_value": 36.00,
  "orders_growth": 12.5,
  "revenue_growth": 8.3,
  "daily_sales": [
    {
      "date": "2025-01-15",
      "orders": 25,
      "revenue": 1200.00
    }
  ],
  "top_products": [
    {
      "product_id": 123,
      "product_name": "محصول نمونه",
      "quantity_sold": 45,
      "total_revenue": 2250.00
    }
  ]
}
```

### Account Managers
```json
[
  {
    "id": "1465",
    "name": "Ina Istok",
    "customers_count": 25,
    "is_active": true
  }
]
```

### Orders List
```json
{
  "data": [
    {
      "id": 12345,
      "number": "#12345",
      "status": "completed",
      "total": 150.00,
      "date_created": "2025-01-15 10:30:00",
      "customer_id": 678,
      "billing": {
        "first_name": "احمد",
        "last_name": "محمدی",
        "email": "ahmad@example.com"
      },
      "customer_manager": {
        "id": "1465",
        "name": "Ina Istok"
      }
    }
  ],
  "total": 1250,
  "pages": 63
}
```

## امنیت

- تمام endpoint ها نیاز به authentication دارند (جز login)
- JWT tokens با تاریخ انقضا
- CORS protection
- Rate limiting
- Input validation و sanitization
- Permission checks

## نیازمندی‌ها

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+
- MySQL 5.6+

## پشتیبانی از BuddyPress

اگر BuddyPress نصب باشد، فیلد "Business Province" نیز در اطلاعات مشتریان نمایش داده می‌شود.

## ساختار فایل‌ها

```
sales-dashboard-plugin/
├── sales-dashboard-plugin.php (فایل اصلی)
├── README.md
├── includes/
│   ├── class-jwt-auth.php (احراز هویت JWT)
│   ├── class-api-routes.php (API های اصلی)
│   ├── class-analytics.php (آمار و گزارشات)
│   ├── class-account-managers.php (مدیران حساب)
│   └── class-export.php (خروجی داده‌ها)
└── admin/
    └── class-admin.php (پنل مدیریت)
```

## توسعه

برای افزودن Account Manager جدید:

1. فایل `includes/class-account-managers.php` را باز کنید
2. آرایه `$managers` را به‌روزرسانی کنید
3. همین کار را در سایر فایل‌ها نیز انجام دهید

## تست API

پس از نصب، می‌توانید از WordPress Admin Panel > Sales Dashboard > JWT Tokens برای تست API استفاده کنید.

## مجوز

GPL v2 or later
