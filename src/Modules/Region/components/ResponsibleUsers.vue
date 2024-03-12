<template>
  <div>
    <Container
      :title="title"
      class="bg-white"
      :toggle-visiblity="showToggleVisiblity"
    >
      <avatar-list
        :profiles="responsibleUsers"
      />
    </Container>
  </div>
</template>

<script>
import Container from '@/components/Container/Container.vue'
import AvatarList from '@/components/Avatar/AvatarList.vue'

export default {
  components: { AvatarList, Container },
  props: {
    title: { type: String, required: true },
    responsibleUsers: { type: Array, required: true },
  },
  computed: {
    showToggleVisiblity () {
      return this.responsibleUsers.length >= 7
    },
  },
}
</script>
