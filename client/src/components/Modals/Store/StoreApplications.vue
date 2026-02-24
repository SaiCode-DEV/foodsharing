<template>
  <b-modal
    id="requests"
    :title="$t('store.request.title', { storeTitle })"
    header-class="d-flex"
    hide-footer
    static
    centered
    scrollable
    size="lg"
  >
    <b-alert show variant="info">
      <i class="fas fa-info-circle" />
      {{ $t('store.request.air_line') }}
    </b-alert>
    <div
      v-for="(request, index) in requests"
      :key="request.user.id"
      class="request d-flex align-items-start py-2"
    >
      <Avatar
        :user="request.user"
        :size="50"
      />
      <div class="d-flex flex-grow-1 flex-wrap 1 ml-3">
        <div class="d-flex flex-grow-1 flex-wrap justify-content-end">
          <div class="flex-grow-1">
            <i
              v-b-tooltip.hover="request.verified ? $t('store.request.verified') : $t('store.request.unverified')"
              class="fas fa-fw mr-1"
              :class="{'fa-user-check': request.verified, 'fa-user-slash': !request.verified}"
            />
            <a :href="$url('profile', request.user.id)" v-text="request.user.name" />
            <Time :time="request.date" class="ml-2" />
            <p class="my-0" v-text="formatDistance(request.distanceInKm)" />
          </div>
          <b-button-group class="request-actions my-1" size="sm">
            <b-button
              variant="primary"
              @click="acceptRequest(storeId, request.user.id, false, index)"
            >
              <i class="fas fa-user-check" /> {{ $t('store.request.to-team') }}
            </b-button>
            <b-button
              v-b-tooltip="$t('store.request.to-jumper')"
              variant="outline-primary"
              @click="acceptRequest(storeId, request.user.id, true, index)"
            >
              <i class="fas fa-people-carry" />
            </b-button>
            <b-button
              v-b-tooltip.hover="$t('store.request.to-nowhere')"
              variant="outline-danger"
              @click="denyRequest(storeId, request.user.id, request.firstName, index)"
            >
              <i class="fas fa-user-times" />
            </b-button>
          </b-button-group>

          <b-button
            v-b-tooltip.hover="$t('chat.open_chat')"
            variant="outline-success"
            size="sm"
            class="ml-2 my-1"
            @click="openChat(request.user.id)"
          >
            <i class="fas fa-message" />
          </b-button>
        </div>
        <br>
        <blockquote
          v-if="request.message"
          class="my-1 w-100"
          v-text="request.message"
        />
      </div>
    </div>
    <RequiredMessageModal
      ref="denyRequestModal"
      message-key="decline_store_application"
      :initial-params="{ storeId, store: storeTitle }"
    >
      <b-alert show class="my-3">
        <i class="fas fa-save mr-2" />
        {{ $t('store.log.message_saved_info') }}
      </b-alert>
    </RequiredMessageModal>
  </b-modal>
</template>

<script>
import { acceptStoreRequest, declineStoreRequest } from '@/api/stores'
import Avatar from '@/components/Avatar/Avatar.vue'
import Time from '@/components/Time.vue'
import { hideLoader, showLoader, pulseError } from '@/script'
import StoreData from '@/stores/stores'
import conversationStore from '@/stores/conversations'
import RequiredMessageModal from '../RequiredMessageModal.vue'

export default {
  components: { Avatar, Time, RequiredMessageModal },
  props: {
    storeId: { type: Number, required: true },
    storeTitle: { type: String, default: '' },
    storeRequests: { type: Array, default: () => [] },
  },
  data () {
    return {
      requests: this.storeRequests,
    }
  },
  watch: {
    storeRequests: {
      handler (newRequests) {
        if (newRequests <= 0) {
          this.$bvModal.hide('requests')
        }
        this.requests = newRequests
      },
    },
  },
  methods: {
    async acceptRequest (storeId, userId, moveToStandby, index) {
      showLoader()
      try {
        await acceptStoreRequest(storeId, userId, moveToStandby)
        this.$delete(this.requests, index)
        await StoreData.mutations.loadStoreMember(this.storeId)
      } catch (e) {
        pulseError(this.$t('error_unexpected'))
      } finally {
        hideLoader()
      }
    },
    async denyRequest (storeId, userId, fistName, index) {
      showLoader()
      const message = await this.$refs.denyRequestModal.tryGetMessage({ name: fistName })
      if (message === false) return
      try {
        await declineStoreRequest(storeId, userId, message)
        this.$delete(this.requests, index)
      } catch (e) {
        pulseError(this.$t('error_unexpected'))
      } finally {
        hideLoader()
      }
    },
    formatDistance (distance) {
      if (distance === -1) {
        return this.$t('store.request.distance.unknown')
      }
      if (distance === 0) {
        return this.$t('store.request.distance.close')
      }
      return this.$t('store.request.distance.normal', { distance })
    },
    openChat (userId) {
      conversationStore.openChatWithUser(userId)
    },
  },
}
</script>
<style scoped>
.request a {
  color: var(--fs-color-secondary-500);
}
.request:not(:last-child){
  border-bottom: 1px solid var(--fs-border-default);
}
</style>
