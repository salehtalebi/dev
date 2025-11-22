<template>
  <VCard>
    <VCardTitle class="d-flex justify-space-between align-center">
      <span>Sales Targets Progress</span>
      <VSelect v-model="selectedBrand" :items="brandOptions" label="Category" hide-details density="compact" :clearable="true" style="max-width:220px" />
    </VCardTitle>
    <VCardText>
      <VRow>
        <VCol cols="12" md="4" v-for="p in periodTypes" :key="p">
          <div class="font-weight-medium mb-2 text-capitalize">{{ p }} Progress</div>
          <div v-if="progressData[p]">
            <div class="d-flex justify-space-between mb-1 text-caption">
              <span>{{ progressData[p].period_key }}</span>
              <span>{{ progressPercent(progressData[p]) }}%</span>
            </div>
            <VProgressLinear :model-value="progressData[p].progress_percentage" height="10" rounded color="primary" />
            <div class="mt-1 text-caption">
              {{ formatCurrency(progressData[p].actual_revenue) }} / {{ formatCurrency(progressData[p].target_amount) }} {{ progressData[p].currency }}
            </div>
          </div>
          <div v-else class="text-medium-emphasis text-caption">No target</div>
        </VCol>
      </VRow>
      <VDivider class="my-4" />
      <div class="text-caption text-medium-emphasis mb-2">Manager (if any) vs Global comparison</div>
      <VRow>
        <VCol cols="12" md="6">
          <div class="font-weight-medium mb-1">Global Month</div>
          <template v-if="globalMonth">
            <VProgressLinear :model-value="globalMonth.progress_percentage" height="8" color="success" />
            <div class="text-caption mt-1">{{ formatCurrency(globalMonth.actual_revenue) }} / {{ formatCurrency(globalMonth.target_amount) }} {{ globalMonth.currency }}</div>
          </template>
          <div v-else class="text-caption text-medium-emphasis">No global target</div>
        </VCol>
        <VCol cols="12" md="6" v-if="managerMonth">
          <div class="font-weight-medium mb-1">My Month</div>
          <VProgressLinear :model-value="managerMonth.progress_percentage" height="8" color="info" />
          <div class="text-caption mt-1">{{ formatCurrency(managerMonth.actual_revenue) }} / {{ formatCurrency(managerMonth.target_amount) }} {{ managerMonth.currency }}</div>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>

<script setup>
import { useSalesTargets } from '@/composables/useSalesTargets'
import { useSalesTargetsStore } from '@/stores/salesTargets'
import { computed, onMounted, ref, watch } from 'vue'

const { fetchProgress, brandCategories, fetchBrandCategories } = useSalesTargets()
const store = useSalesTargetsStore()

const periodTypes = ['week','month','year']
const selectedBrand = ref(null)
const brandOptions = computed(() => brandCategories.value.map(c => c.slug))

const progressData = ref({})
const managerId = computed(() => store.myManagerId)
const isSuperAdmin = computed(() => store.isSuperAdmin)

import { API_CONFIG } from '@/config/api'
function formatCurrency(v, currency='USD'){return API_CONFIG.formatCurrency(Number(v||0), currency)}
function progressPercent(d){return (d?.progress_percentage ?? 0).toFixed(1)}

async function loadProgress() {
  const brand = selectedBrand.value || undefined
  const now = new Date()
  const monthKey = now.toISOString().slice(0,7)
  // week key
  const d = new Date()
  const dayNum = (d.getUTCDay() + 6) % 7
  d.setUTCDate(d.getUTCDate() - dayNum + 3)
  const isoYear = d.getUTCFullYear()
  const firstThursday = new Date(Date.UTC(isoYear,0,4))
  const weekNum = Math.ceil(((d - firstThursday) / 86400000 + 1)/7)
  const weekKey = `${isoYear}-W${String(weekNum).padStart(2,'0')}`
  const yearKey = now.getFullYear().toString()

  const periods = { week: weekKey, month: monthKey, year: yearKey }
  const result = {}
  for (const [ptype, pkey] of Object.entries(periods)) {
    result[ptype] = await fetchProgress({ period_type: ptype, period_key: pkey, brand })
  }
  progressData.value = result
}

const globalMonth = computed(() => progressData.value.month && !progressData.value.month.manager_id ? progressData.value.month : (progressData.value.month?.manager_id ? null : progressData.value.month))
const managerMonth = computed(() => progressData.value.month && progressData.value.month.manager_id ? progressData.value.month : null)

watch(selectedBrand, () => loadProgress())

onMounted(async () => {
  await fetchBrandCategories()
  await loadProgress()
})
</script>
