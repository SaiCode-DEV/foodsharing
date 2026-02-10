<template>
  <div>
    <b-skeleton-table
      v-if="!reports"
      :rows="3"
      :columns="4"
    />
    <div v-else-if="reports.length">
      <b-table
        ref="test"
        :fields="fields"
        :items="reports"
        :current-page="currentPage"
        :per-page="perPage"
        @row-clicked="toggleDetails"
      >
        <template #cell(time)="row">
          <Time :time="row.item.reportedAt" :muted="false" />
        </template>
        <template #cell(reported)="row">
          <Avatar :user="row.item.reported" />
          <a v-if="row.item.reported.name" :href="$url('profile', row.item.reported.id)">{{ row.item.reported.name }}</a>
          <span v-else v-text="$t('forum.deleted_user')" />
          <i
            v-if="row.item.reported.mail"
            v-b-tooltip="row.item.reported.mail"
            class="fas fa-envelope ml-1"
            @click.stop="copyToClipboard(row.item.reported.mail)"
          />
        </template>
        <template #cell(reporter)="row">
          <Avatar :user="row.item.reporter" />
          <a :href="$url('profile', row.item.reporter.id)">{{ row.item.reporter.name }}</a>
          <i
            v-b-tooltip="row.item.reporter.mail"
            class="fas fa-envelope ml-1"
            @click.stop="copyToClipboard(row.item.reporter.mail)"
          />
        </template>

        <template #cell(actions)="row">
          <OverflowMenu :options="[{hide: !mayDelete, icon:'trash-alt', textKey: 'delete', callback: () => $emit('delete-report', row.item.id)}]" />
        </template>

        <template #row-details="row">
          <div class="report">
            <p><strong>{{ $t('reports.report_id') }}</strong>: {{ row.item.id }}</p>
            <p><strong>{{ $t('reports.time') }}</strong>: {{ dateFormatter.dateTime(row.item.reportedAt) }}</p>
            <p v-if="row.item.store">
              <strong>{{ $t('reports.store') }}</strong>: <a :href="$url('store', row.item.store.id)">
                {{ row.item.store.name }}</a> ({{ row.item.store.id }})
            </p>
            <p v-else>
              <strong>{{ $t('reports.store') }}</strong>: -
            </p>
            <p><strong>{{ $t('reports.reported') }}</strong>: {{ row.item.reported.name }} ({{ row.item.reported.id }}), {{ row.item.reported.mail }}</p>
            <p><strong>{{ $t('reports.reporter') }}</strong>: {{ row.item.reporter.name }} ({{ row.item.reporter.id }}), {{ row.item.reporter.mail }}</p>
            <p><strong>{{ $t('reports.reason') }}</strong>: {{ row.item.reason }}</p>
            <p><strong>{{ $t('reports.message') }}</strong>: {{ row.item.message }}</p>
          </div>
        </template>
      </b-table>
      <div class="float-right">
        <b-pagination
          v-if="reports.length > perPage"
          v-model="currentPage"
          :total-rows="reports.length"
          :per-page="perPage"
        />
      </div>
    </div>
    <b-alert
      v-else
      show
    >
      {{ $t('reports.no_reports_fallback') }}
    </b-alert>
  </div>
</template>
<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import Time from '@/components/Time.vue'
import CopyToClipboardMixin from '@/mixins/CopyToClipboardMixin.js'
import OverflowMenu from '@/components/OverflowMenu.vue'
import { useUserStore } from '@/stores/user'
import dateFormatter from '@/helper/date-formatter'

const userStore = useUserStore()

export default {
  components: { Avatar, Time, OverflowMenu },
  mixins: [CopyToClipboardMixin],
  props: {
    reports: { type: Array, default: null },
  },
  setup () {
    return {
      userStore,
      dateFormatter,
    }
  },
  data () {
    return {
      currentPage: 1,
      perPage: 20,
      fields: [
        { key: 'id', label: this.$t('reports.id') },
        { key: 'time', label: this.$t('reports.time'), sortable: true },
        { key: 'reported', label: this.$t('reports.reported'), sortable: true },
        { key: 'reporter', label: this.$t('reports.reporter'), sortable: true },
        { key: 'reason', label: this.$t('reports.reason') },
        { key: 'actions', label: '' },
      ],
    }
  },
  computed: {
    mayDelete () {
      return userStore.isOrga
    },
  },
  methods: {
    toggleDetails (report, index) {
      const expanded = this.reports.find((report, i) => i !== index && report._showDetails)
      if (expanded) expanded._showDetails = false
      report._showDetails ^= true
    },
  },
}
</script>
<style scoped>
::v-deep tr:not(.b-table-details) {
  cursor: pointer;
}
</style>
