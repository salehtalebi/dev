import { API_CONFIG } from '@/config/api'
import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('auth_token'),
    isAuthenticated: !!localStorage.getItem('auth_token'), // Set true if token exists
    loading: false,
    error: null,
    tokenValidatedAt: null, // Track when token was last validated
    tokenValidationInterval: 5 * 60 * 1000, // 5 minutes in milliseconds
  }),

  getters: {
    isLoggedIn: (state) => !!state.token && state.isAuthenticated,
    userRole: (state) => {
      if (!state.user?.roles) return null
      // Handle both object and array formats
      if (Array.isArray(state.user.roles)) {
        return state.user.roles[0] || null
      }
      // Handle object format like {"8": "administrator", "9": "bbp_keymaster"}
      return Object.values(state.user.roles)[0] || null
    },
    isAdmin: (state) => {
      if (!state.user?.roles) return false

      const allowedRoles = ['administrator', 'shop_manager']

      // Handle both object and array formats
      if (Array.isArray(state.user.roles)) {
        return state.user.roles.some(role => allowedRoles.includes(role))
      }

      // Handle object format like {"8": "administrator", "9": "bbp_keymaster"}
      const roleValues = Object.values(state.user.roles)
      return roleValues.some(role => allowedRoles.includes(role))
    },
  },

  actions: {
    async initialize() {
      console.log('Initializing auth store...')
      // Initialize auth state from localStorage
      const token = localStorage.getItem('auth_token')
      if (token) {
        console.log('Token found in localStorage')
        this.token = token
        this.isAuthenticated = true

        // Try to get user info if not available
        if (!this.user) {
          console.log('Getting current user info...')
          await this.getCurrentUser()
        }

        // Validate token
        const isValid = await this.validateToken()
        if (!isValid) {
          console.log('Token validation failed, logging out')
          await this.logout()
          return false
        }
        console.log('Auth initialization successful')
        return true
      } else {
        console.log('No token found in localStorage')
        this.isAuthenticated = false
        return false
      }
    },

    async login(credentials) {
      this.loading = true
      this.error = null

      try {
        const response = await fetch(`${API_CONFIG.JWT_URL}/login`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(credentials)
        })

        if (!response.ok) {
          const errorData = await response.json()
          throw new Error(errorData.message || 'خطا در ورود')
        }

        const data = await response.json()

        if (data.token && data.user) {
          this.token = data.token
          this.user = data.user
          this.isAuthenticated = true

          localStorage.setItem('auth_token', data.token)

          return { success: true, user: data.user }
        } else {
          throw new Error('پاسخ نامعتبر از سرور')
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

      localStorage.removeItem('auth_token')
    },

    async validateToken() {
      if (!this.token) {
        return false
      }

      // Check if token was recently validated (within last 5 minutes)
      const now = Date.now()
      if (this.tokenValidatedAt && (now - this.tokenValidatedAt) < this.tokenValidationInterval) {
        console.log('Token recently validated, skipping validation')
        return true
      }

      try {
        console.log('Validating token...')
        const response = await fetch(`${API_CONFIG.JWT_URL}/validate`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${this.token}`
          }
        })

        if (response.ok) {
          const data = await response.json()
          console.log('Token validation response:', data)

          // Check for WordPress JWT response format
          if (data.code === 'jwt_auth_valid_token' && data.data && data.data.status === 200) {
            this.isAuthenticated = true
            this.tokenValidatedAt = now // Update validation timestamp

            // Get current user info if not available
            if (!this.user) {
              await this.getCurrentUser()
            }

            return true
          }

          // Also check for simple valid field
          if (data.valid) {
            this.isAuthenticated = true
            this.tokenValidatedAt = now // Update validation timestamp

            // Get current user info if not available
            if (!this.user) {
              await this.getCurrentUser()
            }

            return true
          }
        }

        console.log('Token validation failed, logging out')
        await this.logout()
        return false
      } catch (error) {
        console.error('Token validation error:', error)
        console.error('Token validation error:', error)
        await this.logout()
        return false
      }
    },

    async getCurrentUser() {
      if (!this.token) return null

      try {
        const response = await fetch(`${API_CONFIG.JWT_URL}/me`, {
          headers: {
            'Authorization': `Bearer ${this.token}`
          }
        })

        if (response.ok) {
          const userData = await response.json()
          this.user = userData
          return userData
        }

        return null
      } catch (error) {
        console.error('Failed to get current user:', error)
        return null
      }
    },

    clearError() {
      this.error = null
    },
  },
})
