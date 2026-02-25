<template>
  <section
    v-if="storeInformation"
    class="container my-3 my-sm-5 p-0"
  >
    <b-tabs
      content-class="mt-3"
      card
      fill
    >
      <b-tab
        :title="$t('storeview.common')"
        active
      >
        <div class="row">
          <div class="col-lg-3 mr-lg-4 mr-xl-0">
            <StoreMenu
              :key="'storeMenu' + componentKey"
              :store-name="storeInformation.name"
              :team-conversation-id="permissions.teamConversationId"
              :jumper-conversation-id="permissions.jumperConversationId"
              :may-edit-store="permissions.mayEditStore"
              :is-user-in-store="isUserInStore"
              :may-leave-store-team="permissions.mayLeaveStoreTeam"
              :is-jumper="permissions.isJumper"
              :fs-id="userId"
              :store-id="storeId"
              :is-coordinator="permissions.isCoordinator"
              :is-verified="isVerified"
              :category-type="storeInformation.categoryType"
              :may-delete-store="permissions.mayDeleteStore"
              @multi-chat="multiChat"
            />
            <Wall
              v-if="viewIsMobile && permissions.isJumper === false"
              target="store"
              :target-id="storeId"
            />
            <StoreTeam
              v-if="!viewIsMobile"
              :key="'storeTeam' + componentKey"
              :fs-id="userId"
              :is-coordinator="permissions.isCoordinator"
              :may-edit-store="permissions.mayEditStore"
              :team="storeMember"
              :loaded="finishedLoading"
              :store-id="storeId"
              :store-title="storeInformation.name"
              :region-id="regionId"
              @multi-chat="multiChat"
            />
          </div>
          <div class="col flex-shrink-fix">
            <div
              v-if="permissions.isJumper && !permissions.mayEditStore"
              class="alert alert-info"
              role="alert"
            >
              {{ $t('store.willgetcontacted') }}
            </div>
            <div
              v-if="finishedLoading && !isVerified"
              class="alert alert-info"
              role="alert"
            >
              {{ $t('store.not_verified') }}
            </div>
            <PickupHistory
              v-if="permissions.maySeePickupHistory"
              :store-id="storeId"
              :cooperation-start="storeInformation.cooperationStart"
            />
            <StoreLog
              v-if="permissions.maySeeStoreLog"
              :store-id="storeId"
              :cooperation-start="storeInformation.cooperationStart"
            />
            <Wall
              v-if="!viewIsMobile && permissions.isJumper === false"
              target="store"
              :target-id="storeId"
            />
          </div>
          <div class="col-lg-3 flex-shrink-fix">
            <StoreInfos
              :key="'storeInfo' + componentKey"
              :particularities-description="storeInformation.description"
              :chain-details="storeInformation.chain"
              :weight-type="storeInformation.weight"
              :store-title="storeInformation.name"
              :latitude="storeInformation.location.lat"
              :longitude="storeInformation.location.lon"
              :street="storeInformation.address.street"
              :postcode="storeInformation.address.postalCode"
              :city="storeInformation.address.city"
              :last-fetch-date="lastFetchDate"
              :press="storeInformation.publicity"
              :store-id="storeId"
              :region-pickup-rules="storeInformation.options.useRegionPickupRule"
              :region-pickup-rule-active="regionOptions.isRegionPickupRuleActive"
              :region-pickup-rule-timespan="regionOptions.regionPickupRuleTimespan"
              :region-pickup-rule-limit="regionOptions.regionPickupRuleLimit"
              :region-pickup-rule-limit-day="regionOptions.regionPickupRuleLimitDay"
              :region-pickup-rule-inactive="regionOptions.regionPickupRuleInactiveHours"
              :category-type="storeInformation.categoryType"
            />
            <PickupList
              v-if="permissions.maySeePickup"
              :key="'pickupList' + componentKey"
              :may-see-pickup="permissions.maySeePickup"
              :store-id="storeId"
              :store-title="storeInformation.name"
              :is-coordinator="permissions.isCoordinator"
              :may-edit-store="permissions.mayEditStore"
              :category-type="storeInformation.categoryType"
              :team-conversation-id="permissions.teamConversationId"
            />
            <StoreTeam
              v-if="viewIsMobile"
              :key="'storeTeamMobile' + componentKey"
              :fs-id="userId"
              :is-coordinator="permissions.isCoordinator"
              :may-edit-store="permissions.mayEditStore"
              :team="storeMember"
              :loaded="areMembersLoaded"
              :store-id="storeId"
              :store-title="storeInformation.name"
              :region-id="regionId"
              @multi-chat="multiChat"
            />
          </div>
        </div>
      </b-tab>
      <b-tab :title="$t('storeview.show_settings')">
        <StoreInformation
          v-if="finishedLoading"
          :key="'storeInfo' + componentKey"
          :is-jumper="permissions.isJumper"
          :store-id="storeId"
          :may-edit-store="permissions.mayEditStore"
          :is-coordinator="permissions.isCoordinator"
          :is-verified="isVerified"
          :loaded-pickups="loadedPickups"
        />
      </b-tab>
    </b-tabs>
  </section>
</template>

