<template>
  <router-link
    :to="$url('publicRegion', region.id)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        <i
          v-if="isAmbassador"
          v-b-tooltip.noninteractive="$t('search.results.region.ambassador_tooltip')"
          class="fas fa-user-cog"
        />
        <i
          v-else-if="isHome"
          v-b-tooltip.noninteractive="$t('search.results.region.home_region_tooltip')"
          class="fas fa-home"
        />
        <i
          v-else-if="region.isMember"
          v-b-tooltip.noninteractive="$t('search.results.region.member_tooltip')"
          class="fas fa-user-check"
        />
        {{ region.name }}
      </h6>
      <br>
      <small class="separate">
        <span v-if="region.parentId">
          {{ $t('search.results.in') }}
          <router-link :to="$url('publicRegion', region.parentId)">
            {{ region.parentName }}
          </router-link>
        </span>
        <a
          v-if="region.email"
          :href="`mailto:${region.email}`"
          @click.stop
          v-text="region.email"
        />
      </small>
    </div>
    <AvatarStack :users="region.ambassadors" />
  </router-link>
</template>
<script>
import AvatarStack from '@/components/Avatar/AvatarStack.vue'
import { useUserStore } from '@/stores/user'

export default {
  components: { AvatarStack },
  props: {
    region: {
      type: Object,
      required: true,
    },
  },
  setup () {
    const userStore = useUserStore()
    return {
      userStore,
    }
  },
  computed: {
    isAmbassador () {
      // eslint-disable-next-line eqeqeq
      return this.region.ambassadors.includes(ambassador => ambassador.id == this.userStore.getUserId)
    },
    isHome () {
      return this.region.id === this.userStore.getHomeRegion
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: ' • ';
}
</style>
