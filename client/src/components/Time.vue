<template>
  <span
    v-if="time"
    v-b-tooltip="tooltip ?? tooltipTime"
    class="time"
    :class="{ 'text-muted': muted }"
  >
    <i
      v-if="showIcon"
      class="far fa-fw fa-clock"
    />
    {{ $dateFormatter.relativeTime(date) }}
  </span>
</template>

<script>
export default {
  props: {
    time: { type: [Date, String], default: null },
    showIcon: { type: Boolean, default: true },
    muted: { type: Boolean, default: true },
    dateOnly: { type: Boolean, default: false },
    tooltip: { type: [Object, String], default: null },
  },
  data () {
    if (this.time === null) return {}
    const date = new Date(this.time)
    if (isNaN(date.valueOf())) throw new Error('invalid time')
    return { date }
  },
  computed: {
    tooltipTime () {
      const method = this.dateOnly ? 'date' : 'dateTime'
      return this.$dateFormatter[method](this.date)
    },
  },
  mounted () {
    this.update()
  },
  methods: {
    update () {
      const now = new Date()
      const updateFrequency = Math.abs(now - this.date) < 60_000 ? 10_000 : 60_000
      const timeUntilUpdate = updateFrequency - (now - this.date) % updateFrequency
      window.setTimeout(this.update, timeUntilUpdate)
      this.date = new Date(this.date) // trigger display update
    },
  },
}
</script>

<style scoped>
.time {
  font-size: smaller;
}
</style>
