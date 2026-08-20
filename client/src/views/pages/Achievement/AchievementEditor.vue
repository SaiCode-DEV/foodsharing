<template>
  <BasePage>
    <template #left>
      <Container
        :title="$t('terminology.bezirk')"
        wrap-content="p-0"
      >
        <RegionTree
          include-working-groups
          @change="updateRegion"
        />
      </Container>
    </template>
    <Container
      v-if="region"
      :title="$t('achievements.inRegion', {region: region.name, count: achievements?.length ?? '...'})"
    >
      <div class="list-group-item">
        <Achievements
          :achievements="achievements"
          no-modal
          @click="selectAchievement"
        />
      </div>
      <ContainerButton
        variant="success"
        text-key="achievements.add"
        icon="fas fa-tags"
        @click="newAchievement"
      />
    </Container>
    <EditAchievementContainer
      :achievement="selectedAchievement"
      @save="saveAchievement"
      @cancel="selectedAchievement = null"
      @delete="deleteAchievement"
    />
  </BasePage>
</template>
<script>
import BasePage from '@/views/pages/Layout/BasePage.vue'
import Container from '@/components/Container/Container.vue'
import RegionTree from '@/components/regiontree/RegionTree.vue'
import Achievements from '@/components/Achievement/Achievements.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'
import EditAchievementContainer from '@/components/Achievement/EditAchievementContainer.vue'

import { addAchievement, deleteAchievement, getAchievementsFromRegion, patchAchievement } from '@/api/achievements'
import { pulseSuccess } from '@/script'

export default {
  components: { BasePage, Container, RegionTree, Achievements, ContainerButton, EditAchievementContainer },
  data: () => ({
    region: null,
    achievements: null,
    selectedAchievement: null,
  }),
  methods: {
    async updateRegion (selected) {
      if (selected.states.id === this.region?.id) return
      this.region = {
        id: selected.states.id,
        name: selected.data.text,
      }
      this.getAchievements()
    },
    async getAchievements () {
      this.achievements = null
      this.selectedAchievement = null
      this.achievements = await getAchievementsFromRegion(this.region.id)
    },
    async selectAchievement (achievement) {
      this.selectedAchievement = achievement
    },
    newAchievement () {
      this.selectedAchievement = {
        name: '',
        description: '',
        icon: null,
        validityInDaysAfterAssignment: NaN,
        regionId: this.region.id,
      }
    },
    async saveAchievement (achievement) {
      if (!achievement.validityInDaysAfterAssignment) achievement.validityInDaysAfterAssignment = null
      else achievement.validityInDaysAfterAssignment = +achievement.validityInDaysAfterAssignment

      if (achievement.id) {
        delete achievement.createdAt
        delete achievement.updatedAt
        await patchAchievement(achievement)
        const index = this.achievements.findIndex(a => a.id === achievement.id)
        this.$set(this.achievements, index, achievement)
      } else {
        achievement.id = await addAchievement(achievement)
        this.achievements.push(achievement)
      }
      this.selectedAchievement = achievement
      pulseSuccess(this.$t('globals.saved'))
    },
    async deleteAchievement (achievementId) {
      await deleteAchievement(achievementId)
      this.achievements = this.achievements.filter(a => a.id !== achievementId)
      this.selectedAchievement = null
    },
  },
}
</script>
