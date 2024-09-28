<template>
  <div class="bg-white rounded pt-4 pb-4">
    <b-container>
      <b-row>
        <b-col cols="12" xl="3">
          <ProfileMenu :profile-menu="menu" :current-user-id="currentUserId" />
          <ProfileInfos :profile-infos="profileInfos" class="pt-2" />
        </b-col>
        <b-col cols="12" xl="9">
          <EmailBounceList
            v-if="isBounceWarningNotEmpty"
            :bounce-warning="bounceWarning"
          />
          <div v-if="!showProfileTab">
            <ProfileRegionAndGroupInfos
              :user-id="menu.fsId"
              :name="menu.foodSaverName"
              :statistics="statistics"
              :ambassador-regions="ambassadorRegions"
              :food-saver-regions="foodSaverRegions"
              :about-me-intern="aboutMeIntern"
              :working-groups="workingGroups"
              :working-groups-admins="workingGroupsAdmins"
              :sleeping-information="sleepingInformation"
              :home-district-history="homeDistrictHistory"
              :role="profileInfos.role"
              :home-region-id="profileInfos.homeRegionId"
              :home-region-name="profileInfos.homeRegionName"
              class="mt-2"
            />
          </div>
          <div v-else>
            <b-tabs
              fill
              card
              class="pb-4"
            >
              <b-tab
                :title="$i18n('profile.tab_navigation.profile')"
                active
              >
                <ProfileRegionAndGroupInfos
                  :user-id="menu.fsId"
                  :name="menu.foodSaverName"
                  :statistics="statistics"
                  :ambassador-regions="ambassadorRegions"
                  :food-saver-regions="foodSaverRegions"
                  :about-me-intern="aboutMeIntern"
                  :working-groups="workingGroups"
                  :working-groups-admins="workingGroupsAdmins"
                  :sleeping-information="sleepingInformation"
                  :home-district-history="homeDistrictHistory"
                  :role="profileInfos.role"
                  :home-region-id="profileInfos.homeRegionId"
                  :home-region-name="profileInfos.homeRegionName"
                  class="mt-2"
                />
              </b-tab>
              <b-tab
                v-if="showPickupsTab"
                :title="$i18n('profile.tab_navigation.pickups')"
              >
                <PickupsSection :pickups-section="pickupsSection" />
              </b-tab>
              <b-tab
                v-if="showProfileCommitmentsStat"
                :title="$i18n('profile.tab_navigation.commitment_statistics')"
              >
                <ProfileCommitmentsStat :commitments-stats="profileCommitmentsStat.data" />
              </b-tab>
              <b-tab
                v-if="menu.maySeeStores"
                :title="$i18n('profile.tab_navigation.stores', { count: stores.length })"
              >
                <ProfileStoreList
                  :user-id="menu.fsId"
                  :stores="stores"
                />
              </b-tab>
              <b-tab
                v-if="maySeeUserNotes"
                :title="$i18n('profile.tab_navigation.notes', { count: noteCount })"
              >
                <b-alert
                  show
                  class="mt-2"
                >
                  {{ $i18n('profile.notes.info') }}
                </b-alert>
                <Wall
                  target="usernotes"
                  :target-id="profileInfos.fsId"
                />
              </b-tab>
              <b-tab v-if="awardedAchievements?.length" :title="$i18n('terminology.achievements') + `(${awardedAchievements.length})`">
                <Achievements
                  :achievements="awardedAchievements"
                />
              </b-tab>
            </b-tabs>
          </div>
        </b-col>
      </b-row>
      <b-row>
        <b-col>
          <Wall
            target="foodsaver"
            :target-id="profileInfos.fsId"
          />
        </b-col>
      </b-row>
    </b-container>
  </div>
</template>

<script>
import ProfileMenu from './ProfileMenu.vue'
import ProfileInfos from './ProfileInfos.vue'
import ProfileRegionAndGroupInfos from './ProfileRegionAndGroupInfos.vue'
import Wall from '@/components/Wall/Wall.vue'
import ProfileCommitmentsStat from './ProfileCommitmentsStat.vue'
import EmailBounceList from './EmailBounceList.vue'
import PickupsSection from '@/components/PickupTable/PickupsSection.vue'
import ProfileStoreList from './ProfileStoreList.vue'
import Achievements from '@/components/Achievement/Achievements.vue'
import { useUserStore } from '@/stores/user'
import { ROLE } from '@/consts'

const userStore = useUserStore()

export default {
  name: 'Profile',
  components: { ProfileStoreList, ProfileMenu, ProfileInfos, ProfileRegionAndGroupInfos, Wall, ProfileCommitmentsStat, EmailBounceList, PickupsSection, Achievements },
  props: {
    menu: { type: Object, required: true },
    statistics: { type: Object, required: true },
    ambassadorRegions: { type: Array, required: true },
    foodSaverRegions: { type: Array, required: true },
    aboutMeIntern: { type: String, required: true },
    workingGroups: { type: Array, required: true },
    workingGroupsAdmins: { type: Array, required: true },
    sleepingInformation: { type: Object, required: true },
    profileInfos: { type: Object, required: true },
    profileCommitmentsStat: { type: Object, required: true },
    bounceWarning: { type: Object, required: true },
    pickupsSection: { type: Object, required: true },
    maySeeUserNotes: { type: Boolean, required: true },
    noteCount: { type: Number, required: true },
    stores: { type: Array, required: true },
    homeDistrictHistory: { type: Object, required: true },
    awardedAchievements: { type: [Array, Object], default: null },
  },
  setup () {
    return {
      userStore,
    }
  },
  data () {
    return {
      currentUserId: userStore.getUserId,
    }
  },
  computed: {
    isBounceWarningNotEmpty () {
      return Object.keys(this.bounceWarning).length > 0
    },
    showPickupsTab () {
      return this.pickupsSection.showHistoryTab || this.pickupsSection.showOptionsTab || this.pickupsSection.showRegisteredTab
    },
    showProfileCommitmentsStat () {
      const isFoodsaver = this.isFoodSaver
      return isFoodsaver &&
        this.profileCommitmentsStat.maySeeCommitmentsStat &&
        (this.profileCommitmentsStat.data.length > 0)
    },
    isFoodSaver () {
      return this.profileInfos.role > ROLE.FOODSHARER
    },
    showProfileTab () {
      return this.showPickupsTab || this.showProfileCommitmentsStat || this.menu.maySeeStores || this.maySeeUserNotes
    },
  },
}
</script>
