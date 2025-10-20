<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Revenue</VCardTitle>
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
        <VCol cols="12" sm="4" md="3">
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

        <!-- Month Selector (shown when filterType is 'month') -->
        <VCol v-if="localFilters.filterType === 'month'" cols="12" sm="4" md="3">
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

        <!-- Year Selector (shown when filterType is 'year') -->
        <VCol v-if="localFilters.filterType === 'year'" cols="12" sm="4" md="3">
          <VSelect
            v-model="localFilters.filterValue"
            :items="yearOptions"
            label="Select Year"
            density="compact"
            variant="outlined"
            hide-details
            @update:model-value="onFilterChange"
          />
        </VCol>

        <!-- Compare Checkbox -->
        <VCol cols="12" sm="4" md="2" class="d-flex align-center">
          <VCheckbox
            v-model="localFilters.compare"
            label="Compare"
            density="compact"
            hide-details
            @update:model-value="onCompareToggle"
          />
        </VCol>

        <!-- Compare Mode Selector (shown when compare is enabled) -->
        <VCol v-if="localFilters.compare" cols="12" sm="4" md="3">
          <VSelect
            v-model="localFilters.compareMode"
            :items="compareModeOptions"
            label="Compare Mode"
            density="compact"
            variant="outlined"
            hide-details
            @update:model-value="onCompareModeChange"
          />
        </VCol>

        <!-- Custom Compare Filter Type (shown when compareMode is 'custom') -->
        <VCol v-if="localFilters.compare && localFilters.compareMode === 'custom'" cols="12" sm="4" md="2">
          <VSelect
            v-model="localFilters.compareFilterType"
            :items="filterTypeOptions"
            label="Compare Period"
            density="compact"
            variant="outlined"
            hide-details
            @update:model-value="onFilterChange"
          />
        </VCol>

        <!-- Custom Month Selector (shown when compareMode is 'custom' and compareFilterType is 'month') -->
        <VCol v-if="localFilters.compare && localFilters.compareMode === 'custom' && localFilters.compareFilterType === 'month'" cols="12" sm="4" md="3">
          <VTextField
            v-model="localFilters.compareFilterValue"
            type="month"
            label="Compare Month"
            density="compact"
            variant="outlined"
            hide-details
            @change="onFilterChange"
          />
        </VCol>

        <!-- Custom Year Selector (shown when compareMode is 'custom' and compareFilterType is 'year') -->
        <VCol v-if="localFilters.compare && localFilters.compareMode === 'custom' && localFilters.compareFilterType === 'year'" cols="12" sm="4" md="2">
          <VSelect
            v-model="localFilters.compareFilterValue"
            :items="yearOptions"
            label="Compare Year"
            density="compact"
            variant="outlined"
            hide-details
            @update:model-value="onFilterChange"
          />
        </VCol>
      </VRow>

      <!-- Revenue Summary -->
      <div class="d-flex align-center mb-4">
        <h2 class="text-h2 text-primary me-4">
          ${{ formatCurrency(currentRevenue) }}
        </h2>
        <VChip
          v-if="revenueGrowth !== null"
          :color="revenueGrowth >= 0 ? 'success' : 'error'"
          variant="tonal"
          size="small"
        >
          <VIcon
            :icon="revenueGrowth >= 0 ? 'bx-trending-up' : 'bx-trending-down'"
            start
          />
          {{ Math.abs(revenueGrowth).toFixed(1) }}%
        </VChip>
      </div>

      <!-- Comparison Summary (if compare mode is active) -->
      <div v-if="localFilters.compare && comparisonSummary" class="mb-4">
        <VRow dense>
          <VCol cols="auto">
            <div class="text-caption text-medium-emphasis">{{ currentPeriodLabel }}</div>
            <div class="text-subtitle-1 font-weight-medium">${{ formatCurrency(comparisonSummary.primary_total) }}</div>
          </VCol>
          <VCol cols="auto">
            <div class="text-caption text-medium-emphasis">{{ comparePeriodLabel }}</div>
            <div class="text-subtitle-1">${{ formatCurrency(comparisonSummary.compare_total) }}</div>
          </VCol>
          <VCol cols="auto">
            <div class="text-caption text-medium-emphasis">Difference</div>
            <div class="text-subtitle-1" :class="comparisonSummary.difference >= 0 ? 'text-success' : 'text-error'">
              ${{ formatCurrency(Math.abs(comparisonSummary.difference)) }}
            </div>
          </VCol>
        </VRow>
      </div>

      <!-- Chart -->
      <VueApexCharts
        v-if="chartData.series[0].data.length > 0"
        type="area"
        height="270"
        :options="chartOptions"
        :series="chartData.series"
      />
      
      <!-- No Data State -->
      <div v-else class="text-center py-8">
        <VIcon
          icon="bx-bar-chart"
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
  getRevenueChartData, 
  revenueComparisonSummary,
  revenueFilters,
  refreshRevenueData,
  updateRevenueFilters
} = useAnalytics()

