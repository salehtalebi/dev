/**
 * Authentication Store - Pinia
 */
import { authAPI } from '@/services/api'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { defineStore } from 'pinia'
import salesDashboardAPI from '@/services/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('auth_token'),
    isAuthenticated: false,
    loading: false,
    error: null,
  }),

  getters: {
    isLoggedIn: (state) => !!state.token && state.isAuthenticated,
    userRole: (state) => state.user?.roles?.[0] || null,
    isAdmin: (state) => {
      const allowedRoles = ['administrator', 'shop_manager']
      return state.user?.roles?.some(role => allowedRoles.includes(role)) || false
    },
  },

  actions: {
    async login(credentials) {
      this.loading = true
      this.error = null

      try {
        const response = await salesDashboardAPI.login(credentials)
        
        if (response.token && response.user) {
          this.token = response.token
          this.user = response.user
          this.isAuthenticated = true
          
          // Store token in localStorage
          localStorage.setItem('auth_token', response.token)
          
          return { success: true, user: response.user }
        } else {
          throw new Error('Invalid response format')
        }
      } catch (error) {
        this.error = error.message
        return { success: false, message: error.message }
      } finally {
        this.loading = false
      }
    },

    async logout() {
      this.user = null
      this.token = null
      this.isAuthenticated = false
      this.error = null
      
      // Remove token from localStorage
      localStorage.removeItem('auth_token')
    },

    async validateToken() {
      if (!this.token) {
        return false
      }

      try {
        const response = await salesDashboardAPI.validateToken()
        
        if (response.valid) {
          this.isAuthenticated = true
          return true
        } else {
          await this.logout()
          return false
        }
      } catch (error) {
        await this.logout()
        return false
      }
    },

    clearError() {
      this.error = null
    },
  },
})
