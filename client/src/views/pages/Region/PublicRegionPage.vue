<template>
  <BasePage v-if="regionData" wide-cols>
    <template #top>
      <Breadcrumbs :items="breadcrumbs" />
      <InaccessibleRegionRedirectWarning />
      <PublicRegionTopBanner :region-data="regionData" />
    </template>

    <template #left>
      <RegionSideNav
        v-if="mayAccessRegion && regionMenu"
        :region-menu="regionMenu"
        :is-work-group="false"
      />
      <RegionChildrenContainer :children="regionData.children" :name="regionData.name" />
      <SimpleRegionStatistics v-if="regionData.statistics" :statistics="regionData.statistics" />
      <PublicRegionContactContainer :region-data="regionData" />
      <JoinRegionContainer v-if="mayJoinRegion" :region-data="regionData" />
      <LeaveRegionContainer
        v-else-if="isRegionMember"
        :region-id="id"
        :name="regionData.name"
      />
    </template>

    <PublicRegionDescriptionContainer
      :region-id="id"
      :description.sync="regionData.description"
      :may-edit="mayEditData"
    />
    <RegionMap
      :region-id="id"
      :location.sync="regionData.location"
      :food-share-points="regionData.foodSharePoints"
      :may-edit="mayEditData"
    />
    <Wall
      :title="$i18n('region.public.wall')"
      target="bezirk"
      :target-id="id"
      :allow-image-attachments="false"
    />
    <PublicEventsContainer :events="regionData.events" />

    <b-alert
      show
      variant="warning"
      class="only-if-first"
    >
      <i class="fas fa-eye-slash mr-2" />
      {{ $i18n('region.public.no_more_info') }}
    </b-alert>
  </BasePage>
  <div v-else class="px-2">
    <b-skeleton
      width="100%"
      height="10em"
      class="mb-4"
    />
    <b-row>
      <b-col lg="4" class="mb-4">
        <b-skeleton width="85%" />
        <b-skeleton width="65%" />
        <b-skeleton width="70%" />
      </b-col>
      <b-col lg="8">
        <b-skeleton width="85%" />
        <b-skeleton width="65%" />
        <b-skeleton width="70%" />
      </b-col>
    </b-row>
  </div>
</template>
<script>
import Wall from '@/components/Wall/Wall.vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { ACCESSIBLE_REGION_TYPES, useRegionStore } from '@/stores/regions'
import { useUserStore } from '@/stores/user'
import BasePage from '@/views/pages/Layout/BasePage.vue'
import Breadcrumbs from '@/views/partials/Navigation/Breadcrumbs.vue'
import RegionSideNav from '@php/Modules/Region/components/RegionSideNav.vue'
import RegionChildrenContainer from './RegionChildrenContainer.vue'
import RegionMap from './RegionMap.vue'
import SimpleRegionStatistics from './SimpleRegionStatistics.vue'
import InaccessibleRegionRedirectWarning from '@/components/InaccessibleRegionRedirectWarning.vue'
import PublicRegionTopBanner from './PublicRegionTopBanner.vue'
import PublicRegionContactContainer from './PublicRegionContactContainer.vue'
import JoinRegionContainer from './JoinRegionContainer.vue'
import LeaveRegionContainer from './LeaveRegionContainer.vue'
import PublicRegionDescriptionContainer from './PublicRegionDescriptionContainer.vue'
import PublicEventsContainer from './PublicEventsContainer.vue'

const userStore = useUserStore()
const regionStore = useRegionStore()

export default {
  components: { BasePage, Breadcrumbs, RegionMap, SimpleRegionStatistics, RegionSideNav, RegionChildrenContainer, Wall, InaccessibleRegionRedirectWarning, PublicRegionTopBanner, PublicRegionContactContainer, JoinRegionContainer, LeaveRegionContainer, PublicRegionDescriptionContainer, PublicEventsContainer },
  props: {
    id: { type: Number, required: true },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  data: () => ({
    regionData: null,
    showWall: false,
    regionMenu: null,
    loading: false,
  }),
  computed: {
    breadcrumbs () {
      if (!this.regionData) return []
      const breadcrumbs = this.regionData.ancestors.map(region => ({ href: this.$url('publicRegion', region.id), text: region.name }))
      breadcrumbs.push({ text: this.regionData.name })
      breadcrumbs.unshift({ href: this.$url('communities'), text: this.$i18n('content.communities.title') })
      return breadcrumbs
    },
    isLoggedIn () {
      return userStore.isLoggedIn
    },
    isFoodsaver () {
      return userStore.isFoodsaver
    },
    isRegionMember () {
      return regionStore.regions.some(region => region.id === this.id)
    },
    mayJoinRegion () {
      return this.isLoggedIn &&
        this.isFoodsaver &&
        !this.isRegionMember &&
        ACCESSIBLE_REGION_TYPES.includes(this.regionData.type)
    },
    mayEditData () {
      return userStore.isOrga || (this.isRegionMember && this.regionMenu?.maySetRegionPin)
    },
    mayAccessRegion () {
      return this.isRegionMember || userStore.isOrga
    },
  },
  async created () {
    this.regionData = await regionStore.fetchPublicRegionData(this.id)
    document.title += ' | ' + this.regionData.name
    if (this.regionData.email) {
      // move to the text-based url without reload if the email is set
      history.replaceState(null, '', `/region/${this.regionData.email}`)
    }
    if (this.mayAccessRegion) {
      this.regionMenu = await regionStore.fetchRegionMenu(this.id)
    }
  },
}
</script>
<style lang="scss" scoped>
.only-if-first:not(:first-child) {
  display: none;
}
</style>
