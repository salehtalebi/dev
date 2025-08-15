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
                <span class="text-h4">جزئیات سفارش #{{ orderId }}</span>
              </VCol>
              <VCol cols="12" md="6" class="text-end">
                <VSelect
                  v-model="orderStatus"
                  :items="orderStatuses"
                  item-title="text"
                  item-value="value"
                  label="تغییر وضعیت"
                  style="max-width: 200px"
                  class="d-inline-block"
                  @update:model-value="updateStatus"
                />
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

    <!-- Order Details -->
    <div v-else-if="order">
      <VRow>
        <!-- Order Info -->
        <VCol cols="12" md="8">
          <VCard class="mb-6">
            <VCardTitle>اطلاعات سفارش</VCardTitle>
            <VCardText>
              <VRow>
                <VCol cols="6">
                  <div class="mb-4">
                    <div class="text-caption text-medium-emphasis">شماره سفارش</div>
                    <div class="font-weight-bold">#{{ order.id }}</div>
                  </div>
                </VCol>
                <VCol cols="6">
                  <div class="mb-4">
                    <div class="text-caption text-medium-emphasis">تاریخ سفارش</div>
                    <div>{{ formatDate(order.date_created) }}</div>
                  </div>
                </VCol>
                <VCol cols="6">
                  <div class="mb-4">
                    <div class="text-caption text-medium-emphasis">وضعیت</div>
                    <VChip
                      :color="getStatusColor(order.status)"
                      size="small"
                      variant="tonal"
                    >
                      {{ getStatusText(order.status) }}
                    </VChip>
                  </div>
                </VCol>
                <VCol cols="6">
                  <div class="mb-4">
                    <div class="text-caption text-medium-emphasis">مبلغ کل</div>
                    <div class="text-h6 text-success">${{ parseFloat(order.total).toFixed(2) }}</div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <!-- Order Items -->
          <VCard class="mb-6">
            <VCardTitle>آیتم‌های سفارش</VCardTitle>
            <VCardText>
              <VTable>
                <thead>
                  <tr>
                    <th>محصول</th>
                    <th>تعداد</th>
                    <th>قیمت واحد</th>
                    <th>جمع</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in order.line_items" :key="item.id">
                    <td>
                      <div class="font-weight-medium">{{ item.name }}</div>
                      <div v-if="item.variation && item.variation.length" class="text-caption">
                        <span v-for="variation in item.variation" :key="variation.id">
                          {{ variation.attribute }}: {{ variation.value }}
                        </span>
                      </div>
                    </td>
                    <td>{{ item.quantity }}</td>
                    <td>${{ parseFloat(item.price).toFixed(2) }}</td>
                    <td class="font-weight-bold">${{ parseFloat(item.total).toFixed(2) }}</td>
                  </tr>
                </tbody>
              </VTable>

              <!-- Order Totals -->
              <VDivider class="my-4" />
              <VRow class="justify-end">
                <VCol cols="12" md="6">
                  <div class="d-flex justify-space-between mb-2">
                    <span>جمع فرعی:</span>
                    <span>${{ parseFloat(order.subtotal || 0).toFixed(2) }}</span>
                  </div>
                  <div v-if="order.total_tax && parseFloat(order.total_tax) > 0" class="d-flex justify-space-between mb-2">
                    <span>مالیات:</span>
                    <span>${{ parseFloat(order.total_tax).toFixed(2) }}</span>
                  </div>
                  <div v-if="order.shipping_total && parseFloat(order.shipping_total) > 0" class="d-flex justify-space-between mb-2">
                    <span>هزینه ارسال:</span>
                    <span>${{ parseFloat(order.shipping_total).toFixed(2) }}</span>
                  </div>
                  <div v-if="order.discount_total && parseFloat(order.discount_total) > 0" class="d-flex justify-space-between mb-2 text-success">
                    <span>تخفیف:</span>
                    <span>-${{ parseFloat(order.discount_total).toFixed(2) }}</span>
                  </div>
                  <VDivider class="my-2" />
                  <div class="d-flex justify-space-between text-h6 font-weight-bold">
                    <span>مجموع:</span>
                    <span>${{ parseFloat(order.total).toFixed(2) }}</span>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Customer & Billing Info -->
        <VCol cols="12" md="4">
          <!-- Customer Info -->
          <VCard class="mb-6">
            <VCardTitle>اطلاعات مشتری</VCardTitle>
            <VCardText>
              <div class="mb-4">
                <div class="font-weight-bold">{{ order.billing.first_name }} {{ order.billing.last_name }}</div>
                <div class="text-body-2">{{ order.billing.email }}</div>
                <div v-if="order.billing.phone" class="text-body-2">{{ order.billing.phone }}</div>
              </div>
              
              <VBtn
                color="primary"
                variant="outlined"
                size="small"
                @click="viewCustomer"
              >
                مشاهده پروفایل مشتری
              </VBtn>
            </VCardText>
          </VCard>

          <!-- Billing Address -->
          <VCard class="mb-6">
            <VCardTitle>آدرس صورتحساب</VCardTitle>
            <VCardText>
              <div>{{ order.billing.first_name }} {{ order.billing.last_name }}</div>
              <div v-if="order.billing.company">{{ order.billing.company }}</div>
              <div>{{ order.billing.address_1 }}</div>
              <div v-if="order.billing.address_2">{{ order.billing.address_2 }}</div>
              <div>{{ order.billing.city }}, {{ order.billing.state }} {{ order.billing.postcode }}</div>
              <div>{{ order.billing.country }}</div>
            </VCardText>
          </VCard>

          <!-- Shipping Address -->
          <VCard v-if="order.shipping && Object.keys(order.shipping).length">
            <VCardTitle>آدرس ارسال</VCardTitle>
            <VCardText>
              <div>{{ order.shipping.first_name }} {{ order.shipping.last_name }}</div>
              <div v-if="order.shipping.company">{{ order.shipping.company }}</div>
              <div>{{ order.shipping.address_1 }}</div>
              <div v-if="order.shipping.address_2">{{ order.shipping.address_2 }}</div>
              <div>{{ order.shipping.city }}, {{ order.shipping.state }} {{ order.shipping.postcode }}</div>
              <div>{{ order.shipping.country }}</div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Order Notes -->
      <VRow v-if="order.customer_note">
        <VCol cols="12">
          <VCard>
            <VCardTitle>یادداشت مشتری</VCardTitle>
            <VCardText>
              {{ order.customer_note }}
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </div>
  </div>
</template>

<script setup>
import { useOrdersStore } from '@/stores/orders'
import { format } from 'date-fns'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const ordersStore = useOrdersStore()

// Local state
const orderId = route.params.id
const orderStatus = ref('')

// Computed
const order = computed(() => ordersStore.currentOrder)
const isLoading = computed(() => ordersStore.isLoading)
const orderStatuses = computed(() => ordersStore.orderStatuses)

// Methods
const fetchOrder = async () => {
  try {
    await ordersStore.fetchOrder(orderId)
    if (order.value) {
      orderStatus.value = order.value.status
    }
  } catch (error) {
    console.error('Error fetching order:', error)
    router.push('/orders')
  }
}

const updateStatus = async (newStatus) => {
  try {
    await ordersStore.updateOrderStatus(orderId, newStatus)
    // Show success message
  } catch (error) {
    console.error('Error updating status:', error)
    // Reset status
    orderStatus.value = order.value.status
  }
}

const viewCustomer = () => {
  if (order.value?.customer_id) {
    router.push(`/customers/${order.value.customer_id}`)
  }
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

// Initialize
onMounted(() => {
  fetchOrder()
})
</script>
