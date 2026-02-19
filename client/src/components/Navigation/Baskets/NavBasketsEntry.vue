<template>
  <a
    class="dropdown-header dropdown-item"
    :class="{
      'list-group-item-warning': basket.requests.length > 0,
    }"
    :href="$url('basket', basket.id)"
  >
    <span
      class="d-flex justify-content-between align-items-center text-truncate"
    >
      <Avatar
        class="mr-2"
        :image="basket.picture"
        :icon="basket.picture ? undefined : 'fas fa-shopping-basket'"
        variant="light"
      />
      <span class="w-100 d-flex flex-column text-truncate">
        <span class="d-flex justify-content-between align-items-center text-truncate">
          <!-- eslint-disable vue/no-v-html -->
          <!-- Sanitized in Modules/Basket/BasketGateway.php getBasket() -->
          <span
            class="mb-1 text-truncate"
            v-html="basket.description"
          />
          <!-- eslint-enable -->
          <Time :time="new Date(basket.createdAt)" />
        </span>
        <small
          v-if="!basket.requests.length"
          class="mb-1 text-truncate"
          v-text="$t('basket.no_requests')"
        />
        <small
          v-if="basket.requests.length > 0"
          class="testing-basket-requested-by mb-1 text-truncate"
          v-text="$t('basket.requested_by', { name: basket.requests.map(r => r.user.name).join(', ') })"
        />
      </span>
    </span>
    <button
      v-for="(entry, key) in basket.requests"
      :key="key"
      class="testing-basket-requests w-100 img-thumbnail mt-1 d-flex align-items-center justify-content-between truncated"
      @click.prevent="openChat(entry.user.id, $event)"
    >
      <div class="d-flex align-items-center confirm-basket-dialog">
        <Avatar
          class="mr-2"
          :user="entry.user"
          :size="24"
        />
        <small>
          {{ entry.user.name }}
          {{ $dateFormatter.relativeTime(entry.requestedAt) }}
        </small>
      </div>
      <button
        v-b-tooltip.left="$t('basket.request_close')"
        :title="$t('basket.request_close')"
        class="testing-basket-requests-close btn btn-sm btn-outline-secondary"
        @click.prevent.stop="openRemoveDialog(basket.id, entry)"
      >
        <i class="fas fa-check" />
      </button>
    </button>
  </a>
</template>

<script>
// Others
import Avatar from '@/components/Avatar/Avatar.vue'
import Time from '@/components/Time.vue'
import conversationStore from '@/stores/conversations'

export default {
  components: { Avatar, Time },
  props: {
    basket: { type: Object, default: () => ({}) },
  },
  methods: {
    openChat (userId) {
      conversationStore.openChatWithUser(userId)
    },
    openRemoveDialog (basketId, request) {
      // Ammend the request object to include the basketId
      request.id = basketId
      this.$emit('basket-remove', request)
    },
  },
}
</script>
<style lang="scss" scoped>
.time-ago {
  color: var(--fs-color-grey-alpha-40);
  margin-left: 1rem;
}
.confirm-basket-dialog {
  color: var(--fs-color-primary-900);
}
</style>
