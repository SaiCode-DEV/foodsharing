<template>
  <!-- eslint-disable vue/max-attributes-per-line -->
  <Container id="store-log" :title="$i18n('store.log.title')" :container-is-expanded="isContainerExpanded" tag="store_log">
    <div class="corner-bottom margin-bottom bootstrap store-log">
      <DateRangePicker ref="dateRange" :cooperation-start="cooperationStart" :max-age-in-months="6" />

      <Multiselect
        v-model="selectedActionTypes"
        :multiple="true"
        :options="actionTypeOptions"
        :searchable="false"
        :close-on-select="false"
        track-by="id"
        label="name"
        :placeholder="$i18n('store.log.log_types_placeholder')"
        :show-labels="false"
      >
        <template slot="selection" slot-scope="{ values }">
          <span v-if="values.length === 1">
            {{ values[0].name }}
          </span>
          <span v-else-if="values.length">
            {{ $i18n('store.log.selected_log_types', {amount: values.length}) }}
          </span>
        </template>
      </Multiselect>

      <div class="p-1">
        <b-button id="search-store-log" size="sm" class="d-block mx-auto" :disabled="disableSearch" @click="loadStoreLog">
          <i class="fas fa-fw fa-search" />
          {{ $i18n('store.log.search') }}
        </b-button>
      </div>
      <div>
        <div v-for="(action, i) of loggedActions" :key="i" class="store-log-entry d-flex">
          <div class="avatar-time-line">
            <a v-b-tooltip.hover="action.acting_foodsaver.name" :href="$url('profile', action.acting_foodsaver.id)" class="d-inline-block">
              <Avatar :round="true" :url="action.acting_foodsaver.avatar" :auto-scale="false" />
            </a>
          </div>
          <span class="log-entry-content">
            <StoreLogEntryMessage :action="action" />
            <small class="text-muted">({{ $dateFormatter.dateTime(action.performed_at, { weekday: false }) }})</small>
            <blockquote v-if="action.reason" v-text="action.reason" />
            <blockquote v-if="action.content">
              <Markdown :source="action.content" />
            </blockquote>
          </span>
        </div>
        <div v-if="loggedActions.length >= 100" class="alert alert-info">
          <i class="fas fa-info-circle" />
          {{ $i18n('store.log.max_entries_message') }}
        </div>
      </div>
    </div>
  </Container>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import DateRangePicker from './DateRangePicker.vue'
import Multiselect from 'vue-multiselect'
import { getStoreLog } from '@/api/stores'
import Avatar from '@/components/Avatar.vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import { pulseError } from '@/script'
import StoreLogEntryMessage from './StoreLogEntryMessage.vue'

const NUMBER_OF_ACTION_TYPES = 16

export default {
  components: { Container, DateRangePicker, Multiselect, Avatar, Markdown, StoreLogEntryMessage },
  props: {
    collapsedAtFirst: { type: Boolean, default: true },
    storeId: { type: Number, default: null },
    cooperationStart: { type: String, default: null },
  },
  data () {
    const actionTypeIds = [...Array(NUMBER_OF_ACTION_TYPES).keys()].map((id) => id + 1) // action type IDs start at 1
    const actionTypeOptions = actionTypeIds.map((id) => ({ id, name: this.$i18n(`store.log.type.${id}`) }))

    return {
      isContainerExpanded: false,
      isLoading: false,
      selectedActionTypes: [],
      actionTypeOptions,
      loggedActions: [],
    }
  },
  computed: {
    disableSearch () {
      return !this.selectedActionTypes.length || this.isLoading
    },
  },
  methods: {
    async loadStoreLog () {
      this.isLoading = true
      try {
        this.loggedActions = await getStoreLog(
          this.storeId,
          this.selectedActionTypes.map((selected) => selected.id),
          this.$refs.dateRange.getDateRange(),
        )
      } catch (e) {
        pulseError(this.$i18n('error_unexpected') + e)
      }
      this.isLoading = false
    },
  },
}
</script>

<style lang="scss" scoped>
.avatar-time-line, .log-entry-content{
  position: relative;
  padding: 0 .25em 1em 0.25em;
}
.store-log-entry:not(:last-child) .avatar-time-line::before{
  content: "";
  border-right: 2px solid var(--fs-border-default);
  left: calc(50% - 1px);
  height: 100%;
  display: inline-block;
  position: absolute;
}

</style>
