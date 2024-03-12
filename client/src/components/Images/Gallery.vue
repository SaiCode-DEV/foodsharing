<template>
  <div
    v-if="images?.length"
    ref="gallery"
    class="gallery"
  >
    <div
      v-for="(image, i) of images"
      :key="i"
      class="d-inline-block"
      :draggable="allowReorder"
      :index="i"
      @dragstart="dragStart"
      @dragend="dragEnd"
      @dragover="dragOver"
    >
      <ResponsiveImage
        ref="images"
        :image="image"
        :height-in-px="heightInPx"
        :min-width-in-px="Math.round(heightInPx / maxAspectRatioFactor)"
        :max-width-in-px="Math.round(heightInPx * maxAspectRatioFactor)"
        :image-action-icon="allowDelete ? 'fas fa-trash-alt' : ''"
        :gallery-index="i"
        @image-action="$emit('delete-image', i)"
      />
    </div>
  </div>
</template>

<script>
import ResponsiveImage from './ResponsiveImage'

const goldenRatio = 1.618

export default {
  components: { ResponsiveImage },
  props: {
    images: { type: Array, default: () => [] },
    heightInPx: { type: Number, default: 150 },
    maxAspectRatioFactor: { type: Number, default: goldenRatio },
    allowDelete: { type: Boolean, default: false },
    allowReorder: { type: Boolean, default: false },
  },
  data () {
    return {
      selected: null,
    }
  },
  methods: {
    dragStart (evt) {
      evt.dataTransfer.effectAllowed = 'move'
      evt.dataTransfer.setData('text/plain', null)
      this.selected = evt.target
    },
    dragEnd () {
      this.selected = null
    },
    dragOver (evt) {
      const target = evt.target.parentNode
      if (this.selected === target || this.selected?.parentNode !== target.parentNode) {
        return
      }
      const parent = this.selected.parentNode
      const isBefore = this.isBefore(this.selected, target)
      const rects = {
        target: target.getBoundingClientRect(),
        selected: this.selected.getBoundingClientRect(),
      }
      if (isBefore && rects.target.left + rects.selected.width > evt.x) {
        parent.insertBefore(this.selected, target)
      }
      if (!isBefore && rects.selected.left + rects.target.width < evt.x) {
        parent.insertBefore(this.selected, target.nextSibling)
      }
    },
    isBefore (el1, el2) {
      for (let current = el1.previousSibling; current; current = current.previousSibling) {
        if (current === el2) return true
      }
      return false
    },
    getOrder () {
      return [...this.$refs.gallery.children].map(image => +image.getAttribute('index'))
    },
    getGallerySiblings (index) {
      if (!this.$refs?.images) return [null, null]
      let modalIds = this.$refs.images.map(image => image.uniqueId)
      const order = this.getOrder()
      modalIds = order.map(i => modalIds[i])
      index = order[index]
      return [modalIds[index - 1], modalIds[index + 1]]
    },
  },
}
</script>

<style lang="scss" scoped>

.gallery {
  white-space: nowrap;
  overflow-x: scroll;
  position: relative;
  width: 0px;
  min-width: 100%;
  margin-top: 1em;
  padding: 0.25em 0 0 0.25em;
  box-shadow: 0 0 4px 2px #0002 inset;
  border-radius: var(--border-radius);
  text-align: center;
  background-color: var(--fs-color-gray-100);
  > :last-child {
    margin-right: 0.25em;
  }
}
</style>
