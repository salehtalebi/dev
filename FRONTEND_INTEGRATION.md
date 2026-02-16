# راهنمای Integration Frontend با API پلاگین

این راهنما نحوه استفاده از API پلاگین Sales Dashboard در frontend Vue.js را توضیح می‌دهد.

## فایل‌های مهم

### 1. تنظیمات API - `src/config/api.js`
```javascript
import { API_CONFIG } from '@/config/api'

// استفاده از کانفیگ
console.log(API_CONFIG.BASE_URL) // URL پایه API
console.log(API_CONFIG.ENDPOINTS.AUTH.LOGIN) // endpoint ورود
console.log(API_CONFIG.MANAGERS) // لیست مدیران
```

### 2. سرویس API - `src/services/api.js`
```javascript
import salesDashboardAPI from '@/services/api'

// ورود
const response = await salesDashboardAPI.login('username', 'password')

// دریافت آمار
const analytics = await salesDashboardAPI.getAnalytics({
  date_range: 'last_month',
  account_manager: 'house'
})
```

### 3. Stores - پلاگین Pinia
```javascript
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import { useOrdersStore } from '@/stores/orders'
import { useCustomersStore } from '@/stores/customers'

// در component
const authStore = useAuthStore()
const dashboardStore = useDashboardStore()
```

### 4. Composables - `src/composables/useAPI.js`
```javascript
import { useAuth, useDashboard, useOrders } from '@/composables/useAPI'

// در component
export default {
  setup() {
    const { login, logout, user } = useAuth()
    const { getAnalytics, loading } = useDashboard()
    
    return {
      login,
      logout,
      user,
      getAnalytics,
      loading
    }
  }
}
```

## نمونه استفاده در Components

### 1. صفحه ورود - Login.vue
```vue
<template>
  <form @submit.prevent="handleLogin">
    <input v-model="username" placeholder="نام کاربری" />
    <input v-model="password" type="password" placeholder="رمز عبور" />
    <button type="submit" :disabled="loading">
      {{ loading ? 'در حال ورود...' : 'ورود' }}
    </button>
  </form>
</template>

<script>
import { ref } from 'vue'
import { useAuth } from '@/composables/useAPI'
import { useRouter } from 'vue-router'

export default {
  setup() {
    const { login, loading, error } = useAuth()
    const router = useRouter()
    
    const username = ref('')
    const password = ref('')
    
    const handleLogin = async () => {
      try {
        await login(username.value, password.value)
        router.push('/dashboard')
      } catch (err) {
        console.error('Login failed:', err)
      }
    }
    
    return {
      username,
      password,
      handleLogin,
      loading,
      error
    }
  }
}
</script>
```

### 2. داشبورد - Dashboard.vue
```vue
<template>
  <div>
    <!-- فیلترها -->
    <div class="filters">
      <select v-model="selectedManager" @change="updateFilters">
        <option value="">همه مدیران</option>
        <option 
          v-for="manager in managers" 
          :key="manager.id" 
          :value="manager.id"
        >
          {{ manager.name }}
        </option>
      </select>
      
      <select v-model="dateRange" @change="updateFilters">
        <option value="last_week">هفته گذشته</option>
        <option value="last_month">ماه گذشته</option>
        <option value="last_3_months">3 ماه گذشته</option>
      </select>
    </div>
    
    <!-- آمار -->
    <div class="stats" v-if="!loading">
      <div class="stat-card">
        <h3>کل فروش</h3>
        <p>{{ formatPrice(stats.totalRevenue) }}</p>
      </div>
      
      <div class="stat-card">
        <h3>تعداد سفارشات</h3>
        <p>{{ formatNumber(stats.totalOrders) }}</p>
      </div>
      
      <div class="stat-card">
        <h3>تعداد مشتریان</h3>
        <p>{{ formatNumber(stats.totalCustomers) }}</p>
      </div>
      
      <div class="stat-card">
        <h3>میانگین سفارش</h3>
        <p>{{ formatPrice(stats.averageOrderValue) }}</p>
      </div>
    </div>
    
    <!-- لودینگ -->
    <div v-if="loading" class="loading">
      در حال بارگذاری...
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import { useFormatters } from '@/composables/useAPI'
import { API_CONFIG } from '@/config/api'

export default {
  setup() {
    const dashboardStore = useDashboardStore()
    const { formatPrice, formatNumber } = useFormatters()
    
    const selectedManager = ref('')
    const dateRange = ref('last_month')
    
    const managers = computed(() => API_CONFIG.MANAGERS)
    const stats = computed(() => dashboardStore.stats)
    const loading = computed(() => dashboardStore.isLoading)
    
    const updateFilters = async () => {
      dashboardStore.setFilters({
        accountManager: selectedManager.value,
        dateRange: dateRange.value
      })
      
      await dashboardStore.fetchAllDashboardData(true)
    }
    
    onMounted(async () => {
      await dashboardStore.fetchAllDashboardData()
    })
    
    return {
      selectedManager,
      dateRange,
      managers,
      stats,
      loading,
      updateFilters,
      formatPrice,
      formatNumber
    }
  }
}
</script>
```

