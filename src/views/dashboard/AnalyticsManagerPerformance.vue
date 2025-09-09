<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Account Managers Performance</VCardTitle>
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
      <!-- Chart -->
      <div v-if="chartData.categories.length > 0">
        <VueApexCharts
          type="bar"
          height="300"
          :options="chartOptions"
          :series="chartData.series"
        />
      </div>
      
      <!-- No Data State -->
      <div v-else class="text-center py-8">
        <VIcon
          icon="bx-user-circle"
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

      <!-- Manager Stats -->
      <div v-if="managerPerformance.length > 0" class="mt-4">
        <VDivider class="mb-4" />
        <VRow>
          <VCol
            v-for="manager in managerPerformance.slice(0, 3)"
            :key="manager.manager_id"
            cols="12"
            md="4"
          >
            <div class="text-center pa-2">
              <VAvatar
                :color="getManagerColor(manager.manager_id)"
                size="40"
                class="mb-2"
              >
                <VIcon icon="bx-user" />
              </VAvatar>
              <div class="text-body-2 font-weight-medium mb-1">
                {{ manager.manager_name }}
              </div>
              <div class="text-caption text-medium-emphasis mb-2">
                {{ manager.orders_count }} orders
              </div>
              <VChip
                :color="getManagerColor(manager.manager_id)"
                variant="tonal"
                size="small"
              >
                ${{ formatCurrency(manager.revenue) }}
              </VChip>
            </div>
          </VCol>
        </VRow>
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
  managerPerformance,
  getManagerPerformanceData,
  refreshData 
} = useAnalytics()

// Chart data
const chartData = computed(() => getManagerPerformanceData.value)

// Chart options
const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  
  const primaryColor = `rgba(${hexToRgb(String(currentTheme.primary))}, 1)`
  const successColor = `rgba(${hexToRgb(String(currentTheme.success))}, 1)`
  const textColor = `rgba(${hexToRgb(String(currentTheme['on-surface']))}, ${variableTheme['high-emphasis-opacity']})`
  const borderColor = `rgba(${hexToRgb(String(variableTheme['border-color']))}, ${variableTheme['border-opacity']})`
  
  return {
    chart: {
      type: 'bar',
      parentHeightOffset: 0,
      toolbar: { show: false },
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800
      }
    },
    colors: [primaryColor, successColor],
    dataLabels: { enabled: false },
    stroke: {
      width: 0
    },
    legend: {
      show: true,
      position: 'top',
      horizontalAlign: 'left',
      labels: {
        colors: textColor
      }
    },
    grid: {
      show: true,
      borderColor: borderColor,
      strokeDashArray: 0,
      xaxis: { lines: { show: false } },
      yaxis: { lines: { show: true } }
    },
    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: '60%',
        borderRadius: 4,
        dataLabels: {
          position: 'top'
        }
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
    yaxis: [
      {
        title: {
          text: 'Orders Count',
          style: {
            color: textColor,
            fontSize: '12px'
          }
        },
        labels: {
          style: {
            colors: textColor,
            fontSize: '12px'
          }
        }
      },
      {
        opposite: true,
        title: {
          text: 'Revenue ($)',
          style: {
            color: textColor,
            fontSize: '12px'
          }
        },
        labels: {
          style: {
            colors: textColor,
            fontSize: '12px'
          },
          formatter: value => `$${formatCurrency(value)}`
        }
      }
    ],
    tooltip: {
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
      shared: true,
      intersect: false,
      y: [
        {
          formatter: (val) => `${val} orders`
        },
        {
          formatter: (val) => `$${formatCurrency(val)}`
        }
      ]
    }
  }
})

// Helper methods
const formatCurrency = (value) => {
  return parseFloat(value || 0).toLocaleString('en-US')
}

const getManagerColor = (managerId) => {
  const colors = ['primary', 'success', 'warning', 'info', 'error']
  const index = parseInt(managerId) || 0
  return colors[index % colors.length]
}
</script>

<style lang="scss" scoped>
@use "@core/scss/template/libs/apex-chart.scss";
</style>
