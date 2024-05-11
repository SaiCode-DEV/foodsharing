/* eslint-disable camelcase */
import '@/core'
import '@/globals'
import './Settings.css'
import { GET, collapse_wrapper } from '@/script'
import { expose } from '@/utils'
import { confirmDeleteUser } from '../Foodsaver/Foodsaver'
import { vueApply, vueRegister } from '@/vue'
import Calendar from './components/Calendar'
import ProfilePicture from './components/ProfilePicture'
import NameInput from './components/NameInput'
import LeafletLocationSearchVForm from '@/components/map/LeafletLocationSearchVForm'
import RegionTreeVForm from '@/components/regiontree/RegionTreeVForm'
import Passport from './components/Passport.vue'
import Notifications from './components/Notifications.vue'
import SleepingMode from './components/SleepingMode.vue'
import ChangeEmailForm from './components/ChangeEmailForm'
import DeleteAccount from './components/DeleteAccount.vue'
import Quiz from '@/views/pages/Quiz/Quiz'

// collapse_wrapper is used by v_input_wrapper if it has the parameter 'collapse'
expose({ collapse_wrapper })

switch (GET('sub')) {
  case 'deleteaccount':
    vueRegister({ DeleteAccount })
    vueApply('#delete-account')
    break
  case 'info':
    vueRegister({ Notifications })
    vueApply('#notifications')
    break
  case 'passport':
    vueRegister({ Passport })
    vueApply('#passport')
    break
  case 'calendar':
    vueRegister({ Calendar })
    vueApply('#calendar')
    break
  case 'sleeping':
    vueRegister({ SleepingMode })
    vueApply('#sleeping-mode')
    break
  case 'changeEmail':
    vueRegister({ ChangeEmailForm })
    vueApply('#change-email-form')
    break
  case 'general':
    vueRegister({ ProfilePicture, NameInput, LeafletLocationSearchVForm, RegionTreeVForm })
    vueApply('#image-upload')
    vueApply('#name-input')
    vueApply('#settings-address-search')

    if (document.getElementById('region-tree-vform') !== null) {
      vueApply('#region-tree-vform')
    }
    break
  case 'rise_role':
    vueRegister({ Quiz })
    vueApply('#vue-quiz-page')
    break
}

expose({
  confirmDeleteUser,
})
