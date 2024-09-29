import Vue from 'vue'
import { deleteBells, getBellList, markBellsAsRead } from '@/api/bells'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'

const bellsRateLimitInterval = 60000 // 1 minute in milliseconds
const cacheRequestName = 'bells'
const pageSize = 20

export const store = Vue.observable({
  bells: [],
  limit: pageSize,
  finishedFirstLoad: false,
})

export const getters = {
  get: () => store.bells,
  getUnreadCount: () => {
    const count = store.bells.filter(b => !b.isRead).length
    const maybeMore = !(count < store.bells.length || getters.getAreAllLoaded())
    return { count, maybeMore }
  },
  getAreAllLoaded: () => store.finishedFirstLoad && store.bells.length < store.limit,
}

// even with "pagination", allways fetch all pages 1 to n to prevent invalid states if the bells changed
export const mutations = {
  async fetch (withoutCache = false) {
    try {
      if (await getCacheInterval(cacheRequestName, bellsRateLimitInterval) || withoutCache) {
        store.bells = await getBellList(store.limit)

        await setCache(cacheRequestName, store.bells)
        store.finishedFirstLoad = true
      } else {
        store.bells = await getCache(cacheRequestName)
        store.limit = Math.max(pageSize, Math.ceil(store.bells.length / pageSize) * pageSize)
        store.finishedFirstLoad = true
      }
    } catch (e) {
      console.error('Error fetching bells:', e)
    }
  },
  async delete (ids) {
    try {
      await deleteBells(ids)
      store.bells = store.bells.filter(b => !ids.includes(b.id))
      await setCache(cacheRequestName, store.bells)
      await this.fetch(true)
    } catch (err) {
      console.log(err)
      throw err
    }
  },
  markAsRead (bell) {
    const bellsToMarkAsRead = this.allBellsWithSameHref(bell)
    this.markBells(bellsToMarkAsRead)
  },
  markNewBellsAsRead () {
    const bellsToMarkAsRead = store.bells.filter(bell => !bell.isRead)
    this.markBells(bellsToMarkAsRead)
  },
  allBellsWithSameHref (bell) {
    return store.bells.filter(b => b.href === bell.href)
  },
  async markBells (bellsToMarkAsRead) {
    if (bellsToMarkAsRead.length > 0) {
      const ids = bellsToMarkAsRead.map(bell => bell.id)
      bellsToMarkAsRead.forEach(bell => { bell.isRead = true })

      try {
        await Promise.all([
          await setCache(cacheRequestName, store.bells),
          await markBellsAsRead(ids),
        ])
      } catch (err) {
        console.error('Error marking bells as read:', err)
      }
    }
  },
  async loadMore () {
    if (store.bells.length === store.limit) {
      store.limit += pageSize
      store.finishedFirstLoad = false
    }
    await this.fetch(true)
  },
}

export default { store, getters, mutations }
