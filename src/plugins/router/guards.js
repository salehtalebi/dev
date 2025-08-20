/**
 * Route Guards برای Authentication
 */
import { useAuthStore } from '@/stores/auth'

export const authGuard = async (to, from, next) => {
  const authStore = useAuthStore()
  
  // Check if user is authenticated
  if (!authStore.isAuthenticated) {
    // Try to validate existing token
    const isValid = await authStore.validateToken()
    if (!isValid) {
      return next('/login')
    }
  }
  
  // Check if user has admin role
  if (!authStore.isAdmin) {
    return next('/login')
  }
  
  next()
}

export const guestGuard = (to, from, next) => {
  const authStore = useAuthStore()
  
  if (authStore.isAuthenticated && authStore.isAdmin) {
    return next('/dashboard')
  }
  
  next()
}
