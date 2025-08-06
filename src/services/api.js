/**
 * API Service for WordPress & WooCommerce Integration
 * Base URL should be your main domain: academy.com
 */
import axios from 'axios'

// Base configuration for WordPress API
const API_BASE_URL = 'https://academy.com'
const WP_API_BASE = `${API_BASE_URL}/wp-json/wp/v2`
const WC_API_BASE = `${API_BASE_URL}/wp-json/wc/v3`

// Create axios instance
const apiClient = axios.create({
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  }
})

// Request interceptor to add auth token
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('wp_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token expired, redirect to login
      localStorage.removeItem('wp_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

// WordPress Authentication API
export const authAPI = {
  // Login with WordPress JWT
  login: async (credentials) => {
    const response = await apiClient.post(`${API_BASE_URL}/wp-json/jwt-auth/v1/token`, credentials)
    return response.data
  },
  
  // Validate token
  validate: async () => {
    const response = await apiClient.post(`${API_BASE_URL}/wp-json/jwt-auth/v1/token/validate`)
    return response.data
  },
  
  // Get current user info
  me: async () => {
    const response = await apiClient.get(`${WP_API_BASE}/users/me`)
    return response.data
  }
}

// WooCommerce Orders API
export const ordersAPI = {
  // Get all orders with filters
  getOrders: async (params = {}) => {
    const response = await apiClient.get(`${WC_API_BASE}/orders`, { params })
    return {
      data: response.data,
      totalCount: parseInt(response.headers['x-wp-total']),
      totalPages: parseInt(response.headers['x-wp-totalpages'])
    }
  },
  
  // Get single order
  getOrder: async (orderId) => {
    const response = await apiClient.get(`${WC_API_BASE}/orders/${orderId}`)
    return response.data
  },
  
  // Update order status
  updateOrderStatus: async (orderId, status) => {
    const response = await apiClient.put(`${WC_API_BASE}/orders/${orderId}`, { status })
    return response.data
  }
}

// WooCommerce Customers API
export const customersAPI = {
  // Get all customers
  getCustomers: async (params = {}) => {
    const response = await apiClient.get(`${WC_API_BASE}/customers`, { params })
    return {
      data: response.data,
      totalCount: parseInt(response.headers['x-wp-total']),
      totalPages: parseInt(response.headers['x-wp-totalpages'])
    }
  },
  
  // Get single customer
  getCustomer: async (customerId) => {
    const response = await apiClient.get(`${WC_API_BASE}/customers/${customerId}`)
    return response.data
  },
  
  // Get customer orders
  getCustomerOrders: async (customerId, params = {}) => {
    const response = await apiClient.get(`${WC_API_BASE}/orders`, { 
      params: { customer: customerId, ...params } 
    })
    return response.data
  }
}

// Products API for dashboard stats
export const productsAPI = {
  // Get top selling products
  getTopProducts: async (params = {}) => {
    const response = await apiClient.get(`${WC_API_BASE}/products`, { params })
    return response.data
  },
  
  // Get product stats
  getProductStats: async () => {
    const response = await apiClient.get(`${WC_API_BASE}/reports/products`)
    return response.data
  }
}

// Reports API for dashboard analytics
export const reportsAPI = {
  // Sales reports
  getSalesReport: async (period = 'week') => {
    const response = await apiClient.get(`${WC_API_BASE}/reports/sales`, {
      params: { period }
    })
    return response.data
  },
  
  // Top sellers
  getTopSellers: async (period = 'week') => {
    const response = await apiClient.get(`${WC_API_BASE}/reports/top_sellers`, {
      params: { period }
    })
    return response.data
  }
}

export default apiClient
