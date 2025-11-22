/**
 * Order Statistics Composable
 * Provides computed properties and helpers for order statistics display
 */
import { useDashboardStore } from '@/stores/dashboard'
import { computed } from 'vue'

export function useOrderStatistics() {
    const dashboardStore = useDashboardStore()

    // State refs
    const orderStatistics = computed(() => dashboardStore.orderStatistics)
    const orderStatisticsFilters = computed(() => dashboardStore.orderStatisticsFilters)
    const loading = computed(() => dashboardStore.loading.orderStatistics)

    // Chart data for donut chart
    const getDonutChartData = computed(() => {
        const brands = orderStatistics.value.brands || []

        if (brands.length === 0) {
            return {
                series: [],
                labels: []
            }
        }

        // Take top 5 brands by orders
        const topBrands = brands.slice(0, 5)

        return {
            series: topBrands.map(brand => brand.orders || 0),
            labels: topBrands.map(brand => brand.brand || 'Unknown')
        }
    })

    // Brands list with formatted data
    const getBrandsList = computed(() => {
        const brands = orderStatistics.value.brands || []
        const hasComparison = !!orderStatistics.value.comparisonSummary

        return brands.map((brand, idx) => ({
            brand: brand.brand,
            brandId: brand.brand_id,
            brandSlug: brand.brand_slug,
            orders: brand.orders || 0,
            revenue: brand.revenue || 0,
            compareOrders: brand.compare_orders || 0,
            compareRevenue: brand.compare_revenue || 0,
            ordersGrowth: brand.orders_growth || 0,
            revenueGrowth: brand.revenue_growth || 0,
            hasComparison,
            // Display properties
            ordersFormatted: (brand.orders || 0).toLocaleString('en-US'),
            revenueFormatted: `$${(brand.revenue || 0).toLocaleString('en-US')}`,
            ordersGrowthFormatted: formatGrowth(brand.orders_growth),
            revenueGrowthFormatted: formatGrowth(brand.revenue_growth),
            avatarColor: getAvatarColor(idx),
        }))
    })

    // Total stats
    const getTotalStats = computed(() => {
        const stats = orderStatistics.value
        const hasComparison = !!stats.comparisonSummary

        return {
            totalOrders: stats.totalOrders || 0,
            totalRevenue: stats.totalRevenue || 0,
            compareTotalOrders: stats.compareTotalOrders || 0,
            compareTotalRevenue: stats.compareTotalRevenue || 0,
            ordersGrowth: stats.comparisonSummary?.total_orders_growth || 0,
            revenueGrowth: stats.comparisonSummary?.total_revenue_growth || 0,
            periodLabel: stats.periodLabel || '',
            comparePeriodLabel: stats.comparePeriodLabel || '',
            hasComparison,
            // Formatted
            totalOrdersFormatted: (stats.totalOrders || 0).toLocaleString('en-US'),
            totalRevenueFormatted: `$${(stats.totalRevenue || 0).toLocaleString('en-US')}`,
            ordersGrowthFormatted: formatGrowth(stats.comparisonSummary?.total_orders_growth),
            revenueGrowthFormatted: formatGrowth(stats.comparisonSummary?.total_revenue_growth),
        }
    })

    // Helper: Format growth percentage
    function formatGrowth(value) {
        if (value === undefined || value === null) return '0.0'
        const num = parseFloat(value)
        return num >= 0 ? `+${num.toFixed(1)}` : num.toFixed(1)
    }

    // Helper: Get avatar color by index
    function getAvatarColor(index) {
        const colors = ['primary', 'success', 'warning', 'info', 'error']
        return colors[index % colors.length]
    }

    // Helper: Get growth color class
    function getGrowthColor(growth) {
        const num = parseFloat(growth || 0)
        return num >= 0 ? 'success' : 'error'
    }

    // Helper: Format period label
    function formatPeriodLabel(filterType, filterValue, startDate, endDate) {
        if (filterType === 'date-range' && startDate && endDate) {
            return `${startDate} - ${endDate}`
        }

        if (filterType === 'month' && filterValue) {
            const [year, month] = filterValue.split('-')
            const date = new Date(year, parseInt(month) - 1, 1)
            return date.toLocaleString('en-US', { month: 'long', year: 'numeric' })
        }

        if (filterType === 'year' && filterValue) {
            return filterValue
        }

        return 'All Time'
    }

    // Fetch data
    async function fetchOrderStatistics() {
        try {
            await dashboardStore.fetchOrderStatistics()
        } catch (error) {
            console.error('Error fetching order statistics:', error)
        }
    }

    // Update filters
    function updateFilters(newFilters) {
        Object.assign(dashboardStore.orderStatisticsFilters, newFilters)
    }

    // Reset filters
    function resetFilters() {
        dashboardStore.orderStatisticsFilters = {
            filterType: 'year',
            filterValue: null,
            startDate: null,
            endDate: null,
            compare: false,
            compareFilterType: null,
            compareFilterValue: null,
            compareStartDate: null,
            compareEndDate: null,
        }
    }

    return {
        // State
        orderStatistics,
        orderStatisticsFilters,
        loading,

        // Computed data
        getDonutChartData,
        getBrandsList,
        getTotalStats,

        // Helpers
        formatGrowth,
        getAvatarColor,
        getGrowthColor,
        formatPeriodLabel,

        // Actions
        fetchOrderStatistics,
        updateFilters,
        resetFilters,
    }
}
