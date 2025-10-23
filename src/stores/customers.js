import { useAPI } from '@/composables/useAPI'
import { defineStore } from 'pinia'

export const useCustomersStore = defineStore('customers', {
  state: () => ({
    customers: [],
    currentCustomer: null,
    customerOrders: [],
    customerOrdersPagination: {
      page: 1,
      perPage: 20,
      totalOrders: 0,
      pages: 1,
    },
    customerStats: null,
    customerStatsFiltered: null, // Statistics with filters applied
    customerCategories: null, // Top/Bottom categories
    customerStatsFilters: {
      filterType: 'year',
      filterValue: null,
      startDate: null,
      endDate: null,
      compare: false,
      compareFilterType: null,
      compareFilterValue: null,
      compareStartDate: null,
      compareEndDate: null,
    },
    totalCustomers: 0,
    loading: {
      list: false,
      detail: false,
      update: false,
      stats: false,
      categories: false,
    },
    error: null,
    pagination: {
      page: 1,
      perPage: 20,
      totalPages: 1,
      hasNext: false,
      hasPrev: false,
    },
    filters: {
      search: '',
      role: '',
      accountManager: '',
      dateFrom: '',
      dateTo: '',
    },
    sorting: {
      orderby: 'registered_date',
      order: 'desc',
    },
  }),

  getters: {
    isLoading: (state) => Object.values(state.loading).some(loading => loading),

    filteredCustomers: (state) => {
      let filtered = [...state.customers]

      if (state.filters.search) {
        const search = state.filters.search.toLowerCase()
        filtered = filtered.filter(customer =>
          customer.first_name?.toLowerCase().includes(search) ||
          customer.last_name?.toLowerCase().includes(search) ||
          customer.email?.toLowerCase().includes(search) ||
          customer.username?.toLowerCase().includes(search)
        )
      }

      return filtered
    },

    currentPage: (state) => state.pagination.page,
    totalPages: (state) => state.pagination.totalPages,
    hasNextPage: (state) => state.pagination.hasNext,
    hasPrevPage: (state) => state.pagination.hasPrev,
  },

  actions: {
    // Initialize API instance
    _getAPI() {
      const {
        getCustomers,
        getCustomer,
        getCustomerStatistics,
        getCustomerStatisticsWithFilter,
        getCustomerCategories,
        getOrders
      } = useAPI()
      return {
        getCustomers,
        getCustomer,
        getCustomerStatistics,
        getCustomerStatisticsWithFilter,
        getCustomerCategories,
        getOrders
      }
    },

    async fetchCustomers(page = 1, refresh = false) {
      if (this.loading.list && !refresh) return

      this.loading.list = true
      this.error = null

      try {
        const params = {
          page,
          per_page: this.pagination.perPage,
          orderby: this.sorting.orderby,
          order: this.sorting.order,
          ...this.buildAPIParams(),
        }

        const api = this._getAPI()
        const { data, headers } = await api.getCustomers(params)
        console.debug('[customers] fetchCustomers params:', params)
        const items = Array.isArray(data) ? data : (data?.data || [])

        if (refresh || page === 1) {
          this.customers = items
        } else {
          this.customers = items
        }

        // Prefer JSON body meta; fallback to WP headers if needed
        let total = Number.parseInt(data?.total ?? 'NaN')
        if (!Number.isFinite(total)) {
          const headerTotal = headers?.get?.('x-wp-total') || headers?.get?.('X-WP-Total')
          total = Number.parseInt(headerTotal ?? '0')
        }

        let totalPages = Number.parseInt(data?.pages ?? 'NaN')
        if (!Number.isFinite(totalPages)) {
          const headerPages = headers?.get?.('x-wp-totalpages') || headers?.get?.('X-WP-TotalPages')
          totalPages = Number.parseInt(headerPages ?? '1')
        }

        this.totalCustomers = Number.isFinite(total) ? total : 0
        this.pagination.totalPages = Number.isFinite(totalPages) ? totalPages : 1
        this.pagination.hasNext = page < this.pagination.totalPages
        this.pagination.hasPrev = page > 1

        // Adjust current page if it exceeds total pages
        if (this.pagination.page > this.pagination.totalPages) {
          this.pagination.page = this.pagination.totalPages || 1
        }

        if (this.pagination.page !== page) {
          this.pagination.page = page
        }
        console.debug('[customers] totals:', {
          total: this.totalCustomers,
          totalPages: this.pagination.totalPages,
          page: this.pagination.page,
          perPage: this.pagination.perPage,
        })
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customers:', error)
        // Add mock data for testing
        this.customers = [
          {
            id: 1,
            first_name: 'Ahmad',
            last_name: 'Mohammad',
            email: 'ahmad@test.com',
            username: 'ahmad',
            date_created: '2023-01-15T10:00:00',
            orders_count: 5,
            total_spent: '250000'
          },
          {
            id: 2,
            first_name: 'Sarah',
            last_name: 'Johnson',
            email: 'sarah@test.com',
            username: 'sarah',
            date_created: '2023-02-20T14:30:00',
            orders_count: 3,
            total_spent: '180000'
          }
        ]
        this.totalCustomers = 2
      } finally {
        this.loading.list = false
      }
    },

    async fetchCustomer(customerId) {
      this.loading.detail = true
      this.error = null

      try {
        const api = this._getAPI()
        const customer = await api.getCustomer(customerId)
        this.currentCustomer = customer
        return customer
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customer:', error)
        // Mock data for testing
        this.currentCustomer = {
          id: customerId,
          first_name: 'Ahmad',
          last_name: 'Mohammad',
          email: 'ahmad@test.com',
          username: 'ahmad',
          date_created: '2023-01-15T10:00:00',
          orders_count: 5,
          total_spent: '250000',
          phone: '09123456789',
          billing: {
            first_name: 'Ahmad',
            last_name: 'Mohammad',
            address_1: 'Main Street',
            city: 'New York',
            country: 'US'
          }
        }
        return this.currentCustomer
      } finally {
        this.loading.detail = false
      }
    },

    async fetchCustomerOrders(customerId, page = 1, perPage = 20) {
      this.loading.detail = true
      this.error = null

      try {
        const params = {
          customer: customerId,
          page,
          per_page: perPage,
          orderby: 'date',
          order: 'desc'
        }
        const api = this._getAPI()
        const { data, headers } = await api.getOrders(params)
        console.debug('[customers] fetchCustomerOrders params:', params)
        this.customerOrders = Array.isArray(data) ? data : (data?.data || [])

        // Derive totals from body, fallback to WP headers
        let total = Number.parseInt(data?.total ?? 'NaN')
        if (!Number.isFinite(total)) {
          const headerTotal = headers?.get?.('x-wp-total') || headers?.get?.('X-WP-Total')
          total = Number.parseInt(headerTotal ?? '0')
        }
        let pages = Number.parseInt(data?.pages ?? 'NaN')
        if (!Number.isFinite(pages)) {
          const headerPages = headers?.get?.('x-wp-totalpages') || headers?.get?.('X-WP-TotalPages')
          pages = Number.parseInt(headerPages ?? '1')
        }

        this.customerOrdersPagination = {
          page,
          perPage,
          totalOrders: Number.isFinite(total) ? total : 0,
          pages: Number.isFinite(pages) ? pages : 1,
        }
        console.debug('[customers] customerOrders totals:', this.customerOrdersPagination)
        return { data: this.customerOrders, total: this.customerOrdersPagination.totalOrders, pages: this.customerOrdersPagination.pages }
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customer orders:', error)
        // Mock data for testing
        this.customerOrders = [
          {
            id: 123,
            status: 'completed',
            total: '125000',
            date_created: '2023-03-15T12:00:00',
            line_items: [
              { name: 'Sample Product', quantity: 2, price: 62500 }
            ]
          }
        ]
        this.customerOrdersPagination = {
          page,
          perPage,
          totalOrders: this.customerOrders.length,
          pages: 1,
        }
        return { data: this.customerOrders, total: this.customerOrdersPagination.totalOrders, pages: this.customerOrdersPagination.pages }
      } finally {
        this.loading.detail = false
      }
    },

    async fetchCustomerStatistics(customerId) {
      this.loading.detail = true
      this.error = null

      try {
        const api = this._getAPI()
        const stats = await api.getCustomerStatistics(customerId)
        this.customerStats = stats
        return stats
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customer statistics:', error)
        // Mock stats for testing
        this.customerStats = {
          total_orders: 5,
          total_spent: 250000,
          average_order_value: 50000,
          growth_percentage: 15.5,
          last_order_date: '2023-03-15T12:00:00',
          monthly_orders: [
            { month: '2024-01', orders_count: 2, total_spent: 100000 },
            { month: '2024-02', orders_count: 3, total_spent: 150000 }
          ]
        }
        return this.customerStats
      } finally {
        this.loading.detail = false
      }
    },

    setFilters(filters) {
      // Normalize snake_case fields coming from UI
      const normalized = { ...filters }
      if (Object.prototype.hasOwnProperty.call(filters, 'account_manager')) normalized.accountManager = filters.account_manager
      // UI may pass date_registered_*; map to dateFrom/dateTo used by API params
      if (Object.prototype.hasOwnProperty.call(filters, 'date_registered_from')) normalized.dateFrom = filters.date_registered_from
      if (Object.prototype.hasOwnProperty.call(filters, 'date_registered_to')) normalized.dateTo = filters.date_registered_to
      if (Object.prototype.hasOwnProperty.call(filters, 'date_from')) normalized.dateFrom = filters.date_from
      if (Object.prototype.hasOwnProperty.call(filters, 'date_to')) normalized.dateTo = filters.date_to
      if (Object.prototype.hasOwnProperty.call(filters, 'search')) normalized.search = filters.search
      if (Object.prototype.hasOwnProperty.call(filters, 'role')) normalized.role = filters.role
      if (Object.prototype.hasOwnProperty.call(filters, 'dateRange')) normalized.dateRange = filters.dateRange

      this.filters = { ...this.filters, ...normalized }
      this.pagination.page = 1
    },

    setPage(page) {
      this.pagination.page = page
    },

    setPerPage(perPage) {
      this.pagination.perPage = perPage
      this.pagination.page = 1
    },

    setSorting(sorting) {
      this.sorting = { ...this.sorting, ...sorting }
    },

    updatePagination(response, page) {
      this.totalCustomers = response.total || response.length || 0
      this.pagination = {
        page: page,
        perPage: this.pagination.perPage,
        totalPages: Math.ceil((response.total || response.length || 0) / this.pagination.perPage),
        hasNext: (response.total || response.length || 0) > (page * this.pagination.perPage),
        hasPrev: page > 1,
      }
    },

    buildAPIParams() {
      const params = {}

      if (this.filters.search) params.search = this.filters.search
      if (this.filters.role) params.role = this.filters.role
      if (this.filters.accountManager) params.account_manager = this.filters.accountManager
      // Support both normalized and raw UI keys (defensive)
      const dateFrom = this.filters.dateFrom || this.filters.date_registered_from
      const dateTo = this.filters.dateTo || this.filters.date_registered_to
      if (dateFrom) params.date_from = dateFrom
      if (dateTo) params.date_to = dateTo

      return params
    },

    clearFilters() {
      this.filters = {
        search: '',
        role: '',
        accountManager: '',
        dateFrom: '',
        dateTo: '',
      }
    },

    // Fully reset filters + pagination when re-entering customers page
    resetFilters() {
      this.clearFilters()
      this.pagination.page = 1
      // preserve perPage & sorting as user preference
    },

    clearError() {
      this.error = null
    },

    // Customer Analytics with Filters
    async fetchCustomerStatisticsFiltered(customerId, refresh = false) {
      if (this.loading.stats && !refresh) return

      this.loading.stats = true
      this.error = null

      try {
        const api = this._getAPI()
        const filters = this.customerStatsFilters

        // Build parameters
        const params = {
          filterType: filters.filterType || 'year',
          filterValue: filters.filterValue || new Date().getFullYear().toString(),
        }

        if (filters.filterType === 'date-range' && filters.startDate && filters.endDate) {
          params.startDate = filters.startDate
          params.endDate = filters.endDate
        }

        if (filters.compare) {
          params.compare = true
          params.compareFilterType = filters.compareFilterType
          params.compareFilterValue = filters.compareFilterValue

          if (filters.compareFilterType === 'date-range' && filters.compareStartDate && filters.compareEndDate) {
            params.compareStartDate = filters.compareStartDate
            params.compareEndDate = filters.compareEndDate
          }
        }

        const stats = await api.getCustomerStatisticsWithFilter(customerId, params)
        this.customerStatsFiltered = stats

        return stats
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customer statistics:', error)
        this.customerStatsFiltered = null
        throw error
      } finally {
        this.loading.stats = false
      }
    },

    async fetchCustomerCategories(customerId, refresh = false) {
      if (this.loading.categories && !refresh) return

      this.loading.categories = true
      this.error = null

      try {
        const api = this._getAPI()
        const filters = this.customerStatsFilters

        // Build parameters
        const params = {
          filterType: filters.filterType || 'year',
          filterValue: filters.filterValue || new Date().getFullYear().toString(),
          limit: 10,
        }

        if (filters.filterType === 'date-range' && filters.startDate && filters.endDate) {
          params.startDate = filters.startDate
          params.endDate = filters.endDate
        }

        if (filters.compare) {
          params.compare = true
          params.compareFilterType = filters.compareFilterType
          params.compareFilterValue = filters.compareFilterValue

          if (filters.compareFilterType === 'date-range' && filters.compareStartDate && filters.compareEndDate) {
            params.compareStartDate = filters.compareStartDate
            params.compareEndDate = filters.compareEndDate
          }
        }

        const categories = await api.getCustomerCategories(customerId, params)
        this.customerCategories = categories

        return categories
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customer categories:', error)
        this.customerCategories = null
        throw error
      } finally {
        this.loading.categories = false
      }
    },

    setCustomerStatsFilters(filters) {
      this.customerStatsFilters = {
        ...this.customerStatsFilters,
        ...filters,
      }
    },
  },
})
