<template>
  <div>
    <!-- Page Header -->

    <!-- Filters -->
    <VRow class="order-report-table">
      <VCol cols="6">
        <VCardTitle>Orders Reports</VCardTitle>
      </VCol>
      <VCol cols="6">
        <VCard class="mb-6">
          <VCardText>
            <VRow>
              <VCol cols="12" md="3">
                <v-btn
                  color="primary"
                  prepend-icon="nu-filter"
                  class="mb-4 nu-filter-btn"
                  @click="showFilter = true"
                >
                  Filter
                </v-btn>

                <!-- دیالوگ/مودال فیلتر -->
                <v-dialog
                  v-model="showFilter"
                  transition="dialog-bottom-transition"
                  max-width="420"
                  scrollable
                >
                  <v-card class="pa-4 rounded-xl">
                    <v-card-title class="text-h6 font-weight-bold"
                      >Filter</v-card-title
                    >

                    <v-card-text>
                      <div class="text-subtitle-2 mb-2">Order Type</div>

                      <v-row dense>
                        <v-col cols="6">
                          <v-checkbox
                            v-model="filters.orderTypes"
                            value="home"
                            label="Home Delivery"
                            density="compact"
                            hide-details
                          />
                        </v-col>
                        <v-col cols="6">
                          <v-checkbox
                            v-model="filters.orderTypes"
                            value="pickup"
                            label="Pick Up"
                            density="compact"
                            hide-details
                          />
                        </v-col>
                      </v-row>

                      <v-divider class="my-4" />

                      <div class="text-subtitle-2 mb-2">Status</div>
                      <v-select
                        v-model="localFilters.status"
                        :items="orderStatuses"
                        item-title="text"
                        item-value="value"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                      />

                      <div class="text-subtitle-2 mt-4 mb-2">Customer</div>
                      <VTextField
                        v-model="localFilters.search"
                        variant="outlined"
                        density="comfortable"
                        label="جستجو (شماره سفارش، نام مشتری)"
                        prepend-inner-icon="bx-search"
                        hide-details
                        clearable
                      />
                    </v-card-text>

                    <v-card-actions class="pt-2">
                      <v-btn variant="text" @click="reset">Reset</v-btn>
                      <v-spacer />
                      <v-btn
                        color="primary"
                        size="large"
                        class="rounded-xl"
                        block
                        @click="applyFilters"
                      >
                        Filter
                      </v-btn>
                    </v-card-actions>
                  </v-card>
                </v-dialog>
              </VCol>
              <!-- جای دو ورودی تاریخ قبلی -->
              <VCol cols="12" md="6">
                <DateFilter @apply="onDateFilterApply" />
              </VCol>

              <!-- نمایش خلاصه رنج انتخاب‌شده + امکان پاک‌کردن -->
              <VCol
                cols="12"
                md="6"
                v-if="localFilters.date_from || localFilters.date_to"
                class="d-flex align-center"
              >
                <VChip class="mr-3" color="primary" variant="tonal">
                  {{ localFilters.date_from || "—" }} →
                  {{ localFilters.date_to || "—" }}
                </VChip>
                <VBtn variant="outlined" @click="clearDateFilter"
                  >Clear Date</VBtn
                >
              </VCol>

              <VCol cols="12" md="3">
                <VBtn
                  color="primary"
                  :loading="isExporting"
                  @click="exportOrders"
                >
                  <VIcon start icon="bx-download" />
                  Export
                </VBtn>
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
                <VBtn color="primary" @click="applyFilters"> اعمال فیلتر </VBtn>
                <VBtn variant="outlined" @click="clearFilters"> پاک کردن </VBtn>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Orders Table -->

      <VCol cols="12">
        <VCard>
          <VCardText>
            <!-- Loading -->
            <div v-if="isLoading" class="text-center py-8">
              <VProgressCircular size="50" color="primary" indeterminate />
              <p class="mt-4">Loading...</p>
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
import { useOrdersStore } from "@/stores/orders";
import { format } from "date-fns";
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import DateFilter from "@/components/orders/DateFilter.vue";

const router = useRouter();
const ordersStore = useOrdersStore();

// Local state
const isExporting = ref(false);
const localFilters = ref({
  status: "",
  search: "",
  date_from: "",
  date_to: "",
  min_amount: "",
  max_amount: "",
});

