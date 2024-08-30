import Vue from 'vue'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'
import { getMailUnreadCount } from '@/api/mailbox'
import { getDetails } from '@/api/user'
import serverData from '@/helper/server-data'
import { ROLE } from '@/consts'

const mailUnreadCountRateLimitInterval = 300000 // 5 minutes in milliseconds
const userDetailsRateLimitInterval = 60000 // 1 minute in milliseconds

export const store = Vue.observable({
  mailUnreadCount: 0,
  details: {},
  locations: serverData.locations, // null if the user is not logged in or does not have a home address
  user: serverData.user,
  permissions: serverData.permissions,
  isLoggedIn: serverData.user?.id !== null,
})

const fetching = new Set()

export const SLEEP_STATUS = Object.freeze({
  NONE: 0,
  TEMP: 1,
  FULL: 2,
})

export const getters = {
  isLoggedIn () {
    return store.isLoggedIn
  },
  isSleeping () {
    return store.details?.isSleeping
  },
  isVerified () {
    return store.details?.isVerified
  },
  isFoodsaver () {
    return store.user?.isFoodsaver
  },
  isOrga () {
    return store.details?.role >= ROLE.ORGA
  },
  isStoreManager () {
    return store.details?.role >= ROLE.STORE_MANAGER
  },
  isAmbassador () {
    return store.details?.role >= ROLE.AMBASSADOR
  },
  getUser () {
    return store.user
  },
  getUserId () {
    return store.user?.id
  },
  getUserDetails () {
    return store.details
  },
  getMobilePhoneNumber () {
    return store.details?.mobile
  },
  getPhoneNumber () {
    return store.details?.landline
  },
  getAvatar () {
    return store.user?.avatar
  },
  getUserFirstName  () {
    return store.user?.firstname
  },
  getUserLastName () {
    return store.user?.lastname || ''
  },
  hasHomeRegion () {
    return store.user?.homeRegionId > 0
  },
  getHomeRegion () {
    return store.user?.homeRegionId
  },
  getHomeRegionName () {
    return store.details?.regionName
  },
  hasCalendarToken () {
    return store.user?.hasCalendarToken !== null || false
  },
  hasMailBox () {
    return store.user?.hasMailbox || false
  },
  getMailUnreadCount () {
    if (store.mailUnreadCount > 0) {
      return store.mailUnreadCount < 99 ? store.mailUnreadCount : '99+'
    }
    return null
  },
  getStats () {
    return store.details?.stats || {}
  },
  hasLocations () {
    return store.locations?.lat && store.locations?.lon
  },
  /**
   * Returns the user's home coordinates as a {lat, lon} object. Returns {lat: 0, lon: 0} if the user is not logged in
   * or does not have home coordinates. The default value is only used to prevent errors. Users should check with
   * hasLocations() if this function will return the default value.
   */
  getLocations () {
    return store.locations || { lat: 0, lon: 0 }
  },
  getPermissions () {
    return store.permissions || {}
  },
  hasAdminPermissions () {
    const permissions = Object.entries(store.permissions)
    return permissions.some(([key, value]) => ['administrateBlog', 'editQuiz', 'administrateRegions', 'manageMailboxes', 'editContent'].includes(key) && value)
  },
  hasBouncingEmail () {
    return false
    // return store.user?.bouncingEmail
  },
  hasActiveEmail () {
    return true
    // return store.user?.email_is_activated
  },
}

export const mutations = {
  async fetchDetails () {
    if (fetching.has('details')) return
    fetching.add('details')
    const cacheRequestName = 'userDetails'
    try {
      if (await getCacheInterval(cacheRequestName, userDetailsRateLimitInterval)) {
        store.details = await getDetails()

        await setCache(cacheRequestName, store.details)
      } else {
        store.details = await getCache(cacheRequestName)
      }
    } catch (e) {
      console.error('Error fetching user details:', e)
    }
    fetching.delete('details')
  },
  async fetchMailUnreadCount () {
    const cacheRequestName = 'mailUnreadCount'
    try {
      if (await getCacheInterval(cacheRequestName, mailUnreadCountRateLimitInterval)) {
        store.mailUnreadCount = await getMailUnreadCount()

        await setCache(cacheRequestName, store.mailUnreadCount)
      } else {
        store.mailUnreadCount = await getCache(cacheRequestName)
      }
    } catch (e) {
      console.error('Error fetching mail unread count:', e)
    }
  },
}

export default { store, getters, mutations }
