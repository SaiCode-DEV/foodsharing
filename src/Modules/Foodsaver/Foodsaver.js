import '@/core'
import '@/globals'
import { GET, goTo, pulseSuccess } from '@/script'
import { expose } from '@/utils'
import 'jquery-dynatree'
import i18n from '@/helper/i18n'
import { deleteUser } from '@/api/user'
import './Foodsaver.css'
import { vueApply, vueRegister } from '@/vue'
import RegionTreeVForm from '@/components/regiontree/RegionTreeVForm'
import LeafletLocationSearchVForm from '@/components/map/LeafletLocationSearchVForm'

export async function confirmDeleteUser (fsId, name) {
  let reason
  do {
    reason = window.prompt(i18n('foodsaver.delete_account_sure_reason', { name }))
    if (reason !== null && reason.length > 0) {
      await deleteUser(fsId, reason)
      pulseSuccess(i18n('success'))
      goTo('/?page=dashboard')
    }
  } while (reason !== null && reason.length < 1)
}

expose({
  confirmDeleteUser,
})

if (GET('a') === 'edit') {
  vueRegister({
    RegionTreeVForm,
    LeafletLocationSearchVForm,
  })
  vueApply('#region-tree-vform', true)
  vueApply('#foodsaver-address-search')
}
