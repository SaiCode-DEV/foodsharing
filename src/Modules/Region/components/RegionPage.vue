<template>
  <div class="container">
    <div class="row">
      <div class="col-12">
        <RegionTop
          :region-id="regionId"
          :name="name"
          :food-saver-count="foodSaverCount"
          :food-saver-home-district-count="foodSaverHomeDistrictCount"
          :food-saver-has-sleeping-hat-count="foodSaverHasSleepingHatCount"
          :ambassador-count="ambassadorCount"
          :stores-count="storesCount"
          :stores-cooperation-count="storesCooperationCount"
          :stores-pickups-count="storesPickupsCount"
          :stores-fetched-weight="storesFetchedWeight"
          :is-work-group="isWorkGroup"
        />
      </div>
    </div>
    <div class="row">
      <div class="col-12 col-lg-4 col-xl-3">
        <RegionSideNav
          v-if="!isWorkGroup"
          :is-work-group="isWorkGroup"
          :region-menu="menu"
        />
        <GroupSideNav
          v-else
          :applications="applications"
          :group-menu="menu"
        />
        <div
          v-for="(allAdminData, index) in allAdminsData"
          :key="index"
        >
          <ResponsibleUsers
            v-if="allAdminData.value.length > 0"
            :responsible-users="allAdminData.value"
            :title="$i18n(allAdminData.label)"
          />
        </div>
        <LeaveRegionContainer
          :region-id="regionId"
          :name="name"
          :is-work-group="isWorkGroup"
        />
      </div>
      <div class="col-12 col-lg-8 col-xl-9">
        <NewThread
          v-if="(activeSubpage === SUB_PAGE.FORUM || activeSubpage === SUB_PAGE.AMBASSADOR_FORUM) && showNewThreadForm"
          :subforum-id="subForumId"
          :group-id="regionId"
          :is-moderated="moderated"
        />
        <ThreadList
          v-if="(activeSubpage === SUB_PAGE.FORUM || activeSubpage === SUB_PAGE.AMBASSADOR_FORUM) && !forumThreadId && !showNewThreadForm"
          :subforum-id="subForumId"
          :group-id="regionId"
        />
        <Thread
          v-if="(activeSubpage === SUB_PAGE.FORUM || activeSubpage === SUB_PAGE.AMBASSADOR_FORUM) && forumThreadId"
          :id="forumThreadId"
        />
        <EventList
          v-if="activeSubpage === SUB_PAGE.EVENTS"
          :region-id="regionId"
        />
        <FoodSharePointsList
          v-if="activeSubpage === SUB_PAGE.FOODSHARINGPOINT"
          :region-name="name"
          :region-id="regionId"
          :food-share-point-permission="regionMenu?.mayAddFoodSharePoints ?? false"
        />
        <PollList
          v-if="activeSubpage === SUB_PAGE.POLLS"
          :region-id="regionId"
          :may-create-poll="regionMenu?.mayCreatePoll ?? false"
        />
        <MemberList
          v-if="activeSubpage === SUB_PAGE.MEMBERS"
          :group-id="regionId"
          :region-name="name"
          :region-id="regionId"
          :user-id="pageData.userId"
          :may-set-admin-or-ambassador="pageData.maySetAdminOrAmbassador"
          :may-remove-admin-or-ambassador="pageData.mayRemoveAdminOrAmbassador"
          :may-edit-members="pageData.mayEditMembers"
          :is-work-group="isWorkGroup"
        />
        <Options
          v-if="activeSubpage === SUB_PAGE.OPTIONS"
          :region-id="regionId"
          :region-name="name"
        />
        <Statistics
          v-if="activeSubpage === SUB_PAGE.STATISTIC"
          :region-id="regionId"
          :region-name="name"
        />
        <Wall
          v-if="activeSubpage === SUB_PAGE.WALL"
          target="bezirk"
          :target-id="regionId"
        />
        <ApplicationsList
          v-if="activeSubpage === SUB_PAGE.APPLICATIONS"
          :group-name="name"
          :applications="applications"
          :group-id="regionId"
        />
        <Achievements
          v-if="activeSubpage === SUB_PAGE.ACHIEVEMENTS"
          :group-name="name"
          :group-id="regionId"
          :is-work-group="isWorkGroup"
          :may-administrate-achievements="regionMenu?.mayAdministrateAchievements ?? false"
        />
        <Resources
          v-if="activeSubpage === SUB_PAGE.RESOURCES"
          :group-name="name"
          :group-id="regionId"
        />
        <WorkingGroupEditForm
          v-if="isWorkGroup && activeSubpage === SUB_PAGE.SETTINGS"
          :group="pageData.group"
        />
      </div>
    </div>
  </div>
</template>

