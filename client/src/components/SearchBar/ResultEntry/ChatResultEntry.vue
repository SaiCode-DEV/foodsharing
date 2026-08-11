<template>
  <a
    class="d-flex dropdown-item search-result"
    href="#"
    tabindex="1"
    @click.prevent="openChat"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        {{ title }}
      </h6>
      <br>
      <small>
        <a :href="$url('profile', chat.lastFoodsaverId)">
          {{ chat.lastFoodsaverName }}
        </a>
        {{ $dateFormatter.relativeTime(new Date(chat.lastMessageSentAt)) }}:
        {{ chat.lastMessage }}
      </small>
    </div>
    <AvatarStack
      :users="fullSizeMembersList"
      :max-width-in-px="150"
      :show-overflow-tooltip="false"
    />
  </a>
</template>
<script>
import AvatarStack from '@/components/Avatar/AvatarStack'
import conversationStore from '@/stores/conversations'

export default {
  components: { AvatarStack },
  props: {
    chat: {
      type: Object,
      required: true,
    },
  },
  computed: {
    fullSizeMembersList () {
      const missingProfilesCount = this.chat.memberCount - this.chat.members.length
      return [...this.chat.members, ...Array(missingProfilesCount).fill({})]
    },
    title () {
      if (this.chat.name) return this.chat.name
      const formatter = new Intl.ListFormat(this.$t('calendar.locale'), { type: 'conjunction' })
      const allNamesAvailable = this.chat.members.length === this.chat.memberCount
      const names = this.chat.members.map(member => member.name)
      if (!allNamesAvailable) names.push(this.$t('search.results.chat.chat_with_others'))
      const jointNames = formatter.format(names)
      return this.$t('search.results.chat.chat_with', { names: jointNames })
    },
  },
  methods: {
    openChat () {
      conversationStore.openChat(this.chat.id)
      this.$emit('close-without-returning-focus')
    },
  },
}
</script>
