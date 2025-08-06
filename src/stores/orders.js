/**
 * Orders Store - Pinia
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { ordersAPI } from '@/services/api'

export const useOrdersStore = defineStore('orders', () => {
  // State
  const orders = ref([])
  const currentOrder = ref(null)
  const isLoading = ref(false)
  const totalCount = ref(0)
  const totalPages = ref(0)
  const currentPage = ref(1)
  
  // Filters
  const filters = ref({
    status: '',
    customer: '',
    account_manager: '',
    date_from: '',
    date_to: '',
    min_amount: '',
    max_amount: '',
    search: ''
  })

  // Getters
  const filteredOrders = computed(() => {
    let filtered = orders.value
    
    if (filters.value.status) {
      filtered = filtered.filter(order => order.status === filters.value.status)
    }
    
    if (filters.value.search) {
      filtered = filtered.filter(order => 
        order.id.toString().includes(filters.value.search) ||
        order.billing.first_name.toLowerCase().includes(filters.value.search.toLowerCase()) ||
        order.billing.last_name.toLowerCase().includes(filters.value.search.toLowerCase())
      )
    }
    
    return filtered
  })

  const orderStatuses = computed(() => [
    { value: 'pending', text: 'در انتظار پرداخت', color: 'warning' },
    { value: 'processing', text: 'در حال پردازش', color: 'info' },
    { value: 'on-hold', text: 'در انتظار', color: 'secondary' },
    { value: 'completed', text: 'تکمیل شده', color: 'success' },
    { value: 'cancelled', text: 'لغو شده', color: 'error' },
    { value: 'refunded', text: 'بازگشت داده شده', color: 'error' },
    { value: 'failed', text: 'ناموفق', color: 'error' }
  ])

  // Actions
  const fetchOrders = async (params = {}) => {
    try {
      isLoading.value = true
      const queryParams = {
        page: currentPage.value,
        per_page: 20,
        ...filters.value,
        ...params
      }
      
      const response = await ordersAPI.getOrders(queryParams)
      orders.value = response.data
      totalCount.value = response.totalCount
      totalPages.value = response.totalPages
      
    } catch (error) {
      console.error('Fetch orders error:', error)
    } finally {
      isLoading.value = false
    }
  }

  const fetchOrder = async (orderId) => {
    try {
      isLoading.value = true
      const order = await ordersAPI.getOrder(orderId)
      currentOrder.value = order
      return order
    } catch (error) {
      console.error('Fetch order error:', error)
      throw error
    } finally {
      isLoading.value = false
    }
  }

  const updateOrderStatus = async (orderId, status) => {
    try {
      const updatedOrder = await ordersAPI.updateOrderStatus(orderId, status)
      
      // Update in list
      const index = orders.value.findIndex(order => order.id === orderId)
      if (index !== -1) {
        orders.value[index] = updatedOrder
      }
      
      // Update current order if it's the same
      if (currentOrder.value?.id === orderId) {
        currentOrder.value = updatedOrder
      }
      
      return updatedOrder
    } catch (error) {
      console.error('Update order status error:', error)
      throw error
    }
  }

  const setFilters = (newFilters) => {
    filters.value = { ...filters.value, ...newFilters }
    currentPage.value = 1
  }

  const clearFilters = () => {
    filters.value = {
      status: '',
      customer: '',
      account_manager: '',
      date_from: '',
      date_to: '',
      min_amount: '',
      max_amount: '',
      search: ''
    }
    currentPage.value = 1
  }

  const setPage = (page) => {
    currentPage.value = page
  }

  return {
    // State
    orders,
    currentOrder,
    isLoading,
    totalCount,
    totalPages,
    currentPage,
    filters,
    
    // Getters
    filteredOrders,
    orderStatuses,
    
    // Actions
    fetchOrders,
    fetchOrder,
    updateOrderStatus,
    setFilters,
    clearFilters,
    setPage
  }
})
