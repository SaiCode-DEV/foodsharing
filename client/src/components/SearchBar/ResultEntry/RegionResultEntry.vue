<template>
  <a
    :href="$url('forum', region.id)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        <i
          v-if="isAmbassador"
          v-b-tooltip.noninteractive="$i18n('search.results.region.ambassador_tooltip')"
          class="fas fa-user-cog"
        />
        <i
          v-else-if="isHome"
          v-b-tooltip.noninteractive="$i18n('search.results.region.home_region_tooltip')"
          class="fas fa-home"
        />
        <i
          v-else-if="region.is_member"
          v-b-tooltip.noninteractive="$i18n('search.results.region.member_tooltip')"
          class="fas fa-user-check"
        />
        {{ region.name }}
      </h6>
      <br>
      <small class="separate">
        <span v-if="region.parent_id">
          {{ $i18n('search.results.in') }}
          <a :href="$url('forum', region.parent_id)">
            {{ region.parent_name }}
          </a>
        </span>
        <a
          v-if="region.email"
          :href="`mailto:${region.email}`"
          v-text="region.email"
        />
      </small>
    </div>
    <AvatarStack :users="region.ambassadors" />
  </a>
</template>
<script>
import AvatarStack from '@/components/Avatar/AvatarStack.vue'
import { useUserStore } from '@/stores/user'

const userStore = useUserStore()

export default {
  components: { AvatarStack },
  props: {
    region: {
      type: Object,
      required: true,
    },
  },
  setup () {
    return {
      userStore,
    }
  },
  computed: {
    isAmbassador () {
      // eslint-disable-next-line eqeqeq
      return this.region.ambassadors.includes(ambassador => ambassador.id == userStore.getUserId)
    },
    isHome () {
      return this.region.id === userStore.getHomeRegion
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: ' • ';
}
</style>
