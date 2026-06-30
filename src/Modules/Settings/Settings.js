import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import ProfileSettingsPage from './components/ProfileSettingsPage.vue'
import DeleteAccountPage from './components/DeleteAccountPage.vue'

vueRegister({
  DeleteAccountPage,
  ProfileSettingsPage,
})

document.addEventListener('DOMContentLoaded', () => {
  vueApply('#delete-account-page')
  vueApply('#profile-settings-page')
})
