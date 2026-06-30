import '@/core'
import '@/globals'
import { vueRegister, vueApply } from '@/vue'

import { GET } from '@/script'
import Wall from '@/components/Wall/Wall'
import RegionReportPage from '@/views/pages/Report/RegionReportPage.vue'
import UserReportPage from '@/views/pages/Report/UserReportPage.vue'

vueRegister({ Wall })
document.addEventListener('DOMContentLoaded', () => {
  if (GET('sub') === 'foodsaver') {
    vueApply('#vue-wall')
  }

  // TODO
  vueRegister({ RegionReportPage, UserReportPage })
  vueApply('#report-page')
})
