/**
 * Analytics Composable for Dashboard Components
 */
import { useDashboardStore } from '@/stores/dashboard'
import { computed, onMounted, ref } from 'vue'

// Global state to prevent multiple fetches
let isInitialized = false
let isCurrentlyFetching = false

export function useAnalytics() {
    const dashboardStore = useDashboardStore()
    const loading = ref(false)
    const error = ref(null)

    // Computed properties for dashboard data
    const dashboardStats = computed(() => dashboardStore.stats)
    const monthlyRevenue = computed(() => dashboardStore.monthlyRevenue)
    const topProducts = computed(() => dashboardStore.topProducts)
    const salesComparison = computed(() => dashboardStore.salesComparison)
    const managerPerformance = computed(() => dashboardStore.managerPerformance)
    const recentOrders = computed(() => dashboardStore.recentOrders)

    // Chart data formatters
    const getRevenueChartData = computed(() => {
        if (!monthlyRevenue.value || monthlyRevenue.value.length === 0) {
            return {
                categories: [],
                series: [{
                    name: 'Monthly Revenue',
                    data: []
                }]
            }
        }

        const categories = monthlyRevenue.value.map(item => {
            const date = new Date(item.month + '-01')
            return date.toLocaleDateString('fa-IR', {
                year: 'numeric',
                month: 'short'
            })
        })

        const data = monthlyRevenue.value.map(item =>
            parseFloat(item.revenue || 0)
        )

        return {
            categories,
            series: [{
                name: 'Monthly Revenue',
                data
            }]
        }
    })

    const getTopProductsChartData = computed(() => {
        if (!topProducts.value || topProducts.value.length === 0) {
            return {
                labels: [],
                series: []
            }
        }

        const labels = topProducts.value.map(product =>
            product.product_name || `Product ${product.product_id}`
        )
        const series = topProducts.value.map(product =>
            parseInt(product.total_sold || 0)
        )

        return { labels, series }
    })

    const getSalesComparisonData = computed(() => {
        const current = parseFloat(salesComparison.value.currentMonth || 0)
        const previous = parseFloat(salesComparison.value.previousMonth || 0)
        const growth = parseFloat(salesComparison.value.growth || 0)

        return {
            current,
            previous,
            growth,
            series: [current, previous]
        }
    })

    const getManagerPerformanceData = computed(() => {
        if (!managerPerformance.value || managerPerformance.value.length === 0) {
            return {
                categories: [],
                series: [{
                    name: 'Orders Count',
                    data: []
                }, {
                    name: 'Revenue',
                    data: []
                }]
            }
        }

        const categories = managerPerformance.value.map(manager =>
            manager.manager_name
        )
        const ordersData = managerPerformance.value.map(manager =>
            parseInt(manager.orders_count || 0)
        )
        const revenueData = managerPerformance.value.map(manager =>
            parseFloat(manager.revenue || 0)
        )

        return {
            categories,
            series: [{
                name: 'Orders Count',
                data: ordersData
            }, {
                name: 'Revenue',
                data: revenueData
            }]
        }
    })

    // Methods
    const fetchDashboardData = async (force = false) => {
        // If currently fetching, wait
        if (isCurrentlyFetching && !force) {
            return
        }

        // If already initialized and not forced, don't fetch
        if (isInitialized && !force) {
            return
        }

        isCurrentlyFetching = true
        loading.value = true
        error.value = null

        try {
            console.log('Fetching dashboard data...', { force, isInitialized })
            await dashboardStore.fetchAllDashboardData(force)
            console.log('Dashboard data fetched successfully')
            isInitialized = true
        } catch (err) {
            console.error('Error fetching dashboard data:', err)
            error.value = err.message || 'Error fetching dashboard data'
        } finally {
            loading.value = false
            isCurrentlyFetching = false
        }
    }

    const refreshData = () => {
        isInitialized = false // Reset for force fetch
        return fetchDashboardData(true)
    }

    // Auto fetch on mount only if not initialized
    onMounted(() => {
        if (!isInitialized && !isCurrentlyFetching) {
            fetchDashboardData()
        }
    })

    return {
        // State
        loading,
        error,

        // Dashboard stats
        dashboardStats,
        monthlyRevenue,
        topProducts,
        salesComparison,
        managerPerformance,
        recentOrders,

        // Chart data
        getRevenueChartData,
        getTopProductsChartData,
        getSalesComparisonData,
        getManagerPerformanceData,

        // Methods
        fetchDashboardData,
        refreshData
    }
}
