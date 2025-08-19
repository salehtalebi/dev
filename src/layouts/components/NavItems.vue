<script setup>
import VerticalNavSectionTitle from '@/@layouts/components/VerticalNavSectionTitle.vue'
import VerticalNavGroup from '@layouts/components/VerticalNavGroup.vue'
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue'
</script>

<template>
  <!-- Dashboard -->
  <VerticalNavLink
    :item="{
      title: 'Main Dashboard',
      icon: 'nu-dashboard',
      to: '/dashboard',
    }"
  />

  <!-- Sales Management -->
 
  <VerticalNavLink
    :item="{
      title: 'سفارشات',
      icon: 'nu-bag',
      to: '/orders',
    }"
  />
  
  <VerticalNavLink
    :item="{
      title: 'مشتریان',
      icon: 'nu-two-user',
      to: '/customers',
    }"
  />

  <!-- Original Menu Items -->


  <VerticalNavLink
    :item="{
      title: 'Account Settings',
      icon: 'nu-folder',
      to: '/account-settings',
    }"
  />
  

  <VerticalNavLink
    :item="{
      title: 'Tables',
      icon: 'nu-export',
      to: '/tables',
    }"
  />
  
  <VerticalNavLink
    :item="{
      title: 'Form Layouts',
      icon: 'bx-detail',
       icon: 'nu-setting',
    }"
  />
</template>
