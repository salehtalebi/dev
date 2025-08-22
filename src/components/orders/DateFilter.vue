<!-- DateFilter.vue -->
<template>
  <v-btn color="primary" class="mb-4" @click="open = true" prepend-icon="mdi-filter-variant">
    Filter
  </v-btn>

  <v-dialog v-model="open" max-width="420" scrollable transition="dialog-bottom-transition">
    <v-card class="pa-4 rounded-xl">
      <v-card-title class="text-h6 font-weight-bold">By Date</v-card-title>

      <v-card-text>
        <!-- Presets -->
        <v-row dense>
          <v-col cols="6">
            <v-checkbox
              label="This Week"
              :model-value="mode === 'thisWeek'"
              @update:model-value="onModeToggle('thisWeek', $event)"
              hide-details density="compact"
              :disabled="mode === 'range'"
            />
          </v-col>
          <v-col cols="6">
            <v-checkbox
              label="Last Week"
              :model-value="mode === 'lastWeek'"
              @update:model-value="onModeToggle('lastWeek', $event)"
              hide-details density="compact"
              :disabled="mode === 'range'"
            />
          </v-col>

          <v-col cols="6">
            <v-checkbox
              label="This Month"
              :model-value="mode === 'thisMonth'"
              @update:model-value="onModeToggle('thisMonth', $event)"
              hide-details density="compact"
              :disabled="mode === 'range'"
            />
          </v-col>
          <v-col cols="6">
            <v-checkbox
              label="Last Month"
              :model-value="mode === 'lastMonth'"
              @update:model-value="onModeToggle('lastMonth', $event)"
              hide-details density="compact"
              :disabled="mode === 'range'"
            />
          </v-col>

          <v-col cols="6">
            <v-checkbox
              label="This Year"
              :model-value="mode === 'thisYear'"
              @update:model-value="onModeToggle('thisYear', $event)"
              hide-details density="compact"
              :disabled="mode === 'range'"
            />
          </v-col>
          <v-col cols="6">
            <v-checkbox
              label="Last Year"
              :model-value="mode === 'lastYear'"
              @update:model-value="onModeToggle('lastYear', $event)"
              hide-details density="compact"
              :disabled="mode === 'range'"
            />
          </v-col>
        </v-row>

        <v-divider class="my-2" />

        <!-- Date Range -->
        <v-checkbox
          class="mb-2"
          label="Date Range"
          :model-value="mode === 'range'"
          @update:model-value="onToggleRange"
          hide-details density="compact"
        />

        <div v-if="mode === 'range'" class="mb-2">
          <v-btn-toggle v-model="activePart" rounded="lg" class="mb-3" mandatory>
            <v-btn value="start">From</v-btn>
            <v-btn value="end">To</v-btn>
          </v-btn-toggle>

          <!-- مهم: باید Date[] بدهیم -->
          <v-date-picker
            v-model="rangeDates"
            :range="true"
            show-adjacent-months
            color="primary"
          />

          <div class="text-caption mt-2">
            <strong>From:</strong> {{ rangeDates[0] ? toISO(rangeDates[0]) : '—' }} &nbsp; | &nbsp;
            <strong>To:</strong> {{ rangeDates[1] ? toISO(rangeDates[1]) : '—' }}
          </div>
        </div>
      </v-card-text>

      <v-card-actions>
        <v-btn variant="text" @click="reset">Reset</v-btn>
        <v-spacer />
        <v-btn color="primary" class="rounded-xl" block size="large" @click="apply">
          Filter
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'


const emit = defineEmits(['apply']) 
const open = ref(false)
const mode = ref(null)              // 'thisWeek'|'lastWeek'|'thisMonth'|'lastMonth'|'thisYear'|'lastYear'|'range'|null
const activePart = ref('start')     // فقط برای UI

// *** تفاوت اصلی: v-date-picker با range باید Date[] بگیرد ***
const rangeDates = ref([])          // [] | [Date] | [Date, Date]

// preset که عوض شد، بازه را بساز
watch(mode, (m) => {
  if (!m || m === 'range') return
  rangeDates.value = presetToRangeDates(m)   // ← Date[]
})

function onModeToggle(target, checked) {
  if (checked) mode.value = target
  else if (mode.value === target) mode.value = null
}

function onToggleRange(checked) {
  mode.value = checked ? 'range' : null
  if (!checked) rangeDates.value = []
}

function reset () {
  mode.value = null
  rangeDates.value = []
}

function apply () {
  if (mode.value && mode.value !== 'range' && rangeDates.value.length !== 2) {
    rangeDates.value = presetToRangeDates(mode.value)
  }

  const [s, e] = normalizeRange(rangeDates.value)
  const payload = {
    start: s ? toISO(s) : null,
    end:   e ? toISO(e) : null,
    mode:  mode.value,
  }

  emit('apply', payload)              // ⬅️ این خط مهمه
  open.value = false
}

/* ---------- کمک‌های تاریخ (بدون پکیج) ---------- */
const pad = n => String(n).padStart(2, '0')
const toISO = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`

// اگر هفته از یکشنبه می‌خواهی 0 بگذار
const weekStartsOn = 1

function startOfWeek(date) {
  const d = new Date(date)
  const day = d.getDay()
  const diff = (day - weekStartsOn + 7) % 7
  d.setDate(d.getDate() - diff); d.setHours(0,0,0,0)
  return d
}
function endOfWeek(date) {
  const s = startOfWeek(date)
  const e = new Date(s); e.setDate(s.getDate() + 6); e.setHours(0,0,0,0)
  return e
}
function startOfMonth(date) { const d = new Date(date.getFullYear(), date.getMonth(), 1); d.setHours(0,0,0,0); return d }
function endOfMonth(date)   { const d = new Date(date.getFullYear(), date.getMonth()+1, 0); d.setHours(0,0,0,0); return d }
function addMonths(date,n)  { return new Date(date.getFullYear(), date.getMonth()+n, date.getDate()) }
function startOfYear(date)  { const d = new Date(date.getFullYear(), 0, 1);  d.setHours(0,0,0,0); return d }
function endOfYear(date)    { const d = new Date(date.getFullYear(), 11, 31); d.setHours(0,0,0,0); return d }

function presetToRangeDates(key) {
  const today = new Date()
  switch (key) {
    case 'thisWeek':  return [ startOfWeek(today), endOfWeek(today) ]
    case 'lastWeek':  {
      const s = startOfWeek(today); s.setDate(s.getDate() - 7)
      const e = endOfWeek(today);   e.setDate(e.getDate() - 7)
      return [ s, e ]
    }
    case 'thisMonth': return [ startOfMonth(today), endOfMonth(today) ]
    case 'lastMonth': {
      const lm = addMonths(today, -1)
      return [ startOfMonth(lm), endOfMonth(lm) ]
    }
    case 'thisYear':  return [ startOfYear(today), endOfYear(today) ]
    case 'lastYear':  {
      const ly = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate())
      return [ startOfYear(ly), endOfYear(ly) ]
    }
    default:          return []
  }
}

function normalizeRange(arr) {
  if (!arr || arr.length === 0) return [null, null]
  if (arr.length === 1) return [arr[0], arr[0]]
  const [a, b] = arr
  return a <= b ? [a, b] : [b, a]
}
</script>
