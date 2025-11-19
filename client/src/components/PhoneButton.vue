<template>
  <b-dropdown
    :variant="variant"
    split
    right
    class="phone-button"
    :split-href="$url('phone_number', phoneNumber, true)"
  >
    <template #button-content>
      <i class="fas fa-phone" />
    </template>
    <b-dropdown-item
      :href="$url('phone_number', phoneNumber, true)"
    >
      <i class="fas fa-phone" />
      {{ $t('pickup.call') }}
    </b-dropdown-item>
    <b-dropdown-item
      @click.prevent="copyToClipboard(phoneNumber)"
    >
      <i class="fas fa-clone" />
      {{ $t('pickup.copyNumber') }}
    </b-dropdown-item>
  </b-dropdown>
</template>

<script>
import CopyToClipboardMixin from '@/mixins/CopyToClipboardMixin.js'

export default {
  mixins: [CopyToClipboardMixin],
  props: {
    phoneNumber: {
      type: String,
      required: true,
    },
    variant: {
      type: String,
      default: 'primary',
    },
  },
}
</script>

<style lang="scss" scoped>
.phone-button ::v-deep{
   .btn:not(.dropdown-toggle-split) {
    padding-right: 3px;
  }
  .dropdown-toggle-split {
    padding-left: 3px;
  }
}
</style>
