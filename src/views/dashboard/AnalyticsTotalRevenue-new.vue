<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Monthly Revenue</VCardTitle>
      <template #append>
        <VBtn
          icon
          size="x-small"
          color="default"
          variant="text"
          @click="refreshData"
          :loading="loading"
        >
          <VIcon
            size="20"
            icon="bx-refresh"
          />
        </VBtn>
      </template>
    </VCardItem>

    <VCardText>
      <div class="d-flex align-center mb-4">
        <h2 class="text-h2 text-primary me-4">
          ${{ formatCurrency(currentMonthRevenue) }}
        </h2>
        <VChip
          :color="revenueGrowth >= 0 ? 'success' : 'error'"
          variant="tonal"
          size="small"
        >
          <VIcon
            :icon="revenueGrowth >= 0 ? 'bx-trending-up' : 'bx-trending-down'"
            start
          />
          {{ Math.abs(revenueGrowth).toFixed(1) }}%
        </VChip>
      </div>

      <!-- Chart -->
      <VueApexCharts
        v-if="chartData.series[0].data.length > 0"
        type="area"
        height="270"
        :options="chartOptions"
        :series="chartData.series"
      />
      
      <!-- No Data State -->
      <div v-else class="text-center py-8">
        <VIcon
          icon="bx-bar-chart"
          size="48"
          class="text-disabled mb-4"
        />
        <p class="text-medium-emphasis">No data available for display</p>
        <VBtn
          color="primary"
          variant="outlined"
          size="small"
          @click="refreshData"
          :loading="loading"
        >
          Retry
        </VBtn>
      </div>
    </VCardText>
  </VCard>
</template>

<script setup>
import { useAnalytics } from '@/composables/useAnalytics'
import { hexToRgb } from '@core/utils/colorConverter'
import { computed } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'

const vuetifyTheme = useTheme()
const { 
  loading, 
  getRevenueChartData, 
  salesComparison,
  refreshData 
} = useAnalytics()

// Chart data
const chartData = computed(() => getRevenueChartData.value)

// Current month revenue and growth
const currentMonthRevenue = computed(() => 
  parseFloat(salesComparison.value.current || 0)
)

const revenueGrowth = computed(() => 
  parseFloat(salesComparison.value.growth || 0)
)

// Chart options
const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  
  const primaryColor = `rgba(${hexToRgb(String(currentTheme.primary))}, 1)`
  const textColor = `rgba(${hexToRgb(String(currentTheme['on-surface']))}, ${variableTheme['high-emphasis-opacity']})`
  const borderColor = `rgba(${hexToRgb(String(variableTheme['border-color']))}, ${variableTheme['border-opacity']})`
  
  return {
    chart: {
      parentHeightOffset: 0,
      toolbar: { show: false },
      sparkline: { enabled: false },
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800
      }
    },
    colors: [primaryColor],
    fill: {
      type: 'gradient',
      gradient: {
        shade: 'light',
        shadeIntensity: 0.8,
        opacityFrom: 0.8,
        opacityTo: 0.25,
        stops: [0, 95, 100]
      }
    },
    dataLabels: { enabled: false },
    stroke: {
      width: 3,
      curve: 'smooth'
    },
    grid: {
      show: true,
      borderColor: borderColor,
      strokeDashArray: 0,
      xaxis: { lines: { show: false } },
      yaxis: { lines: { show: true } },
      padding: {
        top: 0,
        right: 10,
        bottom: 0,
        left: 10
      }
    },
    xaxis: {
      categories: chartData.value.categories,
      labels: {
        style: {
          colors: textColor,
          fontSize: '12px'
        }
      },
      axisBorder: { show: false },
      axisTicks: { show: false }
    },
    yaxis: {
      labels: {
        style: {
          colors: textColor,
          fontSize: '12px'
        },
        formatter: value => `$${formatCurrency(value)}`
      }
    },
    tooltip: {
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
      y: {
        formatter: value => `$${formatCurrency(value)}`
      }
    },
    markers: {
      size: 0,
      strokeColors: primaryColor,
      strokeWidth: 3,
      strokeOpacity: 1,
      fillOpacity: 1,
      hover: { size: 8 }
    }
  }
})

// Helper methods
const formatCurrency = (value) => {
  return parseFloat(value || 0).toLocaleString('en-US')
}
</script>

<style lang="scss" scoped>
@use "@core/scss/template/libs/apex-chart.scss";
</style>
