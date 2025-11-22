<script setup>
import { useAuthStore } from '@/stores/auth'
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue'
import { computed } from 'vue'

const auth = useAuthStore()
const isSuperAdmin = computed(() => !!auth.user?.is_super_admin)
</script>

<template>
  <!-- Dashboard -->
  <VerticalNavLink
    :item="{
      title: 'Main Dashboard',
      icon: 'bx-bar-chart-alt-2',
      to: '/dashboard',
    }"
  />

  <!-- Sales Management -->
 
  <VerticalNavLink
    :item="{
      title: 'Orders Reports',
      icon: 'nu-bag',
      to: '/orders',
    }"
  />
  
  <VerticalNavLink
    :item="{
      title: 'Customers Reports',
      icon: 'nu-two-user',
      to: '/customers',
    }"
  />

  <!-- Sales Targets (Super Admin Only) -->
  <VerticalNavLink
    v-if="isSuperAdmin"
    :item="{
      title: 'Sales Targets',
      icon: 'bx-target-lock',
      to: '/sales-targets',
    }"
  />

  <!-- Original Menu Items -->


  <!-- <VerticalNavLink
    :item="{
      title: 'Cart & User Behavior',
      icon: 'nu-folder',
      to: '/account-settings',
    }"
  /> -->
  

  <!-- <VerticalNavLink
    :item="{
      title: 'Export Reports',
      icon: 'nu-export',
      to: '/tables',
    }"
  /> -->
  
  <!-- <VerticalNavLink
    :item="{
      title: 'Settings',
      icon: 'bx-detail',
       icon: 'nu-setting',
    }"
  /> -->
</template>
