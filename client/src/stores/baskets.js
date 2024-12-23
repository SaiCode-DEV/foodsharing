import { defineStore } from 'pinia'
import { getBaskets, getBasketsNearby } from '@/api/baskets'
import { getMapMarkers } from '@/api/map'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'

const nearbyCacheRequestName = 'nearbyBaskets'
const nearbyCacheInterval = 300000 // 5 Minuten in Millisekunden

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
    nearby: [],
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
    async fetchOwn () {
      this.own = await getBaskets()
    },
    async fetchNearby ({ lat, lon } = {}, distance = this.radius) {
      try {
        if (await getCacheInterval(nearbyCacheRequestName, nearbyCacheInterval)) {
          this.nearby = await getBasketsNearby(parseFloat(lat), parseFloat(lon), distance)
          await setCache(nearbyCacheRequestName, this.nearby)
        } else {
          this.nearby = await getCache(nearbyCacheRequestName)
        }
        return this.nearby
      } catch (e) {
        console.error('Error fetching nearby baskets:', e)
        return null
      }
    },
    async fetchAllCoordinates () {
      this.allCoordinates = (await getMapMarkers(['baskets'], [''])).baskets
    },
    getNearby (amount = 10) {
      return this.nearby.slice(0, amount)
    },
  },
})
