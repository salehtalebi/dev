/**
 * Dashboard Store for WooCommerce Analytics
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { reportsAPI, productsAPI } from '@/services/api'

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
    // Implementation for order statistics
  }

  const fetchCustomerStats = async () => {
    // Implementation for customer statistics
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
