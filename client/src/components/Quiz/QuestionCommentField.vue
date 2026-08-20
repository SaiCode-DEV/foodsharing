<template>
  <div>
    <a
      ref="comment-collapse-toggle"
      v-b-toggle
      href="#comment-collapse"
      @click.prevent
    >
      {{ $t('quiz.comment.toggle') }}
    </a>
    <b-collapse
      id="comment-collapse"
      ref="comment-collapse"
      v-model="commentSectionVisible"
    >
      <b-form-textarea
        v-model="comment"
        :label="$t('quiz.comment.label')"
        :placeholder="$t('quiz.comment.placeholder')"
        rows="3"
      />
      <div class="send-button-wrapper">
        <b-button
          variant="primary"
          :disabled="!comment"
          @click="sendCommentHandler"
        >
          {{ $t('quiz.comment.send') }}
        </b-button>
      </div>
    </b-collapse>
  </div>
</template>

<script>
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { addPost } from '@/api/wall'
import { pulseError, pulseInfo } from '@/script'

export default {
  props: {
    questionId: { type: Number, required: true },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  data: () => ({
    comment: '',
    commentSectionVisible: false,
  }),
  methods: {
    async sendCommentHandler () {
      if (!await this.confirmationDialogue('quiz.confirmComment', {
        okTitle: this.$t('terminology.yes'),
        okVariant: undefined,
      })) return
      try {
        await addPost('question', this.questionId, this.comment)
        this.commentSectionVisible = false
        this.comment = ''

        pulseInfo(this.$t('quiz.comment.sent'))
      } catch (error) {
        pulseError(this.$t('error_unexpected'))
      }
    },
  },
}
</script>

<style scoped>
.send-button-wrapper {
  text-align: right;
  padding-top: .5em;
}
</style>
