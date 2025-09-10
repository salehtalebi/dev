<script setup>
import Footer from "@/layouts/components/Footer.vue";
import NavbarThemeSwitcher from "@/layouts/components/NavbarThemeSwitcher.vue";
import NavItems from "@/layouts/components/NavItems.vue";
import UserProfile from "@/layouts/components/UserProfile.vue";
import logo from "@images/logo.svg?raw";
import VerticalNavLayout from "@layouts/components/VerticalNavLayout.vue";
import { ref, watch } from 'vue';
import { useRoute } from "vue-router";


const route = useRoute()
const pageTitle = ref("")

const checkRoute = (p) => {
  const s = p.toLowerCase()

  switch (true) {
    case s.includes('dashboard-new'):
      pageTitle.value = 'Dashboard New'
      break
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
    console.log('fullPath:', n)
    checkRoute(n)
  },
)

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

        <h1>{{ pageTitle }}</h1>

        <VSpacer />

        

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
}
</style>
