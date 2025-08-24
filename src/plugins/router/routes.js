export const routes = [
  { path: '/', redirect: '/dashboard' },
  {
    path: '/',
    component: () => import('@/layouts/default.vue'),
    children: [
      {
        path: 'dashboard',
        component: () => import('@/pages/dashboard.vue'),
      },
      {
        path: 'dashboard-new',
        component: () => import('@/pages/dashboard-new.vue'),
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
        path: 'form-layouts',
        component: () => import('@/pages/form-layouts.vue'),
      },
      {
        path: 'icons',
        component: () => import('@/pages/icons.vue'),
      },
    ],
  },
  {
    path: '/',
    component: () => import('@/layouts/blank.vue'),
    children: [
      {
        path: 'login',
        component: () => import('@/pages/login.vue'),
      },
      {
        path: '/:pathMatch(.*)*',
        component: () => import('@/pages/[...error].vue'),
      },
    ],
  },
]
