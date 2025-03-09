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
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import Container from '@/components/Container/Container.vue'
import ReportList from '@/components/Report/ReportList.vue'
import { deleteReport } from '@/api/report'

export default {
  components: { Container, ReportList },
  props: {
    reportFetcher: { type: Function, required: true },
    title: { type: String, required: true },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
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
