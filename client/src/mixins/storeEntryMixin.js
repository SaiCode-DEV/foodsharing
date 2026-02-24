import {
  STORE_CATEGORY_PICKUP,
  STORE_CATEGORY_GIVING,
  STORE_CATEGORY_ORGA,
} from '@/constants/storeCategoryTypes'

export default {
  computed: {
    pickupStringStatus () {
      if (this.entry && this.entry.pickupStatus > 0) {
        return this.$t('store.tooltip_' + ['yellow', 'orange', 'red'][this.entry.pickupStatus - 1]) + ' (' + this.storeCategoryTypeStatus + ')'
      }
      return this.storeCategoryTypeStatus
    },
    storeCategoryTypeStatus () {
      return this.entry ? this.$t('categories.types.' + this.entry.categoryType) : ''
    },
    storeCategoryTypeIcon () {
      if (!this.entry) return 'fa-question-circle'
      const type = parseInt(this.entry?.categoryType, 10)
      if (type === STORE_CATEGORY_PICKUP) {
        return 'fa-shopping-cart'
      } else if (type === STORE_CATEGORY_GIVING) {
        return 'fa-hand-holding-hand'
      } else if (type === STORE_CATEGORY_ORGA) {
        return 'fa-clipboard-list'
      } else {
        return 'fa-question-circle'
      }
    },
  },
}
