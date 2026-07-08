<template>
  <Container
    :title="title"
    wrap-content
    info-key="report"
    :collapsible="false"
  >
    <ReportList
      :reports="reports"
      :region-id="props.regionId"
      :region-report-group-id="props.regionReportGroupId"
    />
  </Container>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import Container from '@/components/Container/Container.vue'
import ReportList from '@/components/Report/ReportList.vue'

const props = defineProps({
  reportFetcher: { type: Function, required: true },
  title: { type: String, required: true },
  regionId: { type: Number, required: false, default: null },
  regionReportGroupId: { type: Number, required: false, default: null },
})

const reports = ref(null)

onMounted(async () => {
  const fetched = await props.reportFetcher()
  fetched.forEach(r => { r._showDetails = false })
  reports.value = fetched
})

</script>
