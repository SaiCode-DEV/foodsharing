import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import RegisterForm from './components/RegisterForm.vue'
import RegisterEmail from './components/RegisterEmail.vue'

if (document.getElementById('register-form')) {
  vueRegister({
    RegisterForm,
  })
  vueApply('#register-form')
} else if (document.getElementById('register-email')) {
  vueRegister({
    RegisterEmail,
  })
  vueApply('#register-email')
}
