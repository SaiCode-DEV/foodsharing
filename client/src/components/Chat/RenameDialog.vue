<template>
  <b-modal
    v-model="visible"
    :title="$t('chat.rename')"
    size="md"
    :cancel-title="$t('button.cancel')"
    :ok-title="$t('button.save')"
    @ok="save"
    @hidden="reset"
  >
    <b-form-group
      :label="$t('chat.rename')"
      label-for="rename-input"
    >
      <small class="text-muted">
        {{ $t('chat.rename_empty_hint') }}
      </small>
      <b-form-input
        id="rename-input"
        v-model="newTitle"
        :placeholder="$t('chat.rename_placeholder')"
        maxlength="50"
        autofocus
        @keyup.enter="save"
      />
    </b-form-group>
  </b-modal>
</template>

<script setup>
import { ref } from 'vue'
import conversationStore from '@/stores/conversations'
import { pulseError } from '@/script'
import i18n from '@/helper/i18n'

const visible = ref(false)
const newTitle = ref('')
const conversation = ref(null)

const emit = defineEmits(['save-rename', 'cancel-rename'])

function open (conversationId) {
  conversation.value = conversationStore.conversations[conversationId]
  if (!conversation.value) {
    console.error('Conversation not found for renaming:', conversationId)
    return
  }
  newTitle.value = conversation.value.title || ''
  visible.value = true
}

async function save () {
  try {
    const trimmedTitle = newTitle.value.trim()

    await conversationStore.renameConversation(conversation.value.id, trimmedTitle)

    visible.value = false
    emit('save-rename', {
      conversationId: conversation.value.id,
      newTitle: trimmedTitle,
    })
  } catch (e) {
    console.error('Failed to rename conversation:', e)
    pulseError(i18n('chat.error.renaming_conversation'))
  }
}

function reset () {
  newTitle.value = ''

  emit('cancel-rename')
}

defineExpose({
  open,
})
</script>
