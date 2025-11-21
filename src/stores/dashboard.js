/**
 * Dashboard Store for Sales Dashboard
 */
import { useAPI } from '@/composables/useAPI'
import { defineStore } from 'pinia'

export const useDashboardStore = defineStore('dashboard', {
  state: () => ({
    stats: {
      // Core totals
      totalOrders: 0,
      totalRevenue: 0,
      totalCustomers: 0,
      averageOrderValue: 0,
      // Today metrics
      todayOrders: 0,
      todayRevenue: 0,
      newCustomers: 0,
      // Growth metrics
      ordersGrowth: 0,
      revenueGrowth: 0,
      monthlyGrowth: 0,
      customersGrowth: 0,
    },
    recentOrders: [],
    topProducts: [],
    monthlyRevenue: [],
    monthlyRevenueComparison: [], // For comparison data
    revenuePeriodLabel: '', // Current period label (e.g., "October 2024")
    revenueComparePeriodLabel: '', // Compare period label (e.g., "January 2024")
    salesComparison: {
      currentMonth: 0,
      previousMonth: 0,
      growth: 0,
    },
    revenueComparisonSummary: null, // Summary for chart comparison
    managerPerformance: [],
    managerPerformanceComparison: [], // For comparison data
    managerPeriodLabel: '', // Current period label
    managerComparePeriodLabel: '', // Compare period label
    loading: {
      stats: false,
      orders: false,
      products: false,
      revenue: false,
      comparison: false,
      performance: false,
      orderStatistics: false,
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
      filterType: 'year', // 'month', 'year'
      filterValue: null, // specific month (YYYY-MM) or year (YYYY)
      compare: false,
      compareFilterType: null, // 'month', 'year' (for custom comparison)
      compareFilterValue: null, // specific month (YYYY-MM) or year (YYYY) for comparison
    },
    // Products chart specific filters
    productsFilters: {
      filterType: 'year', // 'month', 'year'
      filterValue: null, // specific month (YYYY-MM) or year (YYYY)
    },
    // Order statistics specific state
    orderStatistics: {
      totalOrders: 0,
      totalRevenue: 0,
      categories: [],
      compareTotalOrders: 0,
      compareTotalRevenue: 0,
      comparisonSummary: null,
      periodLabel: '',
      comparePeriodLabel: '',
    },
    // Order statistics filters
    orderStatisticsFilters: {
      filterType: 'year', // 'month', 'year', 'date-range'
      filterValue: null,
      startDate: null,
      endDate: null,
      compare: false,
      compareFilterType: null,
      compareFilterValue: null,
      compareStartDate: null,
      compareEndDate: null,
    },
    // Manager performance filters
    managerPerformanceFilters: {
      filterType: 'year', // 'month', 'year', 'date-range'
      filterValue: null,
      startDate: null,
      endDate: null,
      compare: false,
      compareFilterType: null,
      compareFilterValue: null,
      compareStartDate: null,
      compareEndDate: null,
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
      const {
        getAnalytics,
        getOrders,
        getTopProducts,
        getMonthlyRevenue,
        getSalesComparison,
        getManagerPerformance,
        getOrderStatistics
      } = useAPI()
      return {
        getAnalytics,
        getOrders,
        getTopProducts,
        getMonthlyRevenue,
        getSalesComparison,
        getManagerPerformance,
        getOrderStatistics
      }
    },

    setFilters(filters) {
      this.filters = { ...this.filters, ...filters }
    },

    setRevenueFilters(filters) {
      this.revenueFilters = { ...this.revenueFilters, ...filters }
    },

    setProductsFilters(filters) {
      this.productsFilters = { ...this.productsFilters, ...filters }
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
          // Totals (fallback to today metrics if period-specific totals absent)
          totalOrders: stats.total_orders ?? stats.todayOrders ?? 0,
          // Use numeric coercion to avoid string values
          totalRevenue: stats.total_revenue ?? stats.todayRevenue ?? 0,
          totalCustomers: stats.total_customers ?? stats.newCustomers ?? 0,
          averageOrderValue: stats.averageOrderValue ?? stats.average_order_value ?? 0,
          // Today metrics
          todayOrders: stats.todayOrders ?? 0,
          todayRevenue: stats.todayRevenue ?? 0,
          newCustomers: stats.newCustomers ?? 0,
          // Growth metrics (fallback chain)
          ordersGrowth: stats.ordersGrowth ?? stats.growth_percentage ?? 0,
          revenueGrowth: stats.revenueGrowth ?? stats.growth_percentage ?? 0,
          // Legacy / compatibility fields (will often be 0 if not provided)
          monthlyGrowth: stats.monthly_growth ?? stats.growth_percentage ?? 0,
          customersGrowth: stats.customers_growth ?? 0,
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

    async fetchTopProducts(useProductsFilters = true) {
      this.loading.products = true

      try {
        const api = this._getAPI()
        let params = {}

        // Use products-specific filters if enabled
        if (useProductsFilters) {
          params = {
            filterType: this.productsFilters.filterType,
            filterValue: this.productsFilters.filterValue,
          }
          console.log('[dashboard] fetchTopProducts with filters:', params)
        } else {
          params = this.buildAPIParams()
        }

        const response = await api.getTopProducts(params)
        console.log('[dashboard] fetchTopProducts response:', response)

        // Support different possible response shapes
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

        // Normalize field names
        const products = productsRaw.map(p => ({
          product_id: p.product_id || p.id,
          product_name: p.product_name || p.name || `Product ${p.product_id || p.id}`,
          total_sold: p.total_sold || p.total_quantity || p.quantity || 0,
          total_revenue: p.total_revenue || p.revenue || p.total_revenue_usd || p.total || 0,
          category_name: p.category_name || p.category || p.product_category,
          price: p.price || p.unit_price || 0,
        }))

        this.topProducts = products

        console.debug('[dashboard] topProducts fetched:', {
          count: products.length,
          sample: products.slice(0, 3),
        })
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
            filterType: this.revenueFilters.filterType,
            filterValue: this.revenueFilters.filterValue,
            compare: this.revenueFilters.compare,
            compareFilterType: this.revenueFilters.compareFilterType,
            compareFilterValue: this.revenueFilters.compareFilterValue,
          }
          console.log('[dashboard] fetchMonthlyRevenue with params:', params)
        } else {
          params = this.buildAPIParams()
        }

        const response = await api.getMonthlyRevenue(params)

        // Handle response structure
        this.monthlyRevenue = response.data || []
        this.revenuePeriodLabel = response.period_label || ''

        // Store comparison data if present
        if (response.compare_data) {
          this.monthlyRevenueComparison = response.compare_data
          this.revenueComparisonSummary = response.comparison_summary || null
          this.revenueComparePeriodLabel = response.compare_period_label || ''
        } else {
          this.monthlyRevenueComparison = []
          this.revenueComparisonSummary = null
          this.revenueComparePeriodLabel = ''
        }

        console.debug('[dashboard] monthlyRevenue fetched:', {
          count: this.monthlyRevenue.length,
          hasComparison: !!response.compare_data,
          summary: this.revenueComparisonSummary,
          periodLabel: this.revenuePeriodLabel,
          comparePeriodLabel: this.revenueComparePeriodLabel
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

    async fetchManagerPerformance(refresh = false) {
      this.loading.performance = true

      try {
        const api = this._getAPI()
        const filters = this.managerPerformanceFilters

        // Build parameters based on filters
        const params = {
          filterType: filters.filterType || 'year',
          filterValue: filters.filterValue || new Date().getFullYear().toString(),
        }

        // Add date range if specified
        if (filters.filterType === 'date-range' && filters.startDate && filters.endDate) {
          params.startDate = filters.startDate
          params.endDate = filters.endDate
        }

        // Add comparison parameters if enabled
        if (filters.compare) {
          params.compare = true
          params.compareFilterType = filters.compareFilterType
          params.compareFilterValue = filters.compareFilterValue

          if (filters.compareFilterType === 'date-range' && filters.compareStartDate && filters.compareEndDate) {
            params.compareStartDate = filters.compareStartDate
            params.compareEndDate = filters.compareEndDate
          }
        }

        const response = await api.getManagerPerformance(params)

        this.managerPerformance = response.data || []
        this.managerPeriodLabel = response.period_label || ''

        if (filters.compare && response.comparison_data) {
          this.managerPerformanceComparison = response.comparison_data || []
          this.managerComparePeriodLabel = response.compare_period_label || ''
        } else {
          this.managerPerformanceComparison = []
          this.managerComparePeriodLabel = ''
        }
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch manager performance:', error)
        this.managerPerformance = []
      } finally {
        this.loading.performance = false
      }
    },

    // Set manager performance filters
    setManagerPerformanceFilters(filters) {
      this.managerPerformanceFilters = {
        ...this.managerPerformanceFilters,
        ...filters,
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

    async fetchOrderStatistics() {
      this.loading.orderStatistics = true

      try {
        const api = this._getAPI()
        const params = {
          filterType: this.orderStatisticsFilters.filterType,
          filterValue: this.orderStatisticsFilters.filterValue,
          startDate: this.orderStatisticsFilters.startDate,
          endDate: this.orderStatisticsFilters.endDate,
          compare: this.orderStatisticsFilters.compare,
          compareFilterType: this.orderStatisticsFilters.compareFilterType,
          compareFilterValue: this.orderStatisticsFilters.compareFilterValue,
          compareStartDate: this.orderStatisticsFilters.compareStartDate,
          compareEndDate: this.orderStatisticsFilters.compareEndDate,
        }

        console.log('[dashboard] fetchOrderStatistics with params:', params)

        const response = await api.getOrderStatistics(params)

        // Update order statistics state
        this.orderStatistics = {
          totalOrders: response.total_orders || 0,
          totalRevenue: response.total_revenue || 0,
          categories: response.categories || [],
          compareTotalOrders: response.compare_total_orders || 0,
          compareTotalRevenue: response.compare_total_revenue || 0,
          comparisonSummary: response.comparison_summary || null,
          periodLabel: response.period_label || '',
          comparePeriodLabel: response.compare_period_label || '',
        }

        console.debug('[dashboard] orderStatistics fetched:', {
          totalOrders: this.orderStatistics.totalOrders,
          categoriesCount: this.orderStatistics.categories.length,
          hasComparison: !!response.comparison_summary,
          periodLabel: this.orderStatistics.periodLabel,
          comparePeriodLabel: this.orderStatistics.comparePeriodLabel
        })
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch order statistics:', error)
      } finally {
        this.loading.orderStatistics = false
      }
    },
  },
})
