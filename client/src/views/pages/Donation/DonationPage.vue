<template>
  <div id="app">
    <div class="bg-white">
      <!-- Header -->
      <div class="text-center">
        <component
          :is="showBackButton ? 'a' : 'div'"
          :href="showBackButton ? '#' : null"
          class="align-items-center justify-content-center text-decoration-none text-body"
          :class="mobile ? 'd-inline' : 'd-flex'"
          @click.prevent="showBackButton ? resetSelection() : null"
        >
          <h1>
            {{ $t('donation_page.title') }}
          </h1>
          <div class="foodsharing-logo pl-2">
            <span>
              food<span class="part">sharing</span>
            </span>
          </div>
          <img
            src="/img/icon/donation-strawberry.svg"
            class="ml-3"
            width="70"
            height="60"
          >
        </component>
        <p class="lead">
          {{ $t('donation_page.subtitle') }}
        </p>
      </div>

      <!-- Donation options -->
      <DonationOptions
        v-if="!selectedDonationType"
        :show-campaign-card="donationStore.showCampaignCard"
        @select="handleDonationButtonClick"
      />

      <!-- Donation form area -->
      <b-card v-if="showDonationForm" class="mb-5">
        <b-button
          variant="outline-secondary"
          @click.prevent="resetSelection"
        >
          <i class="fas fa-arrow-left fa-lg" aria-hidden="true" />
          <span class="ml-4">{{ $t('back') }}</span>
        </b-button>
        <div
          ref="twingleFormContainer"
          class="twingle-container mt-3"
          :class="selectedDonationType === 'onetime' ? 'twingle-container--onetime' : ''"
        />
      </b-card>

      <!-- Donation status, campaign & projects only for active campaign -->
      <DonationCampaign v-if="selectedDonationType === 'campaign'" />

      <!-- Numbers below -->
      <DonationBadges />

      <DonationFaq />
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router/composables'
import { useDonationStore } from '@/stores/donation'
import DonationOptions from '@/components/DonationOptions.vue'
import DonationCampaign from '@/components/DonationCampaign.vue'
import DonationFaq from '@/components/DonationFaq.vue'
import DonationBadges from '@/components/DonationBadges.vue'
import { useMediaQuery } from '@/composables/useMediaQuery'

const selectedDonationType = ref(null)
const showDonationForm = ref(false)
const showBackButton = ref(false)
const donationStore = useDonationStore()
const { mobile } = useMediaQuery()
const twingleFormContainer = ref(null)

const initialPage = window.location.pathname.split('/donation/')[1] || null

function selectDonationType (type) {
  selectedDonationType.value = type
  showDonationForm.value = false
  showBackButton.value = false
}

function resetSelection () {
  selectedDonationType.value = null
  showDonationForm.value = false
  showBackButton.value = false
  // Remove Twingle form content if present
  if (twingleFormContainer.value) {
    twingleFormContainer.value.innerHTML = ''
  }
  history.pushState(null, '', '/donation')
}

