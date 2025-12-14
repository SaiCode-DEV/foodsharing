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
const touchStart = ref({ x: 0, y: 0 })

function changePage (delta) {
  currentPage.value = Math.min(
    totalPages.value,
    Math.max(1, currentPage.value + delta),
  )
}

function onWheel (event) {
  if (Math.abs(event.deltaX) < Math.abs(event.deltaY)) return

  event.preventDefault()
  horizontalWheelDistance.value += event.deltaX
  const now = Date.now()

  // The following line might be unintuitive, but yields a nice result:
  // After a while of not scrolling, the threshold is small so that you change
  // page immediately after you start scrolling. Depending on how fast you scroll,
  // you can still go through many pages quickly, but a short scroll, even if
  // quite fast, will not result in scrolling through multiple pages.
  const threshold = SCROLL_DISTANCE_THRESHOLD / (now - lastPageChangeTime.value + 1)
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
