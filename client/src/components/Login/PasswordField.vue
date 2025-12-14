<template>
  <div class="input-group mb-3">
    <input
      :value="value"
      :type="inputType"
      class="form-control"
      :class="{ 'is-invalid': invalid }"
      :placeholder="$t(placeholder)"
      :aria-label="$t(placeholder)"
      autocomplete="current-password"
      @input="$emit('input', $event.target.value)"
      @blur="$emit('blur')"
    >
    <div class="input-group-append">
      <button
        class="btn btn-outline-primary"
        type="button"
        @click="isVisible = !isVisible"
      >
        <i class="fas" :class="iconClass" />
      </button>
    </div>
  </div>
</template>

<script>
/**
 * A password field that allows toggling the visibility of the input
 */
export default {
  props: {
    /**
     * Value of the password field that can be used with a v-model directive.
     */
    value: { type: String, required: true },
    /**
     * Translation key for the input's placeholder and the aria-label.
     */
    placeholder: { type: String, default: 'login.password' },
    invalid: { type: Boolean, default: false },
  },
  data () {
    return {
      isVisible: false,
    }
  },
  computed: {
    inputType () {
      return this.isVisible ? 'text' : 'password'
    },
    iconClass () {
      return this.isVisible ? 'fa-eye-slash' : 'fa-eye'
    },
  },
}
</script>

<style scoped>
.is-invalid {
  border-color: var(--fs-color-danger-500) !important;
}
</style>
