// API Configuration for Frontend
export const API_CONFIG = {
  // Base URLs
  BASE_URL: process.env.VITE_API_BASE_URL || 'https://academy.com/wp-json/sales-dashboard/v1',
  WP_API_URL: process.env.VITE_WP_API_URL || 'https://academy.com/wp-json/wp/v2',
  
  // Account Managers
  MANAGERS: {
    'house': 'House',
    '1465': 'Ina Istok',
    '845': 'Pina Lee',
    '1886': 'Vidika Shenton',
    '2532': 'Sarah Hearn',
    '2533': 'Jonathon Regan',
  },

  // API Endpoints
  ENDPOINTS: {
    // Authentication
    AUTH: {
      LOGIN: '/auth/login',
      VALIDATE: '/auth/validate',
      REFRESH: '/auth/refresh',
      ME: '/auth/me',
    },

    // Analytics
    ANALYTICS: {
      DASHBOARD: '/analytics/dashboard',
      MONTHLY_COMPARISON: '/analytics/monthly-comparison',
      MANAGERS_PERFORMANCE: '/analytics/managers-performance',
    },

    // Reports
    REPORTS: {
      SALES: (period) => `/reports/sales/${period}`,
      TOP_SELLERS: (period) => `/reports/top-sellers/${period}`,
    },

    // Orders
    ORDERS: {
      LIST: '/wc/orders',
      STATISTICS: '/orders/statistics',
      BY_MANAGER: (managerId) => `/account-managers/${managerId}/orders`,
    },

    // Customers
    CUSTOMERS: {
      LIST: '/wc/customers',
      STATISTICS: (customerId) => `/customers/${customerId}/statistics`,
      BY_MANAGER: (managerId) => `/account-managers/${managerId}/customers`,
    },

    // Account Managers
    ACCOUNT_MANAGERS: {
      LIST: '/account-managers',
      STATISTICS: (managerId) => `/account-managers/${managerId}/stats`,
      ASSIGN: '/account-managers/assign',
      UNASSIGN: '/account-managers/unassign',
      BULK_ASSIGN: '/account-managers/bulk-assign',
    },

    // Export
    EXPORT: {
      ORDERS: '/export/orders',
      CUSTOMERS: '/export/customers',
      REPORTS: (type) => `/export/reports/${type}`,
    },
  },

  // Default Parameters
  DEFAULTS: {
    PER_PAGE: 20,
    MAX_PER_PAGE: 100,
    DEFAULT_PERIOD: 'month',
    TOKEN_EXPIRY_DAYS: 7,
  },

  // Available Filters
  FILTERS: {
    PERIODS: ['week', 'month', 'quarter', 'year'],
    ORDER_STATUSES: ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'],
    EXPORT_FORMATS: ['csv', 'json', 'xml'],
    REPORT_TYPES: ['sales', 'products', 'customers', 'managers'],
  },

  // Request Headers
  HEADERS: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },

  // Error Messages
  ERROR_MESSAGES: {
    NETWORK_ERROR: 'خطا در اتصال به سرور',
    INVALID_TOKEN: 'توکن نامعتبر است',
    PERMISSION_DENIED: 'دسترسی مجاز نیست',
    NOT_FOUND: 'اطلاعات مورد نظر یافت نشد',
    SERVER_ERROR: 'خطای سرور',
    VALIDATION_ERROR: 'اطلاعات وارد شده صحیح نیست',
  },

  // Success Messages
  SUCCESS_MESSAGES: {
    LOGIN_SUCCESS: 'ورود موفقیت‌آمیز',
    DATA_LOADED: 'اطلاعات بارگذاری شد',
    EXPORT_SUCCESS: 'خروجی با موفقیت ایجاد شد',
    ASSIGNMENT_SUCCESS: 'تخصیص با موفقیت انجام شد',
  },
}

// Helper functions for API calls
export const API_HELPERS = {
  // Build query string from parameters
  buildQueryString: (params) => {
    const query = new URLSearchParams()
    Object.entries(params).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        query.append(key, value)
      }
    })
    return query.toString() ? `?${query.toString()}` : ''
  },

  // Get authorization header
  getAuthHeaders: (token) => ({
    ...API_CONFIG.HEADERS,
    'Authorization': `Bearer ${token}`,
  }),

  // Build full URL
  buildUrl: (endpoint, params = {}) => {
    const queryString = API_HELPERS.buildQueryString(params)
    return `${API_CONFIG.BASE_URL}${endpoint}${queryString}`
  },

  // Format date for API
  formatDate: (date) => {
    if (!date) return null
    return new Date(date).toISOString().split('T')[0] // YYYY-MM-DD
  },

  // Parse API response
  parseResponse: async (response) => {
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}))
      throw new Error(errorData.message || API_CONFIG.ERROR_MESSAGES.SERVER_ERROR)
    }
    return response.json()
  },

  // Handle API errors
  handleError: (error) => {
    console.error('API Error:', error)
    
    if (error.message.includes('401')) {
      return API_CONFIG.ERROR_MESSAGES.INVALID_TOKEN
    }
    
    if (error.message.includes('403')) {
      return API_CONFIG.ERROR_MESSAGES.PERMISSION_DENIED
    }
    
    if (error.message.includes('404')) {
      return API_CONFIG.ERROR_MESSAGES.NOT_FOUND
    }
    
    if (error.message.includes('422')) {
      return API_CONFIG.ERROR_MESSAGES.VALIDATION_ERROR
    }
    
    return error.message || API_CONFIG.ERROR_MESSAGES.NETWORK_ERROR
  },

  // Get manager name by ID
  getManagerName: (managerId) => {
    return API_CONFIG.MANAGERS[managerId] || 'نامشخص'
  },

  // Format currency
  formatCurrency: (amount, currency = 'IRR') => {
    return new Intl.NumberFormat('fa-IR', {
      style: 'currency',
      currency: currency,
    }).format(amount)
  },

  // Format date for display
  formatDisplayDate: (dateString) => {
    if (!dateString) return '-'
    return new Intl.DateTimeFormat('fa-IR').format(new Date(dateString))
  },

  // Format number
  formatNumber: (number) => {
    if (!number) return '0'
    return new Intl.NumberFormat('fa-IR').format(number)
  },
}

// Export types for TypeScript users
export const API_TYPES = {
  Period: ['week', 'month', 'quarter', 'year'],
  OrderStatus: ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'],
  ExportFormat: ['csv', 'json', 'xml'],
  ReportType: ['sales', 'products', 'customers', 'managers'],
  ManagerId: ['house', '1465', '845', '1886', '2532', '2533'],
}
