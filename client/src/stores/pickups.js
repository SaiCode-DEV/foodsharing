import { defineStore } from 'pinia'
import { listRegisteredPickups, listPickupOptions, getRegularPickup, listPickups } from '@/api/pickups'
import { invalidateCache, getCacheAge } from '@/helper/cache'
import { cacheKeys } from '@/helper/cache-keys'

export const usePickupStore = defineStore('pickup', {
  state: () => ({
    registered: [],
    options: [],
    regularPickup: [],
    pickups: [],
  }),
  getters: {
    getRegistered: (state) => state.registered,
    getOptions: (state) => state.options,
    getRegularPickup: (state) => state.regularPickup,
    getPickups: (state) => state.pickups,
  },
  actions: {
    /**
     * Drops what belongs to one particular store. `registered` and `options` are the
     * user's own data and are not bound to a store, so they stay.
     */
    resetStoreBoundState () {
      this.regularPickup = []
      this.pickups = []
    },
    async fetchRegistered (id) {
      try {
        this.registered = await listRegisteredPickups(id)
      } catch (error) {
        console.error('Error fetching registered pickups:', error)
      }
    },
    async fetchRegularPickup (storeId) {
      try {
        this.regularPickup = await getRegularPickup(storeId)
      } catch (error) {
        console.error('Error fetching regular pickup:', error)
      }
    },
    async fetchOptions (force = false) {
      try {
        this.options = await listPickupOptions({ force })
      } catch (error) {
        console.error('Error fetching pickup options:', error)
      }
    },
    async invalidateOptionsCache () {
      await invalidateCache(cacheKeys.listPickupOptions())
    },
    async invalidateRegisteredCache (id) {
      const userId = id ?? 'current'
      await invalidateCache(cacheKeys.listRegisteredPickups(userId))
    },
    async getOptionsCacheAge () {
      return await getCacheAge(cacheKeys.listPickupOptions())
    },
    async loadPickups (storeId) {
      try {
        this.pickups = await listPickups(storeId)
      } catch (error) {
        console.error('Error loading pickups:', error)
      }
    },
  },
})
