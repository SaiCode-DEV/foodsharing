import '@/core'
import '@/globals'
import './Event.css'
// Wallpost
import { vueRegister, vueApply } from '@/vue'
import Wall from '@/components/Wall/Wall'
import EventPanel from './components/EventPanel'
import LeafletLocationSearchVForm from '@/components/map/LeafletLocationSearchVForm'

const path = window.location.pathname.toLowerCase()
const eventEditRegEx = /^\/event\/\d+\/edit$/
const eventAddRegEx = /^\/event\/\d+\/add$/

if (eventAddRegEx.test(path) || eventEditRegEx.test(path)) {
  vueRegister({ LeafletLocationSearchVForm })
  vueApply('#event-address-search')
} else {
  vueRegister({
    EventPanel,
    Wall,
  })

  vueApply('#event-panel', true)
  vueApply('#vue-wall', true)
}
