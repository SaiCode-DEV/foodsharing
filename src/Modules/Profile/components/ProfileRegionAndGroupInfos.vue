<template>
  <div class="container bg-white">
    <div class="row justify-content-center">
      <div
        v-for="(badgeItem, index) in filteredBadges"
        :id="badgeItem.id"
        :key="'badge_' + index"
        class="d-inline mr-2"
      >
        <div
          :id="badgeItem.id"
          class="customBadge"
        >
          <a
            v-if="badgeItem.link"
            href="#"
            @click="badgeItem.link"
          >
            <span
              v-if="badgeItem.id === 'bananas'"
              class="item mb-4 mr-3"
              :class="{
                'bananaCount': badgeItem.id === 'bananas',
                'bananaCountAdd': badgeItem.id === 'bananas' && bananaData?.mayGiveBanana,
              }"
            >
              <span class="value mb-4">{{ badgeItem.value }}</span>
            </span>
            <span v-else class="item mb-4 mr-3">
              <span class="value">{{ badgeItem.value }}</span>
              <span class="text">{{ badgeItem.text }}</span>
            </span>
          </a>
          <span v-else class="item mb-4 mr-3">
            <span class="value">{{ badgeItem.value }}</span>
            <span class="text">{{ badgeItem.text }}</span>
          </span>
        </div>
      </div>
    </div>

    <div v-if="homeRegionName">
      <h5 class="mb-2 mt-4">
        {{ $i18n('profile.sections.home_region_from') }}:
      </h5>
      <div class="d-inline d-flex flex-wrap flex-row">
        <div class="sectionClass">
          <a :href="$url('publicRegion', homeRegionId)" v-text="homeRegionName" />
          <span v-if="homeDistrictHistory.changerFullName">
            ({{ $i18n('profile.homeDistrictHistory.changed') }}
            {{ $dateFormatter.date(homeDistrictHistory.date, {type: 'full'}) }}
            {{ $i18n('profile.homeDistrictHistory.by') }}
            <a :href="$url('profile', homeDistrictHistory.changerId)" v-text="homeDistrictHistory.changerFullName" /><!--
         --><span v-if="homeDistrictHistory.previousRegionId">,
              {{ $i18n('profile.homeDistrictHistory.previous') }}
              <a :href="$url('publicRegion', homeDistrictHistory.previousRegionId)" v-text="homeDistrictHistory.previousRegionName" />
            </span>)
          </span>
        </div>
      </div>
    </div>

    <div v-for="section in sections" :key="section.id">
      <div v-if="section.value.length > 0 && !(isMe && section.id === 'JOINT_WORK_GROUPS')">
        <h5 class="mb-2 mt-4">
          {{ section.title }}
        </h5>
        <div class="d-inline d-flex flex-wrap flex-row" style="gap: 5px">
          <a
            v-for="(item, index) in section.value"
            :key="item.id"
            :href="$url(section.isWorkingGroups ? 'wall' : 'publicRegion', item.id)"
            class="sectionClass"
          >
            {{ item.name }}<span v-if="index !== section.value.length - 1">,</span>
          </a>
        </div>
      </div>
    </div>

    <div v-if="kamPositions?.length > 0">
      <h5 class="mb-2 mt-4">
        {{ $i18n('profile.sections.kam_for') }}
      </h5>
      <div class="d-inline d-flex flex-wrap flex-row" style="gap: 5px">
        <span v-for="(item, index) in kamPositions" :key="item.id">
          {{ item.name }}<span v-if="index !== kamPositions.length - 1">,</span>
        </span>
      </div>
    </div>

    <div v-if="sleepingInformation.isSleeping > 0">
      <h5 class="mb-2 mt-4">
        <span v-if="sleepingInformation.sleepStatus === SLEEP_STATUS.TEMP">
          {{
            $i18n('profile.sleeping_info_from_until', {
              from: $dateFormatter.format(new Date(sleepingInformation.sleepFrom * 1000), { day: '2-digit', month: '2-digit', year: 'numeric' }),
              until: $dateFormatter.format(new Date(sleepingInformation.sleepUntil * 1000), { day: '2-digit', month: '2-digit', year: 'numeric' })
            })
          }}
        </span>
        <span v-if="sleepingInformation.sleepStatus === SLEEP_STATUS.FULL">
          {{ $i18n('profile.sleeping') }}
        </span>
      </h5>
      <div v-if="sleepingInformation.sleepMessage" class="d-inline d-flex flex-wrap flex-row">
        <div class="sectionClass">
          {{ sleepingInformation.sleepMessage }}
        </div>
      </div>
    </div>

    <div v-if="isOrgUser" class="mt-4">
      <Markdown :source="$i18n('profile.sections.orga_rights', { wikiurl: 'https://wiki.foodsharing.de/Benutzerrolle_:_Orga' })" />
    </div>

    <div v-if="aboutMeIntern">
      <h4 class="mb-2 mt-4">
        {{ $i18n('profile.about_me_intern') }}:
      </h4>
      <Markdown :source="aboutMeIntern" />
    </div>
    <BananaModal
      :recipient="{ id: userId, name }"
      :metadata="bananaData"
      @bananas-updated="updateBananas"
    />
    <BuddiesModal
      :buddies="buddiesList"
    />
  </div>
</template>

<script>
import { useUserStore, SLEEP_STATUS } from '@/stores/user'
import BananaModal from '@/components/Modals/Profile/BananaModal.vue'
import BuddiesModal from './BuddiesModal.vue'
import { ROLE } from '@/consts'
import Markdown from '@/components/Markdown/Markdown.vue'
import { getBananaMetadata } from '@/api/banana'
import { locale } from '@/helper/i18n'

