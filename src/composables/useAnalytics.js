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
    const monthlyRevenueComparison = computed(() => dashboardStore.monthlyRevenueComparison)
    const revenueComparisonSummary = computed(() => dashboardStore.revenueComparisonSummary)
    const topProducts = computed(() => dashboardStore.topProducts)
    const salesComparison = computed(() => dashboardStore.salesComparison)
    const managerPerformance = computed(() => dashboardStore.managerPerformance)
    const managerPerformanceComparison = computed(() => dashboardStore.managerPerformanceComparison)
    const managerPeriodLabel = computed(() => dashboardStore.managerPeriodLabel)
    const managerComparePeriodLabel = computed(() => dashboardStore.managerComparePeriodLabel)
    const recentOrders = computed(() => dashboardStore.recentOrders)
    const revenueFilters = computed(() => dashboardStore.revenueFilters)
    const productsFilters = computed(() => dashboardStore.productsFilters)
    const managerPerformanceFilters = computed(() => dashboardStore.managerPerformanceFilters)
    // Derived convenience values (optional export for components needing direct access)
    const totalOrders = computed(() => dashboardStore.stats.totalOrders || 0)

    // Chart data formatters
    const getRevenueChartData = computed(() => {
        const hasComparison = monthlyRevenueComparison.value && monthlyRevenueComparison.value.length > 0

        if (!monthlyRevenue.value || monthlyRevenue.value.length === 0) {
            return {
                categories: [],
                series: [{
                    name: 'Revenue',
                    data: []
                }]
            }
        }

        // Extract categories from primary data
        const categories = monthlyRevenue.value.map(item => item.label || item.period)

        // Primary series
        const primaryData = monthlyRevenue.value.map(item =>
            parseFloat(item.revenue || 0)
        )

        // Get dynamic labels from store or fallback to defaults
        const currentLabel = dashboardStore.revenuePeriodLabel || (hasComparison ? 'Current Period' : 'Revenue')
        const compareLabel = dashboardStore.revenueComparePeriodLabel || 'Comparison Period'

        const series = [{
            name: currentLabel,
            data: primaryData
        }]

        // Add comparison series if available
        if (hasComparison) {
            const comparisonData = monthlyRevenueComparison.value.map(item =>
                parseFloat(item.revenue || 0)
            )

            series.push({
                name: compareLabel,
                data: comparisonData
            })
        }

        return {
            categories,
            series
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
            product.product_name || product.name || `Product ${product.product_id}`
        )
        const series = topProducts.value.map(product =>
            parseInt(product.total_sold || product.total_quantity || 0)
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
        const hasComparison = managerPerformanceComparison.value && managerPerformanceComparison.value.length > 0

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

        // Primary series - current period
        const ordersData = managerPerformance.value.map(manager =>
            parseInt(manager.orders_count || 0)
        )
        const revenueData = managerPerformance.value.map(manager =>
            parseFloat(manager.revenue || 0)
        )

        // Get dynamic labels from store or fallback to defaults
        const currentLabel = managerPeriodLabel.value || (hasComparison ? 'Current Period' : '')
        const compareLabel = managerComparePeriodLabel.value || 'Comparison Period'

        const series = [
            {
                name: hasComparison ? `Orders (${currentLabel})` : 'Orders Count',
                data: ordersData
            },
            {
                name: hasComparison ? `Revenue (${currentLabel})` : 'Revenue',
                data: revenueData
            }
        ]

        // Add comparison series if available
        if (hasComparison) {
            const compareOrdersData = managerPerformance.value.map(manager =>
                parseInt(manager.compare_orders || 0)
            )
            const compareRevenueData = managerPerformance.value.map(manager =>
                parseFloat(manager.compare_revenue || 0)
            )

            series.push({
                name: `Orders (${compareLabel})`,
                data: compareOrdersData
            })
            series.push({
                name: `Revenue (${compareLabel})`,
                data: compareRevenueData
            })
        }

        return {
            categories,
            series
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

    const refreshRevenueData = async () => {
        loading.value = true
        try {
            await dashboardStore.fetchMonthlyRevenue(true)
        } finally {
            loading.value = false
        }
    }

    const updateRevenueFilters = (filters) => {
        dashboardStore.setRevenueFilters(filters)
        return refreshRevenueData()
    }

    const refreshProductsData = async () => {
        loading.value = true
        try {
            await dashboardStore.fetchTopProducts(true)
        } finally {
            loading.value = false
        }
    }

    const updateProductsFilters = (filters) => {
        dashboardStore.setProductsFilters(filters)
        return refreshProductsData()
    }

    const refreshManagerPerformance = async () => {
        loading.value = true
        try {
            await dashboardStore.fetchManagerPerformance(true)
        } finally {
            loading.value = false
        }
    }

    const updateManagerPerformanceFilters = (filters) => {
        dashboardStore.setManagerPerformanceFilters(filters)
        return refreshManagerPerformance()
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
        monthlyRevenueComparison,
        revenueComparisonSummary,
        topProducts,
        salesComparison,
        managerPerformance,
        managerPerformanceComparison,
        managerPeriodLabel,
        managerComparePeriodLabel,
        recentOrders,
        revenueFilters,
        productsFilters,
        managerPerformanceFilters,

        // Chart data
        getRevenueChartData,
        getTopProductsChartData,
        getSalesComparisonData,
        getManagerPerformanceData,
        totalOrders,

        // Methods
        fetchDashboardData,
        refreshData,
        refreshRevenueData,
        updateRevenueFilters,
        refreshProductsData,
        updateProductsFilters,
        refreshManagerPerformance,
        updateManagerPerformanceFilters
    }
}
