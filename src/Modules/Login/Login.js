import '@/core'
import '@/globals'
import './Login.css'
import { vueApply, vueRegister } from '@/vue'
import LoginPage from '@/views/pages/Login/LoginPage.vue'
import ForgotPasswordPage from '@/views/pages/Login/ForgotPasswordPage.vue'
import ResetPasswordWithTokenPage from '@/views/pages/Login/ResetPasswordWithTokenPage.vue'

vueRegister({
  LoginPage,
  ForgotPasswordPage,
  ResetPasswordWithTokenPage,
})

document.addEventListener('DOMContentLoaded', () => {
  vueApply('#reset-password-with-token-page')
  vueApply('#forgot-password-page')
  vueApply('#login-page')
})
