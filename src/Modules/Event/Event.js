import '@/core'
import '@/globals'
import './Event.css'
// Wallpost
import { vueRegister, vueApply } from '@/vue'
import EventForm from '@/components/Event/EventForm.vue'
import EventPage from '@/views/pages/Event/EventPage.vue'

const path = window.location.pathname.toLowerCase()
const eventEditRegEx = /^\/event\/\d+\/edit(\?|$)/
const eventAddRegEx = /^\/event\/add(\?|$)/

if (eventAddRegEx.test(path) || eventEditRegEx.test(path)) {
  vueRegister({ EventForm })
  vueApply('#event-form')
} else {
  vueRegister({ EventPage })
  vueApply('#event-page')
}
