<template>
  <div>
    <b-alert show variant="info">
      <i class="fas fa-info-circle" />
      {{ $t('polls.hint') }}<br>
      {{ $t('polls.hint_2') }}: <a :href="$url('wiki_voting')">{{ $url('wiki_voting') }}</a>
    </b-alert>
    <Container v-if="ongoingPolls.length || mayCreatePoll" :title="$t('polls.ongoing')">
      <PollListEntry
        v-for="poll in ongoingPolls"
        :key="poll.id"
        :poll="poll"
      />
      <template #buttons>
        <ContainerButton
          v-if="mayCreatePoll"
          variant="success"
          text-key="polls.new_poll"
          :href="$url('pollNew', regionId)"
        />
      </template>
    </Container>
    <Container v-if="futurePolls.length > 0" :title="$t('polls.future')">
      <PollListEntry
        v-for="poll in futurePolls"
        :key="poll.id"
        :poll="poll"
      />
    </Container>
    <Container :title="$t('polls.ended')">
      <b-list-group-item>
        <b-form-group :label="$t('filter_by')">
          <b-form-input
            v-model="filterText"
            type="text"
            class="form-control form-control-sm col-8"
            :placeholder="$t('name')"
          />
        </b-form-group>
      </b-list-group-item>
      <PollListEntry
        v-for="poll in endedPollsPaginated"
        :key="poll.id"
        :poll="poll"
      />
      <b-list-group-item v-if="endedPolls.length > perPage" class="align-right">
        <b-pagination
          v-model="currentPage"
          :total-rows="endedPolls.length"
          :per-page="perPage"
          aria-controls="endedPollsList"
          class="m-0"
        />
      </b-list-group-item>
    </Container>
  </div>
</template>

<script>
import { optimizedCompare } from '@/utils'
import GroupsData from '@/stores/groups'
import Container from '@/components/Container/Container.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import PollListEntry from './PollListEntry.vue'

export default {
  components: { Container, ContainerButton, PollListEntry },
  props: {
    regionId: {
      type: Number,
      required: true,
    },
    mayCreatePoll: {
      type: Boolean,
      default: false,
    },
  },
  data () {
    return {
      currentPage: 1,
      perPage: 20,
      filterText: null,
    }
  },
  computed: {
    polls () {
      return this.sortPolls(GroupsData.getters.getPolls(this.regionId))
    },
    ongoingPolls: function () {
      return this.polls.filter(p => !this.isPollInFuture(p) && !this.isPollInPast(p))
    },
    futurePolls: function () {
      return this.polls.filter(p => this.isPollInFuture(p))
    },
    endedPolls: function () {
      // select polls that ended in the past, filter by name, and sort as new-to-old
      let filtered = this.polls.filter(p => this.isPollInPast(p))

      const filterText = this.filterText ? this.filterText.toLowerCase() : null
      if (filterText) {
        filtered = filtered.filter(p => p.name.toLowerCase().indexOf(filterText) !== -1)
      }

      return filtered
    },
    endedPollsPaginated: function () {
      return this.endedPolls.slice(
        (this.currentPage - 1) * this.perPage,
        this.currentPage * this.perPage,
      )
    },
  },
  created () {
    GroupsData.mutations.listPolls(this.regionId)
  },
  methods: {
    compare: optimizedCompare,
    isPollInPast (poll) {
      return this.convertDate(poll.endDate) < new Date()
    },
    isPollInFuture (poll) {
      return this.convertDate(poll.startDate) > new Date()
    },
    convertDate (date) {
      return new Date(Date.parse(date))
    },
    formatDate (date, formatStr) {
      if (formatStr === 'MMMM') {
        return this.$dateFormatter.format(date, { month: 'long' })
      }

      return date.getDate()
    },
    sortPolls (pollsToSort) {
      // Return a sorted copy of the polls list, newest first
      return pollsToSort.toSorted((a, b) => this.convertDate(b.endDate) - this.convertDate(a.endDate))
    },
  },
}
</script>

<style lang="scss" scoped>
.btn {
  width: 200px;
}
</style>
