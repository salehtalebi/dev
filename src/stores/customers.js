import { defineStore } from 'pinia'
import { API_CONFIG } from '@/config/api'

export const useCustomersStore = defineStore('customers', {
  state: () => ({
    customers: [],
    currentCustomer: null,
    totalCustomers: 0,
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

        const query = new URLSearchParams(params).toString()
        const response = await this.makeRequest(`${API_CONFIG.WC_API_URL}/customers${query ? '?' + query : ''}`.replace(API_CONFIG.BASE_URL, ''))
        
        if (refresh || page === 1) {
          this.customers = response.data || response || []
        } else {
          this.customers.push(...(response.data || response || []))
        }

        this.updatePagination(response, page)
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customers:', error)
        // Add mock data for testing
        this.customers = [
          {
            id: 1,
            first_name: 'احمد',
            last_name: 'محمدی',
            email: 'ahmad@test.com',
            username: 'ahmad',
            date_created: '2023-01-15T10:00:00',
            orders_count: 5,
            total_spent: '250000'
          },
          {
            id: 2,
            first_name: 'فاطمه',
            last_name: 'حسینی',
            email: 'fatemeh@test.com',
            username: 'fatemeh',
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
        const customer = await this.makeRequest(`${API_CONFIG.WC_API_URL}/customers/${customerId}`.replace(API_CONFIG.BASE_URL, ''))
        this.currentCustomer = customer
        return customer
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customer:', error)
        return null
      } finally {
        this.loading.detail = false
      }
    },

    async fetchCustomerStatistics(customerId) {
      try {
        return await this.makeRequest(`${API_CONFIG.CUSTOM_API_URL}/customers/${customerId}/statistics`.replace(API_CONFIG.BASE_URL, ''))
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customer statistics:', error)
        return null
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
      if (this.filters.dateFrom) params.after = this.filters.dateFrom
      if (this.filters.dateTo) params.before = this.filters.dateTo

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

    clearError() {
      this.error = null
    },
  },
})
