<template>
  <span
    v-b-tooltip.hover="description"
    :data-status="cooperationStatus"
    class="status"
  >
    <i class="fas fa-circle" />
  </span>
</template>

<script>

import { VBTooltip } from 'bootstrap-vue'
import i18n from '@/helper/i18n'
import { COOPERATION_STATUS } from '@/stores/stores'

export default {
  directives: { VBTooltip },
  props: {
    cooperationStatus: {
      type: Number,
      required: true,
    },
  },
  computed: {
    description () {
      switch (this.cooperationStatus) {
        case COOPERATION_STATUS.NO_CONTACT:
          return i18n('storestatus.1')
        case COOPERATION_STATUS.IN_NEGOTIATION:
          return i18n('storestatus.2')
        case COOPERATION_STATUS.COOPERATION_STARTING:
        case COOPERATION_STATUS.COOPERATION_ESTABLISHED:
          return i18n('storestatus.5')
        case COOPERATION_STATUS.DOES_NOT_WANT_TO_WORK_WITH_US:
          return i18n('storestatus.4')
        case COOPERATION_STATUS.GIVES_TO_OTHER_CHARITY:
          return i18n('storestatus.6')
        case COOPERATION_STATUS.PERMANENTLY_CLOSED:
          return i18n('storestatus.7')
        default: // COOPERATION_STATUS.UNCLEAR
          return i18n('storestatus.0')
      }
    },
  },
}
</script>

<style scoped lang="scss">
.status {
    width: 50px;
    text-align: center;

    &[data-status="1"] {
        color: var(--fs-color-gray-500);
    }

    &[data-status="2"] {
        color: var(--fs-color-warning-500);
    }

    &[data-status="3"],
    &[data-status="5"] {
        color: var(--fs-color-secondary-500);
    }

    &[data-status="4"],
    &[data-status="7"] {
        color: var(--fs-color-danger-500);
    }

    &[data-status="6"] {
        color: var(--fs-color-info-500);
    }
}
</style>
