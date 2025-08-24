<script setup>
import { useAuthStore } from '@/stores/auth'
import { onMounted } from 'vue'

const authStore = useAuthStore()

// Initialize auth store when app starts
onMounted(async () => {
  await authStore.initialize()
})
</script>

<template>
  <VApp>
    <RouterView />
  </VApp>
</template>
