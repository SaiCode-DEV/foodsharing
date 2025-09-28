// https://vitepress.dev/guide/custom-theme
import { h } from 'vue'
import type { Theme } from 'vitepress'
import DefaultTheme from 'vitepress/theme'

import RegisterSW from './components/RegisterSW.vue'

import { theme } from 'vitepress-openapi/client'
import spec from '../../data/api_dump.json'

import './style.css'
import 'vitepress-openapi/dist/style.css'

export default {
  extends: DefaultTheme,
  Layout: () => {
    return h(DefaultTheme.Layout, null, {
      // https://vitepress.dev/guide/extending-default-theme#layout-slots
      'layout-bottom': () => h(RegisterSW)
    })
  },
  async enhanceApp({ app }) {
    theme.enhanceApp({ app })
  }
} satisfies Theme
