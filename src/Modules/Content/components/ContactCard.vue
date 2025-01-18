<template>
  <b-col lg="4" class="mb-4">
    <b-card class="h-100 contact-card">
      <div class="text-center mb-4">
        <span class="contact-icon">
          <i :class="['fas', 'fa-2x', icon]" />
        </span>
      </div>
      <h2 class="h4 text-center mb-3">
        {{ title }}
      </h2>
      <div class="text-center">
        <b-button
          variant="primary"
          size="lg"
          class="mt-2 mb-2"
          :href="buttonHref"
          :target="buttonTarget"
          @click="handleClick"
        >
          {{ buttonText }}
        </b-button>
      </div>
      <slot />
    </b-card>
  </b-col>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue'

const props = defineProps({
  icon: { type: String, required: true },
  title: { type: String, required: true },
  buttonText: { type: String, required: true },
  buttonHref: { type: String, default: null },
  buttonTarget: { type: String, default: null },
})

const emit = defineEmits(['click'])

const handleClick = (event) => {
  if (!props.buttonHref) {
    event.preventDefault()
  }
  emit('click', event)
}
</script>
