import L from 'leaflet'
// import 'mapbox-gl-leaflet'

import {
  MAP_RASTER_TILES_URL_GEOAPIFY,
  MAP_RASTER_TILES_URL_GEOAPIFY_DARK,
  MAP_RASTER_TILES_URL_OSM, MAP_ATTRIBUTION,
} from '@/consts'
import { isWebGLSupported } from '@/utils'
import { useThemeStore } from '@/stores/theme'
import { geoapifyApiKey, isDev, isTest } from '@/helper/server-data'

/**
 * @deprecated use the Vue component @/components/map/LeafletMap instead
 */
export function initMap (element, center, zoom, maxZoom = 20) {
  const map = L.map(element, {}).setView(center, zoom)

  if (isWebGLSupported()) {
    // L.mapboxGL({
    //   style: MAP_TILES_URL,
    // }).addTo(map)
  } else {
    // WebGL is not supported, fallback to raster tiles
  }
  L.tileLayer(getMapRasterTilesUrl(), { maxZoom: maxZoom }).addTo(map)
  map.attributionControl.setPrefix(MAP_ATTRIBUTION)

  return map
}

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
