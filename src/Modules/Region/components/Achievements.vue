<template>
  <div>
    <Container
      :title="$i18n('terminology.achievements')"
      info-key="achievements"
    >
      <div class="list-group-item">
        <p v-text="$i18n('achievements.inThis.' + (isWorkGroup ? 'group' : 'region'))" />
        <Achievement
          v-for="achievement of achievements"
          :key="achievement.id"
          :achievement="achievement"
        />
      </div>
    </Container>
  </div>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import Achievement from '@/components/Achievement/Achievement.vue'
import { getAchievementsFromRegion } from '@/api/achievements.js'
export default {
  components: { Container, Achievement },
  props: {
    groupName: { type: String, required: true },
    groupId: { type: Number, required: true },
    isWorkGroup: { type: Boolean, default: false },
  },
  data: () => ({
    achievements: () => [],
  }),
  async mounted () {
    this.achievements = await getAchievementsFromRegion(this.groupId)
  },
}
</script>
