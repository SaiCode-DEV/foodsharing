<template>
  <b-modal
    id="quizSessionHistoryModal"
    :title="$t('profile.nav.quizSessionHistory')"
    :ok-title="$t('globals.close')"
    size="lg"
    ok-only
    @show="fetchQuizSessions"
  >
    <div
      v-if="isLoading"
      class="loader-container mx-auto"
    >
      <i class="fas fa-spinner fa-spin" />
    </div>
    <div v-else-if="entries.length">
      <div
        v-for="{ quiz, sessions } in entries"
        :key="quiz.id"
      >
        <h5>{{ quiz.name }}</h5>
        <ul>
          <li
            v-for="session in sessions"
            :key="session.id"
          >
            {{ $t(`quiz.sessionStatus.${session.status}`, session) }}
            <i
              class="fas fa-trash-alt float-right ml-2 delete-icon"
              @click="deleteSession(session.id)"
            />
            <TimeDisplay
              :time="session.endTime"
              class="float-right"
            />
          </li>
        </ul>
      </div>
    </div>
    <p v-else>
      {{ $t('profile.history.noData') }}
    </p>
  </b-modal>
</template>

<script>
import { pulseError } from '@/script'
import { getQuizSessionHistory, deleteQuizSession } from '@/api/quiz'
import TimeDisplay from '@/components/TimeDisplay.vue'

export default {
  components: { TimeDisplay },
  props: {
    foodsaverId: { type: Number, required: true },
  },
  data () {
    return {
      isLoading: false,
      entries: [],
    }
  },
  methods: {
    async fetchQuizSessions () {
      this.isLoading = true
      try {
        this.entries = await getQuizSessionHistory(this.foodsaverId)
      } catch (e) {
        pulseError(this.$t('error_unexpected'))
      }
      this.isLoading = false
    },
    async deleteSession (sessionId) {
      if (await this.$confirmationDialogue('quiz.confirmDelete.session')) {
        await deleteQuizSession(sessionId)
        this.fetchQuizSessions()
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.delete-icon {
  cursor: pointer;
  &:hover {
    color: var(--fs-color-danger-500);
  }
}
</style>
