<template>
  <form class="my-1">
    <div class="col-sm-auto">
      <label for="mobile">{{ $t('terminology.mobile_phone') }}</label>
      <PhoneNumberInput
        input-name="mobile"
        :input-value="mobile"
        @update-phone-number="handleValidValue"
      />
    </div>
    <div
      v-if="!isValid && mobile.length > 0"
      class="col-sm-auto invalid-feedback"
    >
      <span>{{ $t('register.phone_not_valid') }}</span>
    </div>
    <div class="mt-3 col-sm-auto">
      <div class="alert alert-info">
        <i class="fas fa-info-circle" /> {{ $t('register.login_phone_info') }}
      </div>
    </div>
    <div class="col-sm-auto">
      <button
        class="btn btn-primary mt-3"
        type="button"
        @click="$emit('prev')"
      >
        {{ $t('register.prev') }}
      </button>
      <button
        class="btn btn-primary mt-3"
        type="submit"
        :disabled="!isValid"
        @click.prevent="redirect()"
      >
        {{ $t('register.next') }}
      </button>
    </div>
  </form>
</template>
<script>
import PhoneNumberInput from '@/components/PhoneNumberInput.vue'

export default {
  components: {
    PhoneNumberInput,
  },
  data () {
    return {
      mobile: { value: null, valid: true },
    }
  },
  computed: {
    isValid () {
      return this.mobile.valid
    },
  },
  methods: {
    handleValidValue (data) {
      this[`${data.id}`] = { value: data.value, valid: data.valid }
    },
    redirect () {
      if (this.isValid) {
        this.$emit('next')
        this.$emit('update-mobile-number', this.mobile)
      }
    },
  },
}
</script>
<style lang="scss" scoped>
.is-invalid {
    outline: var(--fs-color-danger-500) auto 1px;
}
.invalid-feedback {
  font-size: 100%;
  display: unset;
}
</style>
