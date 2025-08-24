import axios from 'axios'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'https://academy.com'
const WP_API_ENDPOINT = import.meta.env.VITE_WP_API_ENDPOINT || '/wp-json/wp/v2'
const WC_API_ENDPOINT = import.meta.env.VITE_WC_API_ENDPOINT || '/wp-json/wc/v3'
const CUSTOM_API_ENDPOINT = import.meta.env.VITE_CUSTOM_API_ENDPOINT || '/wp-json/sales-dashboard/v1'
const JWT_ENDPOINT = import.meta.env.VITE_JWT_ENDPOINT || '/wp-json/sales-dashboard/v1/auth'

class SalesDashboardAPI {
  constructor() {
    this.apiClient = axios.create({
      baseURL: API_BASE_URL,
      timeout: 30000, // افزایش به 30 ثانیه
      headers: {
        'Content-Type': 'application/json',
      }
    })

    // Add JWT token to requests
    this.apiClient.interceptors.request.use(
      (config) => {
        const token = localStorage.getItem('auth_token')
        if (token) {
          config.headers.Authorization = `Bearer ${token}`
        }
        return config
      },
      (error) => {
        console.error('Request interceptor error:', error)
        return Promise.reject(error)
      }
    )

    // Handle response errors
    this.apiClient.interceptors.response.use(
      (response) => response.data,
      (error) => {
        console.error('API Error:', error)

        // بررسی نوع خطا
        if (error.code === 'ECONNABORTED') {
          console.error('Request timeout')
          error.message = 'درخواست بیش از حد طولانی بود'
        } else if (error.response?.status === 500) {
          error.message = 'خطای داخلی سرور'
        } else if (!error.response) {
          error.message = 'عدم دسترسی به سرور'
        }

        return Promise.reject(error)
      }
    )
  }

  async request(endpoint, options = {}) {
    const config = {
      url: `${endpoint}`,
      method: options.method || 'GET',
      ...options
    }

    if (options.params) {
      config.params = options.params
    }

    if (options.body) {
      config.data = options.body
    }

    // retry mechanism برای درخواست‌های ناموفق
    const maxRetries = 2
    let retries = 0

    while (retries <= maxRetries) {
      try {
        const response = await this.apiClient(config)
        return response
      } catch (error) {
        retries++

        // اگه خطای timeout یا network error باشه و تعداد تلاش کافی نباشه
        if (retries <= maxRetries &&
          (error.code === 'ECONNABORTED' ||
            error.code === 'ERR_NETWORK' ||
            !error.response)) {
          console.warn(`Retry ${retries}/${maxRetries} for ${endpoint}`)
          await new Promise(resolve => setTimeout(resolve, 1000 * retries)) // تاخیر تصاعدی
          continue
        }

        throw new Error(error.response?.data?.message || error.message || 'خطا در برقراری ارتباط با سرور')
      }
    }
  }

  // Auth methods
  async login(credentials) {
    return await this.request(`${JWT_ENDPOINT}/login`, {
      method: 'POST',
      body: credentials
    })
  }

  async validateToken() {
    return await this.request(`${JWT_ENDPOINT}/validate`, {
      method: 'POST'
    })
  }

  // Orders methods - Updated to use sales-dashboard API  
  async getOrders(params = {}) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/wc/orders`, { params })
  }

  async getOrder(orderId) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/wc/orders/${orderId}`)
  }

  async updateOrder(orderId, data) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/wc/orders/${orderId}/status`, {
      method: 'PUT',
      body: data
    })
  }

  // Customers methods - Updated to use sales-dashboard API
  async getCustomers(params = {}) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/wc/customers`, { params })
  }

  async getCustomer(customerId) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/wc/customers/${customerId}`)
  }

  // Analytics methods - Using class-analytics.php endpoints
  async getAnalytics(params = {}) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/analytics/dashboard`, { params })
  }

  async getTopProducts(params = {}) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/analytics/top-products`, { params })
  }

  async getMonthlyRevenue(params = {}) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/analytics/monthly-revenue`, { params })
  }

  async getSalesComparison(params = {}) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/analytics/sales-comparison`, { params })
  }

  async getManagerPerformance(params = {}) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/analytics/manager-performance`, { params })
  }

  // Export methods - Using class-export.php endpoints (POST requests)
  async exportOrders(filters = {}) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/export/orders`, {
      method: 'POST',
      body: filters
    })
  }

  async exportCustomers(filters = {}) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/export/customers`, {
      method: 'POST',
      body: filters
    })
  }
}

export default new SalesDashboardAPI()
