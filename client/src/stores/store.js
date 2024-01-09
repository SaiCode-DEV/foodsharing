import { defineStore } from 'pinia'
import { listStoresDetailsForCurrentUser, listStoresForCurrentUser } from '@/api/stores'
import { pulseError } from '@/script'
import { listRegionStores } from '@/api/regions'

let pendingFetchStoresForCurrentUser = null
let pendingFetchUserStoreRelations = null

function showError (callback) {
  return callback().catch(error => {
    pulseError(this.$i18n('error_unexpected'))
    throw error
  })
}

export const useStoreStore = defineStore('store', {
  state: () => {
    return {
      /**
       * list of stores indexed by id
       */
      storeData: () => {},
      /**
       * list of relations that the current user has to certain stores
       */
      userRelations: null,
      regionId: null,
    }
  },
  getters: {
    /**
     * list of stores
     */
    stores: (state) => Object.values(state.storeData),
    /**
     * list of stores the current user has a relation to
     */
    userStores: (state) => state.stores.filter(store => state.userRelatedStoreIds.includes(store.id)),
    /**
     * list of stores within the region of this.regionId
     */
    regionStores: (state) => state.stores.filter(store => store.oneOfPossibleMoreAnchestorRegion === state.regionId),
    userRelatedStoreIds: (state) => {
      if (state.userRelations === null) {
        return []
      } else {
        return state.userRelations.map(relation => relation.id)
      }
    },
  },
  actions: {
    async fetchStoresForRegion (regionId = this.regionId) {
      const { stores } = await showError(() => listRegionStores(regionId))
      for (const store of stores) {
        store.oneOfPossibleMoreAnchestorRegion = regionId
      }
      this.regionId = regionId
      this.addStores(stores)
    },
    async fetchStoresForCurrentUser () {
      if (!pendingFetchStoresForCurrentUser) {
        pendingFetchStoresForCurrentUser = showError(listStoresDetailsForCurrentUser)
      }
      const { stores } = await pendingFetchStoresForCurrentUser
      this.addStores(stores)
      pendingFetchStoresForCurrentUser = null
    },
    async fetchUserStoreRelations () {
      if (!pendingFetchUserStoreRelations) {
        pendingFetchUserStoreRelations = showError(listStoresForCurrentUser)
      }
      this.userRelations = await pendingFetchUserStoreRelations
      pendingFetchUserStoreRelations = null
    },
    addStores (stores) {
      const patch = { ...this.storeData }
      stores.forEach(store => {
        patch[store.id] = store
      })
      this.$patch({
        storeData: patch,
      })
    },
  },
})
