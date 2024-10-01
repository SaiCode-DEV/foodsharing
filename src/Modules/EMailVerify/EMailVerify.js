import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import EmailVerificationPage from '@/views/pages/EMailVerify/EmailVerificationPage.vue'

vueRegister({
  EmailVerificationPage,
})
vueApply('#email-verification-page')
