import {
  MAP_RASTER_TILES_URL_GEOAPIFY,
  MAP_RASTER_TILES_URL_GEOAPIFY_DARK,
  MAP_RASTER_TILES_URL_OSM,
} from '@/consts'
import { useThemeStore } from '@/stores/theme'
import { geoapifyApiKey, isDev, isTest } from '@/helper/server-data'
import { MAP_CONSTANTS } from '@/stores/map'

export function getMapRasterTilesUrl () {
  if (isTest) {
    return '/mock/geoapify/{z}/{x}/{y}.png'
  } else if (isDev) {
    return MAP_RASTER_TILES_URL_OSM
  } else {
    if (useThemeStore().isDark) {
      return MAP_RASTER_TILES_URL_GEOAPIFY_DARK + geoapifyApiKey
    } else {
      return MAP_RASTER_TILES_URL_GEOAPIFY + geoapifyApiKey
    }
  }
}

/**
 * A point on the map.
 * @typedef {Object} Coordinate
 * @property {number} lat - The latitude of the coordinate.
 * @property {number} lon - The longitude of the coordinate.
 */

/**
 * Checks if a coordinate is valid for use on the map.
 * @param {Coordinate} coordinate - The coordinate with {lat, lon} to check.
 * @returns {boolean} True if the coordinate is valid, false otherwise.
 */
export function isValidMapCoordinate (coordinate) {
  const lat = coordinate?.lat
  const lon = coordinate?.lon
  return typeof lat === 'number' && typeof lon === 'number' && isFinite(lat) && isFinite(lon)
}

/**
 * Returns the coordinate if it is valid, otherwise a safe default. For use as map center or marker position.
 * @param {Coordinate} coordinate - The coordinate with {lat, lon} to check.
 * @returns {Coordinate} A safe coordinate with {lat, lon} for use on the map.
 */
export function getCoordinateOrSafeDefaultForMap (coordinate) {
  if (isValidMapCoordinate(coordinate)) {
    return coordinate
  }
  return { lat: MAP_CONSTANTS.CENTER_GERMANY_LAT, lon: MAP_CONSTANTS.CENTER_GERMANY_LON }
}
