import { get } from './base'

export function getMapMarkers (type, status) {
  const statusParams = Object.entries(status).filter(entry => entry[1] !== null).map(([key, value]) => `${key}=${value}`).join('&')
  const kebabCaseType = type.split(/(?=[A-Z])/).join('-').toLowerCase()
  return get(`/map/markers/${kebabCaseType}?${statusParams}`)
}

export function getRegionBubbleContent (regionId) {
  return get(`/map/markers/regions/${regionId}`)
}

export function getBasketBubbleContent (basketId) {
  return get(`/map/markers/baskets/${basketId}`)
}

export function getStoreBubbleContent (storeId) {
  return get(`/map/markers/stores/${storeId}`)
}

export function getFoodSharePointBubbleContent (foodSharePointId) {
  return get(`/map/markers/food-share-points/${foodSharePointId}`)
}

export function getEventBubbleContent (eventId) {
  return get(`/map/markers/events/${eventId}`)
}

export function getUserBubbleContent (userId) {
  return get(`/map/markers/users/${userId}`)
}
