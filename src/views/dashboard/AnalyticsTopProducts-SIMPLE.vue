<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Top Products</VCardTitle>
      <template #append>
        <VBtn
          icon
          size="x-small"
          color="default"
          variant="text"
          @click="refreshData"
          :loading="loading"
        >
          <VIcon
            size="20"
            icon="bx-refresh"
          />
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <!-- Filter Controls -->
      <div class="d-flex flex-wrap align-center gap-3 mb-4">
        <!-- Filter Type -->
        <VSelect
          v-model="localFilters.filterType"
          :items="filterTypes"
          label="Filter By"
          density="compact"
          style="max-width: 140px;"
          @update:model-value="onFilterChange"
        />

        <!-- Month Selector (only visible when filterType is 'month') -->
        <VTextField
          v-if="localFilters.filterType === 'month'"
          v-model="localFilters.filterValue"
          type="month"
          label="Select Month"
          density="compact"
          style="max-width: 180px;"
          @update:model-value="onFilterChange"
        />

        <!-- Year Selector (only visible when filterType is 'year') -->
        <VTextField
          v-if="localFilters.filterType === 'year'"
          v-model="localFilters.filterValue"
          type="number"
          label="Select Year"
          density="compact"
          style="max-width: 140px;"
          min="2020"
          :max="currentYear"
          @update:model-value="onFilterChange"
        />
      </div>

      <!-- Chart -->
      <div v-if="chartData.labels.length > 0">
        <VueApexCharts
          type="donut"
          height="350"
          :options="chartOptions"
          :series="chartData.series"
        />
      </div>
      
      <!-- No Data State -->
      <div v-else class="text-center py-8">
        <VIcon
          icon="bx-pie-chart"
          size="48"
          class="text-disabled mb-4"
        />
        <p class="text-medium-emphasis">No data available for display</p>
        <VBtn
          color="primary"
          variant="outlined"
          size="small"
          @click="refreshData"
          :loading="loading"
        >
          Retry
        </VBtn>
      </div>

      <!-- Product List -->
      <div v-if="topProducts.length > 0" class="mt-4">
        <VDivider class="mb-4" />
        <div
          v-for="(product, index) in topProducts.slice(0, 5)"
          :key="product.product_id"
          class="mb-3"
        >
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <VAvatar
                :color="getProductColor(index)"
                size="12"
                class="me-3"
              />
              <div>
                <div class="text-body-2 font-weight-medium">
                  {{ product.product_name || `Product ${product.product_id}` }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ product.total_sold }} units sold
                </div>
              </div>
            </div>
            <div class="text-end">
              <div class="text-body-2 font-weight-medium">
                ${{ formatCurrency(product.total_revenue) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>

<script setup>
import { useAnalytics } from '@/composables/useAnalytics'
import { hexToRgb } from '@core/utils/colorConverter'
import { computed, onMounted, ref } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'

const vuetifyTheme = useTheme()
const { 
  loading, 
  topProducts,
  getTopProductsChartData,
  refreshData,
  updateProductsFilters
} = useAnalytics()

// Current year for max validation
const currentYear = new Date().getFullYear()

// Filter options
const filterTypes = [
  { title: 'Month', value: 'month' },
  { title: 'Year', value: 'year' },
]

// Local filters state
const localFilters = ref({
  filterType: 'year',
  filterValue: currentYear.toString(),
})

// Initialize filters on mount
const initializeFilters = () => {
  const now = new Date()
  if (localFilters.value.filterType === 'year' && !localFilters.value.filterValue) {
    localFilters.value.filterValue = now.getFullYear().toString()
  } else if (localFilters.value.filterType === 'month' && !localFilters.value.filterValue) {
    const year = now.getFullYear()
    const month = String(now.getMonth() + 1).padStart(2, '0')
    localFilters.value.filterValue = `${year}-${month}`
  }
  
  // Load initial data
  onFilterChange()
}

// Handle filter changes
const onFilterChange = () => {
  console.log('Products Filter changed:', localFilters.value)
  
  // Build params object
  const params = {
    filterType: localFilters.value.filterType,
    filterValue: localFilters.value.filterValue,
  }
  
  // Update filters in store and fetch data
  updateProductsFilters(params)
}

onMounted(() => {
  initializeFilters()
})

// Helper methods
const formatCurrency = (value) => {
  return parseFloat(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const getProductColor = (index) => {
  const colors = ['primary', 'success', 'warning', 'info', 'error']
  return colors[index % colors.length]
}

<script setup>
import { useAnalytics } from '@/composables/useAnalytics'
import { hexToRgb } from '@core/utils/colorConverter'
import { computed, onMounted, ref } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'

const vuetifyTheme = useTheme()
const { 
  loading, 
  topProducts,
  productsComparisonSummary,
  getTopProductsChartData,
  refreshData,
  updateProductsFilters
} = useAnalytics()

// Current year for max validation
const currentYear = new Date().getFullYear()

// Filter options
const filterTypes = [
  { title: 'Month', value: 'month' },
  { title: 'Year', value: 'year' },
]

const compareOptions = [
  { title: 'Last Year', value: 'last_year' },
  { title: 'Two Years Ago', value: 'two_years_ago' },
]

// Local filters state
const localFilters = ref({
  filterType: 'year',
  filterValue: currentYear.toString(),
  compare: false,
  compareWith: 'last_year',
})

// Initialize filters on mount
const initializeFilters = () => {
  const now = new Date()
  if (localFilters.value.filterType === 'year' && !localFilters.value.filterValue) {
    localFilters.value.filterValue = now.getFullYear().toString()
  } else if (localFilters.value.filterType === 'month' && !localFilters.value.filterValue) {
    const year = now.getFullYear()
    const month = String(now.getMonth() + 1).padStart(2, '0')
    localFilters.value.filterValue = `${year}-${month}`
  }
  
  // Load initial data
  onFilterChange()
}

// Handle filter changes
const onFilterChange = () => {
  console.log('Products Filter changed:', localFilters.value)
  
  // Build params object
  const params = {
    filterType: localFilters.value.filterType,
    filterValue: localFilters.value.filterValue,
    compare: localFilters.value.compare,
    compareWith: localFilters.value.compareWith,
  }
  
  // Update filters in store and fetch data
  updateProductsFilters(params)
}

onMounted(() => {
  initializeFilters()
})

// Helper methods
const formatCurrency = (value) => {
  return parseFloat(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const formatGrowth = (value) => {
  const num = parseFloat(value || 0)
  return num >= 0 ? `+${num.toFixed(1)}` : num.toFixed(1)
}

const getGrowthColor = (value) => {
  return parseFloat(value || 0) >= 0 ? 'text-success' : 'text-error'
}

const getProductColor = (index) => {
  const colors = ['primary', 'success', 'warning', 'info', 'error']
  return colors[index % colors.length]
}

// Chart data
const chartData = computed(() => getTopProductsChartData.value)

// Chart options
const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  
  const colors = [
    `rgba(${hexToRgb(String(currentTheme.primary))}, 1)`,
    `rgba(${hexToRgb(String(currentTheme.success))}, 1)`,
    `rgba(${hexToRgb(String(currentTheme.warning))}, 1)`,
    `rgba(${hexToRgb(String(currentTheme.info))}, 1)`,
    `rgba(${hexToRgb(String(currentTheme.error))}, 1)`
  ]
  
  const textColor = `rgba(${hexToRgb(String(currentTheme['on-surface']))}, ${variableTheme['high-emphasis-opacity']})`
  
  return {
    chart: {
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800
      }
    },
    colors: colors,
    labels: chartData.value.labels,
    dataLabels: {
      enabled: true,
      style: {
        colors: [currentTheme.surface]
      },
      formatter: (val) => `${val.toFixed(1)}%`
    },
    legend: {
      show: false
    },
    stroke: {
      width: 0
    },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            name: {
              fontSize: '16px',
              fontWeight: 600,
              color: textColor
            },
            value: {
              fontSize: '24px',
              fontWeight: 600,
              color: textColor,
              formatter: (val) => `${val} units`
            },
            total: {
              show: true,
              fontSize: '14px',
              label: 'Total Sales',
              color: textColor,
              formatter: () => {
                const total = chartData.value.series.reduce((sum, val) => sum + val, 0)
                return `${total} units`
              }
            }
          }
        }
      }
    },
    tooltip: {
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
      y: {
        formatter: (val) => `${val} units sold`
      }
    },
    responsive: [{
      breakpoint: 480,
      options: {
        chart: {
          width: 300,
          height: 300
        }
      }
    }]
  }
})
</script>

<style lang="scss" scoped>
@use "@core/scss/template/libs/apex-chart.scss";
</style>
