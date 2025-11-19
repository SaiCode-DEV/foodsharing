<template>
  <div class="mb-3">
    <input
      :value="value"
      type="text"
      class="form-control"
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
    placeholder: { type: String, default: 'settings.2fa.totp_token' },
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
  },
}
</script>
