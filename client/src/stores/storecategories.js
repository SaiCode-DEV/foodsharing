import Vue from 'vue'
import { getStoreCategories, editStoreCategory, addStoreCategory, removeStoreCategory } from '@/api/storeCategories'

export const store = Vue.observable({
  categories: [],
})

export const getters = {
  getCategories () {
    return store.categories
  },
}

export const mutations = {
  async fetchCategories () {
    store.categories = await getStoreCategories()
  },
  async addCategory (name) {
    const category = await addStoreCategory(name)
    store.categories.push(category)
  },
  async removeCategory (id) {
    const index = store.categories.findIndex(category => category.id === id)
    if (index >= 0) {
      await removeStoreCategory(id)
      store.categories.splice(index, 1)
    }
  },
  async editCategory (id, name) {
    const index = store.categories.findIndex(category => category.id === id)
    if (index >= 0) {
      await editStoreCategory(id, name)
      store.categories[index].name = name
    }
  },
}

export default { store, getters, mutations }
