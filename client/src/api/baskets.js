import { get, post, remove, patch } from './base'

export async function getBaskets () {
  return await get('/users/current/baskets')
}

export async function requestBasket (basketId, message) {
  return post(`/baskets/${basketId}/requests`, {
    message,
  })
}

export async function updateRequestStatus (basketId, requesterId, status) {
  return patch(`/baskets/${basketId}/requests/${requesterId}/status?status=${status}`)
}

export async function withdrawBasketRequest (basketId) {
  return remove(`/baskets/${basketId}/requests`)
}

export async function removeBasket (basketId) {
  return remove(`/baskets/${basketId}`)
}

export async function getBasketsNearby (lat, lon, distance = 30) {
  if (lat && lon) {
    return await get(`/baskets/nearby?lat=${lat}&lon=${lon}&distance=${distance}`)
  }
  throw new Error('Missing lat or lon')
}

export async function addBasket (basketData) {
  return post('/baskets', basketData)
}

export async function editBasket (basketId, basketData) {
  return patch(`/baskets/${basketId}`, basketData)
}
