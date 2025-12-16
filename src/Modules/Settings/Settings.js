import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import ProfileSettingsPage from './components/ProfileSettingsPage.vue'
import DeleteAccountPage from './components/DeleteAccountPage.vue'

if (document.getElementById('delete-account-page')) {
  vueRegister({
    DeleteAccountPage,
  })
  vueApply('#delete-account-page')
} else {
  vueRegister({
    ProfileSettingsPage,
  })
  vueApply('#profile-settings-page')
}
