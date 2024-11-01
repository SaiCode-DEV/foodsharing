/* eslint-disable eqeqeq */
import '@/core'
import '@/globals'
import './WorkGroup.css'
import { GET } from '@/browser'
import { vueApply, vueRegister } from '@/vue'
import WorkingGroupEditForm from '@/components/workinggroups/WorkingGroupEditForm'
import Groups from './components/Groups.vue'

if (GET('sub') === 'edit') {
  vueRegister({
    WorkingGroupEditForm,
  })
  vueApply('#vue-group-edit-form')
} else if (GET('sub') === undefined) {
  vueRegister({
    Groups,
  })
  vueApply('#vue-groups')
}
