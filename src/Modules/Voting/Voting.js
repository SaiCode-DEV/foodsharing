import '@/core'
import '@/globals'
import { vueRegister, vueApply } from '@/vue'
import PollOverview from './components/PollOverview.vue'
import NewPollForm from './components/NewPollForm'
import { GET } from '@/browser'

vueRegister({
  NewPollForm,
  PollOverview,
})
document.addEventListener('DOMContentLoaded', () => {
  if (GET('sub') === 'new') {
    vueApply('#new-poll-form')
  } else if (GET('sub') === 'edit') {
    // reuse NewPollForm for edit mode; the server will provide the `poll` prop
    vueApply('#new-poll-form')
  } else {
    vueApply('#poll-overview')
  }
})
