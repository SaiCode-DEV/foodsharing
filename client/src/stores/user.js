import { defineStore } from 'pinia'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'
import { getMailUnreadCount } from '@/api/mailbox'
import { getDetails, getUserProfileSettings } from '@/api/user'
import serverData from '@/helper/server-data'
import { ROLE } from '@/consts'
import { BROADCAST_TYPE, channel } from '@/broadcastChannel'

const mailUnreadCountRateLimitInterval = 300000 // 5 minutes in milliseconds
const userDetailsRateLimitInterval = 60000 // 1 minute in milliseconds
const userprofileSettingsRateLimitInterval = 60000 // 1 minutes in milliseconds

export const useUserStore = defineStore('user', {
  state: () => ({
    mailUnreadCount: 0,
    details: {},
    settings: {},
    locations: serverData.locations, // null if the user is not logged in or does not have a home address
    user: serverData.user,
    permissions: serverData.permissions,
    fetching: {},
    clearingForLogout: false,
  }),
  getters: {
    isLoadingFinished: (state) => Object.keys(state.details || {}).length > 0,
    isSleeping: (state) => state.details?.isSleeping,
    isVerified: (state) => state.details?.isVerified,
    isFoodsaver: (state) => state.user?.isFoodsaver,
    isLoggedIn: (state) => state.user?.id !== null,
    isOrga: (state) => state.details?.role >= ROLE.ORGA,
    isStoreManager: (state) => state.details?.role >= ROLE.STORE_MANAGER,
    isAmbassador: (state) => state.details?.role >= ROLE.AMBASSADOR,
    getUser: (state) => state.user,
    getUserId: (state) => state.user?.id,
    getUserDetails: (state) => state.details,
    getUserSettings: (state) => state.settings,
    getMobilePhoneNumber: (state) => state.details?.mobile,
    getPhoneNumber: (state) => state.details?.landline,
    getAvatar: (state) => state.user?.avatar,
    getUserFirstName: (state) => state.user?.firstname,
    getUserLastName: (state) => state.user?.lastname || '',
    getEmailAddress: (state) => state.details?.email,
    hasHomeRegion: (state) => state.user?.homeRegionId > 0,
    getHomeRegion: (state) => state.user?.homeRegionId,
    getHomeRegionName: (state) => state.details?.regionName,
    hasCalendarToken: (state) => state.details?.hasCalendarToken,
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
      // Whitelist of permissions that show the admin menu
      return permissions.some(([key, value]) => [
        'mayAdministrateOAuthClients',
        'mayAdministrateEmailBlocklist',
        'administrateBlog',
        'editQuiz',
        'handleReports',
        'editContent',
        'administrateRegions',
        'editAchievements',
      ].includes(key) && value)
    },
    hasBouncingEmail: () => false,
    hadPassport () {
      if (!this.isLoadingFinished) return null
      return this.details?.lastPassUntilValid !== null
    },
    isPassportInvalid () {
      if (!this.isLoadingFinished) return null
      return this.details?.lastPassUntilValid ? (this.details.lastPassUntilValidInDays <= PASSPORT_STATUS.INVALID) : true
    },
    isPassportInvalidSoon: (state) => {
      return state.details?.lastPassUntilValid ? (state.details.lastPassUntilValidInDays <= PASSPORT_STATUS.INVALID_SOON_WARNING_TIME) : false
    },
  },
  actions: {
    async fetchDetails (force = false) {
      if (this.clearingForLogout) return
      if ('details' in this.fetching) return this.fetching.details
      let resolver
      this.fetching.details = new Promise(resolve => { resolver = resolve })
      const cacheRequestName = 'userDetails'
      try {
        if (force || await getCacheInterval(cacheRequestName, userDetailsRateLimitInterval)) {
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
    async fetchProfileSettings (force = false) {
      if (this.clearingForLogout) return
      if ('profileSettings' in this.fetching) return this.fetching.profileSettings
      let resolver
      this.fetching.profileSettings = new Promise(resolve => { resolver = resolve })
      const cacheRequestName = 'profileSettings'
      try {
        if (force || await getCacheInterval(cacheRequestName, userprofileSettingsRateLimitInterval)) {
          this.settings = await getUserProfileSettings(this.getUserId)
          await setCache(cacheRequestName, this.settings)
        } else {
          this.settings = await getCache(cacheRequestName)
        }
      } catch (e) {
        console.error('Error fetching profile settings:', e)
      }
      delete this.fetching.profileSettings
      resolver()
      return this.settings
    },
    async fetchMailUnreadCount (force = false) {
      if (this.clearingForLogout) return
      const cacheRequestName = 'mailUnreadCount'
      try {
        if (force || await getCacheInterval(cacheRequestName, mailUnreadCountRateLimitInterval)) {
          await this.updateMailUnreadCount(await getMailUnreadCount())
        } else {
          this.mailUnreadCount = await getCache(cacheRequestName)
        }
      } catch (e) {
        console.error('Error fetching mail unread count:', e)
      }
    },
    async updateMailUnreadCount (unreadCount) {
      if (this.clearingForLogout) return
      this.mailUnreadCount = unreadCount
      await setCache('mailUnreadCount', unreadCount)
      channel.postMessage({
        type: BROADCAST_TYPE.UPDATE_MAIL_UNREAD_COUNT,
        unreadCount,
      })
    },
    clearForLogout () {
      // Prevent refetching data while logging out
      this.clearingForLogout = true

      // Clear persisted state
      this.mailUnreadCount = 0
      this.details = {}

      // Flush immediately before the page navigates away
      this.$persist()
    },
  },
  persist: {
    pick: ['mailUnreadCount', 'details'],
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

export const VERIFIED_FILTER_OPTIONS = Object.freeze({
  ALL: null,
  VERIFIED: 1,
  UNVERIFIED: 2,
})

export const PASSPORT_STATUS = Object.freeze({
  INVALID: 0,
  INVALID_SOON_WARNING_TIME: 90,
})
