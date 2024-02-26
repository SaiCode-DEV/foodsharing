<!-- eslint-disable vue/max-attributes-per-line -->
<template>
  <div class="store-applications bootstrap">
    <b-modal
      id="requests"
      :title="$i18n('store.request.title', { storeTitle })"
      header-class="d-flex"
      hide-footer
      static
      centered
      scrollable
      size="lg"
    >
      <b-alert
        show
        variant="info"
      >
        <i class="fas fa-info-circle" />
        {{ $i18n('store.request.air_line') }}
      </b-alert>
      <div
        v-for="(request, index) in requests"
        :key="request.id"
        class="request d-flex align-items-center flex-wrap flex-sm-nowrap py-2"
      >
        <!-- TODO send data in fitting format -->
        <Avatar
          :user="{...request, avatar: request.photo, isSleeping: request.sleep_status }"
          :size="50"
        />
        <div class="name font-weight-bolder flex-grow-1 mx-3">
          <i
            v-b-tooltip.hover="request.verified ? $i18n('store.request.verified') : $i18n('store.request.unverified')"
            class="fas fa-fw mr-1"
            :class="{'fa-user-check': request.verified, 'fa-user-slash': !request.verified}"
          />
          <a :href="$url('profile', request.id)">
            {{ request.name }}
          </a>
          <p>
            {{ formatDistance(request.distance) }}
          </p>
        </div>

        <b-button-group class="request-actions my-1" size="sm">
          <b-button
            variant="primary"
            @click="acceptRequest(storeId, request.id, false, index)"
          >
            <i class="fas fa-user-check" /> {{ $i18n('store.request.to-team') }}
          </b-button>
          <b-button
            variant="outline-primary"
            @click="acceptRequest(storeId, request.id, true, index)"
          >
            <i class="fas fa-user-tag" /> {{ $i18n('store.request.to-jumper') }}
          </b-button>
          <b-button
            v-b-tooltip.hover="$i18n('store.request.to-nowhere')"
            variant="outline-danger"
            @click="denyRequest(storeId, request.id, index)"
          >
            <i class="fas fa-user-times" />
          </b-button>
        </b-button-group>
      </div>
    </b-modal>
  </div>
</template>

<script>
import { acceptStoreRequest, declineStoreRequest } from '@/api/stores'
import Avatar from '@/components/Avatar/Avatar.vue'
import { hideLoader, showLoader, pulseError } from '@/script'
import StoreData from '@/stores/stores'

export default {
  components: { Avatar },
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
        pulseError(this.$i18n('error_unexpected'))
      } finally {
        hideLoader()
      }
    },
    async denyRequest (storeId, userId, index) {
      showLoader()
      try {
        await declineStoreRequest(storeId, userId)
        this.$delete(this.requests, index)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      } finally {
        hideLoader()
      }
    },
    formatDistance (distance) {
      if (distance === null) {
        return this.$i18n('store.request.distance_unknown')
      }
      if (distance === 0) {
        return this.$i18n('store.request.distance_close')
      }
      return this.$i18n('store.request.distance', { distance })
    },
  },
}
</script>

<style lang="scss" scoped>
.request-actions .btn {
  white-space: unset;
}

.member-pic ::v-deep img {
  width: 50px;
}

.name a {
  color: var(--fs-color-secondary-500);
  font-size: 0.875rem;
}
</style>
