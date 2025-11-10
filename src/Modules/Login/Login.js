import '@/core'
import '@/globals'
import './Login.css'
import { vueApply, vueRegister } from '@/vue'
import LoginPage from '@/views/pages/Login/LoginPage.vue'
import ForgotPasswordPage from '@/views/pages/Login/ForgotPasswordPage.vue'
import ResetPasswordWithTokenPage from '@/views/pages/Login/ResetPasswordWithTokenPage.vue'
import { url } from '@/helper/urls'

vueRegister({
  LoginPage,
  ForgotPasswordPage,
  ResetPasswordWithTokenPage,
})

const currentPath = window.location.pathname
const isPasswordResetWithToken = currentPath.match(/password-reset\/[a-f0-9]+$/)
const isForgotPassword = currentPath === url('passwordReset')

if (isPasswordResetWithToken) {
  vueApply('#reset-password-with-token-page')
} else if (isForgotPassword) {
  vueApply('#forgot-password-page')
} else {
  vueApply('#login-page')
}
