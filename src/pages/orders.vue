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
                  :loading="isExporting"
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
              <VCol cols="12" md="3">
                <VSelect
                  v-model="localFilters.status"
                  :items="orderStatuses"
                  item-title="text"
                  item-value="value"
                  label="وضعیت سفارش"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.search"
                  label="جستجو (شماره سفارش، نام مشتری)"
                  prepend-inner-icon="bx-search"
                  clearable
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.date_from"
                  label="از تاریخ"
                  type="date"
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.date_to"
                  label="تا تاریخ"
                  type="date"
                />
              </VCol>
            </VRow>
            <VRow>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.min_amount"
                  label="حداقل مبلغ"
                  type="number"
                  prefix="$"
                />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField
                  v-model="localFilters.max_amount"
                  label="حداکثر مبلغ"
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
import { useOrdersStore } from '@/stores/orders'
import { format } from 'date-fns'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const ordersStore = useOrdersStore()

// Local state
const isExporting = ref(false)
const localFilters = ref({
  status: '',
  search: '',
  date_from: '',
  date_to: '',
  min_amount: '',
  max_amount: ''
})

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
  ordersStore.setFilters(localFilters.value)
  fetchOrders()
}

const clearFilters = () => {
  localFilters.value = {
    status: '',
    search: '',
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
  isExporting.value = true
  try {
    // Implementation for Excel export
    console.log('Exporting orders...')
  } catch (error) {
    console.error('Export error:', error)
  } finally {
    isExporting.value = false
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
