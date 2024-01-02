<template>
  <a
    :href="$url('forumThread', thread.region_id, thread.id)"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        <i
          v-if="thread.is_sticky"
          v-b-tooltip.noninteractive="$i18n('search.results.thread.sticky_tooltip')"
          class="fas fa-thumbtack"
        />
        <i
          v-if="thread.is_closed"
          v-b-tooltip.noninteractive="$i18n('search.results.thread.closed_tooltip')"
          :class="{'ml-1': thread.is_sticky}"
          class="fas fa-lock"
        />
        {{ thread.name }}
      </h6>
      <br>
      <small class="separate">
        <span v-if="thread.region_id && !hideRegion">
          {{ $i18n('search.results.in') }}
          <a :href="$url('forum', thread.region_id)">
            {{ $i18n(`search.results.thread.${thread.is_inside_ambassador_forum ? 'ambassador_' : ''}forum`) }}
            {{ thread.region_name }}
          </a>
        </span>
        <span>
          {{ $i18n('search.results.thread.last_post') }}
          {{ $dateFormatter.relativeTime(new Date(thread.time)) }}
        </span>
      </small>
    </div>
  </a>
</template>
<script>
import DataUser from '@/stores/user'

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
  computed: {
    isAmbassador () {
      // eslint-disable-next-line eqeqeq
      return this.region.ambassadors.includes(ambassador => ambassador.id == DataUser.getters.getUserId())
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: '•';
}
</style>
