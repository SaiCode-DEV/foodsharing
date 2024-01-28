import '@/core'
import '@/globals'
import './Profile.css'
import { vueRegister, vueApply } from '@/vue'
import BananaList from './components/BananaList'
import PublicProfile from './components/PublicProfile'
import ProfileStoreList from './components/ProfileStoreList'
import EmailBounceList from './components/EmailBounceList'
import PickupsSection from '@/components/PickupTable/PickupsSection'
import ProfileCommitmentsStat from './components/ProfileCommitmentsStat'
import ProfileMenu from './components/ProfileMenu'
import ProfileInfos from './components/ProfileInfos'
import Wall from '@/components/Wall/Wall'

vueRegister({
  BananaList,
  ProfileStoreList,
  PublicProfile,
  EmailBounceList,
  PickupsSection,
  ProfileCommitmentsStat,
  ProfileInfos,
  ProfileMenu,
  Wall,
})

vueApply('#vue-profile-bananalist', true) // BananaList
vueApply('#vue-profile-storelist', true) // ProfileStoreList
vueApply('#profile-public', true) // PublicProfile
vueApply('#email-bounce-list', true)
vueApply('#profile-commitments-stat', true)
vueApply('#pickups-section', true)
vueApply('#vue-profile-infos', true)
vueApply('#vue-profile-menu', true)
vueApply('#vue-wall', true)
