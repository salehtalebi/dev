import { ref, computed } from 'vue'
import salesDashboardAPI from '@/services/api'
import { useAuthStore } from '@/stores/auth'

/**
 * Composable برای مدیریت API calls و حالت loading
 */
export function useAPI() {
  const loading = ref(false)
  const error = ref(null)

  const clearError = () => {
    error.value = null
  }

  const handleRequest = async (requestFn) => {
    loading.value = true
    error.value = null
    
    try {
      const result = await requestFn()
      return result
    } catch (err) {
      error.value = err.message || 'خطا در برقراری ارتباط با سرور'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    loading: computed(() => loading.value),
    error: computed(() => error.value),
    clearError,
    handleRequest,
  }
}

/**
 * Composable برای مدیریت احراز هویت
 */
export function useAuth() {
  const authStore = useAuthStore()
  const { loading, error, handleRequest } = useAPI()

  const login = async (username, password) => {
    return handleRequest(() => authStore.login(username, password))
  }

  const logout = async () => {
    return handleRequest(() => authStore.logout())
  }

  const validateToken = async () => {
    return handleRequest(() => authStore.validateToken())
  }

  const refreshToken = async () => {
    return handleRequest(() => authStore.refreshToken())
  }

  return {
    // State
    user: computed(() => authStore.user),
    token: computed(() => authStore.token),
    isAuthenticated: computed(() => authStore.isAuthenticated),
    loading,
    error,
    
    // Actions
    login,
    logout,
    validateToken,
    refreshToken,
    clearError: authStore.clearError,
  }
}

/**
 * Composable برای مدیریت dashboard
 */
export function useDashboard() {
  const { loading, error, handleRequest } = useAPI()

  const getAnalytics = async (params = {}) => {
    return handleRequest(() => salesDashboardAPI.getAnalytics(params))
  }

  const getTopProducts = async (params = {}) => {
    return handleRequest(() => salesDashboardAPI.getTopProducts(params))
  }

  const getMonthlyRevenue = async (params = {}) => {
    return handleRequest(() => salesDashboardAPI.getMonthlyRevenue(params))
  }

  const getSalesComparison = async (params = {}) => {
    return handleRequest(() => salesDashboardAPI.getSalesComparison(params))
  }

  const getManagerPerformance = async (params = {}) => {
    return handleRequest(() => salesDashboardAPI.getManagerPerformance(params))
  }

  return {
    loading,
    error,
    getAnalytics,
    getTopProducts,
    getMonthlyRevenue,
    getSalesComparison,
    getManagerPerformance,
  }
}

/**
 * Composable برای مدیریت سفارشات
 */
export function useOrders() {
  const { loading, error, handleRequest } = useAPI()

  const getOrders = async (params = {}) => {
    return handleRequest(() => salesDashboardAPI.getOrders(params))
  }

  const getOrder = async (orderId) => {
    return handleRequest(() => salesDashboardAPI.getOrder(orderId))
  }

  const updateOrder = async (orderId, data) => {
    return handleRequest(() => salesDashboardAPI.updateOrder(orderId, data))
  }

  const getOrderItems = async (orderId) => {
    return handleRequest(() => salesDashboardAPI.getOrderItems(orderId))
  }

  const getOrderStatusCounts = async (params = {}) => {
    return handleRequest(() => salesDashboardAPI.getOrderStatusCounts(params))
  }

  return {
    loading,
    error,
    getOrders,
    getOrder,
    updateOrder,
    getOrderItems,
    getOrderStatusCounts,
  }
}

/**
 * Composable برای مدیریت مشتریان
 */
export function useCustomers() {
  const { loading, error, handleRequest } = useAPI()

  const getCustomers = async (params = {}) => {
    return handleRequest(() => salesDashboardAPI.getCustomers(params))
  }

  const getCustomer = async (customerId) => {
    return handleRequest(() => salesDashboardAPI.getCustomer(customerId))
  }

  const updateCustomer = async (customerId, data) => {
    return handleRequest(() => salesDashboardAPI.updateCustomer(customerId, data))
  }

  return {
    loading,
    error,
    getCustomers,
    getCustomer,
    updateCustomer,
  }
}

/**
 * Composable برای مدیریت Account Managers
 */
export function useAccountManagers() {
  const { loading, error, handleRequest } = useAPI()

  const getAccountManagers = async () => {
    return handleRequest(() => salesDashboardAPI.getAccountManagers())
  }

  const assignAccountManager = async (customerId, managerId) => {
    return handleRequest(() => salesDashboardAPI.assignAccountManager(customerId, managerId))
  }

  return {
    loading,
    error,
    getAccountManagers,
    assignAccountManager,
  }
}

/**
 * Composable برای Export داده‌ها
 */
export function useExport() {
  const { loading, error, handleRequest } = useAPI()

  const exportData = async (params = {}) => {
    return handleRequest(() => salesDashboardAPI.exportData(params))
  }

  const downloadFile = (url, filename = null) => {
    const link = document.createElement('a')
    link.href = url
    if (filename) {
      link.download = filename
    }
    link.target = '_blank'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  return {
    loading,
    error,
    exportData,
    downloadFile,
  }
}

/**
 * Composable برای فرمت کردن داده‌ها
 */
export function useFormatters() {
  const formatPrice = (price, currency = 'تومان') => {
    if (!price) return '0'
    
    const formatted = new Intl.NumberFormat('fa-IR').format(price)
    return `${formatted} ${currency}`
  }

  const formatDate = (date, options = {}) => {
    if (!date) return ''
    
    const defaultOptions = {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    }
    
    return new Intl.DateTimeFormat('fa-IR', { ...defaultOptions, ...options }).format(new Date(date))
  }

  const formatDateTime = (date) => {
    return formatDate(date, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  const formatOrderStatus = (status) => {
    const statusMap = {
      'pending': 'در انتظار پرداخت',
      'processing': 'در حال پردازش',
      'on-hold': 'در انتظار',
      'completed': 'تکمیل شده',
      'cancelled': 'لغو شده',
      'refunded': 'بازگشت داده شده',
      'failed': 'ناموفق',
    }
    
    return statusMap[status] || status
  }

  const formatAccountManager = (managerId) => {
    const managers = {
      'house': 'داخلی',
      '1465': 'مدیر ۱۴۶۵',
      '845': 'مدیر ۸۴۵',
    }
    
    return managers[managerId] || `مدیر ${managerId}`
  }

  const formatPercentage = (value, decimals = 1) => {
    if (!value) return '0%'
    return `${value.toFixed(decimals)}%`
  }

  const formatNumber = (number) => {
    if (!number) return '0'
    return new Intl.NumberFormat('fa-IR').format(number)
  }

  return {
    formatPrice,
    formatDate,
    formatDateTime,
    formatOrderStatus,
    formatAccountManager,
    formatPercentage,
    formatNumber,
  }
}

/**
 * Composable برای مدیریت pagination
 */
export function usePagination(initialPage = 1, initialPerPage = 20) {
  const currentPage = ref(initialPage)
  const perPage = ref(initialPerPage)
  const total = ref(0)

  const totalPages = computed(() => Math.ceil(total.value / perPage.value))
  const hasNext = computed(() => currentPage.value < totalPages.value)
  const hasPrev = computed(() => currentPage.value > 1)

  const setPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
      currentPage.value = page
    }
  }

  const nextPage = () => {
    if (hasNext.value) {
      currentPage.value++
    }
  }

  const prevPage = () => {
    if (hasPrev.value) {
      currentPage.value--
    }
  }

  const reset = () => {
    currentPage.value = initialPage
    total.value = 0
  }

  return {
    currentPage: computed(() => currentPage.value),
    perPage: computed(() => perPage.value),
    total: computed(() => total.value),
    totalPages,
    hasNext,
    hasPrev,
    setPage,
    nextPage,
    prevPage,
    reset,
    setTotal: (newTotal) => { total.value = newTotal },
  }
}
