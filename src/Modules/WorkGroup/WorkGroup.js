/* eslint-disable eqeqeq */
import '@/core'
import '@/globals'
import './WorkGroup.css'
import { GET } from '@/browser'
import { vueApply, vueRegister } from '@/vue'
import Groups from './components/Groups.vue'

if (GET('sub') === undefined) {
  vueRegister({
    Groups,
  })
  vueApply('#vue-groups')
}
