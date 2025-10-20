/**
 * Dashboard Store for Sales Dashboard
 */
import { useAPI } from '@/composables/useAPI'
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
    monthlyRevenueComparison: [], // For comparison data
    salesComparison: {
      currentMonth: 0,
      previousMonth: 0,
      growth: 0,
    },
    revenueComparisonSummary: null, // Summary for chart comparison
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
      dateRange: 'year',
      accountManager: null,
      customStartDate: null,
      customEndDate: null,
    },
    // Revenue chart specific filters
    revenueFilters: {
      filterType: 'year', // 'month', 'year', 'range'
      filterValue: null, // specific month (YYYY-MM) or year (YYYY)
      startDate: null,
      endDate: null,
      compare: false,
      compareWith: 'previous', // 'previous', 'custom'
      compareStart: null,
      compareEnd: null,
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
    // Initialize API instance
    _getAPI() {
      const { getAnalytics, getOrders, getTopProducts, getMonthlyRevenue, getSalesComparison, getManagerPerformance } = useAPI()
      return {
        getAnalytics,
        getOrders,
        getTopProducts,
        getMonthlyRevenue,
        getSalesComparison,
        getManagerPerformance
      }
    },

    setFilters(filters) {
      this.filters = { ...this.filters, ...filters }
    },

    setRevenueFilters(filters) {
      this.revenueFilters = { ...this.revenueFilters, ...filters }
    },

    async fetchDashboardStats(refresh = false) {
      if (this.loading.stats && !refresh) return

      this.loading.stats = true
      this.error = null

      try {
        console.log('Fetching dashboard stats...')
        const params = this.buildAPIParams()
        const api = this._getAPI()
        const stats = await api.getAnalytics(params)

        this.stats = {
          totalOrders: stats.total_orders || 0,
          totalRevenue: stats.total_revenue || 0,
          totalCustomers: stats.total_customers || 0,
          averageOrderValue: stats.average_order_value || 0,
          monthlyGrowth: stats.monthly_growth || 0,
          customersGrowth: stats.customers_growth || 0,
          ordersGrowth: stats.orders_growth || 0,
        }
        console.log('Dashboard stats fetched successfully')
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
        // Always fetch recent orders ignoring long date filters (requirement: show latest regardless of year)
        const params = {
          per_page: limit,
          orderby: 'date',
          order: 'desc'
        }

        const api = this._getAPI()
        const response = await api.getOrders(params)
        // response shape: { data: [...], headers }
        let orders = []
        if (Array.isArray(response.data)) {
          orders = response.data
        } else if (Array.isArray(response.data?.data)) {
          orders = response.data.data
        }
        this.recentOrders = orders
        console.debug('[dashboard] recentOrders fetched:', { count: orders.length, sample: orders.slice(0, 2) })
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
        const api = this._getAPI()
        const response = await api.getTopProducts(params)
        // Support different possible response shapes: array, { data: [] }, { products: [] }, { top_products: [] }
        let productsRaw = []
        if (Array.isArray(response)) {
          productsRaw = response
        } else if (Array.isArray(response?.data)) {
          productsRaw = response.data
        } else if (Array.isArray(response?.products)) {
          productsRaw = response.products
        } else if (Array.isArray(response?.top_products)) {
          productsRaw = response.top_products
        }
        // Normalize field names to those expected by analytics components
        const products = productsRaw.map(p => ({
          product_id: p.product_id || p.id,
          product_name: p.product_name || p.name || `Product ${p.product_id || p.id}`,
          total_sold: p.total_sold || p.total_quantity || p.quantity || 0,
          total_revenue: p.total_revenue || p.revenue || p.total_revenue_usd || p.total || 0,
          category_name: p.category_name || p.category || p.product_category,
          price: p.price || p.unit_price || 0
        }))
        this.topProducts = products
        console.debug('[dashboard] topProducts fetched:', { count: products.length, sample: products.slice(0, 3) })
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch top products:', error)
      } finally {
        this.loading.products = false
      }
    },

    async fetchMonthlyRevenue(useRevenueFilters = true) {
      this.loading.revenue = true

      try {
        const api = this._getAPI()
        let params = {}

        // Use revenue-specific filters if enabled
        if (useRevenueFilters) {
          params = {
            filter_type: this.revenueFilters.filterType,
            filter_value: this.revenueFilters.filterValue,
            start_date: this.revenueFilters.startDate,
            end_date: this.revenueFilters.endDate,
            compare: this.revenueFilters.compare,
            compare_with: this.revenueFilters.compareWith,
            compare_start: this.revenueFilters.compareStart,
            compare_end: this.revenueFilters.compareEnd,
          }
        } else {
          params = this.buildAPIParams()
        }

        const response = await api.getMonthlyRevenue(params)

        // Handle response structure
        this.monthlyRevenue = response.data || []

        // Store comparison data if present
        if (response.compare_data) {
          this.monthlyRevenueComparison = response.compare_data
          this.revenueComparisonSummary = response.comparison_summary || null
        } else {
          this.monthlyRevenueComparison = []
          this.revenueComparisonSummary = null
        }

        console.debug('[dashboard] monthlyRevenue fetched:', {
          count: this.monthlyRevenue.length,
          hasComparison: !!response.compare_data,
          summary: this.revenueComparisonSummary
        })
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
        const api = this._getAPI()
        const response = await api.getSalesComparison(params)

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
        const api = this._getAPI()
        const response = await api.getManagerPerformance(params)
        this.managerPerformance = response.data || []
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch manager performance:', error)
      } finally {
        this.loading.performance = false
      }
    },

    async fetchAllDashboardData(refresh = false) {
      // To prevent requests from being cancelled, we send important requests first
      try {
        // Phase 1: Main statistics
        await this.fetchDashboardStats(refresh)

        // Phase 2: Less important requests
        await Promise.allSettled([
          this.fetchTopProducts(),
          this.fetchMonthlyRevenue(),
          this.fetchSalesComparison(),
        ])

        // Phase 3: Heavy requests
        await Promise.allSettled([
          this.fetchRecentOrders(),
          this.fetchManagerPerformance(),
        ])
      } catch (error) {
        console.error('Error in fetchAllDashboardData:', error)
        this.error = error.message
      }
    },

    async refreshData() {
      await this.fetchAllDashboardData(true)
    },

    buildAPIParams() {
      const params = {}

      // Date range handling
      if (this.filters.dateRange && this.filters.dateRange !== 'custom') {
        // Treat 'year' (and 'all') as full history (no param) per requirement to ignore year/month restrictions
        if (!['year', 'all'].includes(this.filters.dateRange)) {
          params.date_range = this.filters.dateRange
        }
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
