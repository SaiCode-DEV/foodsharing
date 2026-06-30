import '@/core'
import '@/globals'

import { vueRegister, vueApply } from '@/vue'

// View: Message
import '@/views/pages/Message/MessagePage.scss'
import MessagePage from '@/views/pages/Message/MessagePage.vue'

vueRegister({
  MessagePage,
})
document.addEventListener('DOMContentLoaded', () => {
  vueApply('#message')
})
