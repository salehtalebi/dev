<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Account Managers Performance</VCardTitle>
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
      <!-- Filters Section -->
      <VRow class="mb-4" dense>
        <!-- Filter Type -->
        <VCol cols="12" sm="6" md="3">
          <VSelect
            v-model="localFilters.filterType"
            :items="filterTypeOptions"
            label="Period"
            density="compact"
            variant="outlined"
            hide-details
            @update:model-value="onFilterTypeChange"
          />
        </VCol>

        <!-- Month Selector -->
        <VCol v-if="localFilters.filterType === 'month'" cols="12" sm="6" md="3">
          <VTextField
            v-model="localFilters.filterValue"
            type="month"
            label="Select Month"
            density="compact"
            variant="outlined"
            hide-details
            @change="onFilterChange"
          />
        </VCol>

        <!-- Year Selector -->
        <VCol v-if="localFilters.filterType === 'year'" cols="12" sm="6" md="3">
          <VTextField
            v-model="localFilters.filterValue"
            type="number"
            label="Select Year"
            density="compact"
            variant="outlined"
            hide-details
            :min="2020"
            :max="new Date().getFullYear()"
            @change="onFilterChange"
          />
        </VCol>

        <!-- Date Range Start -->
        <VCol v-if="localFilters.filterType === 'date-range'" cols="12" sm="6" md="3">
          <VTextField
            v-model="localFilters.startDate"
            type="date"
            label="Start Date"
            density="compact"
            variant="outlined"
            hide-details
            @change="onFilterChange"
          />
        </VCol>

        <!-- Date Range End -->
        <VCol v-if="localFilters.filterType === 'date-range'" cols="12" sm="6" md="3">
          <VTextField
            v-model="localFilters.endDate"
            type="date"
            label="End Date"
            density="compact"
            variant="outlined"
            hide-details
            @change="onFilterChange"
          />
        </VCol>

        <!-- Compare Checkbox -->
        <VCol cols="12" sm="6" md="3">
          <VCheckbox
            v-model="localFilters.compare"
            label="Compare with"
            density="compact"
            hide-details
            @update:model-value="onCompareToggle"
          />
        </VCol>
      </VRow>

      <!-- Comparison Filters (shown when compare is enabled) -->
      <VRow v-if="localFilters.compare" class="mb-4" dense>
        <!-- Compare Filter Type -->
        <VCol cols="12" sm="6" md="3">
          <VSelect
            v-model="localFilters.compareFilterType"
            :items="compareFilterTypeOptions"
            label="Compare Period"
            density="compact"
            variant="outlined"
            hide-details
            @update:model-value="onCompareFilterTypeChange"
          />
        </VCol>

        <!-- Compare Month Selector -->
        <VCol v-if="localFilters.compareFilterType === 'month'" cols="12" sm="6" md="3">
          <VTextField
            v-model="localFilters.compareFilterValue"
            type="month"
            label="Select Month"
            density="compact"
            variant="outlined"
            hide-details
            @change="onFilterChange"
          />
        </VCol>

        <!-- Compare Year Selector -->
        <VCol v-if="localFilters.compareFilterType === 'year'" cols="12" sm="6" md="3">
          <VTextField
            v-model="localFilters.compareFilterValue"
            type="number"
            label="Select Year"
            density="compact"
            variant="outlined"
            hide-details
            :min="2020"
            :max="new Date().getFullYear()"
            @change="onFilterChange"
          />
        </VCol>

        <!-- Compare Date Range Start -->
        <VCol v-if="localFilters.compareFilterType === 'date-range'" cols="12" sm="6" md="3">
          <VTextField
            v-model="localFilters.compareStartDate"
            type="date"
            label="Start Date"
            density="compact"
            variant="outlined"
            hide-details
            @change="onFilterChange"
          />
        </VCol>

        <!-- Compare Date Range End -->
        <VCol v-if="localFilters.compareFilterType === 'date-range'" cols="12" sm="6" md="3">
          <VTextField
            v-model="localFilters.compareEndDate"
            type="date"
            label="End Date"
            density="compact"
            variant="outlined"
            hide-details
            @change="onFilterChange"
          />
        </VCol>
      </VRow>

      <!-- Chart -->
      <div v-if="chartData.categories.length > 0">
        <VueApexCharts
          type="bar"
          height="300"
          :options="chartOptions"
          :series="chartData.series"
        />
      </div>
      
      <!-- No Data State -->
      <div v-else class="text-center py-8">
        <VIcon
          icon="bx-user-circle"
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

      <!-- Manager Stats with Growth -->
      <div v-if="managerPerformance.length > 0" class="mt-4">
        <VDivider class="mb-4" />
        <VRow>
          <VCol
            v-for="manager in managerPerformance.slice(0, 3)"
            :key="manager.manager_id"
            cols="12"
            md="4"
          >
            <div class="text-center pa-2">
              <VAvatar
                :color="getManagerColor(manager.manager_id)"
                size="40"
                class="mb-2"
              >
                <VIcon icon="bx-user" />
              </VAvatar>
              <div class="text-body-2 font-weight-medium mb-1">
                {{ manager.manager_name }}
              </div>
              <div class="text-caption text-medium-emphasis mb-2">
                {{ manager.orders_count }} orders
                <VChip 
                  v-if="localFilters.compare && manager.orders_growth !== undefined"
                  :color="getGrowthColor(manager.orders_growth)" 
                  size="x-small" 
                  variant="tonal"
                  class="ml-1"
                >
                  {{ formatGrowth(manager.orders_growth) }}%
                </VChip>
              </div>
              <VChip
                :color="getManagerColor(manager.manager_id)"
                variant="tonal"
                size="small"
              >
                ${{ formatCurrency(manager.revenue) }}
                <VIcon 
                  v-if="localFilters.compare && manager.revenue_growth !== undefined"
                  :icon="manager.revenue_growth >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt'"
                  :color="manager.revenue_growth >= 0 ? 'success' : 'error'"
                  size="16"
                  class="ml-1"
                />
              </VChip>
            </div>
          </VCol>
        </VRow>
      </div>
    </VCardText>
  </VCard>
