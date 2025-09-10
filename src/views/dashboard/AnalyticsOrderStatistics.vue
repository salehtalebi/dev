<script setup>
import { useDashboardStore } from '@/stores/dashboard'
import { hexToRgb } from '@core/utils/colorConverter'
import { computed, onMounted } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'

const vuetifyTheme = useTheme()
const dashboardStore = useDashboardStore()

// Derive top categories from existing topProducts (assumes each product has category_name & category_id)
const topCategories = computed(() => {
  const list = dashboardStore.topProducts || []
  if (list.length === 0) return []
  const map = new Map()
  list.forEach(p => {
    const cat = p.category_name || p.category || p.product_category || 'Uncategorized'
    const sold = parseInt(p.total_sold || p.total_quantity || p.units_sold || p.quantity || 0)
    const revenue = parseFloat(p.total_revenue || p.revenue || p.sales_amount || 0)
    const current = map.get(cat) || { category: cat, units: 0, revenue: 0 }
    current.units += isNaN(sold) ? 0 : sold
    current.revenue += isNaN(revenue) ? 0 : revenue
    map.set(cat, current)
  })
  return Array.from(map.values()).sort((a,b) => b.units - a.units).slice(0,5)
})

// Total orders from stats
const totalOrders = computed(() => {
  const direct = dashboardStore.stats.totalOrders || 0
  if (direct > 0) return direct
  // Fallback: sum of units of categories (approx) if stats not loaded yet
  const sumUnits = topCategories.value.reduce((s,c)=>s+c.units,0)
  return sumUnits
})

// Chart series & labels
const series = computed(() => topCategories.value.map(c => c.units))
const labels = computed(() => topCategories.value.map(c => c.category))

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const secondaryTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['medium-emphasis-opacity'] })`
  const primaryTextColor = `rgba(${ hexToRgb(String(currentTheme['on-surface'])) },${ variableTheme['high-emphasis-opacity'] })`

  return {
    chart: { sparkline: { enabled: true }, animations: { enabled: false } },
    stroke: { width: 6, colors: [currentTheme.surface] },
    legend: { show: false },
    tooltip: { enabled: true },
    dataLabels: { enabled: false },
    labels: labels.value,
    colors: [
      currentTheme.primary,
      currentTheme.success,
      currentTheme.warning,
      currentTheme.info,
      currentTheme.error,
    ],
    grid: { padding: { top: -7, bottom: 5 } },
    states: { hover: { filter: { type: 'none' } }, active: { filter: { type: 'none' } } },
    plotOptions: {
      pie: {
        expandOnClick: false,
        donut: {
          size: '75%',
          labels: {
            show: true,
            name: { offsetY: 17, fontSize: '13px', color: secondaryTextColor, fontFamily: 'Public Sans' },
            value: { offsetY: -17, fontSize: '18px', color: primaryTextColor, fontFamily: 'Public Sans', fontWeight: 500 },
            total: {
              show: true,
              label: 'Total',
              fontSize: '13px',
              lineHeight: '18px',
              formatter: () => {
                const sum = series.value.reduce((s,v)=>s+v,0)
                return sum
              },
              color: secondaryTextColor,
              fontFamily: 'Public Sans',
            },
          },
        },
      },
    },
  }
})

// List items from categories
const orders = computed(() => topCategories.value.map((c, idx) => ({
  amount: c.units.toLocaleString('en-US'),
  title: c.category,
  avatarColor: ['primary','success','warning','info','error'][idx % 5],
  subtitle: `$${c.revenue.toLocaleString('en-US')}`,
  avatarIcon: 'bx-category'
})))

const moreList = [
  { title: 'Refresh', value: 'Refresh' },
]

onMounted(async () => {
  if (dashboardStore.topProducts.length === 0) {
    await dashboardStore.fetchTopProducts()
  }
  if (!dashboardStore.stats.totalOrders) {
    await dashboardStore.fetchDashboardStats()
  }
})
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>
        Order Statistics
      </VCardTitle>
  <!-- Removed hardcoded total sales subtitle per requirement -->

      <template #append>
        <MoreBtn :menu-list="moreList" />
      </template>
    </VCardItem>

    <VCardText>
      <div class="d-flex align-center justify-space-between mb-6">
        <div>
          <h3 class="text-h3 mb-1">{{ totalOrders.toLocaleString('en-US') }}</h3>
          <div class="text-caption text-medium-emphasis">Total Orders</div>
        </div>
        <div v-if="series.length && series.reduce((s,v)=>s+v,0) > 0">
          <VueApexCharts
            type="donut"
            :height="120"
            width="100"
            :options="chartOptions"
            :series="series"
          />
        </div>
        <div v-else class="text-caption text-medium-emphasis">No Data</div>
      </div>

      <VList class="card-list">
        <VListItem
          v-for="order in orders"
          :key="order.title"
        >
          <template #prepend>
            <VAvatar
              size="40"
              rounded
              variant="tonal"
              :color="order.avatarColor"
            >
              <VIcon :icon="order.avatarIcon" />
            </VAvatar>
          </template>

          <VListItemTitle class="font-weight-medium">
            {{ order.title }}
          </VListItemTitle>
          <VListItemSubtitle class="text-body-2">
            {{ order.subtitle }}
          </VListItemSubtitle>

          <template #append>
            <span>{{ order.amount }}</span>
          </template>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<style lang="scss">
.card-list {
  --v-card-list-gap: 1.25rem;
}
</style>
