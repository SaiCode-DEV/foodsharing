import { reactive } from 'vue'
import { objectMap } from '@/utils'
import { getMapMarkers } from '@/api/map'

export const MAP_CONSTANTS = Object.freeze({
  CENTER_GERMANY_LAT: 50.89,
  CENTER_GERMANY_LON: 10.13,
  ZOOM_COUNTRY: 6,
  ZOOM_CITY: 15,
  MAX_ZOOM: 20,
})

export const MARKER_TYPES = Object.freeze({
  baskets: { name: 'baskets', label: 'terminology.baskets', icon: 'shopping-basket', color: 'green' },
  stores: { name: 'stores', label: 'menu.entry.stores', icon: 'shopping-cart', color: 'darkred' },
  foodsharepoints: { name: 'foodsharepoints', label: 'terminology.fsp', icon: 'recycle', color: 'beige' },
  communities: { name: 'communities', label: 'menu.entry.regionalgroups', icon: 'users', color: 'blue' },
})

export const STORE_MARKER_SELECT_TYPES = Object.freeze({
  status: ['all', 'cooperating', 'not-cooperating'],
  help: ['all', 'open', 'searching'],
  scope: ['all', 'region', 'member'],
})

// Markers are reloaded from the server if they are older than this
const MAX_MARKER_CACHING_TIME = 10 * 60 * 1000 // 10 minutes in milliseconds

export const store = {
  state: reactive({
    markers: objectMap(MARKER_TYPES, key => { return null }),
    lastMarkerFetchTime: objectMap(MARKER_TYPES, key => { return null }),
    lastMarkerFetchSpecifiers: objectMap(MARKER_TYPES, key => { return null }),
  }),
  /**
   * Loads markers of a specific type and saves them in the store's state.
   */
  async getMarkers (name, specifiers = {}) {
    const now = new Date()
    const specifiersJson = JSON.stringify(specifiers)
    const isCached = this.state.markers[name] !== null &&
      this.state.lastMarkerFetchTime[name] !== null &&
      new Date(this.state.lastMarkerFetchTime[name].getTime() + MAX_MARKER_CACHING_TIME) >= now
    const isCorrectSpecifier = this.state.lastMarkerFetchSpecifiers[name] === specifiersJson

    if (!isCached || !isCorrectSpecifier) {
      const result = await getMapMarkers(name, specifiers)
      this.state.markers[name] = result
      this.state.lastMarkerFetchTime[name] = now
      this.state.lastMarkerFetchSpecifiers[name] = specifiersJson
    }
    return this.state.markers[name]
  },
}
