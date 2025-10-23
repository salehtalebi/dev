<template>
  <div>
    <!-- Page Header -->
    <VRow>
      <VCol cols="12">
        <VCard class="mb-6">
          <VCardText>
            <VRow align="center">
              <VCol cols="12" md="6">
                <VBtn
                  icon
                  variant="text"
                  @click="$router.go(-1)"
                  class="me-2"
                >
                  <VIcon icon="bx-arrow-back" />
                </VBtn>
                <span class="text-h4">Customer Details</span>
              </VCol>
              <VCol cols="12" md="6" class="text-end" />
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Loading -->
    <div v-if="isLoading" class="text-center py-8">
      <VProgressCircular
        size="50"
        color="primary"
        indeterminate
      />
      <p class="mt-4">Loading...</p>
    </div>

    <!-- Customer Details -->
    <div v-else-if="customer">
      <VRow>
        <!-- Customer Info -->
        <VCol cols="12" md="4">
          <VCard class="mb-6">
            <VCardText class="text-center">
              <VAvatar
                size="80"
                color="primary"
                class="mb-4"
              >
                <span class="text-h4 text-white">
                  {{ getInitials(customer.first_name, customer.last_name) }}
                </span>
              </VAvatar>
              
              <h3 class="text-h5 mb-2">
                {{ customer.first_name }} {{ customer.last_name }}
              </h3>
              
              <p class="text-body-1 text-medium-emphasis mb-4">
                {{ customer.email }}
              </p>

              <div class="d-flex justify-center gap-2 mb-4">
                <VChip
                  color="info"
                  size="small"
                  variant="tonal"
                >
                  {{ customer.orders_count || 0 }} orders
                </VChip>
                <VChip
                  color="success"
                  size="small"
                  variant="tonal"
                >
                  ${{ parseFloat(customer.total_spent || 0).toFixed(2) }}
                </VChip>
              </div>

              <div class="text-caption text-medium-emphasis">
                Member since: {{ formatDate(customer.date_created) }}
              </div>
            </VCardText>
          </VCard>

          <!-- Customer Statistics with Filters -->
          <CustomerStatisticsCard
            v-if="customerId"
            :customer-id="customerId"
            class="mb-6"
          />
        </VCol>

        <!-- Customer Details & Recent Orders -->
        <VCol cols="12" md="8">
          <!-- Contact Information -->
          <VCard class="mb-6">
            <VCardTitle>Contact Information</VCardTitle>
            <VCardText>
              <VRow>
                <VCol cols="12" md="6">
                  <div class="mb-4">
                    <div class="text-caption text-medium-emphasis">Email</div>
                    <div>{{ customer.email }}</div>
                  </div>
                </VCol>
                <VCol cols="12" md="6">
                  <div class="mb-4">
                    <div class="text-caption text-medium-emphasis">Phone</div>
                    <div>{{ customer.billing?.phone || '-' }}</div>
                  </div>
                </VCol>
              </VRow>
              
              <!-- Billing Address -->
              <div v-if="customer.billing">
                <h6 class="text-h6 mb-3">Billing Address</h6>
                <div>{{ customer.billing.first_name }} {{ customer.billing.last_name }}</div>
                <div v-if="customer.billing.company">{{ customer.billing.company }}</div>
                <div>{{ customer.billing.address_1 }}</div>
                <div v-if="customer.billing.address_2">{{ customer.billing.address_2 }}</div>
                <div>{{ customer.billing.city }}, {{ customer.billing.state }} {{ customer.billing.postcode }}</div>
                <div>{{ customer.billing.country }}</div>
              </div>
            </VCardText>
          </VCard>

          <!-- Customer Categories Chart -->
          <CustomerCategoriesChart
            v-if="customerId"
            :customer-id="customerId"
            class="mb-6"
          />

              <!-- Customer Orders with Pagination -->
              <VCard>
                <VCardTitle class="d-flex justify-space-between align-center">
                  <span>Customer Orders</span>
                  <div class="d-flex align-center gap-2">
                    <VSelect
                      v-model="ordersPerPage"
                      :items="[10, 20, 50, 100]"
                      label="Items per Page"
                      variant="outlined"
                      density="compact"
                      style="width: 120px"
                      @update:model-value="loadCustomerOrders"
                    />
                  </div>
                </VCardTitle>
                <VCardText>
                  <div v-if="!customerOrders || customerOrders.length === 0" class="text-center py-8">
                    <p class="text-body-1 text-medium-emphasis">No orders found</p>
                  </div>

                  <div v-else>
                    <VDataTableServer
                      :key="`${ordersPagination.currentPage}-${ordersPerPage}-${ordersPagination.totalOrders}`"
                      :page="ordersPagination.currentPage"
                      :items-per-page="ordersPerPage"
                      :headers="orderHeaders"
                      :items="customerOrders"
                      :items-length="ordersPagination.totalOrders"
                      class="text-no-wrap"
                      @update:options="onOrdersOptionsUpdate"
                    >
                      <template #item.id="{ item }">
                        <VBtn variant="text" size="small" @click="viewOrder(item.id)">
                          #{{ item.id }}
                        </VBtn>
                      </template>

                      <template #item.status="{ item }">
                        <VChip :color="getStatusColor(item.status)" size="small" variant="tonal">
                          {{ getStatusText(item.status) }}
                        </VChip>
                      </template>

                      <template #item.total="{ item }">
                        <span class="font-weight-medium">${{ parseFloat(item.total || 0).toFixed(2) }}</span>
                      </template>

                      <template #item.date_created="{ item }">
                        <span class="text-body-2">{{ formatDate(item.date_created) }}</span>
                      </template>

                      <template #bottom>
                        <VDataTableFooter
                          :items-per-page-options="itemsPerPageOptions"
                          :items-per-page="ordersPerPage"
                          :page="ordersPagination.currentPage"
                          :items-length="ordersPagination.totalOrders"
                          show-current-page
                          @update:itemsPerPage="(val)=>onOrdersOptionsUpdate({ page: 1, itemsPerPage: val })"
                          @update:page="(val)=>onOrdersOptionsUpdate({ page: val, itemsPerPage: ordersPerPage })"
                        />
                      </template>
                    </VDataTableServer>
                  </div>
                </VCardText>
              </VCard>
          
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<script setup>
import CustomerCategoriesChart from '@/components/CustomerCategoriesChart.vue'
import CustomerStatisticsCard from '@/components/CustomerStatisticsCard.vue'
import { useCustomersStore } from '@/stores/customers'
import { format } from 'date-fns'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const customersStore = useCustomersStore()

