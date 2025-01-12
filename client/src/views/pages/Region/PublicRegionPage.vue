<template>
  <BasePage v-if="regionData" wide-cols>
    <template #top>
      <Breadcrumbs :items="breadcrumbs" />
      <InaccessibleRegionRedirectWarning />
      <b-alert
        show
        variant="success"
        class="d-flex"
      >
        <div class="flex-grow-1 align-self-center">
          <h1>
            <span class="logo">
              <span>food</span><span>sharing</span>
            </span>
            {{ regionData.name }}
          </h1>
        </div>
        <div>
          <Fork style="height: 8em; float: right; margin: -1em;" />
        </div>
      </b-alert>
    </template>

    <template #left>
      <RegionSideNav
        v-if="isRegionMember && regionMenu"
        :region-menu="regionMenu"
        :is-work-group="false"
      />

      <RegionChildrenContainer :children="regionData.children" :name="regionData.name" />

      <SimpleRegionStatistics v-if="regionData.statistics" :statistics="regionData.statistics" />

      <Container
        :title="$i18n('menu.entry.contact')"
        tag="publicRegionContacts"
        wrap-content
      >
        <span v-if="regionData.hasAmbassador">
          <i class="fas fa-envelope mr-2" />
          <a :href="$url('mailto_mail_foodsharing_network', regionData.email)" v-text="$url('mail_foodsharing_network', regionData.email)" />
        </span>
        <span v-else v-text="$i18n('content.communities.noAmbassador')" />
        <!-- TODO show ambassadors to logged in users -->
      </Container>

      <Container
        v-if="mayJoinRegion"
        :title="$i18n('region.public.join_name', regionData)"
        tag="publicRegionJoin"
      >
        <div class="list-group-item">
          Möchtest du in {{ regionData.name }} aktiv werden? Dann kannst du diesem Bezirk beitreten!
        </div>
        <ContainerButton
          variant="success"
          text-key="region.public.join"
          icon="fas fa-plus"
          :disabled="joining"
          @click="join"
        />
      </Container>
    </template>

    <Container
      v-if="regionData.description"
      :title="$i18n('region.public.description')"
      tag="publicRegionDescription"
      wrap-content
    >
      <Markdown v-if="regionData.description" :source="regionData.description" />
    </Container>

    <RegionMap
      v-if="regionData.location || regionData.foodSharePoints.length"
      :location="regionData.location"
      :food-share-points="regionData.foodSharePoints"
    />

    <Wall
      :title="$i18n('region.public.wall')"
      target="bezirk"
      :target-id="id"
      :allow-image-attachments="false"
    />

    <b-alert
      show
      variant="warning"
      class="only-if-first"
    >
      <i class="fas fa-eye-slash mr-2" />
      {{ $i18n('region.public.no_more_info') }}
    </b-alert>
  </BasePage>
</template>
<script>
import { getPublicRegionData, getRegionMenu, joinRegion } from '@/api/regions'
import Container from '@/components/Container/Container.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import Wall from '@/components/Wall/Wall.vue'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'
import { ACCESSIBLE_REGION_TYPES, useRegionStore } from '@/stores/regions'
import { useUserStore } from '@/stores/user'
import BasePage from '@/views/pages/Layout/BasePage.vue'
import Breadcrumbs from '@/views/partials/Navigation/Breadcrumbs.vue'
import RegionSideNav from '@php/Modules/Region/components/RegionSideNav.vue'
import Fork from '../Index/fork.vue'
import RegionChildrenContainer from './RegionChildrenContainer.vue'
import RegionMap from './RegionMap.vue'
import SimpleRegionStatistics from './SimpleRegionStatistics.vue'
import InaccessibleRegionRedirectWarning from '@/components/InaccessibleRegionRedirectWarning.vue'

const userStore = useUserStore()
const regionStore = useRegionStore()

export default {
  components: { BasePage, Breadcrumbs, Container, Fork, Markdown, RegionMap, SimpleRegionStatistics, RegionSideNav, RegionChildrenContainer, Wall, ContainerButton, InaccessibleRegionRedirectWarning },
  mixins: [ConfirmationDialogue],
  props: {
    id: { type: Number, required: true },
  },
  data: () => ({
    regionData: null,
    showWall: false,
    regionMenu: null,
    joining: false,
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
  },
  async created () {
    this.regionData = await getPublicRegionData(this.id)
    document.title += ' | ' + this.regionData.name
    if (this.isRegionMember) {
      this.regionMenu = await getRegionMenu(this.id)
    }
  },
  methods: {
    async join () {
      if (!await this.confirmationDialogue('region.public.confirm_entering', {
        okTitle: this.$i18n('region.public.join'),
        okVariant: undefined,
        params: { name: this.regionData.name },
      })) return
      this.joining = true
      await joinRegion(this.id)
      const locationWithoutDenied = location.href.substring(location.origin.length).replace(/&?denied=\d+/, '')
      location.href = this.$url('relogin_and_redirect_to_url', locationWithoutDenied)
    },
  },
}
</script>
<style lang="scss" scoped>
.logo span:first-child {
  color: var(--fs-color-primary-500);
}
.logo span:last-child {
  color: var(--fs-color-secondary-500);
}
.only-if-first:not(:first-child) {
  display: none;
}
</style>