// Local filter state
const localFilters = ref({
  filterType: 'year',
  filterValue: new Date().getFullYear().toString(),
  compare: false,
  compareMode: 'last_year', // 'last_year', 'previous_month', 'custom'
  compareFilterType: 'year', // Used when compareMode is 'custom'
  compareFilterValue: null, // Used when compareMode is 'custom'
})

// Filter options
const filterTypeOptions = [
  { title: 'Monthly', value: 'month' },
  { title: 'Yearly', value: 'year' },
]

const compareModeOptions = [
  { title: 'Same Period Last Year', value: 'last_year' },
  { title: 'Previous Month', value: 'previous_month' },
  { title: 'Custom Period', value: 'custom' },
]

// Generate year options (last 5 years + current year)
const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear()
  const years = []
  for (let i = 0; i <= 5; i++) {
    years.push({ title: (currentYear - i).toString(), value: (currentYear - i).toString() })
  }
  return years
})

// Initialize with current year and month
const initializeFilters = () => {
  const now = new Date()
  const currentYear = now.getFullYear()
  const currentMonth = String(now.getMonth() + 1).padStart(2, '0')
  
  localFilters.value = {
    filterType: 'year',
    filterValue: currentYear.toString(),
    compare: false,
    compareMode: 'last_year',
    compareFilterType: 'year',
    compareFilterValue: null,
  }
  
  // Load initial data
  onFilterChange()
}

// Initialize on mount
onMounted(() => {
  initializeFilters()
})

// Helper function to format period label
const formatPeriodLabel = (filterType, filterValue) => {
  if (!filterValue) return 'Period'
  
  if (filterType === 'year') {
    return filterValue
  } else if (filterType === 'month') {
    // Format: YYYY-MM to "Month YYYY"
    const [year, month] = filterValue.split('-')
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                        'July', 'August', 'September', 'October', 'November', 'December']
    const monthIndex = parseInt(month) - 1
    return `${monthNames[monthIndex]} ${year}`
  }
  
  return filterValue
}

// Get formatted labels for current and compare periods
const currentPeriodLabel = computed(() => {
  return formatPeriodLabel(localFilters.value.filterType, localFilters.value.filterValue)
})

const comparePeriodLabel = computed(() => {
  if (!localFilters.value.compare) return ''
  
  const compParams = getComparisonParams()
  if (!compParams) return 'Compare Period'
  
  return formatPeriodLabel(compParams.compareFilterType, compParams.compareFilterValue)
})

// Chart data
const chartData = computed(() => getRevenueChartData.value)

// Comparison summary
const comparisonSummary = computed(() => revenueComparisonSummary.value)

// Current revenue (sum of primary period or from comparison summary)
const currentRevenue = computed(() => {
  if (comparisonSummary.value) {
    return comparisonSummary.value.primary_total
  }
  if (chartData.value.series[0]?.data.length > 0) {
    return chartData.value.series[0].data.reduce((sum, val) => sum + val, 0)
  }
  return 0
})

// Revenue growth
const revenueGrowth = computed(() => {
  if (comparisonSummary.value) {
    return comparisonSummary.value.growth
  }
  return null
})

// Calculate comparison parameters based on compareMode
const getComparisonParams = () => {
  const { filterType, filterValue, compareMode, compareFilterType, compareFilterValue } = localFilters.value
  
  if (!filterValue) return null
  
  // Custom mode: use user-provided values
  if (compareMode === 'custom') {
    if (!compareFilterValue) return null
    return {
      compareFilterType: compareFilterType,
      compareFilterValue: compareFilterValue,
    }
  }
  
  // Previous month mode
  if (compareMode === 'previous_month') {
    if (filterType === 'month') {
      const [year, month] = filterValue.split('-')
      const date = new Date(parseInt(year), parseInt(month) - 1, 1)
      date.setMonth(date.getMonth() - 1)
      const prevYear = date.getFullYear()
      const prevMonth = String(date.getMonth() + 1).padStart(2, '0')
      return {
        compareFilterType: 'month',
        compareFilterValue: `${prevYear}-${prevMonth}`,
      }
    }
    // For year filter, previous month doesn't make sense
    return null
  }
  
  // Last year mode (default)
  if (filterType === 'year') {
    const year = parseInt(filterValue)
    return {
      compareFilterType: 'year',
      compareFilterValue: (year - 1).toString(),
    }
  } else if (filterType === 'month') {
    const [year, month] = filterValue.split('-')
    return {
      compareFilterType: 'month',
      compareFilterValue: `${parseInt(year) - 1}-${month}`,
    }
  }
  
  return null
}

