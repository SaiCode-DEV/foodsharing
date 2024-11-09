<template>
  <Container :title="storeName" :tag="`store-options-${storeId}`">
    <ContainerButton
      v-if="teamConversationId != null && isUserInStore"
      text-key="store.chat.team"
      icon="fas fa-comment"
      @click="openChat(teamConversationId)"
    />
    <ContainerButton
      v-if="jumperConversationId != null && isUserInStore || isJumper"
      text-key="store.chat.jumper"
      icon="fas fa-running"
      @click="openChat(jumperConversationId)"
    />
    <ContainerButton
      v-if="mayLeaveStoreTeam && isUserInStore || isJumper"
      variant="danger"
      text-key="storeedit.team.leave"
      icon="fas fa-user-times"
      @click="removeFromTeam(fsId, $i18n('storeedit.team.leave_myself'))"
    />
  </Container>
</template>

<script>
import conversationStore from '@/stores/conversations'
import { pulseError } from '@/script'
import { useUserStore } from '@/stores/user'
import { removeStoreMember } from '@/api/stores'
import Container from '@/components/Container/Container.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'

export default {
  components: { Container, ContainerButton },
  props: {
    storeName: { type: String, required: true },
    fsId: { type: Number, required: true },
    mayLeaveStoreTeam: { type: Boolean, default: false },
    teamConversationId: {
      type: Number,
      default: null,
    },
    jumperConversationId: {
      type: Number,
      default: null,
    },
    mayEditStore: {
      type: Boolean,
      default: null,
    },
    isCoordinator: {
      type: Boolean,
      default: null,
    },
    storeId: {
      type: Number,
      default: null,
    },
    isUserInStore: { type: Boolean, default: false },
    isJumper: { type: Boolean, default: false },
    isVerified: { type: Boolean, default: false },
  },
  setup () {
    const userStore = useUserStore()
    return {
      userStore,
    }
  },
  methods: {
    openChat (conversationId) {
      conversationStore.openChat(conversationId)
    },
    async removeFromTeam (fsId, fsName) {
      if (!fsId) {
        return
      }
      if (!confirm(this.$i18n('store.sm.reallyRemove', { name: fsName }))) {
        return
      }
      this.isBusy = true
      try {
        await removeStoreMember(this.storeId, this.userStore.getUserId)
        window.location.href = this.$url('dashboard')
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
        this.isBusy = false
        return
      }
      this.isBusy = false
    },
  },
}
</script>
<style lang="scss" scoped>
.list-group-item:not(:last-child) {
  border-bottom: 0;
}
</style>
