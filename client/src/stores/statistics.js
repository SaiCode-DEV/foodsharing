import { defineStore } from 'pinia'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'
import { getOverallStatistics } from '@/api/statistics'

const statisticsRateLimitInterval = 86400000 // 24 hours in milliseconds

export const useStatisticsStore = defineStore('statistics', {
  state: () => ({
    overallStatistics: {},
    fetching: {},
  }),
  getters: {
    isLoadingFinished: (state) => Object.keys(state.overallStatistics).length > 0,
    countAllFoodsaver: (state) => state.overallStatistics?.generalStatistic.countAllFoodsaver || 0,
  },
  actions: {
    async fetchOverallStatistics (force = false) {
      if ('overallStatistics' in this.fetching) return this.fetching.overallStatistics
      let resolver
      this.fetching.overallStatistics = new Promise(resolve => { resolver = resolve })
      const cacheRequestName = 'overallStatistics'
      try {
        if (force || await getCacheInterval(cacheRequestName, statisticsRateLimitInterval)) {
          this.overallStatistics = await getOverallStatistics()
          await setCache(cacheRequestName, this.overallStatistics)
        } else {
          this.overallStatistics = await getCache(cacheRequestName)
        }
      } catch (e) {
        console.error('Error fetching overall statistics:', e)
      }
      delete this.fetching.overallStatistics
      resolver()
    },
  },
  persist: {
    pick: ['overallStatistics'],
  },
})
