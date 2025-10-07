// export const MAP_TILES_URL = 'https://maps.geoapify.com/v1/styles/klokantech-basic/style.json?apiKey='
export const MAP_RASTER_TILES_URL_GEOAPIFY = 'https://maps.geoapify.com/v1/tile/klokantech-basic/{z}/{x}/{y}.png?apiKey='
export const MAP_RASTER_TILES_URL_GEOAPIFY_DARK = 'https://maps.geoapify.com/v1/tile/dark-matter/{z}/{x}/{y}.png?apiKey='
export const MAP_RASTER_TILES_URL_OSM = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
export const MAP_ATTRIBUTION = 'Powered by <a href="https://www.geoapify.com/">Geoapify</a> | <a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>'
export const MAP_GEOCODING_ATTRIBUTION = MAP_ATTRIBUTION
export const ROLE = Object.freeze({
  FOODSHARER: 0,
  FOODSAVER: 1,
  STORE_MANAGER: 2,
  AMBASSADOR: 3,
  ORGA: 4,
  SITE_ADMIN: 5, // this role is not used currently
})
export const MAX_UPLOAD_FILE_SIZE = 1572864 // 1.5 * 1024 * 1024
export const MAX_SUPPORT_TICKET_ATTACHMENT_SIZE = 5242880 // 5 * 1024 * 1024, meaning 5 MB
export const MAX_SUPPORT_TICKET_ATTACHMENT_FILES = 10
export const REGION_IDS = Object.freeze({
  // highest level below root
  EUROPE: 741,
  GLOBAL_WORKING_GROUPS: 392,
  FOODSHARING_ON_FESTIVALS: 1432,

  // second level: countries
  GERMANY: 1,
  AUSTRIA: 63,
  SWITZERLAND: 106,

  STORE_CHAIN_GROUP: 332,
  STORE_CHAIN_GROUP_SWITZERLAND: 1004,
  STORE_CHAIN_GROUP_AUSTRIA: 858,
})

export const HTTP_RESPONSE = Object.freeze({
  NOT_MODIFIED: 304,
  BAD_REQUEST: 400,
  UNAUTHORIZED: 401,
  FORBIDDEN: 403,
  NOT_FOUND: 404,
  CONFLICT: 409,
  UNPROCESSABLE_ENTITY: 422,
  TOO_MANY_REQUESTS: 429,
  INTERNAL_SERVER_ERROR: 500,
})

export const SESSION_STATUS = Object.freeze({
  RUNNING: 0,
  PASSED: 1,
  FAILED: 2,
})

export const ANSWER_RATING = Object.freeze({
  WRONG: 0,
  RIGHT: 1,
  NEUTRAL: 2,
})

export const QUIZ_ID = Object.freeze({
  FOODSAVER: 1,
  STORE_MANAGER: 2,
  AMBASSADOR: 3,
  HYGIENE: 4,
})

export const REPORT_REASON_OPTIONS = Object.freeze({
  SIMPLE: 1,
  EXTENDED: 2,
})

export const EVENT_TYPE = Object.freeze({
  OFFLINE: 0,
  ONLINE: 1,
  OTHER: 2,
})

export const BLOG_POST_OPTIONS = Object.freeze({
  IMAGE: { WIDTH: 500, HEIGHT: 161 },
})

/**
 * Whitelist of allowed mime types. The same list exists in the backend in UploadsRestController. If you change
 * something, please also adjust that list.
 */
export const ACCEPTED_FILE_TYPES =
    // Image files
    'image/*,' +

    // Documents
    'application/pdf,' +
    'text/plain,' +
    'application/rtf,' +

    // Audio and Video files
    'audio/*,' +
    'video/*,' +

    // Compressed archives
    'application/zip,' +
    'application/gzip,' +
    'application/x-7z-compressed,' +
    'application/x-tar,' +
    'application/x-bzip2,' +
    'application/x-xz,' +

    // Data formats
    'text/csv,' +
    'application/json,' +
    'application/xml,' +
    'text/xml,' +
    'application/x-yaml,' +

    // Microsoft Office files
    'application/msword,' +
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document,' +
    'application/vnd.ms-excel,' +
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,' +
    'application/vnd.ms-powerpoint,' +
    'application/vnd.openxmlformats-officedocument.presentationml.presentation,' +

    // OpenDocument files
    'application/vnd.oasis.opendocument.text,' +
    'application/vnd.oasis.opendocument.spreadsheet,' +
    'application/vnd.oasis.opendocument.presentation'
