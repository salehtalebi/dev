import { API_CONFIG } from '@/config/api'
import { useAuthStore } from '@/stores/auth'
import { computed, ref } from 'vue'

/**
 * Base API composable
 */
export function useAPI() {
  const loading = ref(false)
  const error = ref(null)

  const clearError = () => {
    error.value = null
  }

  // When returnHeaders=true, resolve to { data, headers }
  const makeRequest = async (url, options = {}, returnHeaders = false) => {
    const authStore = useAuthStore()

    const config = {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        ...options.headers
      },
      ...options
    }

    if (authStore.token) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }

    if (config.method !== 'GET' && options.body) {
      config.body = JSON.stringify(options.body)
    }

    const response = await fetch(`${API_CONFIG.BASE_URL}${url}`, config)

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || `HTTP ${response.status}`)
    }

    if (returnHeaders) {
      // Clone to allow both headers read and json parse safely in some runtimes
      const data = await response.json().catch(() => null)
      return { data, headers: response.headers }
    }

    return response.json()
  }

  const handleRequest = async (requestFn) => {
    loading.value = true
    error.value = null

    try {
      const result = await requestFn()
      return result
    } catch (err) {
      error.value = err.message || 'Error connecting to server'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Orders API
  const getOrders = async (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/wc/orders${query ? '?' + query : ''}`.replace(API_CONFIG.BASE_URL, ''), {}, true)
  }

  const getOrder = async (orderId) => {
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/wc/orders/${orderId}`.replace(API_CONFIG.BASE_URL, ''))
  }

  const updateOrder = async (orderId, data) => {
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/wc/orders/${orderId}`.replace(API_CONFIG.BASE_URL, ''), {
      method: 'PUT',
      body: data
    })
  }

  // Customers API
  const getCustomers = async (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/wc/customers${query ? '?' + query : ''}`.replace(API_CONFIG.BASE_URL, ''), {}, true)
  }

  const getCustomer = async (customerId) => {
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/wc/customers/${customerId}`.replace(API_CONFIG.BASE_URL, ''))
  }

  const getCustomerStatistics = async (customerId) => {
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/customers/${customerId}/statistics`.replace(API_CONFIG.BASE_URL, ''))
  }

  // Analytics API
  const getAnalytics = async (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/analytics/dashboard${query ? '?' + query : ''}`.replace(API_CONFIG.BASE_URL, ''))
  }

  const getTopProducts = async (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/analytics/top-products${query ? '?' + query : ''}`.replace(API_CONFIG.BASE_URL, ''))
  }

  const getMonthlyRevenue = async (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/analytics/monthly-revenue${query ? '?' + query : ''}`.replace(API_CONFIG.BASE_URL, ''))
  }

  const getSalesComparison = async (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/analytics/sales-comparison${query ? '?' + query : ''}`.replace(API_CONFIG.BASE_URL, ''))
  }

  const getManagerPerformance = async (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/analytics/manager-performance${query ? '?' + query : ''}`.replace(API_CONFIG.BASE_URL, ''))
  }

  // Export API
  const exportOrders = async (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/export/orders${query ? '?' + query : ''}`.replace(API_CONFIG.BASE_URL, ''), {
      method: 'POST',
      body: params
    })
  }

  const exportCustomers = async (params = {}) => {
    const query = new URLSearchParams(params).toString()
    return await makeRequest(`${API_CONFIG.CUSTOM_API_URL}/export/customers${query ? '?' + query : ''}`.replace(API_CONFIG.BASE_URL, ''), {
      method: 'POST',
      body: params
    })
  }

  return {
    loading: computed(() => loading.value),
    error: computed(() => error.value),
    clearError,
    handleRequest,

    // Orders
    getOrders,
    getOrder,
    updateOrder,

    // Customers
    getCustomers,
    getCustomer,
    getCustomerStatistics,

    // Analytics
    getAnalytics,
    getTopProducts,
    getMonthlyRevenue,
    getSalesComparison,
    getManagerPerformance,

    // Export
    exportOrders,
    exportCustomers,
  }
}

/**
 * Auth composable
 */
export function useAuth() {
  const authStore = useAuthStore()
  const { loading, error, handleRequest } = useAPI()

  const login = async (credentials) => {
    return handleRequest(() => authStore.login(credentials))
  }

  const logout = async () => {
    return handleRequest(() => authStore.logout())
  }

  return {
    user: computed(() => authStore.user),
    token: computed(() => authStore.token),
    isAuthenticated: computed(() => authStore.isAuthenticated),
    loading,
    error,
    login,
    logout,
  }
}

/**
 * Formatters composable
 */
export function useFormatters() {
  const formatPrice = (price, currency = 'USD') => {
    if (!price) return '0'

    const formatted = new Intl.NumberFormat('en-US').format(price)
    return `${formatted} ${currency}`
  }

  const formatDate = (date, options = {}) => {
    if (!date) return ''

    const defaultOptions = {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    }

    return new Intl.DateTimeFormat('en-US', { ...defaultOptions, ...options }).format(new Date(date))
  }

  const formatOrderStatus = (status) => {
    const statusMap = {
      'pending': 'Pending Payment',
      'processing': 'Processing',
      'on-hold': 'On Hold',
      'completed': 'Completed',
      'cancelled': 'Cancelled',
      'refunded': 'Refunded',
      'failed': 'Failed',
    }

    return statusMap[status] || status
  }

  const formatNumber = (number) => {
    if (!number) return '0'
    return new Intl.NumberFormat('en-US').format(number)
  }

  return {
    formatPrice,
    formatDate,
    formatOrderStatus,
    formatNumber,
  }
}
