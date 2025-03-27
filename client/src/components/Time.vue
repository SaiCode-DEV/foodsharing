<template>
  <span
    v-if="time || fallback"
    v-b-tooltip="tooltip ?? tooltipTime"
    class="time"
    :class="{ 'text-muted': muted, small: !normalSize }"
  >
    <i v-if="showIcon" class="far fa-fw fa-clock" />
    <span v-if="time" v-text="dateFormatter.relativeTime(date, options, dateOnly)" />
    <span v-else v-text="fallback" />
  </span>
</template>

<script setup>
import { ref, defineProps, onMounted, computed, watch } from 'vue'
import dateFormatter from '@/helper/date-formatter'
import { captureError } from '@/sentry'

const props = defineProps({
  plain: { type: Boolean, default: false },
  time: { type: [Date, String, Number], default: null },
  showIcon: { type: Boolean, default: function () { return !this.plain } },
  normalSize: { type: Boolean, default: function () { return this.plain } },
  muted: { type: Boolean, default: function () { return !this.plain } },
  dateOnly: { type: Boolean, default: false },
  tooltip: { type: [Object, String, Boolean], default: function () { return this.plain ? false : null } },
  options: { type: Object, default: () => {} },
  fallback: { type: String, default: '' },
})

const date = ref(null)

const tooltipTime = computed(() => {
  if (!date.value) return ''
  const method = props.dateOnly ? 'date' : 'dateTime'
  return dateFormatter[method](date.value)
})

function parseTime (time) {
  if (time === null || time === undefined) return {}
  if (typeof time === 'string') {
    try {
      date.value = new Date(time)
      if (isNaN(date.value)) {
        captureError(`Invalid date : ${time}`)
        date.value = null
        return
      }
    } catch (e) {
      console.error('Invalid date', time)
      date.value = null
      return
    }
    return
  }
  date.value = new Date(time)
}

let timeout

function update () {
  const now = new Date()
  const updateFrequency = Math.abs(now - date.value) < 60_000 ? 10_000 : 60_000
  const timeUntilUpdate = updateFrequency - (now - date.value) % updateFrequency
  window.clearTimeout(timeout)
  timeout = window.setTimeout(update, timeUntilUpdate)
  date.value = new Date(date.value)
}

watch(() => props.time, () => {
  parseTime(props.time)
  update()
})

onMounted(() => {
  parseTime(props.time)
  update()
})
</script>

<style scoped>
.small {
  font-size: smaller;
}
</style>
