<template>
  <div
    class="paginated-content"
    @wheel="onWheel"
    @touchstart="onTouchStart"
    @touchend="onTouchEnd"
  >
    <slot :current-page-items="currentPageItems" />
    <b-pagination
      v-if="shouldPaginate"
      v-model="currentPage"
      :total-rows="props.items.length"
      :per-page="props.pageSize"
      size="sm"
      class="m-0"
      align="fill"
    />
  </div>
</template>
<script setup>
import { ref, watch, computed, defineProps, defineEmits } from 'vue'

const MIN_SWIPE_DISTANCE = 60 // distance in px
const SCROLL_DISTANCE_THRESHOLD = 100_000 // picked by feel
const MIN_WHEEL_DISTANCE = 10 // px of sideways movement before paging starts
const HORIZONTAL_DOMINANCE = 2 // deltaX has to beat deltaY by this factor
const MAX_IDLE_TIME = 2_000 // ms, keeps the threshold meaningful after a pause
const GESTURE_GAP = 300 // ms without events, after that a new gesture starts

const props = defineProps({
  items: { type: Array, default: () => [] },
  pageSize: { type: Number, default: 5 },
  threshold: { type: Number, default: 0 },
})

const emit = defineEmits(['update:current-page-items'])

const currentPage = ref(1)

const totalPages = computed(() => {
  return Math.ceil(props.items.length / props.pageSize)
})

const shouldPaginate = computed(() => {
  return props.items.length > props.pageSize + props.threshold
})

const currentPageItems = computed(() => {
  if (!shouldPaginate.value) {
    return props.items
  }
  const start = (currentPage.value - 1) * props.pageSize
  return props.items.slice(start, start + props.pageSize)
})

watch(currentPageItems, items => emit('update:current-page-items', items), { immediate: true })

watch(totalPages, (totalPages) => {
  if (totalPages < currentPage.value && totalPages > 0) {
    currentPage.value = totalPages
  }
})

// Wheel and touch support to change pages:
const horizontalWheelDistance = ref(0)
const lastPageChangeTime = ref(0)
const lastWheelTime = ref(0)
const touchStart = ref({ x: 0, y: 0 })

function changePage (delta) {
  currentPage.value = Math.min(
    totalPages.value,
    Math.max(1, currentPage.value + delta),
  )
}

function onWheel (event) {
  const now = Date.now()
  // A gesture arrives as a stream of events. Once they stop coming the next one
  // starts a new gesture, otherwise sideways jitter during vertical scrolling
  // would add up over time until it pages.
  if (now - lastWheelTime.value > GESTURE_GAP) {
    horizontalWheelDistance.value = 0
  }
  lastWheelTime.value = now

  // A downward gesture on a trackpad carries a small sideways component, so
  // "more horizontal than vertical" is not enough to tell paging from scrolling.
  if (Math.abs(event.deltaX) < HORIZONTAL_DOMINANCE * Math.abs(event.deltaY)) return

  // A trackpad delivers a swipe as many small deltas, so the minimum distance
  // has to look at the accumulated movement, not at a single event.
  horizontalWheelDistance.value += event.deltaX
  if (Math.abs(horizontalWheelDistance.value) < MIN_WHEEL_DISTANCE) return

  // From here this is a deliberate sideways gesture. Swallow it, otherwise the
  // browser turns it into a back or forward navigation.
  event.preventDefault()

  // Paging again needs a longer stroke each time, so one swipe does not race
  // through several pages. The idle time is capped, otherwise the threshold
  // would fall to zero after a pause and a single event would page.
  const sinceLastChange = Math.min(now - lastPageChangeTime.value, MAX_IDLE_TIME)
  const threshold = SCROLL_DISTANCE_THRESHOLD / (sinceLastChange + 1)
  if (Math.abs(horizontalWheelDistance.value) < threshold) return

  horizontalWheelDistance.value = 0
  lastPageChangeTime.value = now
  changePage(Math.sign(event.deltaX))
}

function onTouchStart (event) {
  touchStart.value.x = event.changedTouches[0].screenX
  touchStart.value.y = event.changedTouches[0].screenY
}

function onTouchEnd (event) {
  const deltaX = event.changedTouches[0].screenX - touchStart.value.x
  const deltaY = event.changedTouches[0].screenY - touchStart.value.y
  if (Math.abs(deltaX) < Math.abs(deltaY) || Math.abs(deltaX) < MIN_SWIPE_DISTANCE) return
  changePage(-Math.sign(deltaX))
  event.preventDefault()
}
</script>
<style lang="scss">
.paginated-content > .list-group-item {
  &:last-child {
    border-radius: 0 0 var(--border-radius) var(--border-radius);
  }
  &:not(:last-child) {
    border-bottom: 0;
  }
}
.paginated-content > .pagination {
  .page-item .page-link {
    border-top-left-radius: 0;
    border-top-right-radius: 0;
  }
  .page-item {
    margin-bottom: 0;
  }
}
</style>
