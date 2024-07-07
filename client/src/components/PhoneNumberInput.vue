<template>
  <div>
    <VueTelInput
      v-model="phoneNumber"
      class="form-control"
      :class="{ 'is-invalid': isInvalid }"
      :valid-characters-only="validCharactersOnly"
      :mode="mode"
      :input-options="inputOptions"
      :default-country="defaultCountry"
      :preferred-countries="preferredCountries"
      @input="emitValidPhoneNumber"
      @validate="validate"
    />
    <div v-if="isInvalid" class="col-sm-auto invalid-feedback">
      <span>{{ $i18n('validation.phone_number_invalid') }}</span>
    </div>
  </div>
</template>

<script>
import { VueTelInput } from 'vue-tel-input'
import 'vue-tel-input/dist-modern/vue-tel-input.css'

export default {
  name: 'PhoneNumberInput',
  components: {
    VueTelInput,
  },
  props: {
    inputValue: { type: Object, required: true },
    inputName: { type: String, required: true },
  },
  data () {
    return {
      phoneNumberValid: true,
      mode: 'international',
      preferredCountries: ['DE', 'AT', 'CH'],
      validCharactersOnly: true,
      defaultCountry: 'DE',
      inputOptions: {
        placeholder: this.$i18n('register.phone_example'),
        maxlength: 18,
        id: this.inputName,
      },
      phoneNumber: this.inputValue.value,
    }
  },
  computed: {
    isInvalid () {
      return this.phoneNumberValid !== undefined && !this.phoneNumberValid
    },
  },
  mounted () {
    const inputElement = this.$refs.telInput.$el.querySelector('input')
    inputElement.addEventListener('keypress', this.preventNonNumericInput)
  },
  beforeDestroy () {
    const inputElement = this.$refs.telInput.$el.querySelector('input')
    inputElement.removeEventListener('keypress', this.preventNonNumericInput)
  },
  methods: {
    validate (phoneObject) {
      if (phoneObject === null || phoneObject.valid === undefined || phoneObject === '') {
        this.phoneNumberValid = true
      } else {
        this.phoneNumberValid = phoneObject.valid
      }
    },
    emitValidPhoneNumber (phoneNumber) {
      this.$emit('update-phone-number', { id: this.inputName, value: phoneNumber, valid: this.phoneNumberValid })
    },
    preventNonNumericInput (event) {
      const allowedKeys = [
        'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter',
        '+', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
      ]

      if (!allowedKeys.includes(event.key)) {
        event.preventDefault()
        return false
      }

      if (event.key === '+' && event.target.value.includes('+')) {
        event.preventDefault()
        return false
      }

      return true
    },
  },
}
</script>

<style scoped lang="scss">
::v-deep.vue-tel-input {
  border-radius: 6px;
  border: 1px solid #ced4da;
  position: relative;
  z-index: 3;
}

::v-deep.vue-tel-input:focus-within {
  box-shadow: none;
  border-color: var(--fs-border-default)
}

.form-control {
  display: inline-flex
}
</style>
