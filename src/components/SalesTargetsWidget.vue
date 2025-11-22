<template>
  <VCard>
    <VCardTitle class="d-flex flex-wrap gap-4 align-center justify-space-between">
      <span>Sales Targets Progress</span>
      <div class="d-flex flex-wrap gap-3">
        <VSelect
          v-model="periodType"
          :items="periodOptions"
          item-title="text"
          item-value="value"
          label="Period"
          density="compact"
          hide-details
          style="min-width: 150px"
        />
        <VSelect
          v-model="selectedBrand"
          :items="brandOptions"
          item-title="text"
          item-value="value"
          label="Brand"
          density="compact"
          hide-details
          clearable
          style="min-width: 200px"
        />
      </div>
    </VCardTitle>
    <VCardText>
      <div v-if="isLoading" class="text-center py-8">
        <VProgressCircular indeterminate color="primary" />
        <p class="mt-2 text-medium-emphasis">Loading targets…</p>
      </div>

      <template v-else>
        <div v-if="chartCategories.length">
          <VueApexCharts type="bar" height="320" :series="chartSeries" :options="chartOptions" />
        </div>
        <div v-else class="text-center py-6 text-medium-emphasis">
          {{ infoMessage }}
        </div>

        <VDivider class="my-4" />

        <VRow>
          <VCol cols="12" md="4">
            <VCard variant="tonal">
              <VCardText>
                <div class="text-caption text-medium-emphasis">{{ summaryTitle }}</div>
                <div class="text-h5 mb-1">
                  {{ globalSummary ? formatCurrency(globalSummary.target_amount, globalSummary.currency) : '—' }}
                </div>
                <div class="text-caption">{{ globalSummary?.period_label }}</div>
                <VProgressLinear
                  v-if="globalSummary"
                  :model-value="globalSummary.progress_percentage"
                  height="8"
                  class="mt-3"
                  color="primary"
                />
                <div class="text-caption mt-2" v-if="globalSummary">
                  {{ formatCurrency(globalSummary.actual_revenue, globalSummary.currency) }} actual ·
                  {{ globalSummary.progress_percentage.toFixed(1) }}%
                </div>
                <div class="text-caption text-medium-emphasis mt-2" v-else>
                  No target for this selection
                </div>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="12" md="8">
            <VTable density="comfortable">
              <thead>
                <tr>
                  <th>Manager</th>
                  <th>Target</th>
                  <th>Actual</th>
                  <th>Progress</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!managerRows.length">
                  <td colspan="4" class="text-center text-medium-emphasis">
                    {{ infoMessage }}
                  </td>
                </tr>
                <tr v-for="row in managerRows" :key="row.manager_id || 'self'">
                  <td>{{ row.manager_name }}</td>
                  <td>{{ row.hasTarget ? formatCurrency(row.target_amount, row.currency) : 'No target' }}</td>
                  <td>{{ formatCurrency(row.actual_revenue, row.currency) }}</td>
                  <td style="min-width: 200px">
                    <div class="d-flex align-center gap-2">
                      <VProgressLinear :model-value="row.progress_percentage" height="6" color="success" rounded />
                      <span class="text-caption">{{ row.progress_percentage.toFixed(1) }}%</span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCol>
        </VRow>

        <div class="text-caption text-medium-emphasis mt-4" v-if="infoMessage">
          {{ infoMessage }}
        </div>
      </template>
    </VCardText>
  </VCard>
</template>

<script setup>
import { useSalesTargets } from '@/composables/useSalesTargets'
import { API_CONFIG } from '@/config/api'
import { useAuthStore } from '@/stores/auth'
import { useSalesTargetsStore } from '@/stores/salesTargets'
import { computed, onMounted, ref, watch } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'

const { fetchProgress, fetchBrands, brands } = useSalesTargets()
const store = useSalesTargetsStore()
const auth = useAuthStore()
const vuetifyTheme = useTheme()

const periodOptions = [
  { text: 'Week', value: 'week' },
  { text: 'Month', value: 'month' },
  { text: 'Year', value: 'year' },
]

const managerRoster = [
  { text: 'Ina Istok', value: '1465' },
  { text: 'Pina Lee', value: '845' },
  { text: 'Vidika Shenton', value: '1886' },
  { text: 'Sarah Hearn', value: '2532' },
  { text: 'Jonathon Regan', value: '2533' },
]

const periodType = ref('month')
const selectedBrand = ref(null)
const isLoading = ref(false)
const managerRows = ref([])
const globalSummary = ref(null)
const chartSeries = ref([])
const chartCategories = ref([])
const infoMessage = ref('Select a target period to view progress')
const initialized = ref(false)

