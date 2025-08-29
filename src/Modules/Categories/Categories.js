import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import CategoriesEditor from '@/views/pages/Categories/CategoriesEditor.vue'

vueRegister({ CategoriesEditor })
vueApply('#categories-editor')
