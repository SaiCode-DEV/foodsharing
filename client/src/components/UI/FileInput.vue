<template>
  <b-form-file
    ref="fileInput"
    :value="displayValue"
    :multiple="props.maxFiles > 1"
    :disabled="props.disabled"
    :placeholder="$t('support_page.attachment.placeholder')"
    :drop-placeholder="$t('support_page.attachment.drop_placeholder')"
    :browse-text="$t('support_page.attachment.browse')"
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
import { ref, computed, defineProps, defineEmits, watch, onUnmounted } from 'vue'
import { MAX_UPLOAD_FILE_SIZE, ACCEPTED_FILE_TYPES } from '@/consts'
import { pulseError } from '@/script'
import i18n from '@/helper/i18n'

const props = defineProps({
  value: {
    type: [Array, File],
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

const fileInput = ref(null)
const internalFiles = ref([])

// Computed property for display value
const displayValue = computed(() => {
  if (internalFiles.value.length === 0) return null
  return props.maxFiles === 1 ? internalFiles.value[0] : internalFiles.value
})

// Helper function to normalize file value
const normalizeFileValue = (value) => {
  if (!value) return []
  if (value instanceof File) return [value]
  return Array.isArray(value) ? value : []
}

// Watch prop changes and update internal state
watch(() => props.value, (newValue) => {
  internalFiles.value = normalizeFileValue(newValue)
  if (fileInput?.value) {
    fileInput.value.files = internalFiles.value
  }
}, { immediate: true })

// Clear files on unmount to prevent memory leaks
onUnmounted(() => {
  internalFiles.value = []
  if (fileInput.value) {
    fileInput.value.files = null
  }
})

function updateAttachmentFiles (input) {
  const inputFiles = input?.target?.files
    ? Array.from(input.target.files)
    : Array.isArray(input)
      ? input
      : input instanceof File ? [input] : []

  const newFiles = inputFiles.filter((file) => file.size <= props.maxFileSize)

  if (inputFiles.length > newFiles.length) {
    pulseError(i18n('mailbox.attachment.too_large_to_send'))
  }

  // For single file mode
  if (props.maxFiles === 1) {
    internalFiles.value = newFiles.slice(0, 1)
    emit('update:value', internalFiles.value[0] || null)
    return
  }

  // For multiple files mode
  let combinedFiles = [...internalFiles.value]
  newFiles.forEach(file => {
    if (!combinedFiles.some(existing => existing.name === file.name)) {
      combinedFiles.push(file)
    }
  })

  if (combinedFiles.length > props.maxFiles) {
    combinedFiles = combinedFiles.slice(-props.maxFiles)
    pulseError(i18n('mailbox.attachment.too_many'))
  }

  internalFiles.value = combinedFiles
  emit('update:value', combinedFiles)
}

function removeFile (index) {
  internalFiles.value = internalFiles.value.filter((_, i) => i !== index)
  if (fileInput.value) {
    fileInput.value.files = null // Reset the native file input
  }
  emit('update:value', props.maxFiles === 1 ? internalFiles.value[0] || null : internalFiles.value)
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
