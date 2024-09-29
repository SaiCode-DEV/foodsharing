import '@/core'
import '@/globals'
import { vueRegister, vueApply } from '@/vue'

import { GET } from '@/script'
import Wall from '@/components/Wall/Wall'
import RegionReportPage from '@/views/pages/Report/RegionReportPage.vue'
import UserReportPage from '@/views/pages/Report/UserReportPage.vue'

if (GET('sub') === 'foodsaver') {
  vueRegister({ Wall })
  vueApply('#vue-wall', true)
}

// TODO
vueRegister({ RegionReportPage, UserReportPage })
vueApply('#report-page')
