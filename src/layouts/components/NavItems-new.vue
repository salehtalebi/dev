<script setup>
import VerticalNavSectionTitle from '@/@layouts/components/VerticalNavSectionTitle.vue';
import VerticalNavLink from '@layouts/components/VerticalNavLink.vue';
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
  <VerticalNavSectionTitle
    :item="{
      heading: 'مدیریت فروش',
    }"
  />
  
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
  <VerticalNavSectionTitle
    :item="{
      heading: 'صفحات',
    }"
  />

  <VerticalNavLink
    :item="{
      title: 'Account Settings',
      icon: 'bx-user',
      to: '/account-settings',
    }"
  />
  
  <VerticalNavLink
    :item="{
      title: 'Typography',
      icon: 'bx-message-dots',
      to: '/typography',
    }"
  />
  
  <VerticalNavLink
    :item="{
      title: 'Icons',
      icon: 'bx-crown',
      to: '/icons',
    }"
  />
  
  <VerticalNavLink
    :item="{
      title: 'Cards',
      icon: 'bx-collection',
      to: '/cards',
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
