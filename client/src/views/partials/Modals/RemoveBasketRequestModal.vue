<template>
  <b-modal
    id="RemoveBasketRequestModal"
    ref="RemoveBasketRequestModal"
    :title="$i18n('basket.change-state', { name: basket.requests[0].user.name })"
    :cancel-title="$i18n('globals.close')"
    :ok-title="$i18n('globals.save')"
    :ok-disabled="!selectedStatus"
    @ok="save"
  >
    <div class="d-flex mb-3">
      <Avatar
        :user="basket.requests[0].user"
        :size="80"
        class="mt-1 pr-2 pt-1"
      />
      <p class="mb-0 ml-auto">
        {{ $i18n('request_time') }} {{ $dateFormatter.dateTime(basket.time) }}
      </p>
    </div>

    <p><strong>{{ $i18n('fetchstate') }}</strong></p>

    <b-form-radio-group
      id="basket-request-status"
      v-model="selectedStatus"
    >
      <b-form-radio
        v-for="option in radioOptions"
        :key="option.value"
        :value="option.value"
      >
        {{ option.text }}
      </b-form-radio>
    </b-form-radio-group>
  </b-modal>
</template>

<script>
import { BASKET_REQUEST_STATUS } from '@/stores/baskets'
import Avatar from '@/components/Avatar/Avatar.vue'
import { pulseError, pulseSuccess } from '@/script'
import { updateRequestStatus } from '@/api/baskets'

export default {
  components: { Avatar },
  props: { basket: { type: Object, required: true } },
  data () {
    return {
      selectedStatus: null,
      radioOptions: [
        {
          value: BASKET_REQUEST_STATUS.DELETED_PICKED_UP,
          text: this.$i18n('basket.state.okay'),
        },
        {
          value: BASKET_REQUEST_STATUS.NOT_PICKED_UP,
          text: this.$i18n('basket.state.nope'),
        },
        {
          value: BASKET_REQUEST_STATUS.DELETED_OTHER_REASON,
          text: this.$i18n('basket.state.gone'),
        },
        {
          value: BASKET_REQUEST_STATUS.DENIED,
          text: this.$i18n('basket.state.deny'),
        },
      ],
    }
  },
  methods: {
    async save () {
      try {
        await updateRequestStatus(this.basket.id, this.basket.requests[0].user.id, this.selectedStatus)
        pulseSuccess(this.$i18n('success'))
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
    },
  },
}
</script>
