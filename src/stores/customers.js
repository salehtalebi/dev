/**
 * Customers Store - Pinia
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { customersAPI } from '@/services/api'

export const useCustomersStore = defineStore('customers', () => {
  // State
  const customers = ref([])
  const currentCustomer = ref(null)
  const customerOrders = ref([])
  const customerStats = ref(null)
  const isLoading = ref(false)
  const totalCount = ref(0)
  const totalPages = ref(0)
  const currentPage = ref(1)
  
  // Filters
  const filters = ref({
    account_manager: '',
    search: '',
    date_registered_from: '',
    date_registered_to: '',
    total_spent_min: '',
    total_spent_max: ''
  })

  // Getters
  const filteredCustomers = computed(() => {
    let filtered = customers.value
    
    if (filters.value.search) {
      filtered = filtered.filter(customer => 
        customer.first_name.toLowerCase().includes(filters.value.search.toLowerCase()) ||
        customer.last_name.toLowerCase().includes(filters.value.search.toLowerCase()) ||
        customer.email.toLowerCase().includes(filters.value.search.toLowerCase())
      )
    }
    
    return filtered
  })

  // Actions
  const fetchCustomers = async (params = {}) => {
    try {
      isLoading.value = true
      const queryParams = {
        page: currentPage.value,
        per_page: 20,
        ...filters.value,
        ...params
      }
      
      const response = await customersAPI.getCustomers(queryParams)
      customers.value = response.data
      totalCount.value = response.totalCount
      totalPages.value = response.totalPages
      
    } catch (error) {
      console.error('Fetch customers error:', error)
    } finally {
      isLoading.value = false
    }
  }

  const fetchCustomer = async (customerId) => {
    try {
      isLoading.value = true
      const customer = await customersAPI.getCustomer(customerId)
      currentCustomer.value = customer
      return customer
    } catch (error) {
      console.error('Fetch customer error:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const fetchCustomerOrders = async (customerId, params = {}) => {
    try {
      const orders = await customersAPI.getCustomerOrders(customerId, params)
      customerOrders.value = orders
      
      // Calculate customer stats
      calculateCustomerStats(orders)
      
      return orders
    } catch (error) {
      console.error('Fetch customer orders error:', error)
      throw error
    }
  }

  const calculateCustomerStats = (orders) => {
    if (!orders || orders.length === 0) {
      customerStats.value = null
      return
    }

    const now = new Date()
    const currentMonth = now.getMonth()
    const currentYear = now.getFullYear()
    const lastMonth = currentMonth === 0 ? 11 : currentMonth - 1
    const lastMonthYear = currentMonth === 0 ? currentYear - 1 : currentYear

    // Current month orders
    const currentMonthOrders = orders.filter(order => {
      const orderDate = new Date(order.date_created)
      return orderDate.getMonth() === currentMonth && orderDate.getFullYear() === currentYear
    })

    // Last month orders
    const lastMonthOrders = orders.filter(order => {
      const orderDate = new Date(order.date_created)
      return orderDate.getMonth() === lastMonth && orderDate.getFullYear() === lastMonthYear
    })

    // Calculate totals
    const currentMonthTotal = currentMonthOrders.reduce((sum, order) => sum + parseFloat(order.total), 0)
    const lastMonthTotal = lastMonthOrders.reduce((sum, order) => sum + parseFloat(order.total), 0)
    const totalSpent = orders.reduce((sum, order) => sum + parseFloat(order.total), 0)

    // Calculate growth percentage
    const growthPercentage = lastMonthTotal > 0 
      ? ((currentMonthTotal - lastMonthTotal) / lastMonthTotal) * 100
      : currentMonthTotal > 0 ? 100 : 0

    customerStats.value = {
      totalOrders: orders.length,
      totalSpent,
      currentMonthTotal,
      lastMonthTotal,
      growthPercentage,
      lastOrderDate: orders.length > 0 ? orders[0].date_created : null,
      averageOrderValue: totalSpent / orders.length || 0
    }
  }

  const setFilters = (newFilters) => {
    filters.value = { ...filters.value, ...newFilters }
    currentPage.value = 1
  }

  const clearFilters = () => {
    filters.value = {
      account_manager: '',
      search: '',
      date_registered_from: '',
      date_registered_to: '',
      total_spent_min: '',
      total_spent_max: ''
    }
    currentPage.value = 1
  }

  const setPage = (page) => {
    currentPage.value = page
  }

  return {
    // State
    customers,
    currentCustomer,
    customerOrders,
    customerStats,
    isLoading,
    totalCount,
    totalPages,
    currentPage,
    filters,
    
    // Getters
    filteredCustomers,
    
    // Actions
    fetchCustomers,
    fetchCustomer,
    fetchCustomerOrders,
    setFilters,
    clearFilters,
    setPage
  }
})
