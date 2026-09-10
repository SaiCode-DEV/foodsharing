<template>
  <b-alert
    v-if="showModal"
    show
    class="w-100 mb-2 rounded-0 alertClass"
    variant="success"
  >
    <div class="mt-2">
      <b-row>
        <b-col cols="12" md="3">
          <div class="d-flex align-items-center justify-content-start">
            <b-button
              variant="outline-secondary"
              class="donationInfoTextClass"
              :href="donationModalInfoUrl"
            >
              {{ $t('donation_modal.info_button') }}
            </b-button>
            <b-button
              variant="outline-secondary"
              class="donationInfoTextClass ml-2 d-md-none align-self-start"
              @click="hideModal"
            >
              <i class="fas fa-times" />
            </b-button>
          </div>
        </b-col>
        <b-col
          cols="12"
          md="8"
          class="d-flex mt-md-0 mt-2 align-items-center"
        >
          <h3 class="mb-0 ml-2">
            {{ content.title }}
          </h3>
        </b-col>
        <b-col
          cols="12"
          md="1"
          class="text-right d-none d-md-block"
        >
          <b-button
            variant="outline-secondary"
            class="donationInfoTextClass"
            @click="hideModal"
          >
            <i class="fas fa-times" />
          </b-button>
        </b-col>
      </b-row>
    </div>
    <div class="mt-2 d-md-block">
      <b-row class="w-100">
        <b-col md="9">
          <!-- eslint-disable vue/no-v-html -->
          <!-- Sanitized in Modules/Content/ContentGateway.php get() -->
          <p v-html="content.body?.replace('DONATION_GOAL', $n(goalInEuros.value, 'currency'))" />
          <!-- eslint-enable -->
        </b-col>
      </b-row>
      <b-row>
        <b-col
          cols="12"
          md="4"
          class="mb-2"
        >
          <b-button
            class="donationButtonClass"
            variant="primary"
            block
            @click="openDonationPopup(donationModalPopupUrl)"
          >
            {{ $t('donation_modal.action_button') }}
          </b-button>
        </b-col>
        <b-col
          cols="12"
          md="4"
          class="mb-2"
        >
          <b-button
            v-if="!isGoalReached"
            variant="primary"
            block
            disabled
          >
            {{ $t('donation_modal.amount') }}: <span class="donationAmountClass">
              {{ $n(receivedDonationsInEuros, 'currency') }}
            </span>
          </b-button>
        </b-col>
        <b-col
          cols="12"
          md="4"
        >
          <b-button
            v-if="!isGoalReached && donators > 0"
            class="d-flex align-items-center"
            variant="primary"
            block
            disabled
          >
            <b-progress
              class="p-0 w-75"
              striped
              :variant="progressVariant"
              :value="percentOfGoalReached"
            />
            <div class="mt-1 text-right w-25">
              {{ $n(percentOfGoalReached / 100, 'percent') }}
            </div>
          </b-button>
        </b-col>
      </b-row>
    </div>
  </b-alert>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { CONTENT_IDS, getContent } from '@/api/content'
import { useDonationStore } from '@/stores/donation'
import { useUserStore } from '@/stores/user'
import { storeToRefs } from 'pinia'
import { url } from '@/helper/urls'
import { useRoute } from '@/composables/useRoute'

const route = useRoute()
const showModal = ref(false)
const content = ref(null)
const donationStore = useDonationStore()
const userStore = useUserStore()
const {
  showDonationModal,
  showDonationModalInHoursForLoggedInUsers,
  showDonationModalInHoursForLoggedOutUsers,
  donationModalInfo,
  donationModalInfoUrl,
  donationModalPopupUrl,
} = storeToRefs(donationStore)

const receivedDonationsInEuros = computed(() => donationModalInfo.value?.donationProjectStatus?.receivedDonationsInEuros || 0)
const goalInEuros = computed(() => donationModalInfo.value?.donationProjectStatus?.goalInEuros || 0)
const donators = computed(() => donationModalInfo.value?.donationProjectStatus?.donators || 0)
const percentOfGoalReached = computed(() => donationModalInfo.value?.donationProjectStatus?.percentOfGoalReached || 0)
const isGoalReached = computed(() => donationModalInfo.value?.donationProjectStatus?.isGoalReached || false)
const progressVariant = computed(() => {
  const percentOfGoalReached = donationModalInfo.value?.donationProjectStatus?.percentOfGoalReached || 0
  if (percentOfGoalReached <= 30) return 'danger'
  if (percentOfGoalReached <= 60) return 'warning'
  return 'success'
})

function openDonationPopup (url) {
  const width = 600
  const height = 400
  const left = (window.innerWidth - width) / 2
  const top = (window.innerHeight - height) / 2
  window.open(url, '_blank', `width=${width},height=${height},left=${left},top=${top}`)
}

async function getDonationContent () {
  try {
    content.value = await getContent(CONTENT_IDS.DONATION)
  } catch (e) {
    content.value = null
  }
}

function hideModal () {
  showModal.value = false
  localStorage.setItem('donationModalClosedTime', new Date().getTime())
}

async function checkAndShowDonationModal () {
  // Don't show the banner on the donation page
  if (route.path.startsWith(url('donations'))) {
    return
  }

  const lastClosedTime = localStorage.getItem('donationModalClosedTime')
  const currentTime = new Date().getTime()
  const timeDifference = currentTime - (lastClosedTime || 0)
  const hours = userStore.isLoggedIn
    ? showDonationModalInHoursForLoggedInUsers.value
    : showDonationModalInHoursForLoggedOutUsers.value
  const showDonationModalInMilliseconds = hours * 60 * 60 * 1000
  const isBannerRecentlyClosed = lastClosedTime && timeDifference < showDonationModalInMilliseconds

  if (!isBannerRecentlyClosed && showDonationModal.value) {
    await getDonationContent()
    if (content.value && content.value.body) {
      showModal.value = true
    }
  }
}

onMounted(checkAndShowDonationModal)
watch(showDonationModal, checkAndShowDonationModal)
</script>

<style lang="scss" scoped>
.alertClass {
  z-index: 2000;
}

.donationInfoTextClass {
  font-size: 1.2em;
  color: var(--fs-color-secondary-500);
}

.donationInfoTextClass:hover {
  color: var(--fs-color-primary-900);
}

.donationButtonClass {
  background-color: var(--fs-color-secondary-600);
}

.donationAmountClass {
  color: #f6f5f4;
  font-weight: bold
}

.btn.disabled, .btn:disabled {
  opacity: unset;
}
</style>