### 3. لیست سفارشات - Orders.vue
```vue
<template>
  <div>
    <!-- فیلترها و جستجو -->
    <div class="filters">
      <input 
        v-model="searchQuery" 
        @input="handleSearch"
        placeholder="جستجو در سفارشات..."
      />
      
    <select v-model="statusFilter" @change="filterByStatus">
        <option value="">All statuses</option>
        <option value="pending">Pending payment</option>
        <option value="processing">Processing</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
      </select>
      
      <button @click="exportOrders" :disabled="exporting">
        {{ exporting ? 'در حال Export...' : 'Export CSV' }}
      </button>
    </div>
    
    <!-- جدول سفارشات -->
    <table v-if="!loading">
      <thead>
        <tr>
          <th>شماره سفارش</th>
          <th>مشتری</th>
          <th>مبلغ</th>
          <th>وضعیت</th>
          <th>تاریخ</th>
          <th>مدیر حساب</th>
          <th>عملیات</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="order in orders" :key="order.id">
          <td>#{{ order.id }}</td>
          <td>{{ order.billing.first_name }} {{ order.billing.last_name }}</td>
          <td>{{ formatPrice(order.total) }}</td>
          <td>
            <span :class="getStatusClass(order.status)">
              {{ formatOrderStatus(order.status) }}
            </span>
          </td>
          <td>{{ formatDate(order.date_created) }}</td>
          <td>{{ formatAccountManager(order.account_manager) }}</td>
          <td>
            <button @click="viewOrder(order.id)">مشاهده</button>
            <select @change="updateStatus(order.id, $event.target.value)">
              <option value="">تغییر وضعیت</option>
              <option value="processing">در حال پردازش</option>
              <option value="completed">تکمیل شده</option>
              <option value="cancelled">لغو</option>
            </select>
          </td>
        </tr>
      </tbody>
    </table>
    
    <!-- Pagination -->
    <div class="pagination" v-if="!loading">
      <button 
        @click="prevPage" 
        :disabled="!hasPrevPage"
      >
        قبلی
      </button>
      
      <span>صفحه {{ currentPage }} از {{ totalPages }}</span>
      
      <button 
        @click="nextPage" 
        :disabled="!hasNextPage"
      >
        بعدی
      </button>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useOrdersStore } from '@/stores/orders'
import { useFormatters, useExport } from '@/composables/useAPI'
import { useRouter } from 'vue-router'

export default {
  setup() {
    const ordersStore = useOrdersStore()
    const { formatPrice, formatDate, formatOrderStatus, formatAccountManager } = useFormatters()
    const { exportData } = useExport()
    const router = useRouter()
    
    const searchQuery = ref('')
    const statusFilter = ref('')
    const exporting = ref(false)
    
    const orders = computed(() => ordersStore.orders)
    const loading = computed(() => ordersStore.isLoading)
    const currentPage = computed(() => ordersStore.pagination.page)
    const totalPages = computed(() => ordersStore.pagination.totalPages)
    const hasNextPage = computed(() => ordersStore.hasNextPage)
    const hasPrevPage = computed(() => ordersStore.hasPrevPage)
    
    const handleSearch = async () => {
      await ordersStore.searchOrders(searchQuery.value)
    }
    
    const filterByStatus = async () => {
      await ordersStore.filterByStatus(statusFilter.value)
    }
    
    const updateStatus = async (orderId, status) => {
      if (status) {
        try {
          await ordersStore.updateOrderStatus(orderId, status)
          // نمایش پیام موفقیت
        } catch (error) {
          console.error('Failed to update status:', error)
        }
      }
    }
    
    const exportOrders = async () => {
      exporting.value = true
      try {
        await ordersStore.exportOrders('csv')
      } catch (error) {
        console.error('Export failed:', error)
      } finally {
        exporting.value = false
      }
    }
    
    const viewOrder = (orderId) => {
      router.push(`/orders/${orderId}`)
    }
    
    const prevPage = async () => {
      if (hasPrevPage.value) {
        await ordersStore.fetchOrders(currentPage.value - 1)
      }
    }
    
    const nextPage = async () => {
      if (hasNextPage.value) {
        await ordersStore.fetchOrders(currentPage.value + 1)
      }
    }
    
    const getStatusClass = (status) => {
      const classes = {
        'pending': 'status-warning',
        'processing': 'status-info',
        'completed': 'status-success',
        'cancelled': 'status-error',
        'refunded': 'status-error',
        'failed': 'status-error'
      }
      return classes[status] || 'status-default'
    }
    
    onMounted(async () => {
      await ordersStore.fetchOrders()
    })
    
    return {
      searchQuery,
      statusFilter,
      exporting,
      orders,
      loading,
      currentPage,
      totalPages,
      hasNextPage,
      hasPrevPage,
      handleSearch,
      filterByStatus,
      updateStatus,
      exportOrders,
      viewOrder,
      prevPage,
      nextPage,
      getStatusClass,
      formatPrice,
      formatDate,
      formatOrderStatus,
      formatAccountManager
    }
  }
}
</script>

<style scoped>
.status-warning { color: #f59e0b; }
.status-info { color: #3b82f6; }
.status-success { color: #10b981; }
.status-error { color: #ef4444; }
.status-default { color: #6b7280; }
</style>
```

