<script setup>
import NavItems from "@/layouts/components/NavItems.vue";
import logo from "@images/logo.svg?raw";
import VerticalNavLayout from "@layouts/components/VerticalNavLayout.vue";
import Footer from "@/layouts/components/Footer.vue";
import NavbarThemeSwitcher from "@/layouts/components/NavbarThemeSwitcher.vue";
import UserProfile from "@/layouts/components/UserProfile.vue";
import { useRoute } from "vue-router";
import { ref, watch , onMounted } from 'vue'


const route = useRoute()
const pageTitle = ref("")

const checkRoute = (p) => {
  const s = p.toLowerCase()

  switch (true) {
    case s.includes('dashboard'):
      pageTitle.value = 'Dashboard'
      break
    case s.includes('orders'):
      pageTitle.value = 'Orders Reports'
      break
    case s.includes('customers'):
      pageTitle.value = 'User Reports'
      break
    default:
      pageTitle.value = ''
  }
}

watch(
  () => route.fullPath,
  (n) => {
    checkRoute(n)
  },
)
onMounted(() => {
   checkRoute(route.fullPath)
})

</script>

<template>
  <VerticalNavLayout>
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <!-- 👉 Vertical nav toggle in overlay mode -->
        <IconBtn
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon icon="bx-menu" />
        </IconBtn>

        <h2 class="nu-page-title">{{ pageTitle }}</h2>

        <VSpacer />

        <IconBtn
          href="https://github.com/themeselection/sneat-vuetify-vuejs-admin-template-free"
          target="_blank"
          rel="noopener noreferrer"
        >
          <VIcon icon="bxl-github" />
        </IconBtn>

        <IconBtn>
          <VIcon icon="bx-bell" />
        </IconBtn>

        <NavbarThemeSwitcher class="me-1" />

        <UserProfile />
      </div>
    </template>

    <template #vertical-nav-header="{ toggleIsOverlayNavActive }">
      <RouterLink to="/" class="app-logo app-title-wrapper">
        <!-- eslint-disable vue/no-v-html -->
        <div class="d-flex" v-html="logo" />
        <!-- eslint-enable -->
      </RouterLink>

      <IconBtn
        class="d-block d-lg-none"
        @click="toggleIsOverlayNavActive(false)"
      >
        <VIcon icon="bx-x" />
      </IconBtn>
    </template>

    <template #vertical-nav-content>
      <NavItems />
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>
  </VerticalNavLayout>
</template>

<style lang="scss" scoped>
.meta-key {
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  block-size: 1.5625rem;
  line-height: 1.3125rem;
  padding-block: 0.125rem;
  padding-inline: 0.25rem;
}

.app-logo {
  display: flex;
  align-items: center;
  column-gap: 0.75rem;

  .app-logo-title {
    font-size: 1.25rem;
    font-weight: 500;
    line-height: 1.75rem;
    text-transform: uppercase;
  }

  .nu-page-title{
    
  }
}
</style>
