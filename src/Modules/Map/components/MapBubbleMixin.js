import { pulseError } from '@/script'
import MapPopup from './MapPopup.vue'

export default {
  components: { MapPopup },
  data: () => ({
    loading: false,
  }),
  methods: {
    async timedFetchAction (fetchPromise, modalId, dataHandler, waitThreshold = 200) {
      // Displays the modal as soon as the data is loaded or the wait threshold is over. This removes the display jitter that occurs when displaying the loading animation for a fraction of a second.
      this.loading = true
      try {
        const timeToDisplayPromise = new Promise(resolve => setTimeout(() => resolve(false), waitThreshold))
        const loadedQuickly = await Promise.any([fetchPromise, timeToDisplayPromise])
        if (loadedQuickly) {
          dataHandler(loadedQuickly)
          this.$bvModal.show(modalId)
        } else {
          this.$bvModal.show(modalId)
          dataHandler(await fetchPromise)
        }
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
      this.loading = false
    },
  },
}
