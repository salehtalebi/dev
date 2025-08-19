<script setup>
import VerticalNavSectionTitle from '@/@layouts/components/VerticalNavSectionTitle.vue'
import VerticalNavGroup from '@layouts/components/VerticalNavGroup.vue'
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue'
</script>

<template>
  <!-- Dashboard -->
  <VerticalNavLink
    :item="{
      title: 'Dashboard',
      icon: 'bx-home-circle',
      to: '/dashboard',
    }"
  />

  <!-- Sales Management -->
 
  <VerticalNavLink
    :item="{
      title: 'سفارشات',
      icon: 'bx-cart',
      to: '/orders',
    }"
  />
  
  <VerticalNavLink
    :item="{
      title: 'مشتریان',
      icon: 'bx-user',
      to: '/customers',
    }"
  />

  <!-- Original Menu Items -->


  <VerticalNavLink
    :item="{
      title: 'Account Settings',
      icon: 'bx-user',
      to: '/account-settings',
    }"
  />
  

  <VerticalNavLink
    :item="{
      title: 'Tables',
      icon: 'bx-table',
      to: '/tables',
    }"
  />
  
  <VerticalNavLink
    :item="{
      title: 'Form Layouts',
      icon: 'bx-detail',
      to: '/form-layouts',
    }"
  />
</template>
