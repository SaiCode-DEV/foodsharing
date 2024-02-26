<!-- eslint-disable vue/max-attributes-per-line -->
<template>
  <!-- Flex container reverses elements for correct draw order without z-index -->
  <div class="avatar-stack">
    <div v-if="freeSlots" class="free-slots">
      <span v-text="$i18n(`pickup.overview.freeSlots`, {slots: freeSlots})" />
    </div>

    <div v-if="hiddenUsers.length" ref="hidden" class="hidden-users">
      <span v-text="`+${hiddenUsers.length}`" />
      <b-tooltip v-if="hiddenUsers.length && showOverflowTooltip" :target="$refs.hidden" triggers="hover">
        <span v-for="(user, index) in hiddenUsers" :key="user.id">
          <span v-if="index != 0">, </span>
          <a :href="$url('profile', user.id)" class="tooltip-link">{{ user.name }}</a>
        </span>
      </b-tooltip>
    </div>

    <Avatar
      v-for="user in shownUsers"
      :key="user.id"
      :user="user"
      :size="size"
      shape="round"
    />
  </div>
</template>

<script>
import Avatar from '@/components/Avatar/Avatar.vue'

export default {
  components: { Avatar },
  props: {
    users: { type: Array, default: () => [] },
    totalSlots: { type: Number, default: 0 },
    maxWidthInPx: { type: Number, default: 200 },
    size: { type: Number, default: 35 },
    overlap: { type: Number, default: 15 },
    borderColor: { type: String, default: 'white' },
    showOverflowTooltip: { type: Boolean, default: true },
  },
  computed: {
    shownUsersNum () {
      // The free slots add-on needs 43px
      const width = this.maxWidthInPx - (this.freeSlots ? 43 : 0)
      const maxCircles = Math.max(1, Math.floor((width - this.overlap) / (this.size - this.overlap)))
      if (this.users.length <= maxCircles) {
        return this.users.length
      } else {
        return Math.max(maxCircles - 1, 1)
      }
    },
    shownUsers () {
      return this.users.slice(0, this.shownUsersNum).reverse()
    },
    hiddenUsers () {
      return this.users.slice(this.shownUsersNum)
    },
    freeSlots () {
      return Math.max(0, this.totalSlots - this.users.length)
    },
  },
}
</script>
<style lang="scss" scoped>
::v-deep .b-avatar-img img {
  border: 1px solid v-bind("borderColor");
  border-width: 1px 2px 1px 0;
}

.avatar-stack {
  --overlap: v-bind("overlap + 'px'");
  display: inline-flex;
  flex-direction: row-reverse;

  & *:not(:last-child) {
    margin-left: calc(-1 * var(--overlap));
  }
}

.hidden-users, .free-slots {
    --component-height: v-bind("size + 'px'");
    align-items: center;
    border-radius: var(--component-height);
    border: 1px solid;
    display: flex;
    font-weight: bold;
    height: var(--component-height);
    padding: 0 6px 0 calc(var(--overlap) + 1px);
    min-width: var(--component-height);
    white-space: nowrap;

    &:last-child{
      padding-left: 6px;
    }

    span{
      width: 100%;
      text-align: center;
    }
  }

.free-slots {
  background-color: var(--fs-color-secondary-500);
  border-color: var(--fs-color-secondary-600);
  color: var(--fs-color-light);
}

.hidden-users {
  border-color: var(--fs-color-primary-100);
  border-color: var(--fs-color-primary-300);
  color: var(--fs-color-primary-alpha-70);
  background-color: var(--fs-color-light);
}

.tooltip-link {
  color: var(--fs-color-secondary-500) !important;
}
</style>
