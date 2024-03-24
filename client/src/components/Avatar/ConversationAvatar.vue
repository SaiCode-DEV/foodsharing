<template>
  <b-avatar
    class="wrapper"
    :size="size"
    rounded
    badge-offset="-5px"
    :badge="badgeContent"
    badge-variant="info"
  >
    <Avatar
      v-for="(user, index) in users"
      :key="index"
      :size="user.size"
      shape="square"
      :user="user"
    />
  </b-avatar>
</template>

<script>
import DataUser from '@/stores/user'
import profileStore from '@/stores/profiles'
import Avatar from '@/components/Avatar/Avatar.vue'
import { MARKED_AS_UNREAD } from '@/stores/conversations'

export default {
  components: { Avatar },
  props: {
    conversation: { type: Object, default: () => ({}) },
    size: { type: Number, default: 35 },
  },
  computed: {
    loggedinUser () {
      return DataUser.getters.getUser()
    },
    users () {
      const lastId = this.conversation?.lastMessage?.authorId

      const members = this.conversation.members
        // without ourselve
        .filter(m => m !== this.loggedinUser.id)
        // bring last participant to the top
        .sort((a, b) => {
          if (a === lastId) return -1
          if (b === lastId) return 1
          return 0
        })
        // we dont need more then 4
        .slice(0, 4)
      return members.map((userId, index) => ({
        size: this.getSize(index, members.length),
        avatar: profileStore.profiles[userId].avatar ?? '',
      }))
    },
    gridCols () {
      return this.users.length > 1 ? 2 : 1
    },
    gridRows () {
      return this.users.length > 2 ? 2 : 1
    },
    avatarSize () {
      return this.size / this.gridRows
    },
    badgeContent () {
      if (!this.conversation?.unreadMessages) return false
      if (this.conversation.unreadMessages === MARKED_AS_UNREAD) return true
      if (this.conversation.unreadMessages > 99) return '99+'
      return this.conversation.unreadMessages.toString()
    },
  },
  methods: {
    getSize (index, total) {
      return index + total < 4 ? this.size : this.size / 2
    },
  },
}
</script>
<style lang="scss" scoped>
.wrapper::v-deep .b-avatar-custom {
  display: grid;
  grid-template-columns: repeat(v-bind("gridCols"), v-bind("size / gridCols + 'px'"));
  grid-template-rows: repeat(v-bind("gridRows"), v-bind("size / gridRows + 'px'"));

  &:has(>:last-child:nth-child(3)) :first-child {
    grid-row: span 2;
  }
}
</style>
