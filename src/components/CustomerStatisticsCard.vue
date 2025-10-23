<template>
  <VCard>
    <VCardTitle class="d-flex justify-space-between align-center">
      <span>Customer Statistics</span>
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

    <!-- Statistics Display -->
    <!-- Statistics Display -->
    <VCardText>
      <div v-if="isLoading" class="text-center py-8">
        <VProgressCircular
          size="40"
          color="primary"
          indeterminate
        />
      </div>

      <div v-else-if="statistics">
        <!-- Period Label -->
        <div v-if="statistics.period_label" class="text-caption text-medium-emphasis mb-4">
          {{ statistics.period_label }}
        </div>

        <!-- Stats Grid -->
        <VRow>
          <!-- Total Orders -->
          <VCol cols="12" md="4">
            <div class="text-center pa-4 rounded bg-primary-lighten-5">
              <div class="text-h4 font-weight-bold text-primary">
                {{ statistics.total_orders || 0 }}
              </div>
              <div class="text-body-2 text-medium-emphasis mt-2">
                Total Orders
              </div>
              <VChip
                v-if="statistics.orders_growth !== undefined && statistics.orders_growth !== null"
                :color="statistics.orders_growth >= 0 ? 'success' : 'error'"
                size="small"
                variant="tonal"
                class="mt-2"
              >
                <VIcon
                  :icon="statistics.orders_growth >= 0 ? 'bx-trending-up' : 'bx-trending-down'"
                  size="16"
                  class="me-1"
                />
                {{ Math.abs(statistics.orders_growth).toFixed(1) }}%
              </VChip>
            </div>
          </VCol>

          <!-- Total Spent -->
          <VCol cols="12" md="4">
            <div class="text-center pa-4 rounded bg-success-lighten-5">
              <div class="text-h4 font-weight-bold text-success">
                ${{ formatCurrency(statistics.total_spent || 0) }}
              </div>
              <div class="text-body-2 text-medium-emphasis mt-2">
                Total Spent
              </div>
              <VChip
                v-if="statistics.revenue_growth !== undefined && statistics.revenue_growth !== null"
                :color="statistics.revenue_growth >= 0 ? 'success' : 'error'"
                size="small"
                variant="tonal"
                class="mt-2"
              >
                <VIcon
                  :icon="statistics.revenue_growth >= 0 ? 'bx-trending-up' : 'bx-trending-down'"
                  size="16"
                  class="me-1"
                />
                {{ Math.abs(statistics.revenue_growth).toFixed(1) }}%
              </VChip>
            </div>
          </VCol>

          <!-- Average Order Value -->
          <VCol cols="12" md="4">
            <div class="text-center pa-4 rounded bg-info-lighten-5">
              <div class="text-h4 font-weight-bold text-info">
                ${{ formatCurrency(statistics.avg_order_value || 0) }}
              </div>
              <div class="text-body-2 text-medium-emphasis mt-2">
                Avg Order Value
              </div>
              <VChip
                v-if="statistics.aov_growth !== undefined && statistics.aov_growth !== null"
                :color="statistics.aov_growth >= 0 ? 'success' : 'error'"
                size="small"
                variant="tonal"
                class="mt-2"
              >
                <VIcon
                  :icon="statistics.aov_growth >= 0 ? 'bx-trending-up' : 'bx-trending-down'"
                  size="16"
                  class="me-1"
                />
                {{ Math.abs(statistics.aov_growth).toFixed(1) }}%
              </VChip>
            </div>
          </VCol>
        </VRow>
      </div>

      <div v-else class="text-center py-8 text-medium-emphasis">
        No statistics available
      </div>
    </VCardText>
  </VCard>
</template>

<script setup>
import { useCustomersStore } from '@/stores/customers'
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps({
  customerId: {
    type: [Number, String],
    required: true,
  },
})

const customersStore = useCustomersStore()

const showFilters = ref(false)
const isLoading = ref(false)
const statistics = ref(null)

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

// Methods
const onFilterTypeChange = () => {
  // Reset filter values based on type
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
    // Set default comparison period
    onCompareFilterTypeChange()
  } else {
    applyFilters()
  }
}

const onCompareFilterTypeChange = () => {
  // Set default comparison values based on type
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
  // Fetch directly without updating shared store filters
  await fetchStatisticsWithFilters()
}

const fetchStatisticsWithFilters = async () => {
  console.log('[CustomerStatisticsCard] Fetching statistics for customer:', props.customerId)
  isLoading.value = true
  try {
    const api = customersStore._getAPI()
    const params = {
      filterType: localFilters.value.filterType || 'year',
      filterValue: localFilters.value.filterValue || new Date().getFullYear().toString(),
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
    
    const stats = await api.getCustomerStatisticsWithFilter(props.customerId, params)
    statistics.value = stats // Store in local state
    console.log('[CustomerStatisticsCard] Statistics fetched successfully')
  } catch (error) {
    console.error('[CustomerStatisticsCard] Failed to fetch customer statistics:', error)
    statistics.value = null
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
  // Fetch initial data independently
  if (!isFetching.value && props.customerId) {
    isFetching.value = true
    await fetchStatisticsWithFilters()
    isFetching.value = false
  }
})

// Watch for customer ID changes only (avoid infinite loop)
watch(() => props.customerId, async (newId, oldId) => {
  if (newId && newId !== oldId && !isFetching.value) {
    isFetching.value = true
    await fetchStatisticsWithFilters()
    isFetching.value = false
  }
}, { immediate: false })
</script>

<style scoped>
.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.08);
}

.bg-success-lighten-5 {
  background-color: rgba(var(--v-theme-success), 0.08);
}

.bg-info-lighten-5 {
  background-color: rgba(var(--v-theme-info), 0.08);
}
</style>
