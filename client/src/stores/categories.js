import Vue from 'vue'
import { getCategories, editCategory, addCategory, removeCategory, mergeCategories } from '@/api/categories'

export const store = Vue.observable({
  store: [],
  resource: [],
})

export const getters = {
  getCategories (type) {
    return store[type] || []
  },
}

export const mutations = {
  async fetchCategories (type) {
    store[type] = await getCategories(type)
  },
  async addCategory (type, name) {
    const category = await addCategory(type, name)
    category.usageCount = 0
    store[type].push(category)
  },
  async removeCategory (type, id) {
    const index = store[type].findIndex(category => category.id === id)
    if (index >= 0) {
      await removeCategory(type, id)
      store[type].splice(index, 1)
    }
  },
  async editCategory (type, id, name) {
    const index = store[type].findIndex(category => category.id === id)
    if (index >= 0) {
      await editCategory(type, id, name)
      store[type][index].name = name
    }
  },
  async mergeCategories (type, sourceId, targetId) {
    const sourceIndex = store[type].findIndex(category => category.id === sourceId)
    const targetIndex = store[type].findIndex(category => category.id === targetId)
    if (sourceIndex >= 0 && targetIndex >= 0 && sourceIndex !== targetIndex) {
      const { duplicates } = await mergeCategories(type, sourceId, targetId)
      const sourceCategory = store[type][sourceIndex]
      const targetCategory = store[type][targetIndex]
      sourceCategory.usageCount += targetCategory.usageCount - duplicates
      store[type].splice(targetIndex, 1)
    }
  },
}

export default { store, getters, mutations }
