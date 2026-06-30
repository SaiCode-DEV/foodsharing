import '@/core'
import '@/globals'
import QuizEditor from '@/views/pages/Quiz/QuizEditor'
import { vueApply, vueRegister } from '@/vue'

vueRegister({ QuizEditor })
document.addEventListener('DOMContentLoaded', () => {
  vueApply('#vue-quiz-editor')
})
