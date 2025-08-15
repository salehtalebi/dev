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
              <div class="mb-4">
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-body-2">تعداد کل سفارشات:</span>
                  <span class="font-weight-bold">{{ customerStats.totalOrders }}</span>
                </div>
                
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-body-2">میانگین ارزش سفارش:</span>
                  <span class="font-weight-bold">${{ customerStats.averageOrderValue.toFixed(2) }}</span>
                </div>
                
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-body-2">خرید ماه جاری:</span>
                  <span class="font-weight-bold">${{ customerStats.currentMonthTotal.toFixed(2) }}</span>
                </div>
                
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-body-2">رشد نسبت به ماه قبل:</span>
                  <VChip
                    :color="customerStats.growthPercentage >= 0 ? 'success' : 'error'"
                    size="small"
                    variant="tonal"
                  >
                    {{ customerStats.growthPercentage >= 0 ? '+' : '' }}{{ customerStats.growthPercentage.toFixed(1) }}%
                  </VChip>
                </div>
                
                <div v-if="customerStats.lastOrderDate" class="d-flex justify-space-between align-center">
                  <span class="text-body-2">آخرین سفارش:</span>
                  <span class="text-caption">{{ formatDate(customerStats.lastOrderDate) }}</span>
                </div>
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

          <!-- Recent Orders -->
          <VCard>
            <VCardTitle>آخرین سفارشات</VCardTitle>
            <VCardText>
              <div v-if="customerOrders.length === 0" class="text-center py-8">
                <p class="text-body-1 text-medium-emphasis">هیچ سفارشی یافت نشد</p>
              </div>
              
              <VTable v-else>
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
                  <tr v-for="order in customerOrders.slice(0, 10)" :key="order.id">
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
                    <td class="font-weight-bold">${{ parseFloat(order.total).toFixed(2) }}</td>
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
              
              <div v-if="customerOrders.length > 10" class="text-center mt-4">
                <VBtn
                  color="primary"
                  variant="outlined"
                  @click="viewOrders"
                >
                  مشاهده همه سفارشات
                </VBtn>
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
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const customersStore = useCustomersStore()

// Local state
const customerId = route.params.id

// Computed
const customer = computed(() => customersStore.currentCustomer)
const customerOrders = computed(() => customersStore.customerOrders)
const customerStats = computed(() => customersStore.customerStats)
const isLoading = computed(() => customersStore.isLoading)

// Methods
const fetchCustomer = async () => {
  try {
    await customersStore.fetchCustomer(customerId)
    await customersStore.fetchCustomerOrders(customerId)
  } catch (error) {
    console.error('Error fetching customer:', error)
    router.push('/customers')
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
