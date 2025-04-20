<template>
  <div class="list-group-item manager p-2">
    <span class="text-muted">{{ $i18n('store.sm.inviteMember') }}</span>
    <UserSearchInput
      id="new-member-search"
      :placeholder="$i18n('store.sm.searchPlaceholder')"
      button-icon="fa-user-plus"
      :button-tooltip="$i18n('store.sm.inviteMember')"
      :filter="invitationFilter"
      class="mb-2"
      @user-selected="inviteTeamMember"
    />

    <b-button
      v-if="applications?.length"
      size="sm"
      variant="danger"
      block
      @click="$bvModal.show('requests')"
    >
      <i class="fas fa-question-circle" />
      {{ $i18n('store.requests', { count: applications.length}) }}
    </b-button>

    <b-button
      v-if="invitations?.length"
      size="sm"
      variant="outline-primary"
      block
      @click="$bvModal.show('invitations')"
    >
      <i class="fas fa-clipboard-question" />
      {{ $i18n('store.invitations', { count: invitations.length}) }}
    </b-button>

    <b-button
      size="sm"
      variant="success"
      block
      @click="$emit('toggle-sorting')"
    >
      <i class="fas fa-sort-amount-down" />
      {{ $i18n(`store.sm.sorting.${sortingFunctionName}`) }}
    </b-button>

    <StoreApplications
      :store-id="storeId"
      :store-title="storeTitle"
      :store-requests="applications"
    />
    <StoreInvitations v-bind="{ storeId, storeTitle, storeInvitations: invitations }" />
  </div>
</template>

<script>
import { inviteStoreMember } from '@/api/stores'
import StoreApplications from '@/components/Modals/Store/StoreApplications.vue'
import StoreInvitations from '@/components/Modals/Store/StoreInvitations.vue'
import UserSearchInput from '@/components/UserSearchInput.vue'
import { GET, pulseError } from '@/script'
import StoreData from '@/stores/stores'

export default {
  components: { UserSearchInput, StoreApplications, StoreInvitations },
  props: {
    team: { type: Array, required: true },
    storeId: { type: Number, required: true },
    storeTitle: { type: String, required: true },
    regionId: { type: Number, required: true },
    sortingFunctionName: { type: String, required: true },
  },
  computed: {
    applications () {
      return StoreData.getters.getStoreApplications()
    },
    invitations () {
      return StoreData.getters.getStoreInvitations()
    },
  },
  async mounted () {
    await Promise.all([
      StoreData.mutations.loadStoreApplications(this.storeId),
      StoreData.mutations.loadStoreInvitations(this.storeId),
    ])

    if (GET('showTeamRequests') && this.applications?.length) {
      this.$bvModal.show('requests')
    } else if (GET('showInvitations') && this.invitations?.length) {
      this.$bvModal.show('invitations')
    }
  },
  methods: {
    async inviteTeamMember (userId) {
      try {
        const invitation = await inviteStoreMember(this.storeId, userId)
        this.invitations.unshift(invitation)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
    },
    invitationFilter (userId) {
      const filter = user => (user.user?.id ?? user.id) === userId
      return !this.team.some(filter) && !this.invitations.map(x => x.user).some(filter) && !this.applications.some(filter)
    },
  },
}
</script>
