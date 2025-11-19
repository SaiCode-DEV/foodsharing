<template>
  <b-modal
    id="RemoveBasketRequestModal"
    ref="RemoveBasketRequestModal"
    :title="$t('basket.change-state', { name: request.user.name })"
    :cancel-title="$t('globals.close')"
    :ok-title="$t('globals.save')"
    :ok-disabled="!selectedStatus"
    @ok="save"
  >
    <div class="d-flex mb-3">
      <Avatar
        :user="request.user"
        :size="80"
        class="mt-1 pr-2 pt-1"
      />
      <p class="mb-0 ml-auto">
        {{ $t('request_time') }} {{ $dateFormatter.dateTime(request.time) }}
      </p>
    </div>

    <p><strong>{{ $t('fetchstate') }}</strong></p>

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
import { BASKET_REQUEST_STATUS, useBasketStore } from '@/stores/baskets'
import Avatar from '@/components/Avatar/Avatar.vue'
import { pulseError, pulseSuccess } from '@/script'

const basketStore = useBasketStore()

export default {
  components: { Avatar },
  props: { request: { type: Object, required: true } },
  data () {
    return {
      selectedStatus: null,
      radioOptions: [
        {
          value: BASKET_REQUEST_STATUS.DELETED_PICKED_UP,
          text: this.$t('basket.state.okay'),
        },
        {
          value: BASKET_REQUEST_STATUS.NOT_PICKED_UP,
          text: this.$t('basket.state.nope'),
        },
        {
          value: BASKET_REQUEST_STATUS.DELETED_OTHER_REASON,
          text: this.$t('basket.state.gone'),
        },
        {
          value: BASKET_REQUEST_STATUS.DENIED,
          text: this.$t('basket.state.deny'),
        },
      ],
    }
  },
  methods: {
    async save () {
      try {
        await basketStore.updateBasketRequestStatus(
          this.request.id,
          this.request.user.id,
          this.selectedStatus,
        )
        pulseSuccess(this.$t('success'))
      } catch (e) {
        pulseError(this.$t('error_unexpected'))
      }
    },
  },
}
</script>
