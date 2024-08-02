<template>
  <form class="my-1">
    <div class="col-sm-auto">
      <label>{{ $i18n('terminology.mobile_phone') }}<sup><i class="fas fa-asterisk" /></sup></label>
    </div>
    <div class="col-sm-auto">
      <VueTelInput
        :value="mobile"
        :class="{ 'is-invalid': !isValid }"
        :valid-characters-only="validCharactersOnly"
        :mode="mode"
        :input-options="inputOptions"
        :default-country="defaultCountry"
        :preferred-countries="preferredCountries"
        @input="update"
        @validate="validate"
      />
    </div>
    <div
      v-if="!isValid && mobile.length > 0"
      class="col-sm-auto invalid-feedback"
    >
      <span>{{ $i18n('register.phone_not_valid') }}</span>
    </div>
    <div class="mt-3 col-sm-auto">
      <div class="alert alert-info">
        <i class="fas fa-info-circle" /> {{ $i18n('register.login_phone_info') }}
      </div>
    </div>
    <div class="col-sm-auto">
      <button
        class="btn btn-primary mt-3"
        type="button"
        @click="$emit('prev')"
      >
        {{ $i18n('register.prev') }}
      </button>
      <button
        class="btn btn-primary mt-3"
        type="submit"
        :disabled="!isValid"
        @click.prevent="redirect()"
      >
        {{ $i18n('register.next') }}
      </button>
      <span class="mr-3 d-flex flex-row-reverse">{{ $i18n('register.requiredFields') }}<sup><i class="fas fa-asterisk" /></sup></span>
    </div>
  </form>
</template>
<script>
import { VueTelInput } from 'vue-tel-input'
import 'vue-tel-input/dist/vue-tel-input.css'
import i18n from '@/helper/i18n'

// https://vue-tel-input.iamstevendao.com/documentation/props.html
export default {
  components: {
    VueTelInput,
  },
  props: { mobile: { type: String, default: null } },
  data () {
    return {
      phoneNumberValid: false,
      mode: 'international',
      preferredCountries: ['DE', 'AT', 'CH'],
      validCharactersOnly: true,
      defaultCountry: 'DE',
      inputOptions: {
        placeholder: i18n('register.phone_example'),
        maxlength: 18,
      },
    }
  },
  computed: {
    isValid () {
      return this.phoneNumberValid && this.mobile !== null && this.mobile !== ''
    },
  },
  methods: {
    update (phoneNumber, phoneObject) {
      this.phoneNumberValid = phoneObject.valid
      this.$emit('update:mobile', phoneNumber)
    },
    validate (phoneObject) {
      this.phoneNumberValid = phoneObject.valid
    },
    redirect () {
      if (this.isValid) {
        this.$emit('next')
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
