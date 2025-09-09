/**
 * Export Composable - Updated to use API service
 */
import { useAPI } from '@/composables/useAPI'
import { ref } from 'vue'

export function useExport() {
    const isExporting = ref(false)
    const error = ref(null)
    const { exportOrders: apiExportOrders, exportCustomers: apiExportCustomers } = useAPI()

    const clearError = () => {
        error.value = null
    }

    // Try to decode and download if API returns base64 content/filename
    const maybeDownloadEncoded = (res) => {
        if (res && (res.content || (res.data && res.data.content))) {
            const content = res.content || res.data.content
            const filename = res.filename || res.data.filename || `export_${new Date().toISOString().split('T')[0]}.csv`
            const mimeType = res.mime_type || res.data.mime_type || 'text/csv'

            try {
                const byteCharacters = atob(content)
                const byteNumbers = new Array(byteCharacters.length)
                for (let i = 0; i < byteCharacters.length; i++) {
                    byteNumbers[i] = byteCharacters.charCodeAt(i)
                }
                const byteArray = new Uint8Array(byteNumbers)
                const blob = new Blob([byteArray], { type: mimeType })
                downloadFile(blob, filename)
                return true
            } catch (e) {
                console.error('Failed to decode base64 export content', e)
                return false
            }
        }
        return false
    }

    const convertToCSV = (data, headers) => {
        if (!Array.isArray(data) || data.length === 0) return ''

        const csvHeader = headers.map(h => h.title).join(',')
        const csvRows = data.map(row => headers.map(header => {
            const value = row[header.key] ?? ''
            if (typeof value === 'string' && (value.includes(',') || value.includes('"'))) {
                return `"${value.replace(/"/g, '""')}"`
            }
            return value
        }).join(','))

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
            const response = await apiExportOrders(filters)
            if (maybeDownloadEncoded(response)) return { success: true }

            if (response.data) {
                const csv = convertToCSV(response.data, [
                    { key: 'id', title: 'Order ID' },
                    { key: 'customer_name', title: 'Customer Name' },
                    { key: 'customer_email', title: 'Customer Email' },
                    { key: 'status', title: 'Status' },
                    { key: 'total', title: 'Total Amount' },
                    { key: 'date_created', title: 'Creation Date' },
                    { key: 'account_manager', title: 'Account Manager' }
                ])
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
                const filename = `orders_${new Date().toISOString().split('T')[0]}.csv`
                downloadFile(blob, filename)
            }
            return { success: true }
        } catch (err) {
            console.error('Export orders error:', err)
            error.value = err.message || 'Error exporting orders'
            return { success: false, error: error.value }
        } finally {
            isExporting.value = false
        }
    }

    const exportCustomers = async (filters = {}) => {
        isExporting.value = true
        error.value = null

        try {
            const response = await apiExportCustomers(filters)
            if (maybeDownloadEncoded(response)) return { success: true }

            if (response.data) {
                const csv = convertToCSV(response.data, [
                    { key: 'id', title: 'Customer ID' },
                    { key: 'first_name', title: 'First Name' },
                    { key: 'last_name', title: 'Last Name' },
                    { key: 'email', title: 'Email' },
                    { key: 'phone', title: 'Phone' },
                    { key: 'total_orders', title: 'Total Orders' },
                    { key: 'total_spent', title: 'Total Spent' },
                    { key: 'date_registered', title: 'Registration Date' },
                    { key: 'account_manager', title: 'Account Manager' }
                ])
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
                const filename = `customers_${new Date().toISOString().split('T')[0]}.csv`
                downloadFile(blob, filename)
            }
            return { success: true }
        } catch (err) {
            console.error('Export customers error:', err)
            error.value = err.message || 'Error exporting customers'
            return { success: false, error: error.value }
        } finally {
            isExporting.value = false
        }
    }

    return { isExporting, error, clearError, exportOrders, exportCustomers }
}
