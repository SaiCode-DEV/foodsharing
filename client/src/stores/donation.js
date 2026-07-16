import { defineStore } from 'pinia'
import { getCache, getCacheInterval, setCache } from '@/helper/cache'
import { getDonationData } from '@/api/donation'

const donationDataRateLimitInterval = 60000 // 10 minute in milliseconds
export const CAMPAIGN_CONTENT_IDS = {
  PART_2: 2,
  PART_3: 3,
}

export const CAROUSEL_DIR = '../donation-campaign/'

export const useDonationStore = defineStore('donation', {
  state: () => ({
    donationData: {},
    fetching: {},
  }),
  getters: {
    showDonationModal: (state) => state.donationData?.showDonationModal || false,
    showDonationModalInHoursForLoggedInUsers: (state) => state.donationData?.showDonationModalInHoursForLoggedInUsers || 0,
    showDonationModalInHoursForLoggedOutUsers: (state) => state.donationData?.showDonationModalInHoursForLoggedOutUsers || 0,
    showCampaignCard: (state) => state.donationData?.showCampaignCard || false,
    showDonationCampaignPart1: (state) => state.donationData?.showDonationCampaignPart1 || false,
    showDonationCampaignPart2: (state) => state.donationData?.showDonationCampaignPart2 || false,
    showDonationCampaignGallery: (state) => state.donationData?.showDonationCampaignGallery || false,
    iframeOneTimeUrl: (state) => state.donationData?.iframeOneTimeUrl || '',
    iframeFriendshipCircleUrl: (state) => state.donationData?.iframeFriendshipCircleUrl || '',
    iframeCampaignUrl: (state) => state.donationData?.iframeCampaignUrl || '',
    iframeSelfserviceUrl: (state) => state.donationData?.iframeSelfserviceUrl || '',
    donationModalInfoUrl: (state) => state.donationData?.donationModalInfoUrl || '',
    donationModalPopupUrl: (state) => state.donationData?.donationModalPopupUrl || '',
    twingleEmbedConfig () {
      return {
        campaign: { url: this.iframeCampaignUrl, containerType: 'script' },
        friendship_circle: { url: this.iframeFriendshipCircleUrl, containerType: 'script' },
        onetime: { url: this.iframeOneTimeUrl, containerType: 'script' },
        selfservice: { url: this.iframeSelfserviceUrl, containerType: 'iframe' },
      }
    },
    getDonationInfoByProjectId: (state) => (projectId) => {
      return (
        state.donationData?.donationInformations?.find(
          (info) => info.projectId === projectId,
        ) || null
      )
    },
    campaignDonationInfo () {
      return this.getDonationInfoByProjectId(this.donationData?.campaignId)
    },
    friendshipCircleDonationInfo () {
      return this.getDonationInfoByProjectId(this.donationData?.friendshipCircleId)
    },
    oneTimeDonationInfo () {
      return this.getDonationInfoByProjectId(this.donationData?.oneTimeDonationId)
    },
    donationModalInfo () {
      return this.getDonationInfoByProjectId(this.donationData?.donationModalId)
    },
    friendshipCircleDonators () {
      return this.friendshipCircleDonationInfo?.donationProjectStatus?.donators || 0
    },
    oneTimeDonators () {
      return this.oneTimeDonationInfo?.donationProjectStatus?.donators || 0
    },
    donationModalDonators () {
      return this.donationModalInfo?.donationProjectStatus?.donators || 0
    },
  },
  actions: {
    async fetchDonationData (force = false) {
      if ('donationData' in this.fetching) return this.fetching.donationData
      let resolver
      this.fetching.donationData = new Promise(resolve => { resolver = resolve })
      const cacheRequestName = 'donationData'
      try {
        if (force || await getCacheInterval(cacheRequestName, donationDataRateLimitInterval)) {
          this.donationData = await getDonationData()
          await setCache(cacheRequestName, this.donationData)
        } else {
          this.donationData = await getCache(cacheRequestName)
        }
      } catch (e) {
        console.error('Error fetching donation data:', e)
      }
      delete this.fetching.donationData
      resolver()
    },
  },
})
