import { useDonationStore } from '@/stores/donation'

export function useNavFilter() {
  const donationStore = useDonationStore()

  /**
   * Filters sub-items within a navigation category based on active campaigns.
   */
  function withVisibleItems(category) {
    if (!category || !category.items) return category
    return {
      ...category,
      items: category.items.filter(
        item => !item.requiresCampaign || donationStore.showCampaignCard,
      ),
    }
  }

  /**
   * Filters an entire navigation object or array.
   */
  function filterNavData(navData) {
    if (Array.isArray(navData)) {
      return navData.map(withVisibleItems)
    }
    if (typeof navData === 'object' && navData !== null) {
      return Object.keys(navData).map(key => withVisibleItems(navData[key]))
    }
    return navData
  }

  return {
    withVisibleItems,
    filterNavData,
  }
}