// Computed
const orders = computed(() => ordersStore.orders);
const isLoading = computed(() => ordersStore.isLoading);
const totalPages = computed(() => ordersStore.totalPages);
const currentPage = computed({
  get: () => ordersStore.currentPage,
  set: (value) => ordersStore.setPage(value),
});
const orderStatuses = computed(() => ordersStore.orderStatuses);

// Table headers
const headers = [
  { title: "Name", key: "id", sortable: true },
  { title: "Order Date", key: "customer", sortable: false },
  { title: "Order Type", key: "status", sortable: true },
  { title: "Orders ID", key: "total", sortable: true },
  { title: "Total", key: "date_created", sortable: true },
  { title: "Invoice", key: "actions", sortable: false },
  { title: "Packing List", key: "actions", sortable: false },
  { title: "Tracking Number", key: "actions", sortable: false },
  { title: "Origin", key: "customer", sortable: false },
  { title: "Status", key: "customer", sortable: false },
  { title: "view", key: "actions", sortable: false },
];

// Methods
const fetchOrders = () => {
  ordersStore.fetchOrders();
};

const applyFilters = () => {
  ordersStore.setFilters(localFilters.value);
  fetchOrders();
  if (showFilter.value) {
    showFilter.value = false;
  }
};

const clearFilters = () => {
  localFilters.value = {
    status: "",
    search: "",
    date_from: "",
    date_to: "",
    min_amount: "",
    max_amount: "",
  };
  ordersStore.clearFilters();
  fetchOrders();
};

const viewOrder = (orderId) => {
  router.push(`/orders/${orderId}`);
};

const getStatusColor = (status) => {
  const statusObj = orderStatuses.value.find((s) => s.value === status);
  return statusObj?.color || "default";
};

const getStatusText = (status) => {
  const statusObj = orderStatuses.value.find((s) => s.value === status);
  return statusObj?.text || status;
};

const formatDate = (dateString) => {
  return format(new Date(dateString), "yyyy/MM/dd HH:mm");
};

const exportOrders = async () => {
  isExporting.value = true;
  try {
    // Implementation for Excel export
    console.log("Exporting orders...");
  } catch (error) {
    console.error("Export error:", error);
  } finally {
    isExporting.value = false;
  }
};

// Watch for page changes
watch(currentPage, () => {
  fetchOrders();
});

// Initialize
onMounted(() => {
  fetchOrders();
});

// نمایش/مخفی‌سازی مودال
const showFilter = ref(false);

// مدل فیلترها
const filters = ref({
  orderTypes: [], // ['home', 'pickup']
  status: "all",
  customer: "all",
});

// آیتم‌های انتخابی (نمونه؛ مقادیر خودت رو جایگزین کن)
const statusItems = [
  { label: "All", value: "all" },
  { label: "Pending", value: "pending" },
  { label: "Processing", value: "processing" },
  { label: "Completed", value: "completed" },
  { label: "Cancelled", value: "cancelled" },
];

const customerItems = [
  { label: "All", value: "all" },
  { label: "Alice", value: 1 },
  { label: "Bob", value: 2 },
];

// دکمه‌ها
function reset() {
  filters.value = { orderTypes: [], status: "all", customer: "all" };
}

// همان فایل orders.vue داخل <script setup>
const onDateFilterApply = ({ start, end /*, mode */ }) => {
  // ست‌کردن روی فیلترهای لوکال صفحه
  localFilters.value.date_from = start || "";
  localFilters.value.date_to = end || "";

  // اجرای فیلترها (هر دو تاریخ را به استور می‌دهیم و fetch می‌زنیم)
  applyFilters();
};

const clearDateFilter = () => {
  localFilters.value.date_from = "";
  localFilters.value.date_to = "";
  applyFilters();
};
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
.v-table th {
  text-transform: unset !important;
  border: 1px solid red;
}
.order-report-table {
  margin-top: 0 !important;
  border-radius: 12px;
}
.order-report-table .v-card {
  box-shadow: none !important;
  margin-bottom: 0 !important;
}
.order-report-table .v-col {
  padding: 0 !important;
  box-shadow: none !important;
}

.order-report-table .v-card-title {
  color: #000;
  width: calc(100% - 48px);
  border-bottom: 1px solid;
  margin: 0 24px 30px 24px;
}
.nu-filter-btn {
   color: #53545C !important;
   border: 1px solid #53545C;
   background-color: #fff !important;
   box-shadow: none !important;
}
.nu-filter-btn:hover , .nu-filter-btn:focus{
 background-color: #fff !important;
}
</style>

