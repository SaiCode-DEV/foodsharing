<template>
  <BasePage>
    <ReportContainer
      :title="$t('reports.reports_region', { regionName })"
      :report-fetcher="reportFetcher"
      :region-id="regionId"
      :region-report-group-id="regionReportGroupId"
    />
  </BasePage>
</template>
<script>

import BasePage from '@/views/pages/Layout/BasePage.vue'
import ReportContainer from './ReportContainer.vue'
import { getReportsByRegion } from '@/api/report'

export default {
  components: { BasePage, ReportContainer },
  props: {
    regionId: { type: Number, required: true },
    regionName: { type: String, required: true },
    regionReportGroupId: { type: Number, required: false, default: null },
  },
  methods: {
    async reportFetcher () {
      return await getReportsByRegion(this.regionId)
    },
  },
}
</script>