<script>
import RegionTop from './RegionTop.vue'
import RegionSideNav from './RegionSideNav.vue'
import GroupSideNav from './GroupSideNav.vue'
import ThreadList from './ThreadList.vue'
import EventList from '../../Event/components/EventList.vue'
import FoodSharePointsList from './FoodSharePointsList.vue'
import PollList from './PollList.vue'
import MemberList from './MemberList.vue'
import Options from './Options.vue'
import Statistics from './Statistics.vue'
import ResponsibleUsers from './ResponsibleUsers.vue'
import Wall from '@/components/Wall/Wall'
import Thread from './Thread.vue'
import NewThread from './NewThread.vue'
import { getApplications } from '@/api/applications'
import ApplicationsList from './ApplicationsList.vue'
import Achievements from './Achievements.vue'
import Resources from '@/views/pages/Resources/Resources.vue'
import WorkingGroupEditForm from '@/components/workinggroups/WorkingGroupEditForm.vue'
import { SUB_PAGE, useRegionStore } from '@/stores/regions'
import { GET } from '@/browser'
import LeaveRegionContainer from '@/views/pages/Region/LeaveRegionContainer.vue'

const regionStore = useRegionStore()

export default {
  components: {
    Achievements,
    ApplicationsList,
    LeaveRegionContainer,
    NewThread,
    Thread,
    ResponsibleUsers,
    Statistics,
    Options,
    MemberList,
    PollList,
    FoodSharePointsList,
    RegionTop,
    RegionSideNav,
    GroupSideNav,
    ThreadList,
    EventList,
    Wall,
    Resources,
    WorkingGroupEditForm,
  },
  props: {
    regionId: { type: Number, required: true },
    name: { type: String, required: true },
    isWorkGroup: { type: Boolean, required: true },
    isRegion: { type: Boolean, required: true },
    moderated: { type: Boolean, required: true },
    foodSaverCount: { type: Number, required: true },
    foodSaverHomeDistrictCount: { type: Number, required: true },
    foodSaverHasSleepingHatCount: { type: Number, required: true },
    ambassadorCount: { type: Number, required: true },
    storesCount: { type: Number, required: true },
    storesCooperationCount: { type: Number, required: true },
    storesPickupsCount: { type: Number, required: true },
    storesFetchedWeight: { type: Number, required: true },
    activeSubpage: { type: String, required: true },
    allAdmins: { type: Object, required: true },
    pageData: { type: [Array, Object], default: () => {} },
    menu: { type: Object, required: true },
    mayAccessApplications: { type: Boolean, required: true },
  },
  data () {
    return {
      allAdminsData: this.isWorkGroup
        ? [{ value: this.allAdmins.botschafter, label: 'terminology.admins' }]
        : [
            { value: this.allAdmins.botschafter, label: 'terminology.ambassadors' },
            { value: this.allAdmins.welcomeAdmins ?? [], label: 'terminology.welcomeAdmins' },
            { value: this.allAdmins.votingAdmins ?? [], label: 'terminology.votingAdmins' },
            { value: this.allAdmins.fspAdmins ?? [], label: 'terminology.fspAdmins' },
            { value: this.allAdmins.storesAdmins ?? [], label: 'terminology.storesAdmins' },
            { value: this.allAdmins.reportAdmins ?? [], label: 'terminology.reportAdmins' },
            { value: this.allAdmins.mediationAdmins ?? [], label: 'terminology.mediationAdmins' },
            { value: this.allAdmins.prAdmins ?? [], label: 'terminology.prAdmins' },
            { value: this.allAdmins.moderationAdmins ?? [], label: 'terminology.moderationAdmins' },
            { value: this.allAdmins.boardAdmins ?? [], label: 'terminology.boardAdmins' },
            { value: this.allAdmins.electionAdmins ?? [], label: 'terminology.electionAdmins' },
            { value: this.allAdmins.arbitrationAdmins ?? [], label: 'terminology.arbitrationAdmins' },
            { value: this.allAdmins.resourcesAdmins ?? [], label: 'terminology.resourcesAdmins' },
          ],
      loading: true,
      applications: [],
      regionMenu: null,
    }
  },
  computed: {
    SUB_PAGE () {
      return SUB_PAGE
    },
    subForumId () {
      return this.activeSubpage === SUB_PAGE.AMBASSADOR_FORUM ? 1 : 0
    },
    forumThreadId () {
      return Number(GET('tid')) ?? null
    },
    showNewThreadForm () {
      return Number(GET('newthread')) === 1
    },
  },
  async mounted () {
    if (this.isWorkGroup && this.mayAccessApplications) {
      this.applications = await getApplications(this.regionId)
    }
    this.regionMenu = await regionStore.fetchRegionMenu(this.regionId)
  },
}
</script>
