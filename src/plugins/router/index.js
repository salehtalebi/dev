import { useAuthStore } from '@/stores/auth'
import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

// Global navigation guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Initialize auth store on first load if needed
  if (!authStore.isAuthenticated && !authStore.user && localStorage.getItem('auth_token')) {
    console.log('Initializing auth store...')
    await authStore.initialize()
  }

  // Public routes that don't need authentication
  const publicRoutes = ['/login']

  if (publicRoutes.includes(to.path)) {
    // If already authenticated and trying to access login, redirect to dashboard
    if (authStore.isAuthenticated && authStore.isAdmin) {
      console.log('Already authenticated, redirecting from login to dashboard')
      return next('/dashboard')
    }
    return next()
  }

  // Protected routes - require authentication
  if (!authStore.isAuthenticated || !authStore.token) {
    console.log('Not authenticated, redirecting to login')
    return next('/login')
  }

  // Optimized token validation - only validate if not recently validated
  try {
    const isValid = await authStore.validateToken()
    if (!isValid) {
      console.log('Token validation failed, redirecting to login')
      return next('/login')
    }
  } catch (error) {
    console.error('Token validation failed:', error)
    return next('/login')
  }

  // Check admin role for protected routes
  if (!authStore.isAdmin) {
    console.log('User is not admin, redirecting to login')
    return next('/login')
  }

  next()
})

export default function (app) {
  app.use(router)
}
export { router }
