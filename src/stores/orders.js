import { API_CONFIG } from '@/config/api'
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
      { value: 'pending', text: 'در انتظار پرداخت', color: 'warning' },
      { value: 'processing', text: 'در حال پردازش', color: 'info' },
      { value: 'on-hold', text: 'در انتظار', color: 'secondary' },
      { value: 'completed', text: 'تکمیل شده', color: 'success' },
      { value: 'cancelled', text: 'لغو شده', color: 'error' },
      { value: 'refunded', text: 'بازپرداخت شده', color: 'error' },
      { value: 'failed', text: 'ناموفق', color: 'error' },
    ],

    currentPage: (state) => state.pagination.page,
    totalPages: (state) => state.pagination.totalPages,
    hasNextPage: (state) => state.pagination.hasNext,
    hasPrevPage: (state) => state.pagination.hasPrev,
  },

  actions: {
    async makeRequest(url, options = {}) {
      const config = {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          ...options.headers
        },
        ...options
      }

      const token = localStorage.getItem('auth_token')
      if (token) {
        config.headers.Authorization = `Bearer ${token}`
      }

      if (config.method !== 'GET' && options.body) {
        config.body = JSON.stringify(options.body)
      }

      console.log('Making request to:', `${API_CONFIG.BASE_URL}${url}`)
      console.log('With headers:', config.headers)

      const response = await fetch(`${API_CONFIG.BASE_URL}${url}`, config)

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}))
        throw new Error(errorData.message || `HTTP ${response.status}`)
      }

      return response.json()
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

        const query = new URLSearchParams(params).toString()
        const response = await this.makeRequest(`${API_CONFIG.CUSTOM_API_URL}/wc/orders${query ? '?' + query : ''}`.replace(API_CONFIG.BASE_URL, ''))

        if (refresh || page === 1) {
          this.orders = response.data || response || []
        } else {
          this.orders.push(...(response.data || response || []))
        }

        // Update pagination based on response
        this.totalOrders = response.total || (response.data ? response.data.length : 0)
        this.pagination.page = page
        this.pagination.totalPages = Math.ceil(this.totalOrders / this.pagination.perPage)
        this.pagination.hasNext = page < this.pagination.totalPages
        this.pagination.hasPrev = page > 1
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
              first_name: 'علی',
              last_name: 'رضایی',
              email: 'ali@test.com'
            },
            line_items: [
              { name: 'محصول تست', quantity: 2, price: '62500' }
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
        const order = await this.makeRequest(`${API_CONFIG.CUSTOM_API_URL}/wc/orders/${orderId}`.replace(API_CONFIG.BASE_URL, ''))
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
        const updatedOrder = await this.makeRequest(`${API_CONFIG.CUSTOM_API_URL}/wc/orders/${orderId}/status`.replace(API_CONFIG.BASE_URL, ''), {
          method: 'PUT',
          body: { status }
        })

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
      this.filters = { ...this.filters, ...filters }
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
      if (this.filters.dateFrom) params.after = this.filters.dateFrom
      if (this.filters.dateTo) params.before = this.filters.dateTo
      if (this.filters.minAmount !== null) params.min_amount = this.filters.minAmount
      if (this.filters.maxAmount !== null) params.max_amount = this.filters.maxAmount

      return params
    },

    clearFilters() {
      this.filters = {
        status: '',
        search: '',
        customer: '',
        accountManager: '',
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
