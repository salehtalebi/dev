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
      <!-- Sales Chart -->
      <VCol cols="12" md="8">
        <VCard>
          <VCardTitle>ترند فروش</VCardTitle>
          <VCardText>
            <VueApexCharts
              v-if="salesChartData.series.length"
              type="line"
              height="350"
              :options="salesChartOptions"
              :series="salesChartData.series"
            />
            <div v-else class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
              <p class="mt-4">در حال بارگذاری نمودار...</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Top Products -->
      <VCol cols="12" md="4">
        <VCard>
          <VCardTitle>پرفروش‌ترین محصولات</VCardTitle>
          <VCardText>
            <div v-if="topProducts.length === 0" class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
              <p class="mt-4">در حال بارگذاری...</p>
            </div>
            <div v-else>
              <div
                v-for="(product, index) in topProducts.slice(0, 5)"
                :key="product.id"
                class="d-flex align-center mb-4"
              >
                <div class="me-3">
                  <VChip
                    :color="getTopProductColor(index)"
                    size="small"
                    variant="tonal"
                  >
                    {{ index + 1 }}
                  </VChip>
                </div>
                <div class="flex-grow-1">
                  <div class="font-weight-medium">{{ product.name }}</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ product.quantity }} فروش
                  </div>
                </div>
                <div class="text-end">
                  <div class="font-weight-bold">${{ formatCurrency(product.total) }}</div>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Recent Orders & Account Managers Performance -->
    <VRow>
      <!-- Recent Orders -->
      <VCol cols="12" md="8">
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
            <VTable v-if="recentOrders.length">
              <thead>
                <tr>
                  <th>شماره سفارش</th>
                  <th>مشتری</th>
                  <th>وضعیت</th>
                  <th>مبلغ</th>
                  <th>تاریخ</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="order in recentOrders.slice(0, 5)" :key="order.id">
                  <td>
                    <VChip
                      color="primary"
                      variant="outlined"
                      size="small"
                      @click="$router.push(`/orders/${order.id}`)"
                      class="cursor-pointer"
                    >
                      #{{ order.id }}
                    </VChip>
                  </td>
                  <td>{{ order.billing.first_name }} {{ order.billing.last_name }}</td>
                  <td>
                    <VChip
                      :color="getOrderStatusColor(order.status)"
                      size="small"
                      variant="tonal"
                    >
                      {{ getOrderStatusText(order.status) }}
                    </VChip>
                  </td>
                  <td class="font-weight-bold">${{ formatCurrency(order.total) }}</td>
                  <td>{{ formatDate(order.date_created) }}</td>
                </tr>
              </tbody>
            </VTable>
            <div v-else class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
              <p class="mt-4">در حال بارگذاری...</p>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Account Managers Performance -->
      <VCol cols="12" md="4">
        <VCard>
          <VCardTitle>عملکرد اکانت منیجرها</VCardTitle>
          <VCardText>
            <div v-if="accountManagersPerformance.length === 0" class="text-center py-8">
              <VProgressCircular indeterminate color="primary" />
              <p class="mt-4">در حال بارگذاری...</p>
            </div>
            <div v-else>
              <div
                v-for="manager in accountManagersPerformance"
                :key="manager.id"
                class="d-flex align-center justify-space-between mb-4"
              >
                <div class="d-flex align-center">
                  <VAvatar
                    size="32"
                    color="primary"
                    class="me-3"
                  >
                    {{ getInitials(manager.name) }}
                  </VAvatar>
                  <div>
                    <div class="font-weight-medium">{{ manager.name }}</div>
                    <div class="text-caption">{{ manager.customers_count }} مشتری</div>
                  </div>
                </div>
                <div class="text-end">
                  <div class="font-weight-bold">${{ formatCurrency(manager.total_sales) }}</div>
                  <div class="text-caption" :class="getGrowthColor(manager.growth)">
                    {{ formatGrowth(manager.growth) }}%
                  </div>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<script setup>
