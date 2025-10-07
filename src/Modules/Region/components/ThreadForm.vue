<template>
  <div class="bootstrap">
    <div class="card">
      <div class="card-header">
        {{ $i18n('button.answer') }}
      </div>
      <div class="card-body">
        <div
          v-if="!isOpen"
          class="alert alert-warning mb-2"
          role="alert"
          v-text="$i18n('forum.post.moderator_info')"
        />
        <MarkdownInput
          ref="input"
          :draft-storage-id="'forum-thread-' + threadId"
          :rows="3"
          :value="text"
          :conceal-toolbar="true"
          :region-id="regionId"
          @update:value="newValue => text = newValue"
          @submit="submit"
        />
      </div>
      <div class="card-footer below">
        <div class="row">
          <div class="col">
            <button
              :disabled="!text.trim()"
              class="btn btn-primary float-right"
              @click="submit"
            >
              {{ $i18n('button.send') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import MarkdownInput from '@/components/Markdown/MarkdownInput.vue'

export default {
  components: { MarkdownInput },
  props: {
    isOpen: { type: Boolean, default: false },
    regionId: { type: Number, default: null },
    threadId: { type: Number, default: null },
  },
  data () {
    return {
      text: '',
    }
  },
  methods: {
    submit () {
      if (!this.text.trim()) {
        return
      }
      this.$emit('submit', this.text.trim())
      this.text = ''
    },
    focus () {
      this.$refs.input.setFocus(this.text.length)
    },
    prepend (text) {
      this.text = text + this.text
    },
  },
}
</script>