const userStore = useUserStore()

const formatNumber = (number, unit) => {
  if (number === undefined || number === null || isNaN(number)) {
    return '0'
  }

  const options = {
    notation: 'compact',
    maximumFractionDigits: 2,
  }

  if (unit === 'kg') {
    options.style = 'unit'
    options.unit = 'kilogram'
    options.unitDisplay = 'narrow'
  }

  return new Intl.NumberFormat(locale, options).format(number)
}

export default {
  components: { Markdown, BananaModal, BuddiesModal },
  props: {
    userId: { type: Number, required: true },
    name: { type: String, required: true },
    statistics: { type: Object, required: true },
    ambassadorRegions: { type: Array, required: true },
    foodSaverRegions: { type: Array, required: true },
    aboutMeIntern: { type: String, required: true },
    workingGroups: { type: Array, required: true },
    workingGroupsAdmins: { type: Array, required: true },
    kamPositions: { type: Array, required: true },
    sleepingInformation: { type: Object, required: true },
    homeDistrictHistory: { type: Object, required: true },
    role: { type: Number, required: true },
    isVerified: { type: Boolean, required: true },
    homeRegionId: { type: Number, required: true },
    homeRegionName: { type: String, default: '' },
  },
  setup () {
    return {
      userStore,
    }
  },
  data () {
    return {
      sections: [
        { id: 'AMBASSADOR_FOR', title: `${this.$i18n('terminology.ambassador.d')} ${this.$i18n('profile.sections.ambassador_for')}`, value: this.ambassadorRegions, isWorkingGroups: false },
        { id: 'FOOD_SAVER_IN_REGION', title: this.$i18n('profile.sections.foodSaver_in_region'), value: this.foodSaverRegions, isWorkingGroups: false },
        { id: 'JOINT_WORK_GROUPS', title: this.$i18n('profile.sections.workgroups_member'), value: this.workingGroups, isWorkingGroups: true },
        { id: 'WORKGROUPS_ADMIN', title: this.$i18n('profile.sections.workgroups_admin'), value: this.workingGroupsAdmins, isWorkingGroups: true },
      ],
      bananaData: null,
    }
  },
  computed: {
    SLEEP_STATUS () {
      return SLEEP_STATUS
    },
    badges () {
      return [
        { id: 'bananas', text: this.$i18n('profile.stats.bananas'), value: this.bananaData?.receivedCount, link: this.openBananaModal },
        { id: 'posts', text: this.$i18n('profile.stats.posts'), value: this.statistics.postCount >= 0 ? formatNumber(this.statistics.postCount) : null },
        { id: 'baskets', text: this.$i18n('profile.stats.baskets'), value: this.statistics.basketCount >= 0 ? formatNumber(this.statistics.basketCount) : null },
        { id: 'fetched', text: this.$i18n('profile.stats.fetch_count'), value: this.statistics.fetchCount >= 0 ? formatNumber(this.statistics.fetchCount) + ' x' : null },
        { id: 'saved', text: this.$i18n('profile.stats.weight'), value: this.formatFetchWeight >= 0.00 ? formatNumber(this.formatFetchWeight) + ' ' + this.$i18n('profile.stats.weight_unit') : null },
        { id: 'buddies', text: this.$i18n('profile.infos.buddies'), value: this.statistics.buddyCount >= 0 ? formatNumber(this.statistics.buddyCount) : null, link: this.isMe ? this.openBuddiesModal : null },
      ]
    },
    filteredBadges () {
      const itemsToFilter = []
      if (!this.isSessionUserFoodsaver) {
        itemsToFilter.push('bananas', 'posts', 'fetched', 'saved')
      }
      return this.badges.filter(badge => !itemsToFilter.includes(badge.id))
    },
    isOrgUser () {
      return this.role === ROLE.ORGA
    },
    currentUserId () {
      return userStore.getUserId
    },
    isMe () {
      return this.currentUserId === this.userId
    },
    isSessionUserFoodsaver () {
      return userStore.isFoodsaver
    },
    formatFetchWeight () {
      const value = parseFloat(this.statistics.fetchWeight)
      return value.toFixed(0)
    },
    buddiesList () {
      // Assuming statistics.buddies is an array of {id, name}
      return this.statistics.buddies || []
    },
  },
  async mounted () {
    this.bananaData = await getBananaMetadata(this.userId)
  },
  methods: {
    openBananaModal () {
      this.$bvModal.show('BananaModal')
    },
    openBuddiesModal () {
      this.$bvModal.show('BuddiesModal')
    },
    updateBananas (bananaCount, mayGiveBanana) {
      if (this.bananaData) {
        this.bananaData = {
          ...this.bananaData,
          receivedCount: bananaCount,
          mayGiveBanana: mayGiveBanana,
        }
      }
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
  font-weight: normal;
}

div.customBadge .item a {
  color: var(--fs-color-white);
  font-weight: initial;
}
div.customBadge .bananaCount > .mb-4 {
  margin-bottom: 2.1rem !important;
}
div.customBadge .bananaCount {
  background: var(--fs-color-secondary-500) url(/img/banana-checkmark.png) no-repeat center 2.5em;
}

div.customBadge .bananaCount.bananaCountAdd {
  background: var(--fs-color-secondary-500) url(/img/banana-plus.png) no-repeat center 2.5em;
}

.sectionClass {
  color: var(--fs-color-gray-600);
  font-size: medium;
  background-color: transparent;
  padding: 0;
}
</style>
