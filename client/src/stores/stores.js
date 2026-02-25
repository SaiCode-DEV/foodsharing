import Vue from 'vue'
import {
  listStoresForUser,
  getStoreMember,
  getStoreInformation,
  getStorePermissions,
  listStoreTeamMembershipRequests,
  listStoreTeamInvitations,
} from '@/api/stores'
import { getRegionOptions } from '@/api/regions'

export const STORE_TEAM_STATE = Object.freeze({
  UNVERIFIED: 0,
  ACTIVE: 1,
  JUMPER: 2,
  SLEEPING: 3,
  MANAGE_ROLE: 4,
  HYGIENE: 5,
})

export const MAX_LEN_FOR_PUBLIC_INFO = 520

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

export const COOPERATION_STATUS = Object.freeze({
  NO_CONTACT: 1,
  IN_NEGOTIATION: 2,
  COOPERATION_STARTING: 3,
  DOES_NOT_WANT_TO_WORK_WITH_US: 4,
  COOPERATION_ESTABLISHED: 5,
  GIVES_TO_OTHER_CHARITY: 6,
  PERMANENTLY_CLOSED: 7,
  UNCLEAR: 0,
})

export const store = Vue.observable({
  stores: [],
  metadata: {},
  storeMember: null,
  storeInformation: null,
  permissions: {},
  regionOptions: {},
  applications: [],
  invitations: [],
  log: [],
})

export const STORE_PUBLICITY_AND_STICKER_OPTIONS = Object.freeze({
  NO: 0,
  YES: 1,
  NOT_CHOSEN: 2,
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
  has (id) {
    return store.stores.find(store => store.id === id)
  },
  getStoreMember () {
    return store.storeMember ?? []
  },
  isStoreMembersLoaded () {
    return store.storeMember !== null
  },
  getStoreInformation () {
    return store.storeInformation
  },
  getStorePermissions () {
    return store.permissions
  },
  getStoreRegionOptions () {
    return store.regionOptions
  },
  getStoreApplications () {
    return store.applications
  },
  getStoreInvitations () {
    return store.invitations
  },
  isManager (userId) {
    return getters.getStoreMember.find(user => user.id === userId && user.verantwortlich === 1)
  },
  getFilteredStoreLog (actionIds, userId) {
    return store.log.filter(entry =>
      actionIds.includes(entry.actionType) &&
      (userId === entry.actor.id),
    )
  },
}

export const mutations = {
  /**
   *  TODO: refactor this store further
   * @deprecated use stores/store.js instead
   */
  async fetch (force = false, userId) {
    if (!store.length || force) {
      // this method actually does not what it says, I fixed it.
      // But in the navigation and dashboard status box it seems useful to only show "active" stores.
      // For now we can give an additional parameter to filter out "unactive" stores, like before
      store.stores = await listStoresForUser(true, userId)
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
    store.regionOptions = await getRegionOptions(regionId)
  },
  async loadStoreApplications (storeId) {
    store.applications = await listStoreTeamMembershipRequests(storeId)
  },
  async loadStoreInvitations (storeId) {
    store.invitations = await listStoreTeamInvitations(storeId)
  },
}

export default { store, getters, mutations }
