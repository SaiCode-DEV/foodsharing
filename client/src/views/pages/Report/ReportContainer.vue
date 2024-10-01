<template>
  <Container
    :title="title"
    wrap-content
    info-key="report"
    :collapsible="false"
  >
    <ReportList :reports="reports" @delete-report="deleteReport" />
  </Container>
</template>
<script>

import Container from '@/components/Container/Container.vue'
import ReportList from '@/components/Report/ReportList.vue'
import { deleteReport } from '@/api/report'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'

export default {
  components: { Container, ReportList },
  mixins: [ConfirmationDialogue],
  props: {
    reportFetcher: { type: Function, required: true },
    title: { type: String, required: true },
  },
  data: () => ({ reports: null }),
  async mounted () {
    const reports = await this.reportFetcher(this.regionId)
    reports.forEach(report => { report._showDetails = false })
    this.reports = reports
  },
  methods: {
    async deleteReport (id) {
      if (!await this.confirmationDialogue('profile.report.confirmDelete')) return
      await deleteReport(id)
      this.reports = this.reports.filter(report => report.id !== id)
    },
  },
}
</script>
