<template>
  <b-button
    variant="blank"
    class="nav-link"
    :title="i18n('theme_switcher.title')"
    @click="themeStore.updateTheme()"
  >
    <slot name="icon">
      <i
        class="icon-nav fas"
        :class="themeStore.getCurrentIcon"
      />
    </slot>
  </b-button>
</template>

<script setup>
import { useThemeStore } from '@/stores/theme'
import i18n from '@/helper/i18n'

const themeStore = useThemeStore()

</script>