const isSuperAdmin = computed(() => store.isSuperAdmin)
const myManagerId = computed(() => store.myManagerId)
const myManagerName = computed(() => auth.user?.display_name || auth.user?.name || 'My Target')
const brandOptions = computed(() => {
  const base = [{ text: 'All Brands', value: null }]
  return base.concat(brands.value.map(b => ({ text: b.name, value: b.slug })))
})
const summaryTitle = computed(() => (isSuperAdmin.value ? 'Global Target' : 'My Target'))

const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  return {
    chart: { toolbar: { show: false }, parentHeightOffset: 0, stacked: false },
    colors: [currentTheme.primary, currentTheme.success],
    dataLabels: { enabled: false },
    grid: { borderColor: currentTheme['background'] },
    xaxis: {
      categories: chartCategories.value,
      labels: { style: { colors: currentTheme['on-surface'], fontSize: '12px' } },
    },
    yaxis: {
      labels: { style: { colors: currentTheme['on-surface'], fontSize: '12px' } },
    },
    plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
    legend: { show: true },
  }
})

function formatCurrency(value, currency = 'USD') {
  return API_CONFIG.formatCurrency(Number(value || 0), currency)
}

function derivePeriodKey(type) {
  const now = new Date()
  if (type === 'year') return now.getFullYear().toString()
  if (type === 'month') return now.toISOString().slice(0, 7)
  if (type === 'week') {
    const d = new Date()
    const dayNum = (d.getUTCDay() + 6) % 7
    d.setUTCDate(d.getUTCDate() - dayNum + 3)
    const isoYear = d.getUTCFullYear()
    const firstThursday = new Date(Date.UTC(isoYear, 0, 4))
    const weekNum = Math.ceil(((d - firstThursday) / 86400000 + 1) / 7)
    return `${isoYear}-W${String(weekNum).padStart(2, '0')}`
  }
  return now.toISOString().slice(0, 7)
}

function describeSelection(key) {
  const brandLabel = selectedBrand.value ? `brand “${brandOptions.value.find(b => b.value === selectedBrand.value)?.text || selectedBrand.value}”` : 'all brands'
  return `${periodType.value.toUpperCase()} (${key}) · ${brandLabel}`
}

function normalizeProgress(data, meta, periodKey) {
  if (!data) return null
  return {
    manager_id: meta.value,
    manager_name: meta.text,
    target_amount: Number(data.target_amount || 0),
    actual_revenue: Number(data.actual_revenue || 0),
    currency: data.currency || 'USD',
    progress_percentage: Number(data.progress_percentage || 0),
    hasTarget: Number(data.target_amount || 0) > 0,
    period_label: `${periodType.value.toUpperCase()} ${periodKey}`,
  }
}

async function loadData() {
  isLoading.value = true
  const periodKey = derivePeriodKey(periodType.value)
  const brand = selectedBrand.value || undefined
  const scopedManagers = isSuperAdmin.value
    ? managerRoster
    : (myManagerId.value ? [{ text: myManagerName.value, value: myManagerId.value }] : [])

  try {
    const managerPromises = scopedManagers.map(meta => (
      fetchProgress({ period_type: periodType.value, period_key: periodKey, brand, manager_id: meta.value })
        .then(data => normalizeProgress(data, meta, periodKey))
    ))

    const managerResults = await Promise.all(managerPromises)
    managerRows.value = managerResults.filter(Boolean)

    if (isSuperAdmin.value) {
      const globalData = await fetchProgress({ period_type: periodType.value, period_key: periodKey, brand })
      globalSummary.value = globalData ? normalizeProgress(globalData, { text: 'Global', value: null }, periodKey) : null
    } else {
      globalSummary.value = managerRows.value[0] || null
    }

    updateChart()

    if (!managerRows.value.length && (!globalSummary.value || !globalSummary.value.hasTarget)) {
      infoMessage.value = `No targets configured for ${describeSelection(periodKey)}.`
    } else {
      infoMessage.value = `Showing targets for ${describeSelection(periodKey)}${isSuperAdmin.value ? '' : ' (your scope only)'}.`
    }
  } finally {
    isLoading.value = false
    initialized.value = true
  }
}

function updateChart() {
  chartCategories.value = managerRows.value.map(row => row.manager_name)
  chartSeries.value = [
    { name: 'Target', data: managerRows.value.map(row => row.target_amount) },
    { name: 'Actual', data: managerRows.value.map(row => row.actual_revenue) },
  ]
}

watch([periodType, selectedBrand], () => {
  if (initialized.value) loadData()
})

onMounted(async () => {
  await fetchBrands()
  await loadData()
})
</script>
