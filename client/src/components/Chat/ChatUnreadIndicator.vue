<template>
  <div
    v-if="unread !== 0"
    v-b-tooltip.hover.noninteractive="$t('chat.unread_messages')"
    class="unread-count"
    role="status"
    aria-live="polite"
    aria-atomic="true"
    :aria-label="$t('chat.unread_messages')"
  >
    <b-badge
      variant="info"
      pill
    >
      {{ badgeContent }}
    </b-badge>
  </div>
</template>

<script>
export default {
  components: {},
  props: {
    unread: { type: Number, default: 0 },
  },
  computed: {
    badgeContent () {
      // non-breaking space keeps the badge visible when unread is 0
      return this.unread > 0 ? this.unread : '\u00A0'
    },
  },
  methods: {},
}
</script>

<style lang="scss" scoped>
.unread-count {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.25em 0.5em;
  font-size: .9em;
}

.badge {
  padding: 0.25em;
  min-width: 1.5em;
  min-height: 1.5em;
  margin-bottom: 1px;
}
</style>
