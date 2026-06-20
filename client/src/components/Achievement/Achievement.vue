<template>
  <span class="achievement">
    <b-badge
      ref="badge"
      pill
      @click="onClick"
    >
      <i :class="iconClass" />
      <span v-text="achievement.name" />
      <span v-if="group.length > 1" class="count-addon">{{ group.length }}</span>
    </b-badge>
    <b-modal
      v-if="!noModal"
      ref="detailsModal"
      centered
      ok-only
    >
      <template #modal-title>
        <span v-if="group.length > 1">{{ group.length }} x</span>
        <i :class="iconClass" />
        <span v-text="achievement.name" />
      </template>
      <AchievementInfo :achievement="achievement" />
      <ul :class="{'single-sticker': awardedGroup.length === 1}">
        <li v-for="(awardedAchievement, i) of awardedGroup" :key="i">
          <span>
            {{ $t('achievements.awarded') }}
            <TimeDisplay
              :time="awardedAchievement.createdAt.date"
              plain
              :tooltip="null"
            /><span v-if="awardedAchievement.validUntil?.date" class="inline-separator">,</span>
          </span>
          <span v-if="awardedAchievement.validUntil?.date">
            {{ $t('achievements.validUntil') }}
            <TimeDisplay
              :time="awardedAchievement.validUntil.date"
              plain
              :tooltip="null"
            />
          </span>
          <p v-if="awardedAchievement.notice">
            {{ $t('achievements.notice') }}:
            <i>{{ awardedAchievement.notice }}</i>
          </p>
        </li>
      </ul>
    </b-modal>
  </span>
</template>
<script>
import TimeDisplay from '@/components/TimeDisplay.vue'
import AchievementInfo from './AchievementInfo.vue'

export default {
  components: { TimeDisplay, AchievementInfo },
  props: {
    achievement: { type: Object, required: true },
    noModal: { type: Boolean, default: false },
    group: { type: Array, default: () => [] },
  },
  computed: {
    iconClass () {
      return this.achievement.icon || 'fas fa-tag'
    },
    awardedGroup () {
      if (!this.group.length) {
        return 'validUntil' in this.achievement ? [this.achievement] : []
      }
      return this.group.filter(achievement => 'validUntil' in achievement)
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
    line-height: 1.5em;

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

  .count-addon, .count-addon:hover {
    border-radius: 0 1em 1em 0;
    padding: 1px .75em 1px .65em;
    margin-right: calc(-0.3em - 1px);
    line-height: 1.5em;
    background-color: white;
    color: var(--fs-color-success-600);
  }
}

.achievement-description>div>:first-child {
  display: inline;
}

.single-sticker {
  list-style: none;
  padding-left: 0;
  margin-left: 0;
  > li > span {
    display: block;
  }
  .inline-separator {
    display: none;
  }
}
</style>
