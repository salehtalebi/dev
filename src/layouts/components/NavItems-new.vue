<script setup>
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

  <!-- Dashboard New -->
  <VerticalNavLink
    :item="{
      title: 'Dashboard New',
      icon: 'bx-bar-chart-alt-2',
      to: '/dashboard-new',
    }"
  />


  
  <VerticalNavLink
    :item="{
      title: 'Orders',
      icon: 'bx-cart',
      to: '/orders',
    }"
  />
  
  <VerticalNavLink
    :item="{
      title: 'Customers',
      icon: 'bx-user',
      to: '/customers',
    }"
  />



  <VerticalNavLink
    :item="{
      title: 'Account Settings',
      icon: 'bx-user',
      to: '/account-settings',
    }"
  />
  
  <!-- <VerticalNavLink
    :item="{
      title: 'Typography',
      icon: 'bx-message-dots',
      to: '/typography',
    }"
  /> -->
  
  <!-- <VerticalNavLink
    :item="{
      title: 'Icons',
      icon: 'bx-crown',
      to: '/icons',
    }"
  /> -->
  
  <!-- <VerticalNavLink
    :item="{
      title: 'Cards',
      icon: 'bx-collection',
      to: '/cards',
    }"
  /> -->
  
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
