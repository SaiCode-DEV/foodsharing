import '@/core'
import '@/globals'
import { vueRegister, vueApply } from '@/vue'
import Mailbox from '@/components/Mailbox/Mailbox.vue'

vueRegister({
  Mailbox,
})
document.addEventListener('DOMContentLoaded', () => {
  vueApply('#vue-mailbox') // Mailbox
})
