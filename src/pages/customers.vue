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
                  :loading="isExporting"
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
              <VCol cols="12" md="4">
                <VTextField
                  v-model="localFilters.search"
                  label="جستجو (نام، ایمیل)"
                  prepend-inner-icon="bx-search"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="localFilters.date_registered_from"
                  label="از تاریخ عضویت"
                  type="date"
                />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField
                  v-model="localFilters.date_registered_to"
                  label="تا تاریخ عضویت"
                  type="date"
                />
              </VCol>
            </VRow>
            <VRow>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.total_spent_min"
                  label="حداقل خرید"
                  type="number"
                  prefix="$"
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.total_spent_max"
                  label="حداکثر خرید"
                  type="number"
                  prefix="$"
                />
              </VCol>
              <VCol cols="12" md="6" class="d-flex align-center gap-4">
                <VBtn
                  color="primary"
                  @click="applyFilters"
                >
                  اعمال فیلتر
                </VBtn>
                <VBtn
                  variant="outlined"
                  @click="clearFilters"
                >
                  پاک کردن
                </VBtn>
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
              :headers="headers"
              :items="customers"
              :loading="isLoading"
              item-value="id"
              class="elevation-1"
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
            </VDataTable>

            <!-- Pagination -->
            <div class="d-flex justify-center mt-6">
              <VPagination
                v-model="currentPage"
                :length="totalPages"
                @update:model-value="fetchCustomers"
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useCustomersStore } from '@/stores/customers'
import { format } from 'date-fns'

const router = useRouter()
const customersStore = useCustomersStore()

// Local state
const isExporting = ref(false)
const localFilters = ref({
  search: '',
  date_registered_from: '',
  date_registered_to: '',
  total_spent_min: '',
  total_spent_max: ''
})

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
  customersStore.setFilters(localFilters.value)
  fetchCustomers()
}

const clearFilters = () => {
  localFilters.value = {
    search: '',
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
  isExporting.value = true
  try {
    // Implementation for Excel export
    console.log('Exporting customers...')
  } catch (error) {
    console.error('Export error:', error)
  } finally {
    isExporting.value = false
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
