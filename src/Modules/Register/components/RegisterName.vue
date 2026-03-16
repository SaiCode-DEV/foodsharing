<template>
  <form class="my-1">
    <div class="col-sm-auto">
      <label for="genderRadioGroup">{{ $t('register.select_your_gender') }}<sup><i class="fas fa-asterisk" /></sup></label>
      <b-form-group id="genderFormGroup">
        <b-form-radio-group
          id="genderRadioGroup"
          v-model="state.gender"
          :checked="state.gender"
          name="gender"
          @input="emit('update:gender', $event)"
        >
          <b-form-radio
            id="genderWoman"
            :value="2"
          >
            {{ $t('register.woman') }}
          </b-form-radio>
          <b-form-radio
            id="genderMan"
            :value="1"
          >
            {{ $t('register.man') }}
          </b-form-radio>
          <b-form-radio
            id="genderOther"
            :value="3"
          >
            {{ $t('register.other') }}
          </b-form-radio>
        </b-form-radio-group>
      </b-form-group>
    </div>
    <div class="my-1">
      <div class="col-sm-auto">
        <label for="firstname">{{ $t('register.login_name') }}<sup><i class="fas fa-asterisk" /></sup></label>
      </div> <div class="col-sm-auto">
        <input
          id="firstname"
          v-model="v$.firstname.$model"
          :class="{ 'is-invalid': v$.firstname.$error }"
          type="text"
          name="firstname"
          class="form-control"
          @input="emit('update:firstname', $event.target.value)"
        >
        <div
          v-if="v$.firstname.$error"
          class="invalid-feedback"
        >
          <span v-if="!v$.firstname.required">{{ $t('register.firstname_required') }}</span>
          <span v-if="!v$.firstname.minLength">{{ $t('register.firstname_minLength') }}</span>
        </div>
      </div>
      <div class="my-1">
        <div class="col-sm-auto">
          <label for="lastname">{{ $t('register.login_surname') }}<sup><i class="fas fa-asterisk" /></sup></label>
        </div> <div class="col-sm-auto">
          <input
            id="lastname"
            v-model="v$.lastname.$model"
            :class="{ 'is-invalid': v$.lastname.$error }"
            type="text"
            name="lastname"
            class="form-control"
            @input="emit('update:lastname', $event.target.value)"
          >
          <div v-if="v$.lastname.$error" class="invalid-feedback">
            <span v-if="!v$.lastname.required">{{ $t('register.lastname_required') }}</span>
            <span v-if="!v$.lastname.minLength">{{ $t('register.lastname_minLength') }}</span>
          </div>
        </div>
      </div>
      <button
        class="btn btn-primary ml-3 mt-3"
        type="button"
        @click.prevent="emit('prev')"
      >
        {{ $t('register.prev') }}
      </button>
      <button
        class="btn btn-primary mt-3"
        type="submit"
        @click.prevent="redirect()"
      >
        {{ $t('register.next') }}
      </button>
      <span class="mr-3 d-flex flex-row-reverse">{{ $t('register.requiredFields') }}<sup><i class="fas fa-asterisk" /></sup></span>
    </div>
  </form>
</template>
<script setup>
import { reactive, defineProps, defineEmits } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, minLength } from '@vuelidate/validators'

const props = defineProps({
  firstname: { type: String, default: '' },
  lastname: { type: String, default: '' },
  gender: { type: Number, default: 1 },
})

const emit = defineEmits(['update:firstname', 'update:lastname', 'update:gender', 'prev', 'next'])

const state = reactive({
  firstname: props.firstname,
  lastname: props.lastname,
  gender: props.gender,
})

const rules = {
  firstname: { required, minLength: minLength(2) },
  lastname: { required, minLength: minLength(2) },
  gender: { required },
}

const v$ = useVuelidate(rules, state)

async function redirect () {
  if (await v$.value.$validate()) {
    emit('next')
  }
}
</script>
<style lang="scss" scoped>
.invalid-feedback {
  font-size: 100%;
  display: unset;
}
</style>
