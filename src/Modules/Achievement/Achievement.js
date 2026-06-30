import '@/core'
import '@/globals'

import { vueApply, vueRegister } from '@/vue'
import AchievementEditor from '@/views/pages/Achievement/AchievementEditor.vue'

vueRegister({ AchievementEditor })
document.addEventListener('DOMContentLoaded', () => {
  vueApply('#achievementEditor')
})
