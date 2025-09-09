/**
 * Route Guards for Authentication
 */
import { useAuthStore } from '@/stores/auth'

export const authGuard = async (to, from, next) => {
  const authStore = useAuthStore()

  try {
    // Initialize auth store if needed
    await authStore.initialize()

    // Check if user is authenticated
    if (!authStore.isAuthenticated || !authStore.token) {
      console.log('User not authenticated, redirecting to login')
      return next('/login')
    }

    // Validate token if exists
    const isValid = await authStore.validateToken()
    if (!isValid) {
      console.log('Token invalid, redirecting to login')
      return next('/login')
    }

    // Check if user has admin role
    if (!authStore.isAdmin) {
      console.log('User not admin, redirecting to login')
      return next('/login')
    }

    console.log('Auth guard passed')
    next()
  } catch (error) {
    console.error('Auth guard error:', error)
    return next('/login')
  }
}

export const guestGuard = async (to, from, next) => {
  const authStore = useAuthStore()

  try {
    // Initialize auth store
    await authStore.initialize()

    if (authStore.isAuthenticated && authStore.isAdmin) {
      return next('/dashboard')
    }

    next()
  } catch (error) {
    console.error('Guest guard error:', error)
    next()
  }
}
