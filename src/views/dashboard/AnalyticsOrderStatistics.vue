<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Order Statistics</VCardTitle>
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
        <VCol cols="12" sm="6" md="2" class="d-flex align-center">
          <VCheckbox
            v-model="localFilters.compare"
            label="Compare"
            density="compact"
            hide-details
            @update:model-value="onCompareToggle"
          />
        </VCol>
      </VRow>

      <!-- Comparison Filters (when compare is enabled) -->
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
            @update:model-value="onCompareFilterChange"
          />
        </VCol>

        <!-- Compare Month Selector -->
        <VCol v-if="localFilters.compareFilterType === 'month'" cols="12" sm="6" md="3">
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

        <!-- Compare Year Selector -->
        <VCol v-if="localFilters.compareFilterType === 'year'" cols="12" sm="6" md="3">
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

        <!-- Compare Date Range Start -->
        <VCol v-if="localFilters.compareFilterType === 'date-range'" cols="12" sm="6" md="3">
          <VTextField
            v-model="localFilters.compareStartDate"
            type="date"
            label="Compare Start"
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
            label="Compare End"
            density="compact"
            variant="outlined"
            hide-details
            @change="onFilterChange"
          />
        </VCol>
      </VRow>

      <!-- Total Stats Display -->
      <div class="d-flex align-center justify-space-between mb-6">
        <div>
          <h3 class="text-h3 mb-1">{{ totalStats.totalOrdersFormatted }}</h3>
          <div class="text-caption text-medium-emphasis">
            Total Orders - {{ totalStats.periodLabel || 'All Time' }}
          </div>
          <div v-if="brandsList.length > 0" class="text-caption text-medium-emphasis mt-1">
            {{ brandsList.length }} brands
          </div>
          <div v-if="totalStats.hasComparison" class="mt-2">
            <VChip
              :color="getGrowthColor(totalStats.ordersGrowth)"
              size="small"
              variant="tonal"
            >
              {{ totalStats.ordersGrowthFormatted }}%
            </VChip>
            <span class="text-caption text-medium-emphasis ms-2">vs {{ totalStats.comparePeriodLabel }}</span>
          </div>
        </div>

        <!-- Donut Chart -->
        <div v-if="chartData.series.length > 0" class="text-center">
          <VueApexCharts
            type="donut"
            :height="140"
            :width="140"
            :options="chartOptions"
            :series="chartData.series"
          />
          <div class="text-caption text-medium-emphasis mt-1">
            Top {{ chartData.series.length }} Brands
          </div>
        </div>
        <div v-else class="text-caption text-medium-emphasis">No Data</div>
      </div>

      <!-- Comparison Summary (when compare is enabled) -->
      <VAlert
        v-if="totalStats.hasComparison"
        :color="getGrowthColor(totalStats.revenueGrowth)"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        <div class="d-flex align-center justify-space-between">
          <div>
            <div class="font-weight-medium">Revenue Growth</div>
            <div class="text-caption">
              {{ totalStats.totalRevenueFormatted }} ({{ totalStats.periodLabel }})
              vs
              ${{ totalStats.compareTotalRevenue.toLocaleString('en-US') }} ({{ totalStats.comparePeriodLabel }})
            </div>
          </div>
          <div class="text-h6">{{ totalStats.revenueGrowthFormatted }}%</div>
        </div>
      </VAlert>

      <!-- Brands List -->
      <VList class="card-list">
        <VListItem
          v-for="(brand, index) in displayedBrands"
          :key="brand.brandId || brand.brandSlug || index"
        >
          <template #prepend>
            <VAvatar
              size="40"
              rounded
              variant="tonal"
              :color="brand.avatarColor"
            >
              <VIcon icon="bx-purchase-tag" />
            </VAvatar>
          </template>

          <VListItemTitle class="font-weight-medium">
            {{ brand.brand }}
          </VListItemTitle>
          
          <VListItemSubtitle class="text-body-2">
            <div class="d-flex align-center gap-2">
              <span>{{ brand.revenueFormatted }}</span>
              <VChip
                v-if="brand.hasComparison"
                :color="getGrowthColor(brand.revenueGrowth)"
                size="x-small"
                variant="tonal"
              >
                {{ brand.revenueGrowthFormatted }}%
              </VChip>
            </div>
          </VListItemSubtitle>

          <template #append>
            <div class="text-end">
              <div class="font-weight-medium">{{ brand.ordersFormatted }}</div>
              <div v-if="brand.hasComparison" class="text-caption">
                <VChip
                  :color="getGrowthColor(brand.ordersGrowth)"
                  size="x-small"
                  variant="tonal"
                >
                  {{ brand.ordersGrowthFormatted }}%
                </VChip>
              </div>
            </div>
          </template>
        </VListItem>

        <VListItem v-if="brandsList.length === 0 && !loading">
          <VListItemTitle class="text-center text-medium-emphasis">
            No data available
          </VListItemTitle>
        </VListItem>

        <!-- Load More Button -->
        <VListItem v-if="brandsList.length > itemsToShow && !showAll">
          <VBtn
            block
            variant="outlined"
            color="primary"
            size="small"
            @click="showAll = true"
          >
            Load More ({{ brandsList.length - itemsToShow }} more)
          </VBtn>
        </VListItem>
      </VList>
    </VCardText>
  </VCard>
</template>