<script>
import conversationStore from '@/stores/conversations'
import MediaQueryMixin from '@/mixins/MediaQueryMixin'
import StoreTeam from '@/components/Stores/StoreTeam/StoreTeam.vue'
import StoreInfos from '@/components/Stores/StoreInfos.vue'
import PickupHistory from '@/components/Stores/PickupHistory.vue'
import Wall from '@/components/Wall/Wall.vue'
import PickupList from '@/components/Stores/PickupList.vue'
import { useUserStore } from '@/stores/user'
import { useStoreStore } from '@/stores/store'
import StoreData from '@/stores/stores'
import { pulseInfo } from '@/script'
import StoreLog from '@/components/Stores/StoreLog.vue'
import StoreInformation from '@/components/Stores/StoreInformation.vue'
import StoreMenu from '@/components/Stores/StoreMenu.vue'
import { usePickupStore } from '@/stores/pickups'

export default {
  components: {
    StoreMenu,
    StoreInformation,
    StoreTeam,
    StoreInfos,
    PickupHistory,
    PickupList,
    StoreLog,
    Wall,
  },
  mixins: [MediaQueryMixin],
  props: {
    storeId: { type: Number, required: true },
    collectionQuantity: { type: String, default: '' },
  },
  setup () {
    return {
      finishedLoading: false,
      userStore: useUserStore(),
      pickupStore: usePickupStore(),
      storeStore: useStoreStore(),
    }
  },
  data () {
    return {
      isUserInStore: false,
      lastFetchDate: null,
      // used to force a re-render of the store information when needed
      componentKey: 0,
    }
  },
  computed: {
    isVerified () {
      return this.userStore.isVerified
    },
    userId () {
      return this.userStore.getUserId
    },
    regionId () {
      return this.storeInformation.region.id
    },
    storeMember () {
      return StoreData.getters.getStoreMember()
    },
    areMembersLoaded () {
      return StoreData.getters.isStoreMembersLoaded()
    },
    storeInformation () {
      return StoreData.getters.getStoreInformation()
    },
    permissions () {
      return StoreData.getters.getStorePermissions()
    },
    regionOptions () {
      return StoreData.getters.getStoreRegionOptions()
    },
    loadedPickups () {
      return this.pickupStore.getRegularPickup
    },
  },
  async mounted () {
    // fetch all the required data in parallel

    const permissionsPromise = StoreData.mutations.loadPermissions(this.storeId)
    const userDetailsPromise = this.userStore.fetchDetails()
    const storeInformationPromise = StoreData.mutations.loadStoreInformation(this.storeId)
    const storeMemberPromise = StoreData.mutations.loadStoreMember(this.storeId)
    const metaDataPromise = this.storeStore.fetchMetadata()

    await Promise.all([
      metaDataPromise,
      storeMemberPromise.then(() => {
        this.checkIsUserInStore()
        this.getLastFetchDate()
      }),
      Promise.all([storeInformationPromise, userDetailsPromise, permissionsPromise]).then(async () => {
        const loading = []
        if (this.permissions.maySeePickups) {
          loading.push(this.pickupStore.fetchRegularPickup(this.storeId))
          loading.push(StoreData.mutations.loadPickupSignInDates(this.storeId, this.storeInformation.calendarInterval))
        }
        loading.push(StoreData.mutations.loadGetRegionOptions(this.regionId))
        await Promise.all(loading)
        this.loadRightsInfo()
        this.finishedLoading = true
        this.componentKey += 1
      }),
    ])
  },
  methods: {
    loadRightsInfo () {
      if (this.permissions.isManager) return
      if (this.permissions.mayEditStore) {
        if (this.permissions.isOrgaUser) {
          pulseInfo(this.$t('storeedit.team.orga'))
        } else if (this.permissions.isCoordinator) {
          pulseInfo(this.$t('storeedit.team.coordinator'))
        } else if (this.permissions.isAmbassador) {
          pulseInfo(this.$t('storeedit.team.amb'))
        }
      } else if (this.permissions.isKam) {
        pulseInfo(this.$t('storeedit.team.kam'))
      }
    },
    checkIsUserInStore () {
      this.isUserInStore = this.storeMember.some(item => item.id === this.userId)
    },
    getLastFetchDate () {
      const userItem = this.storeMember.find(item => item.id === this.userId)
      if (userItem) {
        const MILLISECONDS_PER_SECOND = 1000
        // Multiplication by 1000 used in this context to convert a Unix timestamp from seconds to milliseconds.
        // JavaScript expects Unix timestamps in milliseconds, while it comes from databases in seconds.
        this.lastFetchDate = userItem?.last_fetch ? new Date(userItem.last_fetch * MILLISECONDS_PER_SECOND) : null
      }
    },
    multiChat (userId) {
      console.warn('StorePage::multiChat with userId', userId)
      if (!userId) return
      const storeManagers = this.storeMember.filter(item => item.isResponsible).map(item => item.id)
      conversationStore.openMultiChat(storeManagers.concat(userId))
    },
  },
}

</script>

<style lang="scss">
body:not(.page-index):not(.page-msg) #main {
  margin-top: 0 !important;
}
</style>
