<template>
  <Container
    :title="$i18n('events.attendees')"
  >
    <div
      v-if="!displayedGroups.length"
      class="list-group-item"
      v-text="$i18n('events.noneAccepted')"
    />
    <div
      v-for="group of displayedGroups"
      :key="group"
      class="list-group-item"
    >
      <b v-text="$i18n(`events.${group}Count`, { count: attendees[group].length })" />
      <div class="avatar-grid pt-2">
        <Avatar
          v-for="user of attendees[group].slice(0, 40)"
          :key="user.id"
          :user="user"
        />
        <span
          v-if="attendees[group].length > 40"
          class="more-people"
          v-text="$i18n('events.morePeople', { count: attendees[group].length - 40})"
        />
      </div>
    </div>
  </Container>
</template>
<script>
import Container from '@/components/Container/Container.vue'
import Avatar from '@/components/Avatar/Avatar.vue'

export default {
  components: { Container, Avatar },
  props: {
    attendees: { type: Object, required: true },
  },
  computed: {
    displayedGroups () {
      return ['accepted', 'maybe'].filter(group => this.attendees[group].length > 0)
    },
  },
}
</script>
<style scoped lang="scss">
.avatar-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, 35px);
  gap: 3px;
  justify-content: space-evenly;
}

.more-people {
  grid-column: -3 / -1;
  text-align: right;
}
</style>
