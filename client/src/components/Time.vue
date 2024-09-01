<template>
  <span
    v-if="time || fallback"
    v-b-tooltip="tooltip ?? tooltipTime"
    class="time"
    :class="{ 'text-muted': muted, small: !plain }"
  >
    <i
      v-if="showIcon"
      class="far fa-fw fa-clock"
    />
    <span v-if="time" v-text="$dateFormatter.relativeTime(date, options)" />
    <span v-else v-text="fallback" />
  </span>
</template>

<script>
export default {
  props: {
    plain: { type: Boolean, default: false },
    time: { type: [Date, String], default: null },
    showIcon: { type: Boolean, default: function () { return !this.plain } },
    muted: { type: Boolean, default: function () { return !this.plain } },
    dateOnly: { type: Boolean, default: false },
    tooltip: { type: [Object, String], default: function () { return this.plain ? false : null } },
    options: { type: Object, default: () => {} },
    fallback: { type: String, default: '' },
  },
  data () {
    if (this.time === null) return {}
    const date = new Date(this.time)
    if (isNaN(date.valueOf())) throw new Error('invalid time')
    return { date }
  },
  computed: {
    tooltipTime () {
      if (!this.time) return ''
      const method = this.dateOnly ? 'date' : 'dateTime'
      return this.$dateFormatter[method](this.date)
    },
  },
  watch: {
    time () {
      if (this.time === null) return {}
      const date = new Date(this.time)
      if (isNaN(date.valueOf())) throw new Error('invalid time')
      this.date = date
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
.small {
  font-size: smaller;
}
</style>
