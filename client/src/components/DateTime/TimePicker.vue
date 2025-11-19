<template>
  <b-input-group class="time-picker" :class="{ empty: !value }">
    <b-input-group-addon :style="{ '--placeholder': placeholder }">
      <b-form-timepicker
        :value="value"
        button-only
        button-variant="outline-dark"
        hide-header
        :locale="locale"
        v-bind="labelsTimepicker"
        :minutes-step="5"
        :class="{ error: state === false }"
        @input="value => $emit('input', value.substring(0, 5))"
      />
    </b-input-group-addon>
    <b-form-input
      :value="value"
      type="time"
      :state="state"
      @input="value => $emit('input', value)"
    />
  </b-input-group>
</template>

<script>
import i18n, { locale } from '@/helper/i18n'

const translations = ['labelHours', 'labelMinutes', 'labelSeconds', 'labelIncrement', 'labelDecrement', 'labelSelected', 'labelNoTimeSelected', 'labelCloseButton']

export default {
  props: {
    value: { type: String, default: null },
    state: { type: Boolean, default: null },
  },
  data () {
    return {
      locale,
      labelsTimepicker: Object.fromEntries(translations.map(key => [key, i18n(`timepicker.${key}`)])),
      placeholder: JSON.stringify(this.$t('timepicker.placeholder')),
    }
  },
}
</script>
<style lang="scss" scoped>
::v-deep.b-form-timepicker>button:not(:hover) {
  border-color: var(--fs-border-default);
}

::v-deep.b-form-timepicker.error>button:not(:hover) {
  border-color: #cf3a00;
}

.input-group-prepend {
  position: relative;
}

.time-picker.empty {
  .input-group-prepend::after {
    content: var(--placeholder);
    pointer-events: none;

    // Style like placeholders:
    color: #6c757d !important;
    font-size: 16px;

    // Position
    width: 20em;
    position: absolute;
    left: 100%;
    z-index: 1;
    top: 50%;
    transform: translateY(-50%);
    padding-left: 15px;
  }

  ::v-deep input[type=time]:not(:focus) {
    color: transparent;
  }
}

</style>
