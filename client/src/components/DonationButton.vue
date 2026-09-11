<template>
  <b-button
    pill
    :variant="customDonationStyle ? undefined : variant"
    :to="isExternalLink ? undefined : linkTarget"
    :href="isExternalLink ? linkTarget : undefined"
    :class="{'donationpage-btn': customDonationStyle}"
    @click="customDonationStyle || !buttonLink ? emit('click', $event) : undefined"
    @mouseover="switchImage(true)"
    @mouseout="switchImage(false)"
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
import { computed, defineProps, defineEmits, ref } from 'vue'
import i18n from '@/helper/i18n'
import { isExternalUrl } from '@/helper/urls'
import { useStrawberryHover } from '@/composables/useStrawberryHover'

const emit = defineEmits(['click'])
const props = defineProps({
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

// with the donation page style the button only reports clicks, it never links anywhere
const linkTarget = computed(() => (props.customDonationStyle ? null : props.buttonLink))
const isExternalLink = computed(() => isExternalUrl(linkTarget.value))
const { strawberryRef: strawberry, switchImage } = useStrawberryHover()
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

.btn.donationpage-btn {
  border: 2px solid v-bind(borderColor);
  background: var(--fs-color-white);
  color: var(--fs-color-warning-500);
  font-weight: bold;
  box-shadow: none;
  font-size: 1.5rem;
}

.btn.donationpage-btn:hover, .btn.donationpage-btn:focus {
  background: var(--fs-color-warning-100);
  color: var(--fs-color-warning-900);
  border-color: v-bind(borderColor);
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
