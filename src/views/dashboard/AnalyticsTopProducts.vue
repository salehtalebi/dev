<template>
  <VCard>
    <VCardItem>
      <VCardTitle>محصولات پرفروش</VCardTitle>
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
      <div v-if="chartData.labels.length > 0">
        <VueApexCharts
          type="donut"
          height="350"
          :options="chartOptions"
          :series="chartData.series"
        />
      </div>
      
      <!-- No Data State -->
      <div v-else class="text-center py-8">
        <VIcon
          icon="bx-pie-chart"
          size="48"
          class="text-disabled mb-4"
        />
        <p class="text-medium-emphasis">داده‌ای برای نمایش وجود ندارد</p>
        <VBtn
          color="primary"
          variant="outlined"
          size="small"
          @click="refreshData"
          :loading="loading"
        >
          تلاش مجدد
        </VBtn>
      </div>

      <!-- Product List -->
      <div v-if="topProducts.length > 0" class="mt-4">
        <VDivider class="mb-4" />
        <div
          v-for="(product, index) in topProducts.slice(0, 5)"
          :key="product.product_id"
          class="d-flex align-center justify-space-between mb-3"
        >
          <div class="d-flex align-center">
            <VAvatar
              :color="getProductColor(index)"
              size="12"
              class="me-3"
            />
            <div>
              <div class="text-body-2 font-weight-medium">
                {{ product.product_name || `محصول ${product.product_id}` }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ product.total_sold }} عدد فروخته شده
              </div>
            </div>
          </div>
          <div class="text-end">
            <div class="text-body-2 font-weight-medium">
              ${{ formatCurrency(product.total_revenue) }}
            </div>
          </div>
        </div>
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
  topProducts,
  getTopProductsChartData,
  refreshData 
} = useAnalytics()

// Chart data
const chartData = computed(() => getTopProductsChartData.value)

// Chart options
const chartOptions = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  
  const colors = [
    `rgba(${hexToRgb(String(currentTheme.primary))}, 1)`,
    `rgba(${hexToRgb(String(currentTheme.success))}, 1)`,
    `rgba(${hexToRgb(String(currentTheme.warning))}, 1)`,
    `rgba(${hexToRgb(String(currentTheme.info))}, 1)`,
    `rgba(${hexToRgb(String(currentTheme.error))}, 1)`
  ]
  
  const textColor = `rgba(${hexToRgb(String(currentTheme['on-surface']))}, ${variableTheme['high-emphasis-opacity']})`
  
  return {
    chart: {
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800
      }
    },
    colors: colors,
    labels: chartData.value.labels,
    dataLabels: {
      enabled: true,
      style: {
        colors: [currentTheme.surface]
      },
      formatter: (val) => `${val.toFixed(1)}%`
    },
    legend: {
      show: false
    },
    stroke: {
      width: 0
    },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            name: {
              fontSize: '16px',
              fontWeight: 600,
              color: textColor
            },
            value: {
              fontSize: '24px',
              fontWeight: 600,
              color: textColor,
              formatter: (val) => `${val} عدد`
            },
            total: {
              show: true,
              fontSize: '14px',
              label: 'مجموع فروش',
              color: textColor,
              formatter: () => {
                const total = chartData.value.series.reduce((sum, val) => sum + val, 0)
                return `${total} عدد`
              }
            }
          }
        }
      }
    },
    tooltip: {
      theme: vuetifyTheme.current.value.dark ? 'dark' : 'light',
      y: {
        formatter: (val) => `${val} عدد فروخته شده`
      }
    },
    responsive: [{
      breakpoint: 480,
      options: {
        chart: {
          width: 300,
          height: 300
        }
      }
    }]
  }
})

// Helper methods
const formatCurrency = (value) => {
  return parseFloat(value || 0).toLocaleString('en-US')
}

const getProductColor = (index) => {
  const colors = ['primary', 'success', 'warning', 'info', 'error']
  return colors[index % colors.length]
}
</script>

<style lang="scss" scoped>
@use "@core/scss/template/libs/apex-chart.scss";
</style>