## Router Guards برای Authentication

```javascript
// src/router/index.js
import { useAuthStore } from '@/stores/auth'

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  
  // صفحات که نیاز به احراز هویت دارند
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth)
  
  if (requiresAuth) {
    // بررسی وجود token
    if (!authStore.token) {
      next('/login')
      return
    }
    
    // اعتبارسنجی token
    const isValid = await authStore.validateToken()
    if (!isValid) {
      next('/login')
      return
    }
  }
  
  // اگر کاربر وارد شده و می‌خواهد به صفحه ورود برود
  if (to.path === '/login' && authStore.isAuthenticated) {
    next('/dashboard')
    return
  }
  
  next()
})
```

## نکات مهم

### 1. مدیریت خطاها
```javascript
// در component
const { error } = useAPI()

// نمایش خطا در template
<div v-if="error" class="error">
  {{ error }}
</div>
```

### 2. Refresh Token خودکار
```javascript
// در service
class SalesDashboardAPI {
  async request(endpoint, options = {}) {
    try {
      return await this.makeRequest(endpoint, options)
    } catch (error) {
      if (error.status === 401) {
        // تلاش برای refresh token
        const refreshed = await this.refreshToken()
        if (refreshed) {
          // تکرار درخواست
          return await this.makeRequest(endpoint, options)
        }
      }
      throw error
    }
  }
}
```

### 3. Cache مدیریت
```javascript
// در store
const cache = new Map()

actions: {
  async fetchData(params, useCache = true) {
    const cacheKey = JSON.stringify(params)
    
    if (useCache && cache.has(cacheKey)) {
      return cache.get(cacheKey)
    }
    
    const data = await api.getData(params)
    cache.set(cacheKey, data)
    
    return data
  }
}
```

### 4. Real-time Updates
```javascript
// استفاده از polling برای بروزرسانی داده‌ها
const startPolling = () => {
  setInterval(async () => {
    await dashboardStore.refreshData()
  }, 30000) // هر 30 ثانیه
}
```

این راهنما پایه‌ای برای شروع integration است. بر اساس نیازهای خاص پروژه می‌توان آن را گسترش داد.
