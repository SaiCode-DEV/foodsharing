<template>
  <b-form-file
    id="files"
    ref="fileInput"
    :value="props.value"
    :multiple="props.maxFiles > 1"
    :disabled="props.disabled"
    :placeholder="$i18n('support_page.attachment.placeholder')"
    :drop-placeholder="$i18n('support_page.attachment.drop_placeholder')"
    :browse-text="$i18n('support_page.attachment.browse')"
    :accept="props.accept"
    class="custom-files"
    @input="updateAttachmentFiles"
  >
    <template
      slot="file-name"
      slot-scope="{ names }"
    >
      <b-badge
        v-for="(name, index) in names"
        :key="index"
        variant="dark"
        class="attachment"
      >
        <span class="attachment-text">
          {{ name }}
        </span>
        <b-button
          size="sm"
          variant="danger"
          class="remove-attachment"
          @click="removeFile(index)"
        >
          <i class="fas fa-times" />
        </b-button>
      </b-badge>
    </template>
  </b-form-file>
</template>
<script setup>

import { ref, defineProps, defineEmits, watch } from 'vue'
import { MAX_UPLOAD_FILE_SIZE, ACCEPTED_FILE_TYPES } from '@/consts'
import { pulseError } from '@/script'
import i18n from '@/helper/i18n'

const props = defineProps({
  value: {
    type: Array,
    default: () => [],
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  accept: {
    type: String,
    default: ACCEPTED_FILE_TYPES,
  },
  maxFiles: {
    type: Number,
    default: 1,
  },
  maxFileSize: {
    type: Number,
    default: MAX_UPLOAD_FILE_SIZE,
  },
})
const emit = defineEmits(['update:value'])

// b-form-file ignores changes to the value unless 0 files. This is a workaround to update the value
const fileInput = ref(null)
watch(() => props.value, (value) => {
  fileInput.value.files = value
})

function updateAttachmentFiles (event) {
  const newFiles = Array.from(event).filter((file) => file.size <= props.maxFileSize)

  if (Array.from(event).length > newFiles.length) {
    pulseError(i18n('mailbox.attachment.too_large_to_send'))
  }

  // Create a Set of existing file names
  const existingNames = new Set(props.value.map(file => file.name))
  // Filter out new files that have duplicate names
  const uniqueNewFiles = newFiles.filter(file => !existingNames.has(file.name))

  let combinedFiles = [...props.value, ...uniqueNewFiles]
  if (combinedFiles.length > props.maxFiles) {
    combinedFiles = combinedFiles.slice(combinedFiles.length - props.maxFiles)
    pulseError(i18n('mailbox.attachment.too_many'))
  }
  emit('update:value', combinedFiles)
}

function removeFile (index) {
  emit('update:value', props.value.filter((_, i) => i !== index))
}
</script>
<style lang="scss">
.custom-files{
  height: unset;
  input {
    height: unset;
  }
  label.custom-file-label{
    height: unset;
    width: 100%;
    position: relative;
    background-color: var(--fs-color-background);
  &::after {
    height: unset;
    background-color: var(--fs-color-elevated);
    color: var(--fs-color-text);
  }
}
}
.form-file-text {
  display: flex !important;
  width: calc(100% - 8em); // Adjust width to ensure remove button is visible
  flex-wrap: wrap;
  gap: 0.2em;
}
.attachment {
  display: flex;
  flex-direction: row;
  align-items: center;
  max-width: 100%;
  .attachment-text {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex-grow: 1;
    flex-shrink: 1;
    flex-basis: 0;
  }
  .remove-attachment {
    z-index: 10;
    position: relative;
    pointer-events: all;
    height: 1.3em;
    padding: 0 0.2em;
    border-radius: 50%;
    margin-left: 0.5em; // Add margin to separate from text
    flex-shrink: 0;
    i {
      line-height: 1;
      display: flex;
      align-items: center;
    }
  }
}
.custom-file-input[disabled] ~ .custom-file-label, .custom-file-input:disabled ~ .custom-file-label {
  background-color: var(--fs-color-primary-200);
  cursor: not-allowed;
}
</style>