</template>

<script setup>
import { useAnalytics } from '@/composables/useAnalytics'
import { hexToRgb } from '@core/utils/colorConverter'
import { computed, onMounted, reactive } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'

const vuetifyTheme = useTheme()
const {
  loading,
  managerPerformance,
  managerPerformanceFilters,
  getManagerPerformanceData,
  updateManagerPerformanceFilters,
  refreshManagerPerformance
} = useAnalytics()

// Local filters for UI
const localFilters = reactive({
  filterType: 'year',
  filterValue: new Date().getFullYear().toString(),
  startDate: null,
  endDate: null,
  compare: false,
  compareFilterType: 'month',
  compareFilterValue: null,
  compareStartDate: null,
  compareEndDate: null,
})

// Filter options
const filterTypeOptions = [
  { title: 'Month', value: 'month' },
  { title: 'Year', value: 'year' },
  { title: 'Date Range', value: 'date-range' },
]

const compareFilterTypeOptions = [
  { title: 'Month', value: 'month' },
  { title: 'Year', value: 'year' },
  { title: 'Date Range', value: 'date-range' },
]

// Chart data
const chartData = computed(() => getManagerPerformanceData.value)

// Chart options
const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  
  const colors = [
    `rgba(${hexToRgb(String(currentTheme.primary))}, 1)`,
    `rgba(${hexToRgb(String(currentTheme.success))}, 1)`,
    `rgba(${hexToRgb(String(currentTheme.warning))}, 0.7)`,
    `rgba(${hexToRgb(String(currentTheme.info))}, 0.7)`,
  ]
  
  const textColor = `rgba(${hexToRgb(String(currentTheme['on-surface']))}, ${variableTheme['high-emphasis-opacity']})`
  const borderColor = `rgba(${hexToRgb(String(variableTheme['border-color']))}, ${variableTheme['border-opacity']})`
  
  return {
    chart: {
      type: 'bar',
      parentHeightOffset: 0,
      toolbar: { show: false },
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800
      }
    },
    colors: colors,
    dataLabels: { enabled: false },
    stroke: {
      width: 0
    },
    legend: {
      show: true,
      position: 'top',
      horizontalAlign: 'left',
      labels: {
        colors: textColor
      }
    },
    grid: {
      show: true,
      borderColor: borderColor,
      strokeDashArray: 0,
      xaxis: { lines: { show: false } },
      yaxis: { lines: { show: true } }
    },
    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: '60%',
        borderRadius: 4,
        dataLabels: {
          position: 'top'
        }
      }
    },
    xaxis: {
      categories: chartData.value.categories,
      labels: {
        style: {
          colors: textColor,
          fontSize: '12px'
        }
      },
      axisBorder: { show: false },
      axisTicks: { show: false }
    },
    yaxis: [
      {
        title: {
          text: 'Orders Count',
          style: {
            color: textColor,
            fontSize: '12px'
          }
        },
        labels: {
          style: {
            colors: textColor,
            fontSize: '12px'
          }
        }
      },
      {
        opposite: true,
        title: {
          text: 'Revenue ($)',
          style: {
            color: textColor,
            fontSize: '12px'
          }
        },
        labels: {
          style: {
            colors: textColor,
            fontSize: '12px'
          },
          formatter: value => `$${formatCurrency(value)}`
        }
      }
    ],
    tooltip: {
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
      shared: true,
      intersect: false,
      y: {
        formatter: (val, opts) => {
          // Check if it's an orders series or revenue series based on series name
          const seriesName = opts.seriesIndex !== undefined ? chartData.value.series[opts.seriesIndex]?.name : ''
          if (seriesName && seriesName.toLowerCase().includes('revenue')) {
            return `$${formatCurrency(val)}`
          }
          return `${val} orders`
        }
      }
    }
  }
})

