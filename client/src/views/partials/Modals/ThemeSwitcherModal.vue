<template>
  <b-modal
    id="themeSwitcherModal"
    ref="themeSwitcherModal"
    :title="$i18n('theme_switcher.title')"
  >
    {{ $i18n('theme_switcher.content') }}
    <b-form-select
      v-model="themeStore.userTheme"
      :options="themes"
      text="Dropdown Button"
      @change="themeStore.updateTheme"
    />

    <template #modal-footer="{ ok }">
      <b-button
        v-if="ok"
        variant="primary"
        @click="ok"
      >
        {{ $i18n('theme_switcher.choose_button') }}
      </b-button>
    </template>
  </b-modal>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { nextTick, onMounted, onUnmounted, watch } from 'vue'
import { useThemeStore, themes, themeKeys } from '@/stores/theme'
const themeStore = useThemeStore()
const { getCurrentTheme } = storeToRefs(themeStore)
let mediaQueryListener = null

function setDarkMode () {
  themeKeys.forEach((key) => {
    document.body.classList.toggle(`${key}-mode`, key === themeStore.getCurrentTheme)
  })
  // if the theme has a isDark property (not null or undefined)
  if (themeStore.getCurrentDark !== null && themeStore.getCurrentDark !== undefined) {
    themeStore.isDark = themeStore.getCurrentDark
  }

  if (themeStore.getCurrentTheme === 'system') {
    themeStore.isDark = mediaQueryListener.matches
    document.body.classList.add(`${themeStore.isDark ? 'dark' : 'light'}-mode`)
  }
}

onMounted(() => {
  // Create a media query listener to detect changes in the user's system theme
  mediaQueryListener = window.matchMedia('(prefers-color-scheme: dark)')
  nextTick(() => {
    mediaQueryListener.addEventListener('change', setDarkMode)
  })
  setDarkMode()
})

onUnmounted(() => {
  mediaQueryListener.removeEventListener('change', setDarkMode)
})

watch(getCurrentTheme, () => {
  setDarkMode()
})

</script>
