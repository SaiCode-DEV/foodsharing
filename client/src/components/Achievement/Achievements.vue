<template>
  <div>
    <Info info-key="achievements" class="float-right" />
    <div v-for="(regionGroup, i) of regionGroups" :key="i">
      <h5 v-if="regionGroup[0][0]?.scope">
        {{ regionGroup[0][0].scope.name }}:
      </h5>
      <Achievement
        v-for="group of regionGroup"
        :key="group[0].id"
        :achievement="group[0]"
        :group="group"
        :no-modal="noModal"
        @click="$emit('click', group[0])"
      />
    </div>
    <span v-if="achievements?.length === 0" v-text="$t(emptyTextKey)" />
    <div v-if="!achievements" class="achievements-loader">
      <b-skeleton width="10em" height="1.75em" />
      <b-skeleton width="7em" height="1.75em" />
      <b-skeleton width="8em" height="1.75em" />
    </div>
  </div>
</template>
<script>
import Info from '../Help/Info.vue'
import Achievement from './Achievement.vue'

export default {
  components: { Achievement, Info },
  props: {
    achievements: { type: [Array, Object], default: null },
    noModal: { type: Boolean, default: false },
    emptyTextKey: { type: String, default: 'achievements.noneInThisRegion' },
  },
  computed: {
    regionGroups () {
      if (!this.achievements) return []
      const achievementGroups = Object.values(Object.groupBy(this.achievements, achievement => achievement.id))
      return Object.values(Object.groupBy(achievementGroups, group => group[0].regionId))
    },
  },
}
</script>
<style scoped>
.achievements-loader ::v-deep .b-skeleton {
  display: inline-block;
  border-radius: 1em;
  margin-right: 0.5em;
}
</style>
