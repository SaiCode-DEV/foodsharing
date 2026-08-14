<template>
  <router-link
    :to="$url('forumThread', thread.regionId, thread.id)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        <i
          v-if="thread.pinnedLevel > 0"
          v-b-tooltip.noninteractive="$t('search.results.thread.sticky_tooltip')"
          class="fas fa-thumbtack"
        />
        <i
          v-else-if="thread.pinnedLevel < 0"
          v-b-tooltip.noninteractive="$t('search.results.thread.bottom_tooltip')"
          class="fas fa-sign-in-alt fa-rotate-90"
        />
        <i
          v-if="thread.isClosed"
          v-b-tooltip.noninteractive="$t('search.results.thread.closed_tooltip')"
          :class="{'ml-1': thread.pinnedLevel}"
          class="fas fa-lock"
        />
        {{ thread.name }}
      </h6>
      <br>
      <small class="separate">
        <span v-if="thread.regionId && !hideRegion">
          {{ $t('search.results.in') }}
          <router-link :to="$url('forum', thread.regionId)">
            {{ $t(`search.results.thread.${thread.isInsideAmbassadorForum ? 'ambassador_' : ''}forum`) }}
            {{ thread.regionName }}
          </router-link>
        </span>
        <span>
          {{ $t('search.results.thread.last_post') }}
          {{ $dateFormatter.relativeTime(new Date(thread.lastPostSentAt)) }}
        </span>
      </small>
    </div>
  </router-link>
</template>
<script>
import { useUserStore } from '@/stores/user'

export default {
  props: {
    thread: {
      type: Object,
      required: true,
    },
    hideRegion: {
      type: Boolean,
      default: false,
    },
  },
  data () {
    return {
      userStore: useUserStore(),
    }
  },
  computed: {
    isAmbassador () {
      // eslint-disable-next-line eqeqeq
      return this.region.ambassadors.includes(ambassador => ambassador.id == this.userStore.getUserId)
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: ' • ';
}
</style>
