<template>
  <div class="donation-options">
    <b-row v-if="showCampaignCard && contentBlocks.campaign" class="mb-5 justify-content-center">
      <b-col
        cols="12"
        lg="5"
        xl="6"
        class="mt-4 mb-4"
      >
        <b-card
          class="donation-option-card h-100 d-flex flex-column campaign-card mb-5"
          body-class="d-flex flex-column justify-content-between pl-3 pr-3"
        >
          <h3 class="card-title">
            <i class="fas fa-bullhorn fa-lg mr-2" /> {{ contentBlocks.campaign.title }}
          </h3>
          <div class="card-text">
            <!-- eslint-disable vue/no-v-html -->
            <div class="card-text" v-html="contentBlocks.campaign.body" />
            <!-- eslint-enable -->
          </div>
          <div class="text-center">
            <DonationButton
              :text="$t('donation_page.donation_options.campaign.button')"
              :custom-donation-style="true"
              :height="60"
              :width="50"
              :border-color="'var(--fs-color-warning-400)'"
              :custom-donation-text-size="'1.2rem'"
              class="btn-bottom"
              @click="onClick('campaign')"
            />
          </div>
        </b-card>
      </b-col>
    </b-row>

    <!-- The other options in the row below -->
    <b-row class="mb-5 justify-content-center mt-4 mt-lg-5">
      <!-- One-time donation -->
      <b-col
        v-if="contentBlocks.oneTime"
        cols="12"
        md="6"
        lg="5"
        class="pb-5 pb-md-0"
      >
        <b-card
          class="donation-option-card h-100 d-flex flex-column justify-content-between onetime-card"
          body-class="d-flex flex-column justify-content-between mb-4 pl-3 pr-3"
        >
          <h3 class="card-title">
            <i class="fas fa-hand-holding-heart fa-lg mr-2" /> {{ contentBlocks.oneTime.title }}
          </h3>
          <div class="card-text">
            <!-- eslint-disable vue/no-v-html -->
            <div class="card-text" v-html="contentBlocks.oneTime.body" />
            <!-- eslint-enable -->
          </div>
          <div class="text-center">
            <DonationButton
              :text="$t('donation_page.donation_options.one_time.button')"
              :custom-donation-style="true"
              :height="60"
              :width="50"
              :border-color="'var(--fs-color-success-400)'"
              :custom-donation-text-size="'1.2rem'"
              class="btn-bottom"
              @click="onClick('onetime')"
            />
          </div>
        </b-card>
      </b-col>

      <!-- Friendship circle -->
      <b-col
        v-if="contentBlocks.friendshipCircle"
        cols="12"
        md="6"
        lg="5"
        class="pb-5 pb-md-0"
      >
        <b-card
          class="donation-option-card h-100 d-flex flex-column justify-content-between circle-card"
          body-class="d-flex flex-column justify-content-between mb-4 pl-3 pr-3"
        >
          <h3 class="card-title">
            <i class="fas fa-users fa-lg mr-2" /> {{ contentBlocks.friendshipCircle.title }}
          </h3>
          <div class="card-text">
            <!-- eslint-disable vue/no-v-html -->
            <div class="card-text" v-html="contentBlocks.friendshipCircle.body" />
            <!-- eslint-enable -->
          </div>
          <div class="text-center">
            <DonationButton
              :text="$t('donation_page.donation_options.friendship_circle.button')"
              :custom-donation-style="true"
              :height="60"
              :width="50"
              :border-color="'var(--fs-color-danger-400)'"
              :custom-donation-text-size="'1.2rem'"
              class="btn-bottom"
              @click="onClick('friendship_circle')"
            />
          </div>
        </b-card>
      </b-col>
    </b-row>
  </div>
</template>

<script setup>
import { CONTENT_IDS, getContent } from '@/api/content'
import { defineProps, defineEmits, onMounted,ref } from 'vue'
import Markdown from '@/components/Markdown/Markdown.vue'
import DonationButton from '@/components/DonationButton.vue'

defineProps({
  showCampaignCard: { type: Boolean, default: false },
})
const emit = defineEmits(['select'])

function onClick (type) {
  emit('select', type)
}

const contentBlocks = ref({
  campaign: null,
  friendshipCircle: null,
  oneTime: null,
})

const requiredIds = [
  CONTENT_IDS.DONATION_CAMPAIGN_BLOCK,
  CONTENT_IDS.DONATION_FRIENDSHIP_CIRCLE_BLOCK,
  CONTENT_IDS.DONATION_ONE_TIME_BLOCK,
]

async function getDonationContent() {
  try {
    const response = await getContent(requiredIds)
    
    contentBlocks.value = {
      campaign: response.find((x) => x.id === CONTENT_IDS.DONATION_CAMPAIGN_BLOCK) ?? null,
      friendshipCircle: response.find((x) => x.id === CONTENT_IDS.DONATION_FRIENDSHIP_CIRCLE_BLOCK) ?? null,
      oneTime: response.find((x) => x.id === CONTENT_IDS.DONATION_ONE_TIME_BLOCK) ?? null,
    }
  } catch (e) {
    console.error('Failed to fetch content from server:', e)
  }
}

onMounted(getDonationContent)
</script>
