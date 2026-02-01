import { get, patch, post, remove, put, cachedGet } from './base'
import { cacheKeys } from '@/helper/cache-keys'

export async function listPickups (storeId) {
  const res = await get(`/stores/${storeId}/pickups`)

  return res.map(c => ({
    ...c,
    date: new Date(Date.parse(c.date)),
  }))
}

export async function joinPickup (storeId, pickupDate) {
  const date = pickupDate.toISOString()
  return post(`/stores/${storeId}/pickups/${date}/users/current`)
}

export async function leavePickup (storeId, pickupDate, userId, message, sendKickMessage = true) {
  const date = pickupDate.toISOString()
  return remove(`/stores/${storeId}/pickups/${date}/users/${userId}`, {
    message,
    sendKickMessage,
  })
}

export async function leaveAllPickups (userId, message, sendKickMessage = false) {
  return remove(
    `users/${userId}/pickups`,
    {
      message,
      sendKickMessage,
    },
  )
}

export async function confirmPickup (storeId, pickupDate, userId) {
  const date = pickupDate.toISOString()
  return patch(`/stores/${storeId}/pickups/${date}/users/${userId}`, { isConfirmed: true })
}

export async function checkPickupRuleStore (storeId, pickupDate) {
  const date = pickupDate.toISOString()
  const res = await get(`/stores/${storeId}/pickups/${date}/eligibility`)
  return res.isEligible
}

export async function setPickupSlots (storeId, pickupDate, totalSlots, description) {
  const date = pickupDate.toISOString()
  return put(`/stores/${storeId}/pickups/${date}`, { totalSlots, description })
}

export async function listPickupHistory (storeId, fromDate, toDate) {
  const from = fromDate.toISOString()
  const to = toDate.toISOString()
  let slots = await get(`/stores/${storeId}/pickups/history/${from}/${to}`)
  slots = slots.map(s => ({
    ...s,
    storeId,
    isConfirmed: !!s.confirmed,
    date: new Date(Date.parse(s.date)),
  }))

  // https://github.com/you-dont-need/You-Dont-Need-Lodash-Underscore#_groupby
  return slots.reduce((r, v, i, a, k = v.date_ts) => {
    (r[k] || (r[k] = [])).push(v)
    return r
  }, {})
}

export async function listSameDayAgendaForUser (fsId, onDate) {
  const day = onDate.toISOString()
  const res = await get(`/users/${fsId}/agenda/${day}`)

  return res.map(p => ({
    ...p,
    date: new Date(Date.parse(p.date)),
  }))
}

export async function listRegisteredPickups (fsId) {
  const userId = fsId ?? 'current'
  return await cachedGet(`/users/${userId}/pickups/registered`, {
    ...cacheKeys.listRegisteredPickups(userId),
  })
}

export async function listPickupOptions ({ force = false } = {}) {
  return await cachedGet('/users/current/pickups/options', {
    ...cacheKeys.listPickupOptions(),
    force,
  })
}

export async function listPastPickups (userId, limit, offset) {
  return await get(`/users/${userId}/pickups/history?limit=${limit}&offset=${offset}`)
}

export async function getRegularPickup (storeId) {
  return await get(`/stores/${storeId}/regular-pickups`)
}

export async function editRegularPickup (storeId, regularPickups) {
  return await put(`/stores/${storeId}/regular-pickups`, { regularPickups })
}
