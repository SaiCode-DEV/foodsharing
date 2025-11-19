<template>
  <div :class="{disabledLoading: isLoading}">
    <div class="ui-padding-bottom">
      <h3>{{ $t('personal_data.label') }}:</h3>
      <span v-if="mobileNumber">
        {{ $t('personal_data.mobile') }}: <a :href="'tel:' + mobileNumber">{{ mobileNumber }}</a>
      </span>
      <span v-if="landlineNumber">
        {{ $t('personal_data.landline') }}: {{ landlineNumber }}
      </span>
    </div>
    <div
      v-if="allowRequestByMessage"
    >
      <div v-if="hasRequested" class="ui-padding-bottom">
        <a
          class="button button-big"
          href="#"
          @click="openChat"
        >
          {{ $t('chat.open_chat') }}
        </a>
      </div>
      <div v-if="hasRequested" class="ui-padding-bottom">
        <a
          class="button button-big"
          href="#"
          @click="withdraw"
        >
          {{ $t('basket.withdraw_request') }}
        </a>
      </div>
      <div v-if="!hasRequested" class="ui-padding-bottom">
        <a
          class="button button-big"
          href="#"
          @click="$refs.modal_request.show()"
        >
          {{ $t('basket.request') }}
        </a>
      </div>
      <div>
        <span v-if="requestCount === 0">
          {{ $t('basket.no_requests') }}
        </span>
        <span v-if="requestCount > 0">
          {{ $t('basket.n_requests') }} <strong>{{ requestCount }}</strong>
        </span>
      </div>
    </div>
    <b-modal
      ref="modal_request"
      :title="$t('basket.request')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('basket.send_request')"
      modal-class="bootstrap"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      @ok="request(requestMessage)"
    >
      <b-form-textarea
        id="contactmessage"
        v-model="requestMessage"
        rows="4"
      />
    </b-modal>
  </div>
</template>

<script>

import { BFormTextarea, BModal } from 'bootstrap-vue'

import { requestBasket, withdrawBasketRequest } from '@/api/baskets'
import { pulseSuccess, pulseError } from '@/script'
import i18n from '@/helper/i18n'
import conversationStore from '@/stores/conversations'
import { HTTP_RESPONSE } from '@/consts'

export default {
  components: { BFormTextarea, BModal },
  props: {
    basketId: {
      type: Number,
      default: null,
    },
    basketCreatorId: {
      type: Number,
      default: null,
    },
    initialHasRequested: {
      type: Boolean,
      default: false,
    },
    initialRequestCount: {
      type: Number,
      default: null,
    },
    mobileNumber: {
      type: String,
      default: null,
    },
    landlineNumber: {
      type: String,
      default: null,
    },
    allowRequestByMessage: {
      type: Boolean,
      default: false,
    },
  },
  data () {
    return {
      isLoading: false,
      requestMessage: '',
      hasRequested: this.initialHasRequested,
      requestCount: this.initialRequestCount,
    }
  },
  _interval: null,
  methods: {
    async request (message) {
      this.isLoading = true
      try {
        const response = await requestBasket(this.basketId, message)
        this.requestCount = response.requestCount
        this.hasRequested = true
        pulseSuccess(i18n('basket.sent_request'))
      } catch (e) {
        if (e.code === HTTP_RESPONSE.BAD_REQUEST) {
          pulseError(i18n('basket.request_empty'))
        } else if (e.code === HTTP_RESPONSE.FORBIDDEN) {
          pulseError(i18n('basket.request_denied'))
        } else if (e.code === HTTP_RESPONSE.NOT_FOUND) {
          pulseError(i18n('basket.not_found'))
        } else {
          pulseError('Request basket failed: ' + e)
        }
      }
      this.isLoading = false
    },
    async withdraw () {
      this.isLoading = true
      try {
        const basket = await withdrawBasketRequest(this.basketId)
        this.requestCount = basket.requestCount
        this.hasRequested = false
        pulseSuccess(i18n('basket.withdrawn_request'))
      } catch (e) {
        if (e.code === HTTP_RESPONSE.NOT_FOUND) {
          pulseError(i18n('basket.not_found'))
        } else {
          pulseError(i18n('basket.not_withdrawn') + e)
        }
      }
      this.isLoading = false
    },
    openChat () {
      conversationStore.openChatWithUser(this.basketCreatorId)
    },
  },
}
</script>
<style scoped lang="scss">
h3 {
  font-size: 16px;
  margin-bottom: 10px;
}
</style>
