import '@/core'
import '@/globals'
import './Region.css'
import { vueRegister, vueApply } from '@/vue'
import RegionPage from './components/RegionPage.vue'
import RegionsAdmin from '@/views/pages/Region/RegionsAdmin.vue'
import PublicRegionPage from '@/views/pages/Region/PublicRegionPage.vue'

vueRegister({ PublicRegionPage, RegionPage, RegionsAdmin })

document.addEventListener('DOMContentLoaded', () => {
  vueApply('#public-region-page')
  vueApply('#region-page')
  vueApply('#regions-admin-page')
})
