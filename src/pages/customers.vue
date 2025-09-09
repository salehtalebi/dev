<template>
  <div>
    <!-- Page Header -->
    <VRow>
      <VCol cols="12">
        <VCard class="mb-6">
          <VCardText>
            <VRow align="center">
              <VCol cols="12" md="6">
                <h2 class="text-h4 mb-0">Customers List</h2>
                <p class="text-body-1 mb-0">Manage and view customers</p>
              </VCol>
              <VCol cols="12" md="6" class="text-end">
                <VBtn
                  color="primary"
                  :loading="exportLoading"
                  @click="exportCustomers"
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
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.search"
                  label="Search (Name, Email)"
                  prepend-inner-icon="bx-search"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="3">
                <VSelect
                  v-model="localFilters.accountManager"
                  :items="accountManagers"
                  item-title="text"
                  item-value="value"
                  label="Account Manager"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="3">
                <VSelect
                  v-model="localFilters.dateRange"
                  :items="dateRanges"
                  item-title="text"
                  item-value="value"
                  label="Date Range"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="3" class="d-flex align-center gap-2">
                <VBtn
                  color="primary"
                  @click="applyFilters"
                >
                  Apply Filter
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

            <!-- Custom Date Range -->
            <VRow v-if="localFilters.dateRange === 'custom'">
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.date_registered_from"
                  label="From Date"
                  type="date"
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.date_registered_to"
                  label="To Date"
                  type="date"
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.total_spent_min"
                  label="Min Purchase"
                  type="number"
                  suffix="USD"
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.total_spent_max"
                  label="Max Purchase"
                  type="number"
                  suffix="USD"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Customers Table -->
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
            <VDataTableServer
              v-else
              :key="`${totalCustomers}-${itemsPerPage}-${currentPage}`"
              :items-per-page="itemsPerPage"
              :page="currentPage"
              :headers="headers"
              :items="customers"
              :loading="isLoading"
              item-value="id"
              class="elevation-1"
              :items-length="totalCustomers"
              :items-per-page-options="itemsPerPageOptions"
              show-current-page
              @update:options="onTableOptionsUpdate"
            >
              <!-- Customer Name -->
              <template #item.name="{ item }">
                <div class="d-flex align-center">
                  <VAvatar
                    size="40"
                    color="primary"
                    class="me-3"
                  >
                    <span class="text-white">
                      {{ getInitials(item.first_name, item.last_name) }}
                    </span>
                  </VAvatar>
                  <div>
                    <div class="font-weight-medium">
                      {{ item.first_name }} {{ item.last_name }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ item.email }}
                    </div>
                  </div>
                </div>
              </template>

              <!-- Phone -->
              <template #item.phone="{ item }">
                {{ item.billing?.phone || '-' }}
              </template>

              <!-- Orders Count -->
              <template #item.orders_count="{ item }">
                <VChip
                  color="info"
                  size="small"
                  variant="tonal"
                >
                  {{ item.orders_count || 0 }} orders
                </VChip>
              </template>

              <!-- Total Spent -->
              <template #item.total_spent="{ item }">
                <div class="font-weight-bold text-success">
                  ${{ parseFloat(item.total_spent || 0).toFixed(2) }}
                </div>
              </template>

              <!-- Date Created -->
              <template #item.date_created="{ item }">
                {{ formatDate(item.date_created) }}
              </template>

              <!-- Actions -->
              <template #item.actions="{ item }">
                <VBtn
                  icon
                  size="small"
                  variant="text"
                  @click="viewCustomer(item.id)"
                >
                  <VIcon icon="bx-show" />
                </VBtn>
              </template>

              <!-- Enhanced Footer -->
              <template #bottom>
                <VDataTableFooter
                  :items-per-page-options="itemsPerPageOptions"
                  :items-per-page="itemsPerPage"
                  :page="currentPage"
                  :items-length="totalCustomers"
                  show-current-page
                  @update:itemsPerPage="updateItemsPerPage"
                  @update:page="updatePage"
                />
              </template>
            </VDataTableServer>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<script setup>
import { useExport } from '@/composables/useExport'
import { useCustomersStore } from '@/stores/customers'
import { format } from 'date-fns'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const customersStore = useCustomersStore()
const { exportCustomers: exportCustomersToFile, isExporting: exportLoading, error: exportError } = useExport()

