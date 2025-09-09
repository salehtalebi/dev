import { useAPI } from '@/composables/useAPI'
import { defineStore } from 'pinia'

export const useOrdersStore = defineStore('orders', {
  state: () => ({
    orders: [],
    currentOrder: null,
    totalOrders: 0,
    loading: {
      list: false,
      detail: false,
      update: false,
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
      status: '',
      search: '',
      customer: '',
      accountManager: '',
      dateRange: '',
      dateFrom: '',
      dateTo: '',
      minAmount: null,
      maxAmount: null,
    },
    sorting: {
      orderby: 'date',
      order: 'desc',
    },
  }),

  getters: {
    isLoading: (state) => Object.values(state.loading).some(loading => loading),

    filteredOrders: (state) => {
      let filtered = [...state.orders]

      if (state.filters.search) {
        const search = state.filters.search.toLowerCase()
        filtered = filtered.filter(order =>
          order.id.toString().includes(search) ||
          order.billing?.first_name?.toLowerCase().includes(search) ||
          order.billing?.last_name?.toLowerCase().includes(search) ||
          order.billing?.email?.toLowerCase().includes(search)
        )
      }

      return filtered
    },

    orderStatuses: () => [
      { value: 'pending', text: 'Pending Payment', color: 'warning' },
      { value: 'processing', text: 'Processing', color: 'info' },
      { value: 'on-hold', text: 'On Hold', color: 'secondary' },
      { value: 'completed', text: 'Completed', color: 'success' },
      { value: 'cancelled', text: 'Cancelled', color: 'error' },
      { value: 'refunded', text: 'Refunded', color: 'error' },
      { value: 'failed', text: 'Failed', color: 'error' },
    ],

    currentPage: (state) => state.pagination.page,
    totalPages: (state) => state.pagination.totalPages,
    hasNextPage: (state) => state.pagination.hasNext,
    hasPrevPage: (state) => state.pagination.hasPrev,
  },

  actions: {
    // Initialize API instance
    _getAPI() {
      const { getOrders, getOrder, updateOrder } = useAPI()
      return { getOrders, getOrder, updateOrder }
    },

    async fetchOrders(page = 1, refresh = false) {
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
        const { data, headers } = await api.getOrders(params)
        const items = Array.isArray(data) ? data : (data?.data || [])

        if (refresh || page === 1) {
          this.orders = items
        } else {
          this.orders.push(...items)
        }

        // Totals from WP-style headers
        // Prefer WP headers; fall back to body fields if provided
        let total = parseInt(headers.get('x-wp-total') || 'NaN')
        let totalPages = parseInt(headers.get('x-wp-totalpages') || 'NaN')
        if (!Number.isFinite(total)) total = parseInt(data?.total ?? '0')
        if (!Number.isFinite(totalPages)) totalPages = parseInt(data?.pages ?? '1')
        this.totalOrders = Number.isFinite(total) ? total : 0
        this.pagination.totalPages = Number.isFinite(totalPages) ? totalPages : 1
        this.pagination.hasNext = page < this.pagination.totalPages
        this.pagination.hasPrev = page > 1

        if (this.pagination.page !== page) {
          this.pagination.page = page
        }
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch orders:', error)
        // Add mock data for testing
        this.orders = [
          {
            id: 123,
            status: 'completed',
            date_created: '2023-12-01T12:00:00',
            total: '125000',
            billing: {
              first_name: 'John',
              last_name: 'Doe',
              email: 'john@test.com'
            },
            line_items: [
              { name: 'Test Product', quantity: 2, price: '62500' }
            ]
          }
        ]
        this.totalOrders = 1
      } finally {
        this.loading.list = false
      }
    },

    async fetchOrder(orderId) {
      this.loading.detail = true
      this.error = null

      try {
        const api = this._getAPI()
        const order = await api.getOrder(orderId)
        this.currentOrder = order
        return order
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch order:', error)
        return null
      } finally {
        this.loading.detail = false
      }
    },

    async updateOrderStatus(orderId, status) {
      this.loading.update = true
      this.error = null

      try {
        const api = this._getAPI()
        const updatedOrder = await api.updateOrder(orderId, { status })

        // Update order in the list
        const index = this.orders.findIndex(o => o.id === orderId)
        if (index !== -1) {
          this.orders[index] = { ...this.orders[index], status, ...updatedOrder }
        }

        // Update current order if it's the same
        if (this.currentOrder && this.currentOrder.id === orderId) {
          this.currentOrder = { ...this.currentOrder, status, ...updatedOrder }
        }

        return updatedOrder
      } catch (error) {
        this.error = error.message
        console.error('Failed to update order status:', error)
        throw error
      } finally {
        this.loading.update = false
      }
    },

    setFilters(filters) {
      // Normalize snake_case from UI to store camelCase
      const normalized = { ...filters }
      if (Object.prototype.hasOwnProperty.call(filters, 'account_manager')) normalized.accountManager = filters.account_manager
      if (Object.prototype.hasOwnProperty.call(filters, 'date_from')) normalized.dateFrom = filters.date_from
      if (Object.prototype.hasOwnProperty.call(filters, 'date_to')) normalized.dateTo = filters.date_to
      if (Object.prototype.hasOwnProperty.call(filters, 'min_amount')) normalized.minAmount = filters.min_amount
      if (Object.prototype.hasOwnProperty.call(filters, 'max_amount')) normalized.maxAmount = filters.max_amount
      if (Object.prototype.hasOwnProperty.call(filters, 'customer')) normalized.customer = filters.customer
      if (Object.prototype.hasOwnProperty.call(filters, 'status')) normalized.status = filters.status
      if (Object.prototype.hasOwnProperty.call(filters, 'search')) normalized.search = filters.search
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
      this.totalOrders = response.total || 0
      this.pagination = {
        page: page,
        perPage: this.pagination.perPage,
        totalPages: Math.ceil((response.total || 0) / this.pagination.perPage),
        hasNext: response.total > (page * this.pagination.perPage),
        hasPrev: page > 1,
      }
    },

    buildAPIParams() {
      const params = {}

      if (this.filters.search) params.search = this.filters.search
      if (this.filters.status) params.status = this.filters.status
      if (this.filters.customer) params.customer = this.filters.customer
      if (this.filters.accountManager) params.account_manager = this.filters.accountManager

      // Handle dateRange preset values
      if (this.filters.dateRange && this.filters.dateRange !== 'custom') {
        params.date_range = this.filters.dateRange
      } else {
        // Handle custom date range
        if (this.filters.dateFrom) params.date_from = this.filters.dateFrom
        if (this.filters.dateTo) params.date_to = this.filters.dateTo
      }

      if (this.filters.minAmount !== null && this.filters.minAmount !== '') {
        params.min_amount = this.filters.minAmount
      }
      if (this.filters.maxAmount !== null && this.filters.maxAmount !== '') {
        params.max_amount = this.filters.maxAmount
      }

      return params
    },

    clearFilters() {
      this.filters = {
        status: '',
        search: '',
        customer: '',
        accountManager: '',
        dateRange: '',
        dateFrom: '',
        dateTo: '',
        minAmount: null,
        maxAmount: null,
      }
    },

    clearError() {
      this.error = null
    },
  },
})
