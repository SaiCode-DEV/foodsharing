<template>
  <a
    class="d-flex dropdown-item search-result"
    href="#"
    tabindex="1"
    @click="openChat"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        {{ title }}
      </h6>
      <br>
      <small>
        <a :href="$url('profile', chat.last_foodsaver_id)">
          {{ chat.last_foodsaver_name }}
        </a>
        {{ $dateFormatter.relativeTime(new Date(chat.last_message_date)) }}:
        {{ chat.last_message }}
      </small>
    </div>
    <AvatarStack
      :registered-users="fullSizeMembersList"
      :max-width-in-px="150"
      :show-overflow-tooltip="false"
    />
  </a>
</template>
<script>
import AvatarStack from '@/components/AvatarStack'
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
      const missingProfilesCount = this.chat.member_count - this.chat.members.length
      return [...this.chat.members, ...Array(missingProfilesCount).fill({})]
    },
    title () {
      if (this.chat.name) return this.chat.name
      const formatter = new Intl.ListFormat(this.$i18n('calendar.locale'), { type: 'conjunction' })
      const allNamesAvailable = this.chat.members.length === this.chat.member_count
      const names = this.chat.members.map(member => member.name)
      if (!allNamesAvailable) names.push(this.$i18n('search.results.chat.chat_with_others'))
      const jointNames = formatter.format(names)
      return this.$i18n('search.results.chat.chat_with', { names: jointNames })
    },
  },
  methods: {
    openChat () {
      conversationStore.openChat(this.chat.id)
      this.$emit('close-modal')
    },
  },
}
</script>
