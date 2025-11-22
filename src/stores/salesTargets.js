import { API_CONFIG } from '@/config/api'
import { useAuthStore } from '@/stores/auth'
import { defineStore } from 'pinia'

// Endpoints base (reuse CUSTOM_API_URL like other modules)
const BASE = API_CONFIG.CUSTOM_API_URL

const buildQueryString = (params = {}) => {
    const query = new URLSearchParams()
    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '' || value === 'null' || value === 'undefined') {
            return
        }
        query.append(key, value)
    })
    return query.toString()
}

export const useSalesTargetsStore = defineStore('salesTargets', {
    state: () => ({
        targets: [],
        progress: {}, // keyed by period_type + period_key + manager_id + brand
        loading: {
            list: false,
            progress: false,
            mutate: false,
        },
        error: null,
        formDefaults: {
            period_type: 'month',
            period_key: null,
            manager_id: null,
            brand: null,
            target_amount: 0,
            currency: 'USD',
            notes: '',
        },
        brandCategories: [],
    }),
    getters: {
        isSuperAdmin: () => {
            const auth = useAuthStore()
            // treat administrator/shop_manager as super (adjust if backend exposes is_super_admin separately)
            return auth.isAdmin
        },
        myManagerId: () => {
            const auth = useAuthStore()
            // account_manager_id may be in user meta; fallback null
            return auth.user?.account_manager_id || null
        },
    },
    actions: {
        buildHeaders() {
            const auth = useAuthStore()
            const headers = { 'Content-Type': 'application/json' }
            if (auth.token) headers['Authorization'] = `Bearer ${auth.token}`
            return headers
        },
        async fetchTargets(params = {}) {
            this.loading.list = true
            this.error = null
            try {
                const qs = buildQueryString(params)
                const res = await fetch(`${BASE}/targets${qs ? '?' + qs : ''}`, {
                    headers: this.buildHeaders(),
                })
                const data = await res.json()
                if (!res.ok) throw new Error(data.message || 'Failed to fetch targets')
                this.targets = Array.isArray(data.data) ? data.data : []
            } catch (e) {
                this.error = e.message
            } finally {
                this.loading.list = false
            }
        },
        async fetchProgress(params) {
            this.loading.progress = true
            this.error = null
            try {
                const qs = buildQueryString(params)
                const res = await fetch(`${BASE}/targets/progress${qs ? `?${qs}` : ''}`, {
                    headers: this.buildHeaders(),
                })
                const data = await res.json()
                if (!res.ok) throw new Error(data.message || 'Failed to fetch progress')
                const key = `${data.period_type}|${data.period_key}|${data.manager_id || 'global'}|${data.brand || 'all'}`
                this.progress[key] = data
                return data
            } catch (e) {
                this.error = e.message
                return null
            } finally {
                this.loading.progress = false
            }
        },
        async createTarget(payload) {
            this.loading.mutate = true
            this.error = null
            try {
                const res = await fetch(`${BASE}/targets`, {
                    method: 'POST',
                    headers: this.buildHeaders(),
                    body: JSON.stringify(payload),
                })
                const data = await res.json()
                if (!res.ok) throw new Error(data.message || 'Failed to create target')
                await this.fetchTargets({ period_type: payload.period_type, period_key: payload.period_key })
                return data
            } catch (e) {
                this.error = e.message
                return { success: false, message: e.message }
            } finally {
                this.loading.mutate = false
            }
        },
        async updateTarget(id, payload) {
            this.loading.mutate = true
            this.error = null
            try {
                const res = await fetch(`${BASE}/targets/${id}`, {
                    method: 'PUT',
                    headers: this.buildHeaders(),
                    body: JSON.stringify(payload),
                })
                const data = await res.json()
                if (!res.ok) throw new Error(data.message || 'Failed to update target')
                return data
            } catch (e) {
                this.error = e.message
                return { success: false, message: e.message }
            } finally {
                this.loading.mutate = false
            }
        },
        async deleteTarget(id) {
            this.loading.mutate = true
            this.error = null
            try {
                const res = await fetch(`${BASE}/targets/${id}`, {
                    method: 'DELETE',
                    headers: this.buildHeaders(),
                })
                const data = await res.json()
                if (!res.ok) throw new Error(data.message || 'Failed to delete target')
                this.targets = this.targets.filter(t => t.id !== id)
                return data
            } catch (e) {
                this.error = e.message
                return { success: false, message: e.message }
            } finally {
                this.loading.mutate = false
            }
        },
        async fetchBrandCategories() {
            // Use WordPress REST API base to avoid hitting the Vite dev origin
            const url = `${API_CONFIG.WP_API_URL}/product_cat?per_page=100`
            try {
                const res = await fetch(url)
                if (!res.ok) return
                const cats = await res.json()
                this.brandCategories = cats.map(c => ({ id: c.id, name: c.name, slug: c.slug }))
            } catch (e) {
                // silent failure
            }
        },
    },
})
