import { deleteResource, favoriteResource, getResourceCategories, getResourcesForRegion, patchResource, postResource, unfavoriteResource } from '@/api/resources'
import { shuffle } from '@/script'
import { defineStore } from 'pinia'

// The list of categories and resources are null until data is fetched.
// Make sure to initialize properly before using other methods or getters of this store.
export const useResourceStore = defineStore('resource', {
  state: () => {
    return {
      categories: null, // array of categories as {id, name} object
      resources: null, // array of resource objects
    }
  },
  getters: {
    categoriesMap: (state) => Object.fromEntries(state.categories.map(category => [category.id, category.name])),
  },
  actions: {
    async fetchResourceCategories () {
      this.categories = await getResourceCategories()
    },
    async fetchResources (regionId) {
      this.resources = await getResourcesForRegion(regionId)
    },
    async addResource (resource) {
      resource = await postResource(resource)
      this.resources.push(resource)
    },
    async editResource (resourceId, resource) {
      resource = await patchResource(resourceId, resource)
      const index = this.resources.findIndex(resource => resource.id === resourceId)
      if (index < 0) {
        console.warn(`Resource with ID ${resourceId} not found for editing.`)
        this.resources.push(resource)
      } else {
        this.resources.splice(index, 1, resource)
      }
      return resource
    },
    async removeResource (resourceId) {
      await deleteResource(resourceId)
      const index = this.resources.findIndex(resource => resource.id === resourceId)
      if (index < 0) {
        console.warn(`Resource with ID ${resourceId} not found for removal.`)
        return
      }
      this.resources.splice(index, 1)
    },
    shuffleResources () {
      shuffle(this.resources).splice(0, 0)
    },
    sortResources (key = 'id') {
      this.resources.sort((a, b) => b[key] - a[key])
    },
    getResourcesByUser (userId) {
      return this.resources?.filter(resource => resource.user.id === userId)
    },
    getResourcesByCategories (categoryIds) {
      if (!categoryIds.length) return [...this.resources]
      return this.resources.filter(resource => categoryIds.every(id => resource.categories.includes(id)))
    },
    favoriteResource (resourceId, newIsFavorite) {
      this.resources.find(resource => resource.id === resourceId).isFavorite = newIsFavorite
      if (newIsFavorite) {
        favoriteResource(resourceId)
      } else {
        unfavoriteResource(resourceId)
      }
    },
  },
})
