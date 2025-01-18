<template>
  <div class="container">
    <div
      v-for="memberType in memberTypes"
      :id="memberType.anchor"
      :key="memberType.text"
    >
      <div v-if="memberType.value.length > 0">
        <h4>{{ memberType.title }}</h4>
        <b-alert
          v-if="memberType.text"
          show
          variant="success"
        >
          {{ memberType.text }}
        </b-alert>
        <div deck class="pb-4 team-grid">
          <div
            v-for="member in memberType.value"
            :id="member.id"
            :key="member.id"
          >
            <b-card class="team-card mb-4 clickable" @click="showTeamMemberModal(member)">
              <TeamMember :member="member" is-clamp />
            </b-card>
          </div>
        </div>
      </div>
    </div>
    <TeamMemberModal ref="refTeamMemberModal" :member="selectedMember" />
  </div>
</template>

<script setup>
import { ref, computed, defineProps, onMounted, onUnmounted } from 'vue'
import TeamMemberModal from './TeamMemberModal.vue'
import TeamMember from './TeamMember.vue'
import i18n from '@/helper/i18n'

const props = defineProps({
  teamBoardMember: { type: Array, required: true },
  teamAdministrationMember: { type: Array, required: true },
  teamAlumniMember: { type: Array, required: true },
})

const selectedMember = ref(null)
const refTeamMemberModal = ref(null)

const memberTypes = computed(() => [
  { value: props.teamBoardMember, title: i18n('team_page.board_member.title'), text: i18n('team_page.board_member.text'), anchor: 'vorstand' },
  { value: props.teamAdministrationMember, title: i18n('team_page.it_member.title'), text: i18n('team_page.it_member.text'), anchor: 'it' },
  { value: props.teamAlumniMember, title: i18n('team_page.alumni_member.title'), text: i18n('team_page.alumni_member.text'), anchor: 'alumni' },
])

const showTeamMemberModal = (member) => {
  selectedMember.value = member
  refTeamMemberModal.value?.showModal()
}

const findMemberById = (id) => {
  const numId = parseInt(id)
  for (const type of memberTypes.value) {
    const member = type.value.find(m => m.id === numId)
    if (member) return member
  }
  return null
}

const handleHash = () => {
  const hash = window.location.hash.slice(1)
  if (!hash) return

  // Check if hash is a number (member ID)
  if (/^\d+$/.test(hash)) {
    const member = findMemberById(parseInt(hash))
    if (member) {
      showTeamMemberModal(member)
    }
    return
  }

  // Check if hash matches a member type anchor
  const element = document.getElementById(hash)
  if (element) {
    setTimeout(() => {
      element.scrollIntoView({
        behavior: 'auto',
        block: 'start',
      })
    }, 100)
  }
}

onMounted(() => {
  window.addEventListener('hashchange', handleHash)
  handleHash() // Handle hash on initial load
})

onUnmounted(() => {
  window.removeEventListener('hashchange', handleHash)
})
</script>

<style scoped lang="scss">
.team-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(20rem, 1fr));
  gap: 1rem;
  .team-card {
    background-color: var(--fs-color-light);
    margin: 0;
    overflow: hidden;
    display: flex;
    border: solid var(--fs-color-dark);
    border-width: 2px;
    border-radius: 10px;
  }
}

@media (max-width: 20rem) {
  .team-grid {
    grid-template-columns: 1fr;
  }
}

.clickable {
  cursor: pointer;
}

.clickable:hover {
  background-color: var(--fs-color-light-hover);
}

.alert-warning {
  font-size: large;
}

// Add scroll margin to account for navbar
[id] {
  scroll-margin-top: 130px;
}
</style>