// Local state
const customerId = parseInt(route.params.id)
const ordersPerPage = ref(20)
const ordersPagination = ref({
  currentPage: 1,
  totalPages: 1,
  totalOrders: 0,
  pages: 1
})

// Items per page options
const itemsPerPageOptions = [
  { value: 10, title: '10' },
  { value: 20, title: '20' },
  { value: 50, title: '50' },
  { value: 100, title: '100' }
]

// Computed
const customer = computed(() => customersStore.currentCustomer)
const customerOrders = computed(() => Array.isArray(customersStore.customerOrders) ? customersStore.customerOrders : [])
const isLoading = computed(() => customersStore.isLoading)

// Table headers
const orderHeaders = [
  { title: 'Order Number', key: 'id', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Total Amount', key: 'total', sortable: true },
  { title: 'Date', key: 'date_created', sortable: true },
]

// Methods
const fetchCustomer = async () => {
  try {
    await customersStore.fetchCustomer(customerId)
    await loadCustomerOrders()
  } catch (error) {
    console.error('Error fetching customer:', error)
    router.push('/customers')
  }
}

const loadCustomerOrders = async () => {
  try {
    const result = await customersStore.fetchCustomerOrders(
      customerId, 
      ordersPagination.value.currentPage, 
      ordersPerPage.value
    )
    
    if (result) {
      ordersPagination.value = {
        currentPage: ordersPagination.value.currentPage,
        totalPages: result.pages || 1,
        pages: result.pages || 1,
        totalOrders: result.total || 0
      }
    }
  } catch (error) {
    console.error('Error loading customer orders:', error)
  }
}

const viewOrder = (orderId) => {
  router.push(`/orders/${orderId}`)
}

// Removed viewOrders navigation (redundant button removed)

// Server table style pagination handler
const onOrdersOptionsUpdate = (options) => {
  const newPage = options.page
  const newPer = options.itemsPerPage
  const pageChanged = ordersPagination.value.currentPage !== newPage
  const perChanged = ordersPerPage.value !== newPer
  if (perChanged) {
    ordersPerPage.value = newPer
    ordersPagination.value.currentPage = 1
  } else if (pageChanged) {
    ordersPagination.value.currentPage = newPage
  }
  if (pageChanged || perChanged) {
    loadCustomerOrders()
  }
}

const getInitials = (firstName, lastName) => {
  const first = firstName ? firstName.charAt(0).toUpperCase() : ''
  const last = lastName ? lastName.charAt(0).toUpperCase() : ''
  return first + last
}

const formatDate = (dateString) => {
  return format(new Date(dateString), 'yyyy/MM/dd')
}

const formatCurrency = (amount) => {
  return parseFloat(amount || 0).toLocaleString()
}

const formatMonth = (monthString) => {
  if (!monthString) return ''
  const [year, month] = monthString.split('-')
  const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ]
  return `${monthNames[parseInt(month) - 1]} ${year}`
}

const getStatusColor = (status) => {
  const statusColors = {
    'pending': 'warning',
    'processing': 'info',
    'on-hold': 'secondary',
    'completed': 'success',
    'cancelled': 'error',
    'refunded': 'error',
    'failed': 'error'
  }
  return statusColors[status] || 'default'
}

const getStatusText = (status) => {
  const statusTexts = {
    'pending': 'Pending Payment',
    'processing': 'Processing',
    'on-hold': 'On Hold',
    'completed': 'Completed',
    'cancelled': 'Cancelled',
    'refunded': 'Refunded',
    'failed': 'Failed'
  }
  return statusTexts[status] || status
}

// Initialize
onMounted(() => {
  fetchCustomer()
})
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
