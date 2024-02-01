<template>
  <div>
    <b-container>
      <div>
        <b-alert
          v-if="showTop && !donationReached"
          v-model="showTop"
          class="position-fixed fixed-top m-0 rounded-0 alertClass"
          variant="success"
        >
          <b-container
            class="mt-2 d-md-block"
          >
            <b-row>
              <b-col
                cols="10"
                md="11"
              >
                <h3>{{ content.title }}</h3>
              </b-col>
              <b-col
                cols="2"
                md="1"
                class="text-right"
              >
                <b-button
                  variant="outline-secondary"
                  @click="hideBanner"
                >
                  <i class="fas fa-times" />
                </b-button>
              </b-col>
            </b-row>
          </b-container>
          <b-container class="mt-2 d-md-block">
            <b-row>
              <b-col md="9">
                <p v-html="replaceKeywords(content.body)" />
              </b-col>
              <b-col md="3">
                <b-button
                  variant="outline-secondary"
                  class="donationInfoTextClass mb-2"
                  :href="$url('donations')"
                  target="_blank"
                  block
                >
                  {{ $i18n('donation_banner.info_button') }}
                </b-button>
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
                  @click="openDonationPopup($url('donation_form'))"
                >
                  {{ $i18n('donation_banner.action_button') }}
                </b-button>
              </b-col>
              <b-col
                cols="12"
                md="4"
                class="mb-2"
              >
                <b-button
                  variant="primary"
                  block
                  disabled
                >
                  {{ $i18n('donation_banner.amount') }}: <span class="donationAmountClass">
                    {{ formatCurrency(donationAmount) }}
                  </span>
                </b-button>
              </b-col>
              <b-col
                cols="12"
                md="4"
              >
                <b-button
                  v-if="donators > 0"
                  class="d-flex align-items-center"
                  variant="primary"
                  block
                  disabled
                >
                  <b-progress
                    class="p-0 w-75"
                    variant="warning"
                    :value="percentage"
                  />
                  <div class="mt-1 text-right w-25">
                    {{ formatPercentage(percentage) }} %
                  </div>
                </b-button>
              </b-col>
            </b-row>
          </b-container>
        </b-alert>
      </div>
    </b-container>
  </div>
</template>

<script>
import { getContent } from '@/api/content'

export default {
  name: 'DonationModal',
  data () {
    return {
      showTop: false,
      donationAmount: 0,
      donationGoal: 0,
      donators: 0,
      content: null,
      percentage: 0,
    }
  },
  computed: {
    donationReached () {
      return this.donationAmount >= this.donationGoal
    },
  },
  async mounted () {
    const lastClosedTime = localStorage.getItem('bannerClosedTime')
    const currentTime = new Date().getTime()
    const timeDifference = currentTime - (lastClosedTime || 0)
    const TwentyfourHoursInMilliseconds = 24 * 60 * 60 * 1000

    if (lastClosedTime && timeDifference < TwentyfourHoursInMilliseconds) {
      this.showTop = false
    } else {
      await this.getDonationContent()
      if (this.content && this.content.body) {
        await this.fetchDonationLink()
        this.showTop = true
      }
    }
  },
  methods: {
    openDonationPopup (url) {
      const width = 600
      const height = 400
      const left = (window.innerWidth - width) / 2
      const top = (window.innerHeight - height) / 2
      window.open(url, '_blank', `width=${width},height=${height},left=${left},top=${top}`)
    },
    async getDonationContent () {
      const contentIdForDonationBanner = 1
      try {
        this.content = await getContent(contentIdForDonationBanner)
      } catch (e) {
        this.content = null
      }
    },
    async fetchDonationLink () {
      try {
        const response = await fetch(this.$url('donation_project_api'))
        const data = await response.json()
        this.donationAmount = data.amount
        this.donationGoal = data.target
        this.donators = data.donators
        this.percentage = data.percentage
      } catch (error) {
        console.error('Error fetching donation link:', error)
      }
    },
    replaceKeywords (content) {
      return content.replace('DONATION_GOAL', this.formatCurrency(this.donationGoal))
    },
    hideBanner () {
      this.showTop = false
      localStorage.setItem('bannerClosedTime', new Date().getTime())
    },
    formatCurrency (amount) {
      return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(amount)
    },
    formatPercentage (value) {
      const roundedValue = value.toFixed(1)
      if (roundedValue === '0.0') {
        return '0'
      } else {
        return roundedValue
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.alertClass {
  z-index: 2000;
}

.donationInfoTextClass {
  font-size: 1.2em;
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
