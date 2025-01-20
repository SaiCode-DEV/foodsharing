import { defineStore } from 'pinia'
import { getPublicRegionData, getRegionMenu, joinRegion, listRegionChildren, listRegionMembers } from '@/api/regions'
import { url } from '@/helper/urls'
import { REGION_IDS } from '@/consts'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'
const publicRegionRequestName = 'publicRegionData'
const publicRegionCacheInterval = 600_000 // 10 minutes in milliseconds
const regionMenuRequestName = 'regionMenu'
const regionMenuCacheInterval = 600_000 // 10 minutes in milliseconds

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
  REGION_UNIT_TYPE.DISTRICT,
])

export const ACCESSIBLE_REGION_TYPES = Object.freeze([
  REGION_UNIT_TYPE.PART_OF_TOWN,
  REGION_UNIT_TYPE.CITY,
  REGION_UNIT_TYPE.REGION,
  REGION_UNIT_TYPE.DISTRICT,
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
    publicRegions: {},
    regionMenus: {},
  }),
  getters: {
    findRegion: (state) => (regionId) => {
      return state.regions.find(region => region.id === regionId)
    },
    accessibleRegions (state) {
      // TODO: Global working groups and Festivals are filtered out because they have types "City" or "Big city". The
      // filtering can be removed when their type is fixed.
      return state.regions
        .filter(region => ![REGION_IDS.GLOBAL_WORKING_GROUPS, REGION_IDS.FOODSHARING_ON_FESTIVALS].includes(region.id))
        .filter(region => SELECTABLE_REGION_TYPES.includes(region.type))
    },
  },
  actions: {
    async fetchSelectedRegionChildren (regionId) {
      this.selectedRegionChildren = await listRegionChildren(regionId)
      return this.selectedRegionChildren
    },
    async joinRegion (regionId) {
      await joinRegion(regionId)
      document.location.href = url('relogin_and_redirect_to_url', url('region_forum', regionId))
    },
    async fetchMemberList (regionId) {
      this.memberList = await listRegionMembers(regionId)
    },
    async fetchPublicRegionData (id, alwaysUpdate = false) {
      try {
        if (alwaysUpdate || await getCacheInterval(publicRegionRequestName + id, publicRegionCacheInterval)) {
          this.publicRegions[id] = await getPublicRegionData(id)
          await setCache(publicRegionRequestName + id, this.publicRegions[id])
        } else {
          this.publicRegions[id] = await getCache(publicRegionRequestName + id)
        }
        return this.publicRegions[id]
      } catch (e) {
        console.error(`Error fetching public region data for region ${id}:`, e)
        return null
      }
    },
    async fetchRegionMenu (id, alwaysUpdate = false) {
      try {
        if (alwaysUpdate || await getCacheInterval(regionMenuRequestName + id, regionMenuCacheInterval)) {
          this.regionMenus[id] = await getRegionMenu(id)
          await setCache(regionMenuRequestName + id, this.publicRegions[id])
        } else {
          this.regionMenus[id] = await getCache(regionMenuRequestName + id)
        }
        return this.regionMenus[id]
      } catch (e) {
        console.error(`Error fetching region menu data for region ${id}:`, e)
        return null
      }
    },
  },
})
