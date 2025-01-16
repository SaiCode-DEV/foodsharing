
<template>
  <b-modal
    :id="id"
    :title="$i18n('required_messages.sure_title')"
    :cancel-title="$i18n('button.cancel')"
    :ok-title="$i18n('button.yes_i_am_sure')"
    centered
    @ok="resolveCallback?.(optionalMessage)"
    @cancel="rejectCallback?.()"
    @show="initialize"
  >
    <p>
      <Markdown :source="$i18n(`required_messages.${messageKey}.really${multipleAppendix}`, params)" />
      <span v-text="$i18n(`required_messages.message_info${multipleAppendix}`, params)" />
    </p>
    <blockquote>
      <div>{{ $i18n('salutation.3') }} {{ params.name }},</div>
      <div>{{ $i18n(`required_messages.${messageKey}.main`, params) }}</div>
      <br>
      <b-form-textarea
        v-model="optionalMessage"
        :placeholder="$i18n('required_messages.placeholder')"
        max-rows="4"
        maxlength="3000"
        @blur="saveMessageToStorage"
      />
      <br>
      <div>{{ $i18n(`required_messages.${messageKey}.footer`) }}</div>
    </blockquote>
    <b-form-checkbox
      v-if="maySave && optionalMessage"
      v-model="saveMessage"
      class="mt-4"
      @change="saveMessageToStorage"
    >
      {{ $i18n('required_messages.save_message') }}
    </b-form-checkbox>
  </b-modal>
</template>

<script>
import Markdown from '../Markdown/Markdown.vue'

export default {
  components: { Markdown },
  props: {
    messageKey: { type: String, required: true },
    initialParams: { type: Object, default: () => ({}) },
    identifier: { type: String, default: '' },
    maySave: { type: Boolean, default: true },
  },
  data () {
    return {
      params: Object.assign({ name: '' }, this.initialParams),
      optionalMessage: '',
      resolveCallback: null,
      rejectCallback: null,
      saveMessage: true,
      multiple: false,
    }
  },
  computed: {
    id () {
      return `requiredMessageModal-${this.messageKey}-${this.identifier}`
    },
    storageKey () {
      return `requiredMessages-${this.messageKey}`
    },
    multipleAppendix () {
      return this.multiple ? '_multiple' : ''
    },
  },
  methods: {
    initialize () {
      if (!this.maySave) return
      const savedMessage = localStorage.getItem(this.storageKey)
      if (savedMessage === null) return // never used before
      this.optionalMessage = savedMessage
      if (!savedMessage) {
        this.saveMessage = false
      }
    },
    show (params = {}) {
      this.multiple = false
      this.params = Object.assign(this.params, params)
      this.$bvModal.show(this.id)
    },
    showMultiple (count, params = {}) {
      this.multiple = true
      this.params = Object.assign(this.params, { count, name: this.$i18n('required_messages.name_placeholder') }, params)
      this.$bvModal.show(this.id)
    },
    getConfirmationPromise () {
      this.rejectCallback?.()
      return new Promise((resolve, reject) => {
        this.resolveCallback = resolve
        this.rejectCallback = reject
      })
    },
    saveMessageToStorage () {
      localStorage.setItem(this.storageKey, this.saveMessage ? this.optionalMessage : '')
    },
  },
}
</script>
