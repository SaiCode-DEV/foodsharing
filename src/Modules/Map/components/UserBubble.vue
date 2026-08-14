<template>
  <map-popup id="userBubbleModal" :is-loading="loading">
    <template #popup-header>
      <h3>{{ profile.name }}</h3>
    </template>

    <div class="d-flex">
      <Avatar :user="profile" :size="100" />
      <Markdown
        v-if="aboutMeIntern"
        :source="aboutMeIntern"
        class="ml-2"
      />
    </div>

    <template #popup-footer>
      <a
        v-if="!loading && profile.id !== currentUserId"
        class="btn btn-primary"
        type="button"
        @click="openChat"
        v-text="$t('chat.open_chat')"
      />
      <router-link
        v-if="!loading"
        class="btn btn-primary"
        type="button"
        :to="$url('profile', id)"
      >
        {{ $t('map.users.go') }}
      </router-link>
    </template>
  </map-popup>
</template>

<script>
import Markdown from '@/components/Markdown/Markdown'
import { getUserBubbleContent } from '@/api/map'
import MapBubbleMixin from './MapBubbleMixin'
import Avatar from '@/components/Avatar/Avatar.vue'
import { chat } from '@/script'
import { useUserStore } from '@/stores/user'

export default {
  components: { Markdown, Avatar },
  mixins: [MapBubbleMixin],
  data: () => ({
    id: null,
    profile: null,
    aboutMeIntern: '',
  }),
  computed: {
    currentUserId () {
      return useUserStore().getUserId
    },
  },
  methods: {
    async show (userId) {
      await this.timedFetchAction(
        getUserBubbleContent(userId),
        'userBubbleModal',
        (data) => {
          Object.assign(this, data)
          this.id = data.profile.id
        },
      )
    },
    openChat () {
      chat(this.profile.id)
    },
  },
}
</script>