// Handlers
function onFilterTypeChange() {
  // Reset filter value when type changes
  if (localFilters.filterType === 'month') {
    const now = new Date()
    localFilters.filterValue = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  } else if (localFilters.filterType === 'year') {
    localFilters.filterValue = new Date().getFullYear().toString()
  } else {
    localFilters.startDate = null
    localFilters.endDate = null
  }
  onFilterChange()
}

function onCompareToggle() {
  if (localFilters.compare && !localFilters.compareFilterType) {
    localFilters.compareFilterType = 'month'
    onCompareFilterTypeChange()
  } else {
    onFilterChange()
  }
}

function onCompareFilterTypeChange() {
  // Set default values based on compare filter type
  if (localFilters.compareFilterType === 'month') {
    const lastMonth = new Date()
    lastMonth.setMonth(lastMonth.getMonth() - 1)
    localFilters.compareFilterValue = `${lastMonth.getFullYear()}-${String(lastMonth.getMonth() + 1).padStart(2, '0')}`
  } else if (localFilters.compareFilterType === 'year') {
    localFilters.compareFilterValue = (new Date().getFullYear() - 1).toString()
  } else {
    localFilters.compareStartDate = null
    localFilters.compareEndDate = null
  }
  onFilterChange()
}

function onFilterChange() {
  updateManagerPerformanceFilters(localFilters)
}

function refreshData() {
  refreshManagerPerformance()
}

// Helper methods
const formatCurrency = (value) => {
  return parseFloat(value || 0).toLocaleString('en-US')
}

const formatGrowth = (value) => {
  const num = parseFloat(value || 0)
  return num >= 0 ? `+${num.toFixed(1)}` : num.toFixed(1)
}

const getGrowthColor = (growth) => {
  return parseFloat(growth) >= 0 ? 'success' : 'error'
}

const getManagerColor = (managerId) => {
  const colors = ['primary', 'success', 'warning', 'info', 'error']
  const index = parseInt(managerId) || 0
  return colors[index % colors.length]
}

// Initialize on mount
onMounted(() => {
  // Set initial filter value based on type
  if (localFilters.filterType === 'year') {
    localFilters.filterValue = new Date().getFullYear().toString()
  } else if (localFilters.filterType === 'month') {
    const now = new Date()
    localFilters.filterValue = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  }
  
  // Apply initial filters
  updateManagerPerformanceFilters(localFilters)
})
</script>

<style lang="scss" scoped>
@use "@core/scss/template/libs/apex-chart.scss";
</style>
