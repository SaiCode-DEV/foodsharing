import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import RegisterForm from './components/RegisterForm.vue'

vueRegister({
  RegisterForm,
})
vueApply('#register-form')
