<template>
  <div class="bootstrap">
    <ul
      class="container linklist"
      :style="scrollStyle"
    >
      <li
        v-for="profile in profiles"
        :key="profile.id"
      >
        <a
          :href="$url('profile', profile.id)"
          class="row justify-content-start"
        >
          <Avatar
            :user="profile"
            :size="35"
            class="mr-2"
          />
          <span class="d-inline avatar-title">{{ profile.name }}</span>
        </a>
      </li>
    </ul>
  </div>
</template>

<script>

import Avatar from './Avatar'

export default {
  components: { Avatar },
  props: {
    profiles: {
      type: Array,
      default: () => {
        return []
      },
    },
    maxVisibleAvatars: { type: Number, default: -1 },
  },
  computed: {
    scrollStyle () {
      return this.maxVisibleAvatars > 0
        ? `overflow-y: auto; max-height: ${47 * this.maxVisibleAvatars}px`
        : 'overflow-y: visible'
    },
  },
}
</script>

<style scoped>
a.row {
  -moz-border-radius: 4px;
  -webkit-border-radius: 4px;
  border-radius: 4px;
}

.avatar-title {
  text-decoration: none !important;
  color: var(--fs-color-primary-500);
  font-weight: bold;
}
</style>
