import { reactive } from 'vue'
import { objectMap } from '@/utils'
import { getMapMarkers } from '@/api/map'

export const MAP_CONSTANTS = Object.freeze({
  CENTER_GERMANY_LAT: 50.89,
  CENTER_GERMANY_LON: 10.13,
  ZOOM_COUNTRY: 6,
  ZOOM_CITY: 13,
})

export const MARKER_TYPES = Object.freeze({
  baskets: { name: 'baskets', label: 'terminology.baskets', icon: 'shopping-basket', color: 'green' },
  stores: { name: 'stores', label: 'menu.entry.stores', icon: 'shopping-cart', color: 'darkred' },
  foodsharepoints: { name: 'foodsharepoints', label: 'terminology.fsp', icon: 'recycle', color: 'beige' },
  communities: { name: 'communities', label: 'menu.entry.regionalgroups', icon: 'users', color: 'blue' },
})

export const STORE_MARKER_TYPES = Object.freeze({
  allStores: { name: 'allebetriebe', label: 'store.bread' },
  needHelp: { name: 'needhelp', label: 'menu.entry.helpwanted' },
  needHelpUrgently: { name: 'needhelpinstant', label: 'menu.entry.helpneeded' },
  cooperating: { name: 'nkoorp', label: 'menu.entry.other_stores' },
  myStores: { name: 'mine', label: 'map.filters.my_stores' },
})

// Markers are reloaded from the server if they are older than this
const MAX_MARKER_CACHING_TIME = 10 * 60 * 1000 // 10 minutes in milliseconds

export const store = {
  state: reactive({
    markers: objectMap(MARKER_TYPES, key => { return null }),
    lastMarkerFetchTime: objectMap(MARKER_TYPES, key => { return null }),
  }),
  /**
   * Loads markers of a specific type and saves them in the store's state.
   */
  async getMarkers (name, statusNames = []) {
    const now = new Date()

    // The list of stores can not be cached because it can be different depending on the status names
    if (name === MARKER_TYPES.stores.name || this.state.markers[name] === null ||
      this.state.lastMarkerFetchTime[name] === null ||
      new Date(this.state.lastMarkerFetchTime[name].getTime() + MAX_MARKER_CACHING_TIME) < now) {
      const result = await getMapMarkers([name], statusNames)
      for (const key in result) {
        this.state.markers[key] = result[key]
        this.state.lastMarkerFetchTime[key] = now
      }
    }
    return this.state.markers[name]
  },
}
