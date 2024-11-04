<template>
  <div id="header">
    <span v-if="conversationId===null">{{ $i18n('chat.new_message') }}</span>
    <component
      :is="storeId ? 'a' : 'span'"
      class="mr-2"
      :href="storeId ? $url('store', storeId) : ''"
      v-text="title"
    />
    <div class="images">
      <Avatar
        v-for="member in members"
        :key="member.id"
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

const userStore = useUserStore()

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
    return {
      currentUserId: userStore.getUserId,
      title: '',
      storeId: null,
      members: [],
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
        return
      }

      const conversation = await conversationStore.getConversation(this.conversationId)
      const otherMembers = conversation.members.filter(m => m !== this.currentUserId).slice(0, LIMIT_DISPLAYED_USERS)

      this.members = otherMembers.map(member => ProfileStore.profiles[member])

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

.images {
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  line-clamp: 1;
  -webkit-box-orient: vertical;
}

</style>
