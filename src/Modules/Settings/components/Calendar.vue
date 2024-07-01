<template>
  <div class="settings-calendar">
    <b-alert variant="info" show>
      <Markdown :source="$i18n('settings.calendar.info', { calendarUrl: $url('wiki_calendar') })" />
    </b-alert>
    <b-form>
      <b-form-group :label="$i18n('settings.calendar.pickup.label')">
        <b-form-select v-model="includePickups" :options="pickupOptions" />
      </b-form-group>
      <b-form-group :label="$i18n('settings.calendar.event.label')">
        <b-form-select v-model="includeEvents" :options="eventOptions" />
      </b-form-group>
      <b-form-group :label="$i18n('settings.calendar.history.label')">
        <b-form-select v-model="includeHistory" :options="historyOptions" />
      </b-form-group>
      <b-form-group :label="$i18n('settings.calendar.program.label')">
        <b-form-select v-model="selectedProgram" :options="programOptions" />
      </b-form-group>
      <div v-if="selectedProgram === 'other'" class="ml-4">
        <b-form-group :label="$i18n('settings.calendar.protocol')">
          <b-form-select v-model="protocol" :options="protocolOptions" />
        </b-form-group>
        <b-form-group :label="$i18n('settings.calendar.formatting.label')">
          <b-form-select v-model="formatting" :options="formattingOptions" />
        </b-form-group>
      </div>
    </b-form>
    <b-button-toolbar>
      <b-dropdown
        split
        variant="success"
        right
        :text="$i18n('settings.calendar.generate_url.button')"
        :disabled="!selectedProgram"
        @click="copyUrl"
      >
        <b-dropdown-item @click="download">
          {{ $i18n('settings.calendar.download') }}
        </b-dropdown-item>
      </b-dropdown>
      <b-button
        v-if="token"
        variant="outline-danger"
        @click="removeToken"
      >
        {{ $i18n('settings.calendar.delete_token.button') }}
      </b-button>
    </b-button-toolbar>
  </div>
</template>
<script>
import { hideLoader, pulseError, showLoader } from '@/script'
import { createApiToken, getApiToken, removeApiToken } from '@/api/calendar'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  components: { Markdown },
  mixins: [ConfirmationDialogue],
  data () {
    return {
      token: null,

      includePickups: true,
      includeEvents: 'invitations',
      includeHistory: true,
      selectedProgram: null,
      protocol: 'http',
      formatting: 'alt',
      pickupOptions: this.optionsArray([true, false], 'pickup'),
      eventOptions: this.optionsArray(['all', 'invitations', 'maybe', 'accepted', 'none'], 'event'),
      historyOptions: this.optionsArray([true, false], 'history'),
      programOptions: [
        { value: { protocol: 'http', formatting: 'html' }, text: this.$i18n('settings.calendar.program.google') },
        { value: { protocol: 'webcal', formatting: 'alt' }, text: this.$i18n('settings.calendar.program.outlook') },
        { value: { protocol: 'http', formatting: 'alt' }, text: this.$i18n('settings.calendar.program.thunderbird') },
        { value: 'other', text: this.$i18n('settings.calendar.program.other') },
      ],
      protocolOptions: ['http', 'webcal'].map(value => ({ value, text: `${value}://...` })),
      formattingOptions: this.optionsArray(['html', 'alt', 'text'], 'formatting'),
    }
  },
  computed: {
    url () {
      const programSettings = this.selectedProgram === 'other' ? this : this.selectedProgram
      if (!programSettings || !this.token) return false
      return `${programSettings.protocol}://${location.host}/api/calendar/${this.token}?formatting=${programSettings.formatting}&events=${this.includeEvents}&pickups=${this.includePickups}&history=${this.includeHistory}`
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
    optionsArray (keys, type) {
      return keys.map(key => ({ value: key, text: this.$i18n(`settings.calendar.${type}.${key}`) }))
    },
    async haveToken () {
      if (!this.token) {
        showLoader()
        try {
          this.token = await createApiToken()
        } catch (e) {
          pulseError(this.$i18n('error_unexpected'))
          return
        }
        hideLoader()
      }
    },
    async copyUrl () {
      await this.haveToken()
      console.debug(this.url)
    },
    async download () {
      await this.haveToken()
      const link = document.createElement('a')
      link.href = this.url.replace('webcal', 'http') // always use http for download
      link.click()
    },
    async removeToken () {
      if (!await this.confirmationDialogue('settings.calendar.delete_token.message')) return
      showLoader()
      try {
        await removeApiToken()
        this.token = null
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
      hideLoader()
    },
  },
}
</script>
<style scoped lang="scss">
::v-deep.btn-toolbar {
  gap: 0.5em;
  > * {
    margin-bottom: 0.5em;
  }
  .dropdown:first-child {
    flex-grow: 1;
    > .dropdown-toggle-split {
      flex-grow: 0;
    }
  }
}
</style>
