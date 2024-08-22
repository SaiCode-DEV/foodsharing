<template>
  <div class="container">
    <div v-for="memberType in memberTypes" :key="memberType.text">
      <div v-if="memberType.value.length > 0">
        <h4>{{ memberType.title }}</h4>
        <b-alert show variant="warning">
          {{ memberType.text }}
        </b-alert>
        <b-card-group deck class="pb-4">
          <div v-for="member in memberType.value" :key="member.id">
            <b-card class="cardClass mb-4 clickable" @click="showTeamMemberModal(member)">
              <TeamMember :member="member" :is-line-clamp="true" />
            </b-card>
          </div>
        </b-card-group>
      </div>
    </div>
    <TeamMemberModal ref="refTeamMemberModal" :member="selectedMember" />
  </div>
</template>

<script>
import TeamMemberModal from './TeamMemberModal.vue'
import TeamMember from './TeamMember.vue'
export default {
  name: 'TeamPage',
  components: { TeamMemberModal, TeamMember },
  props: {
    teamBoardMember: { type: Array, required: true },
    teamAdministrationMember: { type: Array, required: true },
    teamAlumniMember: { type: Array, required: true },
  },
  data () {
    return {
      memberTypes: [
        { value: this.teamBoardMember, title: this.$i18n('team_page.board_member.title'), text: this.$i18n('team_page.board_member.text') },
        { value: this.teamAdministrationMember, title: this.$i18n('team_page.it_member.title'), text: this.$i18n('team_page.it_member.text') },
        { value: this.teamAlumniMember, title: this.$i18n('team_page.alumni_member.title'), text: this.$i18n('team_page.alumni_member.text') },
      ],
      selectedMember: null,
    }
  },
  methods: {
    showTeamMemberModal (member) {
      this.selectedMember = member
      this.$nextTick(() => {
        this.$refs.refTeamMemberModal.showModal()
      })
    },
  },
}
</script>

<style scoped lang="scss">
.cardClass {
  background-color: var(--fs-color-light);
  width: 20rem;
  height: 30rem;
  max-width: 20rem;
  max-height: 30rem;
  overflow: hidden;
  display: flex;
  border: solid var(--fs-color-dark);
  border-width: 2px;
  border-radius: 10px;
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
</style>
