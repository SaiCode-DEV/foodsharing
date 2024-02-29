<template>
  <Container :title="$i18n('polls.ongoing')">
    <b-container>
      <div class="alert alert-info mb-3 mt-3">
        <i class="fas fa-info-circle" />
        {{ $i18n('polls.hint') }}<br>
        {{ $i18n('polls.hint_2') }}: <a :href="$url('wiki_voting')">{{ $url('wiki_voting') }}</a>
      </div>
    </b-container>
    <b-container>
      <div v-if="mayCreatePoll" class="p-1">
        <b-link
          :href="$url('pollNew', regionId)"
          class="btn btn-sm btn-primary btn-block"
        >
          {{ $i18n('polls.new_poll') }}
        </b-link>
      </div>
    </b-container>
    <b-container>
      <b-list-group>
        <div class="d-flex flex-wrap">
          <b-list-group-item
            v-for="poll in ongoingPolls"
            :key="poll.id"
            class="mr-3 mb-3"
            style="flex-basis: 46%"
          >
            <b-link :href="$url('poll', poll.id)">
              <span class="calendar m-1">
                <span class="month">{{ formatDate(convertDate(poll.endDate.date), 'MMMM') }}</span>
                <span class="day">{{ formatDate(convertDate(poll.endDate.date), 'd') }}</span>
              </span>
              <div class="title mt-2">
                <b>{{ poll.name }}</b>
              </div>
              <div class="mt-2">
                {{ $dateFormatter.dateTime(convertDate(poll.startDate.date)) }} - {{ $dateFormatter.dateTime(convertDate(poll.endDate.date)) }}
              </div>
            </b-link>
          </b-list-group-item>
        </div>
      </b-list-group>
    </b-container>
    <b-container>
      <div
        v-if="futurePolls.length > 0"
        class="card mb-3 rounded"
      >
        <div class="card-header text-white bg-primary">
          {{ $i18n('polls.future') }}
        </div>
        <div class="card-body">
          <ul>
            <li
              v-for="poll in futurePolls"
              :key="poll.id"
              class="mb-2"
            >
              <b-link
                :href="$url('poll', poll.id)"
              >
                <b>{{ poll.name }}</b>
                <div>{{ $i18n('poll.begins_at') }}: {{ $dateFormatter.date(convertDate(poll.startDate.date)) }}</div>
              </b-link>
            </li>
          </ul>
        </div>
      </div>
    </b-container>
    <b-container>
      <div class="card mb-3 rounded">
        <div class="card-header text-white bg-primary">
          {{ $i18n('polls.ended') }}
        </div>
        <div class="card-body">
          <div class="form-row p-1 mb-2">
            <label
              for="filter-input"
              class="col-form-label col-form-label-sm"
            >
              {{ $i18n('filter_by') }}
            </label>
            <b-form-input
              id="filter-input"
              v-model="filterText"
              type="text"
              class="form-control form-control-sm col-8"
              :placeholder="$i18n('name')"
            />
          </div>
          <ul id="endedPollsList">
            <li
              v-for="poll in endedPollsPaginated"
              :key="poll.id"
              class="mb-2"
            >
              <b-link
                :href="$url('poll', poll.id)"
              >
                <b>{{ poll.name }}</b>
                <div>{{ $i18n('poll.ended_at') }} {{ $dateFormatter.date(convertDate(poll.endDate.date)) }}</div>
              </b-link>
            </li>
          </ul>
          <b-pagination
            v-model="currentPage"
            :total-rows="endedPolls.length"
            :per-page="perPage"
            aria-controls="endedPollsList"
            class="my-0"
          />
        </div>
      </div>
    </b-container>
  </Container>
</template>

<script>
import { BLink, BFormInput, BPagination } from 'bootstrap-vue'
import { optimizedCompare } from '@/utils'
import GroupsData from '@/stores/groups'
import Container from '@/components/Container/Container.vue'

export default {
  components: { BLink, BFormInput, BPagination, Container },
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
      return GroupsData.getters.getPolls(this.regionId)
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

      return filtered.sort((a, b) => {
        const aD = this.convertDate(a.endDate.date)
        const bD = this.convertDate(b.endDate.date)
        if (aD.getTime() === bD.getTime()) return 0
        return aD > bD ? -1 : 1
      })
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
      return this.convertDate(poll.endDate.date) < new Date()
    },
    isPollInFuture (poll) {
      return this.convertDate(poll.startDate.date) > new Date()
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
  },
}
</script>

<style lang="scss" scoped>
.btn {
  width: 200px;
}
</style>
