import '@/core'
import '@/globals'
import TeamPage from './components/TeamPage.vue'
import { vueApply, vueRegister } from '@/vue'

vueRegister({
  TeamPage,
})
vueApply('#vue-team-page')
