<template>
  <div>
    <b-card v-if="donationStore.showDonationCampaignPart1" class="mb-5">
      <div v-if="isContentLoading" class="ml-2 my-2">
        <b-skeleton width="85%" />
        <b-skeleton width="65%" />
      </div>
      <div v-else>
        <!-- eslint-disable vue/no-v-html -->
        <div class="campaign-content" v-html="campaignContents[CAMPAIGN_CONTENT_IDS.PART_2]?.body" />
        <!-- eslint-enable -->
      </div>
    </b-card>

    <!-- Carousel between content 2 and 3: images from /img/startpage -->
    <b-card v-if="donationStore.showDonationCampaignGallery && carouselImages.length" class="mb-5">
      <b-carousel
        id="campaign-carousel"
        controls
        indicators
        :interval="5000"
        background="#000"
        class="mb-0"
      >
        <b-carousel-slide
          v-for="(img, idx) in carouselImages"
          :key="idx"
          :img-src="img"
          img-alt="carousel image"
        />
      </b-carousel>
    </b-card>

    <b-card v-if="donationStore.showDonationCampaignPart2" class="mb-5">
      <!-- eslint-disable vue/no-v-html -->
      <div class="campaign-content" v-html="campaignContents[CAMPAIGN_CONTENT_IDS.PART_3]?.body" />
      <!-- eslint-enable -->
    </b-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useDonationStore, CAMPAIGN_CONTENT_IDS, CAROUSEL_DIR } from '@/stores/donation'
import { i18nInstance } from '@/helper/i18n'
import { getContent } from '@/api/content'
import { pulseError } from '@/script'

const donationStore = useDonationStore()
const campaignContents = ref({})
const isContentLoading = ref(false)
const carouselImages = ref([])

function normalizeImageUrl (url) {
  if (url.startsWith('/') || url.startsWith('http')) return url
  return CAROUSEL_DIR + url.replace(/^\.\//, '')
}

async function fetchImagesFromJson () {
  const response = await fetch(CAROUSEL_DIR + 'index.json')
  if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) return []
  const data = await response.json()
  return Array.isArray(data) ? data.map(normalizeImageUrl) : []
}

async function loadCarouselImages () {
  if (carouselImages.value.length) return

  try {
    const urls = await fetchImagesFromJson()
    if (urls.length) {
      carouselImages.value.push(...urls)
    }
  } catch {
    // ignore errors
  }
}

async function fetchCampaignContents () {
  if (isContentLoading.value) return
  isContentLoading.value = true

  try {
    const [Part2, Part3] = await Promise.all([
      getContent(CAMPAIGN_CONTENT_IDS.PART_2),
      getContent(CAMPAIGN_CONTENT_IDS.PART_3),
    ])
    campaignContents.value = {
      [CAMPAIGN_CONTENT_IDS.PART_2]: Part2,
      [CAMPAIGN_CONTENT_IDS.PART_3]: Part3,
    }
  } catch (e) {
    pulseError(i18nInstance.global ? i18nInstance.global.t('content.error_loading') + ': ' + (e.statusText || e.message) : 'Fehler beim Laden: ' + (e.statusText || e.message))
  }

  isContentLoading.value = false
}

onMounted(() => {
  fetchCampaignContents()
  loadCarouselImages()
})
</script>

<style>
img {
  max-width: 35rem;
}
</style>
