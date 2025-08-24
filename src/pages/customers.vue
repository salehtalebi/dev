<template>
  <div>
    <!-- Page Header -->
    <VRow>
      <VCol cols="12">
        <VCard class="mb-6">
          <VCardText>
            <VRow align="center">
              <VCol cols="12" md="6">
                <h2 class="text-h4 mb-0">لیست مشتریان</h2>
                <p class="text-body-1 mb-0">مدیریت و نمایش مشتریان</p>
              </VCol>
              <VCol cols="12" md="6" class="text-end">
                <VBtn
                  color="primary"
                  :loading="exportLoading"
                  @click="exportCustomers"
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
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.search"
                  label="جستجو (نام، ایمیل)"
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
                  label="اکانت منیجر"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="3">
                <VSelect
                  v-model="localFilters.dateRange"
                  :items="dateRanges"
                  item-title="text"
                  item-value="value"
                  label="بازه زمانی"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="3" class="d-flex align-center gap-2">
                <VBtn
                  color="primary"
                  @click="applyFilters"
                >
                  اعمال فیلتر
                </VBtn>
                <VBtn
                  color="secondary"
                  variant="outlined"
                  @click="clearFilters"
                >
                  پاک کردن
                </VBtn>
              </VCol>
            </VRow>
            
            <!-- Custom Date Range -->
            <VRow v-if="localFilters.dateRange === 'custom'">
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.date_registered_from"
                  label="از تاریخ"
                  type="date"
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.date_registered_to"
                  label="تا تاریخ"
                  type="date"
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.total_spent_min"
                  label="حداقل خرید"
                  type="number"
                  suffix="تومان"
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.total_spent_max"
                  label="حداکثر خرید"
                  type="number"
                  suffix="تومان"
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
              <p class="mt-4">در حال بارگذاری...</p>
            </div>

            <!-- Table -->
            <VDataTable
              v-else
              v-model:items-per-page="itemsPerPage"
              v-model:page="currentPage"
              :headers="headers"
              :items="customers"
              :loading="isLoading"
              item-value="id"
              class="elevation-1"
              :items-length="totalCustomers"
              @update:options="updateOptions"
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
                  {{ item.orders_count || 0 }} سفارش
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
                  v-model:items-per-page="itemsPerPage"
                  v-model:page="currentPage"
                  :items-length="totalCustomers"
                  :page-text="pageText"
                  :items-per-page-options="itemsPerPageOptions"
                  show-current-page
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
import { useCustomersStore } from '@/stores/customers'
import { format } from 'date-fns'
import { computed, onMounted, ref, watch } from 'vue'
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
const totalCustomers = computed(() => customersStore.totalCustomers || 0)
const itemsPerPageOptions = [
  { value: 10, title: '10' },
  { value: 20, title: '20' },
  { value: 50, title: '50' },
  { value: 100, title: '100' }
]

const pageText = computed(() => {
  if (totalCustomers.value === 0) return 'هیچ مشتری یافت نشد'
  
  const start = (currentPage.value - 1) * itemsPerPage.value + 1
  const end = Math.min(currentPage.value * itemsPerPage.value, totalCustomers.value)
  
  return `${start}-${end} از ${totalCustomers.value}`
})

const updateOptions = (options) => {
  currentPage.value = options.page
  itemsPerPage.value = options.itemsPerPage
  customersStore.setPage(options.page)
  customersStore.setItemsPerPage?.(options.itemsPerPage)
  fetchCustomers()
}

// Computed
const customers = computed(() => customersStore.customers)
const isLoading = computed(() => customersStore.isLoading)
const totalPages = computed(() => customersStore.totalPages)
const currentPage = computed({
  get: () => customersStore.currentPage,
  set: (value) => customersStore.setPage(value)
})

// Table headers
const headers = [
  { title: 'مشتری', key: 'name', sortable: false },
  { title: 'تلفن', key: 'phone', sortable: false },
  { title: 'تعداد سفارشات', key: 'orders_count', sortable: true },
  { title: 'مجموع خرید', key: 'total_spent', sortable: true },
  { title: 'تاریخ عضویت', key: 'date_created', sortable: true },
  { title: 'عملیات', key: 'actions', sortable: false }
]

// Methods
const fetchCustomers = () => {
  customersStore.fetchCustomers()
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
  fetchCustomers()
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
  fetchCustomers()
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

// Watch for page changes
watch(currentPage, () => {
  fetchCustomers()
})

// Initialize
onMounted(() => {
  fetchCustomers()
})
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
