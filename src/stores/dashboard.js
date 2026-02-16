/**
 * Dashboard Store for Sales Dashboard
 */
import salesDashboardAPI from '@/services/api'
import { defineStore } from 'pinia'

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    stats: {
      totalOrders: 0,
      totalRevenue: 0,
      totalCustomers: 0,
      averageOrderValue: 0,
      monthlyGrowth: 0,
      customersGrowth: 0,
      ordersGrowth: 0,
    },
    recentOrders: [],
    topProducts: [],
    monthlyRevenue: [],
    salesComparison: {
      currentMonth: 0,
      previousMonth: 0,
      growth: 0,
    },
    managerPerformance: [],
    loading: {
      stats: false,
      orders: false,
      products: false,
      revenue: false,
      comparison: false,
      performance: false,
    },
    error: null,
    filters: {
      dateRange: 'last_month',
      accountManager: null,
      customStartDate: null,
      customEndDate: null,
    },
  }),

  getters: {
    isLoading: (state) => Object.values(state.loading).some(loading => loading),
    revenueGrowthPercentage: (state) => {
      if (state.salesComparison.previousMonth === 0) return 0
      return ((state.salesComparison.currentMonth - state.salesComparison.previousMonth) / state.salesComparison.previousMonth) * 100
    },
    topProductsByRevenue: (state) => state.topProducts.slice(0, 5),
    hasData: (state) => state.stats.totalOrders > 0,
  },

  actions: {
    setFilters(filters) {
      this.filters = { ...this.filters, ...filters }
    },

    async fetchDashboardStats(refresh = false) {
      if (this.loading.stats && !refresh) return

      this.loading.stats = true
      this.error = null

      try {
        const params = this.buildAPIParams()
        const stats = await salesDashboardAPI.getAnalytics(params)
        
        this.stats = {
          totalOrders: stats.total_orders || 0,
          totalRevenue: stats.total_revenue || 0,
          totalCustomers: stats.total_customers || 0,
          averageOrderValue: stats.average_order_value || 0,
          monthlyGrowth: stats.monthly_growth || 0,
          customersGrowth: stats.customers_growth || 0,
          ordersGrowth: stats.orders_growth || 0,
        }
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch dashboard stats:', error)
      } finally {
        this.loading.stats = false
      }
    },

    async fetchRecentOrders(limit = 10) {
      this.loading.orders = true

      try {
        const params = {
          ...this.buildAPIParams(),
          per_page: limit,
          orderby: 'date',
          order: 'desc',
        }
        
        const response = await salesDashboardAPI.getOrders(params)
        this.recentOrders = response.data || []
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch recent orders:', error)
      } finally {
        this.loading.orders = false
      }
    },

    async fetchTopProducts() {
      this.loading.products = true

      try {
        const params = this.buildAPIParams()
        const response = await salesDashboardAPI.getTopProducts(params)
        this.topProducts = response.data || []
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch top products:', error)
      } finally {
        this.loading.products = false
      }
    },

    async fetchMonthlyRevenue() {
      this.loading.revenue = true

      try {
        const params = this.buildAPIParams()
        const response = await salesDashboardAPI.getMonthlyRevenue(params)
        this.monthlyRevenue = response.data || []
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch monthly revenue:', error)
      } finally {
        this.loading.revenue = false
      }
    },

    async fetchSalesComparison() {
      this.loading.comparison = true

      try {
        const params = this.buildAPIParams()
        const response = await salesDashboardAPI.getSalesComparison(params)
        
        this.salesComparison = {
          currentMonth: response.current_month || 0,
          previousMonth: response.previous_month || 0,
          growth: response.growth_percentage || 0,
        }
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch sales comparison:', error)
      } finally {
        this.loading.comparison = false
      }
    },

    async fetchManagerPerformance() {
      this.loading.performance = true

      try {
        const params = this.buildAPIParams()
        const response = await salesDashboardAPI.getManagerPerformance(params)
        this.managerPerformance = response.data || []
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch manager performance:', error)
      } finally {
        this.loading.performance = false
      }
    },

    async fetchAllDashboardData(refresh = false) {
      await Promise.all([
        this.fetchDashboardStats(refresh),
        this.fetchRecentOrders(),
        this.fetchTopProducts(),
        this.fetchMonthlyRevenue(),
        this.fetchSalesComparison(),
        this.fetchManagerPerformance(),
      ])
    },

    async refreshData() {
      await this.fetchAllDashboardData(true)
    },

    buildAPIParams() {
      const params = {}

      // Date range handling
      if (this.filters.dateRange && this.filters.dateRange !== 'custom') {
        params.date_range = this.filters.dateRange
      } else if (this.filters.customStartDate && this.filters.customEndDate) {
        params.start_date = this.filters.customStartDate
        params.end_date = this.filters.customEndDate
      }

      // Account manager filter
      if (this.filters.accountManager) {
        params.account_manager = this.filters.accountManager
      }

      return params
    },

    clearError() {
      this.error = null
    },

    resetFilters() {
      this.filters = {
        dateRange: 'last_month',
        accountManager: null,
        customStartDate: null,
        customEndDate: null,
      }
    },
  },
})
