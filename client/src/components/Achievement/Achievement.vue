<template>
  <span class="achievement">
    <b-badge
      ref="badge"
      pill
      @click="onClick"
    >
      <i :class="iconClass" />
      <span v-text="achievement.name" />
    </b-badge>
    <b-modal
      v-if="!noModal"
      ref="detailsModal"
      centered
      ok-only
    >
      <template #modal-title>
        {{ $i18n('terminology.achievement') }}:
        <i :class="iconClass" />
        <span v-text="achievement.name" />
      </template>
      <Markdown :source="achievement.description" /><br>
      <div v-if="isAwarded">
        <p>
          {{ $i18n('achievements.awarded') }}
          <Time
            :time="achievement.createdAt.date"
            plain
            :tooltip="null"
          />
        </p>
        <p v-if="achievement.validUntil?.date">
          {{ $i18n('achievements.validUntil') }}:
          <Time
            :time="achievement.validUntil.date"
            plain
            :tooltip="null"
          />
        </p>
        <p v-if="achievement.notice">
          {{ $i18n('achievements.notice') }}:
          {{ achievement.notice }}
        </p>
      </div>
      <div v-else>
        <i v-if="achievement.validityInDaysAfterAssignment > 0" v-text="$i18n('achievements.validity.days', achievement)" />
        <i v-else v-text="$i18n('achievements.validity.indefinite')" />
      </div>
    </b-modal>
  </span>
</template>
<script>
import Markdown from '@/components/Markdown/Markdown.vue'
import Time from '@/components/Time.vue'

export default {
  components: { Markdown, Time },
  props: {
    achievement: { type: Object, required: true },
    noModal: { type: Boolean, default: false },
  },
  computed: {
    iconClass () {
      return this.achievement.icon || 'fas fa-tag'
    },
    isAwarded () {
      return 'validUntil' in this.achievement
    },
  },
  methods: {
    onClick () {
      if (this.noModal) {
        this.$emit('click')
      } else {
        this.$refs.detailsModal.show()
      }
    },
  },
}
</script>
<style lang="scss" scoped>
.achievement {
  span {
    font-size: 1em;
    cursor: pointer;
    margin-bottom: 0.6em;

    &:hover {
      background-color: var(--fs-color-success-600);
    }

    i {
      font-size: 1.3em;
      position: relative;
      top: 0.05em;
    }
  }

  &:not(:last-child){
    margin-right: 0.5em;
  }
}
</style>
