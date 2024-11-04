import {
  MAP_RASTER_TILES_URL_GEOAPIFY,
  MAP_RASTER_TILES_URL_GEOAPIFY_DARK,
  MAP_RASTER_TILES_URL_OSM,
} from '@/consts'
import { useThemeStore } from '@/stores/theme'
import { geoapifyApiKey, isDev, isTest } from '@/helper/server-data'

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
