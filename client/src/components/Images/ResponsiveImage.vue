<template>
  <div
    ref="image"
    class="responsive-image"
    @click="$bvModal.show(uniqueId)"
  >
    <b-modal
      :id="uniqueId"
      hide-header
      hide-footer
      centered
      body-class="p-1"
      content-class="w-unset"
      dialog-class="fluid-size-dialog"
      @show="loadGalleySiblings"
    >
      <img
        :src="getUrl('image')"
        class="limited-size-image"
        @load="(evt) => evt.target.classList.add('loaded')"
      >
      <div
        v-if="gallerySiblings[0]"
        class="gallery-navigation prev"
        @click="swapToModal(0)"
      >
        <i class="fas fa-angle-left" />
      </div>
      <div
        v-if="gallerySiblings[1]"
        class="gallery-navigation next"
        @click="swapToModal(1)"
      >
        <i class="fas fa-angle-right" />
      </div>
    </b-modal>
    <div
      v-if="imageActionIcon"
      class="image-action"
      @click.stop="$emit('image-action')"
    >
      <i :class="imageActionIcon" />
    </div>
  </div>
</template>

<script>
import { v4 as uuidv4 } from 'uuid'
import { getImageMetadata } from '@/api/uploads'

export default {
  props: {
    image: { type: [String, Object], required: true },
    heightInPx: { type: Number, default: 150 },
    minWidthInPx: { type: Number, default: 75 },
    maxWidthInPx: { type: Number, default: 300 },
    imageActionIcon: { type: String, default: '' },
    galleryIndex: { type: Number, default: null },
  },
  data () {
    return {
      imageWidthInPx: 1,
      imageHeightInPx: 1,
      imageLoaded: false,
      uniqueId: uuidv4(),
      wasIntersecting: false,
      isIntersecting: false,
      gallerySiblings: [null, null],
    }
  },
  computed: {
    imageAspectRatio () {
      return this.imageWidthInPx / this.imageHeightInPx
    },
    widthInPx () {
      const optimalWidth = Math.round(this.heightInPx * this.imageAspectRatio)
      return Math.max(this.minWidthInPx, Math.min(this.maxWidthInPx, optimalWidth))
    },
    displayedAspectRatio () {
      return this.widthInPx / this.heightInPx
    },
    imageType () {
      if (this.image.startsWith('blob:')) return 'blob'
      if (this.image.startsWith('/api/uploads')) return 'api'
      return 'legacy'
    },
    displayUrl () {
      if (!this.imageLoaded) return 'none'
      let url = this.getUrl('medium')
      if (this.imageType === 'api') {
        url += `?w=${this.widthInPx}&h=${this.heightInPx}`
      }
      return `url(${url})`
    },
    backgroundSizeToCover () {
      if (this.imageType === 'api') return '100%'
      return Math.max(1, (this.imageAspectRatio / this.displayedAspectRatio)) * 100 + '%'
    },
  },
  watch: {
    async image () {
      if (this.isIntersecting) {
        this.updateAspectRatio()
      } else {
        this.wasIntersecting = false
        this.imageLoaded = false
      }
    },
  },
  mounted () {
    const observer = new IntersectionObserver(this.intersectionHandler)
    observer.observe(this.$refs.image)
  },
  methods: {
    getUrl (key) {
      if (this.imageType === 'legacy') {
        const keyMap = { image: '', medium: 'medium_', thumb: 'thumb_' }
        return `/images/wallpost/${keyMap[key]}${this.image}`
      }
      return this.image
    },
    async updateAspectRatio () {
      this.imageLoaded = false
      if (this.imageType !== 'api') {
        const imageObj = new Image()
        const loaded = new Promise(resolve => imageObj.addEventListener('load', resolve))
        imageObj.src = this.getUrl('medium')
        await loaded
        this.imageWidthInPx = imageObj.width
        this.imageHeightInPx = imageObj.height
      } else {
        const imageUuid = this.image.replace('/api/uploads/', '')
        const dimensions = await getImageMetadata(imageUuid)
        this.imageWidthInPx = dimensions.width
        this.imageHeightInPx = dimensions.height
      }
      this.imageLoaded = true
    },
    intersectionHandler (evt) {
      this.isIntersecting = evt[0].isIntersecting
      if (!this.wasIntersecting && this.isIntersecting) {
        this.wasIntersecting = true
        this.updateAspectRatio()
      }
    },
    swapToModal (siblingIndex) {
      if (this.gallerySiblings[siblingIndex]) {
        this.$bvModal.hide(this.uniqueId)
        this.$bvModal.show(this.gallerySiblings[siblingIndex])
      }
    },
    loadGalleySiblings () {
      if (this.galleryIndex === null) return
      this.gallerySiblings = this.$parent.getGallerySiblings(this.galleryIndex)
    },
  },
}
</script>

<style lang="scss" scoped>
.responsive-image {
  display: inline-block;
  background-image: v-bind('displayUrl');
  width: v-bind('widthInPx + "px"');
  height: v-bind('heightInPx + "px"');
  background-position: center;
  border: 1px solid var(--fs-border-default);
  border-radius: var(--border-radius);
  margin: 0.25em;
  background-size: v-bind('backgroundSizeToCover'); //like cover but animateable
  transition: background-size 0.3s ease;
  cursor: zoom-in;
  position: relative;
  overflow: hidden;
  &:hover {
    background-size: calc(v-bind('backgroundSizeToCover') * 1.1);
    .image-action {
      display: block;
    }
  }
}
.image-action {
  display: none;
  background-color: var(--fs-color-light);
  width: 1.5em;
  height: 1.5em;
  position: absolute;
  right: 0;
  border-radius: 3px 0 3px 50%;
  top: 0;
  cursor: pointer;
  border: 1px solid var(--fs-border-default);
  text-align: center;
  box-sizing: content-box;
  font-size: 1.2em;
  overflow: hidden;
  transition: padding 0.3s ease;
  &:hover {
    padding: .35em;
    color: red;
    font-size: 1.4em;
  }
}
@media (hover: none) {
  .image-action {
    display: block;
  }
}

::v-deep .fluid-size-dialog {
  width: fit-content !important;
  max-width: unset;
}

::v-deep .w-unset {
  width: unset;
}

.limited-size-image {
  max-width: calc(90vw - 5em);
  max-height: 90vh;
  min-width: 10em;
  min-height: 10em;
  background: url('/img/469.gif') no-repeat center center;
  &.loaded {
    min-width: unset;
    min-height: unset;
  }
}

.gallery-navigation {
  position: absolute;
  font-size: 5em;
  top: 0;
  bottom: 0;
  margin: auto;
  height: fit-content;
  color: var(--fs-color-gray-300);
  cursor: pointer;
  &:hover {
    color: var(--fs-color-light);
  }
  &.next {
    right: -0.75em;
  }
  &.prev {
    left: -0.75em;
  }
}
</style>
