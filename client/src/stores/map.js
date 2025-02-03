import { getMapMarkers } from '@/api/map'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'

// Markers are reloaded from the server if they are older than this
const MAX_MARKER_CACHING_TIME = 10 * 60 * 1000 // 10 minutes in milliseconds
const baseCacheName = 'mapMarkers'

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
  users: { name: 'users', label: 'terminology.users', icon: 'user', color: 'darkpurple' },
  events: { name: 'events', icon: 'calendar', color: 'orange' },
})

export const MARKER_SELECT_TYPES = Object.freeze({
  stores: {
    status: ['all', 'cooperating', 'not-cooperating'],
    help: ['all', 'open', 'searching'],
    scope: ['all', 'region', 'member'],
  },
  users: {
    role: ['all', 'foodsaver', 'store-manager'],
    activity: ['week', 'month', '3months', '6months', 'all'],
    member: ['all', 'homeregion', 'other'],
  },
})

/**
 * Loads markers of a specific type and saves them in the store's state.
 */
export async function getMarkers (name, specifiers = {}) {
  // Get a canonical string representation from a set of specifiers for the cache name
  const identifier = Object.entries(specifiers).sort((a, b) => a[0].localeCompare(b[0])).flat().join('_')
  const cacheName = `${baseCacheName}-${name}-${identifier}`

  let markers
  if (await getCacheInterval(cacheName, MAX_MARKER_CACHING_TIME)) {
    markers = await getMapMarkers(name, specifiers)
    setCache(cacheName, markers) // don't wait for completion of this async function
  } else {
    markers = await getCache(cacheName)
  }
  return markers
}
