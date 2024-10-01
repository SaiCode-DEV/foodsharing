<template>
  <b-nav-item class="theme-switcher" @click="themeStore.updateTheme()">
    <slot name="icon">
      <i
        class="icon-nav fas"
        :class="themeStore.getCurrentIcon"
      />
    </slot>
  </b-nav-item>
</template>

<script setup>
import { useThemeStore } from '@/stores/theme'

const themeStore = useThemeStore()

</script>
