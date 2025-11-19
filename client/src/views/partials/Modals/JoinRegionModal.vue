<template>
  <b-modal
    id="joinRegionModal"
    ref="joinRegionModal"
    modal-class="testing-region-join"
    :title="$t('join_region.headline')"
    :cancel-title="$t('globals.close')"
    :ok-title="$t('globals.save')"
    :ok-disabled="regionIsInValid"
    @show="showModal"
    @hidden="resetModal"
    @ok="joinRegion"
  >
    <div class="description">
      <Markdown :source="$t('join_region.description', {href: $url('wiki_create_region'), mail: $url('mailto_mail_foodsharing_network', 'welcome')})" />
    </div>
    <hr>
    <div class="selector">
      <select
        v-model="selected[0]"
        class="testing-region-join-select custom-select"
        @change="updateSelected(0)"
      >
        <option
          :value="0"
          v-text="$t('globals.select')"
        />
        <option
          v-for="(entry, key) in base"
          :key="key"
          :value="entry.id"
          v-text="entry.name"
        />
      </select>
      <select
        v-for="(region, listId) in regionsList"
        :key="listId"
        v-model="selected[listId + 1]"
        class="custom-select"
        @change="updateSelected(listId + 1)"
      >
        <option
          :value="null"
          v-text="$t('globals.select')"
        />
        <option
          v-for="(entry, key) in region.list"
          :key="key"
          :value="entry.id"
          v-text="entry.name"
        />
      </select>
    </div>
    <div
      v-if="regionIsInValid && selectedRegionType"
      class="alert alert-danger d-flex align-items-center"
    >
      <i class="icon icon--big fas fa-exclamation-triangle" />
      <span v-if="selectedRegionType === 5">
        <strong>{{ $t('join_region.error.is_state_1') }}</strong><br>
        {{ $t('join_region.error.is_state_2') }}
      </span>
      <span v-if="selectedRegionType === 6">
        <strong>{{ $t('join_region.error.is_country_1') }}</strong><br>
        {{ $t('join_region.error.is_country_2') }}
      </span>
      <span v-if="selectedRegionType === 8">
        <strong>{{ $t('join_region.error.is_big_city_1') }}</strong><br>
        {{ $t('join_region.error.is_big_city_2') }}
      </span>
    </div>
  </b-modal>
</template>

<script>
// Stores
import { REGION_UNIT_TYPE, useRegionStore } from '@/stores/regions'
// Others
import { pulseError, showLoader, hideLoader } from '@/script'
import { REGION_IDS } from '@/consts'
import { useUserStore } from '@/stores/user'
import Markdown from '@/components/Markdown/Markdown.vue'

const EXCLUDED_REGIONS = [REGION_IDS.GLOBAL_WORKING_GROUPS]
const EXCLUDED_REGIONS_WITHOUT_HOME = [REGION_IDS.FOODSHARING_ON_FESTIVALS]

export default {
  name: 'JoinRegionModal',
  components: { Markdown },
  setup () {
    const userStore = useUserStore()
    const regionStore = useRegionStore()
    return {
      userStore,
      regionStore,
    }
  },
  data () {
    return {
      selected: [0],
      regions: [],
      base: [],
    }
  },
  /*
    1: Stadt
    2: Bezirk
    3: Region
    5: Bundesland
    6: Land
    7: Arbeitsgruppe
    8: Großstadt
    9: Stadtteil
  */
  computed: {
    regionIsInValid () {
      return ![1, 9, 2, 3].includes(this.selectedRegionType)
    },
    selectedRegionList () {
      return this.selected
    },
    selectedRegionType () {
      return this.selectedRegion?.type
    },
    selectedRegion () {
      const regions = [...this.base]
      this.regions.map(region => region.list).forEach(r => regions.push(...r))
      const last = this.selected[this.selected.length - 1]
      return regions.find(region => region.id === last)
    },
    regionsList () {
      return this.regions
        .filter(region => this.selectedRegionList.includes(region.id) && region.list.length > 0)
    },
  },
  methods: {
    async updateSelected (index) {
      this.selected.length = index + 1

      for (let i = 0; i < index + 1; i++) {
        const id = this.selected[i]
        const region = this.regions.find(r => r.id === id)
        if (id && !region) {
          await this.regionStore.fetchSelectedRegionChildren(id)
          const list = this.filterRegions(this.regionStore.selectedRegionChildren)

          if (list.length > 0) {
            this.regions.push({ id, list })
          }
        } else if (id === null) {
          this.selected.length = index
        }
      }
    },
    async joinRegion () {
      try {
        showLoader()
        await this.regionStore.joinRegion(this.selectedRegion.id)
      } catch (err) {
        console.log(err)
        pulseError('In diesen Bezirk kannst Du Dich nicht eintragen.')
      } finally {
        hideLoader()
      }
    },
    async showModal () {
      await this.regionStore.fetchSelectedRegionChildren(0)
      this.selected = [0]
      this.base = this.filterRegions(this.regionStore.selectedRegionChildren)
    },
    async resetModal () {
      this.selected = [0]
    },
    filterRegions (regions) {
      // Remove all working groups and all excluded regions
      let filtered = regions
        .filter(r => r.type !== REGION_UNIT_TYPE.WORKING_GROUP)
        .filter(r => EXCLUDED_REGIONS.indexOf(r.id) < 0)

      // Remove all regions that are only shown if the user has a home region
      if (!this.userStore.hasHomeRegion) {
        filtered = filtered.filter(r => EXCLUDED_REGIONS_WITHOUT_HOME.indexOf(r.id) < 0)
      }
      return filtered
    },
  },
}
</script>

<style lang="scss" scoped>
.selector select {
    margin-bottom: 0.25rem;
}
</style>
