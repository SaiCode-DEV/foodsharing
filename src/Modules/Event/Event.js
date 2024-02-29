import '@/core'
import '@/globals'
import './Event.css'
import { GET } from '@/browser'
// Wallpost
import { vueRegister, vueApply } from '@/vue'
import Wall from '@/components/Wall/Wall'
import EventPanel from './components/EventPanel'
import LeafletLocationSearchVForm from '@/components/map/LeafletLocationSearchVForm'

const sub = GET('sub')
if (sub === 'add' || sub === 'edit') {
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
