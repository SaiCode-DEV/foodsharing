<template>
  <div id="bananas">
    <div v-if="!bananaCount" class="my-1">
      {{ nonePlaceholder }}
    </div>

    <div v-if="canGiveBanana && !hasGivenBanana" class="mb-2">
      <div v-if="showTextarea">
        <b-alert variant="success" show>
          {{ $t('profile.banana.details', { name: recipient.name }) }}
          <br>
          <strong>
            {{ $t('profile.banana.undo') }}
          </strong>
        </b-alert>
        <b-alert variant="info" show>
          {{ $t('profile.banana.vouch') }}
        </b-alert>

        <b-form-textarea
          v-model="bananaText"
          :placeholder="$t('profile.banana.placeholder')"
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
            {{ $t('button.cancel') }}
          </b-button>
          <b-button
            class="text-right"
            variant="secondary"
            size="sm"
            :disabled="!canSendBanana"
            @click="trySendBanana"
          >
            {{ $t('profile.banana.give', { name: recipient.name }) }}
          </b-button>
        </div>
      </div>
      <div v-else>
        <b-button
          variant="secondary"
          size="sm"
          @click="toggleTextarea"
        >
          {{ $t('profile.banana.give', { name: recipient.name }) }}
        </b-button>
      </div>
    </div>

    <BananaListEntry
      v-for="b in mutableBananas"
      :key="b.id"
      :user="b.user"
      :created-at="b.time"
      :text="b.message"
      :can-remove="canRemoveBanana || (b.user.id === currentUserId())"
      :recipient-id="recipient.id"
      @remove-banana="tryRemoveBanana"
    />
  </div>
</template>

<script>

import { deleteBanana, sendBanana } from '@/api/banana'
import i18n from '@/helper/i18n'
import { hideLoader, pulseError, pulseInfo, showLoader } from '@/script'

import BananaListEntry from './BananaListEntry'
import { HTTP_RESPONSE } from '@/consts'
import { useUserStore } from '@/stores/user'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
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
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  data () {
    return {
      mutableBananas: this.bananas,
      showTextarea: false,
      bananaText: '',
    }
  },
  computed: {
    hasGivenBanana () {
      return this.mutableBananas.findIndex(banana => banana.user.id === this.currentUserId()) !== -1
    },
    bananaCount () {
      return this.mutableBananas.length
    },
    canSendBanana () {
      return this.bananaText && (this.bananaText.trim().length > 99)
    },
  },
  methods: {
    currentUserId () {
      return userStore.getUserId
    },
    async tryRemoveBanana (id, userId, recipientId) {
      const bananaToBeDeleted = this.mutableBananas.findIndex(b => b.id === id)
      if (bananaToBeDeleted === -1) {
        pulseError('error_unexpected')
        return
      }
      if (!await this.confirmationDialogue('profile.banana.remove.confirm_message')) return
      showLoader()
      try {
        if (this.isSent) {
          await deleteBanana(userId, recipientId)
        } else {
          await deleteBanana(recipientId, userId)
        }
        // Remove banana from internal array
        this.mutableBananas.splice(this.mutableBananas, 1)
        pulseInfo(i18n('profile.banana.remove.successful'))

        this.emitBananasUpdated()
      } catch (err) {
        console.error('Couldn\'t delete banana', err)
        pulseError(i18n('error_unexpected'))
      }
      hideLoader()
    },
    async trySendBanana () {
      try {
        this.mutableBananas.unshift(await sendBanana(this.recipient.id, this.bananaText.trim()))

        // Reset UI and component state
        pulseInfo(i18n('profile.banana.sent'))
        this.bananaText = ''
        this.showTextarea = false
        this.emitBananasUpdated()
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
    emitBananasUpdated () {
      const hasSentBanana = this.mutableBananas.findIndex(banana => banana.user.id === this.currentUserId()) !== -1
      this.$emit('bananas-updated', this.mutableBananas.length, !hasSentBanana)
    },
  },
}
</script>
