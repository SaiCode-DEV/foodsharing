<template>
  <div class="phone-number-input-wrapper">
    <div class="input-container">
      <VueTelInput
        v-model="phoneNumber"
        class="form-control has-append"
        :class="{ 'is-invalid': !phoneNumberValid }"
        mode="international"
        :input-options="inputOptions"
        default-country="DE"
        :preferred-countries="preferredCountries"
        :disabled="disabled"
        :valid-characters-only="validCharactersOnly"
        @input="emitValidPhoneNumber"
        @validate="validate"
      />
      <b-button
        class="delete-button is-append"
        variant="outline-danger"
        :disabled="phoneNumber.length === 0"
        @click="deletePhoneNumber"
      >
        <i class="fas fa-trash" />
      </b-button>
    </div>
    <div v-if="!phoneNumberValid || disabled" class="invalid-feedback">
      <span>{{ $i18n('validation.phone_number_invalid') }}</span>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, ref, nextTick } from 'vue'
import { VueTelInput } from 'vue-tel-input'
import i18n from '@/helper/i18n'
import 'vue-tel-input/dist-modern/vue-tel-input.css'

const props = defineProps({
  inputValue: { type: Object, required: true },
  inputName: { type: String, required: true },
})

const emit = defineEmits(['update-phone-number'])

const preferredCountries = ['DE', 'AT', 'CH']
const inputOptions = {
  placeholder: i18n('register.phone_example'),
  maxlength: 18,
  id: props.inputName,
}

const phoneNumberValid = ref(true)
const phoneNumber = ref('')
const disabled = ref(false)
const validCharactersOnly = ref(false)

// replace all special (underscore, dash, dot) chars (just in case...)
phoneNumber.value = props?.inputValue?.value?.replace(/[_\-.]/g, '')

if (phoneNumber.value === null || phoneNumber.value === undefined) {
  phoneNumber.value = ''
} else if (!phoneNumber.value.match(/^[+]{1}(?:[0-9\-\\(\\)/.]\s?){6,15}[0-9]{1}$/) && phoneNumber.value !== '') {
  disabled.value = true
  phoneNumber.value = props.inputValue.value
  phoneNumberValid.value = false
}
nextTick(() => {
  validCharactersOnly.value = true
})

function deletePhoneNumber () {
  phoneNumber.value = ''
  disabled.value = false
}

function validate (phoneObject) {
  if (phoneObject?.valid === undefined) {
    phoneNumberValid.value = true
  } else {
    phoneNumberValid.value = phoneObject.valid
  }
}

function emitValidPhoneNumber (phoneNumber) {
  emit('update-phone-number', { id: props.inputName, value: phoneNumber, valid: phoneNumberValid.value })
}
</script>

<style scoped lang="scss">
.phone-number-input-wrapper {
  display: flex;
  flex-direction: column;
}

.input-container {
  display: flex;
  align-items: center;
}

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
  display: inline-flex;
}

.form-control.disabled {
  color: var(--fs-color-gray-700);
  background-color: var(--fs-color-gray-100);
}

.has-append {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}
.is-append {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
}

.invalid-feedback {
  display: block;
  margin-top: 0.25rem;
  font-size: 80%;
  color: #dc3545;
}
</style>
