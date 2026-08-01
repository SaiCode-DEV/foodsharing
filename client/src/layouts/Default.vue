<template>
  <div>
    <Navigation />
    <UiNotifications />
    <router-view />
    <Footer v-if="!$route.meta.hideFooter" />
  </div>
</template>

<script lang="ts" setup>
import Footer from '@/views/partials/Footer/Footer.vue'
import Navigation from '@/views/partials/Navigation/Navigation.vue'
import UiNotifications from '@/components/UI/UiNotifications.vue'
</script>
