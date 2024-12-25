import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import Statistics from '@/views/pages/Statistics/Statistics.vue'

vueRegister({
  Statistics,
})
vueApply('#statistics')
