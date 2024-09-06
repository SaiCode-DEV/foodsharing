import '@/core'
import '@/globals'
import { vueApply, vueRegister } from '@/vue'
import StoreCategoriesList from '@/views/pages/StoreCategories/StoreCategoriesList.vue'

vueRegister({
  StoreCategoriesList,
})
vueApply('#store-categories-list')
