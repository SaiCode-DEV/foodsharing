import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import EmailVerificationPage from '@/views/pages/EMailVerify/EmailVerificationPage.vue'
import ResendEmailVerificationForm from '@/components/Login/ResendEmailVerificationForm.vue'

vueRegister({
  EmailVerificationPage,
  ResendEmailVerificationForm,
})

document.addEventListener('DOMContentLoaded', () => {
  vueApply('#email-verification-page')
  vueApply('#resend-email-verification-form')
})
