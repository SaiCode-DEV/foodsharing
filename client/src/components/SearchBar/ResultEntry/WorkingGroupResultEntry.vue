<template>
  <a
    :href="url"
    class="d-flex dropdown-item search-result"
    tabindex="1"
  >
    <div class="text-truncate flex-grow-1">
      <h6 class="m-0 text-truncate d-inline">
        <i
          v-if="workingGroup.is_admin"
          v-b-tooltip.noninteractive="$i18n('search.results.working_group.admin_tooltip')"
          class="fas fa-user-cog"
        />
        <i
          v-else-if="workingGroup.is_member"
          v-b-tooltip.noninteractive="$i18n('search.results.working_group.member_tooltip')"
          class="fas fa-user-check"
        />
        {{ workingGroup.name }}
      </h6>
      <br>
      <small class="separate">
        <span v-if="workingGroup.parent_id">
          {{ $i18n('search.results.in') }}
          <a :href="$url('workingGroups', workingGroup.parent_id)">
            {{ workingGroup.parent_name }}
          </a>
        </span>
        <a
          v-if="workingGroup.email"
          :href="$url('mailto_mail_foodsharing_network', workingGroup.email)"
        >
          {{ workingGroup.email }}@foodsharing.network
        </a>
      </small>
    </div>
    <AvatarStack
      :registered-users="workingGroup.admins"
    />
  </a>
</template>
<script>
import AvatarStack from '@/components/AvatarStack.vue'
import DataUser from '@/stores/user'

export default {
  components: { AvatarStack },
  props: {
    workingGroup: {
      type: Object,
      required: true,
    },
  },
  computed: {
    url () {
      if (this.workingGroup.is_member || DataUser.getters.isOrga()) {
        return this.$url('forum', this.workingGroup.id)
      } else {
        return this.$url('workingGroups', this.workingGroup.parent_id)
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.separate>*:not(:last-child)::after {
  content: '•';
}
</style>
