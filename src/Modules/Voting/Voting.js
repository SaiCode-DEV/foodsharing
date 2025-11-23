import '@/core'
import '@/globals'
import { vueRegister, vueApply } from '@/vue'
import PollOverview from './components/PollOverview.vue'
import NewPollForm from './components/NewPollForm'
import { GET } from '@/browser'

if (GET('sub') === 'new') {
  vueRegister({
    NewPollForm,
  })
  vueApply('#new-poll-form')
} else if (GET('sub') === 'edit') {
  // reuse NewPollForm for edit mode; the server will provide the `poll` prop
  vueRegister({
    NewPollForm,
  })
  vueApply('#new-poll-form')
} else {
  vueRegister({
    PollOverview,
  })
  vueApply('#poll-overview')
}
