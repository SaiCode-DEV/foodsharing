<template>
  <b-modal
    v-model="isVisible"
    :title="title"
    :ok-variant="okVariant"
    :ok-title="okTitle"
    :cancel-title="cancelTitle"
    :centered="centered"
    @ok="handleOk"
    @cancel="handleCancel"
  >
    {{ props.message }}
  </b-modal>
</template>

<script setup>
import i18n from '@/helper/i18n'
import { ref, defineProps, defineEmits, defineExpose } from 'vue'

const props = defineProps({
  message: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    default: '',
  },
  okVariant: {
    type: String,
    default: 'primary',
  },
  okTitle: {
    type: String,
    default: i18n('button.ok'),
  },
  cancelTitle: {
    type: String,
    default: i18n('button.cancel'),
  },
  centered: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['confirm', 'cancel'])
const isVisible = ref(false)

const show = () => {
  isVisible.value = true
}

const handleOk = () => {
  isVisible.value = false
  emit('confirm', true)
}

const handleCancel = () => {
  isVisible.value = false
  emit('cancel')
}

defineExpose({ show })
</script>