// Local state
const localFilters = ref({
  search: '',
  accountManager: '',
  dateRange: '',
  date_registered_from: '',
  date_registered_to: '',
  total_spent_min: '',
  total_spent_max: ''
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
const itemsPerPage = ref(customersStore.pagination.perPage)
const totalCustomers = computed(() => customersStore.totalCustomers || 0)
const itemsPerPageOptions = [
  { value: 10, title: '10' },
  { value: 20, title: '20' },
  { value: 50, title: '50' },
  { value: 100, title: '100' }
]

const pageText = computed(() => {
  if (totalCustomers.value === 0) return 'No customers found'

  const start = (currentPage.value - 1) * itemsPerPage.value + 1
  const end = Math.min(currentPage.value * itemsPerPage.value, totalCustomers.value)

  return `${start}-${end} of ${totalCustomers.value}`
})

const onTableOptionsUpdate = async (options) => {
  const pageChanged = currentPage.value !== options.page
  const itemsPerPageChanged = itemsPerPage.value !== options.itemsPerPage
  console.debug('[customers] options update:', options, { pageChanged, itemsPerPageChanged, currentPage: currentPage.value, itemsPerPage: itemsPerPage.value })

  if (pageChanged) currentPage.value = options.page
  if (itemsPerPageChanged) {
    itemsPerPage.value = options.itemsPerPage
    currentPage.value = 1
  }

  if (pageChanged) customersStore.setPage(options.page)
  if (itemsPerPageChanged) {
    customersStore.setPerPage(options.itemsPerPage)
    customersStore.setPage(1)
  }

  // Avoid redundant fetch on initial sync when nothing actually changed
  if (pageChanged || itemsPerPageChanged) {
    const targetPage = itemsPerPageChanged ? 1 : options.page
  console.debug('[customers] fetching with target page:', targetPage)
    await fetchCustomers(true, targetPage)
    currentPage.value = customersStore.currentPage
  }
}
// Handled via onTableOptionsUpdate only


const updateItemsPerPage = async (newItemsPerPage) => {
  if (itemsPerPage.value !== newItemsPerPage) {
    itemsPerPage.value = newItemsPerPage
    customersStore.setPerPage(newItemsPerPage)
    // Reset to first page when per page changes
    customersStore.setPage(1)
    await fetchCustomers(true, 1)
  }
}

const updatePage = async (newPage) => {
  if (currentPage.value !== newPage) {
    // Avoid reactive loop by checking if the page has actually changed
  customersStore.setPage(newPage)
  await fetchCustomers(true, newPage)
  }
}

// Computed
const customers = computed(() => Array.isArray(customersStore.customers) ? customersStore.customers : [])
const isLoading = computed(() => customersStore.isLoading)
const totalPages = computed(() => customersStore.totalPages)
const currentPage = computed({
  get: () => customersStore.currentPage,
  set: (value) => {
    if (customersStore.currentPage !== value) {
      customersStore.setPage(value)
    }
  }
})

// Table headers
const headers = [
  { title: 'Customer', key: 'name', sortable: false },
  { title: 'Phone', key: 'phone', sortable: false },
  { title: 'Orders Count', key: 'orders_count', sortable: true },
  { title: 'Total Spent', key: 'total_spent', sortable: true },
  { title: 'Registration Date', key: 'date_created', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false }
]

// Methods
const fetchCustomers = (refresh = false, pageOverride = null) => {
  const page = pageOverride ?? customersStore.pagination.page
  customersStore.fetchCustomers(page, refresh)
}

const applyFilters = () => {
  // Handle date range presets
  const today = new Date()
  const filters = { ...localFilters.value }

  if (localFilters.value.dateRange && localFilters.value.dateRange !== 'custom') {
    switch (localFilters.value.dateRange) {
      case 'this_week':
        const startOfWeek = new Date(today.setDate(today.getDate() - today.getDay()))
        filters.date_registered_from = startOfWeek.toISOString().split('T')[0]
        filters.date_registered_to = new Date().toISOString().split('T')[0]
        break
      case 'this_month':
        filters.date_registered_from = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0]
        filters.date_registered_to = new Date().toISOString().split('T')[0]
        break
      case 'last_month':
        const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1)
        const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0)
        filters.date_registered_from = lastMonth.toISOString().split('T')[0]
        filters.date_registered_to = lastMonthEnd.toISOString().split('T')[0]
        break
      case 'last_year':
        filters.date_registered_from = new Date(today.getFullYear() - 1, 0, 1).toISOString().split('T')[0]
        filters.date_registered_to = new Date(today.getFullYear() - 1, 11, 31).toISOString().split('T')[0]
        break
    }
  }

  customersStore.setFilters(filters)
  fetchCustomers(true)
}

const clearFilters = () => {
  localFilters.value = {
    search: '',
    accountManager: '',
    dateRange: '',
    date_registered_from: '',
    date_registered_to: '',
    total_spent_min: '',
    total_spent_max: ''
  }
  customersStore.clearFilters()
  fetchCustomers(true)
}

const viewCustomer = (customerId) => {
  router.push(`/customers/${customerId}`)
}

const getInitials = (firstName, lastName) => {
  const first = firstName ? firstName.charAt(0).toUpperCase() : ''
  const last = lastName ? lastName.charAt(0).toUpperCase() : ''
  return first + last
}

const formatDate = (dateString) => {
  return format(new Date(dateString), 'yyyy/MM/dd')
}

const exportCustomers = async () => {
  try {
    // Get current filters for export
    const exportFilters = { ...localFilters.value }

    // Handle date range presets for export
    const today = new Date()
    if (localFilters.value.dateRange && localFilters.value.dateRange !== 'custom') {
      switch (localFilters.value.dateRange) {
        case 'this_week':
          const startOfWeek = new Date(today.setDate(today.getDate() - today.getDay()))
          exportFilters.date_registered_from = startOfWeek.toISOString().split('T')[0]
          exportFilters.date_registered_to = new Date().toISOString().split('T')[0]
          break
        case 'this_month':
          exportFilters.date_registered_from = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0]
          exportFilters.date_registered_to = new Date().toISOString().split('T')[0]
          break
        case 'last_month':
          const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1)
          const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0)
          exportFilters.date_registered_from = lastMonth.toISOString().split('T')[0]
          exportFilters.date_registered_to = lastMonthEnd.toISOString().split('T')[0]
          break
        case 'last_year':
          exportFilters.date_registered_from = new Date(today.getFullYear() - 1, 0, 1).toISOString().split('T')[0]
          exportFilters.date_registered_to = new Date(today.getFullYear() - 1, 11, 31).toISOString().split('T')[0]
          break
      }
    }

    const result = await exportCustomersToFile(exportFilters)
    if (result.success) {
      // Show success message (you can add a toast notification here)
      console.log('Customers exported successfully')
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
    customersStore.setFilters({ customer: customerParam })
  }
}

// Initialize
onMounted(() => {
  checkUrlParameters()
  fetchCustomers()
})
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
