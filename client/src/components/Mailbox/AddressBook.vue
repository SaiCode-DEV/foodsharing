<template>
  <b-modal
    id="modal_open_addressbook"
    ref="modal_open_addressbook"
    :title="$t('mailbox.global_addressbook')"
    hide-footer
    header-class="d-flex"
    content-class="pr-3 pt-3"
    size="xl"
    scrollable
  >
    <b-button-group class="mb-2">
      <b-button
        :variant="getButtonVariant(MAILBOX_ADDRESSBOOK_FILTER_TYPES.GROUPS)"
        @click="updateFilter(MAILBOX_ADDRESSBOOK_FILTER_TYPES.GROUPS)"
      >
        {{ $t('terminology.groups') }}
      </b-button>
      <b-button
        :variant="getButtonVariant(MAILBOX_ADDRESSBOOK_FILTER_TYPES.REGIONS)"
        @click="updateFilter(MAILBOX_ADDRESSBOOK_FILTER_TYPES.REGIONS)"
      >
        {{ $t('terminology.regions') }}
      </b-button>
    </b-button-group>
    <div class="d-flex mb-2">
      <b-form-input
        v-model="filterName"
        :placeholder="$t('mailbox.search_name_email')"
      />
      <b-button
        variant="outline-primary"
        @click="resetFilterName"
      >
        <i class="fas fa-times" />
      </b-button>
    </div>
    <b-list-group>
      <b-list-group-item
        v-for="filteredRegion in filteredRegions"
        :key="filteredRegion.id"
        class="pb-2"
        :class="{ 'selected-item': markedAsSelected.includes(filteredRegion.emailAddress) }"
        href="#"
        @click="$emit('email-selected', filteredRegion.emailAddress)"
      >
        <b>{{ filteredRegion.name }}</b><br>
        {{ filteredRegion.emailAddress }}
      </b-list-group-item>
    </b-list-group>
  </b-modal>
</template>

<script>
import { MAILBOX_ADDRESSBOOK_FILTER_TYPES } from '@/stores/mailbox'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'
import { listRegions } from '@/api/mailbox'

export default {
  props: {
    markedAsSelected: { type: Array, default: () => [] },
  },
  data () {
    return {
      regions: [],
      // regionsByType: {},
      filterName: null,
      filter: { type: MAILBOX_ADDRESSBOOK_FILTER_TYPES.GROUPS, name: null },
    }
  },
  computed: {
    MAILBOX_ADDRESSBOOK_FILTER_TYPES () {
      return MAILBOX_ADDRESSBOOK_FILTER_TYPES
    },
    filteredRegions () {
      const typeFilter = this.filter.type
      const nameFilter = this.filterName

      return this.regions.filter(region => {
        const typeMatch = typeFilter.some(type => type === region.type)
        const nameMatch = !nameFilter ||
          region.name.toLowerCase().includes(nameFilter.toLowerCase()) ||
          region.emailAddress.toLowerCase().includes(nameFilter.toLowerCase())
        return typeMatch && nameMatch
      })
    },
  },
  methods: {
    resetFilterName () {
      this.filterName = null
    },
    getButtonVariant (filterType) {
      return this.filter.type === filterType ? 'primary' : 'secondary'
    },
    updateFilter (type) {
      this.filter.type = type
    },
    async getRegions () {
      const mailboxRegionsRateLimitInterval = 86400000 // 24 hours in Millisekunden
      const cacheRequestName = 'MailboxRegions'
      try {
        if (await getCacheInterval(cacheRequestName, mailboxRegionsRateLimitInterval)) {
          this.regions = await listRegions()

          await setCache(cacheRequestName, this.regions)
        } else {
          this.regions = await getCache(cacheRequestName)
        }
      } catch (e) {
        console.error('Error fetching regions:', e)
      }
    },
    show () {
      this.getRegions()
      this.$refs.modal_open_addressbook.show()
    },
  },
}
</script>

<style lang="scss" scoped>
.selected-item {
  background-color: var(--fs-color-secondary-400);
}
</style>
