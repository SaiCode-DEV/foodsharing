import { defineStore } from 'pinia'
import { getBaskets, getBasketsNearby, updateRequestStatus } from '@/api/baskets'
import { getMapMarkers } from '@/api/map'
import { getCache, getCacheAge, getCacheInterval, setCache } from '@/helper/cache'

const minuteInMs = 60_000
const CACHES = {
  nearby: {
    name: 'nearbyBaskets',
    interval: 5 * minuteInMs,
  },
  own: {
    name: 'ownBaskets',
    intervalWithBaskets: 2 * minuteInMs,
    intervalWithoutBaskets: 10 * minuteInMs,
  },
}

// DBConstants\BasketRequests\Status
export const BASKET_REQUEST_STATUS = Object.freeze({
  REQUESTED_MESSAGE_UNREAD: 0,
  REQUESTED_MESSAGE_READ: 1,
  DELETED_PICKED_UP: 2,
  DENIED: 3,
  NOT_PICKED_UP: 4,
  DELETED_OTHER_REASON: 5,
  FOLLOWED: 9,
  REQUESTED: 10,
})

export const useBasketStore = defineStore('basket', {
  state: () => ({
    own: [],
    nearby: null, // null before data is available, [] if there is no basket nearby
    radius: 30,
    allCoordinates: [],
  }),

  getters: {
    getOwn: (state) => state.own,
    getRadius: (state) => state.radius,
    getRequestedCount: (state) => state.own.reduce((total, basket) => total + basket.requests.length, 0),
    getAllBasketCoordinates: (state) => state.allCoordinates,
  },

  actions: {
    async fetchOwn (forceLoad = false) {
      try {
        const cached = await getCache(CACHES.own.name)
        let interval = CACHES.own.intervalWithoutBaskets
        if (cached && cached.length) interval = CACHES.own.intervalWithBaskets

        if (!cached || forceLoad || await getCacheInterval(CACHES.own.name, interval)) {
          this.own = await getBaskets()
          await setCache(CACHES.own.name, this.own)
        } else {
          this.own = cached
        }
      } catch (e) {
        console.error('Error fetching own baskets:', e)
      }
    },
    async fetchNearby ({ lat, lon } = {}, distance = this.radius) {
      if (lat === undefined || lon === undefined) {
        return console.error('Error fetching nearby baskets: Invalid location')
      }
      try {
        if (await getCacheInterval(CACHES.nearby.name, CACHES.nearby.interval)) {
          this.nearby = await getBasketsNearby(parseFloat(lat), parseFloat(lon), distance)
          await setCache(CACHES.nearby.name, this.nearby)
        } else {
          this.nearby = await getCache(CACHES.nearby.name)
        }
      } catch (e) {
        console.error('Error fetching nearby baskets:', e)
      }
    },
    async fetchAllCoordinates () {
      this.allCoordinates = (await getMapMarkers(['baskets'], []))
    },
    getNearby (amount = 10) {
      return this.nearby?.slice?.(0, amount) ?? null
    },
    async updateBasketRequestStatus (basketId, userId, status) {
      try {
        await updateRequestStatus(basketId, userId, status)
        const index = this.own.findIndex(b => b.id === basketId)
        if (index >= 0) {
          this.own.splice(index, 1)
        }
      } catch (error) {
        console.error('Error updating basket request status:', error)
        throw error
      }
    },
    async getOwnCacheAge () {
      return await getCacheAge(CACHES.own.name)
    },
  },
})
