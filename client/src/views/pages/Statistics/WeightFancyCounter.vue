<template>
  <div id="saved-container">
    <b-alert
      id="saved"
      variant="secondary"
      show
    >
      <h2 id="saved-label">
        <span>{{ i18n('statistics.food_saved') }}</span>
      </h2>
      <h1 id="saved-text" ref="savedText">
        <template v-for="(char, index) in formattedText">
          <span
            v-if="!isNumber(char)"
            :key="'char-' + index"
            class="character"
          >{{ char }}</span>
          <span
            v-else
            :key="'digit-' + index"
            class="digit"
          >
            <span
              ref="tracks"
              :key="'track-' + index"
              class="digit-track"
            >
              {{ getDigitTrack(index) }}
            </span>
          </span>
        </template>
      </h1>
      <div class="actions">
        <b-button
          id="redo-button"
          type="button"
          @click="handleRedo"
        >
          <i class="fas fa-repeat" />
        </b-button>
      </div>
    </b-alert>
  </div>
</template>

<script setup>
import { ref, onMounted, defineProps, computed, watch } from 'vue'
import i18n, { locale } from '@/helper/i18n'

const MINIMUM_ADDITIONAL_ITERATION_COUNT = 2
const DIGIT_HEIGHT = 4
const DIGITS = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]

const props = defineProps({
  number: {
    type: Number,
    default: 0,
  },
})

const ParsedNumber = new Intl.NumberFormat(locale, {
  style: 'unit',
  unit: 'kilogram',
  maximumFractionDigits: 0,
})

// Helper functions
const getSavedDigitByIndex = index => parseInt(props.number.toString()[index])
const determineIterations = index => index + MINIMUM_ADDITIONAL_ITERATION_COUNT

const tracks = ref([])
const savedText = ref(null)

const formattedText = computed(() => ParsedNumber.format(props.number))
const isNumber = (char) => /[0-9]/.test(char)

const getDigitTrack = (index) => {
  const iterations = determineIterations(index)
  let digits = []
  for (let i = 0; i < iterations; i++) {
    digits = [...digits, ...DIGITS]
  }
  return digits.join('\n')
}

const getTrackHeight = (index) => {
  const iterations = determineIterations(index)
  return DIGITS.length * iterations * DIGIT_HEIGHT
}

const getTrackPosition = (index) => {
  const digit = getSavedDigitByIndex(index)
  const iterations = determineIterations(index)
  const activeDigit = ((iterations - 1) * 10) + digit
  return -activeDigit * DIGIT_HEIGHT
}

const updateTrackStyles = (track, height, translate) => {
  track.style.setProperty('--track-height', `${height}rem`)
  track.style.setProperty('--track-translate', `${translate}rem`)
}

const animate = () => {
  if (!tracks.value || !tracks.value.length) {
    return
  }
  tracks.value.forEach((track, index) => {
    if (track && isNumber(getSavedDigitByIndex(index))) {
      updateTrackStyles(
        track,
        getTrackHeight(index),
        getTrackPosition(index),
      )
    }
  })
}

const resetAnimation = () => {
  if (!tracks.value || !tracks.value.length) {
    return
  }
  tracks.value.forEach((track, index) => {
    if (track) {
      track.style.transition = 'none'
      updateTrackStyles(track, getTrackHeight(index), 0)

      track.offsetHeight // eslint-disable-line @typescript-eslint/no-unused-expressions

      requestAnimationFrame(() => {
        track.style.transition = 'transform 5000ms cubic-bezier(.1,.67,0,1)'
      })
    }
  })
}

const handleRedo = () => {
  resetAnimation()
  setTimeout(animate, 100)
}

onMounted(() => {
  setTimeout(() => {
    animate()
  })
})

watch(() => props.number, () => {
  resetAnimation()
  setTimeout(animate, 100)
})
</script>

<style lang="scss">
#saved-container {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
}

#saved {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  position: relative;
  z-index: 2;
  padding: 4rem 4rem;
  background: radial-gradient(
    ellipse farthest-corner at bottom right,
    var(--fs-color-primary-100) 30%,
    var(--fs-color-secondary-300) 50%,
    var(--fs-color-primary-100) 70%
  );
  &::after {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-image: radial-gradient(
    rgba(140, 140, 140, 0.243) 1px,
    transparent 1px
  );
  background-position: center;
  background-size: 1.1rem 1.1rem;
    z-index: -1;
  }

  &-label, &-text {
    text-align: center;
  }

  &-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
    font-size: 2rem;
    opacity: 0.75;

    > span {
      display: inline-flex;
    }

    > .asterisk {
      padding-top: 0.25em;
      line-height: 0.5em;
    }
  }

  &-text {
    display: flex;
    gap: 0.1rem;
    height: 4rem;
    line-height: 4rem;
    font-size: 4rem;

    > .digit {
      width: 4rem;
      position: relative;
      overflow: hidden;
      border-bottom: 0.1rem solid;

      > .digit-track {
        position: absolute;
        left: 0%;
        top: 0%;
        translate: 0% 0%;
        height: var(--track-height, 0);
        transform: translateY(var(--track-translate, 0));
        transition: transform 5000ms cubic-bezier(.1,.67,0,1);
        @media (prefers-reduced-motion: reduce) {
          transition: none !important;
        }
      }
    }
  }
}

.saved-filter {
  height: 100%;
  width: 100%;
  position: absolute;
  left: 0%;
  top: 0%;
  pointer-events: none;
}

.actions {
  position: absolute;
  bottom: 0;
  right: 0;
  button {
    background-color: rgb(0 0 0 / 50%) !important;
  }
}

@media (max-width: 768px) {
  #saved-text {
    scale: 0.75;
  }
}

@media (max-width: 530px) {
  #saved-text {
    scale: 0.5;
  }
  #saved {
    padding: 1rem 1rem;
  }
}
</style>
