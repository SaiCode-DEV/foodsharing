/* eslint-disable camelcase */
import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import ProfileSettingsPage from './components/ProfileSettingsPage.vue'

vueRegister({ ProfileSettingsPage })
vueApply('#profile-settings-page')
