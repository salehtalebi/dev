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
                <span class="text-h4">جزئیات مشتری</span>
              </VCol>
              <VCol cols="12" md="6" class="text-end">
                <VBtn
                  color="primary"
                  variant="outlined"
                  @click="viewOrders"
                >
                  <VIcon start icon="bx-cart" />
                  مشاهده سفارشات
                </VBtn>
              </VCol>
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
      <p class="mt-4">در حال بارگذاری...</p>
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
                  {{ customer.orders_count || 0 }} سفارش
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
                عضو از: {{ formatDate(customer.date_created) }}
              </div>
            </VCardText>
          </VCard>

          <!-- Customer Stats -->
          <VCard v-if="customerStats">
            <VCardTitle>آمار مشتری</VCardTitle>
            <VCardText>
              <!-- Basic Stats -->
              <div class="mb-6">
                <h3 class="text-h6 mb-4">آمار کلی</h3>
                
                <VRow>
                  <VCol cols="12" md="6">
                    <div class="d-flex justify-space-between align-center mb-3">
                      <span class="text-body-2">تعداد کل سفارشات:</span>
                      <VChip color="primary" variant="tonal">{{ customerStats?.total_orders || 0 }}</VChip>
                    </div>
                    
                    <div class="d-flex justify-space-between align-center mb-3">
                      <span class="text-body-2">مجموع خرید:</span>
                      <span class="font-weight-bold text-success">${{ formatCurrency(customerStats?.total_spent || 0) }}</span>
                    </div>
                    
                    <div class="d-flex justify-space-between align-center mb-3">
                      <span class="text-body-2">میانگین ارزش سفارش:</span>
                      <span class="font-weight-bold">${{ formatCurrency(customerStats?.average_order_value || 0) }}</span>
                    </div>
                  </VCol>
                  
                  <VCol cols="12" md="6">
                    <div class="d-flex justify-space-between align-center mb-3">
                      <span class="text-body-2">رشد نسبت به ماه قبل:</span>
                      <VChip
                        :color="(customerStats?.growth_percentage || 0) >= 0 ? 'success' : 'error'"
                        size="small"
                        variant="tonal"
                      >
                        {{ (customerStats?.growth_percentage || 0) >= 0 ? '+' : '' }}{{ (customerStats?.growth_percentage || 0).toFixed(1) }}%
                      </VChip>
                    </div>
                    
                    <div v-if="customerStats?.last_order_date" class="d-flex justify-space-between align-center mb-3">
                      <span class="text-body-2">آخرین سفارش:</span>
                      <span class="text-caption">{{ formatDate(customerStats.last_order_date) }}</span>
                    </div>
                  </VCol>
                </VRow>
              </div>
              
              <!-- Monthly Stats Chart -->
              <div v-if="customerStats?.monthly_orders?.length" class="mb-6">
                <h3 class="text-h6 mb-4">آمار ماهانه (12 ماه گذشته)</h3>
                <VRow>
                  <VCol cols="12">
                    <div class="pa-4 bg-grey-lighten-4 rounded">
                      <VTable density="compact">
                        <thead>
                          <tr>
                            <th>ماه</th>
                            <th>تعداد سفارش</th>
                            <th>مبلغ خرید</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="monthData in customerStats.monthly_orders" :key="monthData.month">
                            <td>{{ formatMonth(monthData.month) }}</td>
                            <td>
                              <VChip size="small" color="info" variant="tonal">
                                {{ monthData.orders_count }}
                              </VChip>
                            </td>
                            <td class="font-weight-bold">${{ formatCurrency(monthData.total_spent) }}</td>
                          </tr>
                        </tbody>
                      </VTable>
                    </div>
                  </VCol>
                </VRow>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Customer Details & Recent Orders -->
        <VCol cols="12" md="8">
          <!-- Contact Information -->
          <VCard class="mb-6">
            <VCardTitle>اطلاعات تماس</VCardTitle>
            <VCardText>
              <VRow>
                <VCol cols="12" md="6">
                  <div class="mb-4">
                    <div class="text-caption text-medium-emphasis">ایمیل</div>
                    <div>{{ customer.email }}</div>
                  </div>
                </VCol>
                <VCol cols="12" md="6">
                  <div class="mb-4">
                    <div class="text-caption text-medium-emphasis">تلفن</div>
                    <div>{{ customer.billing?.phone || '-' }}</div>
                  </div>
                </VCol>
              </VRow>
              
              <!-- Billing Address -->
              <div v-if="customer.billing">
                <h6 class="text-h6 mb-3">آدرس صورتحساب</h6>
                <div>{{ customer.billing.first_name }} {{ customer.billing.last_name }}</div>
                <div v-if="customer.billing.company">{{ customer.billing.company }}</div>
                <div>{{ customer.billing.address_1 }}</div>
                <div v-if="customer.billing.address_2">{{ customer.billing.address_2 }}</div>
                <div>{{ customer.billing.city }}, {{ customer.billing.state }} {{ customer.billing.postcode }}</div>
                <div>{{ customer.billing.country }}</div>
              </div>
            </VCardText>
          </VCard>

          <!-- Customer Orders with Pagination -->
          <VCard>
            <VCardTitle class="d-flex justify-space-between align-center">
              <span>سفارشات مشتری</span>
              <div class="d-flex align-center gap-2">
                <VSelect
                  v-model="ordersPerPage"
                  :items="[10, 20, 50, 100]"
                  label="تعداد در صفحه"
                  variant="outlined"
                  density="compact"
                  style="width: 120px"
                  @update:model-value="loadCustomerOrders"
                />
              </div>
            </VCardTitle>
            <VCardText>
              <div v-if="!customerOrders || customerOrders.length === 0" class="text-center py-8">
                <p class="text-body-1 text-medium-emphasis">هیچ سفارشی یافت نشد</p>
              </div>
              
              <div v-else>
                <VTable>
                  <thead>
                    <tr>
                      <th>شماره سفارش</th>
                      <th>تاریخ</th>
                      <th>وضعیت</th>
                      <th>مبلغ</th>
                      <th>عملیات</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="order in customerOrders" :key="order.id">
                      <td>
                        <VChip
                          color="primary"
                          variant="outlined"
                          size="small"
                          @click="viewOrder(order.id)"
                          class="cursor-pointer"
                        >
                          #{{ order.id }}
                        </VChip>
                      </td>
                      <td>{{ formatDate(order.date_created) }}</td>
                      <td>
                        <VChip
                          :color="getStatusColor(order.status)"
                          size="small"
                          variant="tonal"
                        >
                          {{ getStatusText(order.status) }}
                        </VChip>
                      </td>
                      <td class="font-weight-bold">${{ parseFloat(order.total || 0).toFixed(2) }}</td>
                      <td>
                        <VBtn
                          icon
                          size="small"
                          variant="text"
                          @click="viewOrder(order.id)"
                        >
                          <VIcon icon="bx-show" />
                        </VBtn>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
                
                <!-- Pagination -->
                <div v-if="ordersPagination.totalPages > 1" class="d-flex justify-center align-center mt-4">
                  <VPagination
                    v-model="ordersPagination.currentPage"
                    :length="ordersPagination.totalPages"
                    :total-visible="5"
                    @update:model-value="loadCustomerOrders"
                  />
                  
                  <div class="text-caption ms-4">
                    نمایش {{ ((ordersPagination.currentPage - 1) * ordersPerPage) + 1 }} تا
                    {{ Math.min(ordersPagination.currentPage * ordersPerPage, ordersPagination.totalOrders) }}
                    از {{ ordersPagination.totalOrders }} سفارش
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<script setup>
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
  totalOrders: 0
})

// Computed
const customer = computed(() => customersStore.currentCustomer)
const customerOrders = computed(() => customersStore.customerOrders)
const customerStats = computed(() => customersStore.customerStats)
const isLoading = computed(() => customersStore.isLoading)

// Methods
const fetchCustomer = async () => {
  try {
    await Promise.all([
      customersStore.fetchCustomer(customerId),
      customersStore.fetchCustomerStatistics(customerId)
    ])
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

const viewOrders = () => {
  router.push(`/orders?customer=${customerId}`)
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
    'ژانویه', 'فوریه', 'مارس', 'آوریل', 'می', 'ژوئن',
    'ژوئیه', 'اوت', 'سپتامبر', 'اکتبر', 'نوامبر', 'دسامبر'
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
    'pending': 'در انتظار پرداخت',
    'processing': 'در حال پردازش',
    'on-hold': 'در انتظار',
    'completed': 'تکمیل شده',
    'cancelled': 'لغو شده',
    'refunded': 'بازگشت داده شده',
    'failed': 'ناموفق'
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
