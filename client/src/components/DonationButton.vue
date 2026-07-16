<template>
  <b-button
    pill
    :variant="!customDonationStyle && buttonLink ? variant : (customDonationStyle ? undefined : variant)"
    :href="!customDonationStyle && buttonLink ? buttonLink : undefined"
    :class="{'donationpage-btn': customDonationStyle}"
    :style="customDonationStyle ? {'--donation-border-color': borderColor} : undefined"
    @click="customDonationStyle || !buttonLink ? emit('click', $event) : undefined"
    @mouseover="switchImage('-highlight')"
    @mouseout="switchImage('')"
  >
    <slot>
      <span class="d-inline-flex align-items-center gap-2 text-nowrap">
        <span class="mb-0 donation-button-text pr-1">{{ text }}</span>
        <span :class="!customDonationStyle ? 'donation-icon' : ''">
          <img
            ref="strawberry"
            src="/img/icon/donation-strawberry.svg"
            :width="width"
            :height="height"
          >
        </span>
      </span>
    </slot>
  </b-button>
</template>

<script setup>
import { defineProps, defineEmits, ref } from 'vue'
import i18n from '@/helper/i18n'

const emit = defineEmits(['click'])
defineProps({
  text: {
    type: String,
    default: i18n('menu.entry.donation_button'),
  },
  variant: {
    type: String,
    default: 'primary',
  },
  customDonationStyle: {
    type: Boolean,
    default: false,
  },
  customDonationTextSize: {
    type: String,
    default: '0.8rem',
  },
  buttonLink: {
    type: String,
    default: null,
  },
  borderColor: {
    type: String,
    default: 'var(--fs-color-secondary-500)',
  },
  height: {
    type: Number,
    default: 25,
  },
  width: {
    type: Number,
    default: 30,
  },
})

const strawberry = ref(null)

function switchImage (type) {
  strawberry.value.src = `/img/icon/donation-strawberry${type}.svg`
}
</script>

<style scoped>
.btn {
  padding: 0.25em 0.25em 0.25em 0.75em;
  margin: 2px 0.2em;
  border: 0;
}

.donation-button-text {
  font-weight: 600;
  font-size: v-bind(customDonationTextSize);
}
.donationpage-btn {
  border: 2px solid var(--donation-border-color);
  background: var(--fs-color-white) !important;
  color: var(--fs-color-warning-500);
  font-weight: bold;
  box-shadow: none;
  font-size: 1.5rem;
}
.donationpage-btn:hover, .donationpage-btn:focus {
  background: var(--fs-color-warning-100) !important;
  color: var(--fs-color-warning-900) !important;
  border-color: var(--donation-border-color);
}

.donation-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.2rem;
  height: 2.2rem;
  background: var(--fs-color-danger-200);
  border-radius: 50%;
}
</style>
