<!-- eslint-disable vue/singleline-html-element-content-newline -->
<template>
  <div :class="{disabledLoading: isLoading}">
    <div>
      <div class="card-header text-white bg-primary">
        {{ $t('forum.new_thread') }}
      </div>
    </div>

    <div class="card-header text-black bg-white">
      <label class="font-weight-bold" for="forum-create-thread-form-title">{{ $t('forum.thread.title') }}*</label>
      <b-form-input id="forum-create-thread-form-title" v-model="title" />
      <label class="font-weight-bold mt-3" for="thread-content">{{ $t('forum.post.body') }}*</label>
      <MarkdownInput
        input-name="thread-content"
        :draft-storage-id="'forum-create-thread-form-' + groupId"
        :rows="6"
        :value="body"
        :region-id="groupId"
        @update:value="newValue => body = newValue"
      />

      <div class="row mt-3">
        <div v-if="!isModerated" class="col">
          <input
            id="send_mail_button"
            v-model="sendMail"
            class="mr-2"
            type="checkbox"
          >
          <label for="send_mail_button">{{ $t('forum.thread.delivery_mail') }}</label>
        </div>
        <div class="col-auto">
          <button
            class="btn btn-primary btn-sm"
            :disabled="!body || !title || isLoading"
            @click="createNewThread"
          >
            {{ $t('button.create') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'
import { createThread } from '@/api/forum'
import { pulseError } from '@/script'
import i18n from '@/helper/i18n'
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'

export default {
  components: { MarkdownInput },
  props: {
    groupId: { type: Number, required: true },
    subforumId: { type: Number, required: true },
    isModerated: { type: Boolean, required: true },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  data () {
    return {
      sendMail: false,
      body: '',
      title: null,
      isLoading: false,
    }
  },
  methods: {
    async createNewThread () {
      if (this.sendMail) {
        const dialogueOptions = {
          title: this.$t('forum.mail_confirmation.title'),
          okTitle: this.$t('button.send'),
        }
        if (!await this.confirmationDialogue('forum.mail_confirmation.text', dialogueOptions)) {
          this.sendMail = false
          return
        }
      }
      this.isLoading = true
      try {
        await createThread(this.groupId, this.subforumId, this.title, this.body, this.sendMail)
        this.body = ''
        // redirect to forum overview
        window.location = this.$url('forum', this.groupId, this.subforumId)
      } catch (err) {
        this.isLoading = false
        pulseError(i18n('error_unexpected'))
      }
    },
  },
}
</script>
