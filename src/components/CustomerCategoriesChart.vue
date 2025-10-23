<template>
  <VCard>
    <VCardTitle class="d-flex justify-space-between align-center">
      <span>Top Brands</span>
      <VBtn
        icon
        size="small"
        variant="text"
        @click="showFilters = !showFilters"
      >
        <VIcon :icon="showFilters ? 'bx-chevron-up' : 'bx-filter'" />
      </VBtn>
    </VCardTitle>

    <!-- Filters Section -->
    <VExpandTransition>
      <VCardText v-show="showFilters" class="pt-0">
        <VRow>
          <!-- Filter Type -->
          <VCol cols="12" md="4">
            <VSelect
              v-model="localFilters.filterType"
              :items="filterTypes"
              label="Period Type"
              density="compact"
              @update:model-value="onFilterTypeChange"
            />
          </VCol>

          <!-- Filter Value (Month Picker) -->
          <VCol v-if="localFilters.filterType === 'month'" cols="12" md="4">
            <VTextField
              v-model="localFilters.filterValue"
              type="month"
              label="Select Month"
              density="compact"
              @update:model-value="applyFilters"
            />
          </VCol>

          <!-- Filter Value (Year Picker) -->
          <VCol v-if="localFilters.filterType === 'year'" cols="12" md="4">
            <VSelect
              v-model="localFilters.filterValue"
              :items="yearOptions"
              label="Select Year"
              density="compact"
              @update:model-value="applyFilters"
            />
          </VCol>

          <!-- Date Range Start -->
          <VCol v-if="localFilters.filterType === 'date-range'" cols="12" md="4">
            <VTextField
              v-model="localFilters.startDate"
              type="date"
              label="Start Date"
              density="compact"
              @update:model-value="applyFilters"
            />
          </VCol>

          <!-- Date Range End -->
          <VCol v-if="localFilters.filterType === 'date-range'" cols="12" md="4">
            <VTextField
              v-model="localFilters.endDate"
              type="date"
              label="End Date"
              density="compact"
              @update:model-value="applyFilters"
            />
          </VCol>
        </VRow>

        <!-- View Toggle -->
        <VRow class="mt-2">
          <VCol cols="12">
            <VBtnToggle
              v-model="viewMode"
              mandatory
              density="compact"
              color="primary"
              divided
            >
              <VBtn value="top" min-width="100">Top 10</VBtn>
              <VBtn value="bottom" min-width="100">Bottom 10</VBtn>
              <VBtn value="both" min-width="100">Both</VBtn>
            </VBtnToggle>
          </VCol>
        </VRow>

        <!-- Comparison Toggle -->
        <VRow>
          <VCol cols="12">
            <VCheckbox
              v-model="localFilters.compare"
              label="Compare with another period"
              density="compact"
              hide-details
              @update:model-value="onCompareToggle"
            />
          </VCol>
        </VRow>

        <!-- Comparison Filters -->
        <VExpandTransition>
          <VRow v-if="localFilters.compare" class="mt-2">
            <!-- Compare Filter Type -->
            <VCol cols="12" md="4">
              <VSelect
                v-model="localFilters.compareFilterType"
                :items="filterTypes"
                label="Compare Period Type"
                density="compact"
                @update:model-value="onCompareFilterTypeChange"
              />
            </VCol>

            <!-- Compare Filter Value (Month) -->
            <VCol v-if="localFilters.compareFilterType === 'month'" cols="12" md="4">
              <VTextField
                v-model="localFilters.compareFilterValue"
                type="month"
                label="Compare Month"
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>

            <!-- Compare Filter Value (Year) -->
            <VCol v-if="localFilters.compareFilterType === 'year'" cols="12" md="4">
              <VSelect
                v-model="localFilters.compareFilterValue"
                :items="yearOptions"
                label="Compare Year"
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>

            <!-- Compare Date Range Start -->
            <VCol v-if="localFilters.compareFilterType === 'date-range'" cols="12" md="4">
              <VTextField
                v-model="localFilters.compareStartDate"
                type="date"
                label="Compare Start Date"
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>

            <!-- Compare Date Range End -->
            <VCol v-if="localFilters.compareFilterType === 'date-range'" cols="12" md="4">
              <VTextField
                v-model="localFilters.compareEndDate"
                type="date"
                label="Compare End Date"
                density="compact"
                @update:model-value="applyFilters"
              />
            </VCol>
          </VRow>
        </VExpandTransition>
      </VCardText>
    </VExpandTransition>

    <VDivider />

    <!-- Chart Display -->
    <VCardText>
      <div v-if="isLoading" class="text-center py-8">
        <VProgressCircular
          size="40"
          color="primary"
          indeterminate
        />
      </div>

      <div v-else-if="categories">
        <!-- Top Brands -->
        <div v-if="viewMode === 'top' || viewMode === 'both'">
          <h3 class="text-h6 mb-4">Top 10 Brands</h3>
          
          <div v-if="categories?.top_categories?.length">
            <VueApexCharts
              type="area"
              :height="300"
              :options="topChartOptions"
              :series="topChartSeries"
            />

            <!-- Top Brands Table -->
            <VTable density="compact" class="mt-4">
              <thead>
                <tr>
                  <th>Brand</th>
                  <th class="text-center">Orders</th>
                  <th class="text-end">Revenue</th>
                  <th v-if="localFilters.compare" class="text-center">Growth</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="category in categories.top_categories" :key="category.category">
                  <td class="font-weight-medium">{{ category.category }}</td>
                  <td class="text-center">
                    <VChip size="small" color="primary" variant="tonal">
                      {{ category.orders }}
                    </VChip>
                  </td>
                  <td class="text-end">${{ formatCurrency(category.revenue) }}</td>
                  <td v-if="localFilters.compare" class="text-center">
                    <VChip
                      v-if="category.revenue_growth !== undefined && category.revenue_growth !== null"
                      :color="category.revenue_growth >= 0 ? 'success' : 'error'"
                      size="small"
                      variant="tonal"
                    >
                      {{ category.revenue_growth >= 0 ? '+' : '' }}{{ category.revenue_growth.toFixed(1) }}%
                    </VChip>
                    <span v-else class="text-caption text-medium-emphasis">-</span>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
          
          <div v-else class="text-center py-8 text-medium-emphasis">
            No top brands data available
          </div>
        </div>

        <VDivider v-if="viewMode === 'both'" class="my-6" />

        <!-- Bottom Brands -->
        <div v-if="viewMode === 'bottom' || viewMode === 'both'">
          <h3 class="text-h6 mb-4">Bottom 10 Brands</h3>
          
          <div v-if="categories?.bottom_categories?.length">
            <VueApexCharts
              type="area"
              :height="300"
              :options="bottomChartOptions"
              :series="bottomChartSeries"
            />

            <!-- Bottom Brands Table -->
            <VTable density="compact" class="mt-4">
              <thead>
                <tr>
                  <th>Brand</th>
                  <th class="text-center">Orders</th>
                  <th class="text-end">Revenue</th>
                  <th v-if="localFilters.compare" class="text-center">Growth</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="category in categories.bottom_categories" :key="category.category">
                  <td class="font-weight-medium">{{ category.category }}</td>
                  <td class="text-center">
                    <VChip size="small" color="warning" variant="tonal">
                      {{ category.orders }}
                    </VChip>
                  </td>
                  <td class="text-end">${{ formatCurrency(category.revenue) }}</td>
                  <td v-if="localFilters.compare" class="text-center">
                    <VChip
                      v-if="category.revenue_growth !== undefined && category.revenue_growth !== null"
                      :color="category.revenue_growth >= 0 ? 'success' : 'error'"
                      size="small"
                      variant="tonal"
                    >
                      {{ category.revenue_growth >= 0 ? '+' : '' }}{{ category.revenue_growth.toFixed(1) }}%
                    </VChip>
                    <span v-else class="text-caption text-medium-emphasis">-</span>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
          
          <div v-else class="text-center py-8 text-medium-emphasis">
            No bottom brands data available
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8 text-medium-emphasis">
        No brands data available
      </div>
    </VCardText>
  </VCard>
