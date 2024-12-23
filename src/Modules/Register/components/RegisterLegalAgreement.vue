<template>
  <form class="my-1 ml-3 mt-3">
    <b-form-checkbox
      id="acceptGdpr"
      :checked="acceptGdpr"
      name="acceptGdpr"
      @change="emit('update:acceptGdpr', $event)"
    >
      {{ $i18n('register.have_read_the_legal_stuff1') }}
      <a
        :href="$url('dataprivacy')"
        target="_blank"
        rel="noopener noreferrer nofollow"
      >{{ $i18n('legal.pp') }}</a> {{ $i18n('register.have_read_the_legal_stuff2') }}
    </b-form-checkbox>
    <b-form-checkbox
      id="acceptLegal"
      :checked="acceptLegal"
      name="acceptLegal"
      @input="emit('update:acceptLegal', $event)"
    >
      {{ $i18n('register.have_read_the_legal_stuff1') }}
      <a
        :href="$url('wiki_legal_agreement')"
        target="_blank"
        rel="noopener noreferrer nofollow"
      >{{ $i18n('legal.legal_agreement') }}</a> {{ $i18n('register.have_read_the_legal_stuff2') }}
    </b-form-checkbox>
    <b-form-checkbox
      id="subscribeNewsletter"
      :unchecked="subscribeNewsletter"
      name="subscribeNewsletter"
      @input="emit('update:subscribeNewsletter', $event)"
    >
      {{ $i18n('register.signup_newsletter') }}
    </b-form-checkbox>
    <button
      class="btn btn-primary ml-3 mt-3"
      type="button"
      @click="emit('prev')"
    >
      {{ $i18n('register.prev') }}
    </button>
    <button
      :disabled="!accepted"
      type="submit"
      class="btn btn-primary mt-3"
      @click.prevent="emit('submit')"
    >
      {{ $i18n('register.finish') }}
    </button>
  </form>
</template>
<script setup>
import { defineEmits, defineProps, computed } from 'vue'

const emit = defineEmits(['update:acceptGdpr', 'update:acceptLegal', 'update:subscribeNewsletter', 'prev', 'submit'])

const props = defineProps({
  subscribeNewsletter: { type: Boolean, default: false },
  acceptGdpr: { type: Boolean, default: false },
  acceptLegal: { type: Boolean, default: false },
})

const accepted = computed(() => props.acceptGdpr && props.acceptLegal)
</script>
