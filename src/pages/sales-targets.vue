<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex justify-space-between align-center">
        <span>Sales Targets Management</span>
        <VBtn v-if="isSuperAdmin" color="primary" size="small" @click="openCreate">New Target</VBtn>
      </VCardTitle>
      <VCardText>
        <VAlert v-if="error" type="error" class="mb-4">{{ error }}</VAlert>
        <VRow class="mb-4" v-if="isSuperAdmin">
          <VCol cols="12" md="3">
            <VSelect
              :items="periodFilterOptions"
              item-title="text"
              item-value="value"
              v-model="filters.period_type"
              label="Period Type"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <VTextField v-model="filters.period_key" label="Period Key (auto if empty)" placeholder="YYYY-MM or YYYY-Www or YYYY" />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              v-model="filters.manager_id"
              :items="accountManagers"
              item-title="text"
              item-value="value"
              label="Account Manager"
              clearable
            />
          </VCol>
          <VCol cols="12" md="3">
            <VSelect
              :items="brandOptions"
              item-title="text"
              item-value="value"
              v-model="filters.brand"
              label="Brand"
              clearable
            />
          </VCol>
          <VCol cols="12" class="d-flex gap-2">
            <VBtn color="primary" @click="applyFilters" :loading="loading.list">Apply</VBtn>
            <VBtn variant="tonal" @click="resetFilters">Reset</VBtn>
          </VCol>
        </VRow>

        <VTable density="comfortable" class="text-no-wrap">
          <thead>
            <tr>
              <th>ID</th>
              <th>Manager</th>
              <th>Category</th>
              <th>Period</th>
              <th>Target</th>
              <th>Currency</th>
              <th>Created</th>
              <th v-if="isSuperAdmin">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!targets.length && !loading.list">
              <td colspan="8" class="text-center text-medium-emphasis py-6">No targets found.</td>
            </tr>
            <tr v-for="t in targets" :key="t.id">
              <td>{{ t.id }}</td>
              <td>{{ t.manager_id || 'GLOBAL' }}</td>
              <td>{{ t.brand_slug || '—' }}</td>
              <td>{{ t.period_type }} / {{ t.period_key }}</td>
              <td>{{ formatCurrency(t.target_amount) }}</td>
              <td>{{ t.currency }}</td>
              <td>{{ formatDate(t.created_at) }}</td>
              <td v-if="isSuperAdmin">
                <VBtn icon size="x-small" variant="text" @click="editTarget(t)"><VIcon icon="bx-edit" /></VBtn>
                <VBtn icon size="x-small" variant="text" color="error" @click="removeTarget(t)"><VIcon icon="bx-trash" /></VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <!-- Dialog Create/Edit -->
    <VDialog v-model="dialog" max-width="600">
      <VCard>
        <VCardTitle>{{ editing ? 'Edit Target' : 'New Target' }}</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12" md="4">
              <VSelect :items="periodTypes" v-model="form.period_type" label="Period Type" />
            </VCol>
            <VCol cols="12" md="4">
              <VTextField v-model="form.period_key" label="Period Key" placeholder="YYYY-MM or YYYY-Www or YYYY" />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="form.manager_id"
                :items="accountManagers"
                item-title="text"
                item-value="value"
                label="Account Manager"
                clearable
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                :items="brandOptions"
                item-title="text"
                item-value="value"
                v-model="form.brand"
                label="Brand"
                clearable
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model.number="form.target_amount" label="Target Amount" type="number" min="0" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField v-model="form.currency" label="Currency" />
            </VCol>
            <VCol cols="12" md="6">
              <VTextarea v-model="form.notes" label="Notes" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="closeDialog">Cancel</VBtn>
          <VBtn color="primary" :loading="loading.mutate" @click="saveTarget">Save</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<script setup>
import { useSalesTargets } from '@/composables/useSalesTargets'
import { useSalesTargetsStore } from '@/stores/salesTargets'
import { computed, onMounted, ref } from 'vue'

const { targets, fetchTargets, createTarget, updateTarget, deleteTarget, brands, fetchBrands } = useSalesTargets()
const store = useSalesTargetsStore()

const isSuperAdmin = computed(() => store.isSuperAdmin)
const loading = computed(() => store.loading)
const error = computed(() => store.error)

const periodTypes = ['week','month','year']
const periodFilterOptions = [
  { text: 'All Periods', value: '' },
  { text: 'Week', value: 'week' },
  { text: 'Month', value: 'month' },
  { text: 'Year', value: 'year' }
]
const accountManagers = [
  { text: 'Global (All Managers)', value: '' },
  { text: 'House', value: 'house' },
  { text: 'Ina Istok', value: '1465' },
  { text: 'Pina Lee', value: '845' },
  { text: 'Vidika Shenton', value: '1886' },
  { text: 'Sarah Hearn', value: '2532' },
  { text: 'Jonathon Regan', value: '2533' }
]
const filters = ref({ period_type: '', period_key: '', manager_id: '', brand: null })
const brandOptions = computed(() => brands.value.map(b => ({ text: b.name, value: b.slug })))

const dialog = ref(false)
const editing = ref(false)
const form = ref({ period_type: 'month', period_key: '', manager_id: '', brand: null, target_amount: 0, currency: 'USD', notes: '' })
let editingId = null

function applyFilters() {
  const p = { ...filters.value }
  Object.keys(p).forEach(k => { if (!p[k]) delete p[k] })
  fetchTargets(p)
}
function resetFilters() {
  filters.value = { period_type: '', period_key: '', manager_id: '', brand: null }
  applyFilters()
}
function openCreate() {
  editing.value = false
  editingId = null
  form.value = { period_type: 'month', period_key: '', manager_id: '', brand: null, target_amount: 0, currency: 'USD', notes: '' }
  dialog.value = true
}
function editTarget(t) {
  editing.value = true
  editingId = t.id
  form.value = { period_type: t.period_type, period_key: t.period_key, manager_id: t.manager_id || '', brand: t.brand_slug || null, target_amount: t.target_amount, currency: t.currency, notes: t.notes || '' }
  dialog.value = true
}
async function removeTarget(t) {
  if (!confirm('Delete target?')) return
  await deleteTarget(t.id)
}
function closeDialog() { dialog.value = false }
async function saveTarget() {
  const payload = { ...form.value }
  if (!payload.period_key) {
    // auto derive
    const now = new Date()
    if (payload.period_type === 'month') payload.period_key = now.toISOString().slice(0,7)
    else if (payload.period_type === 'year') payload.period_key = now.getFullYear().toString()
    else if (payload.period_type === 'week') {
      const d = new Date()
      // ISO week number
      const dayNum = (d.getUTCDay() + 6) % 7
      d.setUTCDate(d.getUTCDate() - dayNum + 3)
      const isoYear = d.getUTCFullYear()
      const firstThursday = new Date(Date.UTC(isoYear,0,4))
      const weekNum = Math.ceil(((d - firstThursday) / 86400000 + 1)/7)
      payload.period_key = `${isoYear}-W${String(weekNum).padStart(2,'0')}`
    }
  }
  if (editing.value && editingId) {
    await updateTarget(editingId, { target_amount: payload.target_amount, currency: payload.currency, notes: payload.notes })
  } else {
    await createTarget(payload)
  }
  dialog.value = false
  applyFilters()
}

import { API_CONFIG } from '@/config/api'
function formatCurrency(v, currency='USD') { return API_CONFIG.formatCurrency(Number(v||0), currency) }
function formatDate(v) { if(!v) return ''; return new Date(v).toLocaleString() }

onMounted(async () => {
  await fetchBrands()
  applyFilters()
})
</script>