// Scroll helper: scrolls smoothly to the top of the page
function scrollToTop () {
  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

function generateId () {
  return crypto.randomUUID().slice(0, 8)
}

function createIframeEmbed (url, parent) {
  const iframe = document.createElement('iframe')
  iframe.src = url
  iframe.width = '100%'
  iframe.style.border = '0'
  iframe.style.minHeight = '40em'
  iframe.loading = 'lazy'
  parent.appendChild(iframe)
}

function createScriptEmbed (url, id, parent) {
  const script = document.createElement('script')
  script.type = 'text/javascript'
  script.async = true
  script.defer = true
  script.id = 'tw-' + id
  script.src = url
  parent.appendChild(script)
}

function loadDonationForm () {
  showDonationForm.value = true
  showBackButton.value = true

  nextTick(() => {
    const config = donationStore.twingleEmbedConfig[selectedDonationType.value]
    if (!config?.url || !twingleFormContainer.value) return

    const id = generateId()
    twingleFormContainer.value.innerHTML = ''
    const embedDiv = document.createElement('div')
    embedDiv.id = 'twingle-public-embed-' + id
    twingleFormContainer.value.appendChild(embedDiv)

    if (config.containerType === 'iframe') {
      createIframeEmbed(config.url, embedDiv)
    } else {
      createScriptEmbed(config.url, id, embedDiv)
    }
  })
}

// Watch for the iframe URLs to become available. If the user navigated
// directly to a donation page (e.g. /donation/campaign) `loadDonationForm`
// may have been called before the store populated the URLs. When the
// relevant URL appears, try loading the form again.
watch(
  () => donationStore.twingleEmbedConfig,
  () => {
    const config = donationStore.twingleEmbedConfig[selectedDonationType.value]
    const hasEmbed = twingleFormContainer.value?.querySelector('iframe, script')
    if (showDonationForm.value && config?.url && !hasEmbed) {
      loadDonationForm()
    }
  },
  { deep: true },
)

function handleDonationButtonClick (type) {
  selectDonationType(type)
  history.pushState(null, '', `/donation/${type}`)
  // If user chose the selfservice form, scroll to top so the form is visible
  if (type === 'selfservice') {
    scrollToTop()
  }
  loadDonationForm()
}

function onPopState () {
  const type = window.location.pathname.split('/donation/')[1] || null
  if (type) {
    selectDonationType(type)
    loadDonationForm()
  } else {
    resetSelectionWithoutPush()
  }
}

// Both donation routes render the same markup, so the router swaps the content
// without recreating this component, and it does not fire popstate either.
const route = useRoute()
watch(() => route.path, onPopState)

function resetSelectionWithoutPush () {
  selectedDonationType.value = null
  showDonationForm.value = false
  showBackButton.value = false
  if (twingleFormContainer.value) {
    twingleFormContainer.value.innerHTML = ''
  }
}

onMounted(() => {
  // Load donation form directly if URL contains a donation type
  if (initialPage) {
    selectDonationType(initialPage)
    loadDonationForm()
  }
  window.addEventListener('popstate', onPopState)
})

onUnmounted(() => {
  window.removeEventListener('popstate', onPopState)
})

</script>

<style>
.foodsharing-logo {
  font-family: var(--fs-font-family-headline);
  color: var(--fs-color-primary-500);
  font-size: var(--fs-font-size, 2rem);
  font-weight: normal;
}
.foodsharing-logo .part {
    color: var(--fs-color-secondary-500);
}

.campaign-card {
  background: var(--fs-color-warning-400) !important;
  color: #fff !important;
  position: relative;
  min-height: 160px; /* kleiner, aber genug Platz für Inhalt und Button */
  padding-bottom: 3.5em; /* mehr Platz für Button, verhindert Überlappung */
}
.onetime-card {
  background: var(--fs-color-success-400) !important;
}
.circle-card {
  background-color: var(--fs-color-danger-400) !important;
}

.donation-option-card {
  position: relative;
  padding-bottom: 3.5em;
}

.header {
  background-color: #f8f9fa;
  padding: 2rem 1rem;
  margin-bottom: 2rem;
  border-radius: 8px;
}

.lead {
  font-size: 1.25rem;
  font-weight: 300;
}

.progress {
  background-color: #e9ecef;
}

.progress span {
  color: white;
  font-weight: bold;
}

.card {
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-title {
  color: var(--fs-color-gray-800);
}

.card-text > p {
  color: var(--fs-color-black);
  font-size:1.4em;
}

.project-card {
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  padding: 1.5rem;
  height: 100%;
  transition: transform 0.2s;
}

.project-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.project-card h4 {
  color: #28a745;
  margin-bottom: 1rem;
}

.project-card ul {
  padding-left: 1.2rem;
  color: #28a745;
}

.toggle-btn {
  min-width: 140px;
}

.twingle-container {
  width: 100%;
}

.twingle-container iframe {
  width: 100%;
  border: none;
  overflow: hidden;
}

.stat-circle-dotted {
  border: 3px dotted var(--fs-color-secondary-500);
  border-radius: 50%;
  width: 90px;
  height: 90px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 0.5rem auto;
  background: transparent;
}

.stat-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}

.stat-icon i {
  font-size: 3rem;
  color: var(--fs-color-secondary-500);
}

.btn-bottom {
  position: absolute;
  left: 50%;
  bottom: 0;
  transform: translate(-50%, 50%);
  z-index: 2;
  background: #fff;
  border-radius: 2em;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
  padding: 0 1.5em;
  min-width: 220px;
  min-height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Default: take full available width */
/* Card width is controlled via Bootstrap utility classes (mx-auto, w-*) in the template */

/* FAQ Toggle Button: Kein Rahmen, größere Icons */
.faq-toggle-btn {
  border: none !important;
  box-shadow: none !important;
  background: transparent !important;
  padding: 0.25em 0.5em;
}

/* FAQ Bereich: Gepunktete Linien oben und unten */
.faq-card {
  background: #fcfcf4;
  position: relative;
  padding-top: 0;
  padding-bottom: 0;
}
.faq-dotted-line {
  border-top: 4px dotted #6b3a32;
  width: 100%;
  height: 0;
  margin: 0 0 1rem 0;
}
.faq-dotted-line-bottom {
  margin: 1rem 0 1.5rem 0;
}

/* Back button uses Bootstrap utilities; no custom styles needed */

@media (max-width: 768px) {
  .display-4 {
    font-size: 2rem;
  }
  .project-card {
    margin-bottom: 1rem;
  }
  .donation-options .b-row {
    row-gap: 2.5rem;
  }
}

.campaign-content summary {
  list-style: none;
  cursor: pointer;
}

.campaign-content summary::-webkit-details-marker {
  display: none;
}

.campaign-content summary::before {
  font-family: 'Font Awesome 6 Free', 'Font Awesome 5 Free';
  font-weight: 900;
  content: '\f0da'; /* fa-caret-right */
  display: inline-block;
  margin-right: 0.5em;
  transition: transform 0.2s ease;
}

.campaign-content details[open] > summary::before {
  transform: rotate(90deg);
}

#campaign-carousel .carousel-item img {
  max-height: 600px;   /* Maximale Höhe */
  object-fit: cover;   /* Bild proportional zuschneiden */
}

body.dark-mode .twingle-container iframe {
  filter: invert(0.9) hue-rotate(180deg);
  transition: filter 0.3s ease;
}
</style>
