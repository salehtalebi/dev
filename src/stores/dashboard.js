/**
 * Dashboard Store for WooCommerce Analytics
 */
import { reportsAPI } from '@/services/api'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export const useDashboardStore = defineStore('dashboard', () => {
  // State
  const salesData = ref({})
  const topProducts = ref([])
  const orderStats = ref({})
  const customerStats = ref({})
  const isLoading = ref(false)

  // Getters
  const monthlyGrowth = computed(() => {
    if (!salesData.value.current || !salesData.value.previous) return 0
    return ((salesData.value.current - salesData.value.previous) / salesData.value.previous) * 100
  })

  // Actions
  const fetchDashboardData = async () => {
    try {
      isLoading.value = true
      
      // Fetch sales report
      const sales = await reportsAPI.getSalesReport('month')
      salesData.value = sales
      
      // Fetch top products
      const products = await reportsAPI.getTopSellers('month')
      topProducts.value = products
      
      // You can add more API calls here for other dashboard widgets
      
    } catch (error) {
      console.error('Dashboard data fetch error:', error)
    } finally {
      isLoading.value = false
    }
  }

  const fetchOrderStats = async () => {
    try {
      const stats = await ordersAPI.getOrderStatistics()
      orderStats.value = stats
    } catch (error) {
      console.error('Order stats fetch error:', error)
    }
  }

  const fetchCustomerStats = async () => {
    try {
      const stats = await reportsAPI.getDashboardAnalytics()
      customerStats.value = stats
    } catch (error) {
      console.error('Customer stats fetch error:', error)
    }
  }

  return {
    // State
    salesData,
    topProducts,
    orderStats,
    customerStats,
    isLoading,
    
    // Getters
    monthlyGrowth,
    
    // Actions
    fetchDashboardData,
    fetchOrderStats,
    fetchCustomerStats
  }
})
