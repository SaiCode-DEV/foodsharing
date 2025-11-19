<template>
  <form class="my-1">
    <div class="col-sm-auto">
      <label>{{ $t('register.geb_datum') }}<sup><i class="fas fa-asterisk" /></sup></label>
    </div>
    <div class="mt-2 col-sm-auto">
      <b-form-input
        id="register-birthdate-input"
        v-model="dateString"
        type="date"
        autocomplete="off"
      />
      <div
        v-if="!isValid"
        class="alert alert-danger mt-2"
        v-text="$t('register.error_birthdate')"
      />
    </div>
    <div class="mt-3 col-sm-auto">
      <div class="alert alert-info">
        <i class="fas fa-info-circle" />
        <Markdown :source="$t('register.birthdate_hint', { url: $url('dataprivacy') })" />
      </div>
    </div>
    <button
      class="btn btn-primary ml-3 mt-3"
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
    <span class="mr-3 d-flex flex-row-reverse">{{ $t('register.requiredFields') }}<sup><i class="fas fa-asterisk" /></sup></span>
  </form>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown.vue'

export default {
  components: { Markdown },
  props: {
    birthdate: {
      type: Date,
      default: null,
    },
  },
  data () {
    return {
      dateString: this.birthdate,
    }
  },
  computed: {
    date () {
      return new Date(this.dateString)
    },
    isValid () {
      const age = this.$dateFormatter.getDifferenceToNowInYears(this.date)
      return age >= 18 && age <= 125 && !!this.dateString
    },
  },
  methods: {
    redirect () {
      if (this.isValid) {
        this.$emit('save', this.date)
        this.$emit('next')
      }
    },
  },
}
</script>
