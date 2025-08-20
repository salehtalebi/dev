import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes'
import { authGuard, guestGuard } from './guards'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

// Global navigation guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  
  // Initialize auth store on first load
  if (!authStore.isAuthenticated && !authStore.user && localStorage.getItem('auth_token')) {
    await authStore.initialize()
  }
  
  // Public routes that don't need authentication
  const publicRoutes = ['/login', '/register', '/test-api']
  
  if (publicRoutes.includes(to.path)) {
    // If already authenticated, redirect to dashboard
    if (authStore.isAuthenticated && authStore.isAdmin) {
      return next('/dashboard')
    }
    return next()
  }
  
  // Protected routes - require authentication
  if (!authStore.isAuthenticated) {
    return next('/login')
  }
  
  // Check admin role for protected routes
  if (!authStore.isAdmin) {
    return next('/login')
  }
  
  next()
})

export default function (app) {
  app.use(router)
}
export { router }
