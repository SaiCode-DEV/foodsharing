<template>
  <BasePage>
    <ReportContainer
      :title="$t('reports.reports_user', { userName, userId })"
      :report-fetcher="reportFetcher"
    />
    <template #right>
      <!-- TODO break earlier or not side by side -->
      <Wall
        target="fsreports"
        :target-id="userId"
        :title="$t('profile.report.notes')"
      />
    </template>
  </BasePage>
</template>
<script>

import BasePage from '@/views/pages/Layout/BasePage.vue'
import ReportContainer from './ReportContainer.vue'
import { getReportsByUser } from '@/api/report'
import Wall from '@/components/Wall/Wall.vue'

export default {
  components: { BasePage, ReportContainer, Wall },
  props: {
    userId: { type: Number, required: true },
    userName: { type: String, required: true },
  },
  methods: {
    async reportFetcher () {
      return await getReportsByUser(this.userId)
    },
  },
}
</script>
