<template>
  <div class="list-group-item manager p-2">
    <span class="text-muted">{{ $i18n('store.sm.makeRegularTeamMember') }}</span>
    <UserSearchInput
      id="new-member-search"
      :placeholder="$i18n('store.sm.searchPlaceholder')"
      button-icon="fa-user-plus"
      :button-tooltip="$i18n('store.sm.makeRegularTeamMember')"
      :filter="userId => !team.some(user => user.id === userId)"
      :region-id="regionId"
      class="mb-2"
      @user-selected="addNewTeamMember"
    />

    <b-button
      v-if="applications.storeRequests?.length"
      size="sm"
      variant="danger"
      block
      @click="$bvModal.show('requests')"
    >
      <i class="fas fa-address-card" />
      {{ $i18n('store.requests', { count: applications.storeRequests.length}) }}
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
  </div>
</template>

<script>
import { addStoreMember } from '@/api/stores'
import UserSearchInput from '@/components/UserSearchInput.vue'
import { pulseError } from '@/script'
import StoreData from '@/stores/stores'

export default {
  components: { UserSearchInput },
  props: {
    team: { type: Array, required: true },
    storeId: { type: Number, required: true },
    regionId: { type: Number, required: true },
    sortingFunctionName: { type: String, required: true },
  },
  computed: {
    applications () {
      return StoreData.getters.getStoreApplications()
    },
  },
  async mounted () {
    await StoreData.mutations.loadStoreApplications(this.storeId)
  },
  methods: {
    async addNewTeamMember (userId) {
      try {
        await addStoreMember(this.storeId, userId)
        await StoreData.mutations.loadStoreMember(this.storeId)
      } catch (e) {
        pulseError(this.$i18n('error_unexpected'))
      }
    },
  },
}
</script>
