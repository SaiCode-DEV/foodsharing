<template>
  <b-modal
    v-if="member"
    ref="teamMemberModal"
    :title="member.name"
    ok-only
    size="lg"
    modal-class="bootstrap"
    content-class="pr-3 pt-3"
    header-class="d-flex"
  >
    <TeamMember :member="member" :show-name="false" />
  </b-modal>
</template>`

<script>

import TeamMember from './TeamMember.vue'

export default {
  name: 'TeamMemberModal',
  components: { TeamMember },
  props: {
    member: { type: Object, default: null },
  },
  methods: {
    showModal () {
      this.$refs.teamMemberModal.show()
    },
  },
}
</script>
