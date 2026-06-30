import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import RegisterForm from './components/RegisterForm.vue'
import RegisterEmail from './components/RegisterEmail.vue'

vueRegister({
  RegisterForm,
  RegisterEmail,
})

document.addEventListener('DOMContentLoaded', () => {
  vueApply('#register-form')
  vueApply('#register-email')
})
