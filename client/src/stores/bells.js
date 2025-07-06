import Vue from 'vue'
import { deleteBells, getBellList, setReadStatus } from '@/api/bells'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'
import { BROADCAST_TYPE, storeSynchronizer } from '@/broadcastChannel'

const bellsRateLimitInterval = 60000 // 1 minute in milliseconds
const cacheRequestName = 'bells'
const pageSize = 20

const reactiveStore = new Vue({
  data: {
    bells: [],
    limit: pageSize,
    finishedFirstLoad: false,
  },
  ...storeSynchronizer(BROADCAST_TYPE.UPDATE_BELLS, 'bells'),
})

export const store = reactiveStore.$data

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
  async markAsRead (bell) {
    const bellsToMarkAsRead = this.allBellsWithSameHref(bell)
    await this.markBells(bellsToMarkAsRead)
  },
  async setReadStatus (bell, isRead) {
    const bellsToMark = this.allBellsWithSameHref(bell)
    await this.markBells(bellsToMark, isRead)
  },
  async markNewBellsAsRead () {
    const bellsToMarkAsRead = store.bells.filter(bell => !bell.isRead)
    await this.markBells(bellsToMarkAsRead)
  },
  allBellsWithSameHref (bell) {
    return store.bells.filter(b => b.href === bell.href)
  },
  async markBells (bellsToMark, isRead = true) {
    if (bellsToMark.length === 0) {
      return
    }

    const ids = bellsToMark.map(bell => bell.id)

    try {
      await setReadStatus(ids, isRead)
      bellsToMark.forEach(bellToMark => {
        bellToMark.isRead = isRead
      })
      await setCache(cacheRequestName, store.bells)
    } catch (err) {
      console.error('Error marking bells:', err)
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
