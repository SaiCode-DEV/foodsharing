<template>
  <div>
    <b-container>
      <div>
        <b-alert
          v-if="showTop && !isGoalReached"
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
            <b-row class="w-100">
              <b-col md="9">
                <!-- eslint-disable vue/no-v-html -->
                <!-- Sanitized in Modules/Content/ContentGateway.php get() -->
                <p v-html="replaceKeywords(content.body)" />
                <!-- eslint-enable -->
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
                    {{ formatCurrency(receivedDonationsInEuros) }}
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
                    :value="percentOfGoalReached"
                  />
                  <div class="mt-1 text-right w-25">
                    {{ formatPercentage(percentOfGoalReached) }} %
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
import { CONTENT_IDS, getContent } from '@/api/content'
import { getDonation } from '@/api/donation'
import RouteCheckMixin from '@/mixins/RouteAndDeviceCheckMixin'

export default {
  name: 'DonationModal',
  mixins: [RouteCheckMixin],
  data () {
    return {
      showTop: false,
      receivedDonationsInEuros: 0,
      goalInEuros: 0,
      donators: 0,
      content: null,
      percentOfGoalReached: 0,
      isGoalReached: false,
    }
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
      if (this.content && this.content.body && !this.isTest) {
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
      try {
        this.content = await getContent(CONTENT_IDS.DONATION)
      } catch (e) {
        this.content = null
      }
    },
    async fetchDonationLink () {
      try {
        const response = await getDonation()
        this.receivedDonationsInEuros = response.receivedDonationsInEuros
        this.goalInEuros = response.goalInEuros
        this.donators = response.donators
        this.percentOfGoalReached = response.percentOfGoalReached
        this.isGoalReached = response.isGoalReached
      } catch (error) {
        console.error('Error fetching donation link:', error)
      }
    },
    replaceKeywords (content) {
      return content.replace('DONATION_GOAL', this.formatCurrency(this.goalInEuros))
    },
    hideBanner () {
      this.showTop = false
      localStorage.setItem('bannerClosedTime', new Date().getTime())
    },
    formatCurrency (amount) {
      const roundedAmount = Math.round(amount)
      return roundedAmount.toLocaleString('de-DE') + ' €'
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
