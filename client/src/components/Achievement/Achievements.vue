<template>
  <div>
    <Achievement
      v-for="achievement of (achievements ?? [])"
      :key="achievement.id"
      :achievement="achievement"
      :no-modal="noModal"
      @click="$emit('click', achievement)"
    />
    <span v-if="achievements?.length === 0" v-text="$t(emptyTextKey)" />
    <div v-if="!achievements" class="achievements-loader">
      <b-skeleton width="10em" height="1.75em" />
      <b-skeleton width="7em" height="1.75em" />
      <b-skeleton width="8em" height="1.75em" />
    </div>
  </div>
</template>
<script>
import Achievement from './Achievement.vue'

export default {
  components: { Achievement },
  props: {
    achievements: { type: [Array, Object], default: null },
    noModal: { type: Boolean, default: false },
    emptyTextKey: { type: String, default: 'achievements.noneInThisRegion' },
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
