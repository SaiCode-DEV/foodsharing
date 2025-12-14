import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import EmailVerificationPage from '@/views/pages/EMailVerify/EmailVerificationPage.vue'
import ResendEmailVerificationForm from '@/components/Login/ResendEmailVerificationForm.vue'

if (document.getElementById('#email-verification-page')) {
  vueRegister({
    EmailVerificationPage,
  })
  vueApply('#email-verification-page')
} else if (document.getElementById('resend-email-verification-form')) {
  vueRegister({
    ResendEmailVerificationForm,
  })
  vueApply('#resend-email-verification-form')
}
