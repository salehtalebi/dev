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
      title: 'Orders Reports',
      icon: 'nu-bag',
      to: '/orders',
    }"
  />
  
  <VerticalNavLink
    :item="{
      title: 'User Reports',
      icon: 'nu-two-user',
      to: '/customers',
    }"
  />

  <!-- Original Menu Items -->


  <VerticalNavLink
    :item="{
      title: 'Cart & User Behavior',
      icon: 'nu-folder',
      to: '/account-settings',
    }"
  />
  

  <VerticalNavLink
    :item="{
      title: 'Export Reports',
      icon: 'nu-export',
      to: '/tables',
    }"
  />
  
  <VerticalNavLink
    :item="{
      title: 'Settings',
      icon: 'bx-detail',
       icon: 'nu-setting',
    }"
  />
</template>
