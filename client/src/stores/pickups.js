import { defineStore } from 'pinia'
import { listRegisteredPickups, listPickupOptions, getRegularPickup, listPickups } from '@/api/pickups'
import { getCache, getCacheInterval, setCache, invalidateCache, getCacheAge } from '@/helper/cache'

const CACHES = {
  options: {
    name: 'pickup-options',
    interval: 900000, // 15 minutes in milliseconds
  },
}

export const usePickupStore = defineStore('pickup', {
  state: () => ({
    registred: [],
    options: [],
    regularPickup: [],
    pickups: [],
    loading: null,
  }),
  getters: {
    getRegistered: (state) => state.registred,
    getOptions: (state) => state.options,
    getRegularPickup: (state) => state.regularPickup,
    getPickups: (state) => state.pickups,
  },
  actions: {
    async fetchRegistered (id) {
      try {
        this.registred = await listRegisteredPickups(id)
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
      if (this.loading) return this.loading
      let doneLoading
      this.loading = new Promise(resolve => { doneLoading = resolve })
      try {
        const doRefetch = force || await getCacheInterval(CACHES.options.name, CACHES.options.interval)
        if (doRefetch) {
          this.options = await listPickupOptions()
          await setCache(CACHES.options.name, this.options)
        } else {
          this.options = await getCache(CACHES.options.name)
        }
        doneLoading()
        this.loading = null
        return doRefetch
      } catch (error) {
        console.error('Error fetching pickup options:', error)
      }
    },
    async invalidateOptionsCache () {
      await invalidateCache(CACHES.options.name)
    },
    async getOptionsCacheAge () {
      return await getCacheAge(CACHES.options.name)
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
