import '@/core'
import '@/globals'
import './WorkGroup.css'
import { GET } from '@/browser'
import { vueApply, vueRegister } from '@/vue'
import Groups from './components/Groups.vue'

vueRegister({
  Groups,
})
document.addEventListener('DOMContentLoaded', () => {
  if (GET('sub') === undefined) {
    vueApply('#vue-groups')
  }
})
