import { get, post, remove, put, patch } from './base'

export async function getBaskets () {
  const baskets = await get('/user/current/baskets')
  return baskets.map(basket => {
    basket.createdAt = new Date(basket.createdAt * 1000)
    basket.updatedAt = new Date(basket.updatedAt * 1000)
    basket.requests = basket.requests.map(request => {
      request.time = new Date(request.time * 1000)
      return request
    })
    return basket
  })
}

export async function requestBasket (basketId, message) {
  return (post(`/baskets/${basketId}/request`, {
    message: message,
  }))
}

export async function updateRequestStatus (basketId, requesterId, status) {
  return (patch(`/baskets/${basketId}/requests/${requesterId}/status`, {
    status,
  }))
}

export async function withdrawBasketRequest (basketId) {
  return (post(`/baskets/${basketId}/withdraw`))
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
  return put(`/baskets/${basketId}`, basketData)
}
