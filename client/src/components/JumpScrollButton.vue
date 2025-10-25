<template>
  <b-button
    variant="primary"
    class="jump-btn"
    :class="{
      'up': scrollDirection === -1,
      'down': scrollDirection === 1,
      hidden,
      isAtTop,
      isAtBottom,
    }"
    @click="clickHandler"
  >
    <i class="fas fa-angle-double-down" />
  </b-button>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, defineProps } from 'vue'

const SCROLL_PADDING_IN_PX = 100

const props = defineProps({
  elementId: {
    type: String,
    default: 'main',
  },
})

const lastScrollHeight = ref(undefined)
const scrollDirection = ref(1)
const timeoutId = ref(undefined)
const hidden = ref(true)
const isAtTop = ref(false)
const isAtBottom = ref(false)
const element = ref(null)

const ensureElement = () => {
  if (!element.value) {
    element.value = document.getElementById(props.elementId)
  }
  return element.value
}

const scrollHandler = () => {
  if (!lastScrollHeight.value) {
    lastScrollHeight.value = window.scrollY
    return
  }
  const el = ensureElement()
  if (!el) return

  window.clearTimeout(timeoutId.value)
  scrollDirection.value = Math.sign(window.scrollY - lastScrollHeight.value)
  hidden.value = false
  lastScrollHeight.value = window.scrollY
  timeoutId.value = window.setTimeout(() => { hidden.value = true }, 3000)

  const rect = el.getBoundingClientRect()
  isAtTop.value = rect.top > SCROLL_PADDING_IN_PX
  isAtBottom.value = window.innerHeight - rect.bottom > SCROLL_PADDING_IN_PX
}

const clickHandler = () => {
  const el = ensureElement()
  if (!el) return
  let position = window.scrollY - window.innerHeight / 2
  const rect = el.getBoundingClientRect()
  if (scrollDirection.value === 1) {
    position += rect.bottom
  } else {
    position += rect.top
  }
  window.scrollTo({ top: position, behavior: 'smooth' })
}

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('scroll', scrollHandler)
  }
})

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('scroll', scrollHandler)
    window.clearTimeout(timeoutId.value)
  }
})
</script>

<style lang="scss" scoped>
.jump-btn {
  position: fixed;
  bottom: 50px;
  right: 50px;
  border-radius: 50%;
  box-shadow: 0 0 5px 0 black;
  z-index: 1;
  transition: all .2s;
  &.up {
    transform: rotate(180deg);
  }
  &.hidden:not(:hover),
  &.up.isAtTop,
  &.down.isAtBottom {
    opacity: 0;
    pointer-events: none;
  }
}
</style>
