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
    <b-button
      id="delete-account"
      variant="danger"
      @click="$refs.modal_account_deletion.show()"
    >
      {{ $i18n('foodsaver.delete_account_now') }}
    </b-button>
    <b-modal
      id="modal-delete-account"
      ref="modal_account_deletion"
      :title="$i18n('foodsaver.delete_account')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('foodsaver.delete_account')"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      :ok-disabled="okDisabled"
      @ok="tryDeleteAccount"
    >
      <div v-if="!isMe">
        <b-form-group :label="$i18n('foodsaver.delete_account_reason')">
          <b-form-input v-model="reason" />
        </b-form-group>
      </div>

      <div v-else>
        {{ $i18n('foodsaver.delete_account_sure') }}
      </div>
    </b-modal>
  </div>
</template>

<script>
import { deleteUser } from '@/api/user'
import { goTo, pulseError, pulseSuccess } from '@/script'
import i18n from '@/helper/i18n'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

export default {
  props: {
    userId: { type: Number, required: true },
  },
  setup () {
    return {
      userStore,
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
