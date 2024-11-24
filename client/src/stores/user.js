import { defineStore } from 'pinia'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'
import { getMailUnreadCount } from '@/api/mailbox'
import { getDetails } from '@/api/user'
import serverData from '@/helper/server-data'
import { ROLE } from '@/consts'

const mailUnreadCountRateLimitInterval = 300000 // 5 minutes in milliseconds
const userDetailsRateLimitInterval = 60000 // 1 minute in milliseconds

export const useUserStore = defineStore('user', {
  state: () => ({
    mailUnreadCount: 0,
    details: {},
    locations: serverData.locations, // null if the user is not logged in or does not have a home address
    user: serverData.user,
    permissions: serverData.permissions,
    isLoggedIn: serverData.user?.id !== null,
    fetching: {},
  }),
  getters: {
    isSleeping: (state) => state.details?.isSleeping,
    isVerified: (state) => state.details?.isVerified,
    isFoodsaver: (state) => state.user?.isFoodsaver,
    isOrga: (state) => state.details?.role >= ROLE.ORGA,
    isStoreManager: (state) => state.details?.role >= ROLE.STORE_MANAGER,
    isAmbassador: (state) => state.details?.role >= ROLE.AMBASSADOR,
    getUser: (state) => state.user,
    getUserId: (state) => state.user?.id,
    getUserDetails: (state) => state.details,
    getMobilePhoneNumber: (state) => state.details?.mobile,
    getPhoneNumber: (state) => state.details?.landline,
    getAvatar: (state) => state.user?.avatar,
    getUserFirstName: (state) => state.user?.firstname,
    getUserLastName: (state) => state.user?.lastname || '',
    getEmailAddress: (state) => state.details?.email,
    hasHomeRegion: (state) => state.user?.homeRegionId > 0,
    getHomeRegion: (state) => state.user?.homeRegionId,
    getHomeRegionName: (state) => state.details?.regionName,
    hasCalendarToken: (state) => state.user?.hasCalendarToken !== null || false,
    hasMailBox: (state) => state.user?.hasMailbox || false,
    getMailUnreadCount: (state) => {
      if (state.mailUnreadCount > 0) {
        return state.mailUnreadCount < 99 ? state.mailUnreadCount : '99+'
      }
      return null
    },
    getStats: (state) => state.details?.stats || {},
    hasLocations: (state) => state.locations && state.locations.lat !== null && state.locations.lon !== null,
    getLocations: (state) => state.locations || { lat: 0, lon: 0 },
    getPermissions: (state) => state.permissions || {},
    hasAdminPermissions: (state) => {
      const permissions = Object.entries(state.permissions)
      return permissions.some(([key, value]) => !['mayAdministrateUserProfile', 'mayEditUserProfile', 'addStore'].includes(key) && value)
    },
    hasBouncingEmail: () => false,
    hasActiveEmail: () => true,
    isPassportInvalid: (state) => {
      return state.details.lastPassUntilValid ? (state.details.lastPassUntilValidInDays <= PASSPORT_STATUS.INVALID) : false
    },
    isPassportInvalidSoon: (state) => {
      return state.details.lastPassUntilValid ? (state.details.lastPassUntilValidInDays <= PASSPORT_STATUS.INVALID_SOON_WARNING_TIME) : false
    },
  },
  actions: {
    async fetchDetails () {
      if ('details' in this.fetching) return this.fetching.details
      let resolver
      this.fetching.details = new Promise(resolve => { resolver = resolve })
      const cacheRequestName = 'userDetails'
      try {
        if (await getCacheInterval(cacheRequestName, userDetailsRateLimitInterval)) {
          this.details = await getDetails()
          await setCache(cacheRequestName, this.details)
        } else {
          this.details = await getCache(cacheRequestName)
        }
      } catch (e) {
        console.error('Error fetching user details:', e)
      }
      delete this.fetching.details
      resolver()
    },
    async fetchMailUnreadCount () {
      const cacheRequestName = 'mailUnreadCount'
      try {
        if (await getCacheInterval(cacheRequestName, mailUnreadCountRateLimitInterval)) {
          this.mailUnreadCount = await getMailUnreadCount()
          await setCache(cacheRequestName, this.mailUnreadCount)
        } else {
          this.mailUnreadCount = await getCache(cacheRequestName)
        }
      } catch (e) {
        console.error('Error fetching mail unread count:', e)
      }
    },
  },
})

export const SLEEP_STATUS = Object.freeze({
  NONE: 0,
  TEMP: 1,
  FULL: 2,
})

export const PASSPORT_FILTER_OPTIONS = Object.freeze({
  NO_FILTER: null,
  NO_PASSPORT: 1,
  WITH_PASSPORT: 2,
  INVALID_PASSPORT: 3,
})

export const PASSPORT_STATUS = Object.freeze({
  INVALID: 0,
  INVALID_SOON_WARNING_TIME: 30,
})
