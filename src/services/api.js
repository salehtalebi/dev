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
      timeout: 10000,
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
      }
    )

    // Handle response errors
    this.apiClient.interceptors.response.use(
      (response) => response.data,
      (error) => {
        console.error('API Error:', error)
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

    try {
      const response = await this.apiClient(config)
      return response
    } catch (error) {
      throw new Error(error.response?.data?.message || error.message || 'خطا در برقراری ارتباط با سرور')
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

  // Orders methods
  async getOrders(params = {}) {
    return await this.request(WC_API_ENDPOINT + '/orders', { params })
  }

  async getOrder(orderId) {
    return await this.request(`${WC_API_ENDPOINT}/orders/${orderId}`)
  }

  async updateOrder(orderId, data) {
    return await this.request(`${WC_API_ENDPOINT}/orders/${orderId}`, {
      method: 'PUT',
      body: data
    })
  }

  // Customers methods
  async getCustomers(params = {}) {
    return await this.request(WC_API_ENDPOINT + '/customers', { params })
  }

  async getCustomer(customerId) {
    return await this.request(`${WC_API_ENDPOINT}/customers/${customerId}`)
  }

  // Analytics methods
  async getAnalytics(params = {}) {
    return await this.request(`${CUSTOM_API_ENDPOINT}/analytics/dashboard`, { params })
  }
}

export default new SalesDashboardAPI()
