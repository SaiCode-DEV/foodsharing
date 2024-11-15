<template>
  <div>
    <a
      ref="comment-collapse-toggle"
      v-b-toggle
      href="#comment-collapse"
      @click.prevent
    >
      {{ $i18n('quiz.comment.toggle') }}
    </a>
    <b-collapse
      id="comment-collapse"
      ref="comment-collapse"
      v-model="commentSectionVisible"
    >
      <b-form-textarea
        v-model="comment"
        :label="$i18n('quiz.comment.label')"
        :placeholder="$i18n('quiz.comment.placeholder')"
        rows="3"
      />
      <div class="send-button-wrapper">
        <b-button
          variant="primary"
          :disabled="!comment"
          @click="sendCommentHandler"
        >
          {{ $i18n('quiz.comment.send') }}
        </b-button>
      </div>
    </b-collapse>
  </div>
</template>

<script>

import { addPost } from '@/api/wall'
import { pulseError, pulseInfo } from '@/script'
import ConfirmationDialogue from '@/mixins/ConfirmationDialogue'

export default {
  mixins: [ConfirmationDialogue],
  props: {
    questionId: { type: Number, required: true },
  },
  data: () => ({
    comment: '',
    commentSectionVisible: false,
  }),
  methods: {
    async sendCommentHandler () {
      if (!await this.confirmationDialogue('quiz.confirmComment', {
        okTitle: this.$i18n('yes'),
        okVariant: undefined,
      })) return
      try {
        await addPost('question', this.questionId, this.comment)
        this.commentSectionVisible = false
        this.comment = ''

        pulseInfo(this.$i18n('quiz.comment.sent'))
      } catch (error) {
        pulseError(this.$i18n('error_unexpected'))
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
