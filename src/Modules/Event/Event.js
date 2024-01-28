import '@/core'
import '@/globals'
import './Event.css'
import { attachAddressPicker } from '@/addressPicker'
import { GET } from '@/browser'
// Wallpost
import { vueRegister, vueApply } from '@/vue'
import Wall from '@/components/Wall/Wall'
import EventPanel from './components/EventPanel'

const sub = GET('sub')
if (sub === 'add' || sub === 'edit') {
  attachAddressPicker()
} else {
  vueRegister({
    EventPanel,
    Wall,
  })

  vueApply('#event-panel', true)
  vueApply('#vue-wall', true)
}
