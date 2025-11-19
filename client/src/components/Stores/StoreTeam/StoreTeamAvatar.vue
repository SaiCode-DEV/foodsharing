<template>
  <Avatar
    :user="user"
    tooltip=""
    :size="50"
    :badge-variant="user.mayManage ? 'secondary' : 'primary'"
    :class="{'jumper': user.isJumper}"
    :transparent="user.isJumper || !user.isVerified"
  >
    <template #badge>
      <span ref="badgeContent">
        <i v-if="user.isJumper" class="fas fa-running" />
        <i v-else-if="!user.isVerified" class="fas fa-user-slash" />
        <span v-else v-text="user.fetchCount" />
      </span>
      <b-tooltip :target="() => $refs?.badgeContent?.parentElement" noninteractive>
        <div v-text="$t('store.fetchCount', {'count': user.fetchCount})" />
        <div v-if="user.mayManage" v-text="$t('store.mayManage')" />
        <div v-if="user.isJumper" v-text="$t('store.isJumper')" />
        <div v-if="!user.isVerified" v-text="$t('store.isNotVerified')" />
      </b-tooltip>
    </template>
  </Avatar>
</template>
<script>
import Avatar from '@/components/Avatar/Avatar.vue'

export default {
  components: { Avatar },
  props: {
    user: { type: Object, required: true },
  },
}
</script>
<style scoped>
.jumper ::v-deep img {
  opacity: 50%;
}
</style>
