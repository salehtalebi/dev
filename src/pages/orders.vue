<template>
  <div>
    <!-- Page Header -->
    <VRow>
      <VCol cols="12">
        <VCard class="mb-6">
          <VCardText>
            <VRow align="center">
              <VCol cols="12" md="6">
                <h2 class="text-h4 mb-0">لیست سفارشات</h2>
                <p class="text-body-1 mb-0">مدیریت و نمایش سفارشات</p>
              </VCol>
              <VCol cols="12" md="6" class="text-end">
                <VBtn
                  color="primary"
                  :loading="exportLoading"
                  @click="exportOrders"
                >
                  <VIcon start icon="bx-download" />
                  خروجی Excel
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Filters -->
    <VRow>
      <VCol cols="12">
        <VCard class="mb-6">
          <VCardTitle>فیلترها</VCardTitle>
          <VCardText>
            <VRow>
              <VCol cols="12" md="2">
                <VTextField
                  v-model="localFilters.search"
                  label="جستجو"
                  prepend-inner-icon="bx-search"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="2">
                <VSelect
                  v-model="localFilters.status"
                  :items="orderStatuses"
                  item-title="text"
                  item-value="value"
                  label="وضعیت سفارش"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="2">
                <VSelect
                  v-model="localFilters.accountManager"
                  :items="accountManagers"
                  item-title="text"
                  item-value="value"
                  label="اکانت منیجر"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="2">
                <VSelect
                  v-model="localFilters.dateRange"
                  :items="dateRanges"
                  item-title="text"
                  item-value="value"
                  label="بازه زمانی"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="2">
                <VTextField
                  v-model="localFilters.min_amount"
                  label="حداقل مبلغ"
                  type="number"
                  suffix="تومان"
                />
              </VCol>
              <VCol cols="12" md="2" class="d-flex align-center gap-2">
                <VBtn
                  color="primary"
                  @click="applyFilters"
                >
                  اعمال
                </VBtn>
                <VBtn
                  color="secondary"
                  variant="outlined"
                  @click="clearFilters"
                >
                  پاک
                </VBtn>
              </VCol>
            </VRow>
            
            <!-- Custom Date Range & Max Amount -->
            <VRow v-if="localFilters.dateRange === 'custom' || localFilters.min_amount">
              <VCol v-if="localFilters.dateRange === 'custom'" cols="12" md="3">
                <VTextField
                  v-model="localFilters.date_from"
                  label="از تاریخ"
                  type="date"
                />
              </VCol>
              <VCol v-if="localFilters.dateRange === 'custom'" cols="12" md="3">
                <VTextField
                  v-model="localFilters.date_to"
                  label="تا تاریخ"
                  type="date"
                />
              </VCol>
              <VCol v-if="localFilters.min_amount" cols="12" md="3">
                <VTextField
                  v-model="localFilters.max_amount"
                  label="حداکثر مبلغ"
                  type="number"
                  suffix="تومان"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Orders Table -->
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardText>
            <!-- Loading -->
            <div v-if="isLoading" class="text-center py-8">
              <VProgressCircular
                size="50"
                color="primary"
                indeterminate
              />
              <p class="mt-4">در حال بارگذاری...</p>
            </div>

            <!-- Table -->
            <VDataTable
              v-else
              :headers="headers"
              :items="orders"
              :loading="isLoading"
              item-value="id"
              class="elevation-1"
            >
              <!-- Order ID -->
              <template #item.id="{ item }">
                <VChip
                  color="primary"
                  variant="outlined"
                  size="small"
                  @click="viewOrder(item.id)"
                  class="cursor-pointer"
                >
                  #{{ item.id }}
                </VChip>
              </template>

              <!-- Customer -->
              <template #item.customer="{ item }">
                <div>
                  <div class="font-weight-medium">
                    {{ item.billing.first_name }} {{ item.billing.last_name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    {{ item.billing.email }}
                  </div>
                </div>
              </template>

              <!-- Status -->
              <template #item.status="{ item }">
                <VChip
                  :color="getStatusColor(item.status)"
                  size="small"
                  variant="tonal"
                >
                  {{ getStatusText(item.status) }}
                </VChip>
              </template>

              <!-- Total -->
              <template #item.total="{ item }">
                <div class="font-weight-bold">
                  ${{ parseFloat(item.total).toFixed(2) }}
                </div>
              </template>

              <!-- Date -->
              <template #item.date_created="{ item }">
                {{ formatDate(item.date_created) }}
              </template>

              <!-- Actions -->
              <template #item.actions="{ item }">
                <VBtn
                  icon
                  size="small"
                  variant="text"
                  @click="viewOrder(item.id)"
                >
                  <VIcon icon="bx-show" />
                </VBtn>
              </template>
            </VDataTable>

            <!-- Pagination -->
            <div class="d-flex justify-center mt-6">
              <VPagination
                v-model="currentPage"
                :length="totalPages"
                @update:model-value="fetchOrders"
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<script setup>
import { useExport } from '@/composables/useExport'
import { useOrdersStore } from '@/stores/orders'
import { format } from 'date-fns'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const ordersStore = useOrdersStore()
const { exportOrders: exportOrdersToFile, isExporting: exportLoading, error: exportError } = useExport()

// Local state
const localFilters = ref({
  status: '',
  search: '',
  accountManager: '',
  dateRange: '',
  date_from: '',
  date_to: '',
  min_amount: '',
  max_amount: ''
})

// Data
const accountManagers = [
  { text: 'همه', value: '' },
  { text: 'House', value: 'house' },
  { text: 'Ina Istok', value: '1465' },
  { text: 'Pina Lee', value: '845' },
  { text: 'Vidika Shenton', value: '1886' },
  { text: 'Sarah Hearn', value: '2532' },
  { text: 'Jonathon Regan', value: '2533' }
]

const dateRanges = [
  { text: 'همه', value: '' },
  { text: 'این هفته', value: 'this_week' },
  { text: 'این ماه', value: 'this_month' },
  { text: 'ماه گذشته', value: 'last_month' },
  { text: 'سال گذشته', value: 'last_year' },
  { text: 'بازه دلخواه', value: 'custom' }
]

// Pagination
const itemsPerPage = ref(20)
const totalOrders = computed(() => ordersStore.totalOrders || 0)
const itemsPerPageOptions = [
  { value: 10, title: '10' },
  { value: 20, title: '20' },
  { value: 50, title: '50' },
  { value: 100, title: '100' }
]

const pageText = computed(() => {
  if (totalOrders.value === 0) return 'هیچ آیتمی یافت نشد'
  
  const start = (currentPage.value - 1) * itemsPerPage.value + 1
  const end = Math.min(currentPage.value * itemsPerPage.value, totalOrders.value)
  
  return `${start}-${end} از ${totalOrders.value}`
})

const updateOptions = (options) => {
  currentPage.value = options.page
  itemsPerPage.value = options.itemsPerPage
  ordersStore.setPage(options.page)
  ordersStore.setItemsPerPage(options.itemsPerPage)
  fetchOrders()
}

// Computed
const orders = computed(() => ordersStore.orders)
const isLoading = computed(() => ordersStore.isLoading)
const totalPages = computed(() => ordersStore.totalPages)
const currentPage = computed({
  get: () => ordersStore.currentPage,
  set: (value) => ordersStore.setPage(value)
})
const orderStatuses = computed(() => ordersStore.orderStatuses)

// Table headers
const headers = [
  { title: 'شماره سفارش', key: 'id', sortable: true },
  { title: 'مشتری', key: 'customer', sortable: false },
  { title: 'وضعیت', key: 'status', sortable: true },
  { title: 'مبلغ کل', key: 'total', sortable: true },
  { title: 'تاریخ', key: 'date_created', sortable: true },
  { title: 'عملیات', key: 'actions', sortable: false }
]

// Methods
const fetchOrders = () => {
  ordersStore.fetchOrders()
}

const applyFilters = () => {
  // Handle date range presets
  const today = new Date()
  const filters = { ...localFilters.value }
  
  if (localFilters.value.dateRange && localFilters.value.dateRange !== 'custom') {
    switch (localFilters.value.dateRange) {
      case 'this_week':
        const startOfWeek = new Date(today.setDate(today.getDate() - today.getDay()))
        filters.date_from = startOfWeek.toISOString().split('T')[0]
        filters.date_to = new Date().toISOString().split('T')[0]
        break
      case 'this_month':
        filters.date_from = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0]
        filters.date_to = new Date().toISOString().split('T')[0]
        break
      case 'last_month':
        const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1)
        const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0)
        filters.date_from = lastMonth.toISOString().split('T')[0]
        filters.date_to = lastMonthEnd.toISOString().split('T')[0]
        break
      case 'last_year':
        filters.date_from = new Date(today.getFullYear() - 1, 0, 1).toISOString().split('T')[0]
        filters.date_to = new Date(today.getFullYear() - 1, 11, 31).toISOString().split('T')[0]
        break
    }
  }
  
  ordersStore.setFilters(filters)
  fetchOrders()
}

