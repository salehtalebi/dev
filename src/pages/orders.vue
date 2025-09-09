<template>
  <div>
    <!-- Page Header -->
    <VRow>
      <VCol cols="12">
        <VCard class="mb-6">
          <VCardText>
            <VRow align="center">
              <VCol cols="12" md="6">
                <h2 class="text-h4 mb-0">Orders List</h2>
                <p class="text-body-1 mb-0">Manage and view orders</p>
              </VCol>
              <VCol cols="12" md="6" class="text-end">
                <VBtn
                  color="primary"
                  :loading="exportLoading"
                  @click="exportOrders"
                >
                  <VIcon start icon="bx-download" />
                  Export Excel
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
          <VCardTitle>Filters</VCardTitle>
          <VCardText>
            <VRow>
              <VCol cols="12" md="2">
                <VTextField
                  v-model="localFilters.search"
                  label="Search"
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
                  label="Order Status"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="2">
                <VSelect
                  v-model="localFilters.accountManager"
                  :items="accountManagers"
                  item-title="text"
                  item-value="value"
                  label="Account Manager"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="2">
                <VSelect
                  v-model="localFilters.dateRange"
                  :items="dateRanges"
                  item-title="text"
                  item-value="value"
                  label="Date Range"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="2">
                <VTextField
                  v-model="localFilters.min_amount"
                  label="Min Amount"
                  type="number"
                  suffix="USD"
                />
              </VCol>
              <VCol cols="12" md="2" class="d-flex align-center gap-2">
                <VBtn
                  color="primary"
                  @click="applyFilters"
                >
                  Apply
                </VBtn>
                <VBtn
                  color="secondary"
                  variant="outlined"
                  @click="clearFilters"
                >
                  Clear
                </VBtn>
              </VCol>
            </VRow>
            
            <!-- Custom Date Range & Max Amount -->
            <VRow v-if="localFilters.dateRange === 'custom' || localFilters.min_amount">
              <VCol v-if="localFilters.dateRange === 'custom'" cols="12" md="3">
                <VTextField
                  v-model="localFilters.date_from"
                  label="From Date"
                  type="date"
                />
              </VCol>
              <VCol v-if="localFilters.dateRange === 'custom'" cols="12" md="3">
                <VTextField
                  v-model="localFilters.date_to"
                  label="To Date"
                  type="date"
                />
              </VCol>
              <VCol v-if="localFilters.min_amount" cols="12" md="3">
                <VTextField
                  v-model="localFilters.max_amount"
                  label="Max Amount"
                  type="number"
                  suffix="USD"
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
              <p class="mt-4">Loading...</p>
            </div>

            <!-- Table -->
            <VDataTable
              v-else
              v-model:items-per-page="itemsPerPage"
              v-model:page="currentPage"
              :headers="headers"
              :items="orders"
              :loading="isLoading"
              :items-length="totalOrders"
              item-value="id"
              class="elevation-1"
              @update:options="updateOptions"
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

              <!-- Footer with VDataTableFooter -->
              <template #bottom>
                <VDataTableFooter
                  :items-per-page-options="itemsPerPageOptions"
                  :items-per-page="itemsPerPage"
                  :page="currentPage"
                  :items-length="totalOrders"
                  @update:items-per-page="updateItemsPerPage"
                  @update:page="updatePage"
                />
              </template>
            </VDataTable>
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
import { computed, onMounted, ref } from 'vue'
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
  max_amount: '',
  customer: ''
})

// Data
const accountManagers = [
  { text: 'All', value: '' },
  { text: 'House', value: 'house' },
  { text: 'Ina Istok', value: '1465' },
  { text: 'Pina Lee', value: '845' },
  { text: 'Vidika Shenton', value: '1886' },
  { text: 'Sarah Hearn', value: '2532' },
  { text: 'Jonathon Regan', value: '2533' }
]

const dateRanges = [
  { text: 'All', value: '' },
  { text: 'This Week', value: 'this_week' },
  { text: 'This Month', value: 'this_month' },
  { text: 'Last Month', value: 'last_month' },
  { text: 'Last Year', value: 'last_year' },
  { text: 'Custom Range', value: 'custom' }
]

// Pagination
const itemsPerPage = ref(ordersStore.pagination.perPage)
const totalOrders = computed(() => ordersStore.totalOrders || 0)
const itemsPerPageOptions = [
  { value: 10, title: '10' },
  { value: 20, title: '20' },
  { value: 50, title: '50' },
  { value: 100, title: '100' }
]

const pageText = computed(() => {
  if (totalOrders.value === 0) return 'No items found'
  
  const start = (currentPage.value - 1) * itemsPerPage.value + 1
  const end = Math.min(currentPage.value * itemsPerPage.value, totalOrders.value)
  
  return `${start}-${end} of ${totalOrders.value}`
})

const updateOptions = (options) => {
  const pageChanged = currentPage.value !== options.page
  const itemsPerPageChanged = itemsPerPage.value !== options.itemsPerPage
  
  if (pageChanged) currentPage.value = options.page
  if (itemsPerPageChanged) itemsPerPage.value = options.itemsPerPage
  
  if (pageChanged) ordersStore.setPage(options.page)
  if (itemsPerPageChanged) ordersStore.setPerPage(options.itemsPerPage)
  
  if (pageChanged || itemsPerPageChanged) {
    fetchOrders()
  }
}

const updateItemsPerPage = (newItemsPerPage) => {
  if (itemsPerPage.value !== newItemsPerPage) {
    itemsPerPage.value = newItemsPerPage
    ordersStore.setPerPage(newItemsPerPage)
    fetchOrders()
  }
}

const updatePage = (newPage) => {
  if (currentPage.value !== newPage) {
    // Avoid reactive loop by checking if the page has actually changed
    ordersStore.setPage(newPage)
    fetchOrders()
  }
}

// Computed
const orders = computed(() => Array.isArray(ordersStore.orders) ? ordersStore.orders : [])
const isLoading = computed(() => ordersStore.isLoading)
const totalPages = computed(() => ordersStore.totalPages)
const currentPage = computed({
  get: () => ordersStore.currentPage,
  set: (value) => {
    if (ordersStore.currentPage !== value) {
      ordersStore.setPage(value)
    }
  }
})
const orderStatuses = computed(() => ordersStore.orderStatuses)

// Table headers
const headers = [
  { title: 'Order Number', key: 'id', sortable: true },
  { title: 'Customer', key: 'customer', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Total Amount', key: 'total', sortable: true },
  { title: 'Date', key: 'date_created', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false }
]

// Methods
const fetchOrders = () => {
  ordersStore.fetchOrders(ordersStore.pagination.page)
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

// Check for URL parameters
const checkUrlParameters = () => {
  const urlParams = new URLSearchParams(window.location.search)
  const customerParam = urlParams.get('customer')
  if (customerParam) {
    localFilters.value.customer = customerParam
    ordersStore.setFilters({ customer: customerParam })
  }
}

// Initialize
onMounted(() => {
  checkUrlParameters()
  fetchOrders()
})
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
