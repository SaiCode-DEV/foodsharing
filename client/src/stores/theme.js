import { defineStore } from 'pinia'
import i18n from '@/helper/i18n'

const storageKey = 'theme'

const defaultTheme = {
  userTheme: 'system',
  isDark: false,
}

function loadSettings () {
  if (!window.localStorage || typeof window.localStorage === 'undefined') {
    return defaultTheme
  }
  const themeStoreage = window.localStorage?.getItem(storageKey)
  if (!themeStoreage) {
    return defaultTheme
  }
  return {
    ...defaultTheme,
    ...JSON.parse(themeStoreage),
  }
}

export const useThemeStore = defineStore(storageKey, {
  state: () => ({
    ...loadSettings(),
  }),
  getters: {
    getCurrentTheme: (state) => state.userTheme,
    getCurrentIcon: (state) => themes.find((theme) => theme.value === state.userTheme)?.icon,
    getCurrentText: (state) => themes.find((theme) => theme.value === state.userTheme)?.text,
    getCurrentDark: (state) => themes.find((theme) => theme.value === state.userTheme)?.isDark,
  },
  actions: {
    async updateTheme (theme) {
      if (!theme || !themeKeys.includes(theme)) {
        this.userTheme = themeKeys[(themeKeys.indexOf(this.userTheme) + 1) % themeKeys.length]
      } else {
        this.userTheme = theme
      }
      window.localStorage?.setItem(storageKey, JSON.stringify({ userTheme: this.userTheme }))
    },
  },
})

export const themes = [
  {
    value: 'system',
    text: i18n('theme_switcher.theme.system'),
    icon: 'fa-computer',
    isDark: null,
  },
  {
    value: 'light',
    text: i18n('theme_switcher.theme.light'),
    icon: 'fa-sun',
    isDark: false,
  },
  {
    value: 'dark',
    text: i18n('theme_switcher.theme.dark'),
    icon: 'fa-moon',
    isDark: true,
  },
]

export const themeKeys = themes.map((theme) => theme.value)
