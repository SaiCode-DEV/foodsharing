<template>
  <a
    :href="$url('profile', user.id)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <Avatar
      class="mr-2"
      :size="35"
      :url="user.avatar"
    />
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        <i
          v-if="user.is_buddy"
          v-b-tooltip.noninteractive="$i18n('buddy.is_buddy')"
          class="fas fa-user-friends"
        />
        {{ user.name }} {{ user.last_name }}
      </h6>
      <i
        v-if="!user.is_verified"
        v-b-tooltip.noninteractive="$i18n('store.request.unverified')"
        class="fas fa-user-slash"
      />
      <small>ID: {{ user.id }}</small>
      <br>
      <small class="separate">
        <span v-if="user.region_id">
          {{ $i18n('search.results.from') }}
          <a :href="$url('forum', user.region_id)">
            {{ user.region_name }}
          </a>
        </span>
        <i v-else>{{ $i18n('search.results.user.no_home_region') }}</i>
        <span v-if="user.email">
          <a :href="`mailto:${user.email}`">
            {{ user.email }}
          </a>
        </span>
      </small>
    </div>
    <PhoneButton
      v-if="user.mobile"
      :phone-number="user.mobile"
    />
    <b-button
      v-b-tooltip.noninteractive="$i18n('chat.open_chat')"
      variant="primary"
      class="ml-2"
      @click.prevent="openChat"
    >
      <i class="fas fa-comment" />
    </b-button>
  </a>
</template>
<script>
import Avatar from '@/components/Avatar.vue'
import PhoneButton from '@/components/PhoneButton.vue'
import { chat } from '@/script'

export default {
  components: { Avatar, PhoneButton },
  props: {
    user: {
      type: Object,
      required: true,
    },
  },
  methods: {
    openChat () {
      chat(this.user.id)
      this.$emit('close')
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: '•';
}
</style>
