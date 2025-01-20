/* eslint-disable eqeqeq */
import '@/core'
import '@/globals'
import './Region.css'
import { vueRegister, vueApply } from '@/vue'
import RegionPage from './components/RegionPage'
import RegionsAdmin from '@/views/pages/Region/RegionsAdmin.vue'
import PublicRegionPage from '@/views/pages/Region/PublicRegionPage.vue'

if (/^\/region\/[^/?]+/.test(location.pathname)) {
  vueRegister({ PublicRegionPage })
  vueApply('#public-region-page')
} else if (/^\/region(?!\w)/.test(location.pathname)) {
  vueRegister({ RegionPage })
  vueApply('#region-page')
} else if (location.pathname.startsWith('/regions/edit')) {
  vueRegister({ RegionsAdmin })
  vueApply('#regions-admin-page')
}
