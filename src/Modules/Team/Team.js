import '@/core'
import '@/globals'
import TeamPage from './components/TeamPage.vue'
import { vueApply, vueRegister } from '@/vue'

vueRegister({
  TeamPage,
})
document.addEventListener('DOMContentLoaded', () => {
  vueApply('#vue-team-page')
})
