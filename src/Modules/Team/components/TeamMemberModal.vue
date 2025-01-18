<template>
  <b-modal
    ref="teamMemberModal"
    :title="member?.name"
    ok-only
    size="lg"
    modal-class="bootstrap"
    content-class="pr-3 pt-3"
    header-class="d-flex"
  >
    <TeamMember
      v-if="member"
      :member="member"
      :show-name="false"
    />
  </b-modal>
</template>

<script setup>
import { ref, defineProps, defineExpose } from 'vue'
import TeamMember from './TeamMember.vue'

defineProps({
  member: { type: Object, default: null },
})

const teamMemberModal = ref(null)

const showModal = () => {
  teamMemberModal.value?.show()
}

defineExpose({
  showModal,
})
</script>