</template>

<script setup>
import { useCustomersStore } from '@/stores/customers'
import { hexToRgb } from '@core/utils/colorConverter'
import { computed, onMounted, ref, watch } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'

const props = defineProps({
  customerId: {
    type: [Number, String],
    required: true,
  },
})

const customersStore = useCustomersStore()
const vuetifyTheme = useTheme()

const showFilters = ref(false)
const viewMode = ref('top')
const isLoading = ref(false)
const categories = ref(null)

// Filter Options
const filterTypes = [
  { title: 'Month', value: 'month' },
  { title: 'Year', value: 'year' },
  { title: 'Date Range', value: 'date-range' },
]

const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear()
  const years = []
  for (let i = currentYear; i >= currentYear - 10; i--) {
    years.push({ title: i.toString(), value: i.toString() })
  }
  return years
})

// Local Filters State
const localFilters = ref({
  filterType: 'year',
  filterValue: new Date().getFullYear().toString(),
  startDate: '',
  endDate: '',
  compare: false,
  compareFilterType: 'year',
  compareFilterValue: (new Date().getFullYear() - 1).toString(),
  compareStartDate: '',
  compareEndDate: '',
})

// Chart Data - Top Categories
const topChartSeries = computed(() => {
  if (!categories.value?.top_categories?.length) return []
  
  const series = [{
    name: 'Current Period',
    data: categories.value.top_categories.map(cat => parseFloat(cat.revenue)),
  }]
  
  // Add comparison series if compare is enabled
  if (localFilters.value.compare && categories.value.top_categories.some(cat => cat.compare_revenue !== undefined)) {
    series.push({
      name: 'Comparison Period',
      data: categories.value.top_categories.map(cat => parseFloat(cat.compare_revenue || 0)),
    })
  }
  
  return series
})

const topChartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  
  const primaryColor = `rgba(${hexToRgb(String(currentTheme.primary))}, 1)`
  const infoColor = `rgba(${hexToRgb(String(currentTheme.info))}, 1)`
  const textColor = `rgba(${hexToRgb(String(currentTheme['on-surface']))}, ${variableTheme['high-emphasis-opacity']})`
  const borderColor = `rgba(${hexToRgb(String(variableTheme['border-color']))}, ${variableTheme['border-opacity']})`
  
  const categoryNames = categories.value?.top_categories?.map(cat => cat.category) || []
  
  // Use different colors for comparison
  const colors = localFilters.value.compare ? [primaryColor, infoColor] : [primaryColor]
  
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
    colors: colors,
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
      categories: categoryNames,
      labels: {
        style: {
          colors: textColor,
          fontSize: '11px'
        },
        rotate: -45,
        rotateAlways: categoryNames.length > 6
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
        formatter: val => `$${val.toFixed(0)}`
      }
    },
    tooltip: {
      enabled: true,
      theme: 'dark',
      y: {
        formatter: val => `$${val.toFixed(2)}`
      }
    },
    legend: { 
      show: localFilters.value.compare,
      position: 'top',
      horizontalAlign: 'left',
      labels: {
        colors: textColor
      }
    }
  }
})

// Chart Data - Bottom Categories
const bottomChartSeries = computed(() => {
  if (!categories.value?.bottom_categories?.length) return []
  
  const series = [{
    name: 'Current Period',
    data: categories.value.bottom_categories.map(cat => parseFloat(cat.revenue)),
  }]
  
  // Add comparison series if compare is enabled
  if (localFilters.value.compare && categories.value.bottom_categories.some(cat => cat.compare_revenue !== undefined)) {
    series.push({
      name: 'Comparison Period',
      data: categories.value.bottom_categories.map(cat => parseFloat(cat.compare_revenue || 0)),
    })
  }
  
  return series
})

const bottomChartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  
  const secondaryColor = `rgba(${hexToRgb(String(currentTheme.secondary))}, 1)`
  const warningColor = `rgba(${hexToRgb(String(currentTheme.warning))}, 1)`
  const textColor = `rgba(${hexToRgb(String(currentTheme['on-surface']))}, ${variableTheme['high-emphasis-opacity']})`
  const borderColor = `rgba(${hexToRgb(String(variableTheme['border-color']))}, ${variableTheme['border-opacity']})`
  
  const categoryNames = categories.value?.bottom_categories?.map(cat => cat.category) || []
  
  // Use different colors for comparison
  const colors = localFilters.value.compare ? [secondaryColor, warningColor] : [secondaryColor]
  
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
    colors: colors,
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
      categories: categoryNames,
      labels: {
        style: {
          colors: textColor,
          fontSize: '11px'
        },
        rotate: -45,
        rotateAlways: categoryNames.length > 6
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
        formatter: val => `$${val.toFixed(0)}`
      }
    },
    tooltip: {
      enabled: true,
      theme: 'dark',
      y: {
        formatter: val => `$${val.toFixed(2)}`
      }
    },
    legend: { 
      show: localFilters.value.compare,
      position: 'top',
      horizontalAlign: 'left',
      labels: {
        colors: textColor
      }
    }
  }
})

