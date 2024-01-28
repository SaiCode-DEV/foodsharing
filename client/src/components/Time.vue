<template>
  <span
    v-b-tooltip="$dateFormatter.dateTime(date)"
    class="time text-muted"
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
    time: { type: [Date, String], required: true },
    showIcon: { type: Boolean, default: true },
  },
  data () {
    const date = new Date(this.time)
    if (isNaN(date.valueOf())) throw new Error('invalid time')
    return { date }
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
