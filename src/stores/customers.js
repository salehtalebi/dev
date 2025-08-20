/**
 * Customers Store - Pinia
 */
import { customersAPI } from '@/services/api'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { defineStore } from 'pinia'
import salesDashboardAPI from '@/services/api'

export const useCustomersStore = defineStore('customers', {
  state: () => ({
    customers: [],
    currentCustomer: null,
    customerOrders: [],
    totalCustomers: 0,
    loading: {
      list: false,
      detail: false,
      orders: false,
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
      accountManager: null,
      status: null,
      dateRange: null,
      totalSpentMin: null,
      totalSpentMax: null,
      orderCountMin: null,
      orderCountMax: null,
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
          customer.display_name?.toLowerCase().includes(search) ||
          customer.email?.toLowerCase().includes(search) ||
          customer.billing?.first_name?.toLowerCase().includes(search) ||
          customer.billing?.last_name?.toLowerCase().includes(search)
        )
      }
      
      return filtered
    },
    customerStats: (state) => {
      const total = state.customers.length
      const withOrders = state.customers.filter(c => c.order_count > 0).length
      const averageOrderValue = state.customers.reduce((sum, c) => sum + (c.total_spent || 0), 0) / total || 0
      
      return {
        total,
        withOrders,
        withoutOrders: total - withOrders,
        averageOrderValue,
        averageOrderCount: state.customers.reduce((sum, c) => sum + (c.order_count || 0), 0) / total || 0,
      }
    },
    hasNextPage: (state) => state.pagination.hasNext,
    hasPrevPage: (state) => state.pagination.hasPrev,
  },

  actions: {
    setFilters(filters) {
      this.filters = { ...this.filters, ...filters }
      this.pagination.page = 1 // Reset to first page when filters change
    },

    setSorting(sorting) {
      this.sorting = { ...this.sorting, ...sorting }
      this.pagination.page = 1 // Reset to first page when sorting changes
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

        const response = await salesDashboardAPI.getCustomers(params)
        
        if (refresh || page === 1) {
          this.customers = response.data || []
        } else {
          // Append for pagination
          this.customers.push(...(response.data || []))
        }

        this.totalCustomers = response.total || 0
        this.pagination = {
          page: response.page || 1,
          perPage: response.per_page || 20,
          totalPages: response.total_pages || 1,
          hasNext: response.has_next || false,
          hasPrev: response.has_prev || false,
        }
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customers:', error)
      } finally {
        this.loading.list = false
      }
    },

    async fetchCustomerDetail(customerId) {
      this.loading.detail = true
      this.error = null

      try {
        const customer = await salesDashboardAPI.getCustomer(customerId)
        this.currentCustomer = customer
        return customer
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customer detail:', error)
        return null
      } finally {
        this.loading.detail = false
      }
    },

    async fetchCustomerOrders(customerId, params = {}) {
      this.loading.orders = true

      try {
        const defaultParams = {
          customer: customerId,
          per_page: 20,
          orderby: 'date',
          order: 'desc',
        }

        const response = await salesDashboardAPI.getOrders({ ...defaultParams, ...params })
        this.customerOrders = response.data || []
        return response
      } catch (error) {
        this.error = error.message
        console.error('Failed to fetch customer orders:', error)
        return null
      } finally {
        this.loading.orders = false
      }
    },

    async updateCustomer(customerId, data) {
      this.loading.update = true
      this.error = null

      try {
        const updatedCustomer = await salesDashboardAPI.updateCustomer(customerId, data)
        
        // Update customer in the list
        const index = this.customers.findIndex(c => c.id === customerId)
        if (index !== -1) {
          this.customers[index] = updatedCustomer
        }
        
        // Update current customer if it's the same
        if (this.currentCustomer && this.currentCustomer.id === customerId) {
          this.currentCustomer = updatedCustomer
        }
        
        return updatedCustomer
      } catch (error) {
        this.error = error.message
        console.error('Failed to update customer:', error)
        throw error
      } finally {
        this.loading.update = false
      }
    },

    async loadMoreCustomers() {
      if (this.hasNextPage && !this.loading.list) {
        await this.fetchCustomers(this.pagination.page + 1)
      }
    },

    async refreshCustomers() {
      await this.fetchCustomers(1, true)
    },

    async searchCustomers(query) {
      this.setFilters({ search: query })
      await this.fetchCustomers(1, true)
    },

    async exportCustomers(format = 'csv') {
      try {
        const params = {
          type: 'customers',
          format,
          ...this.buildAPIParams(),
        }

        const response = await salesDashboardAPI.exportData(params)
        
        // Handle file download
        if (response.download_url) {
          window.open(response.download_url, '_blank')
        }
        
        return response
      } catch (error) {
        this.error = error.message
        console.error('Failed to export customers:', error)
        throw error
      }
    },

    buildAPIParams() {
      const params = {}

      // Search filter
      if (this.filters.search) {
        params.search = this.filters.search
      }

      // Account manager filter
      if (this.filters.accountManager) {
        params.account_manager = this.filters.accountManager
      }

      // Status filter
      if (this.filters.status) {
        params.status = this.filters.status
      }

      // Date range filter
      if (this.filters.dateRange) {
        params.date_range = this.filters.dateRange
      }

      // Spending filters
      if (this.filters.totalSpentMin !== null) {
        params.total_spent_min = this.filters.totalSpentMin
      }
      if (this.filters.totalSpentMax !== null) {
        params.total_spent_max = this.filters.totalSpentMax
      }

      // Order count filters
      if (this.filters.orderCountMin !== null) {
        params.order_count_min = this.filters.orderCountMin
      }
      if (this.filters.orderCountMax !== null) {
        params.order_count_max = this.filters.orderCountMax
      }

      return params
    },

    clearFilters() {
      this.filters = {
        search: '',
        accountManager: null,
        status: null,
        dateRange: null,
        totalSpentMin: null,
        totalSpentMax: null,
        orderCountMin: null,
        orderCountMax: null,
      }
    },

    clearError() {
      this.error = null
    },

    resetStore() {
      this.customers = []
      this.currentCustomer = null
      this.customerOrders = []
      this.totalCustomers = 0
      this.pagination.page = 1
      this.clearFilters()
      this.clearError()
    },
  },
})