// Helper function
const onFilterTypeChange = () => {
  if (localFilters.value.filterType === 'month') {
    const now = new Date()
    localFilters.value.filterValue = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  } else if (localFilters.value.filterType === 'year') {
    localFilters.value.filterValue = new Date().getFullYear().toString()
  } else if (localFilters.value.filterType === 'date-range') {
    const now = new Date()
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1)
    localFilters.value.startDate = firstDay.toISOString().split('T')[0]
    localFilters.value.endDate = now.toISOString().split('T')[0]
  }
  applyFilters()
}

const onCompareToggle = () => {
  if (localFilters.value.compare) {
    onCompareFilterTypeChange()
  } else {
    applyFilters()
  }
}

const onCompareFilterTypeChange = () => {
  if (localFilters.value.compareFilterType === 'month') {
    const lastMonth = new Date()
    lastMonth.setMonth(lastMonth.getMonth() - 1)
    localFilters.value.compareFilterValue = `${lastMonth.getFullYear()}-${String(lastMonth.getMonth() + 1).padStart(2, '0')}`
  } else if (localFilters.value.compareFilterType === 'year') {
    localFilters.value.compareFilterValue = (new Date().getFullYear() - 1).toString()
  } else if (localFilters.value.compareFilterType === 'date-range') {
    const now = new Date()
    const lastMonthStart = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    const lastMonthEnd = new Date(now.getFullYear(), now.getMonth(), 0)
    localFilters.value.compareStartDate = lastMonthStart.toISOString().split('T')[0]
    localFilters.value.compareEndDate = lastMonthEnd.toISOString().split('T')[0]
  }
  applyFilters()
}

const applyFilters = async () => {
  // Don't update shared store filters, just fetch data with current local filters
  // Fetch new data with local filters
  await fetchCategoriesWithFilters()
}

const fetchCategoriesWithFilters = async () => {
  console.log('[CustomerCategoriesChart] Fetching categories for customer:', props.customerId)
  isLoading.value = true
  try {
    const api = customersStore._getAPI()
    const params = {
      filterType: localFilters.value.filterType || 'year',
      filterValue: localFilters.value.filterValue || new Date().getFullYear().toString(),
      limit: 10,
    }
    
    if (localFilters.value.filterType === 'date-range' && localFilters.value.startDate && localFilters.value.endDate) {
      params.startDate = localFilters.value.startDate
      params.endDate = localFilters.value.endDate
    }
    
    if (localFilters.value.compare) {
      params.compare = true
      params.compareFilterType = localFilters.value.compareFilterType
      params.compareFilterValue = localFilters.value.compareFilterValue
      
      if (localFilters.value.compareFilterType === 'date-range' && localFilters.value.compareStartDate && localFilters.value.compareEndDate) {
        params.compareStartDate = localFilters.value.compareStartDate
        params.compareEndDate = localFilters.value.compareEndDate
      }
    }
    
    const result = await api.getCustomerCategories(props.customerId, params)
    categories.value = result // Store in local state instead of store
    console.log('[CustomerCategoriesChart] Categories fetched successfully')
  } catch (error) {
    console.error('[CustomerCategoriesChart] Failed to fetch customer categories:', error)
    categories.value = null
  } finally {
    isLoading.value = false
  }
}

const formatCurrency = (value) => {
  return parseFloat(value || 0).toFixed(2)
}

// Lifecycle
const isFetching = ref(false)

onMounted(async () => {
  // Don't sync with store - use independent filters
  // Fetch initial data
  if (!isFetching.value && props.customerId) {
    isFetching.value = true
    await fetchCategoriesWithFilters()
    isFetching.value = false
  }
})

// Watch for customer ID changes only (avoid infinite loop)
watch(() => props.customerId, async (newId, oldId) => {
  if (newId && newId !== oldId && !isFetching.value) {
    isFetching.value = true
    await fetchCategoriesWithFilters()
    isFetching.value = false
  }
}, { immediate: false })
</script>
