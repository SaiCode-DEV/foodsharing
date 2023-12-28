<template>
  <b-modal
    id="modal_open_addressbook"
    ref="modal_open_addressbook"
    :title="$i18n('mailbox.global_addressbook')"
    hide-footer
    header-class="d-flex"
    content-class="pr-3 pt-3"
    size="xl"
    scrollable
  >
    <div>{{ markedAsSelected.length }}, {{ markedAsSelected }}</div>
    <b-button-group class="mb-2">
      <b-button
        :variant="getButtonVariant(MAILBOX_ADDRESSBOOK_FILTER_TYPES.GROUPS)"
        @click="updateFilter(MAILBOX_ADDRESSBOOK_FILTER_TYPES.GROUPS)"
      >
        {{ $i18n('terminology.groups') }}
      </b-button>
      <b-button
        :variant="getButtonVariant(MAILBOX_ADDRESSBOOK_FILTER_TYPES.REGIONS)"
        @click="updateFilter(MAILBOX_ADDRESSBOOK_FILTER_TYPES.REGIONS)"
      >
        {{ $i18n('terminology.regions') }}
      </b-button>
    </b-button-group>
    <b-form-input
      v-model="filterName"
      :placeholder="$i18n('mailbox.search_name_email')"
      class="mb-2"
    />
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
        const typeMatch = region.type === typeFilter || typeFilter === 0
        const nameMatch = !nameFilter || region.name.toLowerCase().includes(nameFilter.toLowerCase())
        return typeMatch && nameMatch
      })
    },
  },
  methods: {
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
