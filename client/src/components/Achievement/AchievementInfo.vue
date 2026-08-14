<template>
  <div>
    <Markdown class="mb-2" :source="achievement.description" />
    <b-alert variant="info" show>
      <div v-if="!('validUntil' in achievement)">
        <i class="fas fa-calendar mr-1" />
        <span v-if="achievement.validityInDaysAfterAssignment" v-text="$t('achievements.validity.days', achievement)" />
        <span v-else v-text="$t('achievements.validity.indefinite')" />
      </div>
      <div v-if="achievement.scope">
        <i class="fas fa-users mr-1" />
        <span v-text="$t('achievements.scope')" />:
        <router-link :to="url('achievements', achievement.scope.id)">
          {{ achievement.scope.name }}
        </router-link>
      </div>
      <div>
        <i class="fas fa-eye mr-1" />
        <span v-b-tooltip="$t(`achievements.visibility_type_explanation.${visibilityTypeName}`, { scope: achievement.scope?.name ?? scopeName })">
          <span v-text="$t('achievements.editor.visibility_type')" />:
          <span v-text="$t(`achievements.visibility_type.${visibilityTypeName}`)" />
        </span>
      </div>
      <div v-if="isMultiple">
        <i class="fas fa-clone mr-1" />
        <span v-text="$t('achievements.duplicate_mode_explanation.multiple')" />
      </div>
    </b-alert>
  </div>
</template>
<script setup>
import { ACHIEVEMENT_DUPLICATE_MODE, ACHIEVEMENT_VISIBILITY_TYPE } from '@/consts'
import { computed, defineProps } from 'vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import { url } from '@/helper/urls'

const props = defineProps({
  achievement: { type: Object, required: true },
  scopeName: { type: String, default: '' },
})

const isMultiple = computed(() => props.achievement.duplicateMode === ACHIEVEMENT_DUPLICATE_MODE.MULTIPLE)
const visibilityTypeName = computed(() => Object.entries(ACHIEVEMENT_VISIBILITY_TYPE)
  .find(([key, value]) => value === props.achievement.visibilityType)[0].toLowerCase())

</script>
