<template>
  <router-link
    :to="url"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        <i
          v-if="workingGroup.isAdmin"
          v-b-tooltip.noninteractive="$t('search.results.working_group.admin_tooltip')"
          class="fas fa-user-cog"
        />
        <i
          v-else-if="workingGroup.isMember"
          v-b-tooltip.noninteractive="$t('search.results.working_group.member_tooltip')"
          class="fas fa-user-check"
        />
        {{ workingGroup.name }}
      </h6>
      <br>
      <small class="separate">
        <span v-if="workingGroup.parentId">
          {{ $t('search.results.in') }}
          <router-link :to="$url('workingGroups', workingGroup.parentId)">
            {{ workingGroup.parentName }}
          </router-link>
        </span>
        <a
          v-if="workingGroup.email"
          :href="`mailto:${workingGroup.email}`"
          @click.stop
          v-text="workingGroup.email"
        />
      </small>
    </div>
    <AvatarStack :users="workingGroup.admins" />
  </router-link>
</template>
<script>
import AvatarStack from '@/components/Avatar/AvatarStack.vue'
import { useUserStore } from '@/stores/user'

export default {
  components: { AvatarStack },
  props: {
    workingGroup: {
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
    url () {
      if (this.workingGroup.isMember || this.userStore.isOrga) {
        return this.$url('forum', this.workingGroup.id)
      } else {
        return this.$url('workingGroups', this.workingGroup.parentId)
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: ' • ';
}
</style>
