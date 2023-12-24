import Vue from 'vue'
import { joinRegion, listRegionChildren, listRegionMembers } from '@/api/regions'
import { url } from '@/helper/urls'

export const REGION_UNIT_TYPE = Object.freeze({
  UNDEFINED: 0,
  CITY: 1,
  DISTRICT: 2,
  REGION: 3,
  FEDERAL_STATE: 5,
  COUNTRY: 6,
  WORKING_GROUP: 7,
  BIG_CITY: 8,
  PART_OF_TOWN: 9,
})

export const store = Vue.observable({
  regions: [],
  choosedRegionChildren: [],
  memberList: [],

})

export const getters = {
  get () {
    return store.regions
  },

  getChoosedRegionChildren () {
    return store.choosedRegionChildren
  },

  find (regionId) {
    return store.regions.find(region => region.id === regionId)
  },
  getMemberList () {
    return store.memberList
  },
}

export const mutations = {
  set (regions) {
    store.regions = regions
  },

  async fetchChoosedRegionChildren (regionId) {
    store.choosedRegionChildren = await listRegionChildren(regionId)
    return store.choosedRegionChildren
  },

  async joinRegion (regionId) {
    await joinRegion(regionId)
    document.location.href = url('relogin_and_redirect_to_url', url('region_forum', regionId))
  },
  async fetchMemberList (regionId) {
    store.memberList = await listRegionMembers(regionId)
  },
}

export default { store, getters, mutations }
