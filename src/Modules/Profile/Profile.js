import '@/core'
import '@/globals'
import { vueRegister, vueApply } from '@/vue'
import Profile from './components/Profile.vue'
import PublicProfile from './components/PublicProfile.vue'

vueRegister({ Profile, PublicProfile })

document.addEventListener('DOMContentLoaded', () => {
  vueApply('#vue-profile')
  vueApply('#profile-public')
})
