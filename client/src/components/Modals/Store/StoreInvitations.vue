<template>
  <b-modal
    id="invitations"
    :title="$t('store.invitation.title', { storeTitle })"
    header-class="d-flex"
    hide-footer
    static
    centered
    scrollable
    size="lg"
  >
    <b-alert show variant="info">
      <i class="fas fa-info-circle" />
      {{ $t('store.invitation.info') }}
    </b-alert>
    <div
      v-for="(invitation, index) in invitations"
      :key="invitation.user.id"
      class="invitation d-flex align-items-center py-2"
    >
      <Avatar :user="invitation.user" :size="50" />
      <div class="d-flex flex-grow-1 flex-wrap 1 ml-3">
        <div class="d-flex flex-grow-1 flex-wrap justify-content-end">
          <div class="flex-grow-1">
            <i
              v-b-tooltip.hover="invitation.verified ? $t('store.request.verified') : $t('store.request.unverified')"
              class="fas fa-fw mr-1"
              :class="{'fa-user-check': invitation.verified, 'fa-user-slash': !invitation.verified}"
            />
            <a
              class="invitee"
              :href="$url('profile', invitation.user.id)"
              v-text="invitation.user.name"
            />
            <TimeDisplay :time="invitation.date" class="ml-2" />
            <br>
            {{ $t('store.invitation.invited_by') }}
            <a :href="$url('profile', invitation.inviter.id)" v-text="invitation.inviter.name" />
          </div>
          <b-button
            variant="outline-danger"
            size="sm"
            @click="withdrawInvitation(invitation.user.id, index)"
          >
            <i class="fas fa-user-times" />
            {{ $t('store.invitation.withdraw') }}
          </b-button>
        </div>
      </div>
    </div>
  </b-modal>
</template>

<script>
import { withdrawStoreTeamInvitation } from '@/api/stores'
import Avatar from '@/components/Avatar/Avatar.vue'
import TimeDisplay from '@/components/TimeDisplay.vue'

export default {
  components: { Avatar, TimeDisplay },
  props: {
    storeId: { type: Number, required: true },
    storeTitle: { type: String, default: '' },
    storeInvitations: { type: Array, default: () => [] },
  },
  data () {
    return {
      invitations: this.storeInvitations,
    }
  },
  watch: {
    storeInvitations: {
      handler (newInvitations) {
        if (newInvitations.length <= 0) {
          this.$bvModal.hide('invitations')
        }
        this.invitations = newInvitations
      },
    },
  },
  methods: {
    async withdrawInvitation (userId, index) {
      await withdrawStoreTeamInvitation(this.storeId, userId)
      this.$delete(this.invitations, index)
    },
  },
}
</script>
<style scoped>
.invitation a.invitee {
  color: var(--fs-color-secondary-500);
}
.invitation:not(:last-child){
  border-bottom: 1px solid var(--fs-border-default);
}
</style>
