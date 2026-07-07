<template>
  <div id="header">
    <span v-if="conversationId===null">{{ $t('chat.new_message') }}</span>
    <component
      :is="storeId ? 'a' : 'span'"
      class="mr-2"
      :href="storeId ? $url('store', storeId) : ''"
    >
      {{ title }}
    </component>
    <!-- Named chats show the participant count already at two people, unnamed
         private chats only above that. Own element so title truncation keeps it visible. -->
    <span
      v-if="participantCount > 2 || (participantCount === 2 && hasOwnTitle)"
      class="participant-count text-muted mr-2"
    >({{ $t('chat.participant_count', { count: participantCount }) }})</span>
    <div class="images">
      <Avatar
        v-for="(member, i) in members"
        :key="i"
        class="ml-1"
        :user="member"
        :size="24"
      />
    </div>
  </div>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'

// Stores
import conversationStore from '@/stores/conversations'
import ProfileStore from '@/stores/profiles'
import { useUserStore } from '@/stores/user'

const LIMIT_DISPLAYED_USERS = 35

export default {
  components: {
    Avatar,
  },
  props: {
    conversationId: {
      type: Number,
      default: null,
    },
  },
  data () {
    const userStore = useUserStore()
    return {
      currentUserId: userStore.getUserId,
      title: '',
      storeId: null,
      members: [],
      participantCount: 0,
      hasOwnTitle: false,
    }
  },
  computed: {
  },
  watch: {
    async conversationId (newConversationId, oldConversationId) {
      await this.init()
    },
  },
  async created () {
    await this.init()
  },
  methods: {
    async init () {
      if (this.conversationId === null) {
        this.title = ''
        this.storeId = null
        this.members = []
        this.participantCount = 0
        this.hasOwnTitle = false
        return
      }

      const conversation = await conversationStore.getConversation(this.conversationId)
      // Full participant count (including the current user), independent of the
      // avatar list which excludes the current user and is capped for display.
      this.participantCount = conversation.members.length
      this.hasOwnTitle = !!conversation.title
      const otherMembers = conversation.members
        .filter(m => m !== this.currentUserId)
        .slice(0, LIMIT_DISPLAYED_USERS)

      this.members = otherMembers.map(member => ProfileStore.profiles[member] ?? {
        id: member,
        name: this.$t('chat.unknown_username'),
      })

      this.title = conversation.title || this.members.map(member => member.name).join(', ')
      this.storeId = conversation.storeId
    },
  },
}
</script>

<style lang="scss" scoped>

#header {
  display: flex;
  align-items: center;
}

#title {
  margin-right: 10px;
}

.member-img {
  padding-left: 2px;
}

.avatar {
  vertical-align: middle;
}

#title {
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
}

.participant-count {
  flex-shrink: 0;
  white-space: nowrap;
}

.images {
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  line-clamp: 1;
  -webkit-box-orient: vertical;
}

</style>
