// export const MAP_TILES_URL = 'https://maps.geoapify.com/v1/styles/klokantech-basic/style.json?apiKey='
export const MAP_RASTER_TILES_URL_GEOAPIFY = 'https://maps.geoapify.com/v1/tile/klokantech-basic/{z}/{x}/{y}.png?apiKey='
export const MAP_RASTER_TILES_URL_OSM = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
export const MAP_ATTRIBUTION = 'Powered by <a href="https://www.geoapify.com/">Geoapify</a> | <a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>'
export const MAP_GEOCODING_ATTRIBUTION = MAP_ATTRIBUTION + ' | Geocoding by <a href="https://photon.komoot.io">Komoot Photon</a>'
export const ROLE = Object.freeze({
  FOODSHARER: 0,
  FOODSAVER: 1,
  STORE_MANAGER: 2,
  AMBASSADOR: 3,
  ORGA: 4,
  SITE_ADMIN: 5, // this role is not used currently
})
export const MAX_UPLOAD_FILE_SIZE = 1572864 // 1.5 * 1024 * 1024
export const REGION_IDS = Object.freeze({
  EUROPE: 741,
  GERMANY: 1,
  AUSTRIA: 63,
  SWITZERLAND: 106,
})

export const HTTP_RESPONSE = Object.freeze({
  BAD_REQUEST: 400,
  UNAUTHORIZED: 401,
  FORBIDDEN: 403,
  NOT_FOUND: 404,
  CONFLICT: 409,
  UNPROCESSABLE_ENTITY: 422,
})
