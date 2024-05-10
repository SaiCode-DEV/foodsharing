<template>
  <div class="container bg-white">
    <div
      v-for="(badgeItem, index) in badges"
      :id="badgeItem.id"
      :key="'badge_' + index"
      class="d-inline mr-2"
    >
      <div :id="badgeItem.id" class="customBadge">
        <a
          v-if="badgeItem.link"
          href="#"
          @click="badgeItem.link"
        >
          <span
            class="item mb-4 mr-3"
            :class="{
              'bananaCount': badgeItem.id === 'bananas' && isMe,
              'bananaCountAdd': badgeItem.id === 'bananas' && !isMe
            }"
          >
            <span class="value mb-4">{{ badgeItem.value }}</span>
          </span>
        </a>
        <span v-else class="item mb-4 mr-3">
          <span class="value">{{ badgeItem.value }}</span>
          <span class="text">{{ badgeItem.text }}</span>
        </span>
      </div>
    </div>

    <div v-for="section in sections" :key="section.id">
      <div v-if="section.value.length > 0">
        <h4 class="mb-2 mt-4">
          {{ section.title }}
        </h4>
        <div
          class="d-inline d-flex flex-wrap flex-row"
          style="gap: 10px"
        >
          <a
            v-for="item in section.value"
            :key="item.id"
            :href="$url('region', item.id)"
            class="badge sectionClass"
          >
            {{ item.name }}
          </a>
        </div>
      </div>
    </div>

    <div v-if="homeRegionName">
      <h4 class="mb-2 mt-4">
        {{ $i18n('profile.sections.home_region_from') }}:
      </h4>
      <div class="d-inline d-flex flex-wrap flex-row">
        <a
          :href="$url('region', homeRegionId)"
          class="badge sectionClass"
        >{{ homeRegionName }}</a>
        <span v-if="homeDistrictHistory.length > 0">(
          <a
            :href="$url('profile', homeDistrictHistory.homeDistrictHistoryChangerId)"
          >
            {{ homeDistrictHistory.homeDistrictHistoryChangerFullName }}</a>
          {{ $dateFormatter.date(homeDistrictHistory.homeDistrictHistoryDate, {type: 'full'}) }})</span>
      </div>
    </div>

    <div v-if="isOrgUser" class="mt-4">
      <Markdown :source="$i18n('profile.sections.orga_rights', { wikiurl: 'https://wiki.foodsharing.de/Benutzerrolle_:_Orga' })" />
    </div>

    <div v-if="aboutMeIntern">
      <h4 class="mb-2 mt-4">
        {{ $i18n('profile.about_me_intern') }}:
      </h4>
      {{ aboutMeIntern }}
    </div>
    <BananaModal :banana-statistics="bananaStatistics" />
  </div>
</template>

<script>
import DataUser from '@/stores/user'
import BananaModal from '@/components/Modals/Profile/BananaModal.vue'
import { ROLE } from '@/consts'
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  components: { Markdown, BananaModal },
  props: {
    userId: { type: Number, required: true },
    name: { type: String, required: true },
    statistics: { type: Object, required: true },
    bananaStatistics: { type: Object, required: true },
    ambassadorRegions: { type: Array, required: true },
    foodSaverRegions: { type: Array, required: true },
    aboutMeIntern: { type: String, required: true },
    workingGroups: { type: Array, required: true },
    workingGroupsAdmins: { type: Array, required: true },
    sleepingInformation: { type: Object, required: true },
    homeDistrictHistory: { type: Object, required: true },
    role: { type: Number, required: true },
  },
  data () {
    return {
      sections: [
        { id: 1, title: this.$i18n('profile.sections.ambassador_for'), value: this.ambassadorRegions },
        { id: 2, title: this.$i18n('profile.sections.foodSaver_in_region'), value: this.foodSaverRegions },
        { id: 3, title: this.$i18n('profile.sections.workgroups_member'), value: this.workingGroups },
        { id: 4, title: this.$i18n('profile.sections.workgroups_admin'), value: this.workingGroupsAdmins },
      ],
    }
  },
  computed: {
    badges () {
      return [
        { id: 'posts', text: this.$i18n('profile.stats.posts'), value: this.statistics.postCount.toString() },
        { id: 'fetched', text: this.$i18n('profile.stats.fetch_count'), value: this.statistics.fetchCount.toString() },
        { id: 'baskets', text: this.$i18n('profile.stats.baskets'), value: this.statistics.basketCount.toString() },
        { id: 'bananas', text: this.$i18n('profile.stats.bananas'), value: this.bananaStatistics.bananas.length.toString(), link: this.openBananaModal },
        { id: 'saved', text: this.$i18n('profile.stats.weight'), value: this.formatFetchWeight.toString() },
        { id: 'buddies', text: this.$i18n('profile.infos.buddies'), value: this.statistics.buddyCount.toString() },
      ]
    },
    isOrgUser () {
      return this.role === ROLE.ORGA
    },
    homeRegionId () {
      return DataUser.getters.getHomeRegion()
    },
    homeRegionName () {
      return DataUser.getters.getHomeRegionName()
    },
    currentUserId () {
      return DataUser.getters.getUserId()
    },
    isMe () {
      return this.currentUserId === this.userId
    },
    formatFetchWeight () {
      const value = parseFloat(this.statistics.fetchWeight)
      return value.toFixed(0)
    },
  },
  methods: {
    openBananaModal () {
      this.$bvModal.show('BananaModal')
    },
  },
}
</script>

<style lang="scss" scoped>
.customBadge {
  position: relative;
  float: right;
  display: contents;
  width: 100%;
  margin-bottom: -5rem;
  padding-bottom: 2rem;
}

.customBadge .item {
  float: right;
  display: flex;
  margin-right: 8px;
  border-radius: 50%;
  background-color: var(--fs-color-secondary-500);
  text-align: center;
  min-width: 7em;
  min-height: 7em;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: var(--fs-color-white);
}

div.customBadge .item .value {
  font-size: 1.2em;;
  font-weight: bold;
  display: block;
}

div.customBadge .item .text {
  font-size: 1em;
  display: block;
  margin: 0 0 14px 0;
}

div.customBadge .item a {
  color: var(--fs-color-white);
  font-weight: initial;
}

div.customBadge .bananaCount {
  background: var(--fs-color-secondary-500) url(/img/bananan.png) no-repeat center 3.5em;
}

div.customBadge .bananaCountAdd {
  background: var(--fs-color-secondary-500) url(/img/banana.png) no-repeat center 3.5em;
}

.sectionClass {
  color: var(--fs-color-gray-600);
  font-size: medium;
  border: 1px solid #ccc;
  background-color: transparent;
  padding: 8px;
  border-radius: 10rem;
}
</style>
