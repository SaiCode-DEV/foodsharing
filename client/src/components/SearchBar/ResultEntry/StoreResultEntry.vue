<template>
  <a
    :href="$url('store', store.id)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        <i
          v-if="store.isManager"
          v-b-tooltip.noninteractive="$t('search.results.store.manager_tooltip')"
          class="fas fa-user-cog"
        />
        <i
          v-else-if="isMember"
          v-b-tooltip.noninteractive="$t('search.results.store.member_tooltip')"
          class="fas fa-user-check"
        />
        <i
          v-else-if="isJumper"
          v-b-tooltip.noninteractive="$t('search.results.store.jumper_tooltip')"
          class="fas fa-running"
        />
        {{ store.name }}
      </h6>
      <br>
      <small class="separate">
        <span v-if="store.regionId">
          {{ $t('search.results.in') }}
          <a :href="$url('stores', store.regionId)">
            {{ store.regionName }}
          </a>
        </span>
        <span>
          {{ $t(`storestatus.${store.cooperationStatus}`) }}
        </span>
        <span v-if="store.city">
          <span v-if="store.street">{{ store.street }},</span>
          <span v-if="store.zipCode">{{ store.zipCode }}</span>
          {{ store.city }}
        </span>
      </small>
    </div>
  </a>
</template>
<script>
import { STORE_TEAM_STATE } from '@/stores/stores'

export default {
  components: { },
  props: {
    store: {
      type: Object,
      required: true,
    },
  },
  computed: {
    isMember () {
      return this.store.membershipStatus === STORE_TEAM_STATE.ACTIVE
    },
    isJumper () {
      return this.store.membershipStatus === STORE_TEAM_STATE.JUMPER
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: ' • ';
}
</style>
