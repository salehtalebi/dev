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

import { authGuard, guestGuard } from './guards'

export const routes = [
  { path: '/', redirect: '/dashboard' },
  {
    path: '/',
    component: () => import('@/layouts/default.vue'),
    beforeEnter: authGuard, // اضافه کردن guard
    children: [
      {
        path: 'dashboard',
        component: () => import('@/pages/dashboard.vue'),
      },
      {
        path: 'orders',
        component: () => import('@/pages/orders.vue'),
      },
      {
        path: 'orders/:id',
        component: () => import('@/pages/order-detail.vue'),
      },
      {
        path: 'customers',
        component: () => import('@/pages/customers.vue'),
      },
      {
        path: 'customers/:id',
        component: () => import('@/pages/customer-detail.vue'),
      },
      {
        path: 'account-settings',
        component: () => import('@/pages/account-settings.vue'),
      },
      {
        path: 'tables',
        component: () => import('@/pages/tables.vue'),
      },
      {
        path: 'form-layouts',
        component: () => import('@/pages/form-layouts.vue'),
      },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/blank.vue'),
    beforeEnter: guestGuard, // اضافه کردن guard
    children: [
      {
        path: 'login',
        component: () => import('@/pages/login.vue'),
      },
      {
        path: 'register',
        component: () => import('@/pages/register.vue'),
      },
      {
        path: '/:pathMatch(.*)*',
        component: () => import('@/pages/[...error].vue'),
      },
    ],
  },
]
