/**
 * Export Composable - Updated to use API service
 */
import salesDashboardAPI from '@/services/api'
import { ref } from 'vue'

export function useExport() {
    const isExporting = ref(false)
    const error = ref(null)

    const clearError = () => {
        error.value = null
    }

    const convertToCSV = (data, headers) => {
        if (!Array.isArray(data) || data.length === 0) {
            return ''
        }

        // Create CSV header
        const csvHeader = headers.map(h => h.title).join(',')

        // Create CSV rows
        const csvRows = data.map(row => {
            return headers.map(header => {
                const value = row[header.key] || ''
                // Escape double quotes and wrap in quotes if contains comma or quotes
                if (typeof value === 'string' && (value.includes(',') || value.includes('"'))) {
                    return `"${value.replace(/"/g, '""')}"`
                }
                return value
            }).join(',')
        })

        return [csvHeader, ...csvRows].join('\n')
    }

    const downloadFile = (blob, filename) => {
        const link = document.createElement('a')
        const url = URL.createObjectURL(blob)
        link.href = url
        link.download = filename
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        URL.revokeObjectURL(url)
    }

    const exportOrders = async (filters = {}) => {
        isExporting.value = true
        error.value = null

        try {
            const response = await salesDashboardAPI.exportOrders(filters)

            if (response.data) {
                // Convert data to CSV
                const csv = convertToCSV(response.data, [
                    { key: 'id', title: 'شماره سفارش' },
                    { key: 'customer_name', title: 'نام مشتری' },
                    { key: 'customer_email', title: 'ایمیل مشتری' },
                    { key: 'status', title: 'وضعیت' },
                    { key: 'total', title: 'مبلغ کل' },
                    { key: 'date_created', title: 'تاریخ ایجاد' },
                    { key: 'account_manager', title: 'اکانت منیجر' }
                ])

                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
                const filename = `orders_${new Date().toISOString().split('T')[0]}.csv`
                downloadFile(blob, filename)
            }

            return { success: true }
        } catch (err) {
            console.error('Export orders error:', err)
            error.value = err.message || 'خطا در خروجی گیری سفارشات'
            return { success: false, error: error.value }
        } finally {
            isExporting.value = false
        }
    }

    const exportCustomers = async (filters = {}) => {
        isExporting.value = true
        error.value = null

        try {
            const response = await salesDashboardAPI.exportCustomers(filters)

            if (response.data) {
                // Convert data to CSV
                const csv = convertToCSV(response.data, [
                    { key: 'id', title: 'شناسه مشتری' },
                    { key: 'first_name', title: 'نام' },
                    { key: 'last_name', title: 'نام خانوادگی' },
                    { key: 'email', title: 'ایمیل' },
                    { key: 'phone', title: 'تلفن' },
                    { key: 'total_orders', title: 'تعداد سفارشات' },
                    { key: 'total_spent', title: 'مجموع خرید' },
                    { key: 'date_registered', title: 'تاریخ عضویت' },
                    { key: 'account_manager', title: 'اکانت منیجر' }
                ])

                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
                const filename = `customers_${new Date().toISOString().split('T')[0]}.csv`
                downloadFile(blob, filename)
            }

            return { success: true }
        } catch (err) {
            console.error('Export customers error:', err)
            error.value = err.message || 'خطا در خروجی گیری مشتریان'
            return { success: false, error: error.value }
        } finally {
            isExporting.value = false
        }
    }

    return {
        isExporting,
        error,
        clearError,
        exportOrders,
        exportCustomers
    }
}
