import '@/core'
import '@/globals'
import '@/tablesorter'
import { vueRegister, vueApply } from '@/vue'

import ReportList from './components/ReportList.vue'
import { GET } from '@/script'
import Wall from '@/components/Wall/Wall'

if (GET('sub') === 'foodsaver') {
  vueRegister({ Wall })
  vueApply('#vue-wall', true)
}

// The container for the report list only exists if a region specific page is requested
const reportListContainerId = 'vue-reportlist'
if (document.getElementById(reportListContainerId)) {
  vueRegister({
    ReportList,
  })
  vueApply('#' + reportListContainerId)
}
