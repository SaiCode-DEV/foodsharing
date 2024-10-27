<template>
  <div id="bananas">
    <div v-if="!bananaCount" class="my-1">
      {{ nonePlaceholder }}
    </div>

    <div v-if="canGiveBanana && !hasGivenBanana" class="mb-2">
      <div v-if="showTextarea">
        <b-alert variant="success" show>
          {{ $i18n('profile.banana.details', { name: recipient.name }) }}
          <br>
          <strong>
            {{ $i18n('profile.banana.undo') }}
          </strong>
        </b-alert>
        <b-alert variant="info" show>
          {{ $i18n('profile.banana.vouch') }}
        </b-alert>

        <b-form-textarea
          v-model="bananaText"
          :placeholder="$i18n('profile.banana.placeholder')"
          class="mb-2"
          max-rows="8"
          size="sm"
          :state="canSendBanana ? true : null"
        />

        <div class="d-flex justify-content-between">
          <b-button
            variant="primary"
            size="sm"
            @click="toggleTextarea"
          >
            {{ $i18n('button.cancel') }}
          </b-button>
          <b-button
            class="text-right"
            variant="secondary"
            size="sm"
            :disabled="!canSendBanana"
            @click="trySendBanana"
          >
            {{ $i18n('profile.banana.give', { name: recipient.name }) }}
          </b-button>
        </div>
      </div>
      <div v-else>
        <b-button
          variant="secondary"
          size="sm"
          @click="toggleTextarea"
        >
          {{ $i18n('profile.banana.give', { name: recipient.name }) }}
        </b-button>
      </div>
    </div>

    <BananaListEntry
      v-for="b in bananaList"
      :key="b.id"
      :user="b.user"
      :created-at="b.time"
      :text="b.message"
      :can-remove="canRemoveBanana || (b.user.id === currentUserId)"
      :recipient-id="recipient.id"
      :is-sent="isSent"
    />
  </div>
</template>

<script>
import { sendBanana } from '@/api/banana'
import i18n from '@/helper/i18n'
import { pulseError, pulseInfo } from '@/script'

import BananaListEntry from './BananaListEntry'
import { HTTP_RESPONSE } from '@/consts'
import { useUserStore } from '@/stores/user'
const userStore = useUserStore()

export default {
  components: { BananaListEntry },
  props: {
    recipient: { type: Object, required: true },
    canGiveBanana: { type: Boolean, default: false },
    canRemoveBanana: { type: Boolean, default: false },
    bananas: { type: Array, default: () => { return [] } },
    nonePlaceholder: { type: String, default: '' },
    isSent: { type: Boolean, default: false },
  },
  data () {
    return {
      bananaCount: this.bananas.length,
      hasGivenBanana: false,
      bananaList: this.bananas,
      showTextarea: false,
      bananaText: '',
    }
  },
  computed: {
    canSendBanana () {
      return this.bananaText && (this.bananaText.trim().length > 99)
    },
    currentUserId: () => userStore.getUserId,
  },
  methods: {
    async trySendBanana () {
      try {
        this.bananaList.unshift(await sendBanana(this.recipient.id, this.bananaText.trim()))

        // Reset UI and component state
        pulseInfo(i18n('profile.banana.sent'))
        this.bananaText = ''
        this.showTextarea = false
        this.hasGivenBanana = true
      } catch (err) {
        if (err.code === HTTP_RESPONSE.BAD_REQUEST) {
          pulseError(i18n('profile.banana.messageTooShort'))
        } else if (err.code === HTTP_RESPONSE.FORBIDDEN) {
          pulseError(i18n('profile.banana.alreadyGiven', { name: this.recipient.name }))
        } else {
          console.error(err)
          pulseError(i18n('error_unexpected'))
        }
      }
    },
    toggleTextarea () {
      this.showTextarea = !this.showTextarea
    },
  },
}
</script>