// Filter change handlers
const onFilterTypeChange = () => {
  // Auto-set appropriate value when changing filter type
  const now = new Date()
  if (localFilters.value.filterType === 'month') {
    const year = now.getFullYear()
    const month = String(now.getMonth() + 1).padStart(2, '0')
    localFilters.value.filterValue = `${year}-${month}`
  } else {
    localFilters.value.filterValue = now.getFullYear().toString()
  }
  onFilterChange()
}

const onCompareModeChange = () => {
  // When compare mode changes, reset custom values if needed
  if (localFilters.value.compareMode === 'custom') {
    localFilters.value.compareFilterType = localFilters.value.filterType
    localFilters.value.compareFilterValue = null
  }
  onFilterChange()
}

const onFilterChange = () => {
  // Build filter params
  const params = {
    filterType: localFilters.value.filterType,
    filterValue: localFilters.value.filterValue,
    compare: localFilters.value.compare,
  }
  
  // Add comparison parameters if compare is enabled
  if (params.compare) {
    const compParams = getComparisonParams()
    if (compParams) {
      params.compareFilterType = compParams.compareFilterType
      params.compareFilterValue = compParams.compareFilterValue
    }
  }
  
  console.log('Revenue filter params:', params)
  
  // Apply filters to store and fetch data
  updateRevenueFilters(params)
}

const onCompareToggle = () => {
  if (!localFilters.value.compare) {
    // Reset compare mode when disabled
    localFilters.value.compareMode = 'last_year'
    localFilters.value.compareFilterValue = null
  }
  onFilterChange()
}

// Chart options
const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  
  const primaryColor = `rgba(${hexToRgb(String(currentTheme.primary))}, 1)`
  const secondaryColor = `rgba(${hexToRgb(String(currentTheme.secondary))}, 1)`
  const textColor = `rgba(${hexToRgb(String(currentTheme['on-surface']))}, ${variableTheme['high-emphasis-opacity']})`
  const borderColor = `rgba(${hexToRgb(String(variableTheme['border-color']))}, ${variableTheme['border-opacity']})`
  
  const hasComparison = chartData.value.series.length > 1
  
  return {
    chart: {
      parentHeightOffset: 0,
      toolbar: { show: false },
      sparkline: { enabled: false },
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800
      }
    },
    colors: hasComparison ? [primaryColor, secondaryColor] : [primaryColor],
    fill: {
      type: 'gradient',
      gradient: {
        shade: 'light',
        shadeIntensity: 0.8,
        opacityFrom: 0.8,
        opacityTo: 0.25,
        stops: [0, 95, 100]
      }
    },
    dataLabels: { enabled: false },
    stroke: {
      width: 3,
      curve: 'smooth'
    },
    grid: {
      show: true,
      borderColor: borderColor,
      strokeDashArray: 0,
      xaxis: { lines: { show: false } },
      yaxis: { lines: { show: true } },
      padding: {
        top: 0,
        right: 10,
        bottom: 0,
        left: 10
      }
    },
    xaxis: {
      categories: chartData.value.categories,
      labels: {
        style: {
          colors: textColor,
          fontSize: '12px'
        },
        rotate: -45,
        rotateAlways: chartData.value.categories.length > 12
      },
      axisBorder: { show: false },
      axisTicks: { show: false }
    },
    yaxis: {
      labels: {
        style: {
          colors: textColor,
          fontSize: '12px'
        },
        formatter: value => `$${formatCurrency(value)}`
      }
    },
    tooltip: {
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
      y: {
        formatter: value => `$${formatCurrency(value)}`
      }
    },
    legend: {
      show: hasComparison,
      position: 'top',
      horizontalAlign: 'left',
      labels: {
        colors: textColor
      }
    },
    markers: {
      size: 0,
      strokeColors: hasComparison ? [primaryColor, secondaryColor] : [primaryColor],
      strokeWidth: 3,
      strokeOpacity: 1,
      fillOpacity: 1,
      hover: { size: 8 }
    }
  }
})

// Helper methods
const formatCurrency = (value) => {
  return parseFloat(value || 0).toLocaleString('en-US')
}

const refreshData = () => {
  refreshRevenueData()
}
</script>

<style lang="scss" scoped>
@use "@core/scss/template/libs/apex-chart.scss";
</style>
