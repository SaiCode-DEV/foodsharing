<template>
  <div>
    <div
      v-if="isEmpty && !isLoading"
      class="alert alert-warning"
    >
      {{ $t('search.noresults') }}
    </div>

    <div
      v-for="section in resultSections"
      :key="section.key"
      class="entry"
    >
      <h3 class="dropdown-header">
        <i :class="`icon-subnav fas fa-${section.icon}`" /> {{ $t(`globals.type.${section.key}`) }}
      </h3>
      <component
        :is="section.component"
        v-for="(entity, i) in shownResults[section.key]"
        :id="`${section.key}-${i}`"
        :key="entity.id"
        :[section.prop]="entity"
        @close="$emit('close')"
      />
      <div
        v-if="hasMaxSearchResultCount[section.key] && expanded[section.key]"
        class="alert alert-warning my-1"
      >
        {{ $t('search.maxresults') }}
      </div>
      <div
        v-if="toggleButtonVisibility[section.key]"
        class="mt-2"
      >
        <button
          :id="`toggle-${section.key}`"
          tabindex="1"
          class="list-group-item btn-sm list-group-item-action font-weight-bold text-center p-1"
          :class="{'list-group-item-secondary': !expanded[section.key]}"
          @click="toggleExpanded(section.key)"
          @keyup.enter="setFocusAfterButtonPress(section.key)"
          v-text="$t(expanded[section.key] ? 'globals.show_less' : 'globals.show_more')"
        />
      </div>
    </div>
  </div>
</template>

<script>
import UserResultEntry from './ResultEntry/UserResultEntry'
import WorkingGroupResultEntry from './ResultEntry/WorkingGroupResultEntry'
import RegionResultEntry from './ResultEntry/RegionResultEntry'
import StoreResultEntry from './ResultEntry/StoreResultEntry'
import FoodSharePointResultEntry from './ResultEntry/FoodSharePointResultEntry'
import ChatResultEntry from './ResultEntry/ChatResultEntry'
import ThreadResultEntry from './ResultEntry/ThreadResultEntry'
import MailResultEntry from './ResultEntry/MailResultEntry'
import EventResultEntry from './ResultEntry/EventResultEntry'
import PollResultEntry from './ResultEntry/PollResultEntry'
import { objectMap } from '@/utils'

const MAX_SEARCH_RESULT_COUNT = 30
const MAX_DISPLAYED_RESULTS_REDUCED = 4

export default {
  components: { UserResultEntry, WorkingGroupResultEntry, RegionResultEntry, StoreResultEntry, FoodSharePointResultEntry, ChatResultEntry, ThreadResultEntry, MailResultEntry, EventResultEntry, PollResultEntry },
  props: {
    results: {
      type: Object,
      default: () => ({}),
    },
    query: { type: String, default: '' },
    isLoading: { type: Boolean, default: true },
  },
  data () {
    return {
      possibleResultSections: [
        { key: 'stores', icon: 'shopping-cart', component: StoreResultEntry, prop: 'store' },
        { key: 'users', icon: 'user', component: UserResultEntry, prop: 'user' },
        { key: 'regions', icon: 'globe', component: RegionResultEntry, prop: 'region' },
        { key: 'workingGroups', icon: 'users', component: WorkingGroupResultEntry, prop: 'workingGroup' },
        { key: 'threads', icon: 'comments', component: ThreadResultEntry, prop: 'thread' },
        { key: 'events', icon: 'calendar-alt', component: EventResultEntry, prop: 'event' },
        { key: 'polls', icon: 'poll-h', component: PollResultEntry, prop: 'poll' },
        { key: 'chats', icon: 'comment', component: ChatResultEntry, prop: 'chat' },
        { key: 'mails', icon: 'envelope', component: MailResultEntry, prop: 'mail' },
        { key: 'foodSharePoints', icon: 'recycle', component: FoodSharePointResultEntry, prop: 'foodSharePoint' },
      ],
      expanded: objectMap(this.results, key => false),
    }
  },
  computed: {
    isEmpty () {
      return Object.values(this.results).every(value => !value.length)
    },
    hasMaxSearchResultCount () {
      return objectMap(this.results, list => list.length >= MAX_SEARCH_RESULT_COUNT)
    },
    resultSections () {
      return this.possibleResultSections.filter(section => this.results[section.key]?.length ?? 0)
    },
    shownResults () {
      return objectMap(this.results, (list, key) => this.expanded[key] ? list : list.slice(0, MAX_DISPLAYED_RESULTS_REDUCED))
    },
    toggleButtonVisibility () {
      return objectMap(this.results, list => list.length > MAX_DISPLAYED_RESULTS_REDUCED)
    },
  },
  methods: {
    toggleExpanded (key) {
      this.expanded[key] = !this.expanded[key]
    },
    async setFocusAfterButtonPress (key) {
      if (this.expanded[key]) document.getElementById(`${key}-${MAX_DISPLAYED_RESULTS_REDUCED}`).focus()
    },
  },
}
</script>

<style lang="scss" scoped>
@import '../../scss/icon-sizes.scss';

.entry:not(:last-child) {
  padding-bottom: 1rem;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--fs-border-default);
}

.entry ::v-deep .btn {
  height: fit-content;
  padding-top: 4px;
  padding-bottom: 4px;
}

.list-group-item:focus{
  outline: none;
}

</style>
