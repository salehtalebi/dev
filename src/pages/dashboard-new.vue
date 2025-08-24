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
                <div class="text-caption text-medium-emphasis">سفارشات امروز</div>
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
                <div class="text-caption text-medium-emphasis">فروش امروز</div>
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
                <div class="text-caption text-medium-emphasis">مشتریان جدید</div>
                <div class="text-h6">{{ dashboardData.newCustomers || 0 }}</div>
                <div class="text-caption text-success">این ماه</div>
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
                <div class="text-caption text-medium-emphasis">میانگین سفارش</div>
                <div class="text-h6">${{ formatCurrency(dashboardData.averageOrderValue) }}</div>
                <div class="text-caption text-medium-emphasis">این ماه</div>
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
            <span>آخرین سفارشات</span>
            <VBtn
              size="small"
              variant="outlined"
              to="/orders"
            >
              مشاهده همه
            </VBtn>
          </VCardTitle>
          <VCardText>
            <div class="text-center py-8">
              <p class="text-medium-emphasis">برای مشاهده آخرین سفارشات به صفحه سفارشات مراجعه کنید</p>
              <VBtn
                color="primary"
                variant="outlined"
                to="/orders"
                class="mt-4"
              >
                مشاهده سفارشات
              </VBtn>
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
import { computed } from 'vue'

const dashboardStore = useDashboardStore()
const { dashboardStats, loading } = useAnalytics()

// Computed
const dashboardData = computed(() => dashboardStats.value)

// Methods
const formatCurrency = (value) => {
  return parseFloat(value || 0).toLocaleString()
}

const formatGrowth = (value) => {
  return parseFloat(value || 0).toFixed(1)
}

const getGrowthColor = (growth) => {
  return parseFloat(growth) >= 0 ? 'text-success' : 'text-error'
}

// Data will be auto-fetched by useAnalytics composable
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
