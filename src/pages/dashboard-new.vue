<template>
  <div>
    <!-- Top Statistics Cards -->
    <VRow>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="primary"
                variant="tonal"
                size="40"
                class="me-3"
              >
                <VIcon icon="bx-cart" />
              </VAvatar>
              <div>
                <div class="text-caption text-medium-emphasis">Today's Orders</div>
                <div class="text-h6">{{ dashboardData.todayOrders || 0 }}</div>
                <div class="text-caption" :class="getGrowthColor(dashboardData.ordersGrowth)">
                  {{ formatGrowth(dashboardData.ordersGrowth) }}%
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="success"
                variant="tonal"
                size="40"
                class="me-3"
              >
                <VIcon icon="bx-dollar" />
              </VAvatar>
              <div>
                <div class="text-caption text-medium-emphasis">Today's Sales</div>
                <div class="text-h6">${{ formatCurrency(dashboardData.todayRevenue) }}</div>
                <div class="text-caption" :class="getGrowthColor(dashboardData.revenueGrowth)">
                  {{ formatGrowth(dashboardData.revenueGrowth) }}%
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="info"
                variant="tonal"
                size="40"
                class="me-3"
              >
                <VIcon icon="bx-user" />
              </VAvatar>
              <div>
                <div class="text-caption text-medium-emphasis">New Customers</div>
                <div class="text-h6">{{ dashboardData.newCustomers || 0 }}</div>
                <div class="text-caption text-success">This Month</div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="d-flex align-center">
              <VAvatar
                color="warning"
                variant="tonal"
                size="40"
                class="me-3"
              >
                <VIcon icon="bx-trending-up" />
              </VAvatar>
              <div>
                <div class="text-caption text-medium-emphasis">Average Order</div>
                <div class="text-h6">${{ formatCurrency(dashboardData.averageOrderValue) }}</div>
                <div class="text-caption text-medium-emphasis">This Month</div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Charts Row -->
    <VRow>
      <!-- Total Revenue Component -->
      <VCol cols="12" md="8">
        <AnalyticsTotalRevenue />
      </VCol>

      <!-- Top Products -->
      <VCol cols="12" md="4">
        <AnalyticsTopProducts />
      </VCol>
    </VRow>

    <!-- Second Row -->
    <VRow>
      <!-- Manager Performance -->
      <VCol cols="12" md="8">
        <AnalyticsManagerPerformance />
      </VCol>

      <!-- Order Statistics -->
      <VCol cols="12" md="4">
        <AnalyticsOrderStatistics />
      </VCol>
    </VRow>

    <!-- Recent Orders (Simplified) -->
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardTitle class="d-flex justify-space-between align-center">
            <span>Recent Orders</span>
            <VBtn
              size="small"
              variant="outlined"
              to="/orders"
            >
              View All
            </VBtn>
          </VCardTitle>
          <VCardText>
            <div v-if="dashboardStore.loading.orders" class="text-center py-6">
              <VProgressCircular indeterminate color="primary" />
            </div>
            <div v-else-if="!recentOrders.length" class="text-center py-6 text-medium-emphasis">
              No recent orders found.
            </div>
            <div v-else>
              <VTable density="comfortable" class="text-no-wrap">
                <thead>
                  <tr>
                    <th class="text-left">#</th>
                    <th class="text-left">Customer</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Total</th>
                    <th class="text-left">Date</th>
                    <th class="text-left">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="order in recentOrders" :key="order.id">
                    <td>
                      <VBtn variant="text" size="x-small" @click="viewOrder(order.id)">#{{ order.id }}</VBtn>
                    </td>
                    <td>
                      <div class="font-weight-medium">{{ order.billing?.first_name }} {{ order.billing?.last_name }}</div>
                      <div class="text-caption text-medium-emphasis">{{ order.billing?.email }}</div>
                    </td>
                    <td>
                      <VChip :color="getStatusColor(order.status)" size="x-small" variant="tonal">{{ getStatusText(order.status) }}</VChip>
                    </td>
                    <td class="font-weight-medium">${{ parseFloat(order.total || 0).toFixed(2) }}</td>
                    <td class="text-caption">{{ formatDate(order.date_created) }}</td>
                    <td>
                      <VBtn icon size="x-small" variant="text" @click="viewOrder(order.id)">
                        <VIcon icon="bx-show" />
                      </VBtn>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<script setup>
import { useAnalytics } from '@/composables/useAnalytics'
import { useDashboardStore } from '@/stores/dashboard'
import AnalyticsManagerPerformance from '@/views/dashboard/AnalyticsManagerPerformance.vue'
import AnalyticsOrderStatistics from '@/views/dashboard/AnalyticsOrderStatistics.vue'
import AnalyticsTopProducts from '@/views/dashboard/AnalyticsTopProducts.vue'
import AnalyticsTotalRevenue from '@/views/dashboard/AnalyticsTotalRevenue.vue'
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const dashboardStore = useDashboardStore()
const { dashboardStats } = useAnalytics()

// Computed
const dashboardData = computed(() => dashboardStats.value)
const recentOrders = computed(() => Array.isArray(dashboardStore.recentOrders) ? dashboardStore.recentOrders.slice(0, 10) : [])

// Lifecycle: ensure recent orders are fetched (independent of date filters)
onMounted(() => {
  if (!dashboardStore.recentOrders.length && !dashboardStore.loading.orders) {
    dashboardStore.fetchRecentOrders().catch(err => console.debug('fetchRecentOrders error', err))
  }
})

// Formatting helpers
const formatCurrency = (value) => parseFloat(value || 0).toLocaleString()
const formatGrowth = (value) => parseFloat(value || 0).toFixed(1)
const getGrowthColor = (growth) => parseFloat(growth) >= 0 ? 'text-success' : 'text-error'

function formatDate(dateStr) {
  if (!dateStr) return ''
  try {
    return new Date(dateStr).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' })
  } catch (e) {
    return dateStr
  }
}

// Order helpers
function viewOrder(id) {
  if (!id) return
  router.push(`/orders?highlight=${id}`)
}

function getStatusColor(status) {
  switch (status) {
    case 'completed': return 'success'
    case 'processing': return 'primary'
    case 'on-hold': return 'warning'
    case 'cancelled': return 'error'
    case 'refunded': return 'info'
    case 'failed': return 'error'
    case 'pending': return 'secondary'
    default: return 'secondary'
  }
}

function getStatusText(status) {
  if (!status) return 'Unknown'
  return status.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
