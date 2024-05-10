<template>
  <div class="bg-white rounded pt-4 pb-4">
    <b-container>
      <b-row>
        <b-col cols="12" xl="3">
          <ProfileMenu :profile-menu="menu" />
          <ProfileInfos :profile-infos="profileInfos" class="pt-2" />
        </b-col>
        <b-col cols="12" xl="9">
          <EmailBounceList
            v-if="bounceWarning > 0"
            :bounce-warning="bounceWarning"
          />
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
                :banana-statistics="bananaStatistics"
                :statistics="statistics"
                :ambassador-regions="ambassadorRegions"
                :food-saver-regions="foodSaverRegions"
                :about-me-intern="aboutMeIntern"
                :working-groups="workingGroups"
                :working-groups-admins="workingGroupsAdmins"
                :sleeping-information="sleepingInformation"
                :home-district-history="homeDistrictHistory"
                :role="profileInfos.role"
                class="mt-2"
              />
            </b-tab>
            <b-tab
              :title="$i18n('profile.tab_navigation.pickups')"
            >
              <PickupsSection :pickups-section="pickupsSection" />
            </b-tab>
            <b-tab
              v-if="profileCommitmentsStat.maySeeCommitmentsStat"
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
          </b-tabs>
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

export default {
  name: 'Profile',
  components: { ProfileStoreList, ProfileMenu, ProfileInfos, ProfileRegionAndGroupInfos, Wall, ProfileCommitmentsStat, EmailBounceList, PickupsSection },
  props: {
    menu: { type: Object, required: true },
    statistics: { type: Object, required: true },
    bananaStatistics: { type: Object, required: true },
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
  },
}
</script>
