// https://vitepress.dev/guide/custom-theme
import { h } from 'vue'
import type { Theme } from 'vitepress'
import DefaultTheme from 'vitepress/theme'

import RegisterSW from './components/RegisterSW.vue'

import { theme, useOpenapi } from 'vitepress-theme-openapi'
import spec from '../../data/api_dump.json' assert { type: 'json' }

import './style.css'
import 'vitepress-theme-openapi/dist/style.css'

export default {
  extends: DefaultTheme,
  Layout: () => {
    return h(DefaultTheme.Layout, null, {
      // https://vitepress.dev/guide/extending-default-theme#layout-slots
      'layout-bottom': () => h(RegisterSW)
    })
  },
  enhanceApp({ app, router, siteData }) {
    const openapi = useOpenapi()
    openapi.setSpec(spec)

    theme.enhanceApp({ app, router, siteData })
  }
} satisfies Theme