import { useDashboardStore } from '@/stores/dashboard'
import { useOrdersStore } from '@/stores/orders'
import { format } from 'date-fns'
import { computed, onMounted, ref } from 'vue'
import VueApexCharts from 'vue3-apexcharts'

const dashboardStore = useDashboardStore()
const ordersStore = useOrdersStore()

// State
const recentOrders = ref([])
const accountManagersPerformance = ref([])

// Computed
const dashboardData = computed(() => dashboardStore.salesData)
const topProducts = computed(() => dashboardStore.topProducts)
const isLoading = computed(() => dashboardStore.isLoading)

// Chart configuration
const salesChartData = ref({
  series: []
})

const salesChartOptions = ref({
  chart: {
    type: 'line',
    toolbar: {
      show: false
    }
  },
  stroke: {
    curve: 'smooth',
    width: 3
  },
  colors: ['#7367F0', '#28C76F'],
  xaxis: {
    categories: []
  },
  yaxis: {
    labels: {
      formatter: (value) => `$${value}`
    }
  },
  grid: {
    show: true,
    borderColor: '#E0E0E0'
  },
  legend: {
    show: true,
    position: 'top'
  }
})

// Methods
const fetchDashboardData = async () => {
  try {
    await dashboardStore.fetchDashboardData()
    await fetchRecentOrders()
    await fetchAccountManagersPerformance()
    setupSalesChart()
  } catch (error) {
    console.error('Dashboard data fetch error:', error)
  }
}

const fetchRecentOrders = async () => {
  try {
    const response = await ordersStore.fetchOrders({ per_page: 10 })
    recentOrders.value = ordersStore.orders
  } catch (error) {
    console.error('Recent orders fetch error:', error)
  }
}

const fetchAccountManagersPerformance = async () => {
  // This would come from your custom API
  accountManagersPerformance.value = [
    { id: 1, name: 'احمد محمدی', customers_count: 15, total_sales: 25000, growth: 12.5 },
    { id: 2, name: 'سارا احمدی', customers_count: 20, total_sales: 35000, growth: -5.2 },
    { id: 3, name: 'علی رضایی', customers_count: 12, total_sales: 18000, growth: 8.7 }
  ]
}

const setupSalesChart = () => {
  // Mock data - replace with actual API data
  const months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور']
  const currentYearSales = [15000, 18000, 22000, 19000, 25000, 28000]
  const lastYearSales = [12000, 15000, 18000, 16000, 20000, 23000]

  salesChartData.value = {
    series: [
      {
        name: 'امسال',
        data: currentYearSales
      },
      {
        name: 'سال گذشته',
        data: lastYearSales
      }
    ]
  }

  salesChartOptions.value.xaxis.categories = months
}

const formatCurrency = (value) => {
  return parseFloat(value || 0).toLocaleString()
}

const formatGrowth = (value) => {
  return parseFloat(value || 0).toFixed(1)
}

const formatDate = (dateString) => {
  return format(new Date(dateString), 'yyyy/MM/dd')
}

const getGrowthColor = (growth) => {
  return parseFloat(growth) >= 0 ? 'text-success' : 'text-error'
}

const getTopProductColor = (index) => {
  const colors = ['success', 'primary', 'warning', 'info', 'secondary']
  return colors[index] || 'primary'
}

const getOrderStatusColor = (status) => {
  const colors = {
    'pending': 'warning',
    'processing': 'info',
    'completed': 'success',
    'cancelled': 'error'
  }
  return colors[status] || 'secondary'
}

const getOrderStatusText = (status) => {
  const texts = {
    'pending': 'در انتظار',
    'processing': 'در حال پردازش',
    'completed': 'تکمیل شده',
    'cancelled': 'لغو شده'
  }
  return texts[status] || status
}

const getInitials = (name) => {
  return name.split(' ').map(n => n[0]).join('').toUpperCase()
}

// Initialize
onMounted(() => {
  fetchDashboardData()
})
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