const clearFilters = () => {
  localFilters.value = {
    status: '',
    search: '',
    accountManager: '',
    dateRange: '',
    date_from: '',
    date_to: '',
    min_amount: '',
    max_amount: ''
  }
  ordersStore.clearFilters()
  fetchOrders()
}

const viewOrder = (orderId) => {
  router.push(`/orders/${orderId}`)
}

const getStatusColor = (status) => {
  const statusObj = orderStatuses.value.find(s => s.value === status)
  return statusObj?.color || 'default'
}

const getStatusText = (status) => {
  const statusObj = orderStatuses.value.find(s => s.value === status)
  return statusObj?.text || status
}

const formatDate = (dateString) => {
  return format(new Date(dateString), 'yyyy/MM/dd HH:mm')
}

const exportOrders = async () => {
  try {
    // Get current filters for export
    const exportFilters = { ...localFilters.value }
    
    // Handle date range presets for export
    const today = new Date()
    if (localFilters.value.dateRange && localFilters.value.dateRange !== 'custom') {
      switch (localFilters.value.dateRange) {
        case 'this_week':
          const startOfWeek = new Date(today.setDate(today.getDate() - today.getDay()))
          exportFilters.date_from = startOfWeek.toISOString().split('T')[0]
          exportFilters.date_to = new Date().toISOString().split('T')[0]
          break
        case 'this_month':
          exportFilters.date_from = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0]
          exportFilters.date_to = new Date().toISOString().split('T')[0]
          break
        case 'last_month':
          const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1)
          const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0)
          exportFilters.date_from = lastMonth.toISOString().split('T')[0]
          exportFilters.date_to = lastMonthEnd.toISOString().split('T')[0]
          break
        case 'last_year':
          exportFilters.date_from = new Date(today.getFullYear() - 1, 0, 1).toISOString().split('T')[0]
          exportFilters.date_to = new Date(today.getFullYear() - 1, 11, 31).toISOString().split('T')[0]
          break
      }
    }
    
    const result = await exportOrdersToFile(exportFilters)
    if (result.success) {
      // Show success message (you can add a toast notification here)
      console.log('Orders exported successfully')
    } else {
      // Show error message
      console.error('Export failed:', result.message)
    }
  } catch (error) {
    console.error('Export error:', error)
  }
}

// Watch for page changes
watch(currentPage, () => {
  fetchOrders()
})

// Initialize
onMounted(() => {
  fetchOrders()
})
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
