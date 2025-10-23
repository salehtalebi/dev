<script setup>
import { useAuthStore } from '@/stores/auth'
import logo from '@images/logo.svg?raw'
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const authStore = useAuthStore()

const form = ref({
  email: 'admin@test.com', // Default for testing
  password: 'admin',       // Default for testing  
  remember: false,
})

const isPasswordVisible = ref(false)
const isLoading = ref(false)
const errorMessage = ref('')

const handleLogin = async () => {
  if (!form.value.email || !form.value.password) {
    errorMessage.value = 'Please enter email and password'
    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    console.log('Attempting login with:', { 
      username: form.value.email, 
      password: '***hidden***' 
    })

    const result = await authStore.login({
      username: form.value.email,
      password: form.value.password
    })

    console.log('Login result:', result)

    if (result && result.success) {
      console.log('Login successful, user roles:', authStore.user?.roles)
      console.log('Is admin?', authStore.isAdmin)
      
      // Check if user has admin role
      if (authStore.isAdmin) {
        console.log('Admin user detected, redirecting to dashboard')
        // Use setTimeout to ensure all reactive updates are complete
        setTimeout(() => {
          router.push('/dashboard')
        }, 100)
      } else {
        console.log('User role not sufficient:', authStore.user?.roles)
        errorMessage.value = 'You do not have permission to access this panel'
        await authStore.logout()
      }
    } else {
      errorMessage.value = result?.message || 'Login error'
      console.error('Login failed:', result)
    }
  } catch (error) {
    console.error('Login error:', error)
    errorMessage.value = error.message || 'Server connection error'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <div class="position-relative my-sm-16">
     

  

      <!-- 👉 Auth Card -->
      <VCard
        class="auth-card"
        max-width="460"
        :class="$vuetify.display.smAndUp ? 'pa-6' : 'pa-0'"
      >
        <VCardItem class="justify-center">
          <RouterLink
            to="/"
            class="app-logo"
          >
            <!-- eslint-disable vue/no-v-html -->
            <div
              class="d-flex"
              v-html="logo"
            
            ></div>
            
          </RouterLink>
        </VCardItem>

        <VCardText>
          <h4 class="text-h4 mb-1">
            Sales Dashboard Login
          </h4>
          <p class="mb-0">
            Please enter your credentials to access the admin panel.
          </p>
        </VCardText>

        <VCardText>
          <VForm @submit.prevent="handleLogin">
            <!-- Error Message -->
            <VAlert
              v-if="errorMessage"
              type="error"
              class="mb-4"
              closable
              @click:close="errorMessage = ''"
            >
              {{ errorMessage }}
            </VAlert>

            <VRow>
              <!-- email -->
              <VCol cols="12">
                <VTextField
                  v-model="form.email"
                  autofocus
                  label="Email or Username"
                  type="email"
                  placeholder="johndoe@email.com"
                  :disabled="isLoading"
                  required
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <VTextField
                  v-model="form.password"
                  label="Password"
                  placeholder="············"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  autocomplete="password"
                  :append-inner-icon="isPasswordVisible ? 'bx-hide' : 'bx-show'"
                  :disabled="isLoading"
                  required
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <!-- remember me checkbox -->
                <div class="d-flex align-center justify-space-between flex-wrap my-6">
                  <VCheckbox
                    v-model="form.remember"
                    label="Remember me"
                    :disabled="isLoading"
                  />

                  
                </div>

                <!-- login button -->
                <VBtn
                  block
                  type="submit"
                  :loading="isLoading"
                  :disabled="!form.email || !form.password"
                >
                  Login
                </VBtn>
              </VCol>

              

              

             
            </VRow>
          </VForm>
        </VCardText>
      </VCard>
    </div>
  </div>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth";
</style>
