import '@/core'
import '@/globals'
import './Event.css'
// Wallpost
import { vueRegister, vueApply } from '@/vue'
import EventForm from '@/components/Event/EventForm.vue'
import EventPage from '@/views/pages/Event/EventPage.vue'

vueRegister({ EventForm, EventPage })

document.addEventListener('DOMContentLoaded', () => {
  vueApply('#event-form')
  vueApply('#event-page')
})
