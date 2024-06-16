<template>
  <div class="settings-calendar">
    <h3 class="heading">
      <i class="fas fa-fw fa-lightbulb" />
      {{ $i18n('settings.calendar.link_title') }}
    </h3>
    <div class="bootstrap">
      <p> {{ $i18n('settings.calendar.teaser') }} <a :href="$url('wiki_calendar')" target="_blank">{{ $url('wiki_calendar') }}</a></p>
      <b-button
        class="my-2"
        @click="createToken"
      >
        {{ $i18n('settings.calendar.create_token.button') }}
      </b-button>
      <b-button
        v-if="token"
        class="my-2"
        @click="removeToken"
      >
        {{ $i18n('settings.calendar.delete_token.button') }}
      </b-button>

      <div v-if="token" class="mt-3">
        <hr>

        <b-form-checkbox v-model="includeInvitations" class="mt-3">
          {{ $i18n('settings.calendar.include_invitations') }}
        </b-form-checkbox>

        <ul class="webcal">
          <li class="pb-1">
            <a :href="webcalPickups">
              {{ webcalPickups }}
            </a>
          </li>
          <li class="pb-1">
            <a :href="httpPickups">
              {{ httpPickups }}
            </a>
          </li>
        </ul>
      </div>

      <b-alert variant="warning" show>
        {{ $i18n('settings.calendar.token-warning') }}
      </b-alert>

      <b-alert variant="secondary" show>
        {{ $i18n('settings.calendar.sync') }}
      </b-alert>
    </div>
  </div>
</template>

<script>
import { hideLoader, pulseError, showLoader } from '@/script'
import { createApiToken, getApiToken, removeApiToken } from '@/api/calendar'
import i18n from '@/helper/i18n'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'

export default {
  mixins: [ConfirmationDialogue],
  props: {
    baseUrlWebcal: { type: String, required: true },
    baseUrlHttp: { type: String, required: true },
  },
  data () {
    return {
      token: null,
      includeInvitations: true,
    }
  },
  computed: {
    queryParams () {
      return `?events=${this.includeInvitations ? 'all' : 'answered'}`
    },
    webcalPickups () {
      return this.baseUrlWebcal + this.token + this.queryParams
    },
    httpPickups () {
      return this.baseUrlHttp + this.token + this.queryParams
    },
  },
  async mounted () {
    showLoader()
    try {
      this.token = await getApiToken()
    } catch (e) {
      this.token = null
    }
    hideLoader()
  },
  methods: {
    async createToken () {
      if (this.token) {
        if (!await this.confirmationDialogue('settings.calendar.create_token.message', { okTitle: i18n('yes') })) return
      }
      showLoader()
      try {
        this.token = await createApiToken()
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      hideLoader()
    },
    async removeToken () {
      if (!await this.confirmationDialogue('settings.calendar.delete_token.message')) return
      showLoader()
      try {
        await removeApiToken()
        this.token = null
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
      hideLoader()
    },
  },
}
</script>

<style lang="scss" scoped>
.heading {
  padding: 0.2rem 0.1rem;
  font-family: 'Alfa Slab One', serif;
  font-weight: normal;
  font-size: 1rem;
  color: var(--fs-color-primary-500);
}

.webcal {
  font-weight: bolder;

  // --breakpoint-xs
  @media (max-width: 576px) {
    word-break: break-all;
    overflow-wrap: break-word;
  }

  li a {
    color: var(--fs-color-secondary-500);
  }
}
</style>
