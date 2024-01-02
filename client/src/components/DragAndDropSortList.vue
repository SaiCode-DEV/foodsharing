<template>
  <div class="drag-drop-container">
    <div
      v-for="(item, key) in items"
      ref="drag-image"
      :key="key"
      @drop="reposition(key)"
      @dragover.prevent="onDragOver"
    >
      <slot
        name="item"
        :item="item"
        :events="{
          dragstart: onDragStart.bind(null, key),
          touchmove: onTouchMove.bind(null, key),
          touchstart: onTouchStart.bind(null, key),
          touchend: onTouchEnd.bind(null, key),
        }"
      />
    </div>
  </div>
</template>

<script>
export default {
  props: {
    value: {
      type: Array,
      required: true,
    },
  },
  data () {
    return {
      startPosition: null,
      hoverPosition: null,
    }
  },
  computed: {
    items: {
      get () {
        return this.value
      },
      set (value) {
        this.$emit('input', value)
      },
    },
  },
  methods: {
    onDragStart (startPosition, event) {
      const dragImage = this.$refs['drag-image'][startPosition]
      const { x, y } = dragImage.getBoundingClientRect()
      this.startPosition = startPosition

      event.dataTransfer.setDragImage(dragImage, event.clientX - x, event.clientY - y)
      // event.dataTransfer.effectAllowed = "copy"; //move
    },
    onDragOver (hoverPosition) {
      this.hoverPosition = hoverPosition
    },
    reposition (dropPosition) {
      // don't alter the props, instead...
      const items = this.items.concat()
      // console.log(`drop ${this.startPosition} onto ${dropPosition}`)
      // reposition
      const item = items.splice(this.startPosition, 1)[0]
      items.splice(dropPosition, 0, item)
      // ...emit the changes
      this.items = items
    },
    onTouchStart (startPosition, event) {
      const { pageY } = event.targetTouches[0]
      this.touchStartY = pageY
      this.startPosition = startPosition
    },
    onTouchMove (startPosition, event) {
      const { pageY } = event.targetTouches[0]
      const dragImage = this.$refs['drag-image'][startPosition]
      dragImage.style.top = `${pageY - this.touchStartY}px`
    },
    onTouchEnd (startPosition, event) {
      const dragImage = this.$refs['drag-image'][startPosition]
      dragImage.style.removeProperty('top')
      const { pageY, pageX } = event.changedTouches[0]
      const dropElement = document.elementFromPoint(pageX, pageY)
      const dropZone = this.findDropZone(dropElement)
      if (dropZone) {
        const dropPosition = this.getLilSiblingsCount(dropZone)
        this.reposition(dropPosition)
      }
    },
    findDropZone (element) {
      while (!element.parentElement.classList.contains('drag-drop-container')) {
        element = element.parentElement
        if (element.parentElement === null) return undefined
      }
      return element
    },
    getLilSiblingsCount (element) {
      let i = 0
      while ((element = element.previousSibling) !== null) i++
      return i
    },
  },
}
</script>
