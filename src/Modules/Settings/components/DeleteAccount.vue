<template>
  <div>
    <ul>
      <li><a href="/?page=legal">{{ $i18n('legal.if_delete.legal_1') }}</a></li>
      <li><a href="https://www.dsgvo.tools/aufbewahrungsfristen">{{ $i18n('legal.if_delete.legal_2') }}</a></li>
    </ul>

    <div
      class="alert alert-secondary"
      role="alert"
    >
      {{ $i18n('legal.if_delete.this_gets_deleted_main') }}
      <ul>
        <li>{{ $i18n('legal.if_delete.this_gets_deleted_stores') }}</li>
        <li>{{ $i18n('legal.if_delete.this_gets_deleted_quiz') }}</li>
        <li>{{ $i18n('legal.if_delete.this_gets_deleted_verify') }}</li>
        <li>{{ $i18n('legal.if_delete.this_gets_deleted_friendlist') }}</li>
        <li>{{ $i18n('legal.if_delete.this_gets_deleted_trustbananas') }}</li>
      </ul>
    </div>
    <div
      class="alert alert-warning"
      role="alert"
    >
      {{ $i18n('legal.if_delete.this_doesnt_get_deleted') }}
      <ul>
        <li>{{ $i18n('legal.if_delete.this_doesnt_get_deleted_name') }}</li>
        <li>{{ $i18n('legal.if_delete.this_doesnt_get_deleted_address') }}</li>
        <li>{{ $i18n('legal.if_delete.this_doesnt_get_deleted_history') }}</li>
      </ul>
    </div>
    <b-form-group
      v-if="!isMe"
      label-for="reason"
      :label="$i18n('foodsaver.delete_account_reason')"
    >
      <b-form-textarea
        id="reason"
        v-model="reason"
        :rows="3"
      />
    </b-form-group>
    <b-button
      id="delete-account"
      variant="danger"
      :disabled="(!isMe && reason === null) || reason?.length < 5"
      @click="tryDeleteAccount"
    >
      {{ $i18n('foodsaver.delete_account_now') }}
    </b-button>
  </div>
</template>

<script>
import { deleteUser } from '@/api/user'
import { goTo, pulseError, pulseSuccess } from '@/script'
import i18n from '@/helper/i18n'
import { useUserStore } from '@/stores/user'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'

const userStore = useUserStore()

export default {
  props: {
    userId: { type: Number, required: true },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return {
      userStore,
      confirmationDialogue,
    }
  },
  data () {
    return {
      reason: null,
    }
  },
  computed: {
    okDisabled () {
      return !this.isMe && this.reason === null
    },
    isMe () {
      return userStore.getUserId === this.userId
    },
  },
  methods: {
    async tryDeleteAccount () {
      if (!this.isMe && this.reason === null) return
      const options = {
        params: {},
      }

      if (!this.isMe) {
        options.params.name = this.userId
        options.params.reason = this.reason
      }
      // Final confirmation with countdown
      const confirmed = await this.confirmationDialogue(
        this.isMe ? 'foodsaver.delete_account_sure' : 'foodsaver.delete_account_sure_reason',
        {
          title: this.$i18n('foodsaver.delete_account'),
          okTitle: this.$i18n('foodsaver.delete_account'),
          okVariant: 'danger',
          countdown: this.isMe ? 30 : 5,
          ...options,
        },
      )

      if (!confirmed) return

      try {
        await deleteUser(this.userId, this.reason)
        pulseSuccess(i18n('success'))
        const goToUrl = this.isMe ? this.$url('logout') : this.$url('dashboard')
        goTo(goToUrl)
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }
    },
  },
}
</script>
