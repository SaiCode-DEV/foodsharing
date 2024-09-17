import { defineStore } from 'pinia'
import { getFoodSharePoint } from '@/api/foodsharepoints'
export const useFoodSharePointStore = defineStore('foodSharePoint', {
  state: () => ({
    foodSharePoint: {},
  }),
  getters: {
    coordinates: (state) => {
      return {
        lat: state.foodSharePoint?.lat || null,
        lon: state.foodSharePoint?.lon || null,
      }
    },
  },
  actions: {
    async fetchFoodSharePoint (foodSharePointId) {
      try {
        this.foodSharePoint = await getFoodSharePoint(foodSharePointId)
      } catch (e) {
        console.error('Error fetching foodSharePoint:', e)
      }
    },

  },
})
