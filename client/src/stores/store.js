import { defineStore } from 'pinia'
import { getStoreMetadata, listStoresDetailsForUser, listStoresForUser } from '@/api/stores'
import { pulseError } from '@/script'
import i18n from '@/helper/i18n'
import { listRegionStores } from '@/api/regions'
import { getCache, setCache } from '@/helper/cache'
import { HTTP_RESPONSE } from '@/consts'

let pendingFetchStoresForUser = null
let pendingFetchUserStoreRelations = null

const CACHES = {
  metadata: {
    name: 'storeMetadata',
  },
}

function showError (callback) {
  return callback().catch(error => {
    pulseError(i18n('error_unexpected'))
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
      metadata: {},
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
    getStoreCategoryTypes: (state) => state.metadata.categories ?? [],
    getStoreConvinceStatusTypes: (state) => state.metadata.convinceStatus ?? [],
    getStoreWeightTypes: (state) => state.metadata.weight ?? [],
    getStoreCooperationStatus: (state) => state.metadata.status ?? [],
    getGrocerieTypes: (state) => state.metadata.groceries ?? [],
    getStoreChains: (state) => state.metadata.storeChains ?? [],
    getPublicTimes: (state) => state.metadata.publicTimes ?? [],
    getMaxCountPickupSlot: (state) => state.metadata.maxCountPickupSlot ?? 0,
  },
  actions: {
    async fetchStoresForRegion (regionId = this.regionId) {
      const stores = await showError(() => listRegionStores(regionId))
      for (const store of stores) {
        store.oneOfPossibleMoreAnchestorRegion = regionId
      }
      this.regionId = regionId
      this.addStores(stores)
    },
    async fetchStoresForUser (userId) {
      if (!pendingFetchStoresForUser) {
        pendingFetchStoresForUser = showError(() => listStoresDetailsForUser(userId))
      }
      const stores = await pendingFetchStoresForUser
      this.addStores(stores)
      pendingFetchStoresForUser = null
    },
    async fetchUserStoreRelations (userId) {
      if (!pendingFetchUserStoreRelations) {
        pendingFetchUserStoreRelations = showError(() => listStoresForUser(false, userId))
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
    async fetchMetadata () {
      // try get current cache (needed for version number)
      // send request
      // update cache if data is returned
      try {
        const metadata = await getCache(CACHES.metadata.name) ?? {}
        const version = metadata.version ?? 0
        const hasChains = Boolean(metadata.storeChains)
        try {
          this.metadata = await getStoreMetadata(version, hasChains)
        } catch (error) {
          if (error.code === HTTP_RESPONSE.NOT_MODIFIED) {
            this.metadata = metadata
            return
          } else {
            throw error
          }
        }
        setCache(CACHES.metadata.name, this.metadata)
      } catch (e) {
        console.error('Error fetching store metadata:', e)
      }
    },
  },
})
