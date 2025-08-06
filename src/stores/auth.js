/**
 * Authentication Store - Pinia
 */
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authAPI } from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const token = ref(localStorage.getItem('wp_token'))
  const isLoading = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin = computed(() => {
    return user.value && (
      user.value.roles?.includes('administrator') || 
      user.value.roles?.includes('shop_manager')
    )
  })

  // Actions
  const login = async (credentials) => {
    try {
      isLoading.value = true
      const response = await authAPI.login(credentials)
      
      token.value = response.token
      localStorage.setItem('wp_token', response.token)
      
      // Get user info
      await fetchUser()
      
      return { success: true }
    } catch (error) {
      console.error('Login error:', error)
      return { 
        success: false, 
        message: error.response?.data?.message || 'خطای لاگین' 
      }
    } finally {
      isLoading.value = false
    }
  }

  const fetchUser = async () => {
    try {
      const userData = await authAPI.me()
      user.value = userData
    } catch (error) {
      console.error('Fetch user error:', error)
      logout()
    }
  }

  const logout = () => {
    user.value = null
    token.value = null
    localStorage.removeItem('wp_token')
  }

  const validateToken = async () => {
    try {
      await authAPI.validate()
      await fetchUser()
      return true
    } catch (error) {
      logout()
      return false
    }
  }

  return {
    // State
    user,
    token,
    isLoading,
    
    // Getters
    isAuthenticated,
    isAdmin,
    
    // Actions
    login,
    logout,
    fetchUser,
    validateToken
  }
})