<script setup>
import { useOrderStatistics } from '@/composables/useOrderStatistics'
import { hexToRgb } from '@core/utils/colorConverter'
import { computed, onMounted, reactive, ref } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'

const vuetifyTheme = useTheme()
const {
  orderStatistics,
  orderStatisticsFilters,
  loading,
  getDonutChartData,
  getBrandsList,
  getTotalStats,
  getGrowthColor,
  fetchOrderStatistics,
  updateFilters,
} = useOrderStatistics()

// Show/hide state for load more
const showAll = ref(false)
const itemsToShow = 5  // تغییر از 10 به 5

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

// Year options (last 5 years)
const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear()
  const years = []
  for (let i = 0; i < 5; i++) {
    years.push({ title: (currentYear - i).toString(), value: (currentYear - i).toString() })
  }
  return years
})

// Computed data
const chartData = computed(() => getDonutChartData.value)
const brandsList = computed(() => getBrandsList.value)
const totalStats = computed(() => getTotalStats.value)

// Display limited or all brands
const displayedBrands = computed(() => {
  if (showAll.value) {
    return brandsList.value
  }
  return brandsList.value.slice(0, itemsToShow)
})

// Chart options
const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const secondaryTextColor = `rgba(${hexToRgb(String(currentTheme['on-surface']))},${variableTheme['medium-emphasis-opacity']})`
  const primaryTextColor = `rgba(${hexToRgb(String(currentTheme['on-surface']))},${variableTheme['high-emphasis-opacity']})`

  return {
    chart: {
      sparkline: { enabled: true },
      animations: { enabled: false },
    },
    stroke: {
      width: 6,
      colors: [currentTheme.surface],
    },
    legend: { show: false },
    tooltip: {
      enabled: true,
      y: {
        formatter: (value) => `${value} orders`,
      },
    },
    dataLabels: { enabled: false },
    labels: chartData.value.labels,
    colors: [
      currentTheme.primary,
      currentTheme.success,
      currentTheme.warning,
      currentTheme.info,
      currentTheme.error,
    ],
    grid: {
      padding: { top: -7, bottom: 5 },
    },
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } },
    },
    plotOptions: {
      pie: {
        expandOnClick: false,
        donut: {
          size: '75%',
          labels: {
            show: true,
            name: {
              offsetY: 17,
              fontSize: '13px',
              color: secondaryTextColor,
              fontFamily: 'Public Sans',
            },
            value: {
              offsetY: -17,
              fontSize: '18px',
              color: primaryTextColor,
              fontFamily: 'Public Sans',
              fontWeight: 500,
            },
            total: {
              show: true,
              label: 'Top 5 Brands',
              fontSize: '13px',
              formatter: () => {
                // Show sum of top 5 brands only
                const sum = chartData.value.series.reduce((s, v) => s + v, 0)
                return sum.toString()
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

// Handlers
function onFilterTypeChange() {
  // Reset filter value when type changes
  if (localFilters.filterType === 'month') {
    const now = new Date()
    localFilters.filterValue = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  } else if (localFilters.filterType === 'year') {
    localFilters.filterValue = new Date().getFullYear().toString()
  } else if (localFilters.filterType === 'date-range') {
    localFilters.startDate = null
    localFilters.endDate = null
  }
  
  onFilterChange()
}

function onCompareToggle() {
  if (localFilters.compare) {
    // Initialize compare filters
    localFilters.compareFilterType = 'month'
    const now = new Date()
    const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    localFilters.compareFilterValue = `${lastMonth.getFullYear()}-${String(lastMonth.getMonth() + 1).padStart(2, '0')}`
  }
  
  onFilterChange()
}

function onCompareFilterChange() {
  // Reset compare filter value when type changes
  if (localFilters.compareFilterType === 'month') {
    const now = new Date()
    const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    localFilters.compareFilterValue = `${lastMonth.getFullYear()}-${String(lastMonth.getMonth() + 1).padStart(2, '0')}`
  } else if (localFilters.compareFilterType === 'year') {
    localFilters.compareFilterValue = (new Date().getFullYear() - 1).toString()
  } else if (localFilters.compareFilterType === 'date-range') {
    localFilters.compareStartDate = null
    localFilters.compareEndDate = null
  }
  
  onFilterChange()
}

function onFilterChange() {
  // Validate date range
  if (localFilters.filterType === 'date-range') {
    if (!localFilters.startDate || !localFilters.endDate) {
      return // Wait for both dates
    }
  }

  if (localFilters.compare && localFilters.compareFilterType === 'date-range') {
    if (!localFilters.compareStartDate || !localFilters.compareEndDate) {
      return // Wait for both compare dates
    }
  }

  // Reset show all when filter changes
  showAll.value = false

  // Update store filters
  updateFilters(localFilters)
  
  // Fetch data
  fetchOrderStatistics()
}

function refreshData() {
  showAll.value = false
  fetchOrderStatistics()
}

// Initialize
onMounted(() => {
  // Set initial filter value
  if (localFilters.filterType === 'year') {
    localFilters.filterValue = new Date().getFullYear().toString()
  } else if (localFilters.filterType === 'month') {
    const now = new Date()
    localFilters.filterValue = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  }

  // Update store and fetch
  updateFilters(localFilters)
  fetchOrderStatistics()
})
</script>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 1.25rem;
}
</style>
