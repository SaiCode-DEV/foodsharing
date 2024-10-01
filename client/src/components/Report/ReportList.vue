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
          <Time :time="row.item.time" :muted="false" />
        </template>
        <template #cell(fs_name)="row">
          <Avatar :user="{ avatar: row.item.fs_photo, name: row.item.fs_name, id: row.item.fs_id}" />
          <a v-if="row.item.fs_name" :href="$url('profile', row.item.fs_id)">{{ row.item.fs_name }} {{ row.item.fs_nachname }}</a>
          <span v-else v-text="$i18n('forum.deleted_user')" />
          <i
            v-if="row.item.fs_name"
            v-b-tooltip="row.item.fs_email"
            class="fas fa-envelope ml-1"
            @click.stop="copyToClipboard(row.item.fs_email)"
          />
        </template>
        <template #cell(rp_name)="row">
          <Avatar :user="{ avatar: row.item.rp_photo, name: row.item.rp_name, id: row.item.rp_id}" />
          <a :href="$url('profile', row.item.rp_id)">{{ row.item.rp_name }} {{ row.item.rp_nachname }}</a>
          <i
            v-b-tooltip="row.item.rp_email"
            class="fas fa-envelope ml-1"
            @click.stop="copyToClipboard(row.item.rp_email)"
          />
        </template>

        <template #cell(actions)="row">
          <OverflowMenu :options="[{hide: !mayDelete, icon:'trash-alt', textKey: 'delete', callback: () => $emit('delete-report', row.item.id)}]" />
        </template>

        <template #row-details="row">
          <div class="report">
            <p><strong>{{ $i18n('reports.report_id') }}</strong>: {{ row.item.id }}</p>
            <p><strong>{{ $i18n('reports.time') }}</strong>: {{ row.item.time }}</p>
            <p v-if="row.item.betrieb_id !== 0">
              <strong>{{ $i18n('reports.store') }}</strong>: <a :href="`/?page=fsbetrieb&id=${row.item.betrieb_id}`">
                {{ row.item.betrieb_name }}</a> ({{ row.item.betrieb_id }})
            </p>
            <p v-else>
              <strong>{{ $i18n('reports.store') }}</strong>: -
            </p>
            <p><strong>{{ $i18n('reports.reported') }}</strong>: {{ row.item.fs_name }} {{ row.item.fs_nachname }} ({{ row.item.fs_id }}), {{ row.item.fs_email }}</p>
            <p><strong>{{ $i18n('reports.reporter') }}</strong>: {{ row.item.rp_name }} {{ row.item.rp_nachname }} ({{ row.item.rp_id }}), {{ row.item.rp_email }}</p>
            <p><strong>{{ $i18n('reports.reason') }}</strong>: {{ row.item.tvalue }}</p>
            <p><strong>{{ $i18n('reports.message') }}</strong>: {{ row.item.msg }}</p>
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
      v-text="$i18n('reports.no_reports_fallback')"
    />
  </div>
</template>
<script>
import Avatar from '@/components/Avatar/Avatar.vue'
import Time from '@/components/Time.vue'
import CopyToClipboardMixin from '@/mixins/CopyToClipboardMixin.js'
import OverflowMenu from '@/components/OverflowMenu.vue'
import { useUserStore } from '@/stores/user'

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
    }
  },
  data () {
    return {
      currentPage: 1,
      perPage: 20,
      fields: [
        { key: 'id', label: this.$i18n('reports.id') },
        { key: 'time', label: this.$i18n('reports.time'), sortable: true },
        { key: 'fs_name', label: this.$i18n('reports.reported'), sortable: true },
        { key: 'rp_name', label: this.$i18n('reports.reporter'), sortable: true },
        { key: 'tvalue', label: this.$i18n('reports.reason') },
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
