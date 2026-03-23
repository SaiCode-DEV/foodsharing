<template>
  <div>
    <b-modal
      id="confirm-password-modal"
      v-model="showPasswordModal"
      :title="$t('foodsaver.delete_account')"
      :ok-variant="'danger'"
      :ok-title="$t('foodsaver.delete_account')"
      :cancel-title="$t('button.cancel')"
      :centered="true"
      @cancel="handleModalCancel"
      @show="startCountdown"
    >
      <div
        v-if="isMe && profileData?.twoFactorEnabled"
        class="alert alert-danger"
        role="alert"
      >
        <strong>{{ $t('terminology.attention') }}:</strong>
        {{ $t('foodsaver.delete_account_totp_hint') }}
      </div>
      <div
        v-else
        class="alert alert-danger"
        role="alert"
      >
        <strong>{{ $t('terminology.attention') }}:</strong>
        {{ $t('foodsaver.delete_description_' + (isMe ? 'own' : 'other')) }}
      </div>

      <b-form-group
        v-if="isMe && !profileData?.twoFactorEnabled"
        label-for="current-password"
        :label="$t('foodsaver.delete_account_password_verify')"
      >
        <b-form-input
          id="current-password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          @keyup.enter="tryDeleteAccount"
        />
      </b-form-group>

      <template #modal-footer="{ cancel }">
        <b-button class="cancel-button" @click="cancel()">
          {{ $t('button.cancel') }}
        </b-button>
        <div>
          <b-button
            v-if="canDelete"
            :disabled="countdownValue > 0 || (isMe && password.length === 0)"
            variant="danger"
            class="confirm-button"
            @click="tryDeleteAccount"
          >
            {{ $t('foodsaver.delete_account') }}
          </b-button>
          <div v-if="canDelete && countdownValue > 0" class="confirm-countdown">
            {{ $t('button.countdown_clickable', { countdown: countdownValue }) }}
          </div>
        </div>
      </template>
    </b-modal>

    <h5>{{ $t('legal.if_delete.this_gets_deleted_main') }}</h5>
    <ul>
      <li>{{ $t('legal.if_delete.this_gets_deleted_stores') }}</li>
      <li>{{ $t('legal.if_delete.this_gets_deleted_quiz') }}</li>
      <li>{{ $t('legal.if_delete.this_gets_deleted_verify') }}</li>
      <li>{{ $t('legal.if_delete.this_gets_deleted_friendlist') }}</li>
      <li>{{ $t('legal.if_delete.this_gets_deleted_trustbananas') }}</li>
    </ul>

    <h5>{{ $t('legal.if_delete.this_doesnt_get_deleted') }}</h5>
    <ul>
      <li>{{ $t('legal.if_delete.this_doesnt_get_deleted_name') }}</li>
      <li>{{ $t('legal.if_delete.this_doesnt_get_deleted_address') }}</li>
      <li>{{ $t('legal.if_delete.this_doesnt_get_deleted_history') }}</li>
    </ul>

    <h5>{{ $t('legal.if_delete.legal_more_info') }}</h5>
    <ul>
      <li><a href="/?page=legal">{{ $t('legal.if_delete.legal_1') }}</a></li>
      <li><a href="https://www.dsgvo.tools/aufbewahrungsfristen">{{ $t('legal.if_delete.legal_2') }}</a></li>
    </ul>

    <hr class="my-3">

    <b-form-group
      v-if="!isMe"
      label-for="reason"
      :label="$t('foodsaver.delete_account_reason')"
    >
      <b-form-textarea
        id="reason"
        v-model="reason"
        :rows="3"
      />
    </b-form-group>
    <b-form-group
      label-for="unsubscribeNewsletter"
    >
      <b-form-checkbox id="unsubscribeNewsletter" v-model="unsubscribeNewsletter">
        {{ $t('legal.if_delete.unsubscribe_newsletter') }}
      </b-form-checkbox>
    </b-form-group>

    <b-button
      id="delete-account"
      variant="danger"
      :disabled="(!isMe && (reason === null || reason?.length < 5))"
      @click="showPasswordModal = true"
    >
      {{ $t('foodsaver.delete_account_now') }}
    </b-button>
  </div>
</template>

<script>
import { deleteUser } from '@/api/user'
import { goTo, pulseError, pulseSuccess } from '@/script'
import i18n from '@/helper/i18n'
import { useUserStore } from '@/stores/user'

export default {
  props: {
    userId: { type: Number, required: true },
    profileData: { type: Object, default: null },
  },
  setup () {
    const userStore = useUserStore()
    return { userStore }
  },
  data () {
    return {
      reason: null,
      showPasswordModal: false,
      password: '',
      countdownValue: 0,
      intervalId: null,
      unsubscribeNewsletter: false,
    }
  },
  computed: {
    isMe () {
      return this.userStore.getUserId === this.userId
    },
    canDelete () {
      // For own account deletion, require password verification via modal and
      // allow only when 2FA is not enabled
      // For other account deletion, perform directly without password
      return (this.isMe && !this.profileData?.twoFactorEnabled) || (!this.isMe && this.reason && this.reason.length >= 5)
    },
  },
  beforeDestroy () {
    if (this.intervalId) clearInterval(this.intervalId)
  },
  beforeUnmount () {
    if (this.intervalId) clearInterval(this.intervalId)
  },
  methods: {
    async tryDeleteAccount () {
      // Do not allow early submission
      if (this.countdownValue > 0) return

      // For own account deletion, require password verification via modal
      // For other account deletion, perform directly without password
      // verification
      if (this.isMe && !this.password) return

      // perform deletion; keep modal open on failure
      try {
        await deleteUser(this.userId, this.reason, this.unsubscribeNewsletter, this.password)
        pulseSuccess(i18n('success'))
        // close modal and navigate on success
        this.showPasswordModal = false
        if (this.intervalId) {
          clearInterval(this.intervalId)
          this.intervalId = null
        }
        this.password = ''
        const goToUrl = this.$url(this.isMe ? 'logout' : 'dashboard')
        goTo(goToUrl)
      } catch (e) {
        console.log(e)
        if (e && e.code === 401) {
          pulseError(i18n('foodsaver.delete_own_account_failed'))
        } else {
          pulseError(i18n('error_unexpected'))
        }
        // keep modal open so the user can correct the password and retry
      }
    },
    startCountdown () {
      if (this.intervalId) {
        clearInterval(this.intervalId)
        this.intervalId = null
      }
      this.countdownValue = this.isMe ? 30 : 5
      if (this.countdownValue > 0) {
        this.intervalId = setInterval(() => {
          this.countdownValue--
          if (this.countdownValue <= 0) {
            this.countdownValue = 0
            clearInterval(this.intervalId)
            this.intervalId = null
          }
        }, 1000)
      }
    },
    handleModalCancel () {
      if (this.intervalId) {
        clearInterval(this.intervalId)
        this.intervalId = null
      }
      this.showPasswordModal = false
      this.password = ''
    },
  },
}
</script>

<style scoped>
.confirm-countdown {
  display: block;
  font-size: 80%;
  color: var(--fs-color-danger-500);
  height: 0;
  text-align: center;
}

h5 {
  margin-bottom: 0.5rem;
}
</style>
