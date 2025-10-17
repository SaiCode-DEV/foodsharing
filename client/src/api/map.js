import { get } from './base'

export function getMapMarkers (type, status) {
  const statusParams = Object.entries(status).map(([key, value]) => `${key}=${value}`).join('&')
  return get(`/map/markers/${type}?${statusParams}`)
}

export function getCommunityBubbleContent (regionId) {
  return get(`/map/regions/${regionId}`)
}

export function getBasketBubbleContent (basketId) {
  return get(`/map/baskets/${basketId}`)
}

export function getStoreBubbleContent (storeId) {
  return get(`/map/stores/${storeId}`)
}

export function getFoodSharePointBubbleContent (foodSharePointId) {
  return get(`/map/foodSharePoint/${foodSharePointId}`)
}

export function getEventBubbleContent (eventId) {
  return get(`/map/event/${eventId}`)
}

export function getUserBubbleContent (userId) {
  return get(`/map/user/${userId}`)
}
