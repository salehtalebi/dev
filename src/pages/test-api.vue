<template>
  <div class="pa-6">
    <h2>API Connection Test</h2>
    
    <VCard class="mb-4">
      <VCardTitle>Environment Variables</VCardTitle>
      <VCardText>
        <pre>{{ envVars }}</pre>
      </VCardText>
    </VCard>

    <VCard class="mb-4">
      <VCardTitle>API Configuration</VCardTitle>
      <VCardText>
        <pre>{{ apiConfig }}</pre>
      </VCardText>
    </VCard>

    <VCard class="mb-4">
      <VCardTitle>Login Test</VCardTitle>
      <VCardText>
        <VTextField
          v-model="testCredentials.username"
          label="Username"
          class="mb-2"
        />
        <VTextField
          v-model="testCredentials.password"
          label="Password"
          type="password"
          class="mb-4"
        />
        <VBtn 
          @click="testLogin"
          :loading="testing"
          color="primary"
          class="me-2"
        >
          Test Login
        </VBtn>
        <VBtn 
          @click="testEndpoint"
          :loading="testing"
          color="secondary"
        >
          Test Endpoint
        </VBtn>
      </VCardText>
    </VCard>

    <VCard v-if="testResult">
      <VCardTitle>Test Result</VCardTitle>
      <VCardText>
        <pre>{{ testResult }}</pre>
      </VCardText>
    </VCard>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { API_CONFIG } from '@/config/api'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const testing = ref(false)
const testResult = ref('')

const testCredentials = ref({
  username: 'admin',
  password: 'admin'
})

const envVars = computed(() => ({
  VITE_API_BASE_URL: import.meta.env.VITE_API_BASE_URL,
  VITE_WP_API_ENDPOINT: import.meta.env.VITE_WP_API_ENDPOINT,
  VITE_WC_API_ENDPOINT: import.meta.env.VITE_WC_API_ENDPOINT,
  VITE_CUSTOM_API_ENDPOINT: import.meta.env.VITE_CUSTOM_API_ENDPOINT,
  VITE_JWT_ENDPOINT: import.meta.env.VITE_JWT_ENDPOINT,
}))

const apiConfig = computed(() => ({
  BASE_URL: API_CONFIG.BASE_URL,
  WP_API_URL: API_CONFIG.WP_API_URL,
  WC_API_URL: API_CONFIG.WC_API_URL,
  CUSTOM_API_URL: API_CONFIG.CUSTOM_API_URL,
  JWT_URL: API_CONFIG.JWT_URL,
}))

const testLogin = async () => {
  testing.value = true
  testResult.value = ''
  
  try {
    console.log('Testing login with URL:', API_CONFIG.JWT_URL)
    const result = await authStore.login(testCredentials.value)
    testResult.value = JSON.stringify(result, null, 2)
  } catch (error) {
    testResult.value = `Error: ${error.message}\nStack: ${error.stack}`
  } finally {
    testing.value = false
  }
}

const testEndpoint = async () => {
  testing.value = true
  testResult.value = ''
  
  try {
    const response = await fetch(API_CONFIG.JWT_URL + '/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        username: 'test',
        password: 'test'
      })
    })
    
    const responseText = await response.text()
    testResult.value = `Status: ${response.status}\nResponse: ${responseText}`
  } catch (error) {
    testResult.value = `Network Error: ${error.message}`
  } finally {
    testing.value = false
  }
}
</script>
