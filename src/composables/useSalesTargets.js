import { useAuthStore } from '@/stores/auth'
import { useSalesTargetsStore } from '@/stores/salesTargets'
import { computed } from 'vue'

export function useSalesTargets() {
    const store = useSalesTargetsStore()
    const auth = useAuthStore()

    const isSuperAdmin = computed(() => store.isSuperAdmin)
    const myManagerId = computed(() => store.myManagerId)
    const targets = computed(() => store.targets)
    const brandCategories = computed(() => store.brandCategories)

    const progressFor = (period_type, period_key, manager_id = null, brand = null) => {
        const key = `${period_type}|${period_key}|${manager_id || 'global'}|${brand || 'all'}`
        return store.progress[key] || null
    }

    const fetchInitial = async () => {
        await Promise.all([
            store.fetchBrandCategories(),
            store.fetchTargets({ period_type: 'month', period_key: new Date().toISOString().slice(0, 7) })
        ])
    }

    return {
        isSuperAdmin,
        myManagerId,
        targets,
        brandCategories,
        loading: computed(() => store.loading),
        error: computed(() => store.error),
        fetchTargets: store.fetchTargets,
        fetchProgress: store.fetchProgress,
        createTarget: store.createTarget,
        updateTarget: store.updateTarget,
        deleteTarget: store.deleteTarget,
        fetchBrandCategories: store.fetchBrandCategories,
        progressFor,
        fetchInitial,
    }
}
