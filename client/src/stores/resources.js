import { deleteResource, favoriteResource, getOwnResources, getResourceCategories, getResourcePermissions, getResourcesForRegion, patchResource, postCommonsResource, postResource, unfavoriteResource } from '@/api/resources'
import { shuffle } from '@/script'
import { defineStore } from 'pinia'

// The list of categories and resources are null until data is fetched.
// Make sure to initialize properly before using other methods or getters of this store.
export const useResourceStore = defineStore('resource', {
  state: () => {
    return {
      categories: null, // array of categories as {id, name} object
      resources: null, // array of resource objects
      otherOwnResourceCount: null, // count of own resources that isn't included in the currently loaded array of resources
      permissions: null,
    }
  },
  getters: {
    categoriesMap: (state) => Object.fromEntries(state.categories.map(category => [category.id, category.name])),
  },
  actions: {
    /**
     * Drops what belongs to one particular region. `categories` are global and stay.
     */
    resetRegionBoundState () {
      this.resources = null
      this.otherOwnResourceCount = null
      this.permissions = null
    },
    async fetchResourceCategories () {
      this.categories = await getResourceCategories()
    },
    async fetchResources (regionId) {
      this.resources = await getResourcesForRegion(regionId)
    },
    async fetchOwnResources () {
      this.resources = await getOwnResources()
    },
    /**
     * make sure that this.resources is loaded before calling this
     */
    async fetchOtherOwnResourceCount () {
      const ownResources = await getOwnResources()
      const ownIds = new Set(ownResources.map(resource => resource.id))
      const resourcesIds = new Set(this.resources.map(resource => resource.id))
      this.otherOwnResourceCount = ownIds.difference(resourcesIds).size
    },
    async fetchResourcePermissions (regionId) {
      this.permissions = await getResourcePermissions(regionId)
    },
    async addResource (resource, isCommonsResource = false) {
      if (isCommonsResource) {
        resource = await postCommonsResource(resource)
      } else {
        resource = await postResource(resource)
      }
      this.resources.push(resource)
      return resource
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
      return this.resources?.filter(resource => resource.user?.id === userId)
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
