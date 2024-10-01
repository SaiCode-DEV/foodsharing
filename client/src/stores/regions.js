import { defineStore } from 'pinia'
import { joinRegion, listRegionChildren, listRegionMembers } from '@/api/regions'
import { url } from '@/helper/urls'

export const REGION_UNIT_TYPE = Object.freeze({
  CITY: 1,
  DISTRICT: 2,
  REGION: 3,
  FEDERAL_STATE: 5,
  COUNTRY: 6,
  WORKING_GROUP: 7,
  BIG_CITY: 8,
  PART_OF_TOWN: 9,
})

export const SELECTABLE_REGION_TYPES = Object.freeze([
  REGION_UNIT_TYPE.CITY,
  REGION_UNIT_TYPE.BIG_CITY,
  REGION_UNIT_TYPE.PART_OF_TOWN,
])

export const WORKGROUP_FUNCTION = Object.freeze({
  WELCOME: 1,
  VOTING: 2,
  FSP: 3,
  STORES_COORDINATION: 4,
  REPORT: 5,
  MEDIATION: 6,
  ARBITRATION: 7,
  FSMANAGEMENT: 8,
  PR: 9,
  MODERATION: 10,
  BOARD: 11,
  ELECTION: 12,
})

export const SUB_PAGE = Object.freeze({
  FORUM: 'forum',
  AMBASSADOR_FORUM: 'botforum',
  EVENTS: 'events',
  FOODSHARINGPOINT: 'fairteiler',
  POLLS: 'polls',
  MEMBERS: 'members',
  STATISTIC: 'statistic',
  PIN: 'pin',
  WALL: 'wall',
  APPLICATIONS: 'applications',
  OPTIONS: 'options',
  ACHIEVEMENTS: 'achievements',
})

export const useRegionStore = defineStore('region', {
  state: () => ({
    regions: [],
    selectedRegionChildren: [],
    memberList: [],
  }),
  getters: {
    findRegion: (state) => (regionId) => {
      return state.regions.find(region => region.id === regionId)
    },
  },
  actions: {
    async fetchSelectedRegionChildren (regionId) {
      this.selectedRegionChildren = await listRegionChildren(regionId)
    },
    async joinRegion (regionId) {
      await joinRegion(regionId)
      document.location.href = url('relogin_and_redirect_to_url', url('region_forum', regionId))
    },
    async fetchMemberList (regionId) {
      this.memberList = await listRegionMembers(regionId)
    },
  },
})
