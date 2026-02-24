<template>
  <div class="mb-3">
    <input
      ref="input"
      :value="value"
      type="text"
      class="form-control"
      :class="{ 'is-invalid': invalid }"
      :placeholder="$t(placeholder)"
      :aria-label="$t(placeholder)"
      autocomplete="one-time-code"
      @input="$emit('input', $event.target.value)"
      @blur="$emit('blur')"
    >
  </div>
</template>

<script>
export default {
  props: {
    value: { type: String, required: true },
    placeholder: { type: String, default: 'settings.two_fa_manage.totp_token_placeholder' },
    invalid: { type: Boolean, default: false },
  },
  data () {
    return {
      isVisible: false,
    }
  },
  methods: {
    toggleVisibility () {
      this.isVisible = !this.isVisible
    },
    focus () {
      // Expose a focus() method so parent components can call $refs.totp2.focus()
      try {
        if (this.$refs.input && typeof this.$refs.input.focus === 'function') {
          this.$refs.input.focus()
        }
      } catch (e) {
        // noop
      }
    },
  },
}
</script>
