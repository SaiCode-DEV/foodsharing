import Vue from 'vue'
import {
  listStoresForCurrentUser,
  getStoreMetaData,
  getStoreMember,
  getStoreInformation,
  getStorePermissions,
  listStoreTeamMembershipRequests,
  getStoreLog,
} from '@/api/stores'
import { getRegionOptions } from '@/api/regions'

export const STORE_TEAM_STATE = Object.freeze({
  UNVERIFIED: 0,
  ACTIVE: 1,
  JUMPER: 2,
  SLEEPING: 3,
  MANAGE_ROLE: 4,
})

export const STORE_LOG_ACTION = Object.freeze({
  REQUEST_TO_JOIN: 1,
  REQUEST_DECLINED: 2,
  REQUEST_APPROVED: 3,
  ADDED_WITHOUT_REQUEST: 4,
  MOVED_TO_JUMPER: 5,
  MOVED_TO_TEAM: 6,
  REMOVED_FROM_STORE: 7,
  LEFT_STORE: 8,
  APPOINT_STORE_MANAGER: 9,
  REMOVED_AS_STORE_MANAGER: 10,
  SIGN_UP_SLOT: 11,
  SIGN_OUT_SLOT: 12,
  REMOVED_FROM_SLOT: 13,
  SLOT_CONFIRMED: 14,
  DELETED_FROM_WALL: 15,
  REQUEST_CANCELLED: 16,
})

export const store = Vue.observable({
  stores: [],
  metadata: {},
  storeMember: [],
  storeInformation: null,
  permissions: null,
  regionPickupRule: {},
  applications: [],
  log: [],
})

export const getters = {
  getAll () {
    return store.stores.length > 0 ? store.stores : []
  },

  getOthers () {
    const others = Array.from(store.stores).filter(s => !s.isManaging && s.membershipStatus === STORE_TEAM_STATE.ACTIVE)
    return others.length > 0 ? others : []
  },

  getManaging () {
    const managing = Array.from(store.stores).filter(s => s.isManaging)
    return managing.length > 0 ? managing : []
  },

  getJumping () {
    const jumping = Array.from(store.stores).filter(s => s.membershipStatus === STORE_TEAM_STATE.JUMPER)
    return jumping.length > 0 ? jumping : []
  },

  hasStores () {
    return store.stores.length > 0
  },

  getStoreCategoryTypes () {
    return store.metadata.categories ?? []
  },

  getStoreConvinceStatusTypes () {
    return store.metadata.convinceStatus ?? []
  },

  getStoreWeightTypes () {
    return store.metadata.weight ?? []
  },

  getStoreCooperationStatus () {
    return store.metadata.status ?? []
  },

  getGrocerieTypes () {
    return store.metadata.groceries ?? []
  },

  getStoreChains () {
    return store.metadata.storeChains ?? []
  },

  getPublicTimes () {
    return store.metadata.publicTimes ?? []
  },

  getMaxCountPickupSlot () {
    return store.metadata.maxCountPickupSlot ?? 0
  },

  has (id) {
    return store.stores.find(store => store.id === id)
  },
  getStoreMember () {
    return store.storeMember
  },
  getStoreInformation () {
    return store.storeInformation
  },
  getStorePermissions () {
    return store.permissions
  },
  getStoreRegionOptions () {
    return store.regionPickupRule
  },
  getStoreApplications () {
    return store.applications
  },
  isManager (userId) {
    return store.storeMember.find(user => user.id === userId && user.verantwortlich === 1)
  },
  getFilteredStoreLog (actionIds, userId) {
    return store.log.filter(entry =>
      actionIds.includes(entry.action_id) &&
      (userId === entry.acting_foodsaver.id),
    )
  },
}

export const mutations = {
  async fetch (force = false) {
    if (!store.length || force) {
      store.stores = await listStoresForCurrentUser()
      store.metadata = await getStoreMetaData()
    }
  },
  async loadStoreMember (storeId) {
    store.storeMember = await getStoreMember(storeId)
  },
  async loadStoreInformation (storeId) {
    store.storeInformation = await getStoreInformation(storeId)
  },
  async loadPermissions (storeId) {
    store.permissions = await getStorePermissions(storeId)
  },
  async loadGetRegionOptions (regionId) {
    store.regionPickupRule = await getRegionOptions(regionId)
  },
  async loadStoreApplications (storeId) {
    store.applications = await listStoreTeamMembershipRequests(storeId)
  },
  async loadStoreLog (storeId, calendarInterval) {
    const actions = [STORE_LOG_ACTION.SIGN_UP_SLOT]
    const intervalPerDays = calendarInterval / 3600 / 24

    const fromDate = new Date()
    fromDate.setDate(fromDate.getDate() - intervalPerDays)
    const today = new Date()
    today.setDate(today.getDate() + 1) // buffer to include everything from today

    store.log = await getStoreLog(storeId, actions, [fromDate, today])
  },
}

export default { store, getters, mutations }
