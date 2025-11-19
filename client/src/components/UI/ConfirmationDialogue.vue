<template>
  <b-modal
    id="confirmation-dialogue"
    v-model="isVisible"
    :title="currentOptions.title"
    :ok-variant="currentOptions.okVariant"
    :ok-title="currentOptions.okTitle"
    :cancel-title="currentOptions.cancelTitle"
    :centered="currentOptions.centered"
    @ok="handleOk"
    @cancel="handleCancel"
    @show="startCountdown"
  >
    {{ currentOptions.message }}
    <template #modal-footer="{ cancel }">
      <b-button class="cancel-button" @click="cancel()">
        {{ currentOptions.cancelTitle }}
      </b-button>
      <div>
        <b-button
          :disabled="countdown > 0"
          :variant="currentOptions.okVariant"
          class="confirm-button"
          @click="handleOk"
        >
          {{ currentOptions.okTitle }}
        </b-button>
        <div v-if="countdown > 0" class="confirm-countdown">
          {{ $t('button.countdown_clickable', { countdown }) }}
        </div>
      </div>
    </template>
  </b-modal>
</template>

<script setup>
import { ref, onMounted, onUnmounted, defineExpose } from 'vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'

const { emitter } = useConfirmationDialogue()

const isVisible = ref(false)
const countdown = ref(0)
let intervalId = null

const currentOptions = ref({
  message: '',
  title: '',
  okVariant: 'primary',
  okTitle: '',
  cancelTitle: '',
  centered: true,
  onConfirm: null,
  onCancel: null,
  countdown: 0,
})

const startCountdown = () => {
  if (intervalId) {
    clearInterval(intervalId)
  }
  countdown.value = currentOptions.value.countdown || 0
  if (countdown.value > 0) {
    intervalId = setInterval(() => {
      if (countdown.value <= 0) {
        clearInterval(intervalId)
      }
      countdown.value--
    }, 1000)
  }
}

const handleOk = () => {
  if (countdown.value > 0) return
  isVisible.value = false
  if (intervalId) {
    clearInterval(intervalId)
  }
  currentOptions.value.onConfirm?.()
}

const handleCancel = () => {
  isVisible.value = false
  if (intervalId) {
    clearInterval(intervalId)
  }
  currentOptions.value.onCancel?.()
}

const show = (options) => {
  currentOptions.value = options
  isVisible.value = true
}

onMounted(() => {
  emitter.on('show-confirmation', show)
})

onUnmounted(() => {
  emitter.off('show-confirmation', show)
})

defineExpose({
  show,
})
</script>

<style scoped>
.confirm-countdown {
  display: block;
  font-size: 80%;
  color: var(--fs-color-danger-500);
  height: 0;
  text-align: center;
}
</style>
