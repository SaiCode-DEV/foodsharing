import '@/core'
import '@/globals'
import { vueRegister, vueApply } from '@/vue'
import Profile from './components/Profile.vue'
import PublicProfile from './components/PublicProfile.vue'

const path = window.location.pathname.toLowerCase()
const profileRegEx = /^\/user\/\d+\/profile$/
const publicProfileRegEx = /^\/user\/\d+\/profile\/public$/

if (profileRegEx.test(path)) {
  vueRegister({
    Profile,
  })
  vueApply('#vue-profile', true)
}

if (publicProfileRegEx.test(path)) {
  vueRegister({
    PublicProfile,
  })
  vueApply('#profile-public', true)
}
